<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Storage;

class EditProject extends Component
{
    use WithFileUploads;

    public Project $projectModel;

    #[Validate('required|min:3')]
    public $name;

    #[Validate('required|min:10')]
    public $description;

    #[Validate('nullable|string')]
    public $location;

    #[Validate('required')]
    public $status;

    #[Validate('required')]
    public $project_type;

    #[Validate('nullable|image|max:10240')]
    public $image;

    public $existingImage;

    public function mount(Project $project)
    {
        $this->projectModel = $project;
        $this->name = $project->name;
        $this->description = $project->description;
        $this->location = $project->location;
        $this->status = $project->status;
        $this->project_type = $project->project_type;
        $this->existingImage = $project->image_url;
    }

    public function updateProject()
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'description' => $this->description,
                'location' => $this->location,
                'status' => $this->status,
                'project_type' => $this->project_type,
            ];

            if ($this->image) {
                $data['image_url'] = $this->image->store('uploads', 'public');
                Storage::disk('public')->delete($this->existingImage);
            }

            $this->projectModel->update($data);

            return redirect()->route('projects-dashboard');
        } catch (\Throwable $th) {
            $this->addError('update_error', 'هناك مشكلة حدثت أثناء تحديث المشروع');
        }
    }

    #[Layout('components.layouts.app')] 
    public function render()
    {
        return view('livewire.edit-project');
    }
}
