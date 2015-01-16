# Aipo to Google Calendar

## Setting

### upload.php

```
$clientId = 'Your Client ID'; 
$authEmail = 'Your Auth Email';
$p12Key = 'Your P12 Key File Path';
$targetCalendar = 'Your Calendar Name';
$aipoIcal = 'Your Aipo Ical Path';
```

## Usage

```
/usr/bin/wget --http-user=username --http-passwd=password http://aipodomain/ical/calendar.ics -O /pathto/aipo.ics
/usr/bin/php /pathto/upload.php
```

