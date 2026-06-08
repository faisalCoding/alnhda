<?php

namespace App\Livewire;

use App\Services\ImageService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Properties extends Component
{
    use WithFileUploads;

    #[Validate('required|min:3')]
    public $name = 'شقة';

    #[Validate('required')]
    public $project_id;

    #[Validate('required')]
    public $price = 550000;

    #[Validate('nullable|numeric')]
    public $offer = null;

    #[Validate('required')]
    public $status = 'جديد';

    #[Validate('required')]
    public $rooms = 5;

    #[Validate('required')]
    public $bathrooms = 3;

    #[Validate('required')]
    public $living_rooms = 1;

    #[Validate('required')]
    public $mainds_room = 1;

    #[Validate('required')]
    public $area = 180;

    #[Validate('required')]
    public $doors = 2;

    #[Validate('required')]
    public $type = 'شقة';

    #[Validate('required')]
    public $parkings = 1;

    #[Validate('required')]
    public $driver_room = 1;

    #[Validate('required')]
    public $facade = 'شرقية جنوبية';

    #[Validate('nullable')]
    public $unit_youtube = null;

    #[Validate('nullable')]
    public $stages_building_youtube = null;

    #[Validate('required')]
    public $furniture = true;

    public $photos = [];

    #[Validate('nullable|file|mimes:pdf|max:40960')]
    public $pdf_file;

    public function render()
    {
        return view('livewire.properties');
    }

    public function createProperties()
    {
        $this->validate();

        $pdf_path = null;
        if ($this->pdf_file) {
            $pdf_path = $this->pdf_file->store('presentations', 'public');
            $pdf_path = \App\Services\PdfService::compress($pdf_path);
        }

        $property = \App\Models\Properties::create([
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
            'pdf_path' => $pdf_path,
        ]);
        $this->saveImages($property->id);

        $this->reset(['name', 'price', 'offer', 'status', 'rooms', 'bathrooms', 'living_rooms', 'mainds_room', 'area', 'doors', 'type', 'parkings', 'driver_room', 'facade', 'furniture', 'photos', 'pdf_file']);
        session()->flash('message', 'Units successfully created.');
    }

    public function saveImages($propertyId)
    {

        foreach ($this->photos as $photo) {
            $path = ImageService::uploadAndProcess($photo, 'uploads', 1200);
            $image = \App\Models\ImageProperties::create([
                'url' => $path,
                'properties_id' => $propertyId,
            ]);

        }
    }

    public function removePhoto($index)
    {
        array_splice($this->photos, $index, 1);
    }

    public function deleteProperty($id)
    {
        \App\Models\Properties::find($id)->delete();
    }
}
