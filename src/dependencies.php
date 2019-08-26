<?php

use Slim\App;

require_once __DIR__ . '/onesignal.php';
require_once __DIR__ . '/api.php';

return function (App $app) {
    $container = $app->getContainer();

    // monolog
    $container['logger'] = function ($c) {
        $settings = $c->get('settings')['logger'];
        $logger = new \Monolog\Logger($settings['name']);
        $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
        $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
        return $logger;
    };

    // database
    $container['db'] = function ($c) {
        $settings = $c->get('settings')['db'];
        $server = "mysql:host=".$settings['host'].";dbname=".$settings['dbname'];
        $db = new PDO($server, $settings['user'], $settings['pass']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    };

    // onesignal api
    $container['onesignal'] = function ($c) {
        $settings = $c->get('settings')['onesignal'];
        $api = new OneSignalAPI($settings['appid'], $settings['restApiKey']);
        $api->setChannelId($settings['channelId']);
        return $api;
    };

    // api
    $container['api'] = function ($c) {
        $api = new API($c);
        return $api;
    };
};
