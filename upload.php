#!/usr/bin/php
<?php

require 'vendor/autoload.php';

require_once 'configs.php';

$scopes = array('https://www.googleapis.com/auth/calendar');

// iCal データ取得
$icalContents = file_get_contents($aipoIcal);
$ical = new vcalendar();
$res = $ical->parse($icalContents);

$aipoEvents = array();
if ($res == true) {
    foreach ($ical->components as $key => $event) {
        $summary = $event->getProperty('summary');
        if (!$summary) {
            continue;
        }
        $dtstamp = $event->getProperty('dtstamp');
        $dtstart = $event->getProperty('dtstart');
        $dtend = $event->getProperty('dtend');
        $uid = $event->getProperty('uid');
        preg_match(('/^([0-9A-Za-z]+)-([0-9]+)\@(.+?)$/'), $uid, $matches);
        $aipoId = $matches[2];

        $start = new Google_Service_Calendar_EventDateTime();
        if (!empty($event->dtstart['params']) && $event->dtstart['params']['VALUE'] == 'DATE') {
            $start->setDate($dtstart['year'] . '-' . sprintf('%02d', $dtstart['month']) . '-' . sprintf('%02d', $dtstart['day']));
        } else {
            $start->setDateTime($dtstart['year'] . '-' . sprintf('%02d', $dtstart['month']) . '-' . sprintf('%02d', $dtstart['day']) . 'T' . $dtstart['hour'] . ':' . $dtstart['min'] . ':' . $dtstart['sec'] . '+09:00');
        }

        $end = new Google_Service_Calendar_EventDateTime();
        if (!empty($event->dtend['params']) && $event->dtend['params']['VALUE'] == 'DATE') {
            $end->setDate($dtend['year'] . '-' . sprintf('%02d', $dtend['month']) . '-' . sprintf('%02d', $dtend['day']));
        } else {
            $end->setDateTime($dtend['year'] . '-' . sprintf('%02d', $dtend['month']) . '-' . sprintf('%02d', $dtend['day']) . 'T' . $dtend['hour'] . ':' . $dtend['min'] . ':' . $dtend['sec'] . '+09:00');
        }

        $aipoEvent = compact('summary', 'dtstamp', 'dtstart', 'dtend', 'uid', 'aipoId', 'start', 'end');
        $aipoEvents[$aipoId] = $aipoEvent;
    }
}

$p12KeyContents = file_get_contents($p12Key);
$client = new Google_Client();
$client->setClientId($clientId);
$credential = new Google_Auth_AssertionCredentials($authEmail, $scopes, $p12KeyContents);
$client->setAssertionCredentials($credential);

$service = new Google_Service_Calendar($client);

$calendarLists = $service->calendarList->listCalendarList();
foreach ($calendarLists['items'] as $key => $calendar) {
    $calenderId = $calendar->id;
    $calenderName = $calendar->getSummary();
    if ($calenderName != $targetCalendar) {
        continue;
    }

    //既存イベント取得  
    $events = $service->events->listEvents($calenderId);
    while(true) {
        foreach ($events->getItems() as $event) {
            $eventId = $event->getId();
            $icalUid = $event->getICalUID();
            preg_match(('/^([0-9A-Za-z]+)-([0-9]+)\@(.+?)$/'), $icalUid, $matches);
            $aipoId = $matches[2];
            $summary = $event->getSummary();
            $start = $event->getStart();
            $end = $event->getEnd();

            // aipo にデータがない場合は削除
            if (empty($aipoEvents[$aipoId])) {
                // 直近3ヶ月の情報だけ消す
                // ※ aipo の ical が直近3ヶ月前までのデータなので
                if (strtotime('- 3month') < strtotime($start->date ? $start->date : $start->dateTime)) {
                    // 削除処理
                    $service->events->delete($calenderId , $eventId);
                    echo '[Delete Event]' . ':' . $summary . ' (' . ($start->date ? $start->date : $start->dateTime) . ')' . "\n";
                }
                continue;
            }
            $aipoEvent = $aipoEvents[$aipoId];

            // 登録データに差異があれば削除
            if ($aipoEvent['summary'] != $summary
                || $aipoEvent['start']->date != $start->date
                || $aipoEvent['start']->dateTime != $start->dateTime
                || $aipoEvent['start']->timeZone != $start->timeZone
                || $aipoEvent['end']->date != $end->date
                || $aipoEvent['end']->dateTime != $end->dateTime
                || $aipoEvent['end']->timeZone != $end->timeZone) {
                // 削除処理
                $service->events->delete($calenderId , $eventId);
                echo '[Delete Event]' . ':' . $summary . ' (' . ($start->date ? $start->date : $start->dateTime) . ')' . "\n";
                continue;
            }

            // 既に登録されているデータなので、更新リストから除外
            unset($aipoEvents[$aipoId]);
        }

        $pageToken = $events->getNextPageToken();
        if ($pageToken) {
            $optParams = array('pageToken' => $pageToken);
            $events = $service->events->listEvents($calenderId, $optParams);
        } else {
            break;
        }
    }

    // イベント登録
    if (!empty($aipoEvents)) {
        foreach ($aipoEvents as $aipoEvent) {
            $event = new Google_Service_Calendar_Event();
            $event->setSummary($aipoEvent['summary']);
            $event->setStart($aipoEvent['start']);
            $event->setEnd($aipoEvent['end']);
            $event->setICalUID($aipoEvent['uid']);
            $service->events->insert($calenderId, $event);
            echo '[Insert Event]' . ':' . $aipoEvent['summary'] . ' (' . ($aipoEvent['start']->date ? $aipoEvent['start']->date : $aipoEvent['start']->dateTime) . ')' . "\n";
        }
    }
}

exit(0);
