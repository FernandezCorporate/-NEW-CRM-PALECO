<?php

use App\Contracts\HasVisualPresentation;
use App\Enums\ComplaintSources;
use App\Enums\EndorsementStatus;
use App\Enums\NonModelActions;
use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;

test('all domain status and category enums implement HasVisualPresentation interface', function () {
    $enums = [
        TicketStatus::class,
        EndorsementStatus::class,
        TicketAccomplishmentStatus::class,
        NonModelActions::class,
        ComplaintSources::class,
    ];

    foreach ($enums as $enumClass) {
        $reflection = new ReflectionClass($enumClass);
        expect($reflection->implementsInterface(HasVisualPresentation::class))->toBeTrue(
            "{$enumClass} must implement HasVisualPresentation"
        );
    }
});

test('TicketStatus cases provide complete and valid visual presentations', function () {
    foreach (TicketStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->badgeClasses())->toBeString()->toContain('bg-')->toContain('text-')
            ->and($case->colorHex())->toMatch('/^#[0-9a-fA-F]{6}$/')
            ->and($case->chartClass())->toStartWith('bg-')
            ->and($case->dotClass())->toStartWith('bg-');
    }

    // Specific domain color assertions
    expect(TicketStatus::OPEN->colorHex())->toBe('#0284c7')
        ->and(TicketStatus::OPEN->chartClass())->toBe('bg-sky-500')
        ->and(TicketStatus::ASSIGNED->colorHex())->toBe('#9333ea')
        ->and(TicketStatus::ASSIGNED->chartClass())->toBe('bg-purple-500')
        ->and(TicketStatus::IN_PROGRESS->colorHex())->toBe('#d97706')
        ->and(TicketStatus::IN_PROGRESS->chartClass())->toBe('bg-amber-500')
        ->and(TicketStatus::PENDING_ENDORSEMENT->colorHex())->toBe('#ea580c')
        ->and(TicketStatus::PENDING_ENDORSEMENT->chartClass())->toBe('bg-orange-500')
        ->and(TicketStatus::RESOLVED->colorHex())->toBe('#059669')
        ->and(TicketStatus::RESOLVED->chartClass())->toBe('bg-emerald-500')
        ->and(TicketStatus::CLOSED->colorHex())->toBe('#64748b')
        ->and(TicketStatus::CLOSED->chartClass())->toBe('bg-slate-500');
});

test('EndorsementStatus cases provide complete and valid visual presentations', function () {
    foreach (EndorsementStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->badgeClasses())->toBeString()->toContain('bg-')->toContain('text-')
            ->and($case->colorHex())->toMatch('/^#[0-9a-fA-F]{6}$/')
            ->and($case->chartClass())->toStartWith('bg-')
            ->and($case->dotClass())->toStartWith('bg-');
    }

    expect(EndorsementStatus::PENDING->badgeClasses())->toBe('bg-amber-50 text-amber-700 border-amber-200')
        ->and(EndorsementStatus::APPROVED->badgeClasses())->toBe('bg-emerald-50 text-emerald-700 border-emerald-200')
        ->and(EndorsementStatus::REJECTED->badgeClasses())->toBe('bg-rose-50 text-rose-700 border-rose-200');
});

test('TicketAccomplishmentStatus cases provide complete and valid visual presentations', function () {
    foreach (TicketAccomplishmentStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->badgeClasses())->toBeString()->toContain('bg-')->toContain('text-')
            ->and($case->colorHex())->toMatch('/^#[0-9a-fA-F]{6}$/')
            ->and($case->chartClass())->toStartWith('bg-')
            ->and($case->dotClass())->toStartWith('bg-');
    }

    expect(TicketAccomplishmentStatus::PENDING->badgeClasses())->toBe('bg-amber-50 text-amber-700 border-amber-200')
        ->and(TicketAccomplishmentStatus::APPROVED->badgeClasses())->toBe('bg-emerald-50 text-emerald-700 border-emerald-200')
        ->and(TicketAccomplishmentStatus::REJECTED->badgeClasses())->toBe('bg-rose-50 text-rose-700 border-rose-200');
});

test('NonModelActions cases provide complete and valid visual presentations', function () {
    foreach (NonModelActions::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->badgeClasses())->toBeString()->toContain('bg-')->toContain('text-')
            ->and($case->colorHex())->toMatch('/^#[0-9a-fA-F]{6}$/')
            ->and($case->chartClass())->toStartWith('bg-')
            ->and($case->dotClass())->toStartWith('bg-');
    }

    expect(NonModelActions::LOGIN_SUCCESS->badgeClasses())->toBe('bg-emerald-50 text-emerald-700 border-emerald-200')
        ->and(NonModelActions::LOGIN_FAILED->badgeClasses())->toBe('bg-rose-50 text-rose-700 border-rose-200')
        ->and(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED->badgeClasses())->toBe('bg-amber-50 text-amber-700 border-amber-200');
});

test('ComplaintSources cases provide complete and valid visual presentations', function () {
    foreach (ComplaintSources::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->badgeClasses())->toBeString()->toContain('bg-')->toContain('text-')
            ->and($case->colorHex())->toMatch('/^#[0-9a-fA-F]{6}$/')
            ->and($case->chartClass())->toStartWith('bg-')
            ->and($case->dotClass())->toStartWith('bg-');
    }

    expect(ComplaintSources::PHONE_CALL->badgeClasses())->toBe('bg-blue-50 text-blue-700 border-blue-200')
        ->and(ComplaintSources::WALK_IN->badgeClasses())->toBe('bg-teal-50 text-teal-700 border-teal-200')
        ->and(ComplaintSources::SMS->badgeClasses())->toBe('bg-cyan-50 text-cyan-700 border-cyan-200')
        ->and(ComplaintSources::ONLINE->badgeClasses())->toBe('bg-violet-50 text-violet-700 border-violet-200')
        ->and(ComplaintSources::EMAIL->badgeClasses())->toBe('bg-indigo-50 text-indigo-700 border-indigo-200');
});

