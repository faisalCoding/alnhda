<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSeoDefaultsRequest;
use App\Http\Requests\Admin\UpdateSeoPageRequest;
use App\Http\Requests\Admin\UpdateSeoRecordRequest;
use App\Http\Requests\Admin\UploadSeoImageRequest;
use App\Models\AppSettings;
use App\Models\Article;
use App\Models\Project;
use App\Models\Properties;
use App\Models\SeoMeta;
use App\Models\SeoPage;
use App\Services\ImageService;
use App\Services\SeoPageDefaults;
use App\Services\SeoRecordDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search and social presentation, in the three layers it resolves through:
 * site defaults, the fixed pages, and per-record overrides.
 */
class SeoController extends Controller
{
    /**
     * Record types an override may be attached to.
     *
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'project' => Project::class,
        'article' => Article::class,
        'properties' => Properties::class,
    ];

    public function __construct(
        private readonly SeoRecordDefaults $recordDefaults,
        private readonly SeoPageDefaults $pageDefaults,
    ) {}

    /**
     * Defaults and fixed pages together: the panel opens on both, and two calls
     * would let it paint a half-loaded form.
     */
    public function index(): JsonResponse
    {
        $settings = AppSettings::current();
        $saved = SeoPage::query()->get()->keyBy('route_name');

        $pages = collect(SeoPage::editableRoutes())->map(function (string $route) use ($saved): array {
            $row = $saved->get($route);
            $auto = $this->pageDefaults->for($route);

            return [
                'route_name' => $route,
                'label' => $this->pageDefaults->label($route),
                'url' => route($route),
                'title' => $row?->title,
                'description' => $row?->description,
                'image_path' => $row?->image_path,
                'image_url' => $this->imageUrl($row?->image_path),
                'og_type' => $row?->og_type,
                'noindex' => (bool) $row?->noindex,
                'auto' => [
                    'title' => $auto['title'],
                    'description' => $auto['description'],
                    'image_url' => null,
                ],
            ];
        })->all();

        return response()->json([
            'data' => [
                'defaults' => [
                    'seo_default_title' => $settings->seo_default_title,
                    'seo_default_description' => $settings->seo_default_description,
                    'seo_default_image_path' => $settings->seo_default_image_path,
                    'seo_default_image_url' => $this->imageUrl($settings->seo_default_image_path),
                ],
                'pages' => $pages,
                'social_size' => [ImageService::SOCIAL_WIDTH, ImageService::SOCIAL_HEIGHT],
                'site_name' => config('app.name'),
            ],
        ]);
    }

    public function updateDefaults(UpdateSeoDefaultsRequest $request): JsonResponse
    {
        $settings = AppSettings::current();
        $settings->fill($request->validated())->save();

        return response()->json(['data' => [
            'seo_default_title' => $settings->seo_default_title,
            'seo_default_description' => $settings->seo_default_description,
            'seo_default_image_path' => $settings->seo_default_image_path,
            'seo_default_image_url' => $this->imageUrl($settings->seo_default_image_path),
        ]]);
    }

    public function updatePage(UpdateSeoPageRequest $request, string $routeName): JsonResponse
    {
        abort_unless(in_array($routeName, SeoPage::editableRoutes(), true), 404);

        $page = SeoPage::query()->updateOrCreate(
            ['route_name' => $routeName],
            $request->validated(),
        );

        $auto = $this->pageDefaults->for($routeName);

        return response()->json(['data' => [
            'route_name' => $page->route_name,
            'label' => $page->label(),
            'url' => route($routeName),
            'title' => $page->title,
            'description' => $page->description,
            'image_path' => $page->image_path,
            'image_url' => $this->imageUrl($page->image_path),
            'og_type' => $page->og_type,
            'noindex' => $page->noindex,
            'auto' => ['title' => $auto['title'], 'description' => $auto['description'], 'image_url' => null],
        ]]);
    }

    /**
     * Records of one type, with what each currently says and whatever has been
     * saved over it. Searchable because a listing of every unit is unusable.
     */
    public function records(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', 'project');

        abort_unless(array_key_exists($type, self::TYPES), 404);

        $model = self::TYPES[$type];
        $search = trim((string) $request->query('search', ''));

        $records = $model::query()
            ->with(['seoMeta', ...($type === 'properties' ? ['project', 'propertiesImages'] : [])])
            ->when($search !== '', fn ($query) => $query->where(
                $type === 'article' ? 'title' : 'name',
                'like',
                '%'.$search.'%'
            ))
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $records->map(fn (Model $record): array => $this->recordPayload($type, $record))->all(),
        ]);
    }

    public function updateRecord(UpdateSeoRecordRequest $request, string $type, int $id): JsonResponse
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $record = self::TYPES[$type]::query()->findOrFail($id);

        $meta = $record->seoMeta ?? new SeoMeta;
        $meta->fill($request->validated());

        // An emptied form means "go back to automatic", so the row goes rather
        // than lingering as a set of blanks that still count as an override.
        if ($meta->isEmpty()) {
            $record->seoMeta?->delete();
            $record->unsetRelation('seoMeta');
        } else {
            $record->seoMeta()->save($meta);
            $record->setRelation('seoMeta', $meta);
        }

        return response()->json(['data' => $this->recordPayload($type, $record)]);
    }

    public function uploadImage(UploadSeoImageRequest $request): JsonResponse
    {
        $path = ImageService::uploadSocialImage($request->file('image'));

        return response()->json([
            'data' => [
                'image_path' => $path,
                'image_url' => $this->imageUrl($path),
                'width' => ImageService::SOCIAL_WIDTH,
                'height' => ImageService::SOCIAL_HEIGHT,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(string $type, Model $record): array
    {
        $auto = $this->recordDefaults->for($record);
        $meta = $record->seoMeta;

        return [
            'type' => $type,
            'id' => $record->getKey(),
            'name' => $type === 'article' ? $record->title : $record->name,
            'url' => route($type === 'properties' ? 'properties' : $type, $record),
            'title' => $meta?->title,
            'description' => $meta?->description,
            'image_path' => $meta?->image_path,
            'image_url' => $this->imageUrl($meta?->image_path),
            'noindex' => (bool) $meta?->noindex,
            'auto' => [
                'title' => $auto['title'],
                'description' => $auto['description'],
                'image_url' => $auto['image'],
            ],
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        return blank($path) ? null : asset('storage/'.ltrim($path, '/'));
    }
}
