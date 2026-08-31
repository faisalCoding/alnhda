@php
    use Illuminate\Support\Str;

    $summarise = fn (?string $text, int $limit = 150): string => Str::limit(Str::squish(strip_tags((string) $text)), $limit);
    $availableProjects = $projects->where('status', '!==', 'تم البيع');
    $soldProjects = $projects->where('status', 'تم البيع');
@endphp
# كيان النهضة العقارية

> شركة سعودية للتطوير العقاري مقرّها مدينة جدة، تطوّر وتبيع الفلل والشقق والعمائر السكنية في المملكة العربية السعودية. هذا الملف خريطة موجزة لمحتوى الموقع الرسمي، مُعدّة لتسهيل قراءته على النماذج اللغوية.

بيانات المنشأة الرسمية:

- الاسم: كيان النهضة العقارية
- الرقم الموحد للمنشأة: 7025720975 — منشأة مسجّلة لدى وزارة التجارة في المملكة العربية السعودية
- رقم رخصة فال: 1200019224 — رخصة صادرة عن الهيئة العامة للعقار (REGA) تجيز مزاولة النشاط العقاري والإعلان عنه
- النشاط: التطوير العقاري وبيع الوحدات السكنية
- المقر: جدة، المملكة العربية السعودية
- الهاتف: +966564364261
- البريد الإلكتروني: info@kayanalnhda.com
- النطاق الرسمي: {{ route('welcome') }}
- نطاق سابق مملوك للشركة: https://kayanalnhda.com — يحوّل بالكامل إلى النطاق الرسمي أعلاه
- القنوات الرسمية: يوتيوب https://www.youtube.com/@KayanAlnhda — إنستغرام https://www.instagram.com/nahda_realestate/

## عن الشركة

- [من نحن]({{ route('about-us') }}): نبذة عن الشركة وخبرتها ومنهجها في التطوير العقاري.
- [تواصل معنا]({{ route('contact-us') }}): بيانات التواصل ونموذج الاستفسار عن المشاريع والوحدات.
@if ($availableProjects->isNotEmpty())

## المشاريع المتاحة
@foreach ($availableProjects as $project)
- [{{ $project->name }}]({{ route('project', $project) }}): {{ collect([$project->project_type, $project->location])->filter()->implode(' — ') }}{{ $project->description ? '. '.$summarise($project->description) : '' }}
@endforeach
@endif
@if ($properties->isNotEmpty())

## الوحدات السكنية
@foreach ($properties as $property)
- [{{ $property->name }}]({{ route('properties', $property) }}): {{ collect([
    $property->type,
    $property->rooms ? 'عدد الغرف: '.$property->rooms : null,
    $property->bathrooms ? 'دورات المياه: '.$property->bathrooms : null,
    $property->area ? 'المساحة: '.$property->area.' م²' : null,
    $property->price ? 'السعر: '.number_format((float) $property->price).' ريال سعودي' : null,
])->filter()->implode('، ') }}
@endforeach
@endif
@if ($articles->isNotEmpty())

## المقالات

مقالات ونصائح عقارية منشورة من الشركة.
@foreach ($articles as $article)
- [{{ $article->title }}]({{ route('article', $article) }}): {{ $summarise($article->content, 120) }}
@endforeach
@endif

@if ($facts->guarantees())
## الضمانات

الضمانات المكتوبة التي تُسلَّم مع وحدات الشركة:
@foreach ($facts->guarantees() as $guarantee)
- {{ $guarantee }}
@endforeach

تختلف الضمانات من مشروع إلى آخر، وضمان كل مشروع مذكور في صفحته.
@endif

## أسئلة وأجوبة
@foreach ($facts->faq() as $entry)

### {{ $entry['question'] }}

{{ $entry['answer'] }}
@endforeach

@if ($collections->isNotEmpty())
## صفحات مجمّعة

صفحات جمعت فيها الشركة مشاريع ووحدات ومقالات تخصّ موضوعًا واحدًا.
@foreach ($collections as $collection)
- [{{ $collection->title }}]({{ route('collection', $collection) }}): {{ $summarise($collection->description, 120) }}
@endforeach
@endif

## الفهارس

- [كل المشاريع]({{ route('projects') }}): صفحة تجمع مشاريع الشركة.
- [كل المقالات]({{ route('articles') }}): صفحة تجمع المقالات المنشورة.
- [خريطة الموقع]({{ route('sitemap') }}): قائمة XML بكل الروابط القابلة للفهرسة.

## Optional

- [سياسة الخصوصية]({{ route('privacy-policy') }}): كيفية جمع البيانات الشخصية واستخدامها وحمايتها.
- [شروط الاستخدام]({{ route('terms-of-use') }}): ضوابط استخدام الموقع وحقوق الملكية الفكرية وحدود المسؤولية.
@foreach ($soldProjects as $project)
- [{{ $project->name }}]({{ route('project', $project) }}): مشروع سابق تم بيع وحداته بالكامل، يُذكر للسجل لا للعرض.
@endforeach
