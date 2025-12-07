<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;
use App\Models\Project;

class Projects extends Component
{

    use WithFileUploads;

    #[Validate('required|image|max:2048|dimensions:max_width=3000,max_height=3000|mimes:jpg,jpeg,png,webp,bmp,gif')]
    public $image;

    public $project = [];

    public function render()
    {
        return view('livewire.projects');
    }

    public function createProject()
    {

        try {
            $upload_and_path = $this->uploadFile();

            Project::create(
                [
                    'name' => $this->project['name'],
                    'description' => $this->project['description'],
                    'location' => $this->project['location'] ?? null,
                    'status' => $this->project['status'],
                    'project_type' => $this->project['project_type'],
                    'image_url' => $upload_and_path,
                ]
            );
        } catch (\Throwable $th) {
            dd($th);
            $this->addError('creating_error', 'هناك مشكلة حدثت أثناء إنشاء المشروع');
        }
    }

    public function uploadFile()
    {

        try {
            $path =  $this->image->store('uploads', 'public');
        } catch (\Throwable $th) {
            dd($th);
            $this->addError('catch_upload', 'هناك مشكلة حدثت أثناء رفع الصورة');
        }

        return $path;
    }
    public function deleteProject($id)
    {
        Project::find($id)->delete();
    }
}
