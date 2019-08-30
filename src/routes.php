<?php

use \Slim\App;

// routes
require_once __DIR__ . '/Routes/Auth.php';
require_once __DIR__ . '/Routes/User.php';
require_once __DIR__ . '/Routes/Clients.php';
require_once __DIR__ . '/Routes/Service.php';
require_once __DIR__ . '/Routes/Notification.php';
require_once __DIR__ . '/Routes/Emergency.php';
require_once __DIR__ . '/Routes/Message.php';

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
        $app->post('/{id}/rating', '\User:addRating');
        $app->get('/{id}/rating', '\User:getRating');
    });

    // clients
    $app->group('/client', function(App $app) {
        $app->get('/', '\Clients:getClients');
        $app->get('/{id}', '\Clients:getClient');
        $app->post('/', '\Clients:addClient');
        $app->patch('/{id}', '\Clients:updateClient');
        $app->delete('/{id}', '\Clients:removeClient');
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

    // emergency
    $app->group('/emergency', function(App $app) {
        $app->get('/', '\Emergency:getLists');
        $app->post('/', '\Emergency:create');
        $app->get('/view/{id}', '\Emergency:getById');
        $app->get('/ambulance', '\Emergency:getAmbulance');
    });

    // message
    $app->group('/message', function(App $app) {
        $app->get('/lists', '\Message:getMessageList');
        $app->get('/get/{id}', '\Message:getUserMessages');
        $app->post('/', '\Message:create');
    });
};
