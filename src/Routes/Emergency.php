<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Emergency {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getLists(Request $request, Response $response, array $args) {
        $stmt = $this->db->prepare("SELECT * FROM emergency WHERE ? < (time + (60 * 60 * 24)) ORDER BY time DESC");
        $stmt->execute([time()]);
        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $row['user'] = $this->api->getUserById($row['user'], 'id, name, image, phone');
            $row['time'] = strftime('%e %B %Y %H:%M', $row['time']);
            $row['lokasi'] = [
                'latitude' => (float) $row['lat'],
                'longitude' => (float) $row['lng'],
            ];
            unset($row['lat']);
            unset($row['lng']);
            $result[] = $row;
        }

        return $this->api->success($result);
    }

    function getById(Request $request, Response $response, array $args) {
        $id = $args['id'] ?? 0;
        $stmt = $this->db->prepare("SELECT * FROM emergency WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $row['user'] = $this->api->getUserById($row['user'], 'id, name, image, phone');
            $row['time'] = strftime('%e %B %Y %H:%M', $row['time']);
            $row['lokasi'] = [
                'latitude' => (float) $row['lat'],
                'longitude' => (float) $row['lng'],
            ];
            unset($row['lat']);
            unset($row['lng']);
        }

        return $this->api->result($row);
    }

    function create(Request $request, Response $response) {
        $userId = $request->getAttribute('token')['id'];
        
        // params
        $data = $request->getParsedBodyParam('data');
        $params = ['lokasi', 'jenis', 'keterangan'];

        if ($this->api->paramIsEmpty($data, $params)) {
            return $this->api->fail('Data tidak lengkap');
        }

        $sql = "INSERT INTO emergency
            (user, time, lat, lng, jenis, keterangan)
            VALUES (?, ?, ?, ?, ?, ?)";
        
        $values = [
            $userId,
            time(),
            $data['lokasi']['latitude'] ?? 0.0,
            $data['lokasi']['longitude'] ?? 0.0,
            $data['jenis'],
            $data['keterangan']
        ];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        return $this->api->result($result);
    }

    function getAmbulance(Request $request, Response $response, array $args) {
        $stmt = $this->db->prepare("SELECT * FROM ambulance");
        $stmt->execute();
        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $row['coordinate'] = [
                'latitude' => (float) $row['lat'],
                'longitude' => (float) $row['lng'],
            ];
            unset($row['lat']);
            unset($row['lng']);
            $result[] = $row;
        }

        return $this->api->success($result);
    }
}

?>
