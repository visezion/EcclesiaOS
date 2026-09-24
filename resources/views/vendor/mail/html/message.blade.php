@props(['brandName' => null])
@php($mailBrandName = filled($brandName) ? $brandName : config('app.name', 'EcclesiaOS'))
<x-mail::layout :brand-name="$mailBrandName">
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $mailBrandName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $mailBrandName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
