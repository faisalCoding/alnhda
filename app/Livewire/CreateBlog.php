<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Blog;
use App\Models\HtmlTag;
use App\Models\HtmlTagText;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

class CreateBlog extends Component
{
    use WithFileUploads;

    public $currentStep = 1;
    public $title;
    public $image;
    public $tags = []; // Tree structure
    public $blogId;

    public function mount()
    {
        // No initial action needed
    }

    public function createBlog()
    {
        $this->validate([
            'title' => 'required',
            'image' => 'nullable|image',
        ]);

        $imagePath = $this->image ? $this->image->store('blogs', 'public') : '\/img\/blog.jpg';

        $blog = Blog::create([
            'title' => $this->title,
            'image_blog' => $imagePath,
        ]);

        $this->blogId = $blog->id;
        $this->currentStep = 2;
        
        // Initialize with one root container if desired, or let user add one
        $this->addTag(null);
    }

    public function addTag($parentId = null)
    {
        HtmlTag::create([
            'html_tag_id' => $parentId ?: null,
            'tag_name' => 'div',
            'classes' => '',
            'order_by' => 0,
            'tag_attributes' => '',
            'blog_id' => $this->blogId,
        ]);
        
        $this->refreshTags();
    }

    public function updateTag($tagId, $field, $value)
    {
        $tag = HtmlTag::find($tagId);
        if (!$tag) return;

        if ($field === 'content') {
            $tag->content()->updateOrCreate([], ['content' => $value]);
        } elseif ($field === 'has_text') {
             // Logic for has_text toggle? 
             // Maybe just ensures HtmlTagText entry exists or deletes it.
             if(!$value) {
                 $tag->content()->delete();
             } else {
                 $tag->content()->create(['content' => '']);
             }
        } else {
            $tag->update([$field => $value]);
        }
        
        $this->refreshTags();
    }

    public function removeTag($tagId)
    {
        $tag = HtmlTag::find($tagId);
        if ($tag) {
            $tag->delete();
        }
        $this->refreshTags();
    }

    public function refreshTags()
    {
        $this->tags = $this->buildTree(null);
    }

    private function buildTree($parentId)
    {
        $tags = HtmlTag::where('blog_id', $this->blogId)
                        ->where('html_tag_id', $parentId)
                        ->orderBy('order_by')
                        ->get();
                        
        return $tags->map(function($tag) {
            $data = $tag->toArray();
            
            $contentModel = $tag->content;
            $data['content'] = $contentModel ? $contentModel->content : '';
            $data['has_text'] = !!$contentModel;
            
            $data['children'] = $this->buildTree($tag->id);
            return $data;
        })->toArray();
    }

    public function finish()
    {
        return redirect()->route('dashboard')->with('message', 'Blog created successfully');
    }

    public function render()
    {
        return view('livewire.create-blog');
    }
}
