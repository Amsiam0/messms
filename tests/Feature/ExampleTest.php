<?php

test('the root path redirects to the admin panel', function () {
    $this->get('/')->assertRedirect('/admin');
});
