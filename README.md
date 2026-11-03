# Statamic RiffRaff 

> RiffRaff is a Statamic addon that analyses all form submissions and checks for spam. It uses the RiffRaff API to check for spam and provides a simple interface to manage spam submissions.

## Features

- Detect spam submissions 
- Automatically "hold" spam submissions
- Simple interface to manage spam submissions
- Easy to install and use

## How to Install

composer require the addon using the following command:

``` bash
composer require alt-design/alt-riffraff
```

Then connect the site to RiffRaff by running this on the server (Statamic 6):

``` bash
php please riffraff:connect "Acme website"
```

It prints a code. A manager of your RiffRaff team enters it in the RiffRaff dashboard, and the key is saved to the addon settings without anyone copying it. Check it worked with `php please riffraff:status`. See [Connecting a site](DOCUMENTATION.md#connecting-a-site) for running it through Forge and what to do if it refuses.

Alternatively, add a key to your `.env` file, which always takes priority over a saved one:

``` bash
ALT_RIFFRAFF_API_KEY=
```

If you'd rather authenticate with an email and password, omit the key and set these instead:

``` bash
ALT_RIFFRAFF_EMAIL=
ALT_RIFFRAFF_PASSWORD=
```

## How to Use

1. The addon will automatically check all form submissions for spam.
2. If a submission is detected as spam, it will be automatically "held" and you will be able to manage it from the "Review Spam" in the "Tools" section of the control panel.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for full configuration details, how held submissions work, and what data is sent to RiffRaff.

## Questions etc

Drop us a big shout-out if you have any questions, comments, or concerns. We're always looking to improve our addons, so if you have any feature requests, we'd love to hear them.

### Starter Kits
- [Alt Starter Kit](https://statamic.com/starter-kits/alt-design/alt-starter-kit)

### Addons
- [Alt Cookies Addon](https://github.com/alt-design/Alt-Cookies-Addon)
- [Alt Redirect Addon](https://github.com/alt-design/Alt-Redirect-Addon)
- [Alt Akismet Addon](https://github.com/alt-design/Alt-Akismet-Addon)
- [Alt Inbound Addon](https://github.com/alt-design/Alt-Inbound-Addon)
- [Alt Sitemap Addon](https://github.com/alt-design/Alt-Sitemap-Addon)
- [Alt Password Protect Addon](https://github.com/alt-design/Alt-Password-Protect-Addon)
- [Alt Google 2FA Addon](https://github.com/alt-design/Alt-Google-2fa-Addon)

## Postcardware

Send us a postcard from your hometown if you like this addon. We love getting mail from other cool peeps!

Alt Design  
St Helens House  
Derby  
DE1 3EE  
UK   
