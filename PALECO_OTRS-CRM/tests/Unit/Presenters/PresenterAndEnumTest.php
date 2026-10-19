<?php

use App\Enums\ComplaintSources;
use App\Enums\EndorsementStatus;
use App\Enums\NonModelActions;
use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Presenters\SvgDonutPresenter;

test('SvgDonutPresenter handles zero total safely', function () {
    $slices = [
        ['key' => 'open', 'label' => 'Open', 'total' => 0],
        ['key' => 'resolved', 'label' => 'Resolved', 'total' => 0],
    ];

    $result = SvgDonutPresenter::computeGeometry($slices, 0);

    expect($result)->toHaveCount(2);
    expect($result[0]['path_d'])->toBe('');
    expect($result[0]['width_percent'])->toBe(0.0);
});

test('SvgDonutPresenter computes full 360 circle arc when single status is 100 percent', function () {
    $slices = [
        ['key' => 'open', 'label' => 'Open', 'total' => 15],
    ];

    $result = SvgDonutPresenter::computeGeometry($slices, 15);

    expect($result[0]['width_percent'])->toBe(100.0);
    expect($result[0]['path_d'])->toContain('A 86 86');
    expect($result[0]['path_d'])->toContain('A 50 50');
});

test('SvgDonutPresenter computes proportional slices and coordinates for mixed totals', function () {
    $slices = [
        ['key' => 'open', 'label' => 'Open', 'total' => 50],
        ['key' => 'resolved', 'label' => 'Resolved', 'total' => 50],
    ];

    $result = SvgDonutPresenter::computeGeometry($slices, 100);

    expect($result[0]['width_percent'])->toBe(50.0);
    expect($result[1]['width_percent'])->toBe(50.0);
    expect($result[0]['path_d'])->not->toBeEmpty();
    expect($result[1]['path_d'])->not->toBeEmpty();
    expect($result[0]['start_percent'])->toBe(0.0);
    expect($result[0]['end_percent'])->toBe(50.0);
    expect($result[1]['start_percent'])->toBe(50.0);
    expect($result[1]['end_percent'])->toBe(100.0);
});

test('TicketStatus enum supplies visual labels, badge classes, and hex colors', function () {
    expect(TicketStatus::cases())->toHaveCount(8);

    foreach (TicketStatus::cases() as $case) {
        expect($case->label())->not->toBeEmpty();
        expect($case->badgeClasses())->toContain('border-');
        expect($case->colorHex())->toStartWith('#');
    }

    expect(TicketStatus::OPEN->label())->toBe('Open');
    expect(TicketStatus::IN_PROGRESS->label())->toBe('In Progress');
    expect(TicketStatus::RESOLVED->label())->toBe('Resolved');
    expect(TicketStatus::CLOSED->label())->toBe('Closed');
});

test('ComplaintSources enum maps valid input channels', function () {
    $expected = ['phone_call', 'walk_in', 'sms_message', 'online_platforms', 'email'];

    $actualValues = array_map(fn ($case) => $case->value, ComplaintSources::cases());
    expect($actualValues)->toEqualCanonicalizing($expected);

    expect(ComplaintSources::PHONE_CALL->label())->toBe('Phone call');
    expect(ComplaintSources::WALK_IN->label())->toBe('Walk-in');
});

test('EndorsementStatus and TicketAccomplishmentStatus define consistent workflows', function () {
    expect(EndorsementStatus::cases())->toHaveCount(3);
    expect(EndorsementStatus::PENDING->value)->toBe('pending');
    expect(EndorsementStatus::APPROVED->value)->toBe('approved');
    expect(EndorsementStatus::REJECTED->value)->toBe('rejected');

    expect(TicketAccomplishmentStatus::cases())->toHaveCount(3);
    expect(TicketAccomplishmentStatus::PENDING->value)->toBe('pending');
    expect(TicketAccomplishmentStatus::APPROVED->value)->toBe('approved');
    expect(TicketAccomplishmentStatus::REJECTED->value)->toBe('rejected');
});

test('NonModelActions enum enumerates authentication audit categories', function () {
    $cases = array_map(fn ($case) => $case->value, NonModelActions::cases());

    expect($cases)->toContain('login_success');
    expect($cases)->toContain('login_failed');
    expect($cases)->toContain('login_pass_account_deactivated');
});
