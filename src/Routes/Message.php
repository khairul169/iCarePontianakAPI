<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class Message {
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function getMessageList(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $stmt = $this->db->prepare('SELECT * FROM (
            SELECT * FROM messages WHERE sender=? ORDER BY id DESC
            LIMIT 18446744073709551615
        ) AS sub GROUP BY sub.receiver');
        $stmt->execute([$userId]);
        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $row['receiver'] = $this->api->getUserById($row['receiver'], 'id, name, image');
            $row['time'] = strftime('%e %B %Y %H:%M', $row['time']);
            $result[] = $row;
        }
        return $this->api->result($result);
    }

    function getUserMessages(Request $request, Response $response, array $args) {
        $userId = $request->getAttribute('token')['id'];
        $id = $args['id'] ?? 0;

        if (!$this->api->getUserById($id, 'id')) {
            return $this->api->fail();
        }
        
        $stmt = $this->db->prepare("SELECT sender, message, time FROM messages
            WHERE (sender=:user AND receiver=:id) OR (sender=:id AND receiver=:user)
            ORDER BY id DESC LIMIT 20");
        $stmt->execute(['user' => $userId, 'id' => $id]);
        
        $messages = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['time'] = strftime('%e %B %Y %H:%M', $row['time']);
            $row['self'] = $row['sender'] == $userId;
            $messages[] = $row;
        }

        return $this->api->result($messages);
    }

    function create(Request $request, Response $response) {
        $userId = $request->getAttribute('token')['id'];
        $id = $request->getParsedBodyParam('id');
        $message = $request->getParsedBodyParam('message');

        if (empty($id) || empty($message)) {
            return $this->api->fail('Input kosong');
        }

        if ($userId == $id) {
            return $this->api->fail('User tidak boleh sama');
        }

        // get sender data
        $sender = $this->api->getUserById($userId, 'name');

        if (!$sender) {
            return $this->api->fail('User tidak valid');
        }

        if (!$this->api->getUserById($id, 'id')) {
            return $this->api->fail('ID tidak ditemukan');
        }

        $sql = "INSERT INTO messages
            (sender, receiver, message, time)
            VALUES (?, ?, ?, ?)";
        
        $values = [
            $userId,
            $id,
            $message,
            time()
        ];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);

        // send notification to receiver
        if ($result) {
            $senderName = $sender['name'];
            $this->api->notify($id, $senderName, $message);
        }

        return $this->api->result($result);
    }
}

?>
