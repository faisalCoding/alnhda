<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\WebpEncoder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Project;
use App\Models\Properties;
use App\Models\ImageProperties;
use App\Models\Article;

class OptimizeImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:optimize-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize, resize, and convert all website images (uploaded and static) to WebP format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting full image optimization process (Target Quality: 95%)...');

        // 1. Process Projects
        $this->optimizeProjects();

        // 2. Process Properties
        $this->optimizeProperties();

        // 3. Process Articles
        $this->optimizeArticles();

        // 4. Process Static Images
        $this->optimizeStaticImages();

        $this->info('Image optimization process completed successfully!');
    }

    /**
     * Optimize all dynamic project cover images.
     */
    private function optimizeProjects()
    {
        $this->info('Optimizing Projects images...');
        $projects = Project::whereNotNull('image_url')->get();
        $count = 0;

        foreach ($projects as $project) {
            $path = $project->image_url;
            if (Str::endsWith(strtolower($path), '.webp')) {
                continue;
            }

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("Project image not found: {$path}");
                continue;
            }

            $absolutePath = Storage::disk('public')->path($path);
            $newPath = preg_replace('/\.(jpg|jpeg|png|gif|bmp)$/i', '.webp', $path);
            $newAbsolutePath = Storage::disk('public')->path($newPath);

            try {
                $img = Image::decode($absolutePath);
                $img->scaleDown(width: 1200);
                $encoded = $img->encode(new WebpEncoder(quality: 95));
                
                Storage::disk('public')->put($newPath, (string) $encoded);
                
                if ($absolutePath !== $newAbsolutePath) {
                    @unlink($absolutePath);
                }

                $project->update(['image_url' => $newPath]);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process project image {$path}: " . $e->getMessage());
            }
        }

        $this->info("Optimized {$count} projects images.");
    }

    /**
     * Optimize all dynamic properties images.
     */
    private function optimizeProperties()
    {
        $this->info('Optimizing Properties images...');
        $images = ImageProperties::whereNotNull('url')->get();
        $count = 0;

        foreach ($images as $imgRecord) {
            $path = $imgRecord->url;
            if (Str::endsWith(strtolower($path), '.webp')) {
                continue;
            }

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("Property image not found: {$path}");
                continue;
            }

            $absolutePath = Storage::disk('public')->path($path);
            $newPath = preg_replace('/\.(jpg|jpeg|png|gif|bmp)$/i', '.webp', $path);
            $newAbsolutePath = Storage::disk('public')->path($newPath);

            try {
                $img = Image::decode($absolutePath);
                $img->scaleDown(width: 1200);
                $encoded = $img->encode(new WebpEncoder(quality: 95));
                
                Storage::disk('public')->put($newPath, (string) $encoded);
                
                if ($absolutePath !== $newAbsolutePath) {
                    @unlink($absolutePath);
                }

                $imgRecord->update(['url' => $newPath]);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process property image {$path}: " . $e->getMessage());
            }
        }

        $this->info("Optimized {$count} properties images.");
    }

    /**
     * Optimize all dynamic articles/blogs cover images.
     */
    private function optimizeArticles()
    {
        $this->info('Optimizing Articles images...');
        $articles = Article::whereNotNull('image_article')->get();
        $count = 0;

        foreach ($articles as $article) {
            $path = $article->image_article;
            if (Str::endsWith(strtolower($path), '.webp')) {
                continue;
            }

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("Article image not found: {$path}");
                continue;
            }

            $absolutePath = Storage::disk('public')->path($path);
            $newPath = preg_replace('/\.(jpg|jpeg|png|gif|bmp)$/i', '.webp', $path);
            $newAbsolutePath = Storage::disk('public')->path($newPath);

            try {
                $img = Image::decode($absolutePath);
                $img->scaleDown(width: 1000);
                $encoded = $img->encode(new WebpEncoder(quality: 95));
                
                Storage::disk('public')->put($newPath, (string) $encoded);
                
                if ($absolutePath !== $newAbsolutePath) {
                    @unlink($absolutePath);
                }

                $article->update(['image_article' => $newPath]);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process article image {$path}: " . $e->getMessage());
            }
        }

        $this->info("Optimized {$count} articles images.");
    }

    /**
     * Optimize all static public/img files.
     */
    private function optimizeStaticImages()
    {
        $this->info('Optimizing static images in public/img/...');
        $imgDir = public_path('img');
        if (!File::exists($imgDir)) {
            $this->warn('public/img directory not found.');
            return;
        }

        $files = File::files($imgDir);
        $count = 0;
        $replacements = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $extension = strtolower($file->getExtension());

            // Skip SVGs, already existing webp, DS_Store, etc.
            if (in_array($extension, ['svg', 'webp', 'ico'])) {
                continue;
            }

            // Skip favicon/KNicon.png to prevent compatibility issues
            if (strpos(strtolower($filename), 'knicon') !== false) {
                continue;
            }

            $absolutePath = $file->getRealPath();
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $webpFilename = $baseName . '.webp';
            $webpAbsolutePath = $imgDir . DIRECTORY_SEPARATOR . $webpFilename;

            // Determine max width based on image type
            $maxWidth = 800; // default for logos/materials
            if (in_array(strtolower($filename), ['homebg.jpg', 'footerbg.jpg'])) {
                $maxWidth = 1920;
            } elseif (in_array(strtolower($filename), ['rebarandplan.jpg', 'frontvilla.jpg', 'villa.jpg'])) {
                $maxWidth = 1600;
            } elseif (in_array(strtolower($filename), ['map.jpg', 'map2.jpg'])) {
                $maxWidth = 1200;
            }

            try {
                $this->info("Processing static image: {$filename} (Max Width: {$maxWidth}px)");
                $img = Image::decode($absolutePath);
                $img->scaleDown(width: $maxWidth);
                $encoded = $img->encode(new WebpEncoder(quality: 95));

                File::put($webpAbsolutePath, (string) $encoded);

                // Add to replacement map
                $replacements[$filename] = $webpFilename;

                // Delete old image
                @unlink($absolutePath);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process static image {$filename}: " . $e->getMessage());
            }
        }

        $this->info("Optimized {$count} static images in public/img/.");

        if (!empty($replacements)) {
            $this->updateBladeReferences($replacements);
        }
    }

    /**
     * Scan and replace old image filename references with the new WebP versions in Blade files.
     */
    private function updateBladeReferences(array $replacements)
    {
        $this->info('Updating image references in Blade templates...');
        $viewsDir = resource_path('views');
        if (!File::exists($viewsDir)) {
            $this->warn('resources/views directory not found.');
            return;
        }

        // Get all blade files recursively
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
                    // Direct replace of filenames
                    $content = str_replace($oldName, $newName, $content);
                }

                if ($content !== $originalContent) {
                    File::put($filePath, $content);
                    $updatedCount++;
                    $this->line("Updated references in: " . $file->getFilename());
                }
            }
        }

        $this->info("Updated image references in {$updatedCount} Blade views.");
    }
}
