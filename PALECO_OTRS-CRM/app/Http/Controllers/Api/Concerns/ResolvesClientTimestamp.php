<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Provides client timestamp extraction and guardrail validations for offline-asynchronous syncing.
 */
trait ResolvesClientTimestamp
{
    /**
     * Resolves, parses, and validates an offline client timestamp from header or request body.
     * Enforces guardrails: valid ISO format, not future-dated (> 5 min), and not earlier than ticket creation.
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
            $parsed = Carbon::parse($raw);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'client_timestamp' => 'The client timestamp format is invalid. Must be a valid ISO-8601 string.',
            ]);
        }

        if ($parsed->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'client_timestamp' => 'The client timestamp cannot be in the future.',
            ]);
        }

        $ticketCreated = $ticket->reported_at ?? $ticket->created_at;
        if ($ticketCreated && $parsed->isBefore($ticketCreated)) {
            throw ValidationException::withMessages([
                'client_timestamp' => 'The client timestamp cannot be earlier than ticket creation date.',
            ]);
        }

        return $parsed;
    }
}

