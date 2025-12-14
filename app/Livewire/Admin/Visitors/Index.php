<?php

namespace App\Livewire\Admin\Visitors;

use App\Models\Visitor;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $visitors = Visitor::orderBy('created_at', 'desc')->get()->groupBy('form_name');
        
        return view('livewire.admin.visitors.index', [
            'visitorsGroups' => $visitors
        ])->layout('components.layouts.app');
    }
}
