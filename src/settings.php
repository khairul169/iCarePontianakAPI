<?php
// Environment variables
try {
    $dotenv->required([
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'JWT_KEY'
    ]);
} catch (Exception $e) {
    die($e->getMessage());
}

// set time locale
date_default_timezone_set("Asia/Jakarta");
setlocale (LC_ALL, "id");

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        'site' => [
            'url' => "http://" . $_SERVER['HTTP_HOST'] . "/icare/public/",
            'imgurl' => 'userimg/',
            'imgdir' => __DIR__ . '/../public/userimg/'
        ],

        // Database settings
        'db' => [
            'host' => $_ENV['DB_HOST'],
            'user' => $_ENV['DB_USER'],
            'pass' => $_ENV['DB_PASS'],
            'dbname' => $_ENV['DB_NAME']
        ],

        // OneSignal API settings
        'onesignal' => [
            'appid' => $_ENV['ONESIGNAL_APPID']
        ],

        // Hash
        'hash'  => [
            'jwt' => $_ENV['JWT_KEY']
        ]
    ],
];
