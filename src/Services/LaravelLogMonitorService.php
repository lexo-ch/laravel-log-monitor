<?php

namespace LEXO\LaravelLogMonitor\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Context;
use Illuminate\Log\Events\MessageLogged;
use LEXO\LaravelLogMonitor\Mail\Notification;

class LaravelLogMonitorService
{
    protected array $config;
    protected array $context;
    protected array $llmContext;
    protected array $filtered_context = [];
    protected ?string $priority = null;

    public const MATTERMOST_PRIORITY_VALUES = [
        'urgent',
        'important'
    ];

    public function __construct()
    {
        $this->config = config('laravel-log-monitor');

        if (!$this->config['enabled']) {
            return;
        }

        $this->validateConfig();
    }

    public function handle(MessageLogged $event): void
    {
        if (!in_array(
            app()->environment(),
            $this->getArrayFromString($this->config['environments']))
        ) {
            return;
        }

        $this->context = $event->context ?? [];
        $this->llmContext = $this->context['llm'] ?? [];

        $level = strtolower($event->level);

        if (!($level === 'error' || (isset($this->llmContext['alert']) && $this->llmContext['alert'] === true))) {
            return;
        }

        $this->context = array_merge($this->context, Context::all());

        if (!empty($this->llmContext)) {
            $this->priority = $this->extractPriorityFromContext();
        }

        $this->filtered_context = $this->filterContext();

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

        $recipients = $this->getArrayFromString($emailConfig['recipients']);

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new Notification([
                    'level' => $event->level,
                    'message' => $event->message,
                    'context' => $this->context
                ]));
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

        $channel_id = $mattermostConfig['channel_id'] ?? null;

        if (empty($channel_id)) {
            return;
        }

        $post_data = [
            'channel_id' => $channel_id,
            'message' => $this->getMattermostMessage($event)
        ];

        if ($this->priority !== null) {
            $post_data['metadata'] = [
                'priority' => [
                    'priority' => $this->priority
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
            error_log("Failed to send to Mattermost channel {$channel_id}: " . $e->getMessage());

            if ($this->config['channels']['email']['send_as_backup']) {
                $this->sendEmailNotification($event);
            }
        }
    }

    protected function getMattermostMessage(MessageLogged $event): string
    {
        return View::make('laravel-log-monitor-mattermost-views::notification', [
            'level' => $event->level,
            'message' => $event->message,
            'context' => $this->filtered_context
        ])->render();
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
            if (empty($this->config['channels']['mattermost']['channel_id'])) {
                throw new \InvalidArgumentException('Mattermost channel_id is required when Mattermost is enabled');
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

    protected function extractPriorityFromContext(): ?string
    {
        $priority = null;

        if (isset($this->llmContext['priority']) && is_string($this->llmContext['priority'])) {
            $priorityValue = strtolower($this->llmContext['priority']);

            if (in_array($priorityValue, self::MATTERMOST_PRIORITY_VALUES)) {
                $priority = $priorityValue;
            }
        }

        return $priority;
    }

    protected function filterContext(): array
    {
        if (empty($this->context)) {
            return [];
        }

        $filteredContext = $this->context;

        if (isset($filteredContext['llm'])) {
            unset($filteredContext['llm']);
        }

        return $filteredContext;
    }
}
