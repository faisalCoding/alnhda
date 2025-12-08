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

    public $title;
    public $image;
    public $tags = [];

    public function mount()
    {
        // Initialize with one root container if needed, or empty
        $this->addTag(); 
    }

    public function addTag($parentId = null)
    {
        $newTag = [
            'id' => Str::uuid()->toString(),
            'tag_name' => 'div',
            'classes' => '',
            'order_by' => 0,
            'tag_attributes' => '',
            'has_text' => false,
            'content' => '',
            'children' => []
        ];

        if ($parentId === null) {
            $this->tags[] = $newTag;
        } else {
            $this->tags = $this->addChildTag($this->tags, $parentId, $newTag);
        }
    }

    private function addChildTag($tags, $parentId, $newTag)
    {
        foreach ($tags as &$tag) {
            if ($tag['id'] === $parentId) {
                $tag['children'][] = $newTag;
                return $tags;
            }
            if (!empty($tag['children'])) {
                $tag['children'] = $this->addChildTag($tag['children'], $parentId, $newTag);
            }
        }
        return $tags;
    }

    public function removeTag($tagId)
    {
        $this->tags = $this->removeChildTag($this->tags, $tagId);
    }

    private function removeChildTag($tags, $tagId)
    {
        foreach ($tags as $key => &$tag) {
            if ($tag['id'] === $tagId) {
                unset($tags[$key]);
                return array_values($tags);
            }
            if (!empty($tag['children'])) {
                $tag['children'] = $this->removeChildTag($tag['children'], $tagId);
            }
        }
        return array_values($tags);
    }

    public function updateTag($tagId, $field, $value)
    {
        $this->tags = $this->updateChildTag($this->tags, $tagId, $field, $value);
    }

    private function updateChildTag($tags, $tagId, $field, $value)
    {
        foreach ($tags as &$tag) {
            if ($tag['id'] === $tagId) {
                $tag[$field] = $value;
                return $tags;
            }
            if (!empty($tag['children'])) {
                $tag['children'] = $this->updateChildTag($tag['children'], $tagId, $field, $value);
            }
        }
        return $tags;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required',
            'image' => 'nullable|image',
        ]);

        $imagePath = $this->image ? $this->image->store('blogs', 'public') : null;

        $blog = Blog::create([
            'title' => $this->title,
            'image_blog' => $imagePath ?? '\/img\/blog.jpg',
        ]);

        foreach ($this->tags as $index => $tagData) {
            $this->saveTag($tagData, $blog->id, null, $index);
        }

        return redirect()->route('dashboard')->with('message', 'Created Blog Successfully');
    }

    private function saveTag($tagData, $blogId, $parentId = null, $orderBy = 0)
    {
        $tag = HtmlTag::create([
            'tag_name' => $tagData['tag_name'],
            'classes' => $tagData['classes'],
            'order_by' => $orderBy,
            'tag_attributes' => $tagData['tag_attributes'],
            'blog_id' => $parentId ? null : $blogId, 
            'html_tag_id' => $parentId
        ]);
        
        if (!$parentId) {
            $tag->blog_id = $blogId;
            $tag->save();
        }

        if ($tagData['has_text'] && !empty($tagData['content'])) {
            HtmlTagText::create([
                'html_tag_id' => $tag->id,
                'content' => $tagData['content']
            ]);
        }

        if (!empty($tagData['children'])) {
            foreach ($tagData['children'] as $index => $childData) {
                $this->saveTag($childData, $blogId, $tag->id, $index);
            }
        }
    }

    public function render()
    {
        return view('livewire.create-blog');
    }
}
