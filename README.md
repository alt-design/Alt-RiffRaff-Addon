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

In your `.env` file, add your RiffRaff Credentials

``` bash
ALT_RIFFRAFF_EMAIL=
ALT_RIFFRAFF_PASSWORD=
```

## How to Use

1. The addon will automatically check all form submissions for spam.
2. If a submission is detected as spam, it will be automatically "held" and you will be able to manage it from the "Review Spam" in the "Tools" section of the control panel.

