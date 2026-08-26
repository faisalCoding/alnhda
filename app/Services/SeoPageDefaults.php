<?php

namespace App\Services;

/**
 * What each fixed page says about itself when nobody has overridden it.
 *
 * These were literal strings inside the seven Blade files, which put them out
 * of reach of the panel: it could not show an editor the text they were about
 * to replace without restating it, and a restatement drifts. The Blade files
 * no longer carry them — this is the only copy.
 */
class SeoPageDefaults
{
    /**
     * @var array<string, array{label: string, title: string, description: string}>
     */
    public const PAGES = [
        'welcome' => [
            'label' => 'الصفحة الرئيسية',
            'title' => 'تطوير عقاري وفلل وشقق سكنية فاخرة',
            'description' => 'شركة كيان النهضة العقارية - روّاد التطوير العقاري في المملكة العربية السعودية. نقدم أرقى الفلل والحلول السكنية والاستثمارية بأعلى معايير الجودة والضمانات. تصفح مشاريعنا المتميزة الآن.',
        ],
        'projects' => [
            'label' => 'المشاريع',
            'title' => 'مشاريعنا العقارية - فلل وشقق للبيع في جدة',
            'description' => 'تصفح مشاريع كيان النهضة العقارية السكنية في جدة — فلل وشقق بمواصفات عصرية وضمانات شاملة. اكتشف المشروع المناسب لك الآن.',
        ],
        'articles' => [
            'label' => 'المقالات',
            'title' => 'مقالات ونصائح عقارية',
            'description' => 'مقالات ونصائح من خبراء كيان النهضة العقارية حول شراء الفلل والشقق والاستثمار العقاري وأحدث اتجاهات السوق العقاري في السعودية.',
        ],
        'about-us' => [
            'label' => 'من نحن',
            'title' => 'من نحن',
            'description' => 'كيان النهضة العقارية - خبرة 40 عاماً في التطوير العقاري والهندسة المعمارية في المملكة العربية السعودية.',
        ],
        'contact-us' => [
            'label' => 'تواصل معنا',
            'title' => 'تواصل معنا',
            'description' => 'تواصل مع كيان النهضة العقارية للاستفسار عن مشاريعنا وخدماتنا في جدة، المملكة العربية السعودية.',
        ],
        'privacy-policy' => [
            'label' => 'سياسة الخصوصية',
            'title' => 'سياسة الخصوصية',
            'description' => 'سياسة الخصوصية في كيان النهضة العقارية: ما المعلومات التي نجمعها عند تصفح الموقع أو التواصل معنا، وكيف نستخدمها ونحميها، ومتى تُشارك مع أطراف أخرى.',
        ],
        'terms-of-use' => [
            'label' => 'شروط الاستخدام',
            'title' => 'شروط الاستخدام',
            'description' => 'شروط استخدام موقع كيان النهضة العقارية: الملكية الفكرية للمحتوى، وضوابط استخدام الموقع، ودقة معلومات المشاريع، وروابط الطرف الثالث، وإخلاء المسؤولية.',
        ],
    ];

    /**
     * @return array{title: ?string, description: ?string}
     */
    public function for(string $routeName): array
    {
        $page = self::PAGES[$routeName] ?? null;

        return [
            'title' => $page['title'] ?? null,
            'description' => $page['description'] ?? null,
        ];
    }

    public function label(string $routeName): string
    {
        return self::PAGES[$routeName]['label'] ?? $routeName;
    }
}
