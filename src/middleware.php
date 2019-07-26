<?php

use Slim\App;
use Slim\Http\Response;

return function (App $app) {
    $app->add(new Tuupola\Middleware\JwtAuthentication([
        "ignore"    => ["/auth/login", "/auth/register"],
        "secret"    => $app->getContainer()->get('settings')['hash']['jwt'],
        "attribute" => "token",
        "error"     => function (Response $response) {
            return $response->withJson(['status' => -1, 'message'  => 'Authentication failed.']);
        }
    ]));
};
