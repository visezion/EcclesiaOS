<div data-copilot-answer data-conversation-id="{{ $conversationId ?? '' }}">
    <div class="copilot-markdown">{!! $result['answer_html'] ?? e($result['answer'] ?? '') !!}</div>
    @if (! in_array(($result['intent'] ?? ''), ['error', 'capabilities', 'unknown'], true))
        <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3 text-[10px] font-semibold text-slate-400"><span class="text-violet-500">●</span> Sources checked: {{ number_format((int) ($result['source_count'] ?? 0)) }} verified records.</div>
    @endif
</div>
