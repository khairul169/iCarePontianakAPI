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

    function getCategories(Request $request, Response $response, array $args) {
        $result = [];
        $stmt = $this->db->prepare("SELECT id, name, icon FROM service_categories");
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            $row['icon'] = $this->api->getUrl($row['icon']);
            $result[] = $row;
        }

        return $this->api->success($result);
    }

    function getCategory(Request $request, Response $response, array $args) {
        $categoryId = !empty($args['id']) ? intval($args['id']) : 0;
        $sql = "SELECT id, name FROM service_categories WHERE id=:id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $categoryId]);
        $result = $stmt->fetch();

        // fetch actions
        if ($result) {
            $sql = "SELECT id, name, cost FROM service_actions WHERE category=:id ORDER BY name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $categoryId]);
            $tindakan = [];

            foreach ($stmt->fetchAll() as $row) {
                $row['cost'] = $this->getCurrency($row['cost']);
                $tindakan[] = $row;
            }

            $result['actions'] = $tindakan;
        }

        return $this->api->success($result);
    }

    function searchNakes(Request $request, Response $response) {
        $userId = $request->getAttribute('token')['id'];
        $kategori = $request->getParsedBodyParam('kategori');
        $lokasi = $request->getParsedBodyParam('lokasi');
        $exclude = $request->getParsedBodyParam('exclude');

        if (!$kategori || !$lokasi || empty($lokasi['latitude']) || empty($lokasi['longitude']))
            return $this->api->fail();
        
        $exclusion = is_array($exclude) ? implode("','", array_map('intval', $exclude)) : '';

        $sql = "SELECT u.id, u.type, u.name, u.image, u.pelayanan, u.active, u.gender,
            u.lat, u.lng,
            -- nakes service counts
            COUNT(s.id) as services,
            -- get distance between user and nakes
            (6371 * acos(cos(radians(:lat)) * cos(radians(u.lat)) * cos(radians(u.lng)
            - radians(:lng)) + sin(radians(:lat)) * sin(radians(u.lat)))) AS distance
            FROM users AS u
            LEFT JOIN service AS s ON s.nakes=u.id AND s.status=1 GROUP BY u.id
            -- limit user and service type
            HAVING u.id!=:user AND u.pelayanan=:type AND u.active=1 AND services=0
            -- max distance
            AND distance<200
            -- filter user
            AND u.id NOT IN ('$exclusion')
            ORDER BY distance LIMIT 1";
        
        // exec query
        $query = $this->db->prepare($sql);
        $query->execute([
            ':user' => $userId,
            ':type' => $kategori,
            ':lat'  => $lokasi['latitude'],
            ':lng'  => $lokasi['longitude']
        ]);
        $result = $query->fetch();

        if ($result) {
            $result['type'] = $this->api->getUserRole($result['type']);
            $result['image'] = $this->api->getUserImageUrl($result['image']);
            $result['gender'] = $this->api->getGenderById($result['gender']);
            $result['distance'] = number_format($result['distance'], 1, '.', '') . ' km';
            $result['lat'] = (float) $result['lat'];
            $result['lng'] = (float) $result['lng'];
        }
        return $this->api->success($result);
    }

    function createService(Request $request, Response $response) {
        // get userid from token
        $userId = $request->getAttribute('token')['id'];

        // params
        $data = $request->getParsedBodyParam('data');
        $dataParams = ['type', 'tindakan', 'klien', 'keluhan', 'alamat', 'lokasi', 'waktu', 'nakes'];

        // data is empty
        if ($this->api->paramIsEmpty($data, $dataParams)) {
            return $this->api->fail("Mohon lengkapi data yang ada");
        }

        // nakes is not available
        if (!$this->isNakesAvailable($data['nakes'])) {
            return $this->api->fail('Nakes tidak tersedia');
        }
        
        $tindakan = is_array($data['tindakan'])
            ? implode(',', array_map('intval', $data['tindakan']))
            : '';

        // insert data
        $values = [
            'user' => $userId,
            'type' => (int) $data['type'],
            'tindakan' => $tindakan,
            'klien' => $data['klien'],
            'keluhan' => $data['keluhan'],
            'alamat' => $data['alamat'],
            'lat' => $data['lokasi']['latitude'],
            'lng' => $data['lokasi']['longitude'],
            'waktu' => $data['waktu'],
            'nakes' => $data['nakes']
        ];
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(',', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO service ($columns) VALUES ($placeholders)";
        $query = $this->db->prepare($sql);
        $res = $query->execute(array_values($values));

        if ($res) {
            $serviceId = (int) $this->db->lastInsertId();
            return $this->api->success($serviceId);
        }
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

    function setCanceled(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $id = $args['id'] ?? null;

        $stmt = $this->db->prepare("UPDATE service SET status=2 WHERE id=:id AND user=:user");
        $result = $stmt->execute([':id' => $id, ':user' => $userId]);
        return $this->api->result($result);
    }

    function setFinished(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $id = $args['id'] ?? null;
        
        $stmt = $this->db->prepare("UPDATE service SET status=3 WHERE id=:id AND taker=:user");
        $result = $stmt->execute([':id' => $id, ':user' => $userId]);
        return $this->api->result($result);
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

    private function isNakesAvailable($id) {
        // query
        $sql = "SELECT u.id, u.active, COUNT(s.id) as services
            FROM users AS u LEFT JOIN service AS s ON s.nakes=u.id AND s.status=1
            HAVING u.id=? AND u.active=1 AND services=0 LIMIT 1";
        
        // fetch query
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
?>
