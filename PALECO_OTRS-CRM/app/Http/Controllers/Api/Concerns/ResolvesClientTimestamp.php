<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Provides client timestamp extraction and guardrail validations for offline-asynchronous syncing.
 */
trait ResolvesClientTimestamp
{
    /**
     * Resolves, parses, and validates an offline client timestamp from header or request body.
     * Enforces guardrails: valid ISO format, not future-dated (> 5 min), and not earlier than preceding lifecycle milestones.
     *
     * @throws ValidationException
     */
    protected function resolveClientTimestamp(Request $request, Ticket $ticket): ?Carbon
    {
        $raw = $request->header('X-Client-Timestamp') ?? $request->input('client_timestamp');

        if (! $raw) {
            return null;
        }

        try {
            $parsed = Carbon::parse($raw)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'client_timestamp' => 'The client timestamp format is invalid. Must be a valid ISO-8601 string.',
            ]);
        }

        if ($parsed->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'client_timestamp' => 'The client timestamp cannot be in the future.',
            ]);
        }

        // Determine chronological baseline to prevent inverted timelines (e.g. started before assigned):
        // 1. Accomplishment submission (ticket is in_progress) cannot precede work start.
        // 2. Work start (ticket is assigned) cannot precede the crew's assignment timestamp.
        // 3. Fallback to ticket creation timestamp.
        $assignedAt = $ticket->activeAssignment?->created_at 
            ?? $ticket->assignments()->whereNull('unassigned_at')->latest('id')->value('created_at');

        $baseline = match ($ticket->status) {
            TicketStatus::IN_PROGRESS => $ticket->started_at ?? $assignedAt ?? $ticket->reported_at ?? $ticket->created_at,
            TicketStatus::ASSIGNED => $assignedAt ?? $ticket->reported_at ?? $ticket->created_at,
            default => $ticket->reported_at ?? $ticket->created_at,
        };

        if ($baseline && $parsed->isBefore($baseline)) {
            // Safely clamp backward device clock drift to the preceding lifecycle baseline
            $parsed = $baseline->copy();
        }

        return $parsed;
    }
}

