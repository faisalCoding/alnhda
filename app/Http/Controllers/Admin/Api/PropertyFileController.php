<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadPdfRequest;
use App\Http\Requests\Admin\UploadPropertyImagesRequest;
use App\Http\Resources\Admin\PropertyResource;
use App\Models\ImageProperties;
use App\Models\Properties;
use App\Services\ImageService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Storage;

class PropertyFileController extends Controller
{
    public function storeImages(UploadPropertyImagesRequest $request, Properties $property): PropertyResource
    {
        foreach ($request->file('photos', []) as $photo) {
            $path = ImageService::uploadAndProcess($photo, 'uploads', 1200);

            ImageProperties::query()->create([
                'url' => $path,
                'properties_id' => $property->id,
            ]);
        }

        return new PropertyResource($property->load('propertiesImages'));
    }

    public function storePdf(UploadPdfRequest $request, Properties $property): PropertyResource
    {
        $path = $request->file('pdf')->store('presentations', 'public');
        $path = PdfService::compress($path);

        if ($property->pdf_path) {
            Storage::disk('public')->delete($property->pdf_path);
        }

        $property->update(['pdf_path' => $path]);

        return new PropertyResource($property->load('propertiesImages'));
    }
}
