<?php

namespace App\Livewire;

use App\Models\Project;
use App\Services\ImageService;
use App\Services\PdfService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Projects extends Component
{
    use WithFileUploads;

    #[Validate('required|image|max:40960|dimensions:max_width=3000,max_height=3000|mimes:jpg,jpeg,png,webp,bmp,gif')]
    public $image;

    #[Validate('nullable|file|mimes:pdf|max:40960')]
    public $pdf_file;

    public $project = [];

    public $guarantees = [];

    public function addGuarantee()
    {
        $this->guarantees[] = '';
    }

    public function removeGuarantee($index)
    {
        unset($this->guarantees[$index]);
        $this->guarantees = array_values($this->guarantees);
    }

    public function render()
    {
        return view('livewire.projects');
    }

    public function createProject()
    {

        try {
            $upload_and_path = $this->uploadFile();

            $pdf_path = null;
            if ($this->pdf_file) {
                $pdf_path = $this->pdf_file->store('presentations', 'public');
                $pdf_path = PdfService::compress($pdf_path);
            }

            // Filter out empty guarantees
            $filteredGuarantees = array_values(array_filter(array_map('trim', $this->guarantees)));

            Project::create(
                [
                    'name' => $this->project['name'],
                    'description' => $this->project['description'],
                    'location' => $this->project['location'] ?? null,
                    'status' => $this->project['status'],
                    'project_type' => $this->project['project_type'],
                    'image_url' => $upload_and_path,
                    'map_url' => $this->project['map_url'] ?? null,
                    'pdf_path' => $pdf_path,
                    'guarantees' => ! empty($filteredGuarantees) ? $filteredGuarantees : null,
                ]
            );

            // Reset fields
            $this->reset(['project', 'image', 'pdf_file', 'guarantees']);
        } catch (\Throwable $th) {
            $this->addError('creating_error', 'هناك مشكلة حدثت أثناء إنشاء المشروع');
        }
    }

    public function uploadFile()
    {

        try {
            $path = ImageService::uploadAndProcess($this->image, 'uploads', 800);
        } catch (\Throwable $th) {
            $this->addError('catch_upload', 'هناك مشكلة حدثت أثناء رفع الصورة');
        }

        return $path;
    }

    public function deleteProject($id)
    {
        Project::find($id)->delete();
    }
}
