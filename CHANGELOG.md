# Changelog

EcclesiaOS uses GitHub Releases as the authoritative changelog for deployed application versions.

## 1.0.23 - 2026-08-10

### Fixed

- Fixed the Docker Composer vendor stage failing because the Composer image does not provide PHP GD, while PhpSpreadsheet requires it.
- Fixed the GitHub release workflow PHP setup missing the GD extension required by Composer and PhpSpreadsheet.
- Fixed SMS and WhatsApp provider enable switches being overwritten by the shared Zender settings form.

### Deployment and compatibility

- Docker runtime images continue to install and enable GD for PhpSpreadsheet support.
- SMS and WhatsApp channels can now be enabled or disabled independently.
- Direct upgrades remain supported from `1.0.0` and later.

## 1.0.21 - 2026-08-04

### Highlights

- Fixed a production-only Bible reader 500 error caused by release packages correctly excluding private translation source files.
- Removed large synchronous Bible imports from normal page requests to prevent request timeouts.
- Hardened Bible Notes, Settings, study-content writes, catalog initialization, and free-translation downloads for production environments.

### Fixed

- Fixed repeated Bible visits inserting the same fallback verses and violating the unique verse-reference index.
- Fixed a fresh production installation working on the first Bible visit and failing with HTTP 500 on a later visit.
- Fixed the reader attempting to import more than 30,000 verses during an ordinary web request when a private KJV source file existed.
- Fixed partially initialized Bible translations repeatedly attempting unsafe repair work during page loads.
- Fixed the Bible Notes book filter using a database-specific string expression that was incompatible with PostgreSQL.
- Fixed multi-word Bible books such as Song of Solomon being reduced to their first word in Notes filters.
- Fixed users without a church triggering non-null database constraint failures when saving bookmarks, notes, or highlights.
- Fixed users without a church being able to enter Bible translation management workflows that require church ownership.
- Fixed malformed legacy reminder-time preferences crashing the Bible Settings page.
- Fixed missing PHP ZIP support, provider connection failures, temporary-file failures, and unwritable storage surfacing as unhandled translation-download errors.

### Improved

- Made lightweight fallback verse creation idempotent and safe under repeated or concurrent requests.
- Changed the Bible reader bootstrap to stop as soon as any translation verses are available.
- Kept full Bible installation in the explicit translation-management workflow instead of the reader request lifecycle.
- Reduced unnecessary writes when the shared free-translation catalog is already initialized.
- Replaced SQL-specific Notes reference parsing with database-portable application parsing.
- Added clear HTTP 422 responses when a church assignment is required for Bible study content.
- Added clear HTTP 502 and 503 responses for external provider and server-capability failures during free-translation downloads.

### Deployment and compatibility

- Direct upgrades remain supported from `1.0.0` and later.
- This release contains no database migration.
- Existing installed translations and saved Bible study content are preserved.
- Production assets are rebuilt by the release workflow and packaged with the required update manifest and checksum.

### Full Changelog

- Removed automatic full KJV installation from `BibleController` page requests.
- Replaced repeated fallback `createMany` calls with conflict-safe verse upserts.
- Added an existence check that makes reader initialization constant-time after the first successful bootstrap.
- Changed free catalog initialization to create only missing definitions.
- Preserved explicit administrator-controlled installation for complete free translations.
- Added graceful handling when the ZIP extension is unavailable.
- Added a bounded connection timeout and friendly upstream failure response for translation downloads.
- Added safe checks for temporary archive creation and writing.
- Added cleanup for invalid temporary ZIP archives.
- Added a storage-write check before reporting a downloaded translation as available.
- Replaced SQLite/MySQL-style `substr` and `instr` Notes filtering with portable reference parsing.
- Preserved full multi-word Bible book names in Notes filter options.
- Added church-assignment guards for bookmark, note, highlight, and translation-management writes.
- Normalized invalid legacy Bible reminder times to the safe 8:00 AM default when rendering settings.
- Added regression coverage for idempotent reader visits, lightweight bootstrap behavior, database-portable book parsing, missing church assignments, and malformed legacy settings.
- Verified all Bible pages, shared navigation routes, production Blade compilation, formatting, and Composer metadata.

## 1.0.20 - 2026-08-04

### Highlights

- Added secure self-service registration for new and returning members, including linked member accounts and a default Member role.
- Added configurable online giving through Stripe, Paystack, and PayPal with administrator-managed credentials, sandbox testing, verified callbacks, and safe financial recording.
- Upgraded user and member directories with real server-side pagination and reliable filtering.
- Redesigned shared under-development pages and corrected missing interface icons across production builds.

### Added

- Added a public member-registration experience with the same branded topbar used by the home page.
- Added separate registration paths for new and returning members with duplicate-member and duplicate-account safeguards.
- Added linked member user accounts, member-to-user relationships, and automatic login after successful registration.
- Added a default Member role with access to Messages and Bible features.
- Added a public online-giving checkout with donor details, amount, currency, fund, note, and provider selection.
- Added Stripe hosted checkout support for global card payments.
- Added Paystack support for Nigerian payments in NGN.
- Added PayPal support for United States payments in USD.
- Added encrypted database-backed payment gateway settings that administrators can update from the GUI.
- Added individual test/live operating modes, enable controls, credential forms, connection tests, and webhook guidance for each payment provider.
- Added payment callback and webhook handling with provider-specific verification.
- Added a payment transaction ledger for provider references, statuses, verified values, timestamps, payload metadata, and linked finance records.
- Added idempotent payment recording to prevent duplicate donations from repeated callbacks.
- Added local Stripe, Paystack, and PayPal logo assets for provider-specific controls.
- Added a Payment Gateways administration route, sidebar entry, permission checks, and responsive management page.
- Added automated feature coverage for member registration, gateway settings, sandbox providers, online giving, user filters, and pagination.

### Improved

- Moved Interface Zoom into the Advanced Branding section while preserving administrator control.
- Made member accounts authenticate through the existing login flow with appropriate default permissions.
- Added clear registration and giving entry points to public and authenticated pages.
- Redesigned the Payment Gateways page to match the existing administration shell, cards, controls, spacing, and responsive behavior.
- Used a standard credit-card icon for the Payment Gateways page and sidebar while retaining provider logos only where provider identity is useful.
- Made the public giving flow show only enabled and fully configured providers.
- Made payment credentials retain their saved encrypted value when an administrator leaves a credential field blank.
- Changed the member directory default page size to 15 and retained 15, 25, and 50 record options.
- Changed User Management to display 20 users per page.
- Reworked User Management search, role, campus, and status filters as server-side filters with combined-query support.
- Preserved User Management filter values across pagination and limited exports to the filtered result set.
- Made the User Management filter toolbar compact and responsive instead of stacking unnecessarily on desktop.
- Redesigned shared planned-module pages with a polished Coming Soon presentation, capability cards, release status, progress timeline, and responsive layout.
- Applied the improved under-development presentation to Reports & Analytics and every module using the shared component.
- Replaced misleading notification actions on planned-module pages with honest release-status guidance.
- Registered the complete icon set required by the new payment and Coming Soon interfaces.

### Fixed

- Fixed oversized and empty User Management sidebar statistic cards, including the Campuses card.
- Fixed User Management filters that appeared to submit but did not reliably filter server-side records.
- Fixed pagination links losing active User Management filters.
- Fixed member and user directories using inconsistent or client-only page limits.
- Fixed missing Payment Gateways page and sidebar icons.
- Fixed missing Custom Reports, Saved Views, Exports, progress, and rollout icons on shared Coming Soon pages.
- Fixed interface icons rendering as blank gray placeholders when their Lucide definitions were omitted from the production bundle.
- Fixed returning-member registration creating duplicate member records.
- Fixed payment callbacks recording donations before provider status, amount, currency, and payment date were verified.
- Fixed repeated provider callbacks being able to create duplicate finance records.
- Fixed login redirects for newly registered member accounts.
- Fixed responsive spacing and wrapping in directory filters, payment controls, planned-module cards, and statistic cards.

### Security

- Encrypts payment gateway credentials at rest and never returns saved secrets to the browser.
- Verifies provider callback signatures or provider API state before recording successful giving.
- Validates payment status, amount, currency, provider reference, and provider timestamp before creating a finance record.
- Rate-limits public registration, checkout, and payment webhook endpoints.
- Applies role and settings permissions to all payment gateway administration endpoints.
- Uses unique transaction references and idempotent recording to protect against replayed callbacks.
- Continues storing account passwords only as secure hashes.

### Deployment and compatibility

- Direct upgrades remain supported from `1.0.0` and later.
- This release contains additive migrations for member-account links, the default Member role, and payment gateway transactions.
- Run database migrations during deployment before enabling member registration or online giving.
- Payment providers remain disabled until an administrator saves valid credentials, selects the correct operating mode, and enables the provider.
- Production assets are rebuilt by the release workflow and packaged with the required update manifest and checksum.

### Full Changelog

- Added new-member self-registration with name, contact, password, and membership details.
- Added returning-member account activation against an existing member record.
- Added validation that prevents duplicate emails, duplicate linked users, and accidental duplicate member profiles.
- Linked member and user models through a nullable user-account association.
- Added an additive migration to connect existing member records to user accounts.
- Added an additive migration and access configuration for the default Member role.
- Granted the Member role access to Messages and Bible without granting staff or administration capabilities.
- Enabled registered members to sign in through the standard login page.
- Added automatic post-registration authentication and safe member landing behavior.
- Reused the public home topbar on the member-registration page.
- Added public navigation links for member registration and online giving.
- Added a gateway-neutral payment contract and manager for provider selection.
- Added Stripe, Paystack, and PayPal provider services.
- Added Stripe Checkout session creation and verified completion handling.
- Added Paystack transaction initialization and verification for NGN payments.
- Added PayPal order creation and capture verification for USD payments.
- Added encrypted GUI-managed provider configuration with environment-variable fallback support.
- Added gateway enable/disable controls and separate Test/Sandbox and Live modes.
- Added per-provider connection tests with visible connection-health results.
- Added provider webhook endpoint instructions and copy controls.
- Added local, reliable provider logos for Stripe, Paystack, and PayPal controls.
- Added a standard credit-card icon to Payment Gateways navigation and page headings.
- Added a responsive public giving form and success/cancellation result screens.
- Added a payment transaction model and additive transaction-ledger migration.
- Recorded provider references, exact amounts, currencies, statuses, provider dates, and verification metadata.
- Linked verified successful transactions to the corresponding finance contribution record.
- Prevented duplicate contribution records when a provider retries a webhook or callback.
- Added throttling to public registration, checkout, and webhook routes.
- Added Payment Gateways to Administration navigation and module registration.
- Added a responsive Payment Gateways dashboard with configuration, enablement, and connection summary cards.
- Added gateway-specific credential fields, operating-mode controls, webhook guidance, and safe-save behavior.
- Added a Giving entry point to the Finance page.
- Set the member directory to 15 records per page with selectable 15, 25, and 50 record sizes.
- Set User Management to 20 records per server-rendered page.
- Added server-side User Management search across supported user identity fields.
- Added working role, campus, and account-status filters.
- Added combined filters, clear controls, query persistence, result totals, and filtered export behavior.
- Improved User Management filter responsiveness and compact desktop layout.
- Corrected the Campuses summary card sizing in User Management.
- Moved Interface Zoom from the general Branding area into Advanced Branding.
- Rebuilt the shared Coming Soon component for Reports & Analytics and other planned modules.
- Added planned-capability cards, development progress, release status, and role-access messaging.
- Added responsive layouts for the Coming Soon hero, capability grid, timeline, and action area.
- Registered Credit Card, Layers, File Download, Rocket, and Construction icons in the application bundle.
- Fixed blank icon placeholders across Payment Gateways and under-development pages.
- Added and expanded automated tests for all new registration, payment, pagination, filtering, and interface behavior.

## 1.0.19 - 2026-08-04

### Highlights

- Added a global administrator-controlled interface zoom setting for consistent sizing across the application.
- Replaced the profile's estimated password status with real password-strength enforcement, live feedback, and honest assessment state.
- Improved dashboard cards and the Calendar, Meetings, Events, and Children & Youth pages for phones, tablets, laptops, and wide displays.
- Made account notifications mark themselves as read when opened and disappear from the topbar unread count.

### Added

- Added an Interface Zoom control under Branding & Appearance with validated values from 70% through 120% in 5% steps.
- Applied the configured zoom to authenticated layouts, login and authentication screens, and public branded pages.
- Added live password-strength feedback to the profile password form.
- Added server-side strong-password requirements: at least 12 characters with uppercase, lowercase, number, and symbol.
- Added stored password-strength assessment metadata after a successful password change without storing plaintext passwords.
- Added a secure account-notification redirect endpoint that marks owned notifications as read before opening their destination.
- Added reusable responsive page headers, action groups, statistic grids, filter grids, content/sidebar layouts, and touch-friendly table containers.
- Added a compact agenda presentation for the Calendar on mobile screens.

### Improved

- Made the sidebar use the exact configured solid background color without color mixing.
- Replaced fractional password-age output with calendar-day labels such as `today`, `1 day ago`, and `N days ago`.
- Reopened the password dialog automatically after password validation errors.
- Made dashboard statistic cards automatically reflow, keep equal row heights, wrap long content, and resize icons and values.
- Made negative dashboard changes use a red downward indicator and neutral changes use a neutral indicator.
- Made page actions, filters, statistic cards, side panels, forms, and tables adapt across screen sizes and interface zoom levels.
- Moved secondary panels below primary content until enough desktop width is available.
- Made Children & Youth create and edit headers stack correctly on narrow screens.

### Fixed

- Fixed account notifications remaining visible in the topbar after the user opened them.
- Fixed recently changed passwords being incorrectly labeled `Strong` based only on password age.
- Fixed legacy passwords being presented as assessed when their strength cannot be recovered from a secure password hash.
- Fixed fractional password ages such as `0.00022562989583333 days ago`.
- Fixed dashboard cards becoming cramped or overflowing at different viewport widths.
- Fixed negative growth values displaying a green upward arrow.
- Fixed Calendar forcing a wide desktop month grid on phones.
- Fixed rigid filter columns and early sidebar splits making Meetings, Events, and Children & Youth pages cramped.

### Removed

- Removed the Active Volunteers summary card from the dashboard while retaining the Volunteers module and page.

### Security

- Prevented open redirects from account notification destinations by allowing only local application URLs.
- Enforced strong passwords on profile password updates using server-side validation.
- Continued storing only password hashes and non-sensitive strength metadata.

### Deployment and compatibility

- Direct upgrades remain supported from `1.0.0` and later.
- This release contains no database migration.
- Production assets are rebuilt by the release workflow and packaged with the required update manifest and checksum.

### Full Changelog

- Account notifications now mark themselves as read when clicked from the topbar or Communications notification list.
- Read notifications are immediately removed from the topbar unread notification count.
- Notification redirects are restricted to safe local destinations with an Account Settings fallback.
- Password age is calculated by calendar day instead of fractional elapsed days.
- Existing passwords without an assessment now display `Not assessed` instead of an unsupported strength claim.
- Profile password changes require at least 12 characters, mixed case, a number, and a symbol.
- The password form provides a live four-stage strength meter and requirement guidance.
- Successful password changes store only the verified label, score, and assessment time.
- Failed password validation automatically reopens the password dialog.
- The sidebar background uses the exact selected solid color.
- Branding & Appearance includes a global Interface Zoom slider from 70% to 120%.
- The configured interface zoom is applied consistently to application, authentication, landing, and feature pages.
- The dashboard Active Volunteers card and its unnecessary summary query were removed.
- Dashboard cache versioning was advanced so the removed card disappears immediately.
- Dashboard summary cards use a fluid auto-fitting grid and equal card heights.
- Long statistic labels, values, and comparison text wrap without overlap.
- Negative and neutral dashboard changes now use semantically correct icons and colors.
- Calendar statistic cards, headers, actions, and side content now reflow responsively.
- Calendar uses a touch-friendly agenda on phones and preserves the month grid on larger displays.
- Meetings headers, actions, filters, tables, and provider panels now adapt to available width.
- Events headers, actions, filters, tables, event details, and side content now adapt to available width.
- Children & Youth statistics, filters, register, inline editor, add panel, and age-group panel now adapt to available width.
- Children & Youth create and edit page headers now stack correctly on narrow screens.
- Wide data tables retain full desktop data while supporting contained momentum scrolling on intermediate widths.
- Added and expanded automated coverage for notifications, password age, password strength, interface zoom, dashboard metrics, and responsive rendering hooks.

## 1.0.18 - 2026-08-04

### Highlights

- Added a complete Bible reading and study workspace with translations, plans, bookmarks, notes, highlights, search, comparison, contextual verse actions, and administrator tools.
- Added church and campus administration improvements, including edit/delete actions, campus cloning, ministry import, and organization terminology throughout the interface.
- Added bulk Module Management controls that let administrators enable or disable complete navigation groups and subgroups.
- Added configurable solid-color sidebar icons and improved sidebar counts, theming, scrolling, and visibility.

### Added

- Added Bible reading and translation foundations backed by real application data.
- Added Bible study workspaces for reading plans, bookmarks, notes, highlights, search, comparison, and settings.
- Added free Bible translation catalogs, bundled local translations, church-licensed translation imports, and installed-translation status.
- Added inline single-verse and multi-verse actions, selected-verse quick actions, contextual study tools, and saved highlight colors.
- Added administrator Bible reading-plan creation, image uploads, image previews, and complete reading-plan workflows.
- Added church and campus edit/delete actions, campus cloning, and ministry imports.
- Added an Ubuntu server deployment guide.
- Added module group and subgroup metadata to the module registry.
- Added an audited bulk module update endpoint for enabling or disabling a complete group or subgroup.
- Added Module Management group/subgroup cards, module counts, enabled progress, and quick Enable/Disable actions.
- Added live unread badges for Communications notifications and Messages inbox items.
- Added a Branding & Appearance option for solid, individually colored sidebar icons.
- Added stable semantic icon colors with a deterministic fallback palette for all remaining sidebar routes.

### Improved

- Applied configurable organization terminology across navigation and the wider interface.
- Redesigned the Bible reader, Bible comparison, highlights, and reading-plan pages.
- Improved Bible book/chapter navigation, automatic navigation, responsive layouts, scrolling, form borders, and full-width plan layouts.
- Added shared navigation across Bible pages and registered the complete Bible icon set.
- Improved Module Management hierarchy, status visibility, bulk-control layout, and required-module protection.
- Made sidebar branding scroll with navigation and kept the profile area isolated from menu content.
- Made the sidebar use its configured solid color without radial or gradient overlays.
- Made the System Name honor the configured Sidebar Text color.
- Added subtle theme-aware sidebar hover/profile borders while keeping active menu items borderless.
- Made colorful sidebar icons fully opaque and solid, with no background, gradient, radial effect, filter, or fade.

### Fixed

- Fixed Bible reader navigation links and dependent Bible reference selectors.
- Fixed missing Bible icons and added Bible relation validation.
- Fixed Bible highlight colors, responsive behavior, separator formatting, and several text-encoding corruption paths.
- Prevented corrupted Bible translation text from reaching the reader.
- Fixed campus editor Alpine expressions.
- Removed the non-functional hardcoded Workflow & Approvals sidebar count.
- Fixed lower sidebar decoration/profile content painting over Administration menu items.
- Fixed sidebar menu visibility and scrolling after custom sidebar colors are applied.
- Fixed filtered bulk module actions so the server updates every matching route, not only visible rows.
- Registered the new Module Management Lucide icons in the production icon bundle.

### Security

- Updated Guzzle from `7.15.1` to `7.15.2` to address noncanonical host and cookie-domain handling advisories.
- Updated the frontend dependency lock to resolve the PostCSS source-map advisory.

### Deployment and compatibility

- Direct upgrades remain supported from `1.0.0` and later.
- The release contains no destructive database migration.
- Production assets are rebuilt by the release workflow and packaged with the required update manifest and checksum.

### Full Changelog

#### Release preparation changes

- Added group/subgroup bulk activation and deactivation to Module Management.
- Added group/subgroup route metadata and counts to the module registry.
- Added audited bulk module setting changes with required-module safeguards.
- Added polished bulk controls, enabled progress indicators, and group/subgroup columns.
- Added real unread sidebar counts for Communications notifications and Messages.
- Removed the hardcoded Workflow & Approvals badge.
- Removed the sidebar radial/gradient color overlay and applied a solid configured sidebar color.
- Fixed sidebar navigation scrolling and clipped decorative content to the profile area.
- Connected the sidebar System Name to the Sidebar Text color setting.
- Added the optional solid-color sidebar icon palette with no icon backgrounds or fades.
- Expanded system settings and feature coverage for the new appearance option.
- Updated vulnerable PHP and JavaScript dependency locks required by the release security gates.
- Registered and verified all static icons introduced by the release.

#### Commit history since v1.0.17

- `81d0cb7` Build Bible reading and translation foundation.
- `d39e07c` Add Bible study workspaces.
- `a873d58` Add Bible search comparison settings and imports.
- `21e18a0` Format Bible translation importer.
- `872e8c3` Fix Bible reader navigation links.
- `e2d3350` Match Bible reader reference layout.
- `37d403c` Add Bible reader icon and color system.
- `8a70bd5` Register all Bible reader icons.
- `4bca382` Match Bible reading plans reference page.
- `a9d7feb` Match Bible bookmarks reference page.
- `5bb361d` Match Bible notes reference page.
- `b7b5067` Match Bible highlights reference page.
- `53c71d8` Match Bible search reference page.
- `9ee17f9` Make Bible settings fully functional.
- `41d010b` Add free Bible translation catalog.
- `5bedf27` Match Bible translations reference page.
- `dbe84aa` Bundle free Bible translations for local install.
- `d90574d` Secure Bible translations and use real reader data.
- `f545edc` Improve Bible book and chapter navigation.
- `83abd9c` Connect Bible study tools to contextual actions.
- `2fe0b70` Show installed Bible translation status.
- `8f04a6f` Add inline Bible verse actions.
- `a5c4a74` Refine inline Bible action controls.
- `3b40f48` Show Bible highlights inline with colors.
- `575f947` Add multi-verse Bible selection actions.
- `72ec33c` Add selected verse quick actions.
- `0d9ff7a` Show saved highlight colors in Bible reader.
- `e32d3d0` Keep Bible reader layout scrollable.
- `e6f4388` Update Bible comparison page design.
- `2891252` Add automatic Bible book and chapter navigation.
- `09feb32` Fix Bible highlight colors and encoding.
- `d7626d2` Normalize Bible reader separators.
- `e51062a` Remove remaining Bible encoding corruption.
- `c3ee741` Add shared navigation to Bible pages.
- `bd8a78f` Prevent corrupted Bible translation text.
- `1d93e53` Hide Bible verse selection checkboxes.
- `1aed653` Bundle more free Bible translations.
- `129881f` Import church licensed Bible translations.
- `5a7ac83` Highlight Bible translation differences.
- `baa9c39` Update Bible highlights page design.
- `f94ee80` Fix Bible highlights responsive layout.
- `d874537` Add administrator Bible plan creation.
- `eb08f34` Build real Bible reading plan workflows.
- `0aa537f` Improve Bible plan form borders.
- `3166858` Open Bible features to authenticated users.
- `a66775f` Fix dependent Bible reference selectors.
- `a2e0e6c` Fix missing icons and validate Bible relations.
- `1b99be9` Redesign Bible reading plans page.
- `6232217` Use full width for Bible plans.
- `58cfdb1` Add reading plan image uploads.
- `22f31da` Add reading plan image previews.
- `1fa7784` Add Ubuntu server deployment guide.
- `48d0dfd` Add church and campus edit/delete actions.
- `9ad2e91` Fix campus editor Alpine expressions.
- `579813e` Add ministry import and campus cloning.
- `f119af1` Apply organization terminology across the interface.

[View the complete comparison between v1.0.17 and v1.0.18](https://github.com/visezion/EcclesiaOS/compare/v1.0.17...v1.0.18).

## 1.0.0

- Added the initial versioned release baseline.
- Added secure GitHub release discovery and update notifications.
- Added Super Administrator approval and update history.
- Added verified release installation, backups, maintenance mode, health checks, and code rollback support.

Release maintainers must update `VERSION` before creating a matching `vX.Y.Z` Git tag. The GitHub release workflow generates the public release notes from merged pull requests.
