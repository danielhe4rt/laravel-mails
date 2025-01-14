<?php

namespace Vormkracht10\Mails\Managers;

use Illuminate\Support\Manager;
use Vormkracht10\Mails\Drivers\MailgunDriver;
use Vormkracht10\Mails\Drivers\PostmarkDriver;
use Vormkracht10\Mails\Drivers\ResendDriver;

class MailProviderManager extends Manager
{
    public function with($driver)
    {
        return $this->driver($driver);
    }

    protected function createPostmarkDriver(): PostmarkDriver
    {
        return new PostmarkDriver;
    }

    protected function createMailgunDriver(): MailgunDriver
    {
        return new MailgunDriver;
    }

    protected function createResendDriver(): ResendDriver
    {
        return new ResendDriver;
    }

    public function getDefaultDriver(): ?string
    {
        return null;
    }
}
