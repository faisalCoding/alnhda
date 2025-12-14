<?php

namespace App\Livewire;

use App\Models\Visitor;
use Livewire\Component;

class HeaderPropertyRequest extends Component
{
    public $first_name;
    public $last_name;
    public $phone;
    public $isOpen = false;
    public $selectedType = '';

    protected $rules = [
        'first_name' => 'required|min:2',
        'last_name' => 'required|min:2',
        'phone' => 'required',
    ];

    public function openForm($type)
    {
        $this->selectedType = $type;
        $this->isOpen = true;
    }

    public function closeForm()
    {
        $this->isOpen = false;
        $this->reset(['first_name', 'last_name', 'phone', 'selectedType']);
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate();

        $formName = '';
        switch ($this->selectedType) {
            case 'villa':
                $formName = 'header_villa';
                break;
            case 'apartment':
                $formName = 'header_apartment';
                break;
            case 'floor':
                $formName = 'header_floor';
                break;
            default:
                $formName = 'header_unknown';
        }

        Visitor::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'form_name' => $formName,
        ]);

        $this->closeForm();
        session()->flash('message', 'تم ارسال طلبك بنجاح');
    }

    public function render()
    {
        return view('livewire.header-property-request');
    }
}
