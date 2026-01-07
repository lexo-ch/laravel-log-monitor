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
    protected array $filteredContext = [];
    protected ?string $priority = null;

    public const MATTERMOST_PRIORITY_VALUES = [
        'urgent',
        'important'
    ];

    /**
     * Default maximum post character limit for Mattermost.
     */
    public const DEFAULT_MAX_POST_LENGTH = 16383;

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
        if (!$this->config['enabled']) {
            return;
        }

        if (!in_array(
            app()->environment(),
            $this->getArrayFromString($this->config['environments']))
        ) {
            return;
        }

        $this->context = $event->context ?? [];
        $this->llmContext = $this->context['llm'] ?? [];

        if (!$this->shouldMonitorEvent($event)) {
            return;
        }

        if (!empty($this->llmContext)) {
            $this->priority = $this->extractPriorityFromContext();
        }

        $this->filteredContext = $this->filterContext();

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
            $emailData = $this->buildEmailData($event);


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
        foreach ($channelIds as $channelId) {
            $postData = $this->buildMattermostPostData($channelId, $message);

            // Fire event before sending
            event(new NotificationSending($event, 'mattermost', $postData));

            try {
                $response = $this->sendMattermostPost($mattermostConfig, $postData);

                if (!$response->successful()) {
                    throw new \Exception('Mattermost API returned: ' . $response->status() . ' ' . $response->body());
                }

                // Fire event after successful send
                event(new NotificationSent($event, 'mattermost', $response));
            } catch (\Exception $e) {
                $hasFailure = true;

                // Fire event on failure
                event(new NotificationFailed($event, 'mattermost', $e));

                error_log("Failed to send to Mattermost channel {$channelId}: " . $e->getMessage());
            }
        }

        // Trigger email backup if any channel failed
        if ($hasFailure && $this->config['channels']['email']['send_as_backup']) {
            $this->sendEmailNotification($event);
        }
    }

    protected function getMattermostMessage(MessageLogged $event): string
    {
        return View::make('laravel-log-monitor-mattermost-views::notification', [
            'level' => $event->level,
            'message' => $event->message,
            'context' => $this->filteredContext
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
            $this->validateMattermostConfig();
        }

        if ($this->config['channels']['email']['enabled']) {
            $this->validateEmailConfig();
        }
    }

    protected function validateMattermostConfig(): void
    {
        $config = $this->config['channels']['mattermost'];

        if (empty($config['url'])) {
            throw new \InvalidArgumentException('Mattermost URL is required when Mattermost is enabled');
        }

        if (empty($config['token'])) {
            throw new \InvalidArgumentException('Mattermost token is required when Mattermost is enabled');
        }

        if (empty($config['channel_id'])) {
            throw new \InvalidArgumentException('Mattermost channel_id is required when Mattermost is enabled');
        }

        $this->validateAdditionalChannels($config['additional_channels'] ?? []);
    }

    protected function validateAdditionalChannels(array $additionalChannels): void
    {
        foreach ($additionalChannels as $name => $channelIds) {
            $channelIdsArray = is_array($channelIds) ? $channelIds : [$channelIds];

            if (empty($channelIdsArray)) {
                throw new \InvalidArgumentException("Mattermost additional channel '{$name}' has no channel_ids configured");
            }

            foreach ($channelIdsArray as $channelId) {
                if (empty($channelId)) {
                    throw new \InvalidArgumentException("Mattermost additional channel '{$name}' has empty channel_id");
                }
            }
        }
    }

    protected function validateEmailConfig(): void
    {
        $config = $this->config['channels']['email'];

        if (empty($config['recipients'])) {
            throw new \InvalidArgumentException('Email recipients are required when email notifications are enabled');
        }

        foreach ($this->getArrayFromString($config['recipients']) as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email address: {$email}");
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

    protected function shouldMonitorEvent(MessageLogged $event): bool
    {
        $level = strtolower($event->level);

        return $level === 'error'
            || (isset($this->llmContext['alert']) && $this->llmContext['alert'] === true);
    }

    protected function buildEmailData(MessageLogged $event): array
    {
        return [
            'level' => $event->level,
            'message' => $event->message,
            'context' => $this->filteredContext
        ];
    }

    protected function buildMattermostPostData(string $channelId, string $message): array
    {
        $postData = [
            'channel_id' => $channelId,
            'message' => $message
        ];

        if ($this->priority !== null) {
            $postData['metadata'] = [
                'priority' => [
                    'priority' => $this->priority
                ]
            ];
        }

        return $postData;
    }

    public function sendMattermostPost(array $config, array $postData)
    {
        $message = $postData['message'] ?? '';
        $maxPostLength = $config['max_post_length'] ?? self::DEFAULT_MAX_POST_LENGTH;

        if (mb_strlen($message) <= $maxPostLength) {
            return $this->sendRawMattermostPost($config, $postData);
        }

        return $this->sendMattermostAsFile($config, $postData);
    }

    /**
     * Send a raw post to Mattermost without any processing.
     */
    public function sendRawMattermostPost(array $config, array $postData)
    {
        return Http::retry(
            $config['retry_times'] ?? 3,
            $config['retry_delay'] ?? 100
        )
        ->timeout($config['timeout'] ?? 10)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $config['token'],
            'Content-Type' => 'application/json',
        ])
        ->post($config['url'] . '/api/v4/posts', $postData);
    }

    /**
     * Upload the message as a markdown file and send a summary post with the file attached.
     */
    protected function sendMattermostAsFile(array $config, array $postData)
    {
        $channelId = $postData['channel_id'];
        $message = $postData['message'];
        $filename = 'notification-' . now()->format('Y-m-d-His') . '.md';

        $uploadResponse = $this->uploadMattermostFile($config, $channelId, $filename, $message);

        if (!$uploadResponse->successful()) {
            error_log('Failed to upload Mattermost file: ' . $uploadResponse->status() . ' ' . $uploadResponse->body());

            // Fall back to truncated message
            $maxPostLength = $config['max_post_length'] ?? self::DEFAULT_MAX_POST_LENGTH;
            $postData['message'] = $this->truncateMattermostMessage($message, $maxPostLength);

            return $this->sendRawMattermostPost($config, $postData);
        }

        $fileInfos = $uploadResponse->json('file_infos', []);

        if (empty($fileInfos)) {
            error_log('No file_infos returned from Mattermost upload');

            $maxPostLength = $config['max_post_length'] ?? self::DEFAULT_MAX_POST_LENGTH;
            $postData['message'] = $this->truncateMattermostMessage($message, $maxPostLength);

            return $this->sendRawMattermostPost($config, $postData);
        }

        $fileId = $fileInfos[0]['id'];
        $maxPostLength = $config['max_post_length'] ?? self::DEFAULT_MAX_POST_LENGTH;
        $summaryMessage = $this->generateMattermostSummaryMessage($message, $maxPostLength);

        $postDataWithFile = [
            'channel_id' => $channelId,
            'message' => $summaryMessage,
            'file_ids' => [$fileId],
        ];

        if (isset($postData['metadata'])) {
            $postDataWithFile['metadata'] = $postData['metadata'];
        }

        return $this->sendRawMattermostPost($config, $postDataWithFile);
    }

    /**
     * Upload a file to Mattermost.
     */
    protected function uploadMattermostFile(array $config, string $channelId, string $filename, string $content)
    {
        return Http::retry(
            $config['retry_times'] ?? 3,
            $config['retry_delay'] ?? 100
        )
        ->timeout($config['file_upload_timeout'] ?? 30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $config['token'],
        ])
        ->attach('files', $content, $filename)
        ->post($config['url'] . '/api/v4/files', [
            'channel_id' => $channelId,
        ]);
    }

    /**
     * Generate a summary message when the full message is sent as a file.
     */
    protected function generateMattermostSummaryMessage(string $fullMessage, int $maxPostLength): string
    {
        $lines = explode("\n", $fullMessage);
        $headerLines = [];

        // Extract up to 5 non-empty lines for the preview
        foreach ($lines as $line) {
            if (count($headerLines) >= 5) {
                break;
            }

            $trimmedLine = trim($line);

            if ($trimmedLine !== '') {
                $headerLines[] = $trimmedLine;
            }
        }

        $preview = implode("\n", $headerLines);

        if (mb_strlen($preview) > 500) {
            $preview = mb_substr($preview, 0, 497) . '...';
        }

        return $preview . "\n\n---\n**Full content attached as file** (message exceeded " . number_format($maxPostLength) . ' character limit)';
    }

    /**
     * Truncate a message to fit within the Mattermost character limit.
     */
    protected function truncateMattermostMessage(string $message, int $maxPostLength): string
    {
        $suffix = "\n\n---\n**Message truncated** (exceeded " . number_format($maxPostLength) . ' character limit)';
        $maxLength = $maxPostLength - mb_strlen($suffix);

        return mb_substr($message, 0, $maxLength) . $suffix;
    }
}