# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- Forms rendered with a `{{ form:create }}` tag pair now carry a hidden `_riffraff_loaded` input holding the time the form loaded, signed with the site's `APP_KEY`. On submission the addon verifies it and sends RiffRaff `seconds_to_submit`, so very fast submissions can be scored. A missing, altered or stale value sends nothing. No template changes are needed. A RiffRaff API without support for the field ignores it.
- `timing_max_age` config option (`ALT_RIFFRAFF_TIMING_MAX_AGE`, 3600 seconds by default). A config file published before this release does not need it.

## [1.4.1] - 2026-11-03

### Fixed

- A form submission is let through unchecked when RiffRaff cannot be reached or does not answer within 10 seconds. Until now a connection failure stopped the submission with an error, and a slow answer held it for up to 30 seconds. Each time it happens a warning is logged, without the submission.

### Security

- Build dependencies updated with `npm audit fix` (vite, browserslist, nanoid, source-map-js, baseline-browser-mapping). These are only used to build the control panel styles; the shipped assets are unchanged.

## [1.4.0] - 2026-11-03

### Added

- `php please riffraff:connect "Site name"` connects a site without anyone copying a key. It prints a code, a manager of the RiffRaff team approves it in the dashboard, and the key is saved to the addon settings. The key is never printed. It refuses when saving the key would be unsafe or would not take effect: `ALT_RIFFRAFF_API_KEY` is set, the settings file is tracked in git, Statamic's git automation would commit it, git cannot say, or a zero downtime deploy would lose it. `--replace` replaces a saved key; `--force` gets past every check. `--wait` sets how long it waits for approval, and running it again picks up the request still waiting, kept encrypted in the site's cache. Needs Statamic 6, which added addon settings, and an `https://` RiffRaff address.
- `php please riffraff:status` reports where the key comes from, whether RiffRaff accepts it, and the site, plan and usage it belongs to, without printing the key.
- `api_device_code_path` and `api_device_token_path` config options. A config file published before this release does not need them.

### Changed

- The API key setting's instructions now recommend `riffraff:connect`.

## [1.3.0] - 2026-10-02

### Added

- Marketplace metadata: `description`, `authors`, `support` and `keywords` in `composer.json`, plus `DOCUMENTATION.md` and this changelog.
- `excluded_content_fields` config option to control which form fields are excluded from the content sent to RiffRaff for evaluation, alongside the form's honeypot field.
- Duplicate submissions are dropped. An identical submission to the same form within `duplicate_window` seconds (60 by default, set with `ALT_RIFFRAFF_DUPLICATE_WINDOW`) is stopped before the spam check, as a silent failure: nothing is stored or emailed, and the visitor still sees the success message. Forms that legitimately receive identical repeats can be listed under `duplicate_window_except`, and a window of `0` turns it off.

### Changed

- The submitted email address and subject line, when they can be identified, are now sent to RiffRaff alongside the content, instead of being folded into the content string. This stops that metadata leaking into the evaluated content.
- `orchestra/testbench` bumped to `^10.8` and `statamic/cms` support dropped to `^5.74|^6.26`. The previous `^4.0|^5.74|^6.26` range paired with `testbench ^9.0` could not be installed: Statamic 4 needs Laravel 9/10 while testbench `^9.0` needs Laravel 11, and none of the supported Statamic versions overlap with Laravel 11 at all. Statamic 4 is dropped rather than reintroduced against a compatible testbench version, since it would mean supporting Laravel 9/10 and Vue 2 alongside Laravel 12/13, which this codebase does not target.
- Added an explicit `php` constraint (`^8.2`).

## [1.2.4] - 2026-08-20

Re-tagged release, identical to 1.2.3.

## [1.2.3] - 2026-08-20

### Fixed

- Tailwind build output.

## [1.2.2] - 2026-08-06

### Fixed

- Statamic 6 control panel bug: the RiffRaff listing linked to forms using a raw `cp_url()` path instead of the `forms.show` named route, which broke under Statamic 6.

## [1.2.1] - 2026-07-28

### Fixed

- Security patches (DEV-5519).

### Changed

- Narrowed the supported `statamic/cms` range from `^4.0|^5.0|^6.0` to `^4.0|^5.74|^6.26`.

## [1.2.0] - 2026-05-15

### Added

- Statamic 6 support.
- Usage display in the control panel, showing how much of the RiffRaff plan quota has been used.

### Changed

- Ported the control panel UI from Vue components to Blade views.

## [1.1.0] - 2026-05-14

### Added

- API key authentication (`ALT_RIFFRAFF_API_KEY`), preferred over email and password.
- `SECURITY.md` with a vulnerability reporting process.

## [1.0.5] - 2025-05-09

### Fixed

- Hotfix for the control panel UI following the 1.0.4 rebuild.

## [1.0.4] - 2025-05-09

### Changed

- Tweaked the control panel UI for quicker spam reviewing.

## [1.0.3] - 2025-05-08

### Fixed

- Form data containing array values is now ignored when building the content sent to RiffRaff, fixing an issue with ambiguous data.

## [1.0.2] - 2025-05-06

### Fixed

- Null check when building the content sent to RiffRaff.

## [1.0.1] - 2025-05-06

### Added

- MIT licence declared in `composer.json`.

### Changed

- Widened supported `statamic/cms` versions from `^5.0` to `^4.0|^5.17.0`.

## [1.0.0] - 2025-04-23

Initial release.

### Added

- Automatic spam checking of Statamic form submissions against the RiffRaff API.
- Held spam submissions, reviewable from the "Review Spam" tool in the control panel.
