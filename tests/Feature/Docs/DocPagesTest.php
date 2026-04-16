<?php

test('docs index returns 200 and lists page links', function () {
    $this->get('/docs')
        ->assertSuccessful()
        ->assertSee('Authentication')
        ->assertSee('Mailboxes')
        ->assertSee('Calendars')
        ->assertSee('Slack');
});

test('docs authentication page returns 200 with rendered content', function () {
    $this->get('/docs/authentication')
        ->assertSuccessful()
        ->assertSee('Bearer YOUR_API_TOKEN')
        ->assertSee('Laravel Sanctum');
});

test('docs mailboxes page returns 200 with endpoint documentation', function () {
    $this->get('/docs/mailboxes')
        ->assertSuccessful()
        ->assertSee('/{account}/mailbox')
        ->assertSee('/{account}/mailbox/{folder}/messages');
});

test('docs calendars page returns 200 with endpoint documentation', function () {
    $this->get('/docs/calendars')
        ->assertSuccessful()
        ->assertSee('/{account}/calendar')
        ->assertSee('google_calendar_used');
});

test('docs slack page returns 200 with tool documentation', function () {
    $this->get('/docs/slack')
        ->assertSuccessful()
        ->assertSee('list_slack_channels')
        ->assertSee('read_slack_messages')
        ->assertSee('send_slack_message');
});

test('docs unknown slug returns 404', function () {
    $this->get('/docs/nonexistent')
        ->assertNotFound();
});

test('docs pages do not require authentication', function () {
    $this->get('/docs')->assertSuccessful();
    $this->get('/docs/authentication')->assertSuccessful();
    $this->get('/docs/mailboxes')->assertSuccessful();
    $this->get('/docs/calendars')->assertSuccessful();
    $this->get('/docs/slack')->assertSuccessful();
});
