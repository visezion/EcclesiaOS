@php($faq = \App\Support\WebsiteFaq::normalize($component))
@php($faqGroup = 'faq-'.\Illuminate\Support\Str::uuid())
@if(count($faq['faq_items']))
    <section class="website-faq" @if($faq['faq_title'] !== '') aria-labelledby="{{ $faqGroup }}-heading" @else aria-label="Frequently asked questions" @endif>
        @if($faq['faq_title'] !== '')<h2 id="{{ $faqGroup }}-heading">{{ $faq['faq_title'] }}</h2>@endif
        <div class="website-faq-list">
            @foreach($faq['faq_items'] as $entry)
                <details class="website-faq-item" @if($faq['faq_mode'] === 'single') name="{{ $faqGroup }}" @endif @if($loop->first && $faq['faq_open'] === 'first') open @endif>
                    <summary><span>{{ $entry['question'] }}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="website-faq-answer">{{ $entry['answer'] }}</div>
                </details>
            @endforeach
        </div>
    </section>
@endif
