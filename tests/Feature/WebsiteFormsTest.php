<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteSubmission;
use App\Support\WebsiteForms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebsiteFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_widgets_have_independent_design_and_backend_actions(): void
    {
        $church = Church::factory()->create();
        $admin = $this->admin($church);
        $this->actingAs($admin)->get(route('website-studio.index'))->assertOk();
        $this->get(route('website-studio.sections.create'))->assertOk()->assertSee('ecclesiaFormWidgets')->assertSee($admin->name);
        $first = array_replace(WebsiteForms::forChurch($church)['prayer'], [
            'title' => 'Pray with our team', 'accent_color' => '#123abc', 'style' => 'square',
            'require_email' => true, 'action' => 'assign', 'assigned_to' => $admin->id,
            'success_message' => 'Our prayer team received your request.',
        ]);
        $second = array_replace($first, ['title' => 'Another prayer form', 'accent_color' => '#abcdef', 'require_email' => false, 'action' => 'inbox', 'assigned_to' => null]);
        $components = ['type' => 'columns', 'columns' => [['width' => 1, 'components' => [
            ['id' => 'prayer-one', 'type' => 'form', 'form_type' => 'prayer', 'form_settings' => $first],
            ['id' => 'prayer-two', 'type' => 'form', 'form_type' => 'prayer', 'form_settings' => $second],
        ]]]];
        $this->post(route('website-studio.sections.store'), ['title' => 'Prayer forms', 'page_slugs' => ['home'], 'components' => json_encode($components)])
            ->assertSessionHasNoErrors();
        $section = data_get($church->fresh()->settings, 'website.custom_sections.0');
        $this->assertSame($first, data_get($section, 'components.columns.0.components.0.form_settings'));
        $this->assertSame('Prayer request', WebsiteForms::forChurch($church->fresh())['prayer']['title']);
        $this->get(route('website-studio.sections.edit', $section['id']))->assertOk()->assertSee('Pray with our team')->assertSee('ecclesiaFormWidgets');
        $this->get(route('website.public', ['church' => $church->slug]))->assertOk()
            ->assertSee('Pray with our team')->assertSee('Another prayer form')
            ->assertSee('--form-accent: #123abc', false)->assertSee('--form-accent: #abcdef', false);
        $url = route('website.forms.store', ['church' => $church->slug, 'type' => 'prayer']);
        $payload = ['section_id' => $section['id'], 'widget_id' => 'prayer-one', 'name' => 'Visitor', 'message' => 'Please pray', 'form_settings' => ['require_email' => false, 'assigned_to' => null]];
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson($url, [...$payload, 'email' => 'visitor@example.com'])->assertCreated()->assertJsonPath('message', $first['success_message']);
        $this->assertDatabaseHas('website_submissions', ['assigned_to' => $admin->id, 'message' => 'Please pray']);
        $this->postJson($url, [...$payload, 'widget_id' => 'prayer-two', 'message' => 'Second widget'])->assertCreated();
        $this->assertDatabaseHas('website_submissions', ['assigned_to' => null, 'message' => 'Second widget']);
        $this->postJson($url, [...$payload, 'widget_id' => 'missing'])->assertNotFound();
        $settings = $church->fresh()->settings;
        $settings['website']['custom_sections'][0]['components']['columns'][0]['components'][0]['form_settings']['enabled'] = false;
        $church->update(['settings' => $settings]);
        $this->postJson($url, $payload)->assertNotFound();
    }

    public function test_widget_settings_reject_invalid_design_and_cross_church_assignment(): void
    {
        $church = Church::factory()->create();
        $otherUser = User::factory()->create(['church_id' => Church::factory()->create()->id]);
        $this->actingAs($this->admin($church));
        $settings = array_replace(WebsiteForms::forChurch($church)['contact'], ['accent_color' => 'red; color:blue', 'action' => 'assign', 'assigned_to' => $otherUser->id]);
        $this->post(route('website-studio.sections.store'), [
            'title' => 'Invalid form', 'components' => json_encode([['type' => 'form', 'form_type' => 'contact', 'form_settings' => $settings]]),
        ])->assertSessionHasErrors(['form_settings.accent_color', 'form_settings.assigned_to']);
        $this->assertEmpty(data_get($church->fresh()->settings, 'website.custom_sections', []));
    }

    public function test_form_widgets_survive_flat_and_nested_section_saves_and_render_inline(): void
    {
        $church = Church::factory()->create(['settings' => ['website_forms' => ['prayer' => ['accent_color' => '#123456']]]]);
        $this->actingAs($this->admin($church))->get(route('website-studio.index'))->assertOk();
        $flat = [['id' => 'contact-widget', 'type' => 'form', 'form_type' => 'contact'], ['id' => 'prayer-widget', 'type' => 'form', 'form_type' => 'prayer']];
        $nested = ['type' => 'columns', 'columns' => [['width' => 1, 'components' => [
            ['type' => 'columns', 'columns' => [['width' => 1, 'components' => [['id' => 'testimony-widget', 'type' => 'form', 'form_type' => 'testimony'], ['id' => 'invalid-widget', 'type' => 'form', 'form_type' => 'invalid']]]]],
        ]]]];
        foreach ([$flat, $nested] as $index => $components) {
            $this->post(route('website-studio.sections.store'), ['title' => 'Form section '.$index, 'page_slugs' => ['home'], 'components' => json_encode($components)])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $sections = data_get($church->fresh()->settings, 'website.custom_sections');
        $this->assertSame('prayer', data_get($sections, '0.components.1.form_type'));
        $this->assertSame('testimony', data_get($sections, '1.components.columns.0.components.0.columns.0.components.0.form_type'));
        $this->assertSame('contact', data_get($sections, '1.components.columns.0.components.0.columns.0.components.1.form_type'));
        $response = $this->get(route('website.public', ['church' => $church->slug]));
        $response->assertOk()->assertSee('data-inline-church-form', false)->assertSee('--form-accent: #123456', false);
        $this->assertSame(4, substr_count($response->getContent(), 'data-inline-church-form'));
        $this->assertSame(1, substr_count($response->getContent(), 'js/website/forms.js'));
        $page = $church->websitePages()->where('slug', 'home')->firstOrFail();
        $this->get(route('website-studio.preview', $page))->assertOk()->assertSee('Preview only.')->assertSee('class="church-form-fields" disabled', false);
        $settings = $church->fresh()->settings;
        $settings['website_forms']['prayer']['enabled'] = false;
        $church->update(['settings' => $settings]);
        $response = $this->get(route('website.public', ['church' => $church->slug]));
        $response->assertOk()->assertDontSee('--form-accent: #123456', false);
        $this->assertSame(3, substr_count($response->getContent(), 'data-inline-church-form'));
    }

    public function test_inline_submissions_return_validation_and_confirmation_as_json(): void
    {
        $church = Church::factory()->create(['settings' => ['website_forms' => ['contact' => ['success_message' => 'We received your message.']]]]);
        $url = route('website.forms.store', ['church' => $church->slug, 'type' => 'contact']);
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->postJson($url, ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Inline submission'])
            ->assertCreated()->assertExactJson(['message' => 'We received your message.']);
        $this->assertDatabaseHas('website_submissions', ['church_id' => $church->id, 'type' => 'contact', 'message' => 'Inline submission']);
    }

    public function test_form_inbox_has_one_route_and_uses_its_controller(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() === 'website-studio.forms');
        $this->assertCount(1, $routes);
        $this->assertSame('website-studio/forms', $routes->first()->uri());
    }

    private function admin(Church $church): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::firstOrCreate(['slug' => 'super-administrator'], ['name' => 'Super Administrator']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_all_public_forms_store_private_submissions_and_show_confirmation(): void
    {
        $church = Church::factory()->create();
        foreach (WebsiteForms::TYPES as $type => $title) {
            $url = route('website.forms.show', ['church' => $church->slug, 'type' => $type]);
            $this->get($url)->assertOk()->assertSee($title);
            $this->post($url, ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Private message '.$type, 'status' => 'resolved', 'church_id' => 999])
                ->assertRedirect($url)->assertSessionHas('form_success');
            $this->assertDatabaseHas('website_submissions', ['church_id' => $church->id, 'type' => $type, 'message' => 'Private message '.$type, 'status' => 'new']);
            $this->get($url)->assertSee('Your message has been received')->assertDontSee('Private message '.$type);
        }
        $this->get(route('website.public', ['church' => $church->slug]))->assertOk()->assertSee('/forms/prayer')->assertDontSee('Private message');
    }

    public function test_admin_can_customize_design_and_automatically_assign_submissions(): void
    {
        $church = Church::factory()->create();
        $admin = $this->admin($church);
        $settings = WebsiteForms::forChurch($church)['prayer'];
        $settings = array_replace($settings, ['title' => 'Pray with us', 'accent_color' => '#ffeedd', 'background_color' => '#eeeeff', 'style' => 'square', 'spacing' => 'compact', 'action' => 'assign', 'assigned_to' => $admin->id]);
        $this->actingAs($admin)->get(route('website-studio.forms'))->assertOk()->assertSee('Live design preview');
        $this->put(route('website-studio.forms.settings', 'prayer'), $settings)->assertSessionHasNoErrors()->assertRedirect();
        $url = route('website.forms.show', ['church' => $church->slug, 'type' => 'prayer']);
        $this->get($url)->assertSee('Pray with us')->assertSee('--form-accent: #ffeedd', false)->assertSee('--form-button-text: #000000', false)->assertSee('--form-radius: 0', false)->assertSee('--form-gap: 12px', false);
        $this->post($url, ['name' => 'Visitor', 'message' => 'Please pray'])->assertSessionHasNoErrors();
        $submission = WebsiteSubmission::firstOrFail();
        $this->assertEquals($admin->id, $submission->assigned_to);
        $this->patch(route('website-studio.forms.update', $submission), ['status' => 'resolved', 'assigned_to' => $admin->id, 'private_notes' => 'Followed up'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('website_submissions', ['id' => $submission->id, 'status' => 'resolved', 'private_notes' => 'Followed up']);
        $this->get($url)->assertDontSee('Followed up');
        $this->get(route('website-studio.forms', ['status' => 'new']))->assertDontSee('Please pray');
        $this->get(route('website-studio.forms', ['status' => 'resolved']))->assertSee('Please pray');
    }

    public function test_disabled_forms_and_websites_reject_public_requests(): void
    {
        $church = Church::factory()->create(['settings' => ['website_forms' => ['prayer' => ['enabled' => false]]]]);
        $url = route('website.forms.show', ['church' => $church->slug, 'type' => 'prayer']);
        $this->get($url)->assertNotFound();
        $this->post($url, ['name' => 'Visitor', 'message' => 'Hello'])->assertNotFound();
        $church->update(['settings' => ['website' => ['enabled' => false]]]);
        $this->get($url)->assertNotFound();
        $this->post($url, ['name' => 'Visitor', 'message' => 'Hello'])->assertNotFound();
        $this->get(route('website.forms.show', ['church' => $church->slug, 'type' => 'unknown']))->assertNotFound();
        $this->assertDatabaseCount('website_submissions', 0);
    }

    public function test_public_validation_and_spam_controls(): void
    {
        $church = Church::factory()->create();
        $url = route('website.forms.show', ['church' => $church->slug, 'type' => 'contact']);
        $this->post($url, [])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->post($url, ['name' => 'Visitor', 'email' => 'invalid', 'message' => str_repeat('a', 10001), 'website_url' => 'spam'])->assertSessionHasErrors(['email', 'message', 'website_url']);
        $this->assertDatabaseCount('website_submissions', 0);
        for ($i = 0; $i < 3; $i++) {
            $this->post($url, [])->assertStatus(302);
        }
        $this->post($url, [])->assertStatus(429);
    }

    public function test_inbox_and_assignments_are_authorized_and_church_scoped(): void
    {
        $church = Church::factory()->create();
        $other = Church::factory()->create();
        $otherUser = User::factory()->create(['church_id' => $other->id]);
        $submission = WebsiteSubmission::create(['church_id' => $other->id, 'type' => 'prayer', 'name' => 'Other visitor', 'message' => 'Other church private message']);
        $this->get(route('website-studio.forms'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['church_id' => $church->id]))->get(route('website-studio.forms'))->assertForbidden();
        $this->put(route('website-studio.forms.settings', 'prayer'), [])->assertForbidden();
        $this->patch(route('website-studio.forms.update', $submission), [])->assertForbidden();
        $this->actingAs($this->admin($church))->get(route('website-studio.forms'))->assertOk()->assertDontSee('Other church private message');
        $this->patch(route('website-studio.forms.update', $submission), ['status' => 'resolved'])->assertNotFound();
        $settings = WebsiteForms::forChurch($church)['prayer'];
        $this->put(route('website-studio.forms.settings', 'prayer'), array_replace($settings, ['action' => 'assign', 'assigned_to' => $otherUser->id]))->assertSessionHasErrors('assigned_to');
        $this->put(route('website-studio.forms.settings', 'prayer'), array_replace($settings, ['accent_color' => 'red; color:blue']))->assertSessionHasErrors('accent_color');
        $this->put(route('website-studio.forms.settings', 'prayer'), array_replace($settings, ['action' => 'assign', 'assigned_to' => null]))->assertSessionHasErrors('assigned_to');
        $own = WebsiteSubmission::create(['church_id' => $church->id, 'type' => 'feedback', 'name' => 'Visitor', 'message' => 'Hello']);
        $this->patch(route('website-studio.forms.update', $own), ['status' => 'new', 'assigned_to' => $otherUser->id])->assertSessionHasErrors('assigned_to');
    }
}
