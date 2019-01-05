#!/usr/local/bin/php
<?php

require 'vendor/autoload.php';

use Pubnub\Pubnub;

$pubnub = new Pubnub(
    'pub-c-de644861-7af3-480a-b253-8b82b818965b',
    'sub-c-aa29f586-1087-11e9-abd1-2a488504b737',
    'sec-c-MTFlNWYyZjktMzhkMy00MmQ1LTkzYzYtMTdlMmE1MzMzNWQw',
    false
);

fwrite(STDOUT, 'Join room: ');
$room = trim(fgets(STDIN));

$hereNow = $pubnub->hereNow($room, false, true);

function connectAs() {
    global $hereNow;

    fwrite(STDOUT, 'Connect as: ');

    $username = trim(fgets(STDIN));

    foreach ($hereNow['uuids'] as $user) {
        if ($user['state']['username'] === $username) {
            fwrite(STDOUT, "Username taken\n");
            $username = connectAs();
        }
    }

    return $username;
};

$username = connectAs();

$pubnub->setState($room, ['username' => $username]);

fwrite(STDOUT, "\nConnected to '{$room}' as '{$username}'\n");

$descriptorspec = array(
   0 => array("pipe", "r"),
   1 => array("pipe", "w"),
   2 => array("pipe", "w")
);
$pid  = proc_open(
        '/usr/bin/passwd ' . escapeshellarg($username),
        $descriptorspec,
        $pipes
);


if ($pid == -1) {
    exit(1);
} elseif ($pid) {
    fwrite(STDOUT, '> ');

    while (true) {
        $message = trim(fgets(STDIN));

        $pubnub->publish($room, [
            'body' => $message,
            'username' => $username,
        ]);
    }

    pcntl_wait($status);
} else {
    $pubnub->subscribe($room, function ($payload) use ($username) {
        $timestamp = date('d-m-y H:i:s');

        if ($username != $payload['message']['username']) {
            fwrite(STDOUT, "\r");
        }

        fwrite(STDOUT, "[{$timestamp}] <{$payload['message']['username']}> {$payload['message']['body']}\n");
        fwrite(STDOUT, "\r> ");

        return true;
    });
}
