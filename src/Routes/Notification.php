<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Notification {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getAll(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'] ?: null;
        $stmt = $this->db->prepare("SELECT id, content, timestamp FROM notification WHERE user=:id ORDER BY id DESC LIMIT 20");
        $stmt->execute([':id' => $userId]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            setlocale (LC_ALL, "id");
            $row['time'] = strftime('%e %B %Y %H.%M', $row['timestamp']);
            $rows[] = $row;
        }

        return $this->api->success($rows);
    }
}

?>
