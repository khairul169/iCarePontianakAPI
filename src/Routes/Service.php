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

        // find service
        $this->assignAvailableService($userId);

        // fetch service
        $sql = "SELECT * FROM service WHERE (type=1 OR user=:id OR taker=:id) AND status=1 ORDER BY id DESC";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        $result = [];
        foreach ($query->fetchAll() as $row) {
            // decode data
            $row['data'] = json_decode($row['data'], true);

            // update service taker
            if (!($row['taker'])) {
                $lokasi = $row['data']['lokasi'] ?? null;
                $row['taker'] = $this->findServiceTaker($row['id'], $row['type'], $userId, $lokasi);
            }

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

    private function assignAvailableService($userId) {
        // get user type
        $query = $this->db->prepare("SELECT users.id, users.type, COUNT(service.id) as services FROM users
        LEFT JOIN service ON service.status=1 AND service.taker=users.id
        WHERE users.id=:uid AND users.type>1 HAVING services=0 LIMIT 1");
        
        $query->execute([':uid' => $userId]);
        $result = $query->fetch();
        $type = $result['type'] ?? null;

        // user is not a caretaker
        if (!$result || !$type) return;

        // find unassigned service
        $query = $this->db->prepare("SELECT id, status, taker, type FROM service
        WHERE status=1 AND taker=0 AND type=:type LIMIT 1");
        $query->execute([':type' => $type]);
        $result = $query->fetch();

        // update care taker
        if ($result) {
            $query = $this->db->prepare("UPDATE service SET taker=:uid WHERE id=:id LIMIT 1");
            $query->execute([':id' => $result['id'], ':uid' => $userId]);
        }
    }

    private function findServiceTaker($id, $serviceType, $userId, $lokasi) {
        // find service taker
        $sql = "SELECT users.id, users.type, COUNT(service.id) as services FROM users
        LEFT JOIN service ON service.status=1 AND service.taker=users.id GROUP BY users.id
        HAVING services=0 AND users.id!=:uid AND users.type=:type LIMIT 1";
        
        // exec query
        $query = $this->db->prepare($sql);
        $query->execute([':uid' => $userId, ':type' => $serviceType]);
        $result = $query->fetch();

        // service taker found
        if ($result) {
            $stmt = $this->db->prepare("UPDATE service SET taker=:uid WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $id, ':uid' => $result['id']]);
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
