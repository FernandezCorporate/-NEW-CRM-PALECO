<?php

namespace Tests\Fakes;

use App\Services\Push\PushGateway;
use App\Services\Push\PushMessage;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Records the notifications that would be sent, instead of calling Firebase.
 */
class FakePushGateway implements PushGateway
{
    /** @var array<int, PushMessage> */
    public array $sent = [];

    public bool $failing = false;

    public function send(array $messages): array
    {
        if ($this->failing) {
            throw new RuntimeException('Firebase is unreachable.');
        }

        array_push($this->sent, ...$messages);

        return ['sent' => count($messages), 'failed' => 0, 'removed' => 0];
    }

    public function messages(): Collection
    {
        return collect($this->sent);
    }

    /**
     * Messages of one type, keyed loosely for assertions.
     */
    public function ofType(string $type): Collection
    {
        return $this->messages()->where('type', $type)->values();
    }
}
