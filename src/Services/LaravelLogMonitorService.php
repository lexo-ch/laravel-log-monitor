<?php

namespace LEXO\LaravelLogMonitor\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Log\Events\MessageLogged;
use LEXO\LaravelLogMonitor\Mail\Notification;

class LaravelLogMonitorService
{
    protected array $config;

    public const ALLOWED_LEVELS = [
        'error',
        'info'
    ];

    public function __construct()
    {
        $this->config = config('laravel-log-monitor');
        $this->validateConfig();
    }

    public function handle(MessageLogged $event): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        if (!in_array(
            app()->environment(),
            $this->getArrayFromString($this->config['environments']))
        ) {
            return;
        }

        $this->sendToMattermost($event);

        if (!$this->config['channels']['email']['send_as_backup']) {
            $this->sendEmailNotification($event);
        }
    }

    private function sendEmailNotification(MessageLogged $event): void
    {
        $emailConfig = $this->config['channels']['email'];
        
        if (!$emailConfig['enabled']) {
            return;
        }

        $level = strtolower($event->level);

        if (!in_array($level, self::ALLOWED_LEVELS)) {
            return;
        }

        if (!($emailConfig['notification_levels'][$level] ?? false)) {
            return;
        }

        $recipients = $this->getArrayFromString($emailConfig['recipients']);

        if (empty($recipients)) {
            return;
        }

        $emailData = [
            'level' => $event->level,
            'message' => $event->message,
            'context' => $event->context ?? []
        ];

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new Notification($emailData));
            } catch (\Exception $e) {
                error_log("Failed to send error notification email to {$recipient}: " . $e->getMessage());
            }
        }
    }

    protected function sendToMattermost(MessageLogged $event): void
    {
        $mattermostConfig = $this->config['channels']['mattermost'];
        
        if (!$mattermostConfig['enabled']) {
            return;
        }

        $level = strtolower($event->level);
        
        if (!in_array($level, self::ALLOWED_LEVELS)) {
            return;
        }

        $levelConfig = $mattermostConfig['notification_levels'][$level] ?? null;

        if (!$levelConfig || !$levelConfig['enabled']) {
            return;
        }

        $formatted_message = $this->formatMattermostMessage($event);

        $post_data = [
            'channel_id' => $levelConfig['channel_id'],
            'props' => [
                'attachments' => [
                    [
                        'title' => $formatted_message['title'],
                        'color' => match ($level) {
                            'error' => '#d24a4e',
                            default => '#2183fc',
                        },
                        'text' => $formatted_message['message'],
                        'footer' => config('app.name'),
                    ]
                ]
            ]
        ];

        if ($level === 'error') {
            $post_data['metadata'] = [
                'priority' => [
                    'priority' => 'urgent'
                ]
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $mattermostConfig['token'],
                'Content-Type' => 'application/json',
            ])->post($mattermostConfig['url'] . '/api/v4/posts', $post_data);
    
            if (!$response->successful()) {
                throw new \Exception('Mattermost API returned: ' . $response->status() . ' ' . $response->body());
            }
    
        } catch (\Exception $e) {
            error_log("Failed to send to Mattermost channel {$levelConfig['channel_id']}: " . $e->getMessage());

            if ($this->config['channels']['email']['send_as_backup']) {
                $this->sendEmailNotification($event);
            }
        }
    }

    protected function formatMattermostMessage(MessageLogged $event): array
    {
        $level = strtolower($event->level);
        $result['title'] = config('app.name'). " - New {$level} notification" . ' (' . now()->format('d.m.Y H:i:s') . ')';

        $result['message'] = $event->message;

        if (!empty($event->context)) {
            $result['message'] .= "\n**Context:**\n```json\n" . 
                json_encode($event->context, JSON_PRETTY_PRINT) . 
                "\n```";
        }

        return $result;
    }

    protected function getArrayFromString(string $string): array
    {
        return collect(explode(',', $string))
            ->map(fn($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    protected function validateConfig(): void
    {
        if ($this->config['channels']['mattermost']['enabled']) {
            if (empty($this->config['channels']['mattermost']['url'])) {
                throw new \InvalidArgumentException('Mattermost URL is required when Mattermost is enabled');
            }
            if (empty($this->config['channels']['mattermost']['token'])) {
                throw new \InvalidArgumentException('Mattermost token is required when Mattermost is enabled');
            }
            
            foreach (['error', 'warning'] as $level) {
                if (($this->config['channels']['mattermost']['notification_levels'][$level]['enable'] ?? false) 
                    && empty($this->config['channels']['mattermost']['notification_levels'][$level]['channel_id'])) {
                    throw new \InvalidArgumentException("Mattermost channel_id is required for {$level} level when enabled");
                }
            }
        }

        if ($this->config['channels']['email']['enabled']) {
            if (empty($this->config['channels']['email']['recipients'])) {
                throw new \InvalidArgumentException('Email recipients are required when email notifications are enabled');
            }
            
            foreach ($this->getArrayFromString($this->config['channels']['email']['recipients']) as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Invalid email address: {$email}");
                }
            }
        }
    }
}
