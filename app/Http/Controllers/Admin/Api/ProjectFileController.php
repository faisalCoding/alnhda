<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadPdfRequest;
use App\Http\Requests\Admin\UploadProjectImageRequest;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\Project;
use App\Services\ImageService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Storage;

class ProjectFileController extends Controller
{
    public function storeImage(UploadProjectImageRequest $request, Project $project): ProjectResource
    {
        $path = ImageService::uploadAndProcess($request->file('image'), 'uploads', 800);

        if ($project->image_url) {
            Storage::disk('public')->delete($project->image_url);
        }

        $project->update(['image_url' => $path]);

        return new ProjectResource($project->loadCount('properties'));
    }

    public function storePdf(UploadPdfRequest $request, Project $project): ProjectResource
    {
        $path = $request->file('pdf')->store('presentations', 'public');
        $path = PdfService::compress($path);

        if ($project->pdf_path) {
            Storage::disk('public')->delete($project->pdf_path);
        }

        $project->update(['pdf_path' => $path]);

        return new ProjectResource($project->loadCount('properties'));
    }
}
