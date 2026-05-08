<?php
class Auth {
    private $db;

    public function __construct($db) {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function redirectTo(string $path): void {
        if (defined('BASE_URL')) {
            $base = rtrim((string)BASE_URL, '/') . '/';
            header('Location: ' . $base . ltrim($path, '/'));
            exit();
        }

        header('Location: ' . $path);
        exit();
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT id, nombre, usuario, email, password_hash, rol, estado FROM usuarios WHERE usuario = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['estado'] == 0) {
                return "Usuario inactivo.";
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_usuario'] = $user['usuario'];
            $_SESSION['user_rol'] = $user['rol'];
            return true;
        }

        return "Credenciales incorrectas.";
    }

    public static function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    public static function checkAccess($requiredRole = null) {
        if (!self::isLoggedIn()) {
            self::redirectTo('views/login.php');
        }

        if ($requiredRole && $_SESSION['user_rol'] !== $requiredRole) {
            self::redirectTo('views/access_denied.php');
        }
    }

    public static function isAdmin(): bool {
        return (($_SESSION['user_rol'] ?? '') === 'admin');
    }

    public static function isCajero(): bool {
        return (($_SESSION['user_rol'] ?? '') === 'cajero');
    }

    public function logout() {
        session_unset();
        session_destroy();
        self::redirectTo('views/login.php');
    }
}
