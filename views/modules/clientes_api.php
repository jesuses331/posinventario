<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Cliente.php';

Auth::checkAccess();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_out(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function require_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_out(403, ['ok' => false, 'message' => 'CSRF inválido.']);
    }
}

try {
    $db = (new Database())->getConnection();
    $clienteModel = new Cliente($db);
} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Listar clientes (para POS)
if ($action === 'list_for_pos') {
    $clientes = $clienteModel->getAll();
    json_out(200, ['ok' => true, 'data' => $clientes]);
}

// Listar clientes (DataTables)
if ($action === 'list_datatables') {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = (string)($_GET['search']['value'] ?? '');
    $orderBy = $_GET['order'][0]['column'] ?? 0;
    $orderDir = $_GET['order'][0]['dir'] ?? 'asc';

    $columns = ['id', 'nombre', 'cedula', 'telefono', 'email', 'created_at'];
    $col = $columns[$orderBy] ?? 'id';

    $total = $clienteModel->countAll('');
    $filtered = $clienteModel->countAll($search);
    $data = $clienteModel->listDataTables($start, $length, $search, $col, $orderDir);

    json_out(200, [
        'ok' => true,
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $data
    ]);
}

// Obtener cliente por ID
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_out(400, ['ok' => false, 'message' => 'ID inválido.']);
    }
    $cliente = $clienteModel->getById($id);
    if (!$cliente) {
        json_out(404, ['ok' => false, 'message' => 'Cliente no encontrado.']);
    }
    json_out(200, ['ok' => true, 'data' => $cliente]);
}

// Crear cliente
if ($action === 'create') {
    require_csrf();
    
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $cedula = trim((string)($_POST['cedula'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $direccion = trim((string)($_POST['direccion'] ?? ''));

    if (empty($nombre)) {
        json_out(422, ['ok' => false, 'message' => 'El nombre es obligatorio.']);
    }

    $id = $clienteModel->create(
        $nombre,
        $cedula ?: null,
        $telefono ?: null,
        $email ?: null,
        $direccion ?: null
    );

    json_out(201, ['ok' => true, 'message' => 'Cliente creado.', 'id' => $id]);
}

// Actualizar cliente
if ($action === 'update') {
    require_csrf();
    
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $cedula = trim((string)($_POST['cedula'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $direccion = trim((string)($_POST['direccion'] ?? ''));

    if ($id <= 0 || empty($nombre)) {
        json_out(422, ['ok' => false, 'message' => 'Datos inválidos.']);
    }

    $clienteModel->update(
        $id,
        $nombre,
        $cedula ?: null,
        $telefono ?: null,
        $email ?: null,
        $direccion ?: null
    );

    json_out(200, ['ok' => true, 'message' => 'Cliente actualizado.']);
}

// Eliminar cliente
if ($action === 'delete') {
    require_csrf();
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_out(422, ['ok' => false, 'message' => 'ID inválido.']);
    }

    $clienteModel->delete($id);
    json_out(200, ['ok' => true, 'message' => 'Cliente eliminado.']);
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);
