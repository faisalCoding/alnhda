<?php

namespace App\Services;

use App\Models\AppSettings;
use App\Models\FaqEntry;
use App\Models\Project;
use App\Models\Properties;

/**
 * What the home page states as fact about the company, in one copy.
 *
 * The page a visitor reads and the FAQPage node a crawler reads must never
 * disagree, and they would the moment each carried its own wording. Anything
 * that depends on records — the guarantees, the districts, the kinds of unit —
 * is read from the records themselves, so the page cannot keep promising a
 * guarantee that was edited away in the panel.
 *
 * An answer whose facts are missing is dropped rather than left half-written:
 * a question answered with nothing is worse than a question not asked.
 */
class HomeFacts
{
    /**
     * The words the front of the site falls back to. They are the floor, not
     * the ceiling: anything typed in the panel wins over them.
     *
     * @var array<string, string>
     */
    public const HERO_DEFAULTS = [
        'eyebrow' => 'أساسات راسخة.. لمستقبل آمن',
        'title' => 'تطوير وبيع فلل وشقق سكنية في جدة',
        'subtitle' => 'مشاريعنا نبنيها لتدوم و تبقى نموذجا للجودة و الاستدامة',
        'primary_label' => 'تصفّح مشاريعنا في جدة',
        'secondary_label' => 'تواصل معنا',
    ];

    /**
     * The sections of the home page an admin may switch off, in the order the
     * page lays them out. Stored as what is hidden rather than what is shown,
     * so a section added later appears without anybody enabling it.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'about' => 'من نحن',
        'projects' => 'المشاريع',
        'districts' => 'أين نبني في جدة',
        'guarantees' => 'الضمانات',
        'articles' => 'المقالات',
        'faq' => 'الأسئلة المتكررة',
    ];

    public const PHONE_LOCAL = '056 436 4261';

    public const EMAIL = 'info@kayanalnhda.com';

    public const UNIFIED_NUMBER = '7025720975';

    public const FAL_LICENCE = '1200019224';

    /**
     * Arabic names the kinds of unit in the plural on a page like this, while
     * the records hold the singular a form was filled in with.
     *
     * @var array<string, string>
     */
    private const PLURALS = [
        'شقة' => 'شقق',
        'فيلا' => 'فلل',
        'دور' => 'أدوار',
        'عمارة' => 'عمائر',
        'أرض' => 'أراضٍ',
    ];

    /**
     * The words on the front of the site.
     *
     * @return array{eyebrow: string, title: string, subtitle: string, primary_label: string, secondary_label: string}
     */
    public function hero(): array
    {
        $settings = AppSettings::current();

        $hero = [];

        foreach (self::HERO_DEFAULTS as $key => $default) {
            $written = $settings->{'hero_'.$key};
            $hero[$key] = filled($written) ? (string) $written : $default;
        }

        return $hero;
    }

    /**
     * The picture behind the front of the site: the one uploaded in the panel,
     * or else the cover of whichever project the panel put first, or else the
     * photograph the site ships with.
     */
    public function heroImage(): string
    {
        $uploaded = AppSettings::current()->hero_image_path;

        if (filled($uploaded)) {
            return asset('storage/'.ltrim((string) $uploaded, '/'));
        }

        $featured = $this->featuredProject();

        return $featured ? asset('storage/'.$featured->image_url) : asset('img/homebg.webp');
    }

    public function heroImageAlt(): string
    {
        $featured = filled(AppSettings::current()->hero_image_path) ? null : $this->featuredProject();

        return $featured
            ? $featured->name.' — مشروع سكني لشركة كيان النهضة العقارية في '.$this->clean((string) $featured->location)
            : 'مشروع سكني لشركة كيان النهضة العقارية في جدة';
    }

    private function featuredProject(): ?Project
    {
        return Project::query()->ordered()->whereNotNull('image_url')->first();
    }

    /**
     * Whether the page draws one of its sections.
     */
    public function showsSection(string $key): bool
    {
        return ! in_array($key, $this->hiddenSections(), true);
    }

    /**
     * @return list<string>
     */
    public function hiddenSections(): array
    {
        return collect(AppSettings::current()->hidden_home_sections ?? [])
            ->filter(fn ($key): bool => array_key_exists($key, self::SECTIONS))
            ->values()
            ->all();
    }

    /**
     * The guarantees the home page lists: the list written in the panel if
     * there is one, and otherwise every guarantee the projects themselves
     * carry.
     *
     * @return list<string>
     */
    public function guarantees(): array
    {
        $written = collect(AppSettings::current()->home_guarantees ?? [])
            ->map(fn ($guarantee): string => $this->clean((string) $guarantee))
            ->filter()
            ->values();

        return $written->isNotEmpty() ? $written->all() : $this->projectGuarantees();
    }

    /**
     * Every distinct guarantee the projects carry, in the order they were
     * first written. This is what the panel offers as a starting point.
     *
     * @return list<string>
     */
    public function projectGuarantees(): array
    {
        return Project::query()
            ->orderBy('sort_order')
            ->pluck('guarantees')
            ->flatten()
            ->map(fn ($guarantee): string => $this->clean((string) $guarantee))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Where the company builds, as its own projects record it.
     *
     * @return list<string>
     */
    public function districts(): array
    {
        return Project::query()
            ->pluck('location')
            ->map(fn ($location): string => $this->clean((string) $location))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The kinds of unit currently on offer, named in the plural.
     *
     * @return list<string>
     */
    public function unitKinds(): array
    {
        return Properties::query()
            ->pluck('type')
            ->map(fn ($type): string => $this->clean((string) $type))
            ->filter()
            ->unique()
            ->map(fn (string $type): string => self::PLURALS[$type] ?? $type)
            ->values()
            ->all();
    }

    /**
     * The projects of each district, in the order the panel arranged them.
     *
     * Districts are what a buyer searches by — «شقق في التيسير» far more often
     * than «شقق في جدة» — and the site never said one out loud before.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Project>>
     */
    public function projectsByDistrict(): array
    {
        return Project::query()
            ->ordered()
            ->get()
            ->groupBy(fn (Project $project): string => $this->clean((string) $project->location))
            ->reject(fn ($projects, $district): bool => $district === '')
            ->all();
    }

    /**
     * The questions the home page answers: the ones written in the panel if
     * any exist, and otherwise the ones the site builds from its own records.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faq(): array
    {
        $written = FaqEntry::query()
            ->ordered()
            ->get()
            ->map(fn (FaqEntry $entry): array => [
                'question' => $entry->question,
                'answer' => $entry->answer,
            ])
            ->all();

        return $written !== [] ? $written : $this->derivedFaq();
    }

    /**
     * The answers the site can write for itself, offered in the panel as a
     * starting point for the questions an admin then owns.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function derivedFaq(): array
    {
        $entries = [];

        if ($guarantees = $this->guarantees()) {
            $entries[] = [
                'question' => 'ما الضمانات التي تقدمونها على الوحدة؟',
                'answer' => 'تشمل ضمانات كيان النهضة العقارية '.$this->sentence($guarantees).'. '
                    .'وتختلف الضمانات من مشروع إلى آخر، والضمان الخاص بكل مشروع مذكور في صفحته.',
            ];
        }

        if ($districts = $this->districts()) {
            $entries[] = [
                'question' => 'أين تقع مشاريع كيان النهضة العقارية؟',
                'answer' => 'جميع مشاريع الشركة داخل مدينة جدة في المملكة العربية السعودية، وتحديدًا في '
                    .$this->sentence($districts).'. وموقع كل مشروع على الخريطة معروض في صفحته.',
            ];
        }

        if ($kinds = $this->unitKinds()) {
            $entries[] = [
                'question' => 'ما أنواع الوحدات المتاحة للبيع؟',
                'answer' => 'تطرح الشركة '.$this->sentence($kinds).' سكنية في مشاريعها بجدة، '
                    .'وتُعرض مواصفات كل وحدة — المساحة وعدد الغرف ودورات المياه والواجهة — في صفحتها الخاصة.',
            ];
        }

        $entries[] = [
            'question' => 'هل كيان النهضة العقارية شركة مرخّصة رسميًا؟',
            'answer' => 'نعم. الشركة منشأة مسجّلة لدى وزارة التجارة برقم موحّد '.self::UNIFIED_NUMBER.'، '
                .'وتحمل رخصة فال رقم '.self::FAL_LICENCE.' الصادرة عن الهيئة العامة للعقار، '
                .'وهي الرخصة التي تجيز مزاولة النشاط العقاري والإعلان عنه في المملكة العربية السعودية.',
        ];

        $entries[] = [
            'question' => 'كيف أستفسر عن وحدة أو أحجز معاينة؟',
            'answer' => 'يمكن التواصل مع كيان النهضة العقارية هاتفيًا أو عبر واتساب على الرقم '.self::PHONE_LOCAL.'، '
                .'أو بالبريد الإلكتروني '.self::EMAIL.'، أو بتعبئة نموذج الاستفسار في صفحة «تواصل معنا».',
        ];

        return $entries;
    }

    /**
     * Joins items the way the sentence would be spoken, not with commas alone.
     *
     * @param  list<string>  $items
     */
    private function sentence(array $items): string
    {
        if (count($items) < 2) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode('، ', $items).' و'.$last;
    }

    /**
     * Panel fields arrive with stray directionality marks pasted in from other
     * documents, which would otherwise reach the page as invisible rubbish.
     */
    private function clean(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B\u{200E}\u{200F}\u{00A0}");
    }
}
