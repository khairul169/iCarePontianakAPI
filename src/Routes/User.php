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
        $user = $this->api->getUserById($id, 'id, username, registered, type, name, phone, image,
            lat, lng, active, gender');
        if (!$user) {
            return $this->api->fail('User is not exists');
        }
        return $this->api->success($user);
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

    function addRating(Request $request, Response $response, array $args) {
        // params
        $userId = $request->getAttribute('token')['id'];
        $user = $args['id'] ?? 0;
        $rating = $request->getParsedBodyParam('rating', 0);
        $message = $request->getParsedBodyParam('message', '');
        $ref = $request->getParsedBodyParam('ref', 0);

        if (!$user || !$rating || empty($message) || !$ref) {
            return $this->api->fail('Input ada yg kosong');
        }

        $stmt = $this->db->prepare('SELECT id FROM ratings WHERE user=? AND ref=? LIMIT 1');
        $stmt->execute([$user, $ref]);

        if ($stmt->fetch()) {
            return $this->api->fail('Ref duplikat');
        }

        // insert data
        $stmt = $this->db->prepare('INSERT INTO ratings
            (user, giver, rating, message, time, ref)
            VALUES (?, ?, ?, ?, ?, ?)');
        $result = $stmt->execute([
            $user,
            $userId,
            $rating,
            $message,
            time(),
            $ref
        ]);

        return $this->api->result($result);
    }

    function getRating(Request $request, Response $response, array $args) {
        $user = $args['id'] ?? 0;

        // fetch ratings
        $stmt = $this->db->prepare('SELECT * FROM ratings WHERE user=? ORDER BY id DESC LIMIT 20');
        $stmt->execute([$user]);
        $ratings = [];

        foreach ($stmt->fetchAll() as $row) {
            $row['giver'] = $this->api->getUserById($row['giver'], 'id, name, image');
            $row['time'] = strftime('%e %B %Y %H.%M', $row['time']);
            $ratings[] = $row;
        }

        $result = [
            'summary' => $this->api->getUserRatingSummary($user),
            'ratings' => $ratings
        ];
        return $this->api->success($result);
    }

    private function selectSetData($userId, $type, $value) {
        switch ($type) {
            // set user name
            case 'name':
                return $this->setUserCol($userId, 'name', trim($value));
            
            // set phone number
            case 'gender':
                return $this->setUserCol($userId, 'gender', trim($value));
            
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
                if (empty($value['latitude']) || empty($value['longitude']))
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
