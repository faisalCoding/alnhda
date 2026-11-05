<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use SplFileInfo;

class ImageService
{
    /**
     * Google will only show a favicon that is square with a side that is a
     * multiple of 48, so the tab icon is built at 192 rather than a rounder
     * looking number. Apple wants 180 for a home-screen shortcut.
     */
    public const FAVICON_SIZE = 192;

    public const APPLE_ICON_SIZE = 180;

    /**
     * Prepares the site icon.
     *
     * PNG throughout, and cropped square: a browser will letterbox a
     * non-square icon into the tab, and Google skips it outright. The random
     * name matters more here than elsewhere — browsers cache a favicon hard,
     * and a fresh URL is the only reliable way past it.
     *
     * @param  mixed  $file
     * @return array{favicon_path: string, apple_touch_icon_path: string}
     */
    public static function uploadFavicon($file, string $folder = 'seo/icons'): array
    {
        $base = Str::random(40);

        $save = function (int $size) use ($file, $folder, $base): string {
            $path = $folder.'/'.$base.'-'.$size.'.png';
            $image = Image::decode($file)->cover($size, $size);

            Storage::disk('public')->put($path, (string) $image->encode(new PngEncoder));

            return $path;
        };

        return [
            'favicon_path' => $save(self::FAVICON_SIZE),
            'apple_touch_icon_path' => $save(self::APPLE_ICON_SIZE),
        ];
    }

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

    /**
     * The WebP quality uploads are stored at.
     *
     * Scored with SSIMULACRA2 against the originals, 90 is where WebP stops
     * visibly giving up detail; past it the file keeps growing for a gain no
     * eye can find, and below it fine texture — tiles, foliage, rebar — is
     * the first thing to smear.
     */
    public const QUALITY = 90;

    /**
     * The widest each kind of upload is kept. Each is twice the widest the
     * site ever draws it, because a phone or a laptop screen packs two or
     * three pixels into every point it shows: a cover kept at the drawn size
     * is enlarged on those screens and comes out soft.
     *
     * The hero stays narrower than the rest. It is the first image on the
     * site and every visitor pays for its bytes before seeing anything.
     */
    public const HERO_WIDTH = 2000;

    public const PROJECT_WIDTH = 2400;

    public const PROPERTY_WIDTH = 2400;

    public const ARTICLE_WIDTH = 1800;

    /**
     * The smaller copies kept beside each upload, so that a phone or a card
     * is sent the smallest one still sharp on it rather than the full image.
     */
    public const VARIANT_WIDTHS = [800, 1200, 1600];

    /**
     * Prepares a photograph for the front of the site.
     */
    public static function uploadHeroImage($file): string
    {
        return self::uploadAndProcess($file, 'hero', self::HERO_WIDTH);
    }

    /**
     * Upload, scale down, compress and save an image.
     *
     * WebP, the default, is stored with its colour profile and with smaller
     * copies beside it for srcset (see VARIANT_WIDTHS).
     *
     * @return string Relative path of the saved image
     */
    public static function uploadAndProcess(mixed $file, string $folder = 'uploads', ?int $width = null, ?int $height = null, string $format = 'webp', int $quality = self::QUALITY): string
    {
        $path = $folder.'/'.Str::random(40).'.'.$format;
        $image = Image::decode($file);

        if ($width || $height) {
            $image->scaleDown(width: $width, height: $height);
        }

        if (strtolower($format) !== 'webp') {
            $encoded = match (strtolower($format)) {
                'png' => $image->encode(new PngEncoder),
                'jpg', 'jpeg' => $image->encode(new JpegEncoder(quality: $quality)),
                'gif' => $image->encode(new GifEncoder),
                default => $image->encode(),
            };

            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        }

        $colourProfile = self::colourProfileOf($file);
        [$encoded, $quality] = self::encodeWebp($image, $quality, $colourProfile);

        Storage::disk('public')->put($path, $encoded);

        foreach (self::VARIANT_WIDTHS as $variantWidth) {
            if ($variantWidth * 1.25 > $image->width()) {
                continue;
            }

            $variant = (clone $image)->scaleDown(width: $variantWidth);
            $variantBytes = (string) $variant->encode(new WebpEncoder(quality: $quality));

            Storage::disk('public')->put(self::variantPath($path, $variantWidth), self::withColourProfile($variantBytes, $colourProfile));
        }

        return $path;
    }

    /**
     * Encodes an image as WebP, the way uploads are stored.
     *
     * A photograph is encoded lossy. Anything that did not arrive as a JPEG —
     * a logo, a plan, a screenshot — is also tried losslessly and kept that
     * way when it comes out no larger: flat colour and lettering compress
     * better without loss, and lossy WebP blurs the edges of text.
     *
     * @return array{string, int} The bytes, and the quality that made them (100 is lossless)
     */
    public static function encodeWebp(ImageInterface $image, int $quality = self::QUALITY, ?string $colourProfile = null): array
    {
        $encoded = (string) $image->encode(new WebpEncoder(quality: $quality));

        if ($image->origin()->mediaType() !== 'image/jpeg') {
            $lossless = (string) $image->encode(new WebpEncoder(quality: 100));

            if (strlen($lossless) <= strlen($encoded)) {
                [$encoded, $quality] = [$lossless, 100];
            }
        }

        return [self::withColourProfile($encoded, $colourProfile), $quality];
    }

    /**
     * Where the copy of an upload at a given width is kept.
     */
    public static function variantPath(string $path, int $width): string
    {
        return preg_replace('/(\.\w+)$/', "-{$width}w$1", $path);
    }

    /**
     * Removes an upload along with the smaller copies kept beside it.
     */
    public static function delete(string $path): void
    {
        Storage::disk('public')->delete([
            $path,
            ...array_map(fn (int $width): string => self::variantPath($path, $width), self::VARIANT_WIDTHS),
        ]);
    }

    /**
     * The srcset and sizes attributes for an upload, so that a browser
     * fetches the smallest copy still sharp on its screen.
     *
     * Empty for an image without copies — anything uploaded before they were
     * made, or not uploaded at all — which then falls back to its src.
     *
     * @param  string  $sizes  How wide the image is drawn, in the syntax of the sizes attribute
     */
    public static function srcsetAttributes(?string $path, string $sizes): HtmlString
    {
        if (! $path || ! str_ends_with($path, '.webp')) {
            return new HtmlString('');
        }

        $disk = Storage::disk('public');
        $candidates = [];

        foreach (self::VARIANT_WIDTHS as $width) {
            $variant = self::variantPath($path, $width);

            if ($disk->exists($variant)) {
                $candidates[] = asset('storage/'.$variant)." {$width}w";
            }
        }

        $size = $candidates === [] ? false : @getimagesize($disk->path($path));

        if ($size === false) {
            return new HtmlString('');
        }

        $candidates[] = asset('storage/'.$path)." {$size[0]}w";

        return new HtmlString('srcset="'.e(implode(', ', $candidates)).'" sizes="'.e($sizes).'"');
    }

    /**
     * Reads the colour profile of whatever was handed to uploadAndProcess.
     */
    private static function colourProfileOf(mixed $file): ?string
    {
        $path = match (true) {
            $file instanceof SplFileInfo => $file->getRealPath(),
            is_string($file) && ! str_contains($file, "\0") && is_file($file) => $file,
            default => false,
        };

        return $path ? IccProfile::fromFile($path) : null;
    }

    private static function withColourProfile(string $webp, ?string $colourProfile): string
    {
        if ($colourProfile === null || IccProfile::isSrgb($colourProfile)) {
            return $webp;
        }

        return IccProfile::embedInWebp($webp, $colourProfile);
    }
}
