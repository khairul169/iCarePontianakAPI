<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Service {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getAll(Request $request, Response $response) {
        $userId = $request->getAttribute('token')['id'];

        $sql = "SELECT * FROM service WHERE user=:id AND status!='0'";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        $result = [];
        foreach ($query->fetchAll() as $row) {
            $row['data'] = json_decode($row['data']);
            $result[] = $row;
        }

        return $this->api->success($result);
    }

    function get(Request $request, Response $response, array $args) {

    }

    function create(Request $request, Response $response) {
        // get userid from token
        $userId = $request->getAttribute('token')['id'];

        // params
        $type = $request->getParsedBodyParam('type', 0);
        $data = $request->getParsedBodyParam('data');

        // data is empty
        if (!$data)
            return $this->api->fail("Data is empty");

        // encode data
        $data = json_encode($data);

        // insert data
        $sql = "INSERT INTO service (user, type, data, status) VALUES (:id, :type, :data, '1')";
        $query = $this->db->prepare($sql);
        $res = $query->execute([
            ':id' => $userId,
            ':type' => $type,
            ':data' => $data
        ]);

        // success
        if ($res) {
            $resId = $this->db->lastInsertId();
            return $this->api->success($resId);
        }

        // fail
        return $this->api->fail("Cannot insert data!");
    }
}