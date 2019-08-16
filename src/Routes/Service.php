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

    function createService(Request $request, Response $response) {
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

    private function getUserServices(int $userId, bool $active = true) {
        // update caretaker
        $this->updateCareTaker();

        // fetch service
        $sql = $active ? "status=1" : "status!=1";
        $sql = "SELECT * FROM service WHERE (type=1 OR user=:id OR taker=:id) AND $sql ORDER BY id DESC";
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
            $sql = "SELECT id, name, type, image, registered, phone FROM users WHERE id=:id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $row['user']]);
            $user = $stmt->fetch();

            if ($user) {
                // profile image
                $user['image'] = $user['image'] ? $this->api->getUserImageUrl($user['image']) : null;

                // registered time
                setlocale (LC_ALL, "id");
                $user['registered'] = strftime('%e %B %Y', $user['registered']);

                // reputation
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
    
    function getActiveServices(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        return $this->getUserServices($userId);
    }

    function getCategory(Request $request, Response $response, array $args) {
        // args
        $serviceId = !empty($args['id']) ? (int) $args['id'] : false;

        $result = [];
        $sql = "SELECT id, name, icon FROM service_categories";

        if ($serviceId) {
            $sql .= " WHERE id=:id LIMIT 1";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $serviceId ?: $serviceId]);
        $categories = $stmt->fetchAll();

        foreach ($categories as $category) {
            $sql = "SELECT id, name, cost FROM service_actions WHERE category=:categoryId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':categoryId' => $category['id']]);
            
            $category['actions'] = $stmt->fetchAll();
            $category['icon'] = $this->api->getUrl($category['icon']);
            $result[] = $category;
        }

        return $this->api->success($result);
    }

    function setStatus(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];

        // params
        $id = $args['id'] ?? null;
        $status = $request->getParsedBodyParam('status');

        // id or status not valid
        if (!$id || !$status)
            return $this->api->fail('ID or status is not valid');
        
        // find service
        $query = $this->db->prepare("SELECT user, taker FROM service WHERE id=:id LIMIT 1");
        $query->execute([':id' => $id]);
        $service = $query->fetch();

        if (!$service)
            return $this->api->fail('Service not found.');

        // update service
        $query = $this->db->prepare("UPDATE service SET status=:status WHERE id=:id LIMIT 1");
        $result = $query->execute([':id' => $id, ':status' => $this->getStatusIdByName($status)]);

        if (!$result)
            return $this->api->fail('Cannot update service');
        
        // push notification
        $title = "Status layanan diubah";
        $message = '';

        switch ($status) {
            case 'cancel':
                $title = "Layanan dibatalkan";
                $message = "membatalkan";
                break;

            case 'success':
                $title = "Layanan selesai";
                $message = "menyelesaikan";
                break;

            default:
                break;
        }
        
        // create notification
        if (!empty($message)) {
            $user = $service['user']; $taker = $service['taker'];
            $message = ":OBJECT telah $message layanan #$id.";
            $this->api->broadcast([$user, $taker], $title, $message, $userId);
        }

        return $this->api->success();
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
        $sql = "SELECT u.id, u.type, u.active, COUNT(s.id) as services, (6371 * acos(
            cos(radians(:lat)) * cos(radians(u.lat)) * cos(radians(u.lng) - radians(:lng)) + 
            sin(radians(:lat)) * sin(radians(u.lat)))) AS distance
        FROM users AS u
        LEFT JOIN service AS s ON s.taker=u.id AND s.status=1 GROUP BY u.id
        HAVING u.id!=:user AND u.type=:type AND u.active=1 AND services < 1 AND distance < 200
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
            $nakes = $result['id'];
            $stmt = $this->db->prepare("UPDATE service SET taker=:taker WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $id, ':taker' => $nakes]);
            
            // create notification
            $nakesName = $this->api->getUserName($nakes);
            $userMsg =  "$nakesName telah mengambil layanan #$id yang anda buat. ";
            $userMsg .= "Silahkan hubungi nakes tersebut untuk info lanjut.";

            $nakesMsg = "Anda telah mendapatkan layanan baru dengan id #$id. ";
            $nakesMsg .= "Segera hubungi klien Anda untuk info lanjut.";

            // notify both user and nakes
            $this->api->notify($user, "Tenaga kesehatan ditemukan!", $userMsg);
            $this->api->notify($nakes, "Layanan baru telah didapakan", $nakesMsg);
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

    private function getCurrency($amount) {
        return "IDR " . number_format($amount, 0, ',', '.');
    }
}
?>
