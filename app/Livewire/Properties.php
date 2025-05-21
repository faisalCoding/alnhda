<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;



class Properties extends Component
{
    use WithFileUploads;


    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('required|min:3')]
    public $project_id;

    #[Validate('required|min:3')]
    public $price;

    #[Validate('required|min:3')]
    public $offer;

    #[Validate('required|min:3')]
    public $status;

    #[Validate('required|min:3')]
    public $rooms;

    #[Validate('required|min:3')]
    public $bathrooms;

    #[Validate('required|min:3')]
    public $living_rooms;

    #[Validate('required|min:3')]
    public $mainds_room;

    #[Validate('required|min:3')]
    public $area;

    #[Validate('required|min:3')]
    public $doors;

    #[Validate('required|min:3')]
    public $type;

    #[Validate('required|min:3')]
    public $parkings;

    #[Validate('required|min:3')]
    public $driver_room;

    #[Validate('required|min:3')]
    public $facade;

    #[Validate('required|min:3')]
    public $furniture;

    #[Validate(['photos.*' => 'image'])]
    public $photos = [];



    public function render()
    {
        return view('livewire.properties');
    }

    public function createProperties()
    {

        $this->save();

        \App\Models\Properties::create([
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
    }

    public function save()
    {
        foreach ($this->photos as $photo) {
            $photo->store('uploads', 'public');
        }
    }
}
