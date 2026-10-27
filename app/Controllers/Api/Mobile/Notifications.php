<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Clock;
use App\Models\NotificationModel;
use CodeIgniter\HTTP\ResponseInterface;

class Notifications extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $unreadOnly = $this->request->getGet('unread') === '1';
        return $this->paginate(
            (new NotificationModel())->listQuery($this->user()['id'], $unreadOnly),
            [NotificationModel::class, 'map']
        );
    }

    /** Opening a notification marks it as read. */
    public function show(string $id): ResponseInterface
    {
        $m = new NotificationModel();
        $row = $m->where(['id' => $id, 'employee_id' => $this->user()['id']])->first();
        if (! $row) {
            return $this->fail('Notification not found.', 404);
        }
        if ($row['read_at'] === null) {
            $row['read_at'] = Clock::now();
            $m->update($id, ['read_at' => $row['read_at']]);
        }
        return $this->json(['notification' => NotificationModel::map($row)]);
    }
}
