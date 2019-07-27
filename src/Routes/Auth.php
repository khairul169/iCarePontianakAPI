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
        $params = $request->getParsedBody();
        $username = isset($params['username']) ? trim($params['username']) : null;
        $password = isset($params['password']) ? trim($params['password']) : null;

        if (empty($username) || empty($password))
            return $this->api->fail('Username or Password is empty.');
        
        if (strlen($username) < 6 || strlen($password) < 6)
            return $this->api->fail('Username or Password is weak.');
        
        // generate password hash
        $password = $this->getPasswordHash($password);

        // check username
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username=:user LIMIT 1");
            $stmt->execute([':user' => $username]);

            if ($stmt->fetch())
                return $this->api->fail('Username already taken.');
        } catch (Exception $e) {
            $this->api->error($e->getMessage());
        }

        // create user
        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, password, registered) VALUES (:user, :pass, :reg)");
            $result = $stmt->execute([
                ':user' => $username,
                ':pass' => $password,
                ':reg'	=> time()
            ]);

            if ($result) {
                $userId = $this->db->lastInsertId();
                $token = $this->generateToken($userId);

                // return OK and access token
                return $this->api->success(['token' => $token]);
            }
        } catch (Exception $e) {
            $this->api->error($e->getMessage());
        }

        // can't handle request
        return $this->api->fail();
    }

    function login(Request $request, Response $response) {
        // params
        $params = $request->getParsedBody();
        $username = isset($params['username']) ? trim($params['username']) : null;
        $password = isset($params['password']) ? trim($params['password']) : null;

        if (empty($username) || empty($password))
            return $this->api->fail('Username or Password is empty.');

        // try login
        try {
            $stmt = $this->db->prepare("SELECT id, password FROM users WHERE username=:user LIMIT 1");
            $stmt->execute([':user' => $username]);
            $user = $stmt->fetch();

            if (!$user)
                return $this->api->fail('User not found.');
        } catch (Exception $e) {
            $this->api->error($e->getMessage());
        }

        // verify password hash
        if (!$this->verifyPassword($password, $user['password']))
            return $this->api->fail('Wrong password.');

        // return token
        $token = $this->generateToken($user['id']);
        return $this->api->success(['token' => $token]);
    }

    function validate(Request $request, Response $response) {
        $token = $request->getAttribute('token');
        $userId = isset($token['id']) ? (int) $token['id'] : null;

        // token expired
        if (time() > (int) $token['exp'])
            return $this->api->fail('Token expired.');
        
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id=:id LIMIT 1");
            $stmt->execute([':id' => $userId]);

            $result = $stmt->fetch();

            // user exist
            if ($result)
                return $this->api->success(['id' => $result['id']]);
        } catch (Exception $e) {
            $this->api->error($e->getMessage());
        }

        // can't validate session
        return $this->api->fail();
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
