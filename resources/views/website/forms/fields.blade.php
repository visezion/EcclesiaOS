@php($currentFormType = $formType ?? $type ?? 'contact')
@php($messagePlaceholder = ['prayer' => 'How can we pray for you today?', 'testimony' => 'Tell us what God has done in your life.', 'feedback' => 'What would you like us to know?', 'contact' => 'How can we help you?'][$currentFormType] ?? 'Share your message with us.')
<label class="church-form-message"><span>{{ $form['message_label'] }} <span class="church-form-required" aria-hidden="true">*</span></span><textarea name="message" rows="5" maxlength="10000" placeholder="{{ $messagePlaceholder }}" required>{{ ($useOldInput ?? true) ? old('message') : '' }}</textarea></label>
<fieldset class="church-form-contact">
    <legend>Contact information</legend>
    <p class="church-form-help">Let us know how to reach you.</p>
    <div class="church-form-contact-grid">
        <label class="church-form-name"><span>Full name <span class="church-form-required" aria-hidden="true">*</span></span><input name="name" autocomplete="name" maxlength="120" placeholder="Your full name" value="{{ ($useOldInput ?? true) ? old('name') : '' }}" required></label>
        <label><span>Email address <span class="{{ $form['require_email'] ? 'church-form-required' : 'church-form-optional' }}">{{ $form['require_email'] ? '*' : '(optional)' }}</span></span><input type="email" name="email" autocomplete="email" maxlength="180" placeholder="you@example.com" value="{{ ($useOldInput ?? true) ? old('email') : '' }}" @required($form['require_email'])></label>
        <label><span>Phone number <span class="church-form-optional">(optional)</span></span><input type="tel" name="phone" autocomplete="tel" maxlength="60" placeholder="Your phone number" value="{{ ($useOldInput ?? true) ? old('phone') : '' }}"></label>
    </div>
</fieldset>
<div hidden aria-hidden="true"><label>Leave this empty<input name="website_url" tabindex="-1" autocomplete="off"></label></div>
<div class="church-form-footer">
    <p class="church-form-privacy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg><span>Shared privately with our church team.<br>Your message will not appear on the website.</span></p>
    <button type="submit"><span>{{ $form['submit_label'] }}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
    <span class="church-form-required-note">* Required fields</span>
</div>
