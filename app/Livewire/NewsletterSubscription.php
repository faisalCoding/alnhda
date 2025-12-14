<?php

namespace App\Livewire;

use App\Models\Visitor;
use Livewire\Component;

class NewsletterSubscription extends Component
{
    public $email;

    protected $rules = [
        'email' => 'required|email',
    ];

    public function subscribe()
    {
        $this->validate();

        $visitor = Visitor::where('email', $this->email)->first();
        if ($visitor) {
            session()->flash('message', 'أنت بالفعل مشترك في القائمة البريدية');
            return;
        }
        Visitor::create([
            'email' => $this->email,
            'form_name' => 'newsletter',
        ]);

        $this->reset('email');
        session()->flash('message', 'تم الاشتراك بنجاح في القائمة البريدية');
    }

    public function render()
    {
        return view('livewire.newsletter-subscription');
    }
}
