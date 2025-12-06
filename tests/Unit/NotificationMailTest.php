<?php

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use LEXO\LaravelLogMonitor\Mail\Notification;
use LEXO\LaravelLogMonitor\Providers\LaravelLogMonitorServiceProvider;

it('can be instantiated with email data', function () {
    $emailData = [
        'level' => 'error',
        'message' => 'Test error message',
        'context' => ['user_id' => 123]
    ];

    $notification = new Notification($emailData);

    expect($notification->emailData)->toBe($emailData);
});

it('has default package name and github url', function () {
    $emailData = ['level' => 'error', 'message' => 'Test'];
    $notification = new Notification($emailData);

    expect($notification->packageName)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_NAME)
        ->and($notification->packageGithubUrl)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_GITHUB_URL);
});

it('can override package name and github url', function () {
    $emailData = ['level' => 'error', 'message' => 'Test'];
    $notification = new Notification($emailData, 'Custom Package', 'https://custom.url');

    expect($notification->packageName)->toBe('Custom Package')
        ->and($notification->packageGithubUrl)->toBe('https://custom.url');
});

it('creates envelope with correct subject for error level', function () {
    $emailData = [
        'level' => 'error',
        'message' => 'Test error',
        'context' => []
    ];

    $notification = new Notification($emailData);
    $envelope = $notification->envelope();

    expect($envelope)->toBeInstanceOf(Envelope::class)
        ->and($envelope->subject)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_NAME . ' - ERROR');
});

it('creates envelope with correct subject for warning level', function () {
    $emailData = [
        'level' => 'warning',
        'message' => 'Test warning',
        'context' => []
    ];

    $notification = new Notification($emailData);
    $envelope = $notification->envelope();

    expect($envelope->subject)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_NAME . ' - WARNING');
});

it('creates envelope with correct subject for info level', function () {
    $emailData = [
        'level' => 'info',
        'message' => 'Test info',
        'context' => []
    ];

    $notification = new Notification($emailData);
    $envelope = $notification->envelope();

    expect($envelope->subject)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_NAME . ' - INFO');
});

it('creates envelope with correct subject for critical level', function () {
    $emailData = [
        'level' => 'critical',
        'message' => 'Test critical',
        'context' => []
    ];

    $notification = new Notification($emailData);
    $envelope = $notification->envelope();

    expect($envelope->subject)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_NAME . ' - CRITICAL');
});

it('uppercases the level in the subject', function () {
    $emailData = [
        'level' => 'emergency',
        'message' => 'Test',
        'context' => []
    ];

    $notification = new Notification($emailData);
    $envelope = $notification->envelope();

    expect($envelope->subject)->toBe(LaravelLogMonitorServiceProvider::PACKAGE_NAME . ' - EMERGENCY');
});

it('returns correct content configuration', function () {
    $emailData = ['level' => 'error', 'message' => 'Test', 'context' => []];
    $notification = new Notification($emailData);
    $content = $notification->content();

    expect($content)->toBeInstanceOf(Content::class)
        ->and($content->view)->toBe('laravel-log-monitor-email-views::notification');
});

it('stores email data as public property for view access', function () {
    $emailData = [
        'level' => 'error',
        'message' => 'Database connection failed',
        'context' => ['database' => 'mysql', 'host' => 'localhost']
    ];

    $notification = new Notification($emailData);

    expect($notification->emailData)->toBe($emailData)
        ->and($notification->emailData['level'])->toBe('error')
        ->and($notification->emailData['message'])->toBe('Database connection failed')
        ->and($notification->emailData['context'])->toBe(['database' => 'mysql', 'host' => 'localhost']);
});

it('uses Queueable trait', function () {
    $emailData = ['level' => 'error', 'message' => 'Test', 'context' => []];
    $notification = new Notification($emailData);

    expect(method_exists($notification, 'onQueue'))->toBeTrue()
        ->and(method_exists($notification, 'onConnection'))->toBeTrue();
});

it('uses SerializesModels trait', function () {
    $reflection = new ReflectionClass(Notification::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\Queue\SerializesModels');
});

it('handles empty context array', function () {
    $emailData = [
        'level' => 'error',
        'message' => 'Test error',
        'context' => []
    ];

    $notification = new Notification($emailData);

    expect($notification->emailData['context'])->toBeArray()
        ->and($notification->emailData['context'])->toBeEmpty();
});

it('handles complex context data', function () {
    $emailData = [
        'level' => 'error',
        'message' => 'Test error',
        'context' => [
            'user_id' => 123,
            'request' => [
                'method' => 'POST',
                'url' => '/api/users',
                'headers' => ['Authorization' => 'Bearer token']
            ],
            'exception' => [
                'class' => 'PDOException',
                'message' => 'Connection failed',
                'trace' => ['line 1', 'line 2']
            ]
        ]
    ];

    $notification = new Notification($emailData);

    expect($notification->emailData['context'])->toBe($emailData['context'])
        ->and($notification->emailData['context']['user_id'])->toBe(123)
        ->and($notification->emailData['context']['request']['method'])->toBe('POST')
        ->and($notification->emailData['context']['exception']['class'])->toBe('PDOException');
});
