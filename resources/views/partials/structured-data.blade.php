@php
    $siteGraph = app(\App\Services\SiteSchema::class)->graph($seo);
@endphp

<script type="application/ld+json">
    {!! json_encode($siteGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
