<?php

namespace App\Livewire;

use App\Models\Blog;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

class Articles extends Component
{
    use WithFileUploads;

    #[Validate('required|min:3')]
    public $title;

    #[Validate('required|min:10')]
    public $content;

    #[Validate('required|image|max:10240')] // 10MB max
    public $image;

    public function create()
    {
        $this->validate();

        try {
            $imagePath = $this->image->store('uploads', 'public');

            $blog = Blog::create([
                'title' => $this->title,
                'image_blog' => $imagePath,
            ]);

            // Save content using the existing HtmlTag structure
            // We create a container div with the text content inside it
            $tag = $blog->htmlTags()->create([
                'tag_name' => 'div',
                'classes' => 'text-gray-200', 
                'order_by' => 1,
            ]);

            $tag->content()->create([
                'content' => $this->content
            ]);

            $this->reset(['title', 'content', 'image']);
            session()->flash('message', 'تم إضافة المقال بنجاح');

        } catch (\Throwable $th) {
            $this->addError('general', 'حدث خطأ أثناء حفظ المقال: ' . $th->getMessage());
        }
    }

    public function delete($id)
    {
        $blog = Blog::find($id);
        if ($blog) {
            // Relationships should handle deletion if cascading, otherwise we might leave orphans.
            // Assuming simplified deletion for now.
            $blog->delete();
        }
    }

    public function removePhoto()
    {
        $this->image = null;
    }

    public function render()
    {
        return view('livewire.articles');
    }
}
