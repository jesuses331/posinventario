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

try {
    $db = (new Database())->getConnection();
} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- VENTAS ---

if ($action === 'list_ventas') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    
    $query = "SELECT v.id, v.total, (v.total + COALESCE(v.descuento,0)) as subtotal, v.descuento, v.fecha, v.estado_pago, v.pago_efectivo, v.pago_qr, u.nombre as usuario, c.nombre as cliente 
               FROM ventas v
               LEFT JOIN usuarios u ON v.usuario_id = u.id
               LEFT JOIN clientes c ON v.cliente_id = c.id
               WHERE 1=1";
    $params = [];

    if ($start) {
        $query .= " AND DATE(v.fecha) >= :start";
        $params[':start'] = $start;
    }
    if ($end) {
        $query .= " AND DATE(v.fecha) <= :end";
        $params[':end'] = $end;
    }

    // Restricción para Cajeros: solo sus ventas de hoy
    if (!Auth::isAdmin()) {
        $query .= " AND v.usuario_id = :u_id AND DATE(v.fecha) = CURDATE()";
        $params[':u_id'] = $_SESSION['user_id'];
    }

    $query .= " ORDER BY v.fecha DESC";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        json_out(200, ['ok' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'venta_detalle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out(400, ['ok' => false, 'message' => 'ID inválido']);

    try {
        // Cabecera
        $stmtV = $db->prepare("SELECT v.*, u.nombre as usuario, c.nombre as cliente, c.cedula as cliente_cedula 
                               FROM ventas v 
                               LEFT JOIN usuarios u ON v.usuario_id = u.id
                               LEFT JOIN clientes c ON v.cliente_id = c.id
                               WHERE v.id = ?");
        $stmtV->execute([$id]);
        $venta = $stmtV->fetch(PDO::FETCH_ASSOC);

        if (!$venta) json_out(404, ['ok' => false, 'message' => 'Venta no encontrada']);

        // Detalle
        $stmtD = $db->prepare("SELECT vd.*, p.nombre as producto, p.codigo
                               FROM ventas_detalle vd
                               JOIN productos p ON vd.producto_id = p.id
                               WHERE vd.venta_id = ?");
        $stmtD->execute([$id]);
        $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(200, ['ok' => true, 'venta' => $venta, 'detalle' => $detalle]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

// --- COMPRAS ---

if ($action === 'list_compras') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    
    $query = "SELECT c.id, c.total, c.fecha, u.nombre as usuario
              FROM compras c
              LEFT JOIN usuarios u ON c.usuario_id = u.id
              WHERE 1=1";
    $params = [];

    if ($start) {
        $query .= " AND DATE(c.fecha) >= :start";
        $params[':start'] = $start;
    }
    if ($end) {
        $query .= " AND DATE(c.fecha) <= :end";
        $params[':end'] = $end;
    }

    $query .= " ORDER BY c.fecha DESC";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        json_out(200, ['ok' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'compra_detalle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out(400, ['ok' => false, 'message' => 'ID inválido']);

    try {
        // Cabecera
        $stmtC = $db->prepare("SELECT c.*, u.nombre as usuario 
                               FROM compras c 
                               LEFT JOIN usuarios u ON c.usuario_id = u.id
                               WHERE c.id = ?");
        $stmtC->execute([$id]);
        $compra = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$compra) json_out(404, ['ok' => false, 'message' => 'Compra no encontrada']);

        // Detalle
        $stmtD = $db->prepare("SELECT cd.*, p.nombre as producto, p.codigo
                               FROM compras_detalle cd
                               JOIN productos p ON cd.producto_id = p.id
                               WHERE cd.compra_id = ?");
        $stmtD->execute([$id]);
        $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(200, ['ok' => true, 'compra' => $compra, 'detalle' => $detalle]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);
