<?php

use Slim\App;
use Slim\Http\Response;

return function (App $app) {
    $app->add(new Tuupola\Middleware\JwtAuthentication([
        "ignore"    => ["/auth"],
        "secret"    => $app->getContainer()->get('settings')['hash']['jwt'],
        "attribute" => "token",
		"secure"	=> false,
        "error"     => function (Response $response) {
            return $response->withJson(['success' => false, 'message'  => 'Authentication failed.']);
        }
    ]));
};
