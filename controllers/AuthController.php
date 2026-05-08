<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/Auth.php';

class AuthController {
    private $db;
    private $auth;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth($this->db);
    }

    public function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
 
            if (empty($username) || empty($password)) {
                return "Todos los campos son obligatorios.";
            }
 
            $result = $this->auth->login($username, $password);
            if ($result === true) {
                header("Location: dashboard.php");
                exit();
            } else {
                return $result;
            }
        }
        return null;
    }

    public function handleLogout() {
        $this->auth->logout();
    }
}
