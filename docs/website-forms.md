# Website forms

Open **Website Studio → Forms & Submissions** to manage testimony, prayer request, contact, and feedback forms. Access uses the existing `manage studio` permission (or Super Administrator role) and is scoped to the administrator's church.

Expand a form to edit its title, introduction, field label, button text, confirmation message, email requirement, accent/background colors, field corners, and spacing. The design preview updates immediately; use **Save form** to apply changes. Button text automatically switches between black and white for contrast.

Each form has a shareable `/site/{church-slug}/forms/{type}` URL, displayed in the editor. Enabled forms are also linked from the website's contact section. These URLs can be used in Website Studio navigation or section buttons. Disabling a form or the website blocks both viewing and submitting it.

To embed a form, open a section in Website Studio, add the **Form** widget to a column, and choose its form type. Customize visibility, text, colors, field style, spacing, email requirements, confirmation, and submission actions directly inside the widget. The widget editor uses compact settings without an embedded preview. Save the section and assign it to your desired pages. Each widget saves its own settings; the separate Forms & Submissions page still manages standalone form defaults and the inbox. Existing widgets start with those defaults when first edited.

Confirmation and validation appear inline; without JavaScript, visitors return to the original page with confirmation or validation feedback. Multiple widgets work independently. Website previews disable submission, and hidden widgets reject submissions. The backend resolves widget settings from the saved section, so public visitors cannot override validation or staff assignment through request fields.

Choose **Save to inbox** or **Save and assign to staff**. Assignment records a responsible user; it does not send email or publish the message. The inbox supports type/status filters, assignment, private notes, and New / In progress / Resolved / Archived statuses. Submissions and private notes are never displayed on public pages.

Deploy the new migration with `php artisan migrate` and build assets with `npm run build`. The public endpoint includes CSRF protection, validation, a honeypot, and a five-submissions-per-minute rate limit. Form settings live in `churches.settings.website_forms`; submissions live in `website_submissions`.

Verification: `php artisan test --filter="WebsiteFormsTest|ChurchWebsiteTest"`.
