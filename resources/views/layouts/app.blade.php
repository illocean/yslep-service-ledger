<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name'))</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        @stack('head', '')
    </head>
    <body>
        @php
            $reportQuery = request()->query('report');
            $scopeQuery = request()->query('scope');

            if (blank($reportQuery) && request()->routeIs('reports.show', 'reports.update', 'reports.destroy')) {
                $reportQuery = request()->route('reportGroup')?->tag;
            }

            $routeParams = [];

            if (filled($scopeQuery)) {
                $routeParams['scope'] = $scopeQuery;
            }

            if ($reportQuery) {
                $routeParams['report'] = $reportQuery;
            }
        @endphp

        <a href="#main-content" class="skip-link sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-stone-900">
            Skip to Content
        </a>

        @php
            $entryReport = request()->routeIs('reports.show')
                ? request()->route('reportGroup')
                : (($selectedScope ?? null) === \App\Enums\IndexScope::Report ? ($selectedReportGroup ?? null) : null);
        @endphp
        <datalist id="entry-academic-years">
            @foreach ($entryAcademicYears as $year)
                <option value="{{ $year }}"></option>
            @endforeach
        </datalist>
        @foreach (\App\Enums\IndexType::cases() as $entryType)
            <x-entry-modal :type="$entryType" :report="$entryReport"
                :form-action="$entryReport ? route('reports.records.store', $entryReport) : route('entries.store')" />
        @endforeach

        <div class="page-grid min-h-screen pb-10">
            <header class="floating-navbar" role="banner">
                <nav class="floating-navbar__inner" aria-label="Main navigation">
                    <div class="brand-lockup">
                        <a href="{{ route('dashboard') }}" class="brand-lockup__title" aria-label="YSLEP Dashboard">
                            YSLEP
                        </a>
                    </div>

                    <div class="nav-cluster__links clean-scroll">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                            Overview
                        </a>

                        @foreach (\App\Enums\IndexType::cases() as $navType)
                            <a href="{{ route('indexes.show', ['type' => $navType->value] + $routeParams) }}" class="nav-link {{ request()->routeIs('indexes.show') && request()->route('type') === $navType ? 'is-active' : '' }}" aria-current="{{ request()->routeIs('indexes.show') && request()->route('type') === $navType ? 'page' : 'false' }}">
                                {{ $navType->label() }}
                            </a>
                        @endforeach

                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" aria-current="{{ request()->routeIs('reports.*') ? 'page' : 'false' }}">
                            Reports
                        </a>

                        <a href="{{ route('academic-year-snapshots.index') }}" class="nav-link {{ request()->routeIs('academic-year-snapshots.*') ? 'is-active' : '' }}" aria-current="{{ request()->routeIs('academic-year-snapshots.*') ? 'page' : 'false' }}">
                            Years
                        </a>
                    </div>

                    <x-quick-add-bar
                        :types="[ \App\Enums\IndexType::Formation, \App\Enums\IndexType::SocialApostolate, \App\Enums\IndexType::ParishInvolvement ]"
                        class="quick-add-bar--header"
                    />
                </nav>
            </header>

            <main id="main-content" class="page-enter px-4 py-6 sm:px-6 lg:px-10" role="main">
                <div class="mx-auto flex max-w-7xl flex-col gap-6">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
