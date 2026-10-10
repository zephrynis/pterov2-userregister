@extends('templates/wrapper')

@section('assets')
    <style>
        /* Mirrors the native AuthLayout and LoginFormContainer without mounting the SPA. */
        .ur-shell { min-height: 100svh; padding: 2rem 0; color: var(--foreground); font-family: var(--font-sans, system-ui, sans-serif); }
        .ur-auth { box-sizing: border-box; width: 100%; }
        .ur-title { font-size: 1.875rem; line-height: 2.25rem; font-weight: 500; padding: 1rem 0; margin: 0; text-align: center; overflow-wrap: anywhere; }
        .ur-card { margin: 0 .25rem; padding: 1.5rem; color: var(--card-foreground); background: var(--card); border-radius: var(--radius, .5rem); box-shadow: 0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1); }
        .ur-logo { flex: none; align-self: center; margin-bottom: 1.5rem; user-select: none; }
        .ur-mark { display: block; box-sizing: border-box; width: 8rem; height: auto; margin: 0 auto; fill: var(--primary); }
        .ur-body { flex: 1; min-width: 0; }
        .ur-lead, .ur-note { color: color-mix(in srgb, var(--foreground) 85%, var(--card)); line-height: 1.6; overflow-wrap: anywhere; }
        .ur-lead { margin: 0 0 1.5rem; font-size: .875rem; }
        .ur-note { font-size: .875rem; margin: .75rem 0 0; }
        .ur-card label { display: block; font-size: .875rem; line-height: 1.25rem; font-weight: 500; text-transform: uppercase; color: var(--muted-foreground); margin-bottom: .25rem; }
        .ur-field + .ur-field { margin-top: 1.5rem; }
        .ur-card input:not([type=checkbox]):not([type=hidden]) { display: block; box-sizing: border-box; width: 100%; border: 2px solid var(--border); background: var(--background); color: var(--foreground); border-radius: calc(var(--radius, .5rem) * .6); padding: .75rem; font: inherit; font-size: .875rem; line-height: 1.25rem; transition: border-color 150ms, box-shadow 150ms; }
        .ur-card :is(a, button, input):focus-visible { outline: 2px solid var(--ring, var(--primary)); outline-offset: 3px; }
        .ur-card a { color: var(--foreground); text-decoration: underline; text-underline-offset: 3px; }
        .ur-button { display: block; width: 100%; box-sizing: border-box; margin-top: 1.5rem; padding: 1rem; font: inherit; font-size: .875rem; line-height: 1.25rem; text-transform: uppercase; letter-spacing: .025em; color: var(--primary-foreground); background: var(--primary); border: 1px solid var(--primary); border-radius: calc(var(--radius, .5rem) * .6); cursor: pointer; transition: background-color 150ms; }
        .ur-button:hover { background: color-mix(in oklab, var(--primary) 80%, transparent); }
        .ur-error { border: 1px solid var(--destructive); border-radius: .375rem; padding: 1rem; margin-bottom: 1.5rem; line-height: 1.5; }
        .ur-error ul { list-style: disc; padding-left: 1rem; margin: 0; }
        .ur-card .ur-back { display: block; margin-top: 1.5rem; text-align: center; font-size: .75rem; line-height: 1rem; text-transform: uppercase; letter-spacing: .025em; text-decoration: none; color: var(--muted-foreground); }
        .ur-card .ur-back:hover { color: var(--foreground); }
        .ur-policies { margin-top: 1.5rem; line-height: 1.6; }
        .ur-policies label { display: flex; align-items: start; gap: .75rem; font-weight: 400; text-transform: none; color: var(--foreground); }
        .ur-policies input { margin-top: .3rem; flex: none; accent-color: var(--primary); }
        .ur-email { font-weight: 500; color: var(--foreground); }
        .ur-footer { margin-top: 1rem; text-align: center; color: var(--muted-foreground); font-size: .75rem; line-height: 1rem; }
        .ur-footer a { color: inherit; text-decoration: none; }
        @media (min-width: 40rem) { .ur-auth { width: 80%; margin: 0 auto; } }
        @media (min-width: 48rem) {
            .ur-auth { padding: 2.5rem; }
            .ur-card { display: flex; padding-left: 0; }
            .ur-logo { margin-bottom: 0; }
            .ur-mark { width: 14rem; padding: 0 3rem; }
        }
        @media (min-width: 64rem) { .ur-auth { width: 60%; } }
        @media (min-width: 80rem) {
            .ur-shell { padding-top: 8rem; }
            .ur-auth { width: 100%; max-width: var(--layout-auth-width, 43.75rem); }
        }
    </style>
@endsection

@section('user-data')@endsection
@section('scripts')@endsection

@section('container')
    <main class="ur-shell">
        <section class="ur-auth" aria-labelledby="ur-title">
            <h1 class="ur-title" id="ur-title">@yield('title')</h1>
            <div class="ur-card">
                <div class="ur-logo">
                    {{-- Same vector mark as the Panel's PterodactylMark component. --}}
                    <svg class="ur-mark" xmlns="http://www.w3.org/2000/svg" viewBox="1115 4684.52 122.47 110.97" role="img" aria-label="Pterodactyl">
                        <path d="M 1153.300781 4732.21875 C 1153.179688 4734.238281 1151.378906 4735.730469 1149.371094 4735.480469 L 1146.621094 4735.128906 L 1153.441406 4729.921875 Z M 1237.460938 4684.519531 L 1192.5 4705.878906 L 1175.949219 4744.699219 L 1168.179688 4741.171875 L 1180.960938 4711.328125 L 1150.378906 4725.78125 L 1115 4754.519531 L 1152.21875 4744.488281 L 1160.941406 4771.191406 L 1228.769531 4795.480469 L 1198.730469 4768.601562 Z M 1237.460938 4684.519531" />
                    </svg>
                </div>
                <div class="ur-body">
                    @if ($errors->any())
                        <div class="ur-error" role="alert">
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @yield('body')
                    <a class="ur-back" href="/auth/login">Back to sign in</a>
                </div>
            </div>
            <p class="ur-footer">&copy; 2015 - {{ now()->year }}&nbsp;<a href="https://pterodactyl.io" target="_blank" rel="noopener nofollow noreferrer">Pterodactyl Software</a></p>
        </section>
    </main>
@endsection
