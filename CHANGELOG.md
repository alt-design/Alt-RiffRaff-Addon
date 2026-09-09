# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- Marketplace metadata: `description`, `authors`, `support` and `keywords` in `composer.json`, plus `DOCUMENTATION.md` and this changelog.
- `excluded_content_fields` config option to control which form fields are excluded from the content sent to RiffRaff for evaluation, alongside the form's honeypot field.

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
