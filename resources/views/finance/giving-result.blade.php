<x-app-layout title="Giving Status" :chromeless="true">
    @php($paid = $transaction->status === 'paid')
    <div class="grid min-h-screen place-items-center bg-slate-50 px-4 py-10">
        <section class="w-full max-w-xl overflow-hidden rounded-3xl border {{ $paid ? 'border-emerald-200' : 'border-amber-200' }} bg-white text-center shadow-xl">
            <div class="{{ $paid ? 'bg-emerald-50' : 'bg-amber-50' }} px-6 py-10">
                <span class="mx-auto grid size-20 place-items-center rounded-full {{ $paid ? 'bg-emerald-500' : 'bg-amber-500' }} text-white">
                    <i data-lucide="{{ $paid ? 'badge-check' : 'circle-pause' }}" class="size-10"></i>
                </span>
                <p class="mt-6 text-xs font-black uppercase tracking-[0.2em] {{ $paid ? 'text-emerald-700' : 'text-amber-700' }}">Payment {{ $paid ? 'confirmed' : Str::headline($transaction->status) }}</p>
                <h1 class="mt-3 text-3xl font-black text-slate-950">{{ $paid ? 'Thank you for your generosity!' : 'Your gift was not completed' }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ $paid ? 'The verified amount and payment date have been recorded in the church giving ledger.' : 'No donation has been added to the finance ledger.' }}</p>
            </div>
            <div class="grid gap-3 p-6 text-left sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-4"><div class="text-xs font-bold uppercase text-slate-400">Amount</div><div class="mt-1 text-xl font-black">{{ $transaction->currency }} {{ number_format((float)$transaction->amount, 2) }}</div></div>
                <div class="rounded-xl bg-slate-50 p-4"><div class="text-xs font-bold uppercase text-slate-400">Reference</div><div class="mt-1 font-mono font-black">{{ $transaction->reference }}</div></div>
                @if($paid)<div class="rounded-xl bg-slate-50 p-4 sm:col-span-2"><div class="text-xs font-bold uppercase text-slate-400">Recorded payment date</div><div class="mt-1 font-black">{{ $transaction->paid_at?->format('F d, Y \a\t h:i A T') }}</div></div>@endif
                <a href="{{ route('giving.create') }}" class="mt-2 rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-bold text-slate-700">Make another gift</a>
                <a href="{{ route('home') }}" class="mt-2 rounded-xl bg-violet-600 px-5 py-3 text-center text-sm font-bold text-white">Return home</a>
            </div>
        </section>
    </div>
</x-app-layout>
