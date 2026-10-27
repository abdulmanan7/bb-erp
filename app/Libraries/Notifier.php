<?php

namespace App\Libraries;

use App\Models\DevicePushTokenModel;
use App\Models\NotificationModel;

/**
 * Creates in-app notifications and sends a push to each recipient's registered devices.
 * A dedupe key per recipient guarantees one notification (and one push) per event.
 */
final class Notifier
{
    public function __construct(
        private readonly NotificationModel $notifications = new NotificationModel(),
        private readonly DevicePushTokenModel $tokens = new DevicePushTokenModel(),
        private readonly PushService $push = new PushService(),
    ) {
    }

    /**
     * @param list<string> $employeeIds
     */
    public function notify(array $employeeIds, string $category, string $title, string $body, string $referenceType, string $referenceId, string $dedupePrefix): void
    {
        $employeeIds = array_values(array_unique(array_filter($employeeIds)));
        $keys = array_map(fn (string $employeeId) => "{$dedupePrefix}:{$employeeId}", $employeeIds);
        $existing = $this->notifications->existingDedupeKeys($keys);
        $fresh = array_values(array_filter($employeeIds, fn (string $employeeId) => ! in_array("{$dedupePrefix}:{$employeeId}", $existing, true)));
        if ($fresh === []) {
            return;
        }

        $now = Clock::now();
        $rows = [];
        foreach ($fresh as $employeeId) {
            $rows[] = [
                'id'             => bin2hex(random_bytes(16)),
                'employee_id'    => $employeeId,
                'category'       => $category,
                'title'          => $title,
                'body'           => $body,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'dedupe_key'     => "{$dedupePrefix}:{$employeeId}",
                'push_status'    => 'pending',
                'created_at'     => $now,
            ];
        }
        $this->notifications->insertBatch($rows);

        $devices = $this->tokens->groupedByEmployees($fresh);
        foreach ($rows as $row) {
            $this->deliver($row, $devices[$row['employee_id']] ?? []);
        }
    }

    private function deliver(array $row, array $devices): void
    {
        $status = 'skipped';
        if ($devices !== [] && $this->push->isConfigured()) {
            $sent = $this->push->send($devices, $row['title'], $row['body'], [
                'notificationId' => $row['id'],
                'category'       => $row['category'],
                'referenceType'  => $row['reference_type'],
                'referenceId'    => $row['reference_id'],
            ]);
            $status = $sent ? 'sent' : 'failed';
        }
        $this->notifications->update($row['id'], ['push_status' => $status]);
    }
}
