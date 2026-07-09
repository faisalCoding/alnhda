<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ImageProperties;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class OptimizeImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:optimize-images
                            {--quality=87 : WebP encoding quality}
                            {--skip-static : Skip the public/img static images}
                            {--skip-dynamic : Skip the uploaded storage images}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resize, compress, and convert all website images (uploaded and static) to WebP, keeping the smaller file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $quality = (int) $this->option('quality');

        $this->info("Starting image optimization (target quality: {$quality}%)...");

        if (! $this->option('skip-dynamic')) {
            $this->optimizeProjects($quality);
            $this->optimizeProperties($quality);
            $this->optimizeArticles($quality);
        }

        if (! $this->option('skip-static')) {
            $this->optimizeStaticImages($quality);
        }

        $this->info('Image optimization process completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Optimize all dynamic project cover images.
     */
    private function optimizeProjects(int $quality): void
    {
        $this->info('Optimizing Projects images...');
        $count = 0;

        foreach (Project::whereNotNull('image_url')->get() as $project) {
            try {
                $newPath = $this->optimizeStoredImage($project->image_url, 1200, $quality);

                if ($newPath === null) {
                    continue;
                }

                if ($newPath !== $project->image_url) {
                    $project->update(['image_url' => $newPath]);
                }

                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process project image {$project->image_url}: ".$e->getMessage());
            }
        }

        $this->info("Optimized {$count} projects images.");
    }

    /**
     * Optimize all dynamic properties images.
     */
    private function optimizeProperties(int $quality): void
    {
        $this->info('Optimizing Properties images...');
        $count = 0;

        foreach (ImageProperties::whereNotNull('url')->get() as $imgRecord) {
            try {
                $newPath = $this->optimizeStoredImage($imgRecord->url, 1200, $quality);

                if ($newPath === null) {
                    continue;
                }

                if ($newPath !== $imgRecord->url) {
                    $imgRecord->update(['url' => $newPath]);
                }

                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process property image {$imgRecord->url}: ".$e->getMessage());
            }
        }

        $this->info("Optimized {$count} properties images.");
    }

    /**
     * Optimize all dynamic articles/blogs cover images.
     */
    private function optimizeArticles(int $quality): void
    {
        $this->info('Optimizing Articles images...');
        $count = 0;

        foreach (Article::whereNotNull('image_article')->get() as $article) {
            try {
                $newPath = $this->optimizeStoredImage($article->image_article, 1000, $quality);

                if ($newPath === null) {
                    continue;
                }

                if ($newPath !== $article->image_article) {
                    $article->update(['image_article' => $newPath]);
                }

                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process article image {$article->image_article}: ".$e->getMessage());
            }
        }

        $this->info("Optimized {$count} articles images.");
    }

    /**
     * Re-encode one image on the public disk as WebP, keeping the original when re-encoding would not shrink it.
     * Returns the (possibly new) relative path, or null when the file is missing or was kept as-is.
     */
    private function optimizeStoredImage(string $path, int $maxWidth, int $quality): ?string
    {
        if (str_starts_with($path, '/img/') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($path);
        $newPath = preg_replace('/\.(jpg|jpeg|png|gif|bmp)$/i', '.webp', $path);
        $isConversion = $newPath !== $path;
        $originalSize = filesize($absolutePath);

        $img = Image::decode($absolutePath);
        $img->scaleDown(width: $maxWidth);
        $encoded = (string) $img->encode(new WebpEncoder(quality: $quality));

        if (! $isConversion && strlen($encoded) >= $originalSize) {
            return null;
        }

        Storage::disk('public')->put($newPath, $encoded);

        if ($isConversion) {
            @unlink($absolutePath);
        }

        $savedKb = round(($originalSize - strlen($encoded)) / 1024);
        $this->line("  {$path} → {$newPath} (saved {$savedKb}KB)");

        return $newPath;
    }

    /**
     * Optimize all static public/img files, including re-compressing existing WebP files.
     */
    private function optimizeStaticImages(int $quality): void
    {
        $this->info('Optimizing static images in public/img/...');
        $imgDir = public_path('img');

        if (! File::exists($imgDir)) {
            $this->warn('public/img directory not found.');

            return;
        }

        $count = 0;
        $replacements = [];

        foreach (File::files($imgDir) as $file) {
            $filename = $file->getFilename();
            $extension = strtolower($file->getExtension());

            if (in_array($extension, ['svg', 'ico', 'pdf'])) {
                continue;
            }

            if (str_contains(strtolower($filename), 'knicon')) {
                continue;
            }

            $absolutePath = $file->getRealPath();
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $webpFilename = $baseName.'.webp';
            $webpAbsolutePath = $imgDir.DIRECTORY_SEPARATOR.$webpFilename;

            $maxWidth = match (strtolower($baseName)) {
                'homebg', 'footerbg' => 1920,
                'rebarandplan' => 1200,
                default => 1600,
            };

            try {
                $originalSize = filesize($absolutePath);
                $img = Image::decode($absolutePath);
                $img->scaleDown(width: $maxWidth);
                $encoded = (string) $img->encode(new WebpEncoder(quality: $quality));

                if ($absolutePath === $webpAbsolutePath && strlen($encoded) >= $originalSize) {
                    $this->line("  {$filename} kept as-is (already smaller)");

                    continue;
                }

                File::put($webpAbsolutePath, $encoded);

                if ($absolutePath !== $webpAbsolutePath) {
                    $replacements[$filename] = $webpFilename;
                    @unlink($absolutePath);
                }

                $savedKb = round(($originalSize - strlen($encoded)) / 1024);
                $this->line("  {$filename} → {$webpFilename} (saved {$savedKb}KB)");
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process static image {$filename}: ".$e->getMessage());
            }
        }

        $this->info("Optimized {$count} static images in public/img/.");

        if (! empty($replacements)) {
            $this->updateBladeReferences($replacements);
        }
    }

    /**
     * Scan and replace old image filename references with the new WebP versions in Blade files.
     */
    private function updateBladeReferences(array $replacements): void
    {
        $this->info('Updating image references in Blade templates...');
        $viewsDir = resource_path('views');

        if (! File::exists($viewsDir)) {
            $this->warn('resources/views directory not found.');

            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $updatedCount = 0;

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getRealPath();
                $content = File::get($filePath);
                $originalContent = $content;

                foreach ($replacements as $oldName => $newName) {
                    $content = str_replace($oldName, $newName, $content);
                }

                if ($content !== $originalContent) {
                    File::put($filePath, $content);
                    $updatedCount++;
                    $this->line('Updated references in: '.$file->getFilename());
                }
            }
        }

        $this->info("Updated image references in {$updatedCount} Blade views.");
    }
}
