<?php

namespace App\Livewire;

use App\Models\Project;
use App\Services\ImageService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    #[Validate('nullable|url')]
    public $map_url;

    #[Validate('nullable|image|max:10240')]
    public $image;

    #[Validate('nullable|file|mimes:pdf|max:40960')]
    public $pdf_file;

    public $existingImage;

    public $existingPdf;

    public $guarantees = [];

    public function mount(Project $project)
    {
        $this->projectModel = $project;
        $this->name = $project->name;
        $this->description = $project->description;
        $this->location = $project->location;
        $this->status = $project->status;
        $this->project_type = $project->project_type;
        $this->existingImage = $project->image_url;
        $this->map_url = $project->map_url;
        $this->existingPdf = $project->pdf_path;
        $this->guarantees = $project->guarantees ?? [];
    }

    public function addGuarantee()
    {
        $this->guarantees[] = '';
    }

    public function removeGuarantee($index)
    {
        unset($this->guarantees[$index]);
        $this->guarantees = array_values($this->guarantees);
    }

    public function updateProject()
    {
        $this->validate();

        try {
            // Filter out empty guarantees
            $filteredGuarantees = array_values(array_filter(array_map('trim', $this->guarantees)));

            $data = [
                'name' => $this->name,
                'description' => $this->description,
                'location' => $this->location,
                'status' => $this->status,
                'project_type' => $this->project_type,
                'map_url' => $this->map_url ?: null,
                'guarantees' => ! empty($filteredGuarantees) ? $filteredGuarantees : null,
            ];

            if ($this->image) {
                $data['image_url'] = ImageService::uploadAndProcess($this->image, 'uploads', 800);
                Storage::disk('public')->delete($this->existingImage);
            }

            if ($this->pdf_file) {
                $newPdfPath = $this->pdf_file->store('presentations', 'public');
                $newPdfPath = PdfService::compress($newPdfPath);
                if ($this->existingPdf) {
                    Storage::disk('public')->delete($this->existingPdf);
                }
                $data['pdf_path'] = $newPdfPath;
                $this->existingPdf = $newPdfPath;
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
