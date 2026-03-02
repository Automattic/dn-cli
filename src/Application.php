<?php

declare(strict_types=1);

namespace DnCli;

use DnCli\Command\CartCommand;
use DnCli\Command\CheckCommand;
use DnCli\Command\CheckoutCommand;
use DnCli\Command\ConfigureCommand;
use DnCli\Command\ContactsSetCommand;
use DnCli\Command\DeleteCommand;
use DnCli\Command\DnsGetCommand;
use DnCli\Command\DnsSetCommand;
use DnCli\Command\InfoCommand;
use DnCli\Command\PrivacySetCommand;
use DnCli\Command\RegisterCommand;
use DnCli\Command\RenewCommand;
use DnCli\Command\RestoreCommand;
use DnCli\Command\SuggestCommand;
use DnCli\Command\TransferCommand;
use DnCli\Command\TransferlockCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('dn', '1.0.0');

        $this->add(new ConfigureCommand());
        $this->add(new CheckCommand());
        $this->add(new SuggestCommand());
        $this->add(new InfoCommand());
        $this->add(new RegisterCommand());
        $this->add(new RenewCommand());
        $this->add(new DeleteCommand());
        $this->add(new RestoreCommand());
        $this->add(new TransferCommand());
        $this->add(new DnsGetCommand());
        $this->add(new DnsSetCommand());
        $this->add(new ContactsSetCommand());
        $this->add(new PrivacySetCommand());
        $this->add(new TransferlockCommand());
        $this->add(new CartCommand());
        $this->add(new CheckoutCommand());
    }
}
