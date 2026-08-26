@php
    $itemIndex = $index ?? '__INDEX__';
    $item = $item ?? [];
    $isTemplate = $itemIndex === '__INDEX__';
    $type = $item['type'] ?? 'link';
    $children = $item['children'] ?? [];
@endphp
<div class="navigation-item" data-navigation-row>
    <button type="button" class="navigation-handle" aria-label="{{ __('Drag to reorder menu item') }}" title="{{ __('Drag to reorder menu item') }}"><i data-lucide="grip-vertical" class="size-4 pointer-events-none"></i></button>
    <div class="navigation-item-fields">
        <label class="min-w-0"><span class="field-label">{{ __('Menu label') }}</span><input name="navigation[{{ $itemIndex }}][label]" value="{{ $isTemplate ? '' : ($item['label'] ?? '') }}" class="field-input" maxlength="60" required data-menu-label></label>
        <label class="min-w-0"><span class="field-label">{{ __('Link') }}</span><input name="navigation[{{ $itemIndex }}][url]" value="{{ $isTemplate ? '#' : ($item['url'] ?? '') }}" class="field-input" maxlength="500" placeholder="/site/church/about, #contact, https://..." required></label>
        <label class="min-w-0"><span class="field-label">{{ __('Menu type') }}</span><select name="navigation[{{ $itemIndex }}][type]" class="field-input" data-menu-type><option value="link" @selected($type === 'link')>{{ __('Link') }}</option><option value="dropdown" @selected($type === 'dropdown')>{{ __('Dropdown') }}</option><option value="mega" @selected($type === 'mega')>{{ __('Mega menu') }}</option></select></label>
    </div>
    <div class="navigation-actions">
        <label class="visibility-toggle"><input type="hidden" name="navigation[{{ $itemIndex }}][visible]" value="0"><input type="checkbox" name="navigation[{{ $itemIndex }}][visible]" value="1" @checked($isTemplate || ($item['visible'] ?? true)) data-menu-visible><span><i data-lucide="eye" class="size-3.5"></i>{{ __('Visible') }}</span></label>
        <button type="button" class="navigation-icon-button" data-move-up title="{{ __('Move up') }}"><i data-lucide="arrow-up" class="size-4"></i></button>
        <button type="button" class="navigation-icon-button" data-move-down title="{{ __('Move down') }}"><i data-lucide="arrow-down" class="size-4"></i></button>
        <button type="button" class="navigation-icon-button is-danger" data-navigation-remove title="{{ __('Remove') }}"><i data-lucide="trash-2" class="size-4"></i></button>
    </div>
    <div class="navigation-submenu" data-submenu-editor @if($type === 'link') hidden @endif>
        <div class="navigation-submenu-heading"><div><strong>{{ __('Submenu links') }}</strong><span>{{ __('Add grouped links beneath this menu item.') }}</span></div><button type="button" class="navigation-add navigation-add-child" data-submenu-add><i data-lucide="plus" class="size-4"></i>{{ __('Add submenu link') }}</button></div>
        <div class="navigation-submenu-list" data-submenu-list>
            @unless($isTemplate)
                @foreach($children as $childIndex => $child)
                    @include('website-studio._navigation-child', ['parentIndex' => $itemIndex, 'childIndex' => $childIndex, 'child' => $child])
                @endforeach
            @endunless
        </div>
    </div>
</div>
