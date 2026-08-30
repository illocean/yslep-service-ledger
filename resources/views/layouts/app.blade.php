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

        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-stone-900">
            Skip to Content
        </a>

        <!-- Quick Add Bar (docked bottom) -->
        <x-quick-add-bar 
            :types="[ \App\Enums\IndexType::Formation, \App\Enums\IndexType::SocialApostolate, \App\Enums\IndexType::ParishInvolvement ]"
            class="fixed bottom-0 left-0 right-0 z-40 sm:bottom-4 sm:left-auto sm:right-4 sm:w-auto sm:max-w-md sm:rounded-panel"
        />

        <!-- Global Conflict Modal -->
        <x-conflict-modal 
            :conflicts="session('sync_conflicts', [])"
            class="fixed inset-0 z-50"
        />

        <!-- Global Entry Modals (for quick-add bar) -->
        @php
            $dashboardAcademicYears = $cardMeta['entry_options']['academic_years'] ?? [];
        @endphp
        <x-entry-modal
            :type="\App\Enums\IndexType::Formation"
            :form-action="route('entries.store')"
            :academic-years="$dashboardAcademicYears"
            :cancel-url="route('dashboard')"
            class="fixed inset-0 z-50"
        />
        <x-entry-modal
            :type="\App\Enums\IndexType::SocialApostolate"
            :form-action="route('entries.store')"
            :academic-years="$dashboardAcademicYears"
            :cancel-url="route('dashboard')"
            class="fixed inset-0 z-50"
        />
        <x-entry-modal
            :type="\App\Enums\IndexType::ParishInvolvement"
            :form-action="route('entries.store')"
            :academic-years="$dashboardAcademicYears"
            :cancel-url="route('dashboard')"
            class="fixed inset-0 z-50"
        />

        <div class="page-grid min-h-screen pb-24 sm:pb-0">
            <header class="px-4 pt-4 sm:px-6 lg:px-10" role="banner">
                <div class="mx-auto max-w-7xl">
                    <div class="paper-panel masthead-shell rounded-panel px-5 py-4 sm:px-6">
                        <div class="masthead-row">
                            <div class="brand-lockup">
                                <div class="section-kicker">YSLEP Service Ledger</div>
                                <a href="{{ route('dashboard') }}" class="brand-lockup__title">
                                    Service Ledger
                                </a>
                            </div>

                            <div class="header-status" role="status" aria-label="Data sources">
                                <span class="header-status__chip header-status__chip--live" aria-label="Live data from Obsidian">Live</span>
                                <span class="header-status__chip header-status__chip--report" aria-label="Saved reports">Reports</span>
                                <span class="header-status__chip header-status__chip--archive" aria-label="Academic year archives">Archives</span>
                            </div>
                        </div>

                        <nav class="nav-shell" aria-label="Main navigation">
                            <div class="nav-cluster__links clean-scroll" role="menubar">
                                <a href="{{ route('dashboard') }}" class="topbar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" role="menuitem" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                                    <span class="topbar-link__indicator" aria-hidden="true"></span>
                                    Overview
                                </a>

                                @foreach (\App\Enums\IndexType::cases() as $navType)
                                    <a href="{{ route('indexes.show', ['type' => $navType->value] + $routeParams) }}" class="topbar-link {{ request()->routeIs('indexes.show') && request()->route('type') === $navType->value ? 'is-active' : '' }}" role="menuitem" aria-current="{{ request()->routeIs('indexes.show') && request()->route('type') === $navType->value ? 'page' : 'false' }}">
                                        <span class="topbar-link__indicator" aria-hidden="true"></span>
                                        {{ $navType->label() }}
                                    </a>
                                @endforeach

                                <a href="{{ route('reports.index') }}" class="topbar-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" role="menuitem" aria-current="{{ request()->routeIs('reports.*') ? 'page' : 'false' }}">
                                    <span class="topbar-link__indicator" aria-hidden="true"></span>
                                    Reports
                                </a>

                                <a href="{{ route('academic-year-snapshots.index') }}" class="topbar-link {{ request()->routeIs('academic-year-snapshots.*') ? 'is-active' : '' }}" role="menuitem" aria-current="{{ request()->routeIs('academic-year-snapshots.*') ? 'page' : 'false' }}">
                                    <span class="topbar-link__indicator" aria-hidden="true"></span>
                                    AY Archives
                                </a>
                            </div>
                        </nav>
                    </div>
                </div>
            </header>

            <main id="main-content" class="page-enter px-4 py-5 sm:px-6 lg:px-10" role="main">
                <div x-data="{}" class="mx-auto flex max-w-7xl flex-col gap-6">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
