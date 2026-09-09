# RiffRaff

RiffRaff checks every Statamic form submission against the [RiffRaff](https://riff-raff.dev) spam detection API. Submissions flagged as spam are held back before they reach the site's normal form submissions and are queued for review in the control panel, where they can be released as false positives or deleted.

## Installation

```bash
composer require alt-design/alt-riffraff
```

The addon's config file is published automatically after install. To publish it manually:

```bash
php artisan vendor:publish --tag="alt-design/alt-riffraff"
```

This creates `config/alt-riffraff.php`.

## Configuration

Configuration lives in `config/alt-riffraff.php` and is driven by the following environment variables.

| Variable | Required | Description |
| --- | --- | --- |
| `ALT_RIFFRAFF_API_KEY` | Recommended | Your RiffRaff API key. This is the preferred way to authenticate and, if set, takes priority over `ALT_RIFFRAFF_EMAIL` / `ALT_RIFFRAFF_PASSWORD`. |
| `ALT_RIFFRAFF_EMAIL` | Only if not using an API key | The email address of your RiffRaff account. Used to log in and obtain a token on each request when no API key is set. |
| `ALT_RIFFRAFF_PASSWORD` | Only if not using an API key | The password for the RiffRaff account above. |
| `ALT_RIFFRAFF_BASE_URL` | No | Overrides the RiffRaff API base URL. Defaults to `https://api.riff-raff.dev`. Only needed for self-hosted or non-standard installations. |

### Authentication

An API key is the preferred method of authentication. Generate one from your RiffRaff account and set it as `ALT_RIFFRAFF_API_KEY`.

If no API key is set, the addon falls back to email and password: it logs in against the RiffRaff API on each request using `ALT_RIFFRAFF_EMAIL` and `ALT_RIFFRAFF_PASSWORD` to obtain a token. This is kept for backwards compatibility with sites configured before API key support was added; new installs should use an API key.

If neither an API key nor a working email and password are configured, spam checking is silently skipped and all submissions are allowed through as normal.

### Excluding fields from the content sent to RiffRaff

Every submitted field is concatenated into a single block of content and sent to RiffRaff for evaluation, except for fields listed under `excluded_content_fields` in the config file:

```php
'excluded_content_fields' => [
    'page_uri',
    'form_reference',
    'enquiry_source',
    'honeypot',
],
```

Add the handle of any field you don't want included, for example internal tracking fields, hidden metadata, or anything that shouldn't leave the site. The form's configured honeypot field is always excluded automatically, in addition to anything listed here.

## How held submissions work

When RiffRaff flags a submission as spam, the addon:

1. Stops the submission being saved as a normal Statamic form submission.
2. Writes it, along with the spam score, threshold and the reasons RiffRaff gave for flagging it, to a YAML file under `content/riffraff/` in the site's content storage.

Held submissions never reach the form's usual submissions list until they're released.

### Finding held submissions

Held submissions appear under **Tools → RiffRaff** in the control panel. This link is only visible to users with the **View RiffRaff** permission, which can be granted per role in **Users → Roles**.

Each entry shows a preview of the submission along with its spam score and the reasons it was flagged.

### Releasing a false positive

Open a held submission from the RiffRaff listing and use **Release**. This saves the submission as a normal Statamic form submission, exactly as if RiffRaff had not flagged it, and removes it from the held queue.

### Deleting held submissions

A held submission can be deleted individually from its listing, or all held submissions can be cleared at once from the RiffRaff listing.

## What data leaves the site

The addon only sends data to the RiffRaff API (`ALT_RIFFRAFF_BASE_URL`, `https://api.riff-raff.dev` by default) over HTTPS, and only in these cases:

- **On every form submission** (unless spam checking is skipped due to missing credentials): the concatenated content of all submitted fields except those in `excluded_content_fields` and the form's honeypot field, plus, if they can be identified from the submission, the sender's email address and a subject line.
- **When authenticating with email and password** (only if no API key is configured): the configured `ALT_RIFFRAFF_EMAIL` and `ALT_RIFFRAFF_PASSWORD` are sent to the RiffRaff login endpoint to obtain a token.
- **When viewing the RiffRaff control panel page**: a request is made to RiffRaff's usage endpoint to display how much of your plan's quota has been used. No site or submission data is sent with this request.

No data is sent anywhere else, and no data leaves the site for form submissions that are not evaluated (for example, when no credentials are configured).
