<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    protected $table = 'projects';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'name', 'description', 'address', 'latitude', 'longitude',
        'radius_m', 'timezone', 'start_time', 'status', 'created_at', 'updated_at',
    ];

    public function allMapped(): array
    {
        return array_map([self::class, 'map'], $this->orderBy('name', 'ASC')->findAll());
    }

    /** Stored as HH:MM:SS; exposed as HH:MM. */
    public static function formatStartTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    public static function map(array $r): array
    {
        return [
            'id'          => $r['id'],
            'name'        => $r['name'],
            'description' => $r['description'] ?? '',
            'address'     => $r['address'] ?? '',
            'latitude'    => (float) $r['latitude'],
            'longitude'   => (float) $r['longitude'],
            'radiusM'     => (int) $r['radius_m'],
            'timezone'    => $r['timezone'],
            'startTime'   => self::formatStartTime($r['start_time'] ?? null),
            'status'      => $r['status'],
            'createdAt'   => $r['created_at'] ?? null,
            'updatedAt'   => $r['updated_at'] ?? null,
        ];
    }
}
