<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

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
} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list_cotizaciones') {
    $query = "SELECT c.id, c.codigo, c.total, c.estado, c.fecha_validez, c.created_at,
                     u.nombre as usuario, cl.nombre as cliente
              FROM cotizaciones c
              LEFT JOIN usuarios u ON c.usuario_id = u.id
              LEFT JOIN clientes cl ON c.cliente_id = cl.id
              WHERE 1=1";
    $params = [];

    if (!Auth::isAdmin()) {
        $query .= " AND c.usuario_id = :u_id";
        $params[':u_id'] = $_SESSION['user_id'];
    }

    $query .= " ORDER BY c.created_at DESC";

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        json_out(200, ['ok' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'get_cotizacion') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) json_out(400, ['ok' => false, 'message' => 'ID inválido']);

    try {
        $stmtC = $db->prepare("
            SELECT c.*, u.nombre as usuario, cl.nombre as cliente
            FROM cotizaciones c
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            WHERE c.id = ?
        ");
        $stmtC->execute([$id]);
        $cotizacion = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$cotizacion) json_out(404, ['ok' => false, 'message' => 'Cotización no encontrada']);

        $stmtD = $db->prepare("
            SELECT * FROM cotizaciones_detalle WHERE cotizacion_id = ?
        ");
        $stmtD->execute([$id]);
        $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(200, ['ok' => true, 'cotizacion' => $cotizacion, 'detalle' => $detalle]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'delete_cotizacion') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_out(400, ['ok' => false, 'message' => 'ID inválido']);

    try {
        $stmt = $db->prepare("DELETE FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        json_out(200, ['ok' => true, 'message' => 'Cotización eliminada.']);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'update_cotizacion_estado') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $estado = trim((string) ($_POST['estado'] ?? ''));

    if ($id <= 0 || !in_array($estado, ['activa', 'aceptada', 'vencida', 'cancelada'])) {
        json_out(422, ['ok' => false, 'message' => 'Datos inválidos.']);
    }

    try {
        $stmt = $db->prepare("UPDATE cotizaciones SET estado = :estado WHERE id = :id");
        $stmt->execute([':estado' => $estado, ':id' => $id]);
        json_out(200, ['ok' => true, 'message' => 'Estado actualizado.']);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'convertir_cotizacion_a_venta') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_out(400, ['ok' => false, 'message' => 'ID inválido']);

    $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
    if ($usuarioId <= 0) json_out(401, ['ok' => false, 'message' => 'Sesión inválida.']);

    try {
        $stmtC = $db->prepare("SELECT * FROM cotizaciones WHERE id = ? AND estado = 'activa'");
        $stmtC->execute([$id]);
        $cotizacion = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$cotizacion) json_out(404, ['ok' => false, 'message' => 'Cotización no encontrada o no está activa.']);

        $stmtD = $db->prepare("SELECT * FROM cotizaciones_detalle WHERE cotizacion_id = ?");
        $stmtD->execute([$id]);
        $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        if (empty($detalle)) json_out(422, ['ok' => false, 'message' => 'La cotización no tiene productos.']);

        $db->beginTransaction();

        $idCaja = $_SESSION['id_caja'] ?? null;

        $stmtV = $db->prepare("
            INSERT INTO ventas (total, usuario_id, id_caja, cliente_id, estado_pago, descuento, fecha)
            VALUES (:total, :usuario_id, :id_caja, :cliente_id, 'pendiente', :descuento, :fecha)
        ");
        $stmtV->execute([
            ':total' => $cotizacion['total'],
            ':usuario_id' => $usuarioId,
            ':id_caja' => $idCaja,
            ':cliente_id' => $cotizacion['cliente_id'],
            ':descuento' => $cotizacion['descuento_global'] ?? 0,
            ':fecha' => date('Y-m-d H:i:s')
        ]);
        $ventaId = (int) $db->lastInsertId();

        $stmtP = $db->prepare("SELECT id, precio_compra1, stock_actual FROM productos WHERE id = ?");
        $stmtDIns = $db->prepare("
            INSERT INTO ventas_detalle (venta_id, producto_id, cantidad, precio_unitario, precio_compra, subtotal)
            VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :precio_compra, :subtotal)
        ");
        $stmtU = $db->prepare("UPDATE productos SET stock_actual = stock_actual - :cantidad WHERE id = :id");

        foreach ($detalle as $d) {
            $precioCompra = 0;
            if ($d['producto_id']) {
                $stmtP->execute([$d['producto_id']]);
                $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
                if ($prod) {
                    $precioCompra = (float) ($prod['precio_compra1'] ?? 0);
                    $stmtU->execute([':cantidad' => $d['cantidad'], ':id' => $d['producto_id']]);
                }
            }
            $stmtDIns->execute([
                ':venta_id' => $ventaId,
                ':producto_id' => $d['producto_id'],
                ':cantidad' => $d['cantidad'],
                ':precio_unitario' => $d['precio_unitario'],
                ':precio_compra' => $precioCompra,
                ':subtotal' => $d['subtotal']
            ]);
        }

        $stmtUpd = $db->prepare("UPDATE cotizaciones SET estado = 'aceptada' WHERE id = ?");
        $stmtUpd->execute([$id]);

        $db->commit();
        json_out(201, ['ok' => true, 'message' => 'Venta creada a partir de la cotización.', 'venta_id' => $ventaId]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        json_out(500, ['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);
