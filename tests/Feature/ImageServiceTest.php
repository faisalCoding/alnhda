<?php

use App\Models\Project;
use App\Services\IccProfile;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('keeps a photograph at twice the width the site draws it, with smaller copies beside it', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 3000, 2000), 'uploads', ImageService::PROJECT_WIDTH);

    expect(storedWidth($path))->toBe(ImageService::PROJECT_WIDTH);

    foreach (ImageService::VARIANT_WIDTHS as $width) {
        expect(storedWidth(ImageService::variantPath($path, $width)))->toBe($width);
    }
});

it('never enlarges a small upload', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 640, 480), 'uploads', ImageService::PROJECT_WIDTH);

    expect(storedWidth($path))->toBe(640);
});

it('makes no copy that would be barely smaller than the upload', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 1400, 1000), 'uploads', ImageService::PROJECT_WIDTH);

    Storage::disk('public')->assertExists(ImageService::variantPath($path, 800));
    Storage::disk('public')->assertMissing(ImageService::variantPath($path, 1200));
    Storage::disk('public')->assertMissing(ImageService::variantPath($path, 1600));
});

it('encodes a photograph lossy', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 1200, 800), 'uploads', ImageService::PROJECT_WIDTH);

    expect(Storage::disk('public')->get($path))->toContain('VP8 ')->not->toContain('VP8L');
});

it('encodes a logo lossless when that comes out smaller', function () {
    $logo = imagecreatetruecolor(900, 300);
    imagefill($logo, 0, 0, imagecolorallocate($logo, 255, 255, 255));
    imagefilledrectangle($logo, 60, 60, 840, 240, imagecolorallocate($logo, 73, 142, 73));
    imagestring($logo, 5, 100, 140, 'KAYAN ALNHDA', imagecolorallocate($logo, 255, 255, 255));

    ob_start();
    imagepng($logo);
    $png = ob_get_clean();

    $file = UploadedFile::fake()->createWithContent('logo.png', $png);
    $path = ImageService::uploadAndProcess($file, 'uploads', ImageService::PROJECT_WIDTH);

    expect(Storage::disk('public')->get($path))->toContain('VP8L');
});

it('carries a wide-gamut colour profile into the webp and its copies', function () {
    $profile = fakeIccProfile(red: [0.5151, 0.2412, -0.0011], green: [0.2919, 0.6922, 0.0419], blue: [0.1571, 0.0666, 0.7841]);
    $file = UploadedFile::fake()->createWithContent('photo.jpg', jpegTaggedWith($profile, 2000, 1500));

    $path = ImageService::uploadAndProcess($file, 'uploads', ImageService::PROJECT_WIDTH);

    foreach ([$path, ImageService::variantPath($path, 800)] as $stored) {
        $bytes = Storage::disk('public')->get($stored);

        expect($bytes)->toContain('ICCP'.pack('V', strlen($profile)).$profile)
            ->and(imagecreatefromstring($bytes))->toBeInstanceOf(GdImage::class);
    }
});

it('leaves an sRGB profile out, since every browser assumes it', function () {
    $profile = fakeIccProfile(red: [0.4361, 0.2225, 0.0139], green: [0.3851, 0.7169, 0.0971], blue: [0.1431, 0.0606, 0.7141]);
    $file = UploadedFile::fake()->createWithContent('photo.jpg', jpegTaggedWith($profile, 1000, 750));

    $path = ImageService::uploadAndProcess($file, 'uploads', ImageService::PROJECT_WIDTH);

    expect(Storage::disk('public')->get($path))->not->toContain('ICCP');
});

it('writes a profile into every shape of webp gd produces', function (int $quality, bool $withAlpha) {
    $image = Image::createImage(300, 200)->fill($withAlpha ? 'rgba(73, 142, 73, 0.5)' : '#498e49');
    $profile = fakeIccProfile(red: [0.5151, 0.2412, -0.0011], green: [0.2919, 0.6922, 0.0419], blue: [0.1571, 0.0666, 0.7841]);

    $webp = IccProfile::embedInWebp((string) $image->encode(new WebpEncoder(quality: $quality)), $profile);

    expect(substr($webp, 12, 4))->toBe('VP8X')
        ->and(ord($webp[20]) & 0x20)->toBe(0x20)
        ->and(unpack('V', substr($webp, 4, 4))[1])->toBe(strlen($webp) - 8)
        ->and(substr($webp, 30, 4))->toBe('ICCP');

    $decoded = imagecreatefromstring($webp);

    expect(imagesx($decoded))->toBe(300)
        ->and(imagesy($decoded))->toBe(200);
})->with([
    'lossy' => [90, false],
    'lossy with alpha' => [90, true],
    'lossless' => [100, false],
    'lossless with alpha' => [100, true],
]);

it('builds srcset from the copies that exist', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 3000, 2000), 'uploads', ImageService::PROJECT_WIDTH);

    $attributes = (string) ImageService::srcsetAttributes($path, '(min-width: 1024px) 33vw, 100vw');

    expect($attributes)
        ->toContain(asset('storage/'.ImageService::variantPath($path, 800)).' 800w')
        ->toContain(asset('storage/'.ImageService::variantPath($path, 1600)).' 1600w')
        ->toContain(asset('storage/'.$path).' 2400w')
        ->toContain('sizes="(min-width: 1024px) 33vw, 100vw"');
});

it('gives no srcset to an image without copies', function (?string $path) {
    Storage::disk('public')->put('uploads/legacy.webp', (string) Image::createImage(800, 600)->encode(new WebpEncoder));

    expect((string) ImageService::srcsetAttributes($path, '100vw'))->toBe('');
})->with([
    'an upload from before the copies' => 'uploads/legacy.webp',
    'a jpeg' => 'uploads/legacy.jpg',
    'a bundled default' => 'img/article.webp',
    'an outside url' => 'https://example.com/photo.webp',
    'nothing' => null,
]);

it('deletes the copies along with the upload', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 3000, 2000), 'uploads', ImageService::PROJECT_WIDTH);

    ImageService::delete($path);

    Storage::disk('public')->assertMissing($path);

    foreach (ImageService::VARIANT_WIDTHS as $width) {
        Storage::disk('public')->assertMissing(ImageService::variantPath($path, $width));
    }
});

it('offers the copies to browsers on the project pages', function () {
    $path = ImageService::uploadAndProcess(UploadedFile::fake()->image('photo.jpg', 3000, 2000), 'uploads', ImageService::PROJECT_WIDTH);
    $project = Project::factory()->create(['image_url' => $path]);
    $srcset = asset('storage/'.ImageService::variantPath($path, 800)).' 800w';

    $this->get(route('projects'))->assertOk()->assertSee($srcset, false);
    $this->get(route('project', $project))->assertOk()->assertSee($srcset, false);
});

function storedWidth(string $path): int
{
    return getimagesize(Storage::disk('public')->path($path))[0];
}

/**
 * A bare RGB display profile with the given primaries, enough for a browser or IccProfile to tell gamuts apart.
 *
 * @param  array{float, float, float}  $red
 * @param  array{float, float, float}  $green
 * @param  array{float, float, float}  $blue
 */
function fakeIccProfile(array $red, array $green, array $blue): string
{
    $tags = ['rXYZ' => $red, 'gXYZ' => $green, 'bXYZ' => $blue];
    $table = pack('N', count($tags));
    $data = '';
    $dataStart = 128 + 4 + count($tags) * 12;

    foreach ($tags as $signature => $xyz) {
        $value = 'XYZ '."\0\0\0\0".implode('', array_map(fn (float $v): string => pack('N', ((int) round($v * 65536)) & 0xFFFFFFFF), $xyz));
        $table .= $signature.pack('N', $dataStart + strlen($data)).pack('N', strlen($value));
        $data .= $value;
    }

    $header = pack('N', 128 + strlen($table) + strlen($data)).str_repeat("\0", 8).'mntr'.'RGB '.'XYZ '.str_repeat("\0", 12).'acsp';

    return str_pad($header, 128, "\0").$table.$data;
}

/**
 * A JPEG carrying the given profile in an APP2 segment, the way a phone writes one.
 */
function jpegTaggedWith(string $profile, int $width, int $height): string
{
    $jpeg = (string) Image::createImage($width, $height)->fill('#3a8f4a')->encode(new JpegEncoder(quality: 90));
    $segment = "\xFF\xE2".pack('n', strlen($profile) + 16)."ICC_PROFILE\0\x01\x01".$profile;

    return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
}
