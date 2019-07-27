<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Users {
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
        $sql = "SELECT id, username, registered, role, name, phone FROM users WHERE id=:id";
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
        $sql = "SELECT id, username, registered, role, name, phone FROM users WHERE id=:id";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        // result
        $result = $query->fetch();
        return $result ? $this->api->success($result) : $this->api->fail("User not exists");
    }

    function setUserData(Request $request, Response $response, array $args) {
        // get userid from token
        $userId = $request->getAttribute('token')['id'] ?: null;

        // params
        $key = $request->getParsedBodyParam('key');
        $value = $request->getParsedBodyParam('value');

        switch ($key) {
            // set user name
            case 'name':
                $sql = 'name'; break;
            
            // set phone number
            case 'phone':
                $sql = 'phone'; break;
            
            default:
                return $this->api->fail('Key not specified');
        }

        // sql statement
        $sql = "UPDATE users SET $sql=:val WHERE id=:id";
        $query = $this->db->prepare($sql);
        
        // execute sql
        $result = $query->execute([
            ':val'  => $value,
            ':id'   => $userId
        ]);

        return $result ? $this->api->success() : $this->api->fail("Failed updating user data");
    }
}

?>
