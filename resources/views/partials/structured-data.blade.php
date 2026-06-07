@php
    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateAgent',
        'name' => 'كيان النهضة العقارية',
        'url' => url('/'),
        'logo' => asset('img/KNicon.png'),
        'image' => asset('img/KNicon.png'),
        'telephone' => '+966564364261',
        'email' => 'info@kayanalnhda.com',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'جدة',
            'addressCountry' => 'SA',
        ],
        'sameAs' => [
            'https://www.youtube.com/@KayanAlnhda',
            'https://www.instagram.com/nahda_realestate/',
        ],
    ];
@endphp

<script type="application/ld+json">
    {!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
