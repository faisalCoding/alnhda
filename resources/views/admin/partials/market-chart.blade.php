{{--
    One bar per quarter. $series is the Alpine expression holding the points. $metric, when given, is the figure this
    chart always draws; without it the chart follows the metric chosen on the screen.
--}}
@php
    $metricArgument = isset($metric) ? ", '".$metric."'" : '';
@endphp

<div>
    <div class="flex h-40 items-end gap-1" dir="ltr">
        <template x-for="point in {{ $series }}" :key="point.key">
            <div class="group relative flex h-full flex-1 items-end justify-center">
                <div class="w-full rounded-t transition-colors"
                    :class="point.key === {{ $current ?? 'quarter' }} ? 'bg-primary-500' : 'bg-primary-500/40 group-hover:bg-primary-500/70'"
                    :style="`height: ${barHeight({{ $series }}, point{!! $metricArgument !!})}`"></div>

                <div class="pointer-events-none absolute bottom-full z-10 mb-2 hidden whitespace-nowrap rounded-lg bg-zinc-900 px-2 py-1 text-[11px] text-white group-hover:block"
                    dir="rtl" x-text="barLabel(point{!! $metricArgument !!})"></div>
            </div>
        </template>
    </div>

    <div class="mt-2 flex justify-between text-[11px] text-zinc-400" dir="ltr">
        <span x-text="shortQuarter({{ $series }}[0])"></span>
        <span x-text="shortQuarter({{ $series }}[{{ $series }}.length - 1])"></span>
    </div>
</div>
