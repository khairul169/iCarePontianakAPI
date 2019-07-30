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

        // update caretaker
        $this->updateCareTaker();

        // fetch service
        $sql = "SELECT * FROM service WHERE (type=1 OR user=:id OR taker=:id) AND status=1 ORDER BY id DESC";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        $result = [];
        foreach ($query->fetchAll() as $row) {
            // decode data
            $row['data'] = json_decode($row['data'], true);

            // is the user making service
            $self = ($row['user'] == $userId);
            $row['self'] = $self;

            // select user
            if ($self && $row['type'] != 1) {
                $row['user'] = $row['taker'];
                unset($row['taker']);
            }

            // fetch user
            $sql = "SELECT id, name, type, registered, phone FROM users WHERE id=:id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $row['user']]);
            $user = $stmt->fetch();

            if ($user) {
                // set registered time
                setlocale (LC_ALL, "id");
                $user['registered'] = strftime('%e %B %Y', $user['registered']);

                if ($user['type'] > 1) {
                    $user['reputation'] = [
                        'rating' => "4.92",
                        'amount' => "249"
                    ];
                }
            }

            $row['user'] = $user;
            $result[] = $row;
        }

        return $this->api->success($result);
    }

    function create(Request $request, Response $response) {
        // get userid from token
        $userId = $request->getAttribute('token')['id'];

        // params
        $type = $request->getParsedBodyParam('type', 0);
        $data = $request->getParsedBodyParam('data');
        $location = $request->getParsedBodyParam('location');

        // data is empty
        if (!$data || !$location)
            return $this->api->fail("Data is empty");

        // encode data
        $data = json_encode($data);

        // insert data
        $sql = "INSERT INTO service (user, type, data, lat, lng, timestamp)
        VALUES (:id, :type, :data, :lat, :lng, :time)";

        $query = $this->db->prepare($sql);
        $res = $query->execute([
            ':id'   => $userId,
            ':type' => $type,
            ':data' => $data,
            ':lat'  => $location['latitude'],
            ':lng'  => $location['longitude'],
            ':time' => time()
        ]);

        // success
        if ($res) {
            $resId = $this->db->lastInsertId();
            return $this->api->success($resId);
        }

        // fail
        return $this->api->fail("Cannot insert data!");
    }

    function setStatus(Request $request, Response $response, array $args) {
        // params
        $id = $args['id'] ?? null;
        $status = $request->getParsedBodyParam('status');
        $status = $this->getStatusIdByName($status);

        // id or status not valid
        if (!$id || !isset($status))
            return $this->api->fail('ID or status is not valid');

        // update service
        $query = $this->db->prepare("UPDATE service SET status=:status WHERE id=:id LIMIT 1");
        $result = $query->execute([':id' => $id, ':status' => $status]);
        return $result ? $this->api->success() : $this->api->fail('Cannot update service');
    }

    private function updateCareTaker() {
        $sql = "SELECT id, user, type, lat, lng FROM service WHERE taker=0 AND status=1 LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            if ($row['type'] <= 1)
                continue;

            // update service taker
            $this->findCareTaker($row['id'], $row['user'], $row['type'], $row['lat'], $row['lng']);
        }
    }

    private function findCareTaker($id, $user, $type, $latitude, $longitude) {
        // find service taker
        $sql = "SELECT u.id, u.type, COUNT(s.id) as services, (6371 * acos(
            cos(radians(:lat)) * cos(radians(u.lat)) * cos(radians(u.lng) - radians(:lng)) + 
            sin(radians(:lat)) * sin(radians(u.lat)))) AS distance
        FROM users AS u
        LEFT JOIN service AS s ON s.taker=u.id AND s.status=1 GROUP BY u.id
        HAVING u.id!=:user AND u.type=:type AND services < 1 AND distance < 200
        ORDER BY distance LIMIT 1";
        
        // exec query
        $query = $this->db->prepare($sql);
        $query->execute([
            ':user' => $user,
            ':type' => $type,
            ':lat'  => $latitude,
            ':lng'  => $longitude
        ]);
        $result = $query->fetch();

        // update caretaker
        if ($result) {
            $stmt = $this->db->prepare("UPDATE service SET taker=:user WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $id, ':user' => $result['id']]);
        }

        return $result ? $result['id'] : 0;
    }

    private function getStatusIdByName($status) {
        $statusId = [
            'failed',
            'active',
            'success',
            'cancel'
        ];
        foreach ($statusId as $key => $value) {
            if ($value == $status)
                return $key;
        }
        return null;
    }
}
?>
