<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Push\PushGateway;
use App\Services\Push\PushMessage;
use Illuminate\Console\Command;

/**
 * Sends a test notification to every phone registered for an account, to check the Firebase setup end to end.
 */
class SendTestPush extends Command
{
    protected $signature = 'push:test {username : The mobile account to notify}';

    protected $description = 'Send a test push notification to the phones registered for an account';

    public function handle(PushGateway $gateway): int
    {
        // Same rule as real notifications: phones whose login expired are skipped
        $user = User::where('username', $this->argument('username'))
            ->with(['pushDevices' => fn ($q) => $q->withLiveLogin()])
            ->first();

        if (! $user) {
            $this->error('No account with that username.');

            return self::FAILURE;
        }

        if ($user->pushDevices->isEmpty()) {
            $this->warn("{$user->username} has no registered phone with a valid login. Sign in on the app first.");

            return self::FAILURE;
        }

        $messages = $user->pushDevices->map(fn ($device) => new PushMessage(
            token: $device->fcm_token,
            type: 'test',
            title: 'PALECO OTRS test notification',
            body: 'Push notifications are working on this phone.',
            userId: $user->id,
        ))->all();

        $result = $gateway->send($messages);

        $this->info("Sent: {$result['sent']}, failed: {$result['failed']}, removed (uninstalled app): {$result['removed']}");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
