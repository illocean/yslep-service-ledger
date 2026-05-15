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

        <div class="page-grid min-h-screen">
            <header class="px-4 pt-4 sm:px-6 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div class="paper-panel masthead-shell rounded-[2rem] px-5 py-4 sm:px-6">
                        <div class="masthead-row">
                            <div class="brand-lockup">
                                <div class="section-kicker">YSLEP Service Ledger</div>
                                <a href="{{ route('dashboard') }}" class="brand-lockup__title">
                                    Service Ledger
                                </a>
                            </div>

                            <div class="header-status">
                                <span class="header-status__chip header-status__chip--live">Live</span>
                                <span class="header-status__chip header-status__chip--report">Reports</span>
                                <span class="header-status__chip header-status__chip--archive">Archives</span>
                            </div>
                        </div>

                        <nav class="nav-shell">
                            <div class="nav-cluster__links clean-scroll">
                                <a href="{{ route('dashboard') }}" class="topbar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                                    <span class="topbar-link__indicator" aria-hidden="true"></span>
                                    Overview
                                </a>

                                @foreach (\App\Enums\IndexType::cases() as $navType)
                                    <a href="{{ route('indexes.show', ['type' => $navType->value] + $routeParams) }}" class="topbar-link {{ request()->routeIs('indexes.show') && request()->route('type') === $navType->value ? 'is-active' : '' }}">
                                        <span class="topbar-link__indicator" aria-hidden="true"></span>
                                        {{ $navType->label() }}
                                    </a>
                                @endforeach

                                <a href="{{ route('reports.index') }}" class="topbar-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}">
                                    <span class="topbar-link__indicator" aria-hidden="true"></span>
                                    Reports
                                </a>

                                <a href="{{ route('academic-year-snapshots.index') }}" class="topbar-link {{ request()->routeIs('academic-year-snapshots.*') ? 'is-active' : '' }}">
                                    <span class="topbar-link__indicator" aria-hidden="true"></span>
                                    AY Archives
                                </a>
                            </div>
                        </nav>
                    </div>
                </div>
            </header>

            <main id="main-content" class="page-enter px-4 py-5 sm:px-6 lg:px-10">
                <div class="mx-auto flex max-w-7xl flex-col gap-6">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
