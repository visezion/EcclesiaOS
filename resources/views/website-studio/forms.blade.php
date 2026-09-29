<x-app-layout title="Forms & Submissions" :breadcrumbs="$breadcrumbs">
    <link rel="stylesheet" href="{{ asset('css/website/forms.css') }}">
    <div class="space-y-6">
        <header><h1 class="text-3xl font-semibold">Forms & Submissions</h1><p class="mt-2 text-slate-500">Design your public forms, choose what happens on submission, and follow up with your visitors.</p></header>
        @if(session('status'))<div role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="dashboard-card space-y-4 p-6">
            <h2 class="text-xl font-semibold">Form design & actions</h2>
            <p class="text-sm text-slate-500">Changes apply after saving. Every submission is stored privately in this inbox.</p>
            @foreach($forms as $type => $form)
                @php($editing = old('form_type') === $type)
                @php($draft = $editing ? array_replace($form, old()) : $form)
                <details class="rounded-xl border border-slate-200 p-4" @if($editing) open @endif>
                    <summary class="cursor-pointer font-semibold">{{ $types[$type] }} <span class="ml-2 text-sm text-slate-500">{{ $form['enabled'] ? 'Enabled' : 'Disabled' }}</span></summary>
                    <div class="mt-4 grid gap-6 xl:grid-cols-2" data-form-designer>
                        <form method="POST" action="{{ route('website-studio.forms.settings', $type) }}" class="space-y-4">
                            @csrf @method('PUT')
                            <input type="hidden" name="form_type" value="{{ $type }}">
                            <div class="flex flex-wrap gap-4">
                                <label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($draft['enabled'])> Enable form</label>
                                <label><input type="hidden" name="require_email" value="0"><input type="checkbox" name="require_email" value="1" @checked($draft['require_email'])> Require email</label>
                            </div>
                            @foreach(['title' => ['Title',120], 'description' => ['Introduction',1000], 'message_label' => ['Message field label',120], 'submit_label' => ['Button text',80], 'success_message' => ['Confirmation message',500]] as $field => [$label,$limit])
                                <label class="block text-sm font-medium">{{ $label }}<input class="mt-1 w-full rounded-lg border border-slate-300 p-2" name="{{ $field }}" value="{{ $draft[$field] }}" maxlength="{{ $limit }}" @required($field !== 'description')></label>
                            @endforeach
                            <div class="grid grid-cols-2 gap-4">
                                <label class="block text-sm font-medium">Accent color<input type="color" name="accent_color" value="{{ $draft['accent_color'] }}" class="mt-1 block h-10 w-full"></label>
                                <label class="block text-sm font-medium">Background color<input type="color" name="background_color" value="{{ $draft['background_color'] }}" class="mt-1 block h-10 w-full"></label>
                                <label class="block text-sm font-medium">Field style<select name="style" class="mt-1 w-full rounded-lg border border-slate-300 p-2">@foreach(['rounded'=>'Rounded','square'=>'Square'] as $value=>$label)<option value="{{ $value }}" @selected($draft['style']===$value)>{{ $label }}</option>@endforeach</select></label>
                                <label class="block text-sm font-medium">Spacing<select name="spacing" class="mt-1 w-full rounded-lg border border-slate-300 p-2">@foreach(['spacious'=>'Spacious','compact'=>'Compact'] as $value=>$label)<option value="{{ $value }}" @selected($draft['spacing']===$value)>{{ $label }}</option>@endforeach</select></label>
                            </div>
                            <label class="block text-sm font-medium">When submitted<select name="action" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><option value="inbox" @selected($draft['action']==='inbox')>Save to inbox</option><option value="assign" @selected($draft['action']==='assign')>Save and assign to staff</option></select></label>
                            <label class="block text-sm font-medium">Assigned staff<select name="assigned_to" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><option value="">Choose staff</option>@foreach($users as $staff)<option value="{{ $staff->id }}" @selected((string)$draft['assigned_to']===(string)$staff->id)>{{ $staff->name }}</option>@endforeach</select></label>
                            <button class="rounded-xl bg-violet-700 px-5 py-3 font-semibold text-white">Save form</button>
                            @if($form['enabled'])<a href="{{ route('website.forms.show', ['church'=>$church->slug,'type'=>$type]) }}" target="_blank" rel="noopener" class="ml-3 text-violet-700 underline">Open public form</a>@endif
                            <p class="break-all text-xs text-slate-500">Use this link in your website navigation or section buttons: {{ route('website.forms.show', ['church'=>$church->slug,'type'=>$type]) }}</p>
                        </form>
                        <div><p class="mb-2 text-sm font-semibold text-slate-500">Live design preview</p>
                            <div class="church-form-page" data-form-preview style="min-height:0;padding:20px">
                                <div class="church-form-card" style="padding:24px">
                                    <span class="church-form-back">{{ $church->name }}</span><h1 data-preview-title></h1><p data-preview-description></p>
                                    <div class="church-form-fields">
                                        <label>Your name *<input placeholder="Your name" disabled></label>
                                        <label><span data-preview-email>Email</span><input placeholder="you@example.com" disabled></label>
                                        <label>Phone (optional)<input placeholder="Phone number" disabled></label>
                                        <label><span data-preview-message></span><textarea rows="3" placeholder="Write your message…" disabled></textarea></label>
                                        <button type="button" data-preview-button tabindex="-1">Send message</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            @endforeach
        </section>
        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Submission inbox</h2>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <label>Form<select name="type" class="ml-2 rounded-lg border border-slate-300 p-2"><option value="">All forms</option>@foreach($types as $type=>$label)<option value="{{ $type }}" @selected(request('type')===$type)>{{ $label }}</option>@endforeach</select></label>
                <label>Status<select name="status" class="ml-2 rounded-lg border border-slate-300 p-2"><option value="">All statuses</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-white">Filter</button>
            </form>
            @forelse($submissions as $submission)
                <article class="dashboard-card space-y-4 p-6">
                    <div class="flex flex-wrap justify-between gap-2"><h3 class="font-semibold">{{ $submission->name }} · {{ $types[$submission->type] }}</h3><span class="text-sm text-slate-500">{{ $submission->created_at->format('M j, Y H:i') }} · {{ $statuses[$submission->status] }}</span></div>
                    <p class="break-words text-sm text-slate-500">{{ $submission->email }} {{ $submission->phone }}</p>
                    <p class="whitespace-pre-wrap break-words">{{ $submission->message }}</p>
                    <form method="POST" action="{{ route('website-studio.forms.update', $submission) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div class="flex flex-wrap gap-4">
                            <label>Status<select name="status" class="ml-2 rounded-lg border border-slate-300 p-2">@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($submission->status===$value)>{{ $label }}</option>@endforeach</select></label>
                            <label>Assign to<select name="assigned_to" class="ml-2 rounded-lg border border-slate-300 p-2"><option value="">Unassigned</option>@foreach($users as $staff)<option value="{{ $staff->id }}" @selected($submission->assigned_to==$staff->id)>{{ $staff->name }}</option>@endforeach</select></label>
                        </div>
                        <label class="block text-sm font-medium">Private notes<textarea name="private_notes" rows="2" maxlength="10000" class="mt-1 w-full rounded-lg border border-slate-300 p-2">{{ $submission->private_notes }}</textarea></label>
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-white">Update submission</button>
                    </form>
                </article>
            @empty
                <div class="dashboard-card p-8 text-center text-slate-500">No submissions match these filters. Share your public forms to start receiving messages.</div>
            @endforelse
            {{ $submissions->links() }}
        </section>
    </div>
    <script src="{{ asset('js/website-studio/forms.js') }}" defer></script>
</x-app-layout>
