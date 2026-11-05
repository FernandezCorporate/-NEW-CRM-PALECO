<?php

namespace App\Services\Push;

/**
 * Delivers notifications to phones. The app uses Firebase; tests swap in a fake that only records them.
 */
interface PushGateway
{
    /**
     * @param  array<int, PushMessage>  $messages
     * @return array{sent: int, failed: int, removed: int}
     */
    public function send(array $messages): array;
}
