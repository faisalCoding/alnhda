<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateArticle extends Component
{
    use WithFileUploads;

    public $title;

    public $content;

    public $image;

    public function createArticle()
    {
        $this->validate([
            'title' => 'required',
            'image' => 'nullable|image',
            'content' => 'nullable|string',
        ]);

        $imagePath = $this->image ? $this->image->store('articles', 'public') : '\/img\/article.jpg';

        Article::create([
            'title' => $this->title,
            'image_article' => $imagePath,
            'content' => $this->content,
        ]);

        return redirect()->route('dashboard')->with('message', 'تم إنشاء المقال بنجاح');
    }

    public function render()
    {
        return view('livewire.create-article');
    }

    public function deleteArticle($id)
    {
        $article = Article::find($id);
        if ($article) {
            $article->delete();
            $this->dispatch('articleDeleted');
        }
    }
}
