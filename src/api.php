<?php

use Slim\Container;
use Slim\Http\Response;

class API {
    /** @var Response */
    protected $response;

    /** @var \Monolog\Logger */
    protected $logger;

    function __construct(Container $container) {
        $this->response = $container->get('response');
        $this->logger = $container->get('logger');
    }

    function success($result = null) {
        return $this->response->withJson(array(
            'error'     => false,
            'result'    => $result
        ));
    }

    function fail(string $message = '') {
        return $this->response->withJson(array(
            'error'     => true,
            'message'   => empty($message) ? "Undefined" : $message
        ));
    }

    function error(string $message = '') {
        $this->logger->error($message);
    }
}
?>
