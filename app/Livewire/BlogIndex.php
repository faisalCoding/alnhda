<?php

namespace App\Livewire;

use App\Models\Blog;
use Livewire\Component;
use Livewire\WithFileUploads;

class BlogIndex extends Component
{
    use WithFileUploads;

    public $title;
    public $content;
    public $image_blog;

    public function render()
    {
        return view('livewire.blog-index');
    }

    public function createBlog() {
        
        try {
            $this->uploadFile();

            Blog::create([
                'title' => $this->title,
                'content' => $this->content,
                'image_blog' => $this->image_blog
            ]);
            
        } catch (\Throwable $th) {
            $this->addError('creating_error', 'هناك مشكلة حدثت أثناء إنشاء الحساب');
        }
        
    }

    public function uploadFile(){
        
        try {
            $this->image_blog =  $this->image_blog->store('uploads','public');
        } catch (\Throwable $th) {
            $this->addError('catch_upload', 'هناك مشكلة حدثت أثناء رفع الصورة');
        }
    }
}
