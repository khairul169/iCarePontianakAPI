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
        $fullname = $request->getParsedBodyParam('fullname');

        if (empty($username) || empty($password) || empty($fullname))
            return $this->api->fail('Kolom input belum terisi semua');
        
        if (!$this->validate_username($username))
            return $this->api->fail('Nama Pengguna minimal 6 angka/huruf dan tidak menggunakan simbol/spasi');
        
        if (strlen($password) < 6)
            return $this->api->fail('Kata Sandi minimal 6 angka/huruf');
        
        // generate password hash
        $password = $this->api->getPasswordHash($password);

        // check username
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username=:user LIMIT 1");
        $stmt->execute([':user' => $username]);

        // username taken
        if ($stmt->fetch())
            return $this->api->fail('Nama Pengguna sudah terpakai');

        // create user
        $stmt = $this->db->prepare("INSERT INTO users (username, password, registered, name) VALUES (:user, :pass, :reg, :name)");
        $result = $stmt->execute([
            ':user' => $username,
            ':pass' => $password,
            ':reg'	=> time(),
            ':name' => $fullname
        ]);

        if (!$result)
            return $this->api->fail('Gagal mendaftar');

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
            return $this->api->fail('Nama Pengguna atau Kata Sandi kosong');

        // try login
        $stmt = $this->db->prepare("SELECT id, password FROM users WHERE username=:user LIMIT 1");
        $stmt->execute([':user' => $username]);
        $user = $stmt->fetch();

        if (!$user)
            return $this->api->fail('Nama Pengguna tidak ditemukan');

        // verify password hash
        if (!$this->api->verifyPassword($password, $user['password']))
            return $this->api->fail('Kata Sandi salah');

        // return token
        $token = $this->generateToken($user['id']);
        return $this->api->success(['token' => $token]);
    }

    function validate(Request $request, Response $response) {
        // params
        $token = $request->getParsedBodyParam('token');

        // decode token
        $token = $this->decodeToken($token);

        if (!$token)
            return $this->api->fail('Token invalid');

        // token expired
        if (time() > $token->exp)
            return $this->api->fail('Token expired');
        
        // get user
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $token->id]);

        // fetch user
        $result = $stmt->fetch();

        // token is invalid
        if (!$result)
            return $this->api->fail('Token invalid');
        
        // token is valid
        $newToken = $this->generateToken((int) $result['id']);
        return $this->api->success(['token' => $newToken]);
    }

    private function validate_username($username) {
        return preg_match('/^[a-zA-Z0-9]{6,}$/', $username);
    }

    private function generateToken(int $userId) {
        $key = $this->container->get('settings')['hash']['jwt'];
        $payload = array(
            'id'	=> $userId,
            'exp'	=> time() + (60*60*24*7)
        );
        return JWT::encode($payload, $key, 'HS256');
    }

    private function decodeToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $key = $this->container->get('settings')['hash']['jwt'];
        return JWT::decode($token, $key, array('HS256'));
    }
}

?>
