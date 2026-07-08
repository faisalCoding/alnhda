{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
@php
    $latestProjectUpdate = $projects->max('updated_at');
    $latestArticleUpdate = $articles->max('updated_at');
    $latestContentUpdate = collect([$latestProjectUpdate, $latestArticleUpdate, $properties->max('updated_at')])
        ->filter()
        ->max();
@endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    {{-- Static Pages --}}
    <url>
        <loc>{{ route('welcome') }}</loc>
        @if ($latestContentUpdate)
            <lastmod>{{ $latestContentUpdate->toAtomString() }}</lastmod>
        @endif
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('projects') }}</loc>
        @if ($latestProjectUpdate)
            <lastmod>{{ $latestProjectUpdate->toAtomString() }}</lastmod>
        @endif
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ route('articles') }}</loc>
        @if ($latestArticleUpdate)
            <lastmod>{{ $latestArticleUpdate->toAtomString() }}</lastmod>
        @endif
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ route('about-us') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc>{{ route('contact-us') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc>{{ route('privacy-policy') }}</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ route('terms-of-use') }}</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>

    {{-- Projects --}}
    @foreach ($projects as $project)
        <url>
            <loc>{{ route('project', $project) }}</loc>
            <lastmod>{{ $project->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
        </url>
    @endforeach

    {{-- Properties --}}
    @foreach ($properties as $property)
        <url>
            <loc>{{ route('properties', $property) }}</loc>
            <lastmod>{{ $property->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
        </url>
    @endforeach

    {{-- Articles --}}
    @foreach ($articles as $article)
        <url>
            <loc>{{ route('article', $article) }}</loc>
            <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>
