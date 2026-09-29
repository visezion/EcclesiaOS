<script>
window.ecclesiaFormWidgets = {{ \Illuminate\Support\Js::from([
    'defaults' => \App\Support\WebsiteForms::forChurch($church),
    'staff' => \App\Models\User::query()->where('church_id', $church->id)->orderBy('name')->get(['id', 'name'])->toArray(),
]) }};
</script>
