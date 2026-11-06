# RiffRaff

> Holds spam form submissions for review, with the reason each one was caught.

RiffRaff checks every Statamic form submission against the [RiffRaff](https://riff-raff.dev) spam scoring API. Anything flagged is kept out of your form submissions and notification emails, and waits in the control panel, where you can release a false positive or delete it.

## Requires a RiffRaff account

The addon is free, but the checking is done by the [RiffRaff](https://riff-raff.dev) API, a separate service with a free tier and paid plans. **[Sign up at riff-raff.dev](https://riff-raff.dev/register)** to get an account before installing. The content of each form submission (all fields except those you exclude, plus the sender's email address and subject where found) is sent to `https://api.riff-raff.dev` over HTTPS to be scored. See [riff-raff.dev](https://riff-raff.dev) for plans and prices, and [DOCUMENTATION.md](DOCUMENTATION.md#what-data-leaves-the-site) for exactly what is sent.

## Features

- Checks every form submission, with no template changes
- Holds suspected spam under **Tools → RiffRaff**, out of your submissions list and inbox
- Shows the score of each held submission and the reason for every point scored
- Releases a false positive as a normal form submission, or deletes held spam one at a time or all at once
- Scores forms posted within seconds of loading, using a signed timestamp the visitor cannot edit
- Drops an identical submission sent again within a minute, such as a double click on Submit
- Connects a site with one command, without anyone copying an API key around
- Lets submissions through as normal if RiffRaff cannot be reached, so a genuine enquiry is not lost

## Requirements

- Statamic 5.74 or later, or Statamic 6.26 or later
- PHP 8.2 or later
- A RiffRaff account and API key

Saving the key from the control panel or with `riffraff:connect` needs Statamic 6. On Statamic 5, set the key in `.env`.

## How to Install

If you do not have a RiffRaff account yet, [create one at riff-raff.dev](https://riff-raff.dev/register) first. The free tier is enough to get going.

Then run the following command from your project root:

``` bash
composer require alt-design/alt-riffraff
```

Then connect the site to RiffRaff by running this on the server (Statamic 6):

``` bash
php please riffraff:connect "Acme website"
```

It prints a code. A manager of your RiffRaff team enters it at [riff-raff.dev/connect](https://riff-raff.dev/connect), and the key is saved to the addon settings without anyone copying it. Check it worked with `php please riffraff:status`. See [Connecting a site](DOCUMENTATION.md#connecting-a-site) for running it through Forge and what to do if it refuses.

Alternatively, create a key under [Settings → API tokens](https://riff-raff.dev/settings/api-tokens) in your RiffRaff account and add it to your `.env` file, which always takes priority over a saved one:

``` bash
ALT_RIFFRAFF_API_KEY=
```

## Basic usage

1. The addon checks every form submission for spam. There is nothing to add to your templates.
2. A submission RiffRaff flags is held. The visitor still sees the form's normal success message, and no notification email is sent.
3. Held submissions are under **Tools → RiffRaff** in the control panel, with the score and the reasons for each. Open one to **Release** it as a normal submission or delete it.

Held submissions are stored as YAML under `content/riffraff`. They contain whatever the visitor typed, so add `content/riffraff` to `.gitignore` if your content is committed to git.

## Permissions

RiffRaff registers a **View RiffRaff** permission. It covers viewing, releasing and deleting held submissions, and is checked on the server for every RiffRaff page and action. Super users always have it. Anyone else needs a role that grants it, under **Users → Roles**.

## Documentation

[DOCUMENTATION.md](DOCUMENTATION.md) covers configuration, excluding fields from what is sent, submission timing, duplicate submissions, how held submissions work and exactly what data leaves the site. Changes are listed in [CHANGELOG.md](CHANGELOG.md).

## Testing

``` bash
composer install
composer test
```

## Questions etc

Drop us a big shout-out if you have any questions, comments, or concerns. We're always looking to improve our addons, so if you have any feature requests, we'd love to hear them.

Bug reports and support requests go to [GitHub issues](https://github.com/alt-design/Alt-RiffRaff-Addon/issues). Questions about the RiffRaff service itself, such as plans, billing or the dashboard, are best raised through [riff-raff.dev](https://riff-raff.dev).

### Starter Kits
- [Alt Starter Kit](https://statamic.com/starter-kits/alt-design/alt-starter-kit)

### Addons
- [Alt Admin Bar Addon](https://github.com/alt-design/Alt-Admin-Bar-Addon)
- [Alt AI Addon](https://github.com/alt-design/Alt-AI-Addon)
- [Alt Akismet Addon](https://github.com/alt-design/Alt-Akismet-Addon)
- [Alt Cookies Addon](https://github.com/alt-design/Alt-Cookies-Addon)
- [Alt Google 2FA Addon](https://github.com/alt-design/Alt-Google-2fa-Addon)
- [Alt Google Maps Addon](https://github.com/alt-design/Alt-google-maps-addon)
- [Alt Inbound Addon](https://github.com/alt-design/Alt-Inbound-Addon)
- [Alt Live Editor Addon](https://github.com/alt-design/Alt-Live-Editor-Addon)
- [Alt Password Protect Addon](https://github.com/alt-design/Alt-Password-Protect-Addon)
- [Alt Redirect Addon](https://github.com/alt-design/Alt-Redirect-Addon)
- [Alt SEO Addon](https://github.com/alt-design/Alt-SEO-Addon)
- [Alt Sitemap Addon](https://github.com/alt-design/Alt-Sitemap-Addon)
- [Alt Tabs Addon](https://github.com/alt-design/Alt-Tabs-Addon)
- [RiffRaff Addon](https://github.com/alt-design/Alt-RiffRaff-Addon)

## Postcardware

Send us a postcard from your hometown if you like this addon. We love getting mail from other cool peeps!

Alt Design  
St Helens House  
Derby  
DE1 3EE  
UK
