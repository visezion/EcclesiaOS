<?php

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class WebsiteForms
{
    public const TYPES = ['testimony' => 'Testimony', 'prayer' => 'Prayer request', 'contact' => 'Contact', 'feedback' => 'Feedback'];

    public static function forChurch(Church $church): array
    {
        $forms = [];
        foreach (self::TYPES as $type => $label) {
            $forms[$type] = array_replace([
                'enabled' => true,
                'title' => $label,
                'description' => 'Share your message with our church team.',
                'message_label' => 'Your message',
                'submit_label' => 'Send message',
                'success_message' => 'Thank you. Your message has been received by our church team.',
                'require_email' => $type === 'contact',
                'action' => 'inbox',
                'assigned_to' => null,
                'accent_color' => '#6d4aff',
                'background_color' => '#f5f3ff',
                'style' => 'rounded',
                'spacing' => 'spacious',
            ], data_get($church->settings, 'website_forms.'.$type, []));
        }

        return $forms;
    }

    public static function buttonTextColor(string $hex): string
    {
        $channels = array_map(function ($channel) {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722 > 0.179 ? '#000000' : '#ffffff';
    }

    public static function validateWidgets(array $node, Church $church): void
    {
        if (($node['type'] ?? null) === 'form' && isset($node['form_settings'])) {
            Validator::make(['form_settings' => $node['form_settings']], [
                'form_settings' => ['array'],
                'form_settings.enabled' => ['sometimes', 'boolean'],
                'form_settings.title' => ['sometimes', 'required', 'string', 'max:120'],
                'form_settings.description' => ['nullable', 'string', 'max:1000'],
                'form_settings.message_label' => ['sometimes', 'required', 'string', 'max:120'],
                'form_settings.submit_label' => ['sometimes', 'required', 'string', 'max:80'],
                'form_settings.success_message' => ['sometimes', 'required', 'string', 'max:500'],
                'form_settings.require_email' => ['sometimes', 'boolean'],
                'form_settings.accent_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'form_settings.background_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'form_settings.style' => ['sometimes', Rule::in(['rounded', 'square'])],
                'form_settings.spacing' => ['sometimes', Rule::in(['compact', 'spacious'])],
                'form_settings.action' => ['sometimes', Rule::in(['inbox', 'assign'])],
                'form_settings.assigned_to' => ['nullable', 'required_if:form_settings.action,assign', Rule::exists('users', 'id')->where('church_id', $church->id)],
            ])->validate();
        }
        foreach ($node as $key => $child) {
            if (is_array($child) && $key !== 'form_settings') {
                self::validateWidgets($child, $church);
            }
        }
    }

    public static function forWidget(Church $church, array $component): ?array
    {
        $base = self::forChurch($church)[$component['form_type'] ?? 'contact'] ?? null;
        if (! $base) {
            return null;
        }

        return array_replace($base, array_intersect_key($component['form_settings'] ?? [], $base));
    }

    public static function findWidget(array $node, string $id): ?array
    {
        if (($node['type'] ?? null) === 'form' && ($node['id'] ?? null) === $id) {
            return $node;
        }
        foreach ($node as $key => $child) {
            if (is_array($child) && $key !== 'form_settings' && ($found = self::findWidget($child, $id))) {
                return $found;
            }
        }

        return null;
    }
}
