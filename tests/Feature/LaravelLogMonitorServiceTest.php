<?php

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use LEXO\LaravelLogMonitor\Events\NotificationFailed;
use LEXO\LaravelLogMonitor\Events\NotificationSending;
use LEXO\LaravelLogMonitor\Events\NotificationSent;
use LEXO\LaravelLogMonitor\Mail\Notification;
use LEXO\LaravelLogMonitor\Services\LaravelLogMonitorService;

beforeEach(function () {
    config([
        'laravel-log-monitor.enabled' => true,
        'laravel-log-monitor.environments' => 'testing',
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel-id',
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => 'test@example.com',
        'laravel-log-monitor.channels.email.send_as_backup' => false,
    ]);

    View::shouldReceive('make')
        ->with('laravel-log-monitor-mattermost-views::notification', \Mockery::any())
        ->andReturn(\Mockery::mock([
            'render' => 'Mocked message content'
        ]));
});

it('can be instantiated', function () {
    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});

it('handles error level messages', function () {
    Event::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);
    Mail::fake();

    $event = new MessageLogged('error', 'Test error message', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationSending::class);
    Event::assertDispatched(NotificationSent::class);
});

it('ignores non-error messages without alert flag', function () {
    Event::fake();
    Http::fake();
    Mail::fake();

    $event = new MessageLogged('info', 'Test info message', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertNotDispatched(NotificationSending::class);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

it('handles messages with alert flag in llm context', function () {
    Event::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);
    Mail::fake();

    $event = new MessageLogged('info', 'Test info message', [
        'llm' => ['alert' => true]
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationSending::class);
    Event::assertDispatched(NotificationSent::class);
});

it('filters llm context from notification context', function () {
    Event::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'user_id' => 123,
        'llm' => ['alert' => true, 'priority' => 'urgent']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationSending::class, function ($e) {
        return !isset($e->payload['message']) || !str_contains($e->payload['message'], 'llm');
    });
});

it('sends to mattermost with priority metadata when priority is set', function () {
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['priority' => 'urgent']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return isset($body['metadata']['priority']['priority'])
            && $body['metadata']['priority']['priority'] === 'urgent';
    });
});

it('sends to mattermost with important priority', function () {
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['priority' => 'important']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return isset($body['metadata']['priority']['priority'])
            && $body['metadata']['priority']['priority'] === 'important';
    });
});

it('ignores invalid priority values', function () {
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['priority' => 'invalid-priority']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return !isset($body['metadata']);
    });
});

it('sends to default channel when no channel is specified', function () {
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return $body['channel_id'] === 'test-channel-id';
    });
});

it('sends to additional channel when specified in llm context', function () {
    config([
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => 'payment-channel-id'
        ]
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Payment error', [
        'llm' => ['channel' => 'payments']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return $body['channel_id'] === 'payment-channel-id';
    });
});

it('sends to multiple channels when array is specified in llm context', function () {
    config([
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'critical' => ['channel-1', 'channel-2']
        ]
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Critical error', [
        'llm' => ['channel' => 'critical']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSentCount(2);
});

it('sends to default channel when using default keyword', function () {
    config([
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => 'payment-channel-id'
        ]
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['channel' => 'default']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return $body['channel_id'] === 'test-channel-id';
    });
});

it('sends email notification when email is enabled and not as backup', function () {
    Mail::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Mail::assertSent(Notification::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

it('sends email to multiple recipients', function () {
    config([
        'laravel-log-monitor.channels.email.recipients' => 'test1@example.com, test2@example.com'
    ]);

    Mail::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Mail::assertSent(Notification::class, 2);
});

it('sends email as backup when mattermost fails and send_as_backup is true', function () {
    config([
        'laravel-log-monitor.channels.email.send_as_backup' => true
    ]);

    Mail::fake();
    Http::fake([
        '*' => Http::response('Error', 500)
    ]);
    Event::fake();

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationFailed::class);
    Mail::assertSent(Notification::class);
});

it('does not send email as backup when mattermost succeeds', function () {
    config([
        'laravel-log-monitor.channels.email.send_as_backup' => true
    ]);

    Mail::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Mail::assertNothingSent();
});

it('skips processing when not in configured environment', function () {
    config([
        'laravel-log-monitor.environments' => 'production'
    ]);

    Http::fake();
    Mail::fake();
    Event::fake();

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertNothingSent();
    Mail::assertNothingSent();
    Event::assertNotDispatched(NotificationSending::class);
});

it('processes when in one of multiple configured environments', function () {
    config([
        'laravel-log-monitor.environments' => 'production, testing, staging'
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);
    Event::fake();

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationSending::class);
});

it('fires NotificationFailed event when mattermost request fails', function () {
    Event::fake();
    Http::fake([
        '*' => Http::response('Error', 500)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationFailed::class, function ($e) {
        return $e->channel === 'mattermost';
    });
});

it('fires NotificationFailed event when email sending fails', function () {
    Event::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);
    Mail::shouldReceive('to')->andThrow(new \Exception('Mail sending failed'));

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Event::assertDispatched(NotificationFailed::class, function ($e) {
        return $e->channel === 'email';
    });
});

it('retries mattermost requests based on configuration', function () {
    config([
        'laravel-log-monitor.channels.mattermost.retry_times' => 3,
        'laravel-log-monitor.channels.mattermost.retry_delay' => 100
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push('Error', 500)
            ->push('Error', 500)
            ->push(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSentCount(3);
});

it('sends short messages directly without file upload', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 1000,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
    ];

    Http::fake([
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => 'Short message under limit'
    ]);

    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v4/posts');
    });
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v4/files');
    });
});

it('uploads long messages as file and sends summary post', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 50,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
        'file_upload_timeout' => 30,
    ];

    Http::fake([
        '*/api/v4/files' => Http::response([
            'file_infos' => [['id' => 'file-123']]
        ], 200),
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => str_repeat('A', 100)
    ]);

    expect($response->successful())->toBeTrue();

    // Should have called files endpoint for upload
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v4/files');
    });

    // Should have sent post with file_ids
    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v4/posts')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        return isset($body['file_ids']) && in_array('file-123', $body['file_ids']);
    });
});

it('falls back to truncated message when file upload fails', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 50,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
        'file_upload_timeout' => 30,
    ];

    Http::fake([
        '*/api/v4/files' => Http::response('Upload failed', 500),
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => str_repeat('A', 100)
    ]);

    expect($response->successful())->toBeTrue();

    // Should still send a post (truncated)
    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v4/posts')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        return str_contains($body['message'], '**Message truncated**');
    });
});

it('falls back to truncated message when file upload returns no file_infos', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 50,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
        'file_upload_timeout' => 30,
    ];

    Http::fake([
        '*/api/v4/files' => Http::response(['file_infos' => []], 200),
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => str_repeat('A', 100)
    ]);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v4/posts')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        return str_contains($body['message'], '**Message truncated**');
    });
});

it('preserves priority metadata when sending with file attachment', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 50,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
        'file_upload_timeout' => 30,
    ];

    Http::fake([
        '*/api/v4/files' => Http::response([
            'file_infos' => [['id' => 'file-123']]
        ], 200),
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => str_repeat('A', 100),
        'metadata' => [
            'priority' => ['priority' => 'urgent']
        ]
    ]);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v4/posts')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        return isset($body['file_ids'])
            && isset($body['metadata']['priority']['priority'])
            && $body['metadata']['priority']['priority'] === 'urgent';
    });
});

it('uses configured max_post_length from config', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 100,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
    ];

    Http::fake([
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    // Message exactly at limit should not trigger file upload
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => str_repeat('A', 100)
    ]);

    expect($response->successful())->toBeTrue();

    // Should only hit posts endpoint, not files
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v4/posts');
    });
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v4/files');
    });
});

it('uses default max_post_length when not configured', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        // max_post_length not set - should use default (16383)
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
    ];

    Http::fake([
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => 'Short message'
    ]);

    expect($response->successful())->toBeTrue();

    // Short message should go through without file upload
    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v4/posts');
    });
});

it('includes summary with file attached notice in summary message', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 50,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
        'file_upload_timeout' => 30,
    ];

    Http::fake([
        '*/api/v4/files' => Http::response([
            'file_infos' => [['id' => 'file-123']]
        ], 200),
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    $service = app(LaravelLogMonitorService::class);
    $response = $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => "Line 1\nLine 2\nLine 3\n" . str_repeat('A', 100)
    ]);

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v4/posts')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        return str_contains($body['message'], '**Full content attached as file**')
            && str_contains($body['message'], 'Line 1');
    });
});

it('sends email only when mattermost is disabled', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => false,
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => 'test@example.com',
        'laravel-log-monitor.channels.email.send_as_backup' => false,
    ]);

    Mail::fake();
    Http::fake();
    Event::fake();

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertNothingSent();
    Mail::assertSent(Notification::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

it('does not send email when email is disabled', function () {
    config([
        'laravel-log-monitor.channels.email.enabled' => false,
    ]);

    Mail::fake();
    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', []);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Mail::assertNothingSent();
    Http::assertSentCount(1);
});

it('sends nothing when channel name in llm context does not exist', function () {
    config([
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => 'payment-channel-id'
        ]
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);
    Mail::fake();
    Event::fake();

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['channel' => 'nonexistent-channel']
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertNothingSent();
    Event::assertNotDispatched(NotificationSending::class, function ($e) {
        return $e->channel === 'mattermost';
    });
});

it('truncates summary preview when it exceeds 500 characters', function () {
    $config = [
        'url' => 'https://mattermost.example.com',
        'token' => 'test-token',
        'max_post_length' => 50,
        'retry_times' => 1,
        'retry_delay' => 0,
        'timeout' => 10,
        'file_upload_timeout' => 30,
    ];

    Http::fake([
        '*/api/v4/files' => Http::response([
            'file_infos' => [['id' => 'file-123']]
        ], 200),
        '*/api/v4/posts' => Http::response(['id' => 'post-123'], 200)
    ]);

    // Create a message where the first 5 lines exceed 500 chars
    $longLine = str_repeat('X', 150);
    $message = "{$longLine}\n{$longLine}\n{$longLine}\n{$longLine}\n{$longLine}\n" . str_repeat('A', 100);

    $service = app(LaravelLogMonitorService::class);
    $service->sendMattermostPost($config, [
        'channel_id' => 'test-channel',
        'message' => $message
    ]);

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/api/v4/posts')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        $summaryPart = explode("\n\n---\n", $body['message'])[0];
        // Should be truncated to 500 chars (497 + '...')
        return mb_strlen($summaryPart) === 500 && str_ends_with($summaryPart, '...');
    });
});

it('sends to both default and additional channel when mixed array is specified', function () {
    config([
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => 'payment-channel-id'
        ]
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['channel' => ['default', 'payments']]
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    Http::assertSentCount(2);

    $sentChannelIds = [];
    Http::assertSent(function ($request) use (&$sentChannelIds) {
        $body = json_decode($request->body(), true);
        $sentChannelIds[] = $body['channel_id'];
        return true;
    });

    expect($sentChannelIds)->toContain('test-channel-id');
    expect($sentChannelIds)->toContain('payment-channel-id');
});

it('deduplicates channel ids when same channel is referenced multiple times', function () {
    config([
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'alias1' => 'same-channel-id',
            'alias2' => 'same-channel-id'
        ]
    ]);

    Http::fake([
        '*' => Http::response(['id' => 'post-123'], 200)
    ]);

    $event = new MessageLogged('error', 'Test error', [
        'llm' => ['channel' => ['alias1', 'alias2']]
    ]);
    $service = app(LaravelLogMonitorService::class);
    $service->handle($event);

    // Should only send once due to deduplication
    Http::assertSentCount(1);
});
