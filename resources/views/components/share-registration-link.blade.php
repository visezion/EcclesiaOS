@props(['compact' => false])

<span
    x-data="{
        status: 'idle',
        registrationUrl: @js(route('members.self-register')),
        labels: {
            shareTitle: @js($term('Member registration')),
            shareText: @js($term('Use this link to register as a church member.')),
            copied: @js($term('Registration link copied')),
            shared: @js($term('Registration link shared')),
            share: @js($term('Share member registration link')),
            linkCopied: @js($term('Link copied')),
            linkShared: @js($term('Link shared')),
            shareLink: @js($term('Share registration link')),
            copyPrompt: @js($term('Copy the member registration link:'))
        },
        resetStatus() {
            window.setTimeout(() => this.status = 'idle', 1800);
        },
        async copyLink() {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(this.registrationUrl);
            } else {
                const field = document.createElement('textarea');
                field.value = this.registrationUrl;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            this.status = 'copied';
            this.resetStatus();
        },
        async shareRegistration() {
            try {
                if (navigator.share) {
                    await navigator.share({
                        title: this.labels.shareTitle,
                        text: this.labels.shareText,
                        url: this.registrationUrl
                    });
                    this.status = 'shared';
                    this.resetStatus();
                    return;
                }
                await this.copyLink();
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    try { await this.copyLink(); } catch (_) { window.prompt(this.labels.copyPrompt, this.registrationUrl); }
                }
            }
        }
    }"
    class="inline-flex"
    data-registration-share
>
    <button
        type="button"
        x-on:click="shareRegistration"
        x-bind:title="status === 'copied' ? labels.copied : (status === 'shared' ? labels.shared : labels.share)"
        x-bind:aria-label="status === 'copied' ? labels.copied : (status === 'shared' ? labels.shared : labels.share)"
        @class([
            'grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-violet-50 hover:text-violet-700 focus-visible:ring-2 focus-visible:ring-violet-500' => $compact,
            'inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:border-violet-300 hover:bg-violet-100 focus-visible:ring-2 focus-visible:ring-violet-500' => ! $compact,
        ])
    >
        <i x-show="status === 'idle'" data-lucide="share-2" class="size-4"></i>
        <i x-cloak x-show="status !== 'idle'" data-lucide="check" class="size-4 text-emerald-600"></i>
        @unless ($compact)
            <span x-text="status === 'copied' ? labels.linkCopied : (status === 'shared' ? labels.linkShared : labels.shareLink)">{{ $term('Share registration link') }}</span>
        @endunless
    </button>
</span>
