<?php

namespace App\Actions\Departments;

use App\Events\ReferenceDataUpdated;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Updates department information using optimistic locking concurrency control.
 */
class UpdateDepartment
{
    /**
     * @return array{success: bool, changed?: bool, message?: string}
     */
    public function handle(Department $dept, array $data): array
    {
        return $this->execute($dept, $data);
    }

    /**
     * @return array{success: bool, changed?: bool, message?: string}
     */
    public function execute(Department $dept, array $data): array
    {
        $originalUpdatedAt = $data['original_updated_at'];
        unset($data['original_updated_at']);

        return DB::transaction(function () use ($dept, $data, $originalUpdatedAt) {
            $lockedDept = Department::where('id', $dept->id)->lockForUpdate()->first();

            if (! $lockedDept->updated_at->eq(Carbon::parse($originalUpdatedAt))) {
                return [
                    'success' => false,
                    'message' => 'Conflict: Another administrator modified this department while you were editing. Please refresh and try again.',
                ];
            }

            $lockedDept->fill($data);

            if ($lockedDept->isClean()) {
                return ['success' => true, 'changed' => false];
            }

            $lockedDept->save();

            DB::afterCommit(function () use ($lockedDept) {
                rescue(fn () => ReferenceDataUpdated::dispatch('department', 'updated', $lockedDept->id, $lockedDept->dept_name), report: true);
            });

            return ['success' => true, 'changed' => true];
        });
    }
}
