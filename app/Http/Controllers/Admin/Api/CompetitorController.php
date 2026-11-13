<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompetitorRequest;
use App\Http\Resources\Admin\ManualMarketUnitResource;
use App\Models\ManualMarketUnit;
use App\Models\Project;
use App\Models\Properties;
use App\Services\Market\PlaceName;
use App\Services\Market\ProjectMarketComparison;
use App\Services\Market\Quantiles;
use App\Services\Market\SalesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The market units chosen by hand as each of the company's units' competitors,
 * set out under the unit they compete with.
 */
class CompetitorController extends Controller
{
    public function __construct(private readonly ProjectMarketComparison $comparison) {}

    /**
     * Every project and unit, each with its competitors and how far its own
     * price per metre sits from theirs.
     */
    public function index(Request $request): JsonResponse
    {
        $city = $request->query('city');
        $cityKey = PlaceName::key(is_string($city) && $city !== '' ? $city : SalesImporter::DETAILED_CITIES[0]);
        $kinds = $this->comparison->kinds($cityKey);

        $projects = Project::query()
            ->ordered()
            ->with(['properties.competitors'])
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'location' => $project->location,
                'units' => $project->properties
                    ->map(fn (Properties $unit): array => $this->unit($unit, $project, $kinds, $request))
                    ->values()
                    ->all(),
            ])
            ->all();

        return response()->json(['data' => ['projects' => $projects]]);
    }

    /**
     * Chooses a market unit as one of a unit's competitors, or changes the
     * note kept on one already chosen.
     */
    public function store(StoreCompetitorRequest $request, Properties $unit): JsonResponse
    {
        $unit->competitors()->syncWithoutDetaching([
            $request->validated('manual_market_unit_id') => ['note' => $request->validated('note')],
        ]);

        return response()->json(['data' => ['unit' => $unit->id, 'competitors' => $unit->competitors()->count()]], 201);
    }

    /**
     * Takes a market unit off a unit's competitors. The market unit itself stays.
     */
    public function destroy(Properties $unit, ManualMarketUnit $manualMarketUnit): JsonResponse
    {
        $unit->competitors()->detach($manualMarketUnit->id);

        return response()->json(['data' => ['unit' => $unit->id, 'competitors' => $unit->competitors()->count()]]);
    }

    /**
     * @param  list<string>  $kinds
     * @return array<string, mixed>
     */
    private function unit(Properties $unit, Project $project, array $kinds, Request $request): array
    {
        $measure = $this->comparison->measure($unit, $project, $kinds);

        $competitors = $unit->competitors
            ->sortBy('price_per_meter')
            ->values()
            ->map(fn (ManualMarketUnit $competitor): array => (new ManualMarketUnitResource($competitor))->resolve($request) + [
                'note' => $competitor->pivot->note,
                // How far the unit's own price per metre sits from this one's.
                'gap' => $this->gap($measure['rate'], (float) $competitor->price_per_meter),
            ]);

        // Each property once, however many of its listings were chosen.
        $rates = ManualMarketUnit::oneEach($unit->competitors)->pluck('price_per_meter')->map(fn (mixed $rate): float => (float) $rate)->all();
        $spread = Quantiles::spread($rates);

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'status' => $unit->status,
            'kind' => $measure['kind'],
            'area' => (float) $unit->area > 0 ? (float) $unit->area : null,
            'land_area' => $measure['land'] > 0 ? $measure['land'] : null,
            'basis' => $measure['basis'],
            'price' => $measure['price'] > 0 ? $measure['price'] : null,
            'rate' => $measure['rate'],
            'competitors' => $competitors->all(),
            'summary' => $rates === [] ? null : ['properties' => count($rates)] + $spread + [
                'gap' => $spread['median'] === null ? null : $this->gap($measure['rate'], $spread['median']),
            ],
        ];
    }

    /**
     * How far one price per metre sits from another, in whole percent.
     */
    private function gap(?float $ours, float $theirs): ?int
    {
        return $ours === null || $theirs <= 0 ? null : (int) round(($ours - $theirs) / $theirs * 100);
    }
}
