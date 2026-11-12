{{-- Units typed in by hand, and the choice of which market the report below reads. --}}
<section x-show="view === 'units'" x-cloak class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-extrabold">وحدات السوق المضافة يدويًا</h2>

        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="مصدر المقارنة">
            <span class="text-xs font-bold text-zinc-500">مصدر المقارنة</span>
            <div class="flex items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                <button type="button" class="{{ $pill }}" @click="setComparisonSource('ministry')"
                    :class="comparisonSource === 'ministry' ? '{{ $pillOn }}' : '{{ $pillOff }}'"
                    :aria-pressed="comparisonSource === 'ministry'">بيانات وزارة العدل</button>
                <button type="button" class="{{ $pill }}" @click="setComparisonSource('manual')"
                    :class="comparisonSource === 'manual' ? '{{ $pillOn }}' : '{{ $pillOff }}'"
                    :aria-pressed="comparisonSource === 'manual'">الوحدات المضافة يدويًا</button>
            </div>
        </div>
    </div>

    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
        أضف وحدة رأيتها في السوق: اسمها، ومشروعها إن عُرف، والمدينة والحي، ونوعها، ومساحتها، والسعر المطلوب.
        تقرير «وحداتنا مقابل السوق» يبقى على صكوك وزارة العدل حتى تختار «الوحدات المضافة يدويًا». عندها تُقارَن
        كل وحدة من وحداتنا بوحدات مضافة من نفس النوع وقريبة من مساحتها (أقل أو أكثر بالخُمس)، في حي المشروع إن وُجدت
        وإلا في المدينة كلها.
    </p>

    <form class="{{ $card }} grid gap-4 sm:grid-cols-2" @submit.prevent="saveManualUnit()">
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-name">اسم الوحدة</label>
            <input id="manual-unit-name" type="text" x-model="manualForm.name" class="{{ $input }}" placeholder="شقة ٣ غرف — الصفا">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('name')" x-text="manualError('name')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-project">المشروع</label>
            <input id="manual-unit-project" type="text" x-model="manualForm.project_name" class="{{ $input }}" placeholder="اسم المشروع في السوق، إن وُجد">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('project_name')" x-text="manualError('project_name')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-city">المدينة</label>
            <input id="manual-unit-city" type="text" x-model="manualForm.city" class="{{ $input }}" placeholder="جدة">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('city')" x-text="manualError('city')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-district">الحي</label>
            <input id="manual-unit-district" type="text" x-model="manualForm.district" class="{{ $input }}" placeholder="الصفا">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('district')" x-text="manualError('district')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-kind">النوع</label>
            <select id="manual-unit-kind" x-model="manualForm.kind" class="{{ $input }}">
                <template x-for="kind in manualKindOptions()" :key="kind">
                    <option :value="kind" x-text="kind"></option>
                </template>
            </select>
            <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('kind')" x-text="manualError('kind')"></p>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-area">المساحة (م²)</label>
                <input id="manual-unit-area" type="number" min="1" step="0.01" x-model="manualForm.area" class="{{ $input }}" dir="ltr">
                <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('area')" x-text="manualError('area')"></p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold text-zinc-500" for="manual-unit-price">السعر (ر.س)</label>
                <input id="manual-unit-price" type="number" min="1" step="1" x-model="manualForm.price" class="{{ $input }}" dir="ltr">
                <p class="mt-1 text-xs font-bold text-red-600" x-show="manualError('price')" x-text="manualError('price')"></p>
            </div>
        </div>
        <div class="flex items-center gap-3 sm:col-span-2">
            <button type="submit" class="{{ $primaryButton }}" :disabled="savingManualUnit">
                <span x-text="savingManualUnit ? 'جارٍ الحفظ…' : 'إضافة الوحدة'"></span>
            </button>
            <p class="text-sm font-bold text-red-600" x-show="manualUnitsError" x-text="manualUnitsError"></p>
        </div>
    </form>

    <div class="{{ $card }}" x-show="manualUnits.length">
        <h3 class="mb-4 font-extrabold">الوحدات المضافة</h3>
        <div class="sheet-wrap is-inset">
            <table class="sheet">
                <thead>
                    <tr>
                        <th>الوحدة</th>
                        <th>المشروع</th>
                        <th>المدينة</th>
                        <th>الحي</th>
                        <th>النوع</th>
                        <th>المساحة (م²)</th>
                        <th>السعر (ر.س)</th>
                        <th>سعر المتر</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="unit in manualUnits" :key="unit.id">
                        <tr>
                            <td class="font-bold" x-text="unit.name"></td>
                            <td x-text="unit.project_name || '—'"></td>
                            <td x-text="unit.city"></td>
                            <td x-text="unit.district"></td>
                            <td x-text="unit.kind"></td>
                            <td x-text="number(unit.area)"></td>
                            <td x-text="number(unit.price)"></td>
                            <td class="font-bold" x-text="rate(unit.rate)"></td>
                            <td>
                                <button type="button" class="text-xs font-bold text-red-600 hover:underline" @click="removeManualUnit(unit)">حذف</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <p x-show="!manualUnits.length" class="text-sm text-zinc-400">لم تُضف وحدات بعد.</p>
</section>
