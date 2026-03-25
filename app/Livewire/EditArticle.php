<?php

namespace App\Livewire;

use App\Models\Article;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditArticle extends Component
{
    use WithFileUploads;

    public Article $articleModel;

    public $title;

    public $content;

    public $image;

    public $existingImage;

    public $articleId;

    public function mount(Article $article)
    {
        $this->articleModel = $article;
        $this->articleId = $article->id;
        $this->title = $article->title;
        $this->content = $article->content;
        $this->existingImage = $article->image_article;
    }

    public function updateArticle()
    {
        $this->validate([
            'title' => 'required',
            'image' => 'nullable|image',
            'content' => 'nullable|string',
        ]);

        $data = [
            'title' => $this->title,
            'content' => $this->content,
        ];

        if ($this->image) {
            $data['image_article'] = $this->image->store('articles', 'public');
            if ($this->existingImage && $this->existingImage != '\/img\/article.jpg' && $this->existingImage != '/img/article.jpg') {
                Storage::disk('public')->delete($this->existingImage);
            }
            $this->existingImage = $data['image_article'];
        }

        $this->articleModel->update($data);

        return redirect()->route('dashboard')->with('message', 'تم تحديث المقال بنجاح');
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.edit-article');
    }
}
