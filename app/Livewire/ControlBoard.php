<?php

namespace App\Livewire;

use Livewire\Component;

class ControlBoard extends Component
{
    protected $layout = 'layouts.guest';

    public function render()
    {
        return view('livewire.control-board');
    }
}
