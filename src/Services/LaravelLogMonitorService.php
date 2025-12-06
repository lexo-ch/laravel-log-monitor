<?php

namespace LEXO\LaravelLogMonitor\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Log\Events\MessageLogged;
use LEXO\LaravelLogMonitor\Mail\Notification;
use LEXO\LaravelLogMonitor\Events\NotificationSending;
use LEXO\LaravelLogMonitor\Events\NotificationSent;
use LEXO\LaravelLogMonitor\Events\NotificationFailed;

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
            $emailData = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $this->filtered_context
            ];

            // Fire event before sending
            event(new NotificationSending($event, 'email', ['recipient' => $recipient, 'data' => $emailData]));

            try {
                Mail::to($recipient)->send(new Notification($emailData));

                // Fire event after successful send
                event(new NotificationSent($event, 'email', ['recipient' => $recipient]));
            } catch (\Exception $e) {
                // Fire event on failure
                event(new NotificationFailed($event, 'email', $e));

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

        // Determine which channels to use (can be multiple)
        $channelIds = $this->resolveChannelId($mattermostConfig);

        if (empty($channelIds)) {
            return;
        }

        $message = $this->getMattermostMessage($event);
        $hasFailure = false;

        // Send to each channel
        foreach ($channelIds as $channel_id) {
            $post_data = [
                'channel_id' => $channel_id,
                'message' => $message
            ];

            if ($this->priority !== null) {
                $post_data['metadata'] = [
                    'priority' => [
                        'priority' => $this->priority
                    ]
                ];
            }

            // Fire event before sending
            event(new NotificationSending($event, 'mattermost', $post_data));

            try {
                $response = Http::retry(
                    $mattermostConfig['retry_times'] ?? 3,
                    $mattermostConfig['retry_delay'] ?? 100
                )
                ->timeout($mattermostConfig['timeout'] ?? 10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $mattermostConfig['token'],
                    'Content-Type' => 'application/json',
                ])->post($mattermostConfig['url'] . '/api/v4/posts', $post_data);

                if (!$response->successful()) {
                    throw new \Exception('Mattermost API returned: ' . $response->status() . ' ' . $response->body());
                }

                // Fire event after successful send
                event(new NotificationSent($event, 'mattermost', $response));
            } catch (\Exception $e) {
                $hasFailure = true;

                // Fire event on failure
                event(new NotificationFailed($event, 'mattermost', $e));

                error_log("Failed to send to Mattermost channel {$channel_id}: " . $e->getMessage());
            }
        }

        // Trigger email backup only if ALL channels failed
        if ($hasFailure && count($channelIds) > 0) {
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

            // Validate additional channels if configured
            $additionalChannels = $this->config['channels']['mattermost']['additional_channels'] ?? [];
            foreach ($additionalChannels as $name => $channelIds) {
                // Support both string (single channel) and array (multiple channels)
                $channelIdsArray = is_array($channelIds) ? $channelIds : [$channelIds];

                foreach ($channelIdsArray as $channelId) {
                    if (empty($channelId)) {
                        throw new \InvalidArgumentException("Mattermost additional channel '{$name}' has empty channel_id");
                    }
                }

                if (empty($channelIdsArray)) {
                    throw new \InvalidArgumentException("Mattermost additional channel '{$name}' has no channel_ids configured");
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

    protected function resolveChannelId(array $mattermostConfig): array
    {
        // Check if a specific channel is requested via llm context
        $requestedChannels = $this->llmContext['channel'] ?? null;

        if ($requestedChannels === null) {
            // No specific channel requested, use default
            $defaultChannel = $mattermostConfig['channel_id'] ?? null;
            return $defaultChannel ? [$defaultChannel] : [];
        }

        // Normalize to array (supports both string and array of channel names)
        $channelNames = is_array($requestedChannels) ? $requestedChannels : [$requestedChannels];
        $resolvedChannelIds = [];

        foreach ($channelNames as $channelName) {
            // Special case: 'default' keyword uses the default channel_id
            if ($channelName === 'default') {
                $defaultChannel = $mattermostConfig['channel_id'] ?? null;
                if ($defaultChannel) {
                    $resolvedChannelIds[] = $defaultChannel;
                }
                continue;
            }

            // Look up in additional_channels
            if (isset($mattermostConfig['additional_channels'][$channelName])) {
                $channelIds = $mattermostConfig['additional_channels'][$channelName];

                // The value can be a string (single channel) or array (multiple channels)
                if (is_array($channelIds)) {
                    $resolvedChannelIds = array_merge($resolvedChannelIds, $channelIds);
                } else {
                    $resolvedChannelIds[] = $channelIds;
                }
            }
        }

        // Remove duplicates and reindex
        return array_values(array_unique($resolvedChannelIds));
    }
}