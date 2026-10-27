<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Clock;
use App\Models\AnnouncementModel;
use CodeIgniter\HTTP\ResponseInterface;

class Announcements extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $query = (new AnnouncementModel())->visibleQuery($this->user()['id']);
        return $this->paginate($query, fn ($row) => AnnouncementModel::map($row, true));
    }

    public function show(string $id): ResponseInterface
    {
        $m = new AnnouncementModel();
        $row = $m->visibleQuery($this->user()['id'])->where('a.id', $id)->get()->getRowArray();
        if (! $row) {
            return $this->fail('Announcement not found.', 404);
        }

        $m->markRead($id, $this->user()['id']);
        $row['read_at'] = $row['read_at'] ?? Clock::now();
        return $this->json(['announcement' => AnnouncementModel::map($row, true)]);
    }
}
