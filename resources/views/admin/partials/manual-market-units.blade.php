{{--
    Units someone saw offered on the market and entered by hand: a group of its own on the board. They are asking prices,
    which neither the ministry's deeds nor the authority's indicators are, and the units report sets the company's own
    units beside them in its «عروض مماثلة» column.
--}}
<section x-show="view === 'offers'" x-cloak class="space-y-4">
    <h2 class="text-lg font-extrabold" x-text="`وحدات السوق المضافة يدويًا — ${offers?.city?.label ?? ''}`"></h2>

    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
        وحدات رأيتها معروضة في السوق: إعلان، مشروع منافس، سعر من وسيط. هي أسعار معروضة لا صفقات مسجّلة، فتكمل ما لا تقوله
        صكوك وزارة العدل ولا مؤشرات الهيئة. تُقارَن بها وحداتنا في تقرير «وحداتنا مقابل السوق»: نفس النوع ومساحة قريبة
        (أقل أو أكثر بالخُمس)، في حي المشروع إن وُجدت وإلا في المدينة كلها. ما مضى على رصده أكثر من
        <span x-text="number(offers?.fresh_months ?? 12)"></span> شهرًا يبقى في القائمة ولا يدخل في المقارنة.
        المدينة هي المختارة أعلاه.
    </p>

    <p x-show="offersError" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="offersError"></p>

    {{-- Many listings at once, from a file with the same columns as the form --}}
    <div class="{{ $card }} space-y-3">
        <h3 class="font-extrabold">استيراد ملف إعلانات</h3>
        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
            ملف CSV بالأعمدة نفسها التي في النموذج أدناه:
            <span class="font-mono" dir="ltr">source_site, listing_id, url, neighborhood, city, title, price_sar, area_m2, rooms, bedrooms, bathrooms, street_width_m, facade, posted_at, updated_at, scraped_at, possible_cross_site_duplicate, property_age, property_age_years</span>.
            نوع العقار يُقرأ من العنوان، والإعلان المضاف من قبل (نفس الرقم على نفس الموقع) يُحدَّث ولا يُكرَّر، والعقار المعروض في أكثر من إعلان يُحسب مرة واحدة.
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-xs font-bold text-zinc-500">
                النوع إن لم يذكره العنوان
                <select x-model="listingKind" class="{{ $select }} py-1">
                    <option value="">— لا شيء —</option>
                    <template x-for="kind in offerKinds()" :key="kind">
                        <option :value="kind" x-text="kind"></option>
                    </template>
                </select>
            </label>
            <label class="{{ $primaryButton }} cursor-pointer" :class="importingListings && 'pointer-events-none opacity-50'">
                <span x-text="importingListings ? 'جارٍ الاستيراد…' : 'اختر ملف CSV'"></span>
                <input type="file" accept=".csv,text/csv" class="hidden" @change="importListings($event)">
            </label>
        </div>
        <p x-show="listingError" x-cloak class="text-sm font-bold text-red-600" x-text="listingError"></p>
        <template x-if="listingReport">
            <div class="space-y-1 text-sm">
                <p class="font-bold text-primary-600 dark:text-primary-300" x-text="listingSummary(listingReport)"></p>
                <template x-for="item in listingReport.skipped" :key="item.row">
                    <p class="text-xs text-amber-600" x-text="`السطر ${number(item.row)} لم يُستورد: ${item.reason}`"></p>
                </template>
                <p class="text-xs text-amber-600" x-show="listingReport.unknown_districts.length"
                    x-text="`أحياء ليست في بيانات السوق فلن تُقارَن بها مشاريع تلك الأحياء: ${listingReport.unknown_districts.join('، ')}`"></p>
            </div>
        </template>
    </div>

    <form class="{{ $card }} grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="saveOffer()">
        <h3 class="font-extrabold sm:col-span-2 lg:col-span-4" x-text="editingOffer ? 'تعديل وحدة' : 'إضافة وحدة'"></h3>

        {{-- Where it was listed --}}
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-site">الموقع</label>
            <input id="offer-site" type="text" list="offer-sites" x-model="offerForm.source_site" class="{{ $input }}" dir="ltr" placeholder="aqar">
            <datalist id="offer-sites">
                <option value="aqar">عقار</option>
                <option value="bayut">بيوت</option>
                <option value="haraj">حراج</option>
                <option value="propertyfinder">بروبرتي فايندر</option>
            </datalist>
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('source_site')" x-text="offerError('source_site')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-listing">رقم الإعلان</label>
            <input id="offer-listing" type="text" dir="ltr" x-model="offerForm.listing_id" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('listing_id')" x-text="offerError('listing_id')"></p>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-link">رابط الإعلان</label>
            <input id="offer-link" type="url" x-model="offerForm.source_url" class="{{ $input }}" dir="ltr" placeholder="https://…">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('source_url')" x-text="offerError('source_url')"></p>
        </div>

        {{-- Where it is and what it is --}}
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-district">الحي</label>

            {{-- From the market's own districts, so the unit lines up with the projects placed in them --}}
            <select id="offer-district" x-show="offers?.districts?.length" x-model="offerForm.district" class="{{ $input }}">
                <option value="">— اختر الحي —</option>
                <template x-for="district in (offers?.districts ?? [])" :key="district.key">
                    <option :value="district.key" :selected="district.key === offerForm.district" x-text="district.label"></option>
                </template>
            </select>

            {{-- A city the deeds have not been uploaded for has no list to choose from --}}
            <input type="text" x-show="!offers?.districts?.length" x-model="offerForm.district" class="{{ $input }}" placeholder="الصفا" aria-label="الحي">

            <p class="mt-1 text-xs text-zinc-400" x-text="`المدينة: ${offers?.city?.label ?? ''}`"></p>
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('district')" x-text="offerError('district')"></p>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-title">عنوان الإعلان</label>
            <input id="offer-title" type="text" x-model="offerForm.name" class="{{ $input }}" placeholder="فيلا للبيع في حي المنار">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('name')" x-text="offerError('name')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-kind">النوع</label>
            <select id="offer-kind" x-model="offerForm.kind" class="{{ $input }}">
                <template x-for="kind in offerKinds()" :key="kind">
                    <option :value="kind" :selected="kind === offerForm.kind" x-text="kind"></option>
                </template>
            </select>
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('kind')" x-text="offerError('kind')"></p>
        </div>

        {{-- Price and size --}}
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-price">السعر (ر.س)</label>
            <input id="offer-price" type="number" min="1" step="1" dir="ltr" x-model="offerForm.price" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('price')" x-text="offerError('price')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-area">المساحة (م²)</label>
            <input id="offer-area" type="number" min="1" step="0.01" dir="ltr" x-model="offerForm.area" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('area')" x-text="offerError('area')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-rooms">الغرف</label>
            <input id="offer-rooms" type="number" min="0" step="1" dir="ltr" x-model="offerForm.rooms" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('rooms')" x-text="offerError('rooms')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-bedrooms">غرف النوم</label>
            <input id="offer-bedrooms" type="number" min="0" step="1" dir="ltr" x-model="offerForm.bedrooms" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('bedrooms')" x-text="offerError('bedrooms')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-bathrooms">دورات المياه</label>
            <input id="offer-bathrooms" type="number" min="0" step="1" dir="ltr" x-model="offerForm.bathrooms" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('bathrooms')" x-text="offerError('bathrooms')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-street">عرض الشارع (م)</label>
            <input id="offer-street" type="text" x-model="offerForm.street_width" class="{{ $input }}" placeholder="15 أو 25 / 12">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('street_width')" x-text="offerError('street_width')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-facade">الواجهة</label>
            <input id="offer-facade" type="text" x-model="offerForm.facade" class="{{ $input }}" placeholder="شمالية غربية">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('facade')" x-text="offerError('facade')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-age">عمر العقار</label>
            <input id="offer-age" type="text" x-model="offerForm.property_age" class="{{ $input }}" placeholder="أكثر من 10 سنوات">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('property_age')" x-text="offerError('property_age')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-age-years">عمر العقار بالسنوات</label>
            <input id="offer-age-years" type="number" min="0" step="1" dir="ltr" x-model="offerForm.property_age_years" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('property_age_years')" x-text="offerError('property_age_years')"></p>
        </div>

        {{-- When --}}
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-posted">تاريخ النشر</label>
            <input id="offer-posted" type="date" dir="ltr" x-model="offerForm.posted_on" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('posted_on')" x-text="offerError('posted_on')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-updated">تاريخ تحديث الإعلان</label>
            <input id="offer-updated" type="date" dir="ltr" x-model="offerForm.listing_updated_on" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('listing_updated_on')" x-text="offerError('listing_updated_on')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-date">تاريخ الرصد</label>
            <input id="offer-date" type="date" dir="ltr" x-model="offerForm.observed_on" class="{{ $input }}">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('observed_on')" x-text="offerError('observed_on')"></p>
        </div>

        {{-- The same property listed elsewhere --}}
        <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-duplicates">إعلانات مكررة لنفس العقار</label>
            <input id="offer-duplicates" type="text" x-model="offerForm.duplicates" class="{{ $input }}" dir="ltr" placeholder="aqar:6874122;haraj:11169632270">
            <p class="mt-1 text-xs text-zinc-400">الموقع ورقم الإعلان، مفصولة بفاصلة منقوطة. العقار المكرر يُحسب في المقارنة مرة واحدة.</p>
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('duplicates')" x-text="offerError('duplicates')"></p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-bold text-zinc-500" for="offer-project">المشروع (إن عُرف)</label>
            <input id="offer-project" type="text" x-model="offerForm.project_name" class="{{ $input }}" placeholder="اختياري">
            <p class="mt-1 text-xs font-bold text-red-600" x-show="offerError('project_name')" x-text="offerError('project_name')"></p>
        </div>

        <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="{{ $primaryButton }}" :disabled="savingOffer"
                x-text="savingOffer ? 'جارٍ الحفظ…' : (editingOffer ? 'حفظ التعديل' : 'إضافة الوحدة')"></button>
            <button type="button" class="{{ $ghostButton }}" x-show="editingOffer" x-cloak @click="cancelOfferEdit()">إلغاء التعديل</button>
        </div>
    </form>

    {{-- What the units still fresh enough to compare with ask, by kind --}}
    <div class="{{ $card }}" x-show="offers?.summary?.length" x-cloak>
        <h3 class="mb-1 font-extrabold">ما تطلبه السوق حسب النوع</h3>
        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">كل عقار مرة واحدة مهما تكرر إعلانه، ومما رُصد خلال آخر سنة.</p>
        <div class="sheet-wrap is-inset">
            <table class="sheet">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>العقارات</th>
                        <th>وسيط سعر المتر المعروض</th>
                        <th>النطاق (ر.س/م²)</th>
                        <th>وسيط السعر المعروض</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in (offers?.summary ?? [])" :key="row.kind">
                        <tr>
                            <td class="font-bold" x-text="row.kind"></td>
                            <td x-text="number(row.units)"></td>
                            <td class="font-bold" x-text="rate(row.median)"></td>
                            <td class="text-zinc-500" x-text="range(row)"></td>
                            <td x-text="money(row.median_price)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="{{ $card }}" x-show="offers?.units?.length" x-cloak>
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
            <h3 class="font-extrabold">الوحدات المضافة</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400"
                x-text="`${number(offers?.units?.length ?? 0)} إعلانًا لـ ${number(offers?.properties ?? 0)} عقارًا`"></p>
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
                        <th>الشارع والواجهة</th>
                        <th>النشر · التحديث</th>
                        <th>الرصد</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="unit in (offers?.units ?? [])" :key="unit.id">
                        <tr :class="{ 'bg-primary-500/10': unit.id === editingOffer, 'text-zinc-400': !unit.fresh }">
                            <td class="max-w-xs">
                                <a x-show="unit.source_url" :href="unit.source_url" target="_blank" rel="noopener noreferrer"
                                    class="block truncate font-bold text-primary-600 hover:underline dark:text-primary-300" x-text="unit.label"></a>
                                <span x-show="!unit.source_url" class="block truncate font-bold" x-text="unit.label"></span>
                                <span class="block text-[11px] text-zinc-400" dir="ltr"
                                    x-show="unit.site_label" x-text="`${unit.site_label} · ${unit.listing_id ?? ''}`"></span>
                                <span class="block text-[11px] font-bold text-amber-600" x-show="unit.group_size > 1"
                                    x-text="`نفس العقار في ${number(unit.group_size)} إعلانات · يُحسب مرة`"></span>
                            </td>
                            <td x-text="unit.district"></td>
                            <td x-text="unit.kind"></td>
                            <td x-text="number(unit.area)"></td>
                            <td x-text="number(unit.price)"></td>
                            <td class="font-bold" x-text="rate(unit.rate)"></td>
                            <td class="text-xs" x-text="offerDetails(unit) || '—'"></td>
                            <td class="text-xs" x-text="offerStreet(unit) || '—'"></td>
                            <td class="text-xs" dir="ltr" x-text="[unit.posted_on, unit.listing_updated_on].filter(Boolean).join(' · ') || '—'"></td>
                            <td class="text-xs">
                                <span dir="ltr" x-text="unit.observed_on"></span>
                                <span x-show="!unit.fresh" class="block text-[11px] font-bold text-amber-600">قديم، لا يُقارَن به</span>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300" @click="editOffer(unit)">تعديل</button>
                                    <button type="button" class="text-xs font-bold text-red-500 hover:underline" @click="removeOffer(unit)">حذف</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <p x-show="offers && !offers.units.length" x-cloak class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
        لم تُضف وحدات لهذه المدينة بعد.
    </p>
</section>
