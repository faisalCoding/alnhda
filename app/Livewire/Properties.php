<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;



class Properties extends Component
{
    use WithFileUploads;


    #[Validate('required|min:3')]
    public $name = 'test';

    #[Validate('required')]
    public $project_id;

    #[Validate('required')]
    public $price = 1000;

    #[Validate('required')]
    public $offer = 100;

    #[Validate('required')]
    public $status   = 'جديد';

    #[Validate('required    ')]
    public $rooms = 1;

    #[Validate('required')]
    public $bathrooms = 1;

    #[Validate('required')]
    public $living_rooms = 1;

    #[Validate('required')]
    public $mainds_room = 1;

    #[Validate('required')]
    public $area = 100;

    #[Validate('required')]
    public $doors = 1;

    #[Validate('required')]
    public $type = ' غرفة واحدة ';

    #[Validate('required')]
    public $parkings = 1;

    #[Validate('required')]
    public $driver_room = 1;

    #[Validate('required')]
    public $facade = 'test';

    #[Validate('required')]
    public $furniture = 'test';

    #[Validate(['photos.*' => 'mimes:jpeg,jpg,png|max:' . 1024 * 100])]
    public $photos = [];



    public function render()
    {
        return view('livewire.properties');
    }

    public function createProperties()
    {
        $this->validate();

        $property = \App\Models\Properties::create([
            'name'         => $this->name,
            'project_id'   => $this->project_id,
            'price'        => $this->price,
            'offer'        => $this->offer,
            'status'       => $this->status,
            'rooms'        => $this->rooms,
            'bathrooms'    => $this->bathrooms,
            'living_rooms' => $this->living_rooms,
            'mainds_room'  => $this->mainds_room,
            'area'         => $this->area,
            'doors'        => $this->doors,
            'type'         => $this->type,
            'parkings'     => $this->parkings,
            'driver_room'  => $this->driver_room,
            'facade'       => $this->facade,
            'furniture'    => $this->furniture,
        ]);
        $this->saveImages($property->id);
        
        $this->reset(['name', 'price', 'offer', 'status', 'rooms', 'bathrooms', 'living_rooms', 'mainds_room', 'area', 'doors', 'type', 'parkings', 'driver_room', 'facade', 'furniture', 'photos']);
        session()->flash('message', 'Units successfully created.');
    }

    public function saveImages($propertyId)
    {
        
        foreach ($this->photos as $photo) {
            $path = $photo->store('uploads', 'public');
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
