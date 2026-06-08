<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PdfService
{
    /**
     * Compress a PDF using Ghostscript (gs)
     *
     * @param  string|null  $relativePath  Relative path in public disk
     * @return string|null The relative path of the compressed PDF
     */
    public static function compress(?string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            return $relativePath;
        }

        $absoluteInputPath = $disk->path($relativePath);

        // Define temporary output path
        $tempPath = $absoluteInputPath.'.compressed.pdf';

        // Run Ghostscript command using Symfony Process
        $process = new Process([
            'gs',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.7',
            '-dPDFSETTINGS=/printer',
            '-dColorConversionStrategy=/LeaveColorUnchanged',
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            "-sOutputFile={$tempPath}",
            $absoluteInputPath,
        ]);

        try {
            $process->mustRun();

            // If compression succeeded and output file is smaller, replace original
            if (file_exists($tempPath)) {
                if (filesize($tempPath) < filesize($absoluteInputPath) && filesize($tempPath) > 0) {
                    rename($tempPath, $absoluteInputPath);
                } else {
                    unlink($tempPath);
                }
            }
        } catch (\Throwable $e) {
            // Log warning but do not crash the request, fallback to original uncompressed PDF
            Log::warning('Ghostscript PDF compression skipped/failed: '.$e->getMessage());

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return $relativePath;
    }
}
