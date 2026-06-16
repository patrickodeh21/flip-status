<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;
use Throwable;

class ImageTimestampService
{
    public static function overlay(string $absolutePath, \DateTimeInterface $when): void
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return; // silently skip if file not found or unreadable
        }

        try {
            $manager = new ImageManager(new Driver());
            $image   = $manager->read($absolutePath);

            // Y coordinate: keep within canvas for small images
            $y = max(28 + 6, $image->height() - 20); // font size + small padding

            $text = 'Captured: ' . $when->format('Y-m-d H:i:s T');

            $image->text($text, 20, $y, function (FontFactory $font) {
                // v3 API — size:int, color:string, align/valign strings, stroke(color,width)
                $font->size(28);
                $font->color('#ffffff');
                $font->align('left');
                $font->valign('bottom');
                $font->stroke('#000000', 1); // <-- fixed order: color first, then width

                // Optional: load a TTF if GD lacks good default font
                // $font->filename(resource_path('fonts/Inter-Regular.ttf'));
            });

            // Save with quality (GD ignores on PNG but safe)
            $image->save($absolutePath, 85);
        } catch (Throwable $e) {
            // Avoid breaking uploads; log for later inspection
            report($e);
        }
    }
}
