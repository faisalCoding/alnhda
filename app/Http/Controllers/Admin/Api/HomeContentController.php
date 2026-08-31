<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderFaqEntriesRequest;
use App\Http\Requests\Admin\StoreFaqEntryRequest;
use App\Http\Requests\Admin\UpdateHomeContentRequest;
use App\Http\Requests\Admin\UploadHeroImageRequest;
use App\Models\AppSettings;
use App\Models\FaqEntry;
use App\Services\HomeFacts;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * The words on the front of the site, as the panel edits them.
 */
class HomeContentController extends Controller
{
    public function __construct(private readonly HomeFacts $facts) {}

    public function index(): JsonResponse
    {
        $settings = AppSettings::current();

        return response()->json([
            'data' => [
                'hero' => [
                    'hero_eyebrow' => $settings->hero_eyebrow,
                    'hero_title' => $settings->hero_title,
                    'hero_subtitle' => $settings->hero_subtitle,
                    'hero_primary_label' => $settings->hero_primary_label,
                    'hero_secondary_label' => $settings->hero_secondary_label,
                ],
                // What each field falls back to, so the screen can show an
                // editor the text they are about to replace instead of an
                // empty box that hides it.
                'hero_defaults' => HomeFacts::HERO_DEFAULTS,
                'hero_image_path' => $settings->hero_image_path,
                'hero_image_url' => $this->facts->heroImage(),
                'hero_image_is_uploaded' => filled($settings->hero_image_path),
                'sections' => collect(HomeFacts::SECTIONS)
                    ->map(fn (string $label, string $key): array => [
                        'key' => $key,
                        'label' => $label,
                        'visible' => $this->facts->showsSection($key),
                    ])
                    ->values()
                    ->all(),
                'home_guarantees' => $settings->home_guarantees ?? [],
                'guarantee_defaults' => $this->facts->projectGuarantees(),
                'faq' => FaqEntry::query()->ordered()->get()->map(fn (FaqEntry $entry): array => [
                    'id' => $entry->id,
                    'question' => $entry->question,
                    'answer' => $entry->answer,
                ])->all(),
                'faq_defaults' => $this->facts->derivedFaq(),
            ],
        ]);
    }

    public function update(UpdateHomeContentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $guarantees = collect($validated['home_guarantees'] ?? [])
            ->map(fn ($guarantee): string => trim((string) $guarantee))
            ->filter()
            ->values()
            ->all();

        AppSettings::current()->update([
            'hidden_home_sections' => ($validated['hidden_home_sections'] ?? []) === []
                ? null
                : array_values($validated['hidden_home_sections']),
            'hero_eyebrow' => $validated['hero_eyebrow'] ?? null,
            'hero_title' => $validated['hero_title'] ?? null,
            'hero_subtitle' => $validated['hero_subtitle'] ?? null,
            'hero_primary_label' => $validated['hero_primary_label'] ?? null,
            'hero_secondary_label' => $validated['hero_secondary_label'] ?? null,
            'home_guarantees' => $guarantees === [] ? null : $guarantees,
        ]);

        return $this->index();
    }

    /**
     * Replaces the picture behind the front of the site. The previous upload is
     * deleted rather than left behind: nothing else ever points at it.
     */
    public function uploadHeroImage(UploadHeroImageRequest $request): JsonResponse
    {
        $settings = AppSettings::current();
        $previous = $settings->hero_image_path;

        $settings->update(['hero_image_path' => ImageService::uploadHeroImage($request->file('image'))]);

        if (filled($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $this->index();
    }

    /**
     * Drops the upload, so the front of the site goes back to showing whichever
     * project is first.
     */
    public function destroyHeroImage(): JsonResponse
    {
        $settings = AppSettings::current();
        $previous = $settings->hero_image_path;

        $settings->update(['hero_image_path' => null]);

        if (filled($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $this->index();
    }

    public function storeFaq(StoreFaqEntryRequest $request): JsonResponse
    {
        $entry = FaqEntry::query()->create([
            ...$request->validated(),
            'sort_order' => (int) FaqEntry::query()->max('sort_order') + 1,
        ]);

        return response()->json(['data' => [
            'id' => $entry->id,
            'question' => $entry->question,
            'answer' => $entry->answer,
        ]], 201);
    }

    public function updateFaq(StoreFaqEntryRequest $request, FaqEntry $faqEntry): JsonResponse
    {
        $faqEntry->update($request->validated());

        return response()->json(['data' => [
            'id' => $faqEntry->id,
            'question' => $faqEntry->question,
            'answer' => $faqEntry->answer,
        ]]);
    }

    public function destroyFaq(FaqEntry $faqEntry): JsonResponse
    {
        $id = $faqEntry->id;
        $faqEntry->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    public function reorderFaq(ReorderFaqEntriesRequest $request): JsonResponse
    {
        foreach ($request->validated()['ids'] as $position => $id) {
            FaqEntry::query()->whereKey($id)->update(['sort_order' => $position]);
        }

        return $this->index();
    }

    /**
     * Copies the answers the site writes for itself into the table, so an
     * admin edits real text rather than starting from a blank page. Refused
     * once questions exist, since it would duplicate them.
     */
    public function importFaq(): JsonResponse
    {
        if (FaqEntry::query()->exists()) {
            return response()->json(['message' => 'الأسئلة موجودة بالفعل — احذفها أولًا إن أردت استيرادها من جديد.'], 422);
        }

        foreach ($this->facts->derivedFaq() as $position => $entry) {
            FaqEntry::query()->create([...$entry, 'sort_order' => $position]);
        }

        return $this->index();
    }
}
