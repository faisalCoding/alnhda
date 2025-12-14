<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    {{-- Static Pages --}}
    <url>
        <loc>{{ route('welcome') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('projects') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ route('blogs') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ route('about-us') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc>{{ route('contact-us') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc>{{ route('privacy-policy') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ route('terms-of-use') }}</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
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

    {{-- Blogs/Articles --}}
    @foreach ($blogs as $blog)
        <url>
            <loc>{{ route('blog', $blog) }}</loc>
            <lastmod>{{ $blog->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>
