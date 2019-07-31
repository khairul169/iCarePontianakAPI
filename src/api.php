<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class API {
    /** @var Response */
    protected $response;
    /** @var \Monolog\Logger */
    protected $logger;
    protected $settings;
    /** @var PDO */
    protected $db;

    function __construct(Container $container) {
        $this->response = $container->get('response');
        $this->logger = $container->get('logger');
        $this->settings = $container->get('settings');
        $this->db = $container->get('db');
    }

    function success($result = null) {
        return $this->response->withJson(array(
            'success'   => true,
            'result'    => $result
        ));
    }

    function fail($message = null) {
        return $this->response->withJson(array(
            'success'   => false,
            'message'   => empty($message) ? "Undefined" : $message
        ));
    }

    function error($message = null) {
        $this->logger->error($message);
    }

    function getUrl($path) {
        return $this->settings['site']['url'] . $path;
    }

    function storeUserImage($data) {
        // decode image
        $image = base64_decode($data);
        
        // data invalid
        if (!$image) return false;
        
        // create image
        $image = imagecreatefromstring($image);
        
        // image invalid
        if (!$image) return false;

        // save image
        $fname = md5(rand().time()) . '.jpg';
        $path = $this->settings['site']['imgdir'] . $fname;
		$imageRes = imagejpeg($image, $path, 80);
        imagedestroy($image);
        
        return $imageRes ? $fname : null;
    }

    function getUserImageUrl($url) {
        return $this->getUrl($this->settings['site']['imgurl'] . $url);
    }

    function getPasswordHash($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    function getUserName(int $id) {
        $stmt = $this->db->prepare("SELECT name FROM users WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ? $result['name'] : null;
    }

    function notify(int $id, string $message) {
        $stmt = $this->db->prepare("INSERT INTO notification (user, content, timestamp) VALUES (:id, :msg, :time)");
        return $stmt->execute([':id' => $id, ':msg' => $message, ':time' => time()]);
    }

    function broadcast(array $users, string $message, $object = null) {
        $objectName = $object ? $this->getUserName($object) : null;

        foreach ($users as $user) {
            $args = [
                ':OBJECT' => $user == $object ? "Anda" : $objectName
            ];
            $msg = str_replace(array_keys($args), array_values($args), $message);
            $this->notify($user, $msg);
        }
    }
}
?>
