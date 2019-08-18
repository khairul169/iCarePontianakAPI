<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Clients {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getClients(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];

        $stmt = $this->db->prepare("SELECT * FROM clients WHERE user=:id");
        $stmt->execute([':id' => $userId]);
        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $row['gender'] = $this->api->getGenderById($row['gender']);
            $result[] = $row;
        }

        return $this->api->success($result);
    }

    function getClient(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $id = $args['id'] ?? 0;

        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id=:id AND user=:userid");
        $stmt->execute(['id' => $id, ':userid' => $userId]);
        $row = $stmt->fetch();

        if ($row) {
            $row['gender'] = $this->api->getGenderById($row['gender']);
        }

        return $this->api->result($row);
    }
}

?>
