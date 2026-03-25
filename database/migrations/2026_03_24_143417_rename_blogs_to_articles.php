<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename table
        Schema::rename('blogs', 'articles');

        // 2. Add content column and rename image_blog
        Schema::table('articles', function (Blueprint $table) {
            $table->renameColumn('image_blog', 'image_article');
            $table->longText('content')->nullable();
        });

        // 3. Migrate data safely using DB facade
        $articles = \Illuminate\Support\Facades\DB::table('articles')->get();
        foreach ($articles as $article) {
            $content = '';
            // Get tags ordered by order_by
            $tags = \Illuminate\Support\Facades\DB::table('html_tags')
                ->where('blog_id', $article->id)
                ->orderBy('order_by')
                ->get();

            foreach ($tags as $tag) {
                // Get inner content
                $innerContent = '';
                $textNode = \Illuminate\Support\Facades\DB::table('html_tag_texts')
                    ->where('html_tag_id', $tag->id)
                    ->first();
                if ($textNode) {
                    $innerContent = $textNode->content;
                }

                $classes = $tag->classes ? ' class="'.$tag->classes.'"' : '';
                $attributes = $tag->tag_attributes ? ' '.$tag->tag_attributes : '';
                $content .= "<{$tag->tag_name}{$classes}{$attributes}>{$innerContent}</{$tag->tag_name}>";
            }

            \Illuminate\Support\Facades\DB::table('articles')
                ->where('id', $article->id)
                ->update(['content' => $content]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('content');
            $table->renameColumn('image_article', 'image_blog');
        });
        Schema::rename('articles', 'blogs');
    }
};
