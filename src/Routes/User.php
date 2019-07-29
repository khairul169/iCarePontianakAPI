<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class User {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function get(Request $request, Response $response, array $args) {
        // get userid from token
        $userId = $request->getAttribute('token')['id'] ?: null;

        // sql statement
        $sql = "SELECT id, username, registered, type, name, phone FROM users WHERE id=:id";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        // result
        $result = $query->fetch();
        return $result ? $this->api->success($result) : $this->api->fail("User not exists");
    }

    function getUser(Request $request, Response $response, array $args) {
        // args
        $userId = isset($args['id']) ? (int) $args['id'] : $userId;

        // sql statement
        $sql = "SELECT id, username, registered, type, name, phone FROM users WHERE id=:id";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        // result
        $result = $query->fetch();
        return $result ? $this->api->success($result) : $this->api->fail("User not exists");
    }

    function setData(Request $request, Response $response, array $args) {
        // get userid from token
        $userId = $request->getAttribute('token')['id'] ?: null;

        // params
        $type = $args['type'] ?? null;
        $value = $request->getParsedBodyParam('value');

        switch ($type) {
            // set user name
            case 'name':
                return $this->setUserCol($userId, 'name', trim($value));
            
            // set phone number
            case 'phone':
                return $this->setUserCol($userId, 'phone', trim($value));
            
            // set location
            case 'location':
                // location is not valid
                if (!isset($value['lat']) || !isset($value['lng']))
                    break;
                return $this->setUserLocation($userId, $value['lat'], $value['lng']);
        }

        return $this->api->fail('Cannot update user!');
    }

    private function setUserCol($id, $col, $value) {
        $sql = "UPDATE users SET :col=:val WHERE id=:id LIMIT 1";
        $query = $this->db->prepare($sql);
        $res = $query->execute([':id' => $id, ':col' => $col, ':val' => $value]);
        return $res ? $this->api->success() : $this->api->fail();
    }

    private function setUserLocation($id, $latitude, $longitude) {
        $sql = "UPDATE users SET lat=:lat, lng=:lng WHERE id=:id LIMIT 1";
        $query = $this->db->prepare($sql);
        $res = $query->execute([':id' => $id, ':lat' => $latitude, ':lng' => $longitude]);
        return $res ? $this->api->success() : $this->api->fail();
    }
}

?>
