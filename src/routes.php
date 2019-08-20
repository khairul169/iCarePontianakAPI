<?php

use \Slim\App;

// routes
require_once __DIR__ . '/Routes/Auth.php';
require_once __DIR__ . '/Routes/User.php';
require_once __DIR__ . '/Routes/Clients.php';
require_once __DIR__ . '/Routes/Service.php';
require_once __DIR__ . '/Routes/Notification.php';
require_once __DIR__ . '/Routes/Ambulance.php';

return function (App $app) {
    // authentication
    $app->group('/auth', function(App $app) {
        $app->post('/register', '\Auth:register');
        $app->post('/login', '\Auth:login');
        $app->post('/validate', '\Auth:validate');
    });

    // users
    $app->group('/user', function(App $app) {
        $app->get('/', '\User:get');
        $app->get('/{id}', '\User:getUser');
        $app->patch('/', '\User:setDataMulti');
        $app->patch('/{type}', '\User:setData');
    });

    // clients
    $app->group('/client', function(App $app) {
        $app->get('/', '\Clients:getClients');
        $app->get('/{id}', '\Clients:getClient');
    });

    // service
    $app->group('/service', function(App $app) {
        $app->get('/category', '\Service:getCategories');
        $app->get('/category/{id}', '\Service:getCategory');
        $app->post('/nakes', '\Service:searchNakes');
        $app->post('/create', '\Service:createService');
        $app->get('/lists', '\Service:getServices');
        $app->get('/view/{id}', '\Service:getServiceById');
        $app->patch('/cancel/{id}', '\Service:setCanceled');
        $app->patch('/finish/{id}', '\Service:setFinished');
    });

    // notification
    $app->group('/notification', function(App $app) {
        $app->get('/', '\Notification:getAll');
    });

    // ambulance
    $app->group('/ambulance', function(App $app) {
        $app->get('/', '\Ambulance:getAll');
    });
};
