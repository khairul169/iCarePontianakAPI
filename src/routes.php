<?php

use \Slim\App;

// routes
require_once __DIR__ . '/Routes/Auth.php';
require_once __DIR__ . '/Routes/Users.php';

return function (App $app) {
    // authentication
    $app->group('/auth', function(App $app) {
        $app->post('/register', '\Auth:register');
        $app->post('/login', '\Auth:login');
        $app->get('/validate', '\Auth:validate');
    });

    // users
    $app->group('/users', function(App $app) {
        $app->get('/[{id}]', '\Users:get');
    });
};
