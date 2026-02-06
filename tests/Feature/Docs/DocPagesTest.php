<?php

test('docs index returns 200 and lists page links', function () {
    $this->get('/docs')
        ->assertSuccessful()
        ->assertSee('Authentication')
        ->assertSee('Mailboxes');
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
        ->assertSee('/api/mailboxes')
        ->assertSee('/api/mailboxes/{mailbox}/folders');
});

test('docs unknown slug returns 404', function () {
    $this->get('/docs/nonexistent')
        ->assertNotFound();
});

test('docs pages do not require authentication', function () {
    $this->get('/docs')->assertSuccessful();
    $this->get('/docs/authentication')->assertSuccessful();
    $this->get('/docs/mailboxes')->assertSuccessful();
});
