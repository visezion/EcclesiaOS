<div class="button-row" style="flex-wrap:wrap;margin-top:20px">
    @foreach(\App\Support\WebsiteForms::forChurch($church) as $formType => $publicForm)
        @if($publicForm['enabled'])
            <a class="button" href="{{ route('website.forms.show', ['church' => $church->slug, 'type' => $formType]) }}">{{ $publicForm['title'] }}</a>
        @endif
    @endforeach
</div>
