<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

Auth::checkAccess('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_out_rep(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db = (new Database())->getConnection();
} catch (Throwable $e) {
    json_out_rep(500, ['ok' => false, 'message' => 'Error de conexión.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'ventas';

if ($action === 'ventas') {
    $desde = (string)($_GET['desde'] ?? '');
    $hasta = (string)($_GET['hasta'] ?? '');
    $estado = (string)($_GET['estado'] ?? '');

    $where = "WHERE DATE(v.fecha) BETWEEN :d AND :h";
    $params = [':d' => $desde, ':h' => $hasta];

    $usuario_id = (int)($_GET['usuario_id'] ?? 0);
    if ($usuario_id > 0) {
        $where .= " AND v.usuario_id = :uid";
        $params[':uid'] = $usuario_id;
    }

    if ($estado !== '') {
        $where .= " AND v.estado_pago = :st";
        $params[':st'] = $estado;
    }

    // 1. Resumen por días (con ganancia y desglose de pago)
    $stmt = $db->prepare("
        SELECT 
            DATE(v.fecha) AS dia, 
            COUNT(DISTINCT v.id) AS num_ventas, 
            SUM(v.total) AS total,
            SUM(v.pago_efectivo) AS efectivo,
            SUM(v.pago_qr) AS qr,
            (SELECT SUM(vd.subtotal - (vd.cantidad * vd.precio_compra)) 
             FROM ventas_detalle vd 
             INNER JOIN ventas v2 ON vd.venta_id = v2.id 
             WHERE DATE(v2.fecha) = DATE(v.fecha) 
             " . ($usuario_id > 0 ? " AND v2.usuario_id = :uid_sub " : "") . "
             " . ($estado !== '' ? " AND v2.estado_pago = :st_sub " : "") . "
            ) AS ganancia
        FROM ventas v
        $where
        GROUP BY DATE(v.fecha)
        ORDER BY DATE(v.fecha) ASC
    ");
    
    $paramsResumen = $params;
    if ($usuario_id > 0) $paramsResumen[':uid_sub'] = $usuario_id;
    if ($estado !== '') $paramsResumen[':st_sub'] = $estado;
    
    $stmt->execute($paramsResumen);
    $dias = $stmt->fetchAll() ?: [];

    // 2. Lista de ventas individual para el DataTable
    $stmtVentas = $db->prepare("
        SELECT v.id, v.total, (v.total + COALESCE(v.descuento,0)) as subtotal, v.descuento, v.fecha, v.estado_pago, v.pago_efectivo, v.pago_qr, c.nombre AS cliente_nombre, u.nombre AS usuario_nombre,
               (SELECT SUM(subtotal - (cantidad * precio_compra)) FROM ventas_detalle WHERE venta_id = v.id) AS ganancia
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        $where
        ORDER BY v.id DESC
    ");
    $stmtVentas->execute($params);
    $lista = $stmtVentas->fetchAll() ?: [];

    json_out_rep(200, [
        'ok' => true, 
        'dias' => $dias, 
        'ventas' => $lista
    ]);
}

if ($action === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out_rep(400, ['ok' => false, 'message' => 'ID inválido.']);

    $stmt = $db->prepare("
        SELECT vd.cantidad, vd.precio_unitario, vd.precio_compra, vd.subtotal, p.nombre, p.codigo
        FROM ventas_detalle vd
        INNER JOIN productos p ON vd.producto_id = p.id
        WHERE vd.venta_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $items = $stmt->fetchAll() ?: [];

    json_out_rep(200, ['ok' => true, 'items' => $items]);
}

if ($action === 'cambiar_estado') {
    $id = (int)($_POST['id'] ?? 0);
    $nuevoEstado = (string)($_POST['estado'] ?? '');

    if ($id <= 0 || !in_array($nuevoEstado, ['pendiente', 'cobrado'])) {
        json_out_rep(400, ['ok' => false, 'message' => 'Datos inválidos.']);
    }

    $stmt = $db->prepare("UPDATE ventas SET estado_pago = :st WHERE id = :id");
    $ok = $stmt->execute([':st' => $nuevoEstado, ':id' => $id]);

    json_out_rep(200, ['ok' => $ok, 'message' => $ok ? 'Estado actualizado.' : 'Error al actualizar.']);
}

if ($action === 'por_usuario') {
    $desde = (string)($_GET['desde'] ?? '');
    $hasta = (string)($_GET['hasta'] ?? '');
    $usuario_id = (int)($_GET['usuario_id'] ?? 0);
    $where = "WHERE DATE(v.fecha) BETWEEN :d AND :h";
    $params = [':d' => $desde, ':h' => $hasta, ':d1' => $desde, ':h1' => $hasta];

    if ($usuario_id > 0) {
        $where .= " AND v.usuario_id = :uid";
        $params[':uid'] = $usuario_id;
        $params[':uid1'] = $usuario_id;
    }

    $stmt = $db->prepare("
        SELECT 
            u.nombre AS usuario, 
            COUNT(DISTINCT v.id) AS num_ventas, 
            SUM(v.total) AS total,
            SUM(v.pago_efectivo) AS efectivo,
            SUM(v.pago_qr) AS qr,
            (SELECT SUM(subtotal - (cantidad * precio_compra)) FROM ventas_detalle WHERE venta_id IN (SELECT id FROM ventas WHERE usuario_id = u.id AND DATE(fecha) BETWEEN :d1 AND :h1 " . ($usuario_id > 0 ? " AND usuario_id = :uid1" : "") . ")) AS ganancia
        FROM ventas v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        $where
        GROUP BY u.id, u.nombre
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    $res = $stmt->fetchAll() ?: [];

    json_out_rep(200, ['ok' => true, 'data' => $res]);
}

if ($action === 'por_producto') {
    $desde = (string)($_GET['desde'] ?? '');
    $hasta = (string)($_GET['hasta'] ?? '');
    $usuario_id = (int)($_GET['usuario_id'] ?? 0);
    $where = "WHERE DATE(v.fecha) BETWEEN :d AND :h";
    $params = [':d' => $desde, ':h' => $hasta];

    if ($usuario_id > 0) {
        $where .= " AND v.usuario_id = :uid";
        $params[':uid'] = $usuario_id;
    }

    $stmt = $db->prepare("
        SELECT 
            p.nombre AS producto, 
            p.codigo,
            SUM(vd.cantidad) AS unidades, 
            SUM(vd.subtotal) AS total,
            SUM(vd.subtotal - (vd.cantidad * vd.precio_compra)) AS ganancia
        FROM ventas_detalle vd
        INNER JOIN ventas v ON vd.venta_id = v.id
        INNER JOIN productos p ON vd.producto_id = p.id
        $where
        GROUP BY p.id, p.nombre, p.codigo
        ORDER BY unidades DESC
    ");
    $stmt->execute($params);
    $res = $stmt->fetchAll() ?: [];

    json_out_rep(200, ['ok' => true, 'data' => $res]);
}

if ($action === 'top_dashboard') {
    $stmtTop = $db->prepare("
        SELECT p.id, p.codigo, p.nombre,
               SUM(vd.cantidad) AS unidades,
               SUM(vd.subtotal) AS importe
        FROM ventas_detalle vd
        INNER JOIN ventas v ON v.id = vd.venta_id
        INNER JOIN productos p ON p.id = vd.producto_id
        GROUP BY p.id, p.codigo, p.nombre
        ORDER BY unidades DESC
        LIMIT 10
    ");
    $stmtTop->execute();
    $top = $stmtTop->fetchAll() ?: [];
    json_out_rep(200, ['ok' => true, 'top' => $top]);
}

json_out_rep(400, ['ok' => false, 'message' => 'Acción inválida.']);

