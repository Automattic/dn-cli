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

    public function authenticate(): string
    {
        $port = self::REDIRECT_PORT;
        $redirectUri = "http://localhost:{$port}/callback";

        $authorizeUrl = self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'response_type' => 'token',
            'scope' => 'global',
        ]);

        $server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        if ($server === false) {
            throw new \RuntimeException("Failed to start callback server: {$errstr}");
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
<html><head><title>dn CLI - Authenticated</title></head>
<body>
<p>Authentication successful! You can close this window and return to the terminal.</p>
</body></html>
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
