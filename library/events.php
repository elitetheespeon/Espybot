<?php
//Update invite data (30 seconds)
if($f3->get('update_invites')){
    $discord->loop->addPeriodicTimer($f3->get('update_invites_interval'), function () use ($discord) {
        update_invites();
    });
}

//Set event callbacks
$discord->on(\Discord\WebSockets\Event::GUILD_BAN_ADD, function ($userData) use ($logger, $discord, $plugins) {
    call_event('GUILD_BAN_ADD',$userData);
});

$discord->on(\Discord\WebSockets\Event::GUILD_BAN_REMOVE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('GUILD_BAN_REMOVE',$userData);
});

$discord->on(\Discord\WebSockets\Event::GUILD_MEMBER_ADD, function ($userData) use ($logger, $discord, $plugins) {
    call_event('GUILD_MEMBER_ADD',$userData);
});

$discord->on(\Discord\WebSockets\Event::GUILD_MEMBER_REMOVE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('GUILD_MEMBER_REMOVE',$userData);
});

$discord->on(\Discord\WebSockets\Event::PRESENCE_UPDATE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('PRESENCE_UPDATE',$userData);
});

$discord->on(\Discord\WebSockets\Event::CHANNEL_CREATE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('CHANNEL_CREATE',$userData);
});

$discord->on(\Discord\WebSockets\Event::CHANNEL_DELETE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('CHANNEL_DELETE',$userData);
});

$discord->on(\Discord\WebSockets\Event::CHANNEL_PINS_UPDATE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('CHANNEL_PINS_UPDATE',$userData);
});

$discord->on(\Discord\WebSockets\Event::VOICE_STATE_UPDATE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('VOICE_STATE_UPDATE',$userData);
});

$discord->on(\Discord\WebSockets\Event::MESSAGE_UPDATE, function ($userData) use ($logger, $discord, $plugins) {
    call_event('MESSAGE_UPDATE',$userData);
});