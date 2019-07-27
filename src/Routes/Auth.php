<?php

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use \Firebase\JWT\JWT;

class Auth {
    /** @var Container */
    protected $container;
    /** @var PDO */
    protected $db;
    /** @var API */
    protected $api;

    function __construct(Container $c) {
        $this->container = $c;
        $this->db = $c->get('db');
        $this->api = $c->get('api');
    }

    function register(Request $request, Response $response) {
        // params
        $username = $request->getParsedBodyParam('username');
        $password = $request->getParsedBodyParam('password');

        if (empty($username) || empty($password))
            return $this->api->fail('Username or Password is empty');
        
        if (strlen($username) < 6 || strlen($password) < 6)
            return $this->api->fail('Username or Password is weak');
        
        // generate password hash
        $password = $this->getPasswordHash($password);

        // check username
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username=:user LIMIT 1");
        $stmt->execute([':user' => $username]);

        // username taken
        if ($stmt->fetch())
            return $this->api->fail('Username already taken');

        // create user
        $stmt = $this->db->prepare("INSERT INTO users (username, password, registered) VALUES (:user, :pass, :reg)");
        $result = $stmt->execute([
            ':user' => $username,
            ':pass' => $password,
            ':reg'	=> time()
        ]);

        if (!$result)
            return $this->api->fail('Cannot register user');

        // generate user token
        $userId = $this->db->lastInsertId();
        $token = $this->generateToken($userId);

        // return user token
        return $this->api->success(['token' => $token]);
    }

    function login(Request $request, Response $response) {
        // params
        $username = $request->getParsedBodyParam('username');
        $password = $request->getParsedBodyParam('password');

        if (empty($username) || empty($password))
            return $this->api->fail('Username or Password is empty');

        // try login
        $stmt = $this->db->prepare("SELECT id, password FROM users WHERE username=:user LIMIT 1");
        $stmt->execute([':user' => $username]);
        $user = $stmt->fetch();

        if (!$user)
            return $this->api->fail('User not found');

        // verify password hash
        if (!$this->verifyPassword($password, $user['password']))
            return $this->api->fail('Wrong password');

        // return token
        $token = $this->generateToken($user['id']);
        return $this->api->success(['token' => $token]);
    }

    function validate(Request $request, Response $response) {
        $token = $request->getAttribute('token');
        $userId = isset($token['id']) ? (int) $token['id'] : null;

        // token expired
        if (time() > (int) $token['exp'])
            return $this->api->fail('Token expired');
        
        // get user
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $userId]);

        // fetch user
        $result = $stmt->fetch();

        // token is invalid
        if (!$result)
            return $this->api->fail('Token invalid');
        
        // token is valid
        return $this->api->success(['id' => $result['id']]);
    }

    private function getPasswordHash($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    private function generateToken($userId) {
        $key = $this->container->get('settings')['hash']['jwt'];
        $payload = array(
            'id'	=> $userId,
            'exp'	=> time() + (60*60*24*7)
        );
        return JWT::encode($payload, $key);
    }
}

?>
