<?php

namespace App\Libraries;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\SubHeadModel;

final class HeadAccess
{
    /** @return array{id: string, type: string, subHeadId: string}|string resolved entry target, or an error message */
    public static function resolve(array $user, string $headId, string $subHeadId): array|string
    {
        $head = (new HeadModel())->find($headId);
        if (! $head) {
            return 'Select a valid account head.';
        }
        if ($user['role'] !== 'admin' && ! in_array($head['id'], (new EmployeeModel())->assignedHeadIds($user['id'] ?? ''), true)) {
            return 'This head is not assigned to you.';
        }
        if ($subHeadId !== '' && ! (new SubHeadModel())->where('id', $subHeadId)->where('head_id', $head['id'])->first()) {
            return 'Invalid sub-head.';
        }
        return [
            'id'        => $head['id'],
            'type'      => $head['type'] === 'Expense' ? 'Payment' : 'Receipt',
            'subHeadId' => $subHeadId,
        ];
    }
}
