<?php

declare(strict_types=1);

namespace DnCli\Auth;

use DnCli\Util\Browser;

class OAuthFlow
{
    private const CLIENT_ID = '134319';
    private const REDIRECT_PORT = 19851;
    private const AUTHORIZE_URL = 'https://public-api.wordpress.com/oauth2/authorize';
    private const TIMEOUT_SECONDS = 120;

    /** @var callable(string): void */
    private $browserOpener;

    /**
     * @param callable(string): void|null $browserOpener Custom browser opener for testing
     */
    public function __construct(?callable $browserOpener = null)
    {
        $this->browserOpener = $browserOpener ?? [Browser::class, 'open'];
    }

    /**
     * @param callable(string): void|null $onReady Called with the authorization URL before the browser opens
     */
    public function authenticate(?callable $onReady = null): string
    {
        $port = self::REDIRECT_PORT;
        $redirectUri = "http://localhost:{$port}/callback";

        $authorizeUrl = self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'response_type' => 'token',
            'scope' => 'global',
        ]);

        $server = stream_socket_server("tcp://[::0]:{$port}", $errno, $errstr);
        if ($server === false) {
            throw new \RuntimeException("Failed to start callback server: {$errstr}");
        }

        if ($onReady !== null) {
            ($onReady)($authorizeUrl);
        }

        ($this->browserOpener)($authorizeUrl);

        $token = $this->waitForToken($server);

        fclose($server);

        return $token;
    }

    /**
     * @param resource $server
     */
    private function waitForToken($server): string
    {
        $deadline = time() + self::TIMEOUT_SECONDS;
        stream_set_blocking($server, false);

        while (time() < $deadline) {
            $client = @stream_socket_accept($server, 1);
            if ($client === false) {
                continue;
            }

            stream_set_blocking($client, true);
            stream_set_timeout($client, 5);

            $request = (string) fread($client, 8192);
            $path = $this->extractPath($request);

            if (str_starts_with($path, '/callback')) {
                // Serve HTML that reads the fragment and sends it back as a query param
                $this->serveCallbackPage($client);
                fclose($client);
                continue;
            }

            if (str_starts_with($path, '/token')) {
                $token = $this->extractTokenFromQuery($path);
                $this->serveSuccessPage($client);
                fclose($client);

                if ($token !== null) {
                    return $token;
                }
            }

            fclose($client);
        }

        throw new \RuntimeException('OAuth authentication timed out. Please try again.');
    }

    private function serveCallbackPage($client): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><head><title>dn CLI - Authenticating...</title></head>
<body>
<p>Completing authentication...</p>
<script>
var hash = window.location.hash.substring(1);
var params = new URLSearchParams(hash);
var token = params.get('access_token');
if (token) {
    window.location.href = '/token?access_token=' + encodeURIComponent(token);
} else {
    document.body.innerHTML = '<p>Authentication failed: no token received.</p>';
}
</script>
</body></html>
HTML;

        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nConnection: close\r\n\r\n{$html}";
        fwrite($client, $response);
    }

    private function serveSuccessPage($client): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>dn CLI — Authenticated</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{
  background:#f6f7f7;
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
}
.card{
  background:#fff;
  border-radius:12px;
  padding:48px 52px 44px;
  text-align:center;
  width:min(400px,90vw);
  box-shadow:0 2px 8px rgba(0,0,0,.06),0 8px 32px rgba(0,0,0,.06);
  animation:rise .4s cubic-bezier(.16,1,.3,1) both;
}
@keyframes rise{
  from{opacity:0;transform:translateY(14px)}
  to{opacity:1;transform:translateY(0)}
}
.check{
  width:52px;height:52px;
  margin:0 auto 20px;
  position:relative;
}
.ring{
  width:52px;height:52px;border-radius:50%;
  background:#f0fdf8;
  border:2px solid #00d084;
  position:absolute;
  animation:pop .32s .18s cubic-bezier(.34,1.56,.64,1) both;
}
.tick{
  position:absolute;top:50%;left:50%;
  animation:popTick .26s .44s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes pop{
  from{opacity:0;transform:scale(.4)}
  to{opacity:1;transform:scale(1)}
}
@keyframes popTick{
  from{opacity:0;transform:translate(-50%,-50%) scale(.4)}
  to{opacity:1;transform:translate(-50%,-50%) scale(1)}
}
h1{
  font-size:20px;font-weight:600;
  color:#000;letter-spacing:-.01em;
  margin-bottom:8px;
  animation:appear .3s .5s ease both;
}
.sub{font-size:14px;color:#787c82;line-height:1.55;animation:appear .3s .56s ease both;}
.rule{height:1px;background:#f0f0f0;margin:24px 0;animation:appear .3s .54s ease both;}
.hint{font-size:13px;color:#aaa;animation:appear .3s .64s ease both;}
@keyframes appear{from{opacity:0}to{opacity:1}}
</style>
</head>
<body>
<div class="card">
  <div class="check">
    <div class="ring"></div>
    <svg class="tick" width="22" height="17" viewBox="0 0 22 17" fill="none">
      <path d="M2 8.5L8.5 15L20 2" stroke="#00d084" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>
  <h1>Authenticated</h1>
  <p class="sub">Your WordPress.com account<br>is now connected.</p>
  <div class="rule"></div>
  <p class="hint">You can close this window<br>and return to the terminal.</p>
</div>
</body>
</html>
HTML;

        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nConnection: close\r\n\r\n{$html}";
        fwrite($client, $response);
    }

    private function extractPath(string $request): string
    {
        if (preg_match('#^(?:GET|POST)\s+(/[^\s]*)#', $request, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function extractTokenFromQuery(string $path): ?string
    {
        $parts = parse_url($path);
        if (!isset($parts['query'])) {
            return null;
        }

        parse_str($parts['query'], $query);

        return isset($query['access_token']) && $query['access_token'] !== '' ? $query['access_token'] : null;
    }

}
