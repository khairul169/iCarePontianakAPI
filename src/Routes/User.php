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
        return $this->getUserInfo($userId);
    }

    function getUser(Request $request, Response $response, array $args) {
        // args
        $userId = isset($args['id']) ? (int) $args['id'] : $userId;
        return $this->getUserInfo($userId);
    }

    private function getUserInfo($id) {
        // sql statement
        $sql = "SELECT id, username, registered, type, name, phone, image, lat, lng FROM users WHERE id=:id";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $id]);

        // result
        $user = $query->fetch();

        if ($user) {
            $user['image'] = $user['image'] ? $this->api->getUserImageUrl($user['image']) : null;
        }

        return $user ? $this->api->success($user) : $this->api->fail("User not exists");
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
                if (!isset($value['latitude']) || !isset($value['longitude']))
                    break;
                return $this->setUserLocation($userId, $value['latitude'], $value['longitude']);
            
            // profile image
            case 'profileimg':
                return $this->setProfileImage($userId, $value);
        }

        return $this->api->fail('Cannot update user!');
    }

    private function setUserCol($id, $col, $value) {
        $sql = "UPDATE users SET $col=:val WHERE id=:id LIMIT 1";
        $query = $this->db->prepare($sql);
        $res = $query->execute([':id' => $id, ':val' => $value]);
        return $res ? $this->api->success() : $this->api->fail();
    }

    private function setUserLocation($id, $latitude, $longitude) {
        $sql = "UPDATE users SET lat=:lat, lng=:lng WHERE id=:id LIMIT 1";
        $query = $this->db->prepare($sql);
        $res = $query->execute([':id' => $id, ':lat' => $latitude, ':lng' => $longitude]);
        return $res ? $this->api->success() : $this->api->fail();
    }

    private function setProfileImage($userId, $data) {
        // store image
        $userimg = $this->api->storeUserImage($data);
        if ($userimg) {
            return $this->setUserCol($userId, 'image', $userimg);
        }
        return $this->api->fail();
    }
}

?>
