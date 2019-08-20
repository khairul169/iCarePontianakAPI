<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Clients {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getClients(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];

        $stmt = $this->db->prepare("SELECT * FROM clients WHERE user=:id ORDER BY nama ASC");
        $stmt->execute([':id' => $userId]);
        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $row['gender'] = $this->api->getGenderById($row['gender']);
            $result[] = $row;
        }

        return $this->api->success($result);
    }

    function getClient(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $id = $args['id'] ?? 0;

        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id=:id AND user=:userid");
        $stmt->execute(['id' => $id, ':userid' => $userId]);
        $row = $stmt->fetch();

        if ($row) {
            $row['gender'] = $this->api->getGenderById($row['gender']);
        }

        return $this->api->result($row);
    }

    function addClient(Request $request, Response $response) {
        $userId = $request->getAttribute('token')['id'];
        
        // params
        $data = $request->getParsedBodyParam('data');
        $params = ['nama', 'umur', 'gender'];

        if ($this->api->paramIsEmpty($data, $params)) {
            return $this->api->fail('Cek data');
        }

        $sql = "INSERT INTO clients
            (user, nama, umur, gender, diagnosa, riwayat, alergi)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $values = [
            $userId,
            $data['nama'],
            $data['umur'],
            $data['gender'],
            $data['diagnosa'] ?? '',
            $data['riwayat'] ?? '',
            $data['alergi'] ?? '',
        ];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        return $this->api->result($result);
    }

    function updateClient(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        
        // params
        $id = !empty($args['id']) ? (int) $args['id'] : 0;
        $data = $request->getParsedBodyParam('data');
        $params = ['nama', 'umur', 'gender'];

        if ($this->api->paramIsEmpty($data, $params)) {
            return $this->api->fail('Cek data');
        }

        $sql = "UPDATE clients
            SET nama=?, umur=?, gender=?, diagnosa=?, riwayat=?, alergi=?
            WHERE id=? AND user=? LIMIT 1";
        
        $values = [
            $data['nama'],
            $data['umur'],
            $data['gender'],
            $data['diagnosa'] ?? '',
            $data['riwayat'] ?? '',
            $data['alergi'] ?? '',
            $id,
            $userId
        ];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        return $this->api->result($result);
    }

    function removeClient(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        
        // params
        $id = !empty($args['id']) ? (int) $args['id'] : 0;

        // delete client
        $sql = "DELETE FROM clients WHERE id=? AND user=? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$id, $userId]);

        return $this->api->result($result);
    }
}

?>
