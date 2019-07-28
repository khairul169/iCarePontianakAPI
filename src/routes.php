<?php

use \Slim\App;

// routes
require_once __DIR__ . '/Routes/Auth.php';
require_once __DIR__ . '/Routes/User.php';
require_once __DIR__ . '/Routes/Service.php';

return function (App $app) {
    // authentication
    $app->group('/auth', function(App $app) {
        $app->post('/register', '\Auth:register');
        $app->post('/login', '\Auth:login');
        $app->get('/validate', '\Auth:validate');
    });

    // users
    $app->group('/user', function(App $app) {
        $app->get('/', '\User:get');
        $app->get('/{id}', '\User:getUser');
        $app->patch('/{type}', '\User:setData');
    });

    // service
    $app->group('/service', function(App $app) {
        $app->get('/', '\Service:getAll');
        $app->get('/{id}', '\Service:get');
        $app->post('/', '\Service:create');
    });
};
