@if(session('status'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if(session('error'))
    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
@endif
