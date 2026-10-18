<?php

namespace App\Services\ActivityLogs;

use App\Enums\NonModelActions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Manages the retrieval, filtering, and presentation formatting of system audit activity logs.
 */
class ActivityLogService
{
    // --- QUERY & AGGREGATION METHODS ---

    /**
     * Retrieves dashboard data containing paginated activity logs and server-provided dropdown options.
     */
    public function getMonitoringDashboard(array $filters): array
    {
        return [
            'logs' => $this->getLogEntries($filters),
            'categories' => $this->getCategoryOptions(),
            'eventTypes' => $this->getEventTypeOptions(),
        ];
    }

    /**
     * Returns strictly defined category filter options for the system monitoring dashboard.
     */
    public function getCategoryOptions(): array
    {
        return [
            'all' => 'All Categories',
            'auth' => 'Authentication',
            'Tickets' => 'Tickets',
            'Users' => 'Users',
            'Teams' => 'Teams',
            'Department' => 'Departments',
            'TicketCategory' => 'Ticket Categories',
        ];
    }

    /**
     * Returns strictly defined event type filter options.
     */
    public function getEventTypeOptions(): array
    {
        return [
            'all' => 'All Events',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'other' => 'Other',
        ];
    }

    /**
     * Retrieves a paginated list of system activity logs filtered by search terms, category, and action event type.
     */
    public function getLogEntries(array $filters): LengthAwarePaginator
    {
        $query = Activity::with(['causer.role', 'subject'])->latest();

        $nonModelLogNames = array_map(fn ($case) => $case->value, NonModelActions::cases());
        $crudEvents = ['created', 'create', 'updated', 'update', 'deleted', 'delete', 'restored', 'restore'];

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('log_name', 'like', $search)
                    ->orWhere('event', 'like', $search)
                    ->orWhere('properties', 'like', $search);
            });
        }

        $category = $filters['category'] ?? null;
        if (! empty($category) && ! in_array($category, ['all', 'All Categories'], true)) {
            if (in_array($category, ['auth', 'login_success', 'login', 'authentication'], true)) {
                $query->where(function ($q) use ($nonModelLogNames) {
                    $q->whereIn('log_name', $nonModelLogNames)
                        ->orWhere('event', 'login');
                });
            } else {
                $query->where('log_name', $category);
            }
        }

        $eventType = $filters['event_type'] ?? $filters['action'] ?? $filters['event'] ?? null;
        if (! empty($eventType) && $eventType !== 'all') {
            if ($eventType === 'create' || $eventType === 'created') {
                $query->whereIn('event', ['created', 'create'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            } elseif ($eventType === 'update' || $eventType === 'updated') {
                $query->whereIn('event', ['updated', 'update', 'password_reset', 'roster_updated', 'account_unlocked'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            } elseif ($eventType === 'delete' || $eventType === 'deleted') {
                $query->whereIn('event', ['deleted', 'delete'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            } elseif ($eventType === 'other' || $eventType === 'non_crud') {
                $query->where(function ($q) use ($nonModelLogNames, $crudEvents) {
                    $q->whereIn('log_name', $nonModelLogNames)
                        ->orWhere('event', 'login')
                        ->orWhereNotIn('event', $crudEvents);
                });
            } elseif ($eventType === 'crud') {
                $query->whereIn('event', ['created', 'updated', 'deleted', 'restored'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            }
        }

        $paginator = $query->paginate(15)->withQueryString();

        $paginator->getCollection()->transform(function ($log) {
            $log->formatted_date = $log->created_at->format('M d, Y');
            $log->formatted_time = $log->created_at->format('h:i A');
            $log->formatted_datetime = $log->created_at->format('M d, Y h:i A');

            $authAction = NonModelActions::tryFrom($log->log_name);
            if ($authAction) {
                $log->module_label = $authAction->label();
                $log->module_badge_class = $authAction->badgeClasses();
            } elseif (str_starts_with($log->log_name, 'login_')) {
                $log->module_label = 'Authentication';
                $log->module_badge_class = 'bg-indigo-50 border-indigo-200 text-indigo-700';
            } else {
                $log->module_label = match ($log->log_name) {
                    'Tickets' => 'Tickets',
                    'Users' => 'Users',
                    'Teams' => 'Teams',
                    'Department' => 'Departments',
                    'TicketCategory' => 'Categories',
                    default => $log->log_name,
                };
                $log->module_badge_class = match ($log->log_name) {
                    'Tickets' => 'bg-blue-50 border-blue-200 text-blue-700',
                    'Users' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
                    'Teams' => 'bg-purple-50 border-purple-200 text-purple-700',
                    'Department' => 'bg-amber-50 border-amber-200 text-amber-700',
                    'TicketCategory' => 'bg-teal-50 border-teal-200 text-teal-700',
                    default => 'bg-slate-100 border-slate-200 text-slate-700',
                };
            }

            $actorName = $log->causer ? ($log->causer->first_name . ' ' . $log->causer->last_name) : ($log->properties['full_name'] ?? $log->properties['username'] ?? 'System');
            $actorRole = $log->causer ? $log->causer->role->role_name : ($log->properties['role'] ?? 'Unknown');
            $log->actor_name = $actorName;
            $log->actor_role = $actorRole;
            $log->actor_initials = strtoupper(substr(trim($actorName), 0, 2)) ?: 'SY';

            return $log;
        });

        return $paginator;
    }

    // --- DETAIL & DIFF PRESENTATION METHODS ---

    /**
     * Prepares normalized detail data for a single activity log entry.
     */
    public function getActivityDetail(Activity $activity): array
    {
        $activity->loadMissing(['causer.role', 'subject']);

        $subject = $activity->subject;
        $isSubjectDeleted = false;
        if (! $subject && $activity->subject_type && $activity->subject_id && class_exists($activity->subject_type)) {
            if (in_array(SoftDeletes::class, class_uses_recursive($activity->subject_type), true)) {
                $subject = ($activity->subject_type)::withTrashed()->find($activity->subject_id);
                $isSubjectDeleted = (bool) ($subject?->trashed());
            }
        }

        $subjectData = $this->resolveSubjectDetails($activity, $subject, $isSubjectDeleted);
        $actorData = $this->resolveActorDetails($activity);
        $clientData = $this->resolveClientDetails($activity);
        $diffData = $this->resolveAttributeDiffs($activity);

        return [
            'activity' => $activity,
            'actor' => $actorData,
            'subject' => $subjectData,
            'client' => $clientData,
            'diff' => $diffData,
            'isAuth' => str_starts_with($activity->log_name, 'login_'),
            'authAction' => NonModelActions::tryFrom($activity->log_name),
            'rawAttributeChanges' => json_encode($activity->attribute_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'rawProperties' => json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'rawPayload' => json_encode($activity->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Resolves target subject entity details and safe navigation links.
     */
    protected function resolveSubjectDetails(Activity $activity, mixed $subject, bool $isSubjectDeleted): ?array
    {
        if (! $activity->subject_type && ! $activity->subject_id && ! $subject) {
            return null;
        }

        $shortType = $activity->subject_type ? class_basename($activity->subject_type) : 'Unknown';

        $title = null;
        $url = null;

        if ($subject) {
            $title = $subject->ticket_number
                ?? $subject->team_name
                ?? $subject->dept_name
                ?? $subject->category_name
                ?? $subject->full_name
                ?? $subject->name
                ?? $subject->username
                ?? (string) $activity->subject_id;

            $url = $this->resolveSubjectUrl($shortType, $subject);
        }

        return [
            'type' => $shortType,
            'full_type' => $activity->subject_type,
            'id' => $activity->subject_id,
            'title' => $title ?: ($activity->subject_id ? "{$shortType} #{$activity->subject_id}" : $shortType),
            'url' => $url,
            'is_deleted' => $isSubjectDeleted,
            'exists' => (bool) $subject,
        ];
    }

    /**
     * Safely resolves a detail view route for known subject models.
     */
    protected function resolveSubjectUrl(string $shortType, mixed $subject): ?string
    {
        try {
            return match ($shortType) {
                'Ticket' => auth()->user()?->role?->slug_identifier === 'admin'
                    ? route('admin.tickets.show', ['ticket' => $subject->getKey()])
                    : route('cwd.tickets.show', ['ticket' => $subject->getKey()]),
                'User' => route('admin.users.show', ['user' => $subject->getKey()]),
                'Team' => route('admin.teams.show', ['team' => $subject->getKey()]),
                'Department' => route('admin.departments.show', ['dept' => $subject->getKey()]),
                'TicketCategory' => route('admin.ticketCategories.show', ['category' => $subject->getKey()]),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolves actor profile information and fallback identity properties.
     */
    protected function resolveActorDetails(Activity $activity): array
    {
        $causer = $activity->causer;
        $properties = $activity->properties?->toArray() ?? [];

        if ($causer) {
            $name = $causer->full_name ?: $causer->username;
            $role = $causer->role?->role_name ?? 'User';
            $username = $causer->username;
            $email = $causer->email;
            $contact = $causer->contact;
            $initials = $causer->avatar_initials ?? strtoupper(substr(trim($name), 0, 2));
            $url = route('admin.users.show', ['user' => $causer->getKey()]);
        } else {
            $name = $properties['full_name'] ?? $properties['username'] ?? 'System';
            $role = $properties['role'] ?? 'System / Anonymous';
            $username = $properties['username'] ?? null;
            $email = $properties['email'] ?? null;
            $contact = $properties['contact'] ?? null;
            $initials = strtoupper(substr(trim($name), 0, 2)) ?: 'SY';
            $url = null;
        }

        return [
            'name' => Str::title($name),
            'username' => $username,
            'role' => Str::headline($role),
            'email' => $email,
            'contact' => $contact,
            'initials' => $initials,
            'url' => $url,
            'is_causer_model' => (bool) $causer,
        ];
    }

    /**
     * Resolves client network and device details from properties.
     */
    protected function resolveClientDetails(Activity $activity): array
    {
        $ip = $activity->getProperty('ip_address', 'Unknown');
        $userAgent = $activity->getProperty('user_agent');

        return [
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'client_hint' => $this->parseClientHint($userAgent),
            'formatted_datetime' => $activity->created_at->format('M d, Y h:i:s A'),
            'relative_time' => $activity->created_at->diffForHumans(),
        ];
    }

    /**
     * Parses user agent header to produce an intuitive browser and OS hint.
     */
    protected function parseClientHint(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Not Recorded';
        }

        if (str_contains($userAgent, 'PostmanRuntime')) {
            return 'Postman API Client';
        }

        $platform = 'Unknown OS';
        if (str_contains($userAgent, 'Windows')) {
            $platform = 'Windows';
        } elseif (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS')) {
            $platform = 'macOS';
        } elseif (str_contains($userAgent, 'Android')) {
            $platform = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $platform = 'iOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        }

        $browser = 'Browser';
        if (str_contains($userAgent, 'Edg/')) {
            $browser = 'Microsoft Edge';
        } elseif (str_contains($userAgent, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/')) {
            $browser = 'Safari';
        }

        return "{$browser} on {$platform}";
    }

    /**
     * Resolves and normalizes attribute diffs across Spatie v5 attribute_changes and legacy properties.
     */
    protected function resolveAttributeDiffs(Activity $activity): array
    {
        $attributeChanges = $activity->attribute_changes?->toArray() ?? [];
        $properties = $activity->properties?->toArray() ?? [];

        $old = $attributeChanges['old'] ?? $properties['old'] ?? null;
        $attributes = $attributeChanges['attributes'] ?? $properties['attributes'] ?? null;

        $hasModelDiff = ($old !== null || $attributes !== null);
        $diffType = match ($activity->event) {
            'created', 'create' => 'created',
            'deleted', 'delete' => 'deleted',
            'updated', 'update', 'roster_updated' => 'updated',
            default => $hasModelDiff ? 'diff' : 'metadata',
        };

        $rows = [];

        if ($hasModelDiff) {
            $allKeys = array_unique(array_merge(
                array_keys((array) ($old ?? [])),
                array_keys((array) ($attributes ?? []))
            ));

            foreach ($allKeys as $key) {
                $oldVal = $old[$key] ?? null;
                $newVal = $attributes[$key] ?? null;

                $rows[] = [
                    'key' => $key,
                    'label' => Str::headline(str_replace('_', ' ', $key)),
                    'old_raw' => $oldVal,
                    'new_raw' => $newVal,
                    'old_display' => $this->formatDiffValue($oldVal),
                    'new_display' => $this->formatDiffValue($newVal),
                    'is_changed' => $oldVal !== $newVal,
                ];
            }
        } else {
            $metadata = array_diff_key($properties, array_flip(['ip_address', 'user_agent']));
            foreach ($metadata as $key => $val) {
                $rows[] = [
                    'key' => $key,
                    'label' => Str::headline(str_replace('_', ' ', $key)),
                    'old_raw' => null,
                    'new_raw' => $val,
                    'old_display' => null,
                    'new_display' => $this->formatDiffValue($val),
                    'is_changed' => false,
                ];
            }
        }

        return [
            'type' => $diffType,
            'has_model_diff' => $hasModelDiff,
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * Formats individual scalar, array, boolean, or datetime values for diff presentation.
     */
    protected function formatDiffValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('M d, Y h:i A');
            } catch (Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }
}

