<?php

use Slim\Container;
use Slim\Http\Response;

class API {
    /** @var Response */
    protected $response;
    /** @var \Monolog\Logger */
    protected $logger;
    protected $settings;

    function __construct(Container $container) {
        $this->response = $container->get('response');
        $this->logger = $container->get('logger');
        $this->settings = $container->get('settings');
    }

    function success($result = null) {
        return $this->response->withJson(array(
            'success'   => true,
            'result'    => $result
        ));
    }

    function fail(string $message = '') {
        return $this->response->withJson(array(
            'success'   => false,
            'message'   => empty($message) ? "Undefined" : $message
        ));
    }

    function error(string $message = '') {
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
}
?>
