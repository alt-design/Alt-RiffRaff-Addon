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
| `ALT_RIFFRAFF_DUPLICATE_WINDOW` | No | How many seconds an identical submission to the same form is treated as a duplicate and dropped. Defaults to `60`. Set it to `0` to turn duplicate checking off. See [Duplicate submissions](#duplicate-submissions). |

### Authentication

An API key is the preferred method of authentication. The addon looks for one in this order:

1. `ALT_RIFFRAFF_API_KEY` in the environment. When it is set, it always wins.
2. The key saved in the addon settings, by `php please riffraff:connect` (see [Connecting a site](#connecting-a-site)) or in the control panel under the addon's settings. This needs Statamic 6.
3. `ALT_RIFFRAFF_EMAIL` and `ALT_RIFFRAFF_PASSWORD`: the addon logs in against the RiffRaff API on each request to obtain a token. This is kept for sites configured before API key support was added; new installs should use an API key.

If none of these is configured, spam checking is skipped and all submissions are allowed through as normal.

### Connecting a site

Run this on the site's server, from the site's directory:

```bash
php please riffraff:connect "Acme website"
```

The name is how the site appears in RiffRaff, up to 100 characters. The command prints a code and a link to RiffRaff. A manager of your RiffRaff team opens the link, enters the code and approves it, and the command saves the key to the addon settings (`resources/addons/alt-riffraff.yaml`) and prints `Connected as Acme website`. The key itself is never printed, so it is safe to run where the output is kept. The code lasts 15 minutes.

If a site with that name already exists in the team, approving the request can replace its key.

#### Running it through Forge

Forge's site Commands panel stops a command after 5 minutes, and does not show the output until the command finishes. Run it in two steps instead:

1. `php please riffraff:connect "Acme website" --wait=0` prints the code and stops straight away.
2. Approve the code in RiffRaff.
3. `php please riffraff:connect --wait=0` picks up the approved request and saves the key.

Running the command again always picks up a request that is still waiting, rather than asking for a new code, unless you give it a different name. `--wait` takes any number of seconds; without it, the command waits until the code expires.

#### When it refuses

The command checks the site before asking RiffRaff for anything, and stops if saving the key would be unsafe or would not take effect:

| It says | Why | What to do |
| --- | --- | --- |
| A key is already saved | Replacing a working key should be deliberate. | Run with `--force`. |
| `ALT_RIFFRAFF_API_KEY` is set | The environment always wins, so a saved key would never be used. | Remove it from the environment, or use the environment instead. |
| Statamic's git automation covers the file | Statamic 6 tracks `resources/addons` and commits when addon settings are saved, so the key would be committed, and pushed if pushing is on. | Take `resources/addons` out of `paths` in `config/statamic/git.php`. |
| The file is tracked in git | The key would end up in a commit. | Remove the file from the repository and add it to `.gitignore`, then deploy. |
| The site deploys with zero downtime | Each release starts from a fresh checkout, so the key would be gone after the next deploy. | Add `resources/addons` to the site's shared paths in Forge. A shared path starts empty, so copy the current `resources/addons` into it first, and note that settings committed under it will no longer reach the server. |

`--force` saves the key in every one of these cases. The command does not edit `.gitignore` on the server itself, because an edited, tracked `.gitignore` can make the next deploy's `git pull` fail, and ignoring a file that is already tracked has no effect.

The git and zero downtime checks only apply when addon settings are stored as files. On Statamic 5, which has no addon settings, the command stops and points you at `ALT_RIFFRAFF_API_KEY`.

### Checking the connection

```bash
php please riffraff:status
```

This reports where the key comes from (the environment, the addon settings, or the email and password login), whether RiffRaff accepts it, and the site, plan and usage it belongs to. It warns when a saved key is being ignored because `ALT_RIFFRAFF_API_KEY` is set. It never prints the key, and exits with an error when the site is not connected or the key does not work.

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

## Duplicate submissions

The same submission can arrive more than once, for example when a visitor clicks Submit again while a slow request is still running. RiffRaff drops a submission when an identical one (same form, same values) arrived within the last `duplicate_window` seconds, 60 by default. This happens before the spam check, so a repeat never reaches the RiffRaff API.

A dropped duplicate is a silent failure: nothing is stored and no notification email is sent, but the visitor still sees the form's normal success message. Each one is logged as a warning, `Dropped duplicate form submission`, with the form handle.

Duplicate checking runs whether or not RiffRaff credentials are configured, and it happens entirely on the site. Only a hash of the submission is kept in the site's cache, for the length of the window.

Some forms legitimately receive identical submissions close together, such as a login or a lookup form. List their handles under `duplicate_window_except` so they are never checked:

```php
'duplicate_window_except' => [
    'delivery_lookup',
],
```

To turn duplicate checking off entirely, set `ALT_RIFFRAFF_DUPLICATE_WINDOW=0`.

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
- **When running `riffraff:connect`**: the site name you give it, then a code RiffRaff issued, every few seconds until the request is approved, denied or expires.
- **When running `riffraff:status`**: the same usage request as the control panel page.

No data is sent anywhere else, and no data leaves the site for form submissions that are not evaluated (for example, when no credentials are configured).
