{{--
    The market units chosen by hand as each of the company's units' competitors, set out under the unit they compete with.
    They are chosen from the hand-entered market units of the city, so a competitor is entered once, in the offers group,
    and chosen here.
--}}
<section x-show="view === 'competitors'" x-cloak class="space-y-4">
    <h2 class="text-lg font-extrabold" x-text="`المنافسون — ${offers?.city?.label ?? ''}`"></h2>

    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
        اختر لكل وحدة من وحداتنا الوحدات التي تنافسها في السوق، فتظهر تفاصيلها تحتها ومعها فرق سعر المتر عندنا عن كل منافس.
        المنافسون يُختارون من وحدات السوق المضافة يدويًا: أضف الوحدة هناك مرة، ثم اخترها هنا لأي عدد من وحداتنا.
        الفلل والدوبلكس تُحسب بمتر الأرض كما تُحسب في الإعلانات، متى أُدخلت مساحة أرض وحدتنا.
    </p>

    <p x-show="offers && !offers.units.length" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400">
        لا وحدات سوق مضافة لهذه المدينة بعد.
        <button type="button" class="font-bold text-primary-600 hover:underline dark:text-primary-300" @click="go('offers')">أضف وحدات رأيتها في السوق</button>
        لتختار منها المنافسين.
    </p>

    <p x-show="competitorsError" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="competitorsError"></p>

    <template x-for="project in (competitors?.projects ?? [])" :key="project.id">
        <div class="{{ $card }} space-y-4">
            <div class="flex flex-wrap items-baseline gap-3">
                <h3 class="font-extrabold" x-text="project.name"></h3>
                <span class="text-[11px] text-zinc-400" x-text="project.location"></span>
            </div>

            <p x-show="!project.units.length" class="text-sm text-zinc-400">لا وحدات في هذا المشروع.</p>

            <template x-for="unit in project.units" :key="unit.id">
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    {{-- Our unit, and where its price sits against its competitors as a whole --}}
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-extrabold" x-text="unit.name"></p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400"
                                x-text="[unit.kind ?? 'نوع غير محدد', unit.area ? `البناء ${number(unit.area)} م²` : '', unit.land_area ? `الأرض ${number(unit.land_area)} م²` : '', unit.price ? `${number(unit.price)} ر.س` : 'بلا سعر'].filter(Boolean).join(' · ')"></p>
                            <p class="text-xs">
                                <span class="text-zinc-500">سعر المتر عندنا:</span>
                                <span class="font-bold" x-text="rate(unit.rate)"></span>
                                <span class="text-zinc-400" x-show="unit.basis === 'land'">لمتر الأرض</span>
                                <span class="font-bold text-amber-600" x-show="unit.basis === 'unit_not_land' && unit.rate">لمتر البناء: أدخل مساحة الأرض لتصح المقارنة</span>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div x-show="unit.summary" class="rounded-xl bg-zinc-50 px-3 py-2 text-xs dark:bg-zinc-800/60">
                                <p>
                                    <span class="text-zinc-500">وسيط المنافسين:</span>
                                    <span class="font-bold" x-text="`${rate(unit.summary?.median)} ر.س/م²`"></span>
                                    <span class="text-zinc-400" x-text="`· ${number(unit.summary?.properties)} عقار`"></span>
                                </p>
                                <p class="font-bold"
                                    :class="{ 'text-red-500': competitorGap(unit.summary?.gap).direction === 'above', 'text-primary-600 dark:text-primary-300': competitorGap(unit.summary?.gap).direction === 'below', 'text-zinc-400': ['level', null].includes(competitorGap(unit.summary?.gap).direction) }"
                                    x-text="`وحدتنا ${competitorGap(unit.summary?.gap).label}`"></p>
                            </div>

                            <button type="button" class="{{ $ghostButton }}" @click="togglePicker(unit)"
                                x-text="pickingFor === unit.id ? 'إغلاق' : 'إضافة منافس'"></button>
                        </div>
                    </div>

                    {{-- The picker: market units of the same district and kind first, the nearest in size first --}}
                    <div x-show="pickingFor === unit.id" x-cloak class="space-y-3 rounded-xl bg-primary-500/5 p-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <input type="search" x-model="pickSearch" class="{{ $input }} max-w-xs" placeholder="ابحث بالعنوان أو الحي أو رقم الإعلان">
                            <input type="text" x-model="pickNote" maxlength="500" class="{{ $input }} max-w-xs" placeholder="ملاحظة على المنافس (اختياري)">
                            <label class="flex items-center gap-2 text-xs font-bold text-zinc-500">
                                <input type="checkbox" class="rounded border-zinc-300 text-primary-500" x-model="pickSameDistrict">
                                حي المشروع فقط
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-zinc-500">
                                <input type="checkbox" class="rounded border-zinc-300 text-primary-500" x-model="pickSameKind">
                                نفس النوع فقط
                            </label>
                        </div>

                        <div class="sheet-wrap is-inset">
                            <table class="sheet">
                                <thead>
                                    <tr>
                                        <th>الإعلان</th>
                                        <th>الحي</th>
                                        <th>النوع</th>
                                        <th>المساحة (م²)</th>
                                        <th>السعر (ر.س)</th>
                                        <th>سعر المتر</th>
                                        <th>الغرف والعمر</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="offer in candidates(unit, project)" :key="offer.id">
                                        <tr :class="!offer.fresh && 'text-zinc-400'">
                                            <td class="max-w-xs">
                                                <span class="block truncate font-bold" x-text="offer.label"></span>
                                                <span class="block text-[11px] text-zinc-400" dir="ltr" x-show="offer.site_label"
                                                    x-text="`${offer.site_label} · ${offer.listing_id ?? ''}`"></span>
                                            </td>
                                            <td x-text="offer.district"></td>
                                            <td x-text="offer.kind"></td>
                                            <td x-text="number(offer.area)"></td>
                                            <td x-text="number(offer.price)"></td>
                                            <td class="font-bold" x-text="rate(offer.rate)"></td>
                                            <td class="text-xs" x-text="offerDetails(offer) || '—'"></td>
                                            <td>
                                                <button type="button" class="text-xs font-bold text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-300"
                                                    :disabled="savingCompetitor" @click="addCompetitor(unit, offer)">اختيار</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="!candidates(unit, project).length">
                                        <td colspan="8" class="text-zinc-400">لا وحدات تطابق. وسّع البحث بإلغاء «حي المشروع فقط» أو «نفس النوع فقط».</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- The competitors chosen for this unit, with everything known about each --}}
                    <div x-show="unit.competitors.length" class="sheet-wrap is-inset">
                        <table class="sheet">
                            <thead>
                                <tr>
                                    <th>المنافس</th>
                                    <th>الحي</th>
                                    <th>النوع</th>
                                    <th>المساحة (م²)</th>
                                    <th>السعر (ر.س)</th>
                                    <th>سعر المتر</th>
                                    <th>فرق وحدتنا</th>
                                    <th>الغرف والعمر</th>
                                    <th>الشارع والواجهة</th>
                                    <th>النشر · التحديث</th>
                                    <th>ملاحظة</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="competitor in unit.competitors" :key="competitor.id">
                                    <tr :class="!competitor.fresh && 'text-zinc-400'">
                                        <td class="max-w-xs">
                                            <a x-show="competitor.source_url" :href="competitor.source_url" target="_blank" rel="noopener noreferrer"
                                                class="block truncate font-bold text-primary-600 hover:underline dark:text-primary-300" x-text="competitor.label"></a>
                                            <span x-show="!competitor.source_url" class="block truncate font-bold" x-text="competitor.label"></span>
                                            <span class="block text-[11px] text-zinc-400" dir="ltr" x-show="competitor.site_label"
                                                x-text="`${competitor.site_label} · ${competitor.listing_id ?? ''}`"></span>
                                            <span class="block text-[11px] font-bold text-amber-600" x-show="competitor.group_size > 1"
                                                x-text="`نفس العقار في ${number(competitor.group_size)} إعلانات`"></span>
                                            <span class="block text-[11px] font-bold text-amber-600" x-show="!competitor.fresh">رُصد قبل أكثر من سنة</span>
                                        </td>
                                        <td x-text="competitor.district"></td>
                                        <td x-text="competitor.kind"></td>
                                        <td x-text="number(competitor.area)"></td>
                                        <td x-text="number(competitor.price)"></td>
                                        <td class="font-bold" x-text="rate(competitor.rate)"></td>
                                        <td class="font-bold"
                                            :class="{ 'text-red-500': competitorGap(competitor.gap).direction === 'above', 'text-primary-600 dark:text-primary-300': competitorGap(competitor.gap).direction === 'below', 'text-zinc-400': ['level', null].includes(competitorGap(competitor.gap).direction) }"
                                            x-text="competitorGap(competitor.gap).label"></td>
                                        <td class="text-xs" x-text="offerDetails(competitor) || '—'"></td>
                                        <td class="text-xs" x-text="offerStreet(competitor) || '—'"></td>
                                        <td class="text-xs" dir="ltr" x-text="[competitor.posted_on, competitor.listing_updated_on].filter(Boolean).join(' · ') || '—'"></td>
                                        <td class="max-w-[12rem] text-xs" x-text="competitor.note || '—'"></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <button type="button" class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300"
                                                    @click="editCompetitorNote(unit, competitor)">ملاحظة</button>
                                                <button type="button" class="text-xs font-bold text-red-500 hover:underline"
                                                    @click="removeCompetitor(unit, competitor)">إزالة</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <p x-show="!unit.competitors.length && pickingFor !== unit.id" class="text-xs text-zinc-400">لم يُختر منافسون لهذه الوحدة بعد.</p>
                </div>
            </template>
        </div>
    </template>

    <p x-show="competitors && !competitors.projects.length" x-cloak
        class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
        لا مشاريع مسجّلة بعد.
    </p>
</section>
