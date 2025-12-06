<?php

use Illuminate\Log\Events\MessageLogged;
use LEXO\LaravelLogMonitor\Events\NotificationFailed;
use LEXO\LaravelLogMonitor\Events\NotificationSending;
use LEXO\LaravelLogMonitor\Events\NotificationSent;

describe('NotificationSending Event', function () {
    it('can be instantiated with required properties', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $event = new NotificationSending($messageLogged, 'mattermost', ['test' => 'data']);

        expect($event->event)->toBe($messageLogged)
            ->and($event->channel)->toBe('mattermost')
            ->and($event->payload)->toBe(['test' => 'data']);
    });

    it('can be dispatched', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $event = new NotificationSending($messageLogged, 'email', ['recipient' => 'test@example.com']);

        expect($event)->toHaveProperty('event')
            ->and($event)->toHaveProperty('channel')
            ->and($event)->toHaveProperty('payload');
    });

    it('stores email channel data correctly', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $payload = ['recipient' => 'test@example.com', 'data' => ['level' => 'error']];
        $event = new NotificationSending($messageLogged, 'email', $payload);

        expect($event->channel)->toBe('email')
            ->and($event->payload)->toBe($payload);
    });

    it('stores mattermost channel data correctly', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $payload = ['channel_id' => 'test-channel', 'message' => 'Test message'];
        $event = new NotificationSending($messageLogged, 'mattermost', $payload);

        expect($event->channel)->toBe('mattermost')
            ->and($event->payload)->toBe($payload);
    });
});

describe('NotificationSent Event', function () {
    it('can be instantiated with required properties', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $event = new NotificationSent($messageLogged, 'mattermost');

        expect($event->event)->toBe($messageLogged)
            ->and($event->channel)->toBe('mattermost')
            ->and($event->response)->toBeNull();
    });

    it('can store response data', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $response = ['id' => 'post-123'];
        $event = new NotificationSent($messageLogged, 'mattermost', $response);

        expect($event->response)->toBe($response);
    });

    it('can be instantiated for email channel', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $event = new NotificationSent($messageLogged, 'email', ['recipient' => 'test@example.com']);

        expect($event->channel)->toBe('email')
            ->and($event->response)->toBe(['recipient' => 'test@example.com']);
    });

    it('accepts any type of response data', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);

        $eventWithArray = new NotificationSent($messageLogged, 'mattermost', ['key' => 'value']);
        expect($eventWithArray->response)->toBeArray();

        $eventWithString = new NotificationSent($messageLogged, 'mattermost', 'response string');
        expect($eventWithString->response)->toBeString();

        $eventWithObject = new NotificationSent($messageLogged, 'mattermost', (object)['key' => 'value']);
        expect($eventWithObject->response)->toBeObject();
    });
});

describe('NotificationFailed Event', function () {
    it('can be instantiated with required properties', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $exception = new \Exception('Test exception');
        $event = new NotificationFailed($messageLogged, 'mattermost', $exception);

        expect($event->event)->toBe($messageLogged)
            ->and($event->channel)->toBe('mattermost')
            ->and($event->exception)->toBe($exception);
    });

    it('stores exception details correctly', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $exception = new \Exception('Connection timeout');
        $event = new NotificationFailed($messageLogged, 'mattermost', $exception);

        expect($event->exception->getMessage())->toBe('Connection timeout');
    });

    it('can be instantiated for email channel failure', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $exception = new \Exception('Mail sending failed');
        $event = new NotificationFailed($messageLogged, 'email', $exception);

        expect($event->channel)->toBe('email')
            ->and($event->exception->getMessage())->toBe('Mail sending failed');
    });

    it('preserves original exception type', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);
        $exception = new \RuntimeException('Runtime error');
        $event = new NotificationFailed($messageLogged, 'mattermost', $exception);

        expect($event->exception)->toBeInstanceOf(\RuntimeException::class);
    });
});

describe('Event Integration', function () {
    it('all events work with Laravel event dispatcher', function () {
        $messageLogged = new MessageLogged('error', 'Test error', []);

        $sendingEvent = new NotificationSending($messageLogged, 'mattermost', ['test' => 'data']);
        $sentEvent = new NotificationSent($messageLogged, 'mattermost', ['id' => 'post-123']);
        $failedEvent = new NotificationFailed($messageLogged, 'email', new \Exception('Test'));

        expect($sendingEvent)->toBeInstanceOf(NotificationSending::class)
            ->and($sentEvent)->toBeInstanceOf(NotificationSent::class)
            ->and($failedEvent)->toBeInstanceOf(NotificationFailed::class);
    });
});
