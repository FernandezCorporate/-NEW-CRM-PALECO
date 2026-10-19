<?php

namespace App\Concerns;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Trait providing standardized Spatie Activitylog configuration across models.
 * Automatically handles dirty-checking, event verb mapping, soft-delete distinction,
 * descriptive log messaging, and redaction of personal information from audit diffs
 * while offering model-specific extension hooks.
 */
trait Auditable
{
    use LogsActivity;

    // --- ACTIVITY LOG CONFIGURATION ---

    /**
     * Configures the Spatie Activitylog options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logName = $this->getActivityLogName();
        $attributes = $this->getActivityLogAttributes();

        return LogOptions::defaults()
            ->useLogName($logName)
            ->logOnly($attributes)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(function (string $eventName) {
                if (method_exists($this, 'getCustomActivityDescription')) {
                    $customDesc = $this->getCustomActivityDescription($eventName);
                    if ($customDesc !== null) {
                        return $customDesc;
                    }
                }

                $action = match ($eventName) {
                    'created' => 'created',
                    'updated' => 'modified',
                    'deleted' => (method_exists($this, 'isForceDeleting') && $this->isForceDeleting())
                        ? 'permanently deleted'
                        : 'archived',
                    'restored' => 'restored',
                    default => $eventName,
                };

                $title = $this->getActivityTitle();

                return "{$title} has been {$action}.";
            });
    }

    /**
     * Returns the activity log name for this model.
     */
    public function getActivityLogName(): string
    {
        return property_exists($this, 'activityLogName')
            ? $this->activityLogName
            : class_basename($this);
    }

    /**
     * Returns the list of attributes to track in activity logs.
     */
    public function getActivityLogAttributes(): array
    {
        return property_exists($this, 'activityLogAttributes')
            ? $this->activityLogAttributes
            : $this->fillable;
    }

    /**
     * Returns the personal-information attributes whose values must never be stored in activity logs.
     */
    public function getActivityLogRedactedAttributes(): array
    {
        return property_exists($this, 'activityLogRedactedAttributes')
            ? $this->activityLogRedactedAttributes
            : [];
    }

    // --- PERSONAL INFORMATION REDACTION ---

    /**
     * Spatie hook invoked just before an activity entry is persisted for this model.
     * Masks redacted attribute values so the audit trail records that a field changed, never its value.
     */
    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        if ($activity->attribute_changes) {
            $activity->attribute_changes = collect(
                $this->redactActivityChanges($activity->attribute_changes->toArray())
            );
        }
    }

    /**
     * Replaces redacted attribute values within an activity change set ('attributes' and 'old').
     * Null values are kept so the log still shows when a field was added or cleared.
     */
    protected function redactActivityChanges(array $changes): array
    {
        foreach ($this->getActivityLogRedactedAttributes() as $attribute) {
            foreach (['attributes', 'old'] as $section) {
                if (isset($changes[$section][$attribute])) {
                    $changes[$section][$attribute] = '[redacted]';
                }
            }
        }

        return $changes;
    }

    /**
     * Returns a human-readable title or identifier for this model in log messages.
     */
    public function getActivityTitle(): string
    {
        if (property_exists($this, 'activityTitleAttribute') && ! empty($this->{$this->activityTitleAttribute})) {
            return (string) $this->{$this->activityTitleAttribute};
        }

        return $this->ticket_number
            ?? $this->team_name
            ?? $this->dept_name
            ?? $this->category_name
            ?? $this->username
            ?? (string) ($this->name ?? class_basename($this));
    }
}
