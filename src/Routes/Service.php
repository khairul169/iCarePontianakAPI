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

    private function findTindakanByName($name) {
        $stmt = $this->db->prepare("SELECT id FROM service_actions WHERE name=:name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : false;
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
        
        $tindakan = isset($data['tindakan']) ? $this->findTindakanByName($data['tindakan']) : '';

        // insert data
        $sql = "INSERT INTO service
            (user, type, lat, lng, timestamp, keluhan, tindakan, diagnosa, alamat, waktu) VALUES
            (:id, :type, :lat, :lng, :time, :keluhan, :tindakan, :diagnosa, :alamat, :waktu)";

        $query = $this->db->prepare($sql);
        $res = $query->execute([
            ':id'   => $userId,
            ':type' => $type,
            ':lat'  => $location['latitude'],
            ':lng'  => $location['longitude'],
            ':time' => time(),

            // fields
            ':keluhan'  => $data['keluhan'] ?? '',
            ':tindakan' => $tindakan,
            ':diagnosa' => $data['diagnosa'] ?? '',
            ':alamat'   => $data['alamat'] ?? '',
            ':waktu'    => $data['waktu'] ?? 0,
        ]);

        if ($res) {
            // get id from last insert id
            $serviceId = $this->db->lastInsertId();

            // return service id
            return $this->api->success($serviceId);
        }

        // fail
        return $this->api->fail("Cannot insert data!");
    }

    function getServices(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];

        // update nakes
        $this->updateNakes();

        $result = [];

        // fetch service
        $sql = "SELECT id, user, nakes, tindakan, status, waktu, alamat
            FROM service WHERE (user=:id OR nakes=:id)
            ORDER BY id DESC LIMIT 20";
        
        $query = $this->db->prepare($sql);
        $query->execute([':id' => $userId]);

        foreach ($query->fetchAll() as $row) {
            // user
            $client = ($row['user'] == $userId);
            $user = $client && $row['nakes'] ? $row['nakes'] : $row['user'];
            $user = $this->api->getUserById($user, 'id, name, type, image, phone');

            // service
            $tindakan = $this->getTindakan($row['tindakan']);
            $status = $this->getServiceStatus($row['status']);
            $kontak = $row['status'] == 1 && $row['nakes'];

            $item = [
                'id' => $row['id'],
                'user' => $user,
                'status' => $status,
                'tindakan' => $tindakan,
                'kontak' => $kontak
            ];

            if ($row['status'] <= 1) {
                $item['waktu'] = strftime('%e %B %Y %H:%M', $row['waktu']);
                $item['alamat'] = $row['alamat'];
            }

            $result[] = $item;
        }

        return $this->api->success($result);
    }

    function getServiceById(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $id = $args['id'] ?? 0;

        // fetch service
        $stmt = $this->db->prepare("SELECT * FROM service WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row)
            return $this->api->fail();
        
        // user
        $client = ($row['user'] == $userId);
        $user = $client && $row['nakes'] ? $row['nakes'] : $row['user'];
        $user = $this->api->getUserById($user, 'id, name, type, image, phone');

        // service
        $location = [
            'latitude' => floatval($row['lat']),
            'longitude' => floatval($row['lng'])
        ];
        $tindakan = $this->getTindakan($row['tindakan']);
        $status = $this->getServiceStatus($row['status']);
        $waktu = strftime('%e %B %Y %H:%M', $row['waktu']);

        $item = [
            'id' => $row['id'],
            'client' => $client,
            'user' => $user,
            'status' => $status,
            'location' => $location,
            'keluhan' => $row['keluhan'],
            'tindakan' => $tindakan,
            'diagnosa' => $row['diagnosa'],
            'alamat' => $row['alamat'],
            'waktu' => $waktu
        ];

        return $this->api->success($item);
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
        $query = $this->db->prepare("SELECT user, nakes FROM service WHERE id=:id LIMIT 1");
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
            $user = $service['user']; $nakes = $service['nakes'];
            $message = ":OBJECT telah $message layanan #$id.";
            $this->api->broadcast([$user, $nakes], $title, $message, $userId);
        }

        return $this->api->success();
    }

    private function updateNakes() {
        $sql = "SELECT id, user, type, lat, lng FROM service WHERE nakes=0 AND status=0 LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            // update nakes
            $this->findNakes($row['id'], $row['user'], $row['type'], $row['lat'], $row['lng']);
        }
    }

    private function findNakes($id, $user, $type, $latitude, $longitude) {
        // find nakes
        $sql = "SELECT u.id, u.kategori_layanan, u.active, COUNT(s.id) as services,
            (6371 * acos(cos(radians(:lat)) * cos(radians(u.lat)) * cos(radians(u.lng) - radians(:lng)) + sin(radians(:lat)) * sin(radians(u.lat)))) AS distance
            FROM users AS u
            LEFT JOIN service AS s ON s.nakes=u.id AND s.status=1 GROUP BY u.id
            HAVING u.id!=:user AND u.kategori_layanan=:type AND u.active=1 AND services < 1 AND distance < 200
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
            $stmt = $this->db->prepare("UPDATE service SET status=1, nakes=:nakes WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $id, ':nakes' => $nakes]);
            
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
        return "IDR " . number_format($amount, 0, ',', '.') . ",-";
    }

    private function getTindakan($tindakan) {
        $tindakan = explode(',', $tindakan);
        $items = [];
        $label = [];
        $totalCost = 0;

        if (is_array($tindakan)) {
            $inQuery = join(',', array_fill(0, count($tindakan), '?'));
            $sql = "SELECT id, name, cost FROM service_actions WHERE id IN ($inQuery)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($tindakan);
            
            foreach ($stmt->fetchAll() as $row) {
                $items[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'cost' => $this->getCurrency($row['cost'])
                ];
                $label[] = $row['name'];
                $totalCost += intval($row['cost']);
            }
        }
        
        $result = [
            'items' => $items,
            'label' => implode(", ", $label),
            'total' => $this->getCurrency($totalCost)
        ];
        return $result;
    }

    private function getServiceStatus($status) {
        $status = intval($status);
        $value = [
            'Mencari Nakes',
            'Sedang Berjalan',
            'Batal',
            'Selesai',
        ];
        return isset($value[$status]) ? $value[$status] : false;
    }
}
?>
