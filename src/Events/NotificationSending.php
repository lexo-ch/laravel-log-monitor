<?php

namespace LEXO\LaravelLogMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Log\Events\MessageLogged;

class NotificationSending
{
    use Dispatchable;

    public function __construct(
        public MessageLogged $event,
        public string $channel,
        public array $payload
    ) {
    }
}
