# Changelog

EcclesiaOS uses GitHub Releases as the authoritative changelog for deployed application versions.

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
