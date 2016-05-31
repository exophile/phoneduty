<?php

/**
 *
 * Twilio twimlet for forwarding inbound calls
 * to the on-call engineer as defined in PagerDuty
 *
 * Designed to be hosted on Heroku
 *
 * (c) 2014 Vend Ltd.
 *
 */

require __DIR__ . '/../vendor/autoload.php';

// Set these Heroku config variables
$scheduleID      = getenv('PAGERDUTY_SCHEDULE_ID');
$APItoken        = getenv('PAGERDUTY_API_TOKEN');
$serviceAPItoken = getenv('PAGERDUTY_SERVICE_API_TOKEN');
$domain          = getenv('PAGERDUTY_DOMAIN');
$greeting        = getenv('PHONEDUTY_ANNOUNCE_GREETING');

// Should we announce the local time of the on-call person?
// (helps raise awareness you might be getting somebody out of bed)
$announceTime    = getenv('PHONEDUTY_ANNOUNCE_TIME');

// What language should Twilio use?
$language        = getenv('TWILIO_LANGUAGE');

// Should we record the conversation once connected to the on-call person?
// https://www.twilio.com/docs/api/twiml/dial#attributes-record
$record          = getenv('TWILIO_RECORD');

if (isset($_POST['CallSid'])) {
    session_id($_POST['CallSid']);
}
session_start();
$_SESSION['engineer_accepted_call'] = false;

$pagerduty = new \Vend\Phoneduty\Pagerduty($APItoken, $serviceAPItoken, $domain);

$userID = $pagerduty->getOncallUserForSchedule($scheduleID);
$user = $pagerduty->getUserDetails($userID);

$twilio = new Services_Twilio_Twiml();

$attributes = array(
    'voice' => 'alice',
    'language' => $language
);

if ($greeting != '') {
    $twilio->say(sprintf("%s", $greeting), $attributes);
}

if ($user !== null) {
    $time = "";
    if (($announceTime == 'true' || $announceTime == 'True') && $user['local_time']) {
        $time = sprintf(" The current time in their timezone is %s.", $user['local_time']->format('g:ia'));
    }

    $twilio->say(sprintf("The current on-call engineer is %s." .
        "%s Please hold while we connect you.",
        $user['first_name'], $time), $attributes);
    
    $dialvars = array('action' => "check_if_completed_by_human.php", 'timeout' => 25);
    if ($record != strtolower("false")) {
        if ($record == strtolower("true")) {
            $dialvars['record'] = "true";
        } else {
            $dialvars['record'] = $record;
        }
    }
    $dial = $twilio->dial(NULL, $dialvars);
    $dial->number($user['phone_number'], array('url' => "check_for_human.php"));
} else {
    $twilio->redirect('voicemail.php');
}

// send response
if (!headers_sent()) {
    header('Content-type: text/xml');
}

echo $twilio;

?>
