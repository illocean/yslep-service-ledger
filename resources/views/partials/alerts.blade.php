@if (session('status'))
    <div aria-live="polite" class="rounded-[1.5rem] border border-emerald-900/15 bg-emerald-50/80 px-5 py-4 text-sm text-emerald-900" role="status">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div aria-live="polite" class="rounded-[1.5rem] border border-rose-900/15 bg-rose-50/80 px-5 py-4 text-sm text-rose-900" role="alert">
        <p class="font-semibold">Please fix the highlighted form fields and try again.</p>
        <ul class="mt-2 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
