@extends('layouts.app')

@section('title', 'YSLEP Yearly Totals')

@section('content')
    @include('partials.alerts')

    @php
        // Index-type colour map (matches the fill-* palette elsewhere on the ledger).
        $typeColor = [
            \App\Enums\IndexType::Formation->value => 'fill-formation',
            \App\Enums\IndexType::ParishInvolvement->value => 'fill-parish_involvement',
            \App\Enums\IndexType::SocialApostolate->value => 'fill-social_apostolate',
        ];

        // Grand total across every saved report.
        $grandTotalLabel = $grandTotalHours;
    @endphp

    {{-- Head spread: the figure that answers "how much service, all told" --}}
    <section class="paper-panel overflow-hidden rounded-panel" aria-labelledby="insight-heading">
        <div class="px-5 py-6 sm:px-8 lg:px-10">
            <p class="section-kicker">Yearly totals</p>

            <h1 id="insight-heading" class="mt-3 max-w-2xl font-serif text-4xl leading-tight text-stone-950 sm:text-5xl">
                Saved reports, grouped by academic year.
            </h1>

            @if (! $hasAnyReport)
                <p class="mt-4 text-sm leading-7 text-stone-600">
                    No saved reports yet. Save a report to see per-year totals here.
                </p>

                <div class="mt-8">
                    <x-quick-add-bar class="mt-2" />
                </div>
            @endif

            @if ($hasAnyReport)
                {{-- Ruled figures: the headline number for the chronicler --}}
                <dl class="mt-6 ruled-figures">
                    <div class="ruled-figures__item">
                        <dt class="section-kicker">All reports</dt>
                        <dd class="font-serif text-3xl font-semibold text-stone-950">{{ $grandTotalLabel }}</dd>
                    </div>
                    <div class="ruled-figures__item">
                        <dt class="section-kicker">Reports</dt>
                        <dd class="font-serif text-3xl font-semibold text-stone-950">{{ $reportCards->count() }}</dd>
                    </div>
                </dl>
            @endif
        </div>
    </section>

    @if ($hasAnyReport)
        {{-- Unfiled reports — not yet swept into a saved academic year --}}
        @if ($unfiledReports->isNotEmpty())
            <section class="mt-8 paper-panel overflow-hidden rounded-panel" aria-labelledby="unfiled-heading">
                <div class="px-5 py-5 sm:px-8 lg:px-10">
                    <h2 id="unfiled-heading" class="font-serif text-xl font-semibold text-stone-950">
                        Unfiled this year
                    </h2>
                    <p class="mt-1 text-xs uppercase tracking-wider text-stone-500">
                        {{ $unfiledReports->count() }} report{{ $unfiledReports->count() === 1 ? '' : 's' }} not yet in a saved academic year
                    </p>

                    <div class="mt-4 -mx-3 sm:-mx-4">
                        @include('insight.partials._report-table', [
                            'reportCards' => $unfiledReports,
                            'typeColor' => $typeColor,
                            'scope' => 'current',
                        ])
                    </div>
                </div>
            </section>
        @endif

        {{-- Year sections, newest first --}}
        @foreach ($yearGroups as $yearGroup)
            @php
                $yearId = 'year-' . $yearGroup['snapshot']->tag;
            @endphp
            <section class="mt-8 paper-panel overflow-hidden rounded-panel" aria-labelledby="{{ $yearId }}-heading">
                <div class="px-5 py-5 sm:px-8 lg:px-10">
                    <h2 id="{{ $yearId }}-heading" class="font-serif text-xl font-semibold text-stone-950">
                        {{ $yearGroup['label'] }}
                    </h2>
                    <p class="mt-1 text-xs uppercase tracking-wider text-stone-500">
                        {{ $yearGroup['reports']->count() }} report{{ $yearGroup['reports']->count() === 1 ? '' : 's' }}
                        @if ($yearGroup['academic_year'])
                            · {{ $yearGroup['academic_year'] }}
                        @endif
                    </p>

                    <div class="mt-4 -mx-3 sm:-mx-4" bis_data_fpmath="rdnn">
                        @include('insight.partials._report-table', [
                            'reportCards' => $yearGroup['reports'],
                            'typeColor' => $typeColor,
                            'scope' => $yearGroup['snapshot']->academic_year,
                        ])
                    </div>
                </div>
            </section>
        @endforeach
    @endif
@endsection
