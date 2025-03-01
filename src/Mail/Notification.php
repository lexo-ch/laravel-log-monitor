<?php

namespace LEXO\LaravelLogMonitor\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use LEXO\LaravelLogMonitor\Providers\LaravelLogMonitorServiceProvider;

class Notification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $emailData,
        public string $packageName = LaravelLogMonitorServiceProvider::PACKAGE_NAME,
        public string $packageGithubUrl = LaravelLogMonitorServiceProvider::PACKAGE_GITHUB_URL
    ){
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: LaravelLogMonitorServiceProvider::PACKAGE_NAME . " - " . strtoupper($this->emailData['level'])
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'laravel-log-monitor-email-views::notification',
        );
    }
}