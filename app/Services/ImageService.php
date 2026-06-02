<?php

namespace App\Services;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Upload, resize, compress, convert to webp, and save an image.
     *
     * @param mixed $file
     * @param string $folder
     * @param int|null $width
     * @param int|null $height
     * @param string $format
     * @param int $quality
     * @return string Relpath of the saved image
     */
    public static function uploadAndProcess($file, $folder = 'uploads', $width = null, $height = null, $format = 'webp', $quality = 95)
    {
        // Generate a unique filename
        $filename = Str::random(40) . '.' . $format;
        $path = $folder . '/' . $filename;

        // Decode the image using Intervention Image v4
        $image = Image::decode($file);

        // Resize down using scaleDown if width or height is provided (maintaining aspect ratio and upsize protection)
        if ($width || $height) {
            $image->scaleDown(width: $width, height: $height);
        }

        // Encode image using explicit encoders
        $encoded = match (strtolower($format)) {
            'webp' => $image->encode(new WebpEncoder(quality: $quality)),
            'png' => $image->encode(new PngEncoder()),
            'jpg', 'jpeg' => $image->encode(new JpegEncoder(quality: $quality)),
            'gif' => $image->encode(new GifEncoder()),
            default => $image->encode(),
        };

        // Put the encoded image onto the public storage disk
        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }
}
