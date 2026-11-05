<?php

namespace App\Services\Push;

/**
 * One notification for one phone, in the shape agreed with the mobile app's inbox.
 */
class PushMessage
{
    public const CHANNEL_DISPATCH = 'dispatch';

    public const CHANNEL_UPDATES = 'updates';

    /**
     * @param  array<string, string|null>  $data  extra inbox fields, e.g. ticket_id and ticket_number
     */
    public function __construct(
        public readonly string $token,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly string $channel = self::CHANNEL_UPDATES,
        public readonly bool $urgent = false,
        public readonly ?string $tag = null,
        public readonly ?string $userId = null,
    ) {}

    /**
     * The message body expected by the FCM HTTP v1 "messages:send" endpoint.
     */
    public function toFcm(): array
    {
        // FCM data values must all be strings; the inbox reads the same fields from every notification
        $data = array_map(fn ($value) => (string) ($value ?? ''), [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'sent_at' => now()->toIso8601String(),
            ...$this->data,
        ]);

        $android = [
            'priority' => $this->urgent ? 'HIGH' : 'NORMAL',
            'notification' => array_filter([
                'channel_id' => $this->channel,
                // Notifications about the same ticket replace each other in the phone's tray
                'tag' => $this->tag,
            ]),
        ];

        // No collapse_key: Firebase ignores it for messages that carry a notification,
        // and it must never imply that an older alert (e.g. a new dispatch) may be dropped

        return [
            'token' => $this->token,
            'notification' => ['title' => $this->title, 'body' => $this->body],
            'data' => $data,
            'android' => $android,
        ];
    }
}
