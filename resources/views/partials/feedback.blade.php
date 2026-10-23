@foreach (['success', 'status'] as $flashKey)
    @if (session($flashKey))
        <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-800">
            {{ session($flashKey) }}
        </div>
    @endif
@endforeach

@if (session('error'))
    <div role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">
        <p class="font-semibold">Please check the following:</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
