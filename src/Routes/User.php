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
        $userId = $request->getAttribute('token')['id'];
        return $this->getUserInfo($userId);
    }

    function getUser(Request $request, Response $response, array $args) {
        // args
        $userId = isset($args['id']) ? (int) $args['id'] : $userId;
        return $this->getUserInfo($userId);
    }

    private function getUserInfo($id) {
        // sql statement
        $sql = "SELECT id, username, registered, type, name, phone, image, lat, lng, active
            FROM users WHERE id=:id";
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
        $userId = $request->getAttribute('token')['id'];

        // params
        $type = $args['type'] ?? null;
        $value = $request->getParsedBodyParam('value');
        return $this->selectSetData($userId, $type, $value);
    }

    function setDataMulti(Request $request, Response $response) {
        // params
        $userId = $request->getAttribute('token')['id'];
        $data = $request->getParsedBodyParam('data');

        foreach ($data as $type => $value) {
            if (!$value) continue;
            $this->selectSetData($userId, $type, $value);
        }
        return $this->api->success();
    }

    private function selectSetData($userId, $type, $value) {
        switch ($type) {
            // set user name
            case 'name':
                return $this->setUserCol($userId, 'name', trim($value));
            
            // set password
            case 'password':
                $password = $this->api->getPasswordHash($value);
                return $this->setUserCol($userId, 'password', $password);
            
            // set phone number
            case 'phone':
                return $this->setUserCol($userId, 'phone', trim($value));
            
            // set location
            case 'location':
                // location is not valid
                if (!isset($value['latitude']) || !isset($value['longitude']))
                    break;
                return $this->setUserLocation(
                    $userId,
                    $value['latitude'],
                    $value['longitude']
                );
            
            // profile image
            case 'profileimg':
                return $this->setProfileImage($userId, $value);
            
            // set user name
            case 'active':
                return $this->setUserCol($userId, 'active', (int) $value);
            
            case 'deviceid':
                return $this->setUserCol($userId, 'device_id', $value, true);
        }
        return $this->api->fail('Cannot update user!');
    }

    private function setUserCol($id, $col, $value, $removeDuplicate = false) {
        if ($removeDuplicate) {
            $query = $this->db->prepare("UPDATE users SET $col='' WHERE $col=:val");
            $query->execute([':val' => $value]);
        }

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
