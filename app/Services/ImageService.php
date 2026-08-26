<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    /**
     * Upload, resize, compress, convert to webp, and save an image.
     *
     * @param  mixed  $file
     * @param  string  $folder
     * @param  int|null  $width
     * @param  int|null  $height
     * @param  string  $format
     * @param  int  $quality
     * @return string Relpath of the saved image
     */
    /**
     * The width and height every link preview is built around. WhatsApp,
     * Facebook and LinkedIn all show a wide banner at roughly 1.91:1 and fall
     * back to a small square thumbnail for anything else, so the ratio is
     * forced rather than suggested.
     */
    public const SOCIAL_WIDTH = 1200;

    public const SOCIAL_HEIGHT = 630;

    /**
     * Prepares an image for link previews.
     *
     * Cropped to fill rather than scaled to fit: an image that keeps its own
     * ratio is exactly what loses the banner. Encoded as JPEG because WhatsApp
     * does not reliably render webp in a preview, whatever the browser does.
     *
     * @param  mixed  $file
     * @return string Relative path of the saved image
     */
    public static function uploadSocialImage($file, string $folder = 'seo'): string
    {
        $filename = Str::random(40).'.jpg';
        $path = $folder.'/'.$filename;

        $image = Image::decode($file)->cover(self::SOCIAL_WIDTH, self::SOCIAL_HEIGHT);

        Storage::disk('public')->put($path, (string) $image->encode(new JpegEncoder(quality: 85)));

        return $path;
    }

    public static function uploadAndProcess($file, $folder = 'uploads', $width = null, $height = null, $format = 'webp', $quality = 87)
    {
        // Generate a unique filename
        $filename = Str::random(40).'.'.$format;
        $path = $folder.'/'.$filename;

        // Decode the image using Intervention Image v4
        $image = Image::decode($file);

        // Resize down using scaleDown if width or height is provided (maintaining aspect ratio and upsize protection)
        if ($width || $height) {
            $image->scaleDown(width: $width, height: $height);
        }

        // Encode image using explicit encoders
        $encoded = match (strtolower($format)) {
            'webp' => $image->encode(new WebpEncoder(quality: $quality)),
            'png' => $image->encode(new PngEncoder),
            'jpg', 'jpeg' => $image->encode(new JpegEncoder(quality: $quality)),
            'gif' => $image->encode(new GifEncoder),
            default => $image->encode(),
        };

        // Put the encoded image onto the public storage disk
        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }
}
