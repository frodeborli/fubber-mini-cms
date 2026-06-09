<?php

namespace MiniCms;

class ImageProcessor
{
    private string $uploadsPath;
    private array $targetWidths = [320, 640, 960, 1280, 1920];

    public function __construct(string $uploadsPath)
    {
        $this->uploadsPath = rtrim($uploadsPath, '/');
    }

    public function getVersions(string $imagePath): array
    {
        $dir = $this->uploadsPath . '/' . $imagePath . '.versions';
        if (!is_dir($dir)) return [];

        $aspects = [];
        foreach (scandir($dir) as $entry) {
            if ($entry[0] === '.') continue;
            $aspectDir = $dir . '/' . $entry;
            if (!is_dir($aspectDir)) continue;

            $cropFile = $aspectDir . '/crop.json';
            $crop = is_file($cropFile) ? json_decode(file_get_contents($cropFile), true) : null;

            $widths = [];
            foreach (glob($aspectDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (is_numeric($name)) {
                    $widths[(int)$name] = '/uploads/' . $imagePath . '.versions/' . $entry . '/' . basename($file);
                }
            }
            ksort($widths);

            $aspects[$entry] = [
                'crop' => $crop,
                'widths' => $widths,
            ];
        }

        return $aspects;
    }

    public function crop(string $imagePath, string $aspect, array $cropRect): array
    {
        $fullPath = $this->uploadsPath . '/' . $imagePath;
        if (!is_file($fullPath)) {
            throw new \RuntimeException("Image not found: $imagePath");
        }

        $info = getimagesize($fullPath);
        if (!$info) {
            throw new \RuntimeException("Not a valid image: $imagePath");
        }

        $src = $this->loadImage($fullPath, $info[2]);
        if (!$src) {
            throw new \RuntimeException("Cannot load image: $imagePath");
        }

        $x = max(0, (int)$cropRect['x']);
        $y = max(0, (int)$cropRect['y']);
        $w = (int)$cropRect['width'];
        $h = (int)$cropRect['height'];

        $cropped = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        imagedestroy($src);

        if (!$cropped) {
            throw new \RuntimeException("Crop failed");
        }

        $versionDir = $this->uploadsPath . '/' . $imagePath . '.versions/' . $aspect;
        if (is_dir($versionDir)) {
            $this->rmdir($versionDir);
        }
        mkdir($versionDir, 0755, true);

        file_put_contents(
            $versionDir . '/crop.json',
            json_encode($cropRect, JSON_PRETTY_PRINT) . "\n"
        );

        $croppedWidth = imagesx($cropped);
        $croppedHeight = imagesy($cropped);
        $ext = $this->outputExtension($info[2]);
        $generated = [];

        foreach ($this->targetWidths as $tw) {
            if ($tw > $croppedWidth) continue;

            $th = (int)round($tw * $croppedHeight / $croppedWidth);
            $resized = imagecreatetruecolor($tw, $th);

            if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_WEBP) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            imagecopyresampled($resized, $cropped, 0, 0, 0, 0, $tw, $th, $croppedWidth, $croppedHeight);

            $outFile = $versionDir . '/' . $tw . '.' . $ext;
            $this->saveImage($resized, $outFile, $info[2]);
            imagedestroy($resized);

            $generated[$tw] = '/uploads/' . $imagePath . '.versions/' . $aspect . '/' . $tw . '.' . $ext;
        }

        if (!isset($generated[$croppedWidth])) {
            $outFile = $versionDir . '/' . $croppedWidth . '.' . $ext;
            $this->saveImage($cropped, $outFile, $info[2]);
            $generated[$croppedWidth] = '/uploads/' . $imagePath . '.versions/' . $aspect . '/' . $croppedWidth . '.' . $ext;
        }

        imagedestroy($cropped);
        ksort($generated);

        return $generated;
    }

    public function deleteVersions(string $imagePath): void
    {
        $dir = $this->uploadsPath . '/' . $imagePath . '.versions';
        if (is_dir($dir)) {
            $this->rmdir($dir);
        }
    }

    private function rmdir(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function loadImage(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private function saveImage(\GdImage $image, string $path, int $type): void
    {
        match ($type) {
            IMAGETYPE_PNG => imagepng($image, $path, 6),
            IMAGETYPE_GIF => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, 85),
            default => imagejpeg($image, $path, 85),
        };
    }

    private function outputExtension(int $type): string
    {
        return match ($type) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
    }
}
