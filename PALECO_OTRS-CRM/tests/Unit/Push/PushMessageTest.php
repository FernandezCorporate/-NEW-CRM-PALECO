<?php

use App\Services\Push\PushMessage;

function dispatchMessage(array $overrides = []): PushMessage
{
    return new PushMessage(...array_merge([
        'token' => 'phone-token-abcdefghijklmnop',
        'type' => 'ticket_assigned',
        'title' => 'New dispatch',
        'body' => 'TKT-1 · Power Interruption',
        'data' => ['ticket_id' => 'abc', 'ticket_number' => 'TKT-1'],
        'channel' => PushMessage::CHANNEL_DISPATCH,
        'urgent' => true,
        'tag' => 'ticket-abc',
    ], $overrides));
}

test('the message never sends a collapse key, so an older alert is never meant to be replaced', function () {
    $android = dispatchMessage()->toFcm()['android'];

    expect($android)->not->toHaveKey('collapse_key');
});

test('the tray tag groups notifications about the same ticket', function () {
    expect(dispatchMessage()->toFcm()['android']['notification']['tag'])->toBe('ticket-abc');
});

test('a message without a ticket has no tag', function () {
    $notification = dispatchMessage(['tag' => null])->toFcm()['android']['notification'];

    expect($notification)->not->toHaveKey('tag')
        ->and($notification['channel_id'])->toBe(PushMessage::CHANNEL_DISPATCH);
});

test('urgent messages use high priority and normal ones normal priority', function () {
    expect(dispatchMessage()->toFcm()['android']['priority'])->toBe('HIGH')
        ->and(dispatchMessage(['urgent' => false])->toFcm()['android']['priority'])->toBe('NORMAL');
});

test('every data value is a string, including empty ticket fields', function () {
    $data = dispatchMessage(['data' => ['ticket_id' => null, 'ticket_number' => '']])->toFcm()['data'];

    expect($data)->toHaveKeys(['type', 'title', 'body', 'sent_at', 'ticket_id', 'ticket_number'])
        ->and(array_filter($data, fn ($value) => ! is_string($value)))->toBeEmpty()
        ->and($data['ticket_id'])->toBe('');
});

test('the notification part shows the same title and body as the inbox data', function () {
    $fcm = dispatchMessage()->toFcm();

    expect($fcm['notification'])->toBe(['title' => 'New dispatch', 'body' => 'TKT-1 · Power Interruption'])
        ->and($fcm['data']['title'])->toBe('New dispatch')
        ->and($fcm['token'])->toBe('phone-token-abcdefghijklmnop');
});
