<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Re-encodes uploaded images with GD before they reach the public disk.
 * Re-encoding drops metadata (EXIF location) and anything smuggled inside the file.
 */
class ImageStore
{
    /** Square crop, e.g. profile photos. */
    public static function square(UploadedFile $file, string $dir, int $size = 600): ?string
    {
        $src = self::load($file);
        if (! $src) {
            return null;
        }

        $edge = min(imagesx($src), imagesy($src));
        $out = imagecreatetruecolor($size, $size);
        imagecopyresampled($out, $src, 0, 0, (int) ((imagesx($src) - $edge) / 2), (int) ((imagesy($src) - $edge) / 2), $size, $size, $edge, $edge);

        return self::save($src, $out, $dir);
    }

    /** Keeps the aspect ratio and transparency, e.g. company logos. */
    public static function fit(UploadedFile $file, string $dir, int $max = 400): ?string
    {
        $src = self::load($file);
        if (! $src) {
            return null;
        }

        $scale = min(1, $max / max(imagesx($src), imagesy($src)));
        $w = max(1, (int) round(imagesx($src) * $scale));
        $h = max(1, (int) round(imagesy($src) * $scale));
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

        return self::save($src, $out, $dir);
    }

    private static function load(UploadedFile $file): \GdImage|false
    {
        return function_exists('imagewebp') ? @imagecreatefromstring((string) file_get_contents($file->getRealPath())) : false;
    }

    private static function save(\GdImage $src, \GdImage $out, string $dir): string
    {
        ob_start();
        imagewebp($out, null, 82);
        $path = $dir.'/'.Str::random(32).'.webp';
        Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($src);
        imagedestroy($out);

        return $path;
    }
}
