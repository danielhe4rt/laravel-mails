<?php

namespace Vormkracht10\Mails\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Vormkracht10\Mails\Actions\AttachUuid;

class AttachMailLogUuid
{
    public function handle(MessageSending $event): void
    {
        (new AttachUuid)($event);
    }
}
