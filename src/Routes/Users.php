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
        $userId = $request->getAttribute('token')['id'] ?: null;

        // args
        $paramId = isset($args['id']) ? (int) $args['id'] : $userId;

        try {
            // prepare statement
            $query = $this->db->prepare("SELECT id, username, registered, role, name, phone FROM users WHERE id=:id");
            $query->execute([':id' => $paramId]);
            
            // fetch user
            $user = $query->fetch();

            // return user data
            if ($user)
                return $this->api->success($user);
            else
                return $this->api->fail("User not exists.");
        
        } catch (Exception $e) {
            $this->api->error($e->getMessage());
        }

        return $this->api->fail();
    }
}

?>
