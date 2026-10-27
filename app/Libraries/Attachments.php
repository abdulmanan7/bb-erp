<?php

namespace App\Libraries;

/**
 * Stores base64 uploads ({name, data}) under WRITEPATH/uploads/<dir>/<id>.<ext>.
 */
final class Attachments
{
    public const MAX_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    /**
     * Returns true when saved, null when nothing was sent, or an error string.
     */
    public static function save(string $dir, string $id, $attachment): bool|null|string
    {
        if (! is_array($attachment) || empty($attachment['data'])) {
            return null;
        }

        $data = (string) $attachment['data'];
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $binary = base64_decode($data, true);
        if ($binary === false || strlen($binary) > self::MAX_SIZE) {
            return 'Attachment too large (max 5 MB).';
        }

        $ext = strtolower(pathinfo((string) ($attachment['name'] ?? ''), PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return 'Only images and PDF files are allowed.';
        }

        $folder = WRITEPATH . 'uploads/' . $dir;
        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }
        self::delete($dir, $id);
        file_put_contents(self::path($dir, $id, $ext), $binary);
        return true;
    }

    public static function file(string $dir, string $id): ?string
    {
        $files = glob(self::path($dir, $id) . '.*') ?: [];
        return isset($files[0]) && is_file($files[0]) ? $files[0] : null;
    }

    public static function delete(string $dir, string $id): void
    {
        foreach (glob(self::path($dir, $id) . '.*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public static function mime(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
    }

    private static function path(string $dir, string $id, string $ext = ''): string
    {
        return WRITEPATH . 'uploads/' . $dir . '/' . $id . ($ext !== '' ? '.' . $ext : '');
    }
}
