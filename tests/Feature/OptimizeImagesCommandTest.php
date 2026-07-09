<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

uses(RefreshDatabase::class);

it('converts legacy jpg images to webp and updates the model path', function () {
    Storage::fake('public');

    $jpeg = Image::createImage(1600, 1200)->fill('#8aa17c');
    Storage::disk('public')->put('uploads/legacy.jpg', (string) $jpeg->encode(new JpegEncoder(quality: 100)));

    $project = Project::factory()->create(['image_url' => 'uploads/legacy.jpg']);

    $this->artisan('app:optimize-images', ['--skip-static' => true])->assertSuccessful();

    expect($project->refresh()->image_url)->toBe('uploads/legacy.webp');
    Storage::disk('public')->assertExists('uploads/legacy.webp');
    Storage::disk('public')->assertMissing('uploads/legacy.jpg');

    $optimized = Image::decode(Storage::disk('public')->path('uploads/legacy.webp'));
    expect($optimized->width())->toBe(1200);
});

it('re-encodes oversized webp images in place and scales them down', function () {
    Storage::fake('public');

    Storage::disk('public')->put('uploads/big.webp', noisyWebp(2400, 1600, quality: 95));
    $originalSize = Storage::disk('public')->size('uploads/big.webp');

    $project = Project::factory()->create(['image_url' => 'uploads/big.webp']);

    $this->artisan('app:optimize-images', ['--skip-static' => true])->assertSuccessful();

    expect($project->refresh()->image_url)->toBe('uploads/big.webp')
        ->and(Storage::disk('public')->size('uploads/big.webp'))->toBeLessThan($originalSize);

    $optimized = Image::decode(Storage::disk('public')->path('uploads/big.webp'));
    expect($optimized->width())->toBe(1200);
});

it('keeps an already optimized webp untouched when re-encoding would not shrink it', function () {
    Storage::fake('public');

    $small = Image::createImage(600, 400)->fill('#8aa17c');
    Storage::disk('public')->put('uploads/small.webp', (string) $small->encode(new WebpEncoder(quality: 87)));
    $originalBytes = Storage::disk('public')->get('uploads/small.webp');

    Project::factory()->create(['image_url' => 'uploads/small.webp']);

    $this->artisan('app:optimize-images', ['--skip-static' => true])->assertSuccessful();

    expect(Storage::disk('public')->get('uploads/small.webp'))->toBe($originalBytes);
});

it('skips dynamic images pointing at bundled defaults outside the storage disk', function () {
    Storage::fake('public');

    Project::factory()->create(['image_url' => '/img/article.jpg']);

    $this->artisan('app:optimize-images', ['--skip-static' => true])->assertSuccessful();
});

/**
 * Build a webp image with enough pixel detail that lossy re-encoding actually shrinks it.
 */
function noisyWebp(int $width, int $height, int $quality): string
{
    $gd = imagecreatetruecolor($width, $height);
    mt_srand(42);

    for ($x = 0; $x < $width; $x += 8) {
        for ($y = 0; $y < $height; $y += 8) {
            $color = imagecolorallocate($gd, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($gd, $x, $y, $x + 7, $y + 7, $color);
        }
    }

    for ($i = 0; $i < 3; $i++) {
        imagefilter($gd, IMG_FILTER_GAUSSIAN_BLUR);
    }

    ob_start();
    imagewebp($gd, null, $quality);

    return ob_get_clean();
}
