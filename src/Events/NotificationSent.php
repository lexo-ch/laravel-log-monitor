<?php

namespace LEXO\LaravelLogMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Log\Events\MessageLogged;

class NotificationSent
{
    use Dispatchable;

    public function __construct(
        public MessageLogged $event,
        public string $channel,
        public mixed $response = null
    ) {
    }
}
