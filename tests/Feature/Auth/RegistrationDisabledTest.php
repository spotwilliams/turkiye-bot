<?php

test('public registration route is unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Walk In',
        'email' => 'walkin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});
