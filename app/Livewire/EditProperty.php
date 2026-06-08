<?php

namespace App\Livewire;

use App\Models\ImageProperties;
use App\Models\Properties;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditProperty extends Component
{
    use WithFileUploads;

    public Properties $propertyModel;

    #[Validate('required|min:3')]
    public $name;

    #[Validate('required')]
    public $project_id;

    #[Validate('required')]
    public $price;

    #[Validate('nullable|numeric')]
    public $offer;

    #[Validate('required')]
    public $status;

    #[Validate('required')]
    public $rooms;

    #[Validate('required')]
    public $bathrooms;

    #[Validate('required')]
    public $living_rooms;

    #[Validate('required')]
    public $mainds_room;

    #[Validate('required')]
    public $area;

    #[Validate('required')]
    public $doors;

    #[Validate('required')]
    public $type;

    #[Validate('required')]
    public $parkings;

    #[Validate('required')]
    public $driver_room;

    #[Validate('required')]
    public $facade;

    #[Validate('nullable')]
    public $unit_youtube;

    #[Validate('nullable')]
    public $stages_building_youtube;

    #[Validate('boolean')]
    public $furniture;

    #[Validate('nullable|file|mimes:pdf|max:40960')]
    public $pdf_file;

    public $existingPdf;

    #[Validate(['photos.*' => 'image|max:10240'])]
    public $photos = [];

    public function mount(Properties $property)
    {
        $this->propertyModel = $property;

        $this->name = $property->name;
        $this->project_id = $property->project_id;
        $this->price = $property->price;
        $this->offer = $property->offer;
        $this->status = $property->status;
        $this->rooms = $property->rooms;
        $this->bathrooms = $property->bathrooms;
        $this->living_rooms = $property->living_rooms;
        $this->mainds_room = $property->mainds_room;
        $this->area = $property->area;
        $this->doors = $property->doors;
        $this->type = $property->type;
        $this->parkings = $property->parkings;
        $this->driver_room = $property->driver_room;
        $this->facade = $property->facade;
        $this->furniture = (bool) $property->furniture;
        $this->unit_youtube = $property->unit_youtube;
        $this->stages_building_youtube = $property->stages_building_youtube;
        $this->existingPdf = $property->pdf_path;
    }

    public function updateProperty()
    {
        $this->validate();

        try {
            $this->propertyModel->update([
                'name' => $this->name,
                'project_id' => $this->project_id,
                'price' => $this->price,
                'offer' => $this->offer ?: null,
                'status' => $this->status,
                'rooms' => $this->rooms,
                'bathrooms' => $this->bathrooms,
                'living_rooms' => $this->living_rooms,
                'mainds_room' => $this->mainds_room,
                'area' => $this->area,
                'doors' => $this->doors,
                'type' => $this->type,
                'parkings' => $this->parkings,
                'driver_room' => $this->driver_room,
                'facade' => $this->facade,
                'furniture' => $this->furniture,
                'unit_youtube' => $this->unit_youtube ?: null,
                'stages_building_youtube' => $this->stages_building_youtube ?: null,
            ]);

            if ($this->pdf_file) {
                $newPath = $this->pdf_file->store('presentations', 'public');
                $newPath = \App\Services\PdfService::compress($newPath);
                if ($this->existingPdf) {
                    Storage::disk('public')->delete($this->existingPdf);
                }
                $this->propertyModel->update(['pdf_path' => $newPath]);
                $this->existingPdf = $newPath;
            }

            if (! empty($this->photos)) {
                foreach ($this->photos as $photo) {
                    $path = ImageService::uploadAndProcess($photo, 'uploads', 1200);
                    ImageProperties::create([
                        'url' => $path,
                        'properties_id' => $this->propertyModel->id,
                    ]);
                }
            }

            return redirect()->route('projects-dashboard');
        } catch (\Throwable $th) {
            $this->addError('update_error', 'هناك مشكلة حدثت أثناء تحديث الوحدة');
        }
    }

    public function deleteImage($imageId)
    {
        $image = ImageProperties::find($imageId);
        if ($image && $image->properties_id == $this->propertyModel->id) {
            Storage::disk('public')->delete($image->url);
            $image->delete();
            $this->propertyModel->load('propertiesImages');
        }
    }

    public function removeNewPhoto($index)
    {
        array_splice($this->photos, $index, 1);
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.edit-property');
    }
}
