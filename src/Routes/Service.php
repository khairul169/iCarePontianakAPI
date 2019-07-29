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
        $sql = "SELECT * FROM service WHERE (user=:id OR taker=:id) AND status!='0'";
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        $result = [];
        foreach ($query->fetchAll() as $row) {
            // decode data
            $row['data'] = json_decode($row['data'], true);

            // update service taker
            if (!($row['taker'])) {
                $lokasi = $row['data']['lokasi'] ?? null;
                $row['taker'] = $this->findServiceTaker($row['id'], $userId, $lokasi);
            }

            // select user
            if ($row['user'] == $userId) {
                $row['user'] = $row['taker'];
                unset($row['taker']);
            }

            // fetch user
            $sql = "SELECT id, name, role, registered, phone FROM users WHERE id=:id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $row['user']]);
            $user = $stmt->fetch();

            if ($user) {
                // set registered time
                setlocale (LC_ALL, "id");
                $user['registered'] = strftime('%e %B %Y', $user['registered']);

                if ($user['role'] > 1) {
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

    private function assignAvailableService($userId) {
        // get user role
        $query = $this->db->prepare("SELECT id, role FROM users WHERE id=:uid AND role>1 LIMIT 1");
        $query->execute([':uid' => $userId]);
        $result = $query->fetch();
        $role = $result['role'] ?? null;

        // user is not a caretaker
        if (!$role) return;

        // find unassigned service
        $query = $this->db->prepare("SELECT id, status, taker, data FROM service WHERE status=1 AND taker=0 LIMIT 1");
        $query->execute();
        $result = $query->fetch();

        // update care taker
        if ($result) {
            $query = $this->db->prepare("UPDATE service SET taker=:uid WHERE id=:id LIMIT 1");
            $query->execute([':id' => $result['id'], ':uid' => $userId]);
        }
    }

    private function findServiceTaker($id, $userId, $lokasi) {
        // find service taker
        $sql = "SELECT users.id, users.role, COUNT(service.id) as services FROM users
        LEFT JOIN service ON service.status=1 AND service.taker=users.id GROUP BY users.id
        HAVING services=0 AND users.id!=:uid AND users.role=2 LIMIT 1";
        
        // exec query
        $query = $this->db->prepare($sql);
        $query->execute([':uid' => $userId]);
        $result = $query->fetch();

        // service taker found
        if ($result) {
            $stmt = $this->db->prepare("UPDATE service SET taker=:uid WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $id, ':uid' => $result['id']]);
        }

        return $result ? $result['id'] : 0;
    }
}
?>
