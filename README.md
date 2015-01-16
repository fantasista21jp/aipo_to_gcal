# Aipo to Google Calendar

## Install

```
git clone git@github.com:fantasista21jp/aipo_to_gcal.git
cd aipo_to_gcal
composer install
```

## Setting

### upload.php

```
$clientId = 'Your Client ID';
$authEmail = 'Your Auth Email';
$p12Key = 'Your P12 Key File Path';
$targetCalendar = 'Your Calendar Name';
$aipoIcal = 'Your Aipo Ical Path';
$aipoUser = 'Your Aipo User';
$aipoPasswd = 'Your Aipo Password';
$aipoIcalUrl = 'Your Aipo Ical URL';
```

## Usage

```
/usr/bin/php /pathto/upload.php
```
