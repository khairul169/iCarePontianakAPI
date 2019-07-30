<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Ambulance {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getAll(Request $request, Response $response, array $args) {
        $stmt = $this->db->prepare("SELECT * FROM ambulance");
        $stmt->execute();
        return $this->api->success($stmt->fetchAll());
    }
}

?>
