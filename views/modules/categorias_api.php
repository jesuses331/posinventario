<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Categoria.php';

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
    $categoriaModel = new Categoria($db);
} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list_all') {
    $categorias = $categoriaModel->getAll();
    json_out(200, ['ok' => true, 'data' => $categorias]);
}

if ($action === 'list_datatables') {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = (string)($_GET['search']['value'] ?? '');
    $orderBy = $_GET['order'][0]['column'] ?? 0;
    $orderDir = $_GET['order'][0]['dir'] ?? 'asc';

    $columns = ['id', 'nombre', 'descripcion', 'created_at'];
    $col = $columns[$orderBy] ?? 'id';

    $total = $categoriaModel->countAll('');
    $filtered = $categoriaModel->countAll($search);
    $data = $categoriaModel->listDataTables($start, $length, $search, $col, $orderDir);

    json_out(200, [
        'ok' => true,
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $data
    ]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_out(400, ['ok' => false, 'message' => 'ID inválido.']);
    }
    $categoria = $categoriaModel->getById($id);
    if (!$categoria) {
        json_out(404, ['ok' => false, 'message' => 'Categoría no encontrada.']);
    }
    json_out(200, ['ok' => true, 'data' => $categoria]);
}

if ($action === 'create') {
    require_csrf();

    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));

    if (empty($nombre)) {
        json_out(422, ['ok' => false, 'message' => 'El nombre es obligatorio.']);
    }

    $id = $categoriaModel->create($nombre, $descripcion ?: null);

    json_out(201, ['ok' => true, 'message' => 'Categoría creada.', 'id' => $id]);
}

if ($action === 'update') {
    require_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));

    if ($id <= 0 || empty($nombre)) {
        json_out(422, ['ok' => false, 'message' => 'Datos inválidos.']);
    }

    $categoriaModel->update($id, $nombre, $descripcion ?: null);

    json_out(200, ['ok' => true, 'message' => 'Categoría actualizada.']);
}

if ($action === 'delete') {
    require_csrf();

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_out(422, ['ok' => false, 'message' => 'ID inválido.']);
    }

    $categoriaModel->delete($id);
    json_out(200, ['ok' => true, 'message' => 'Categoría eliminada.']);
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);
