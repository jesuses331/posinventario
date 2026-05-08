<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

Auth::checkAccess(); // admin o cajero

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_out(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function require_csrf(): void
{
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

if ($action === 'search_products') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        json_out(200, ['ok' => true, 'data' => []]);
    }

    $stmt = $db->prepare("
        SELECT id, codigo, nombre, precio_compra1, precio_compra2, precio_venta, stock_actual, imagen
        FROM productos
        WHERE estado = 1
          AND (codigo LIKE :q_codigo OR nombre LIKE :q_nombre)
        ORDER BY nombre ASC
        LIMIT 20
    ");
    $searchTerm = '%' . $q . '%';
    $stmt->execute([
        ':q_codigo' => $searchTerm,
        ':q_nombre' => $searchTerm,
    ]);
    $rows = $stmt->fetchAll() ?: [];
    json_out(200, ['ok' => true, 'data' => $rows]);
}

if ($action === 'create_sale') {
    require_csrf();

    $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
    if ($usuarioId <= 0) {
        json_out(401, ['ok' => false, 'message' => 'Sesión inválida.']);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_out(400, ['ok' => false, 'message' => 'JSON inválido.']);
    }

    $items = $payload['items'] ?? null;
    $clienteId = (int) ($payload['cliente_id'] ?? 0);
    if ($clienteId <= 0) {
        $clienteId = null; // Venta sin cliente
    }
    $descuento = (float) ($payload['descuento'] ?? 0);
    if ($descuento < 0) $descuento = 0;

    if (!is_array($items) || count($items) === 0) {
        json_out(422, ['ok' => false, 'message' => 'Agrega al menos 1 producto.']);
    }

    $clean = [];
    foreach ($items as $it) {
        $pid = (int) ($it['producto_id'] ?? 0);
        $qty = (float) ($it['cantidad'] ?? 0);
        $precioVenta = (float) ($it['precio_venta'] ?? 0);
        $precioCompra = (float) ($it['precio_compra'] ?? 0);
        if ($pid <= 0 || $qty <= 0 || $precioVenta < 0) {
            json_out(422, ['ok' => false, 'message' => 'Items inválidos.']);
        }
        $clean[] = [
            'producto_id' => $pid,
            'cantidad' => $qty,
            'precio_venta' => $precioVenta,
            'precio_compra' => $precioCompra
        ];
    }

    // Unificar por producto (si se repite, sumamos cantidades pero mantenemos el mismo precio)
    $byId = [];
    foreach ($clean as $it) {
        $pid = $it['producto_id'];
        if (!isset($byId[$pid])) {
            $byId[$pid] = [
                'cantidad' => 0,
                'precio_venta' => $it['precio_venta'],
                'precio_compra' => $it['precio_compra']
            ];
        }
        $byId[$pid]['cantidad'] += $it['cantidad'];
    }

    $productoIds = array_keys($byId);
    $placeholders = implode(',', array_fill(0, count($productoIds), '?'));

    try {
        $db->beginTransaction();

        // Bloquear filas para evitar venta concurrente sin stock
        $stmtP = $db->prepare("
            SELECT id, codigo, nombre, precio_venta, stock_actual
            FROM productos
            WHERE id IN ($placeholders) AND estado = 1
            FOR UPDATE
        ");
        $stmtP->execute($productoIds);
        $productos = $stmtP->fetchAll() ?: [];

        if (count($productos) !== count($productoIds)) {
            $db->rollBack();
            json_out(409, ['ok' => false, 'message' => 'Uno o más productos no existen o están inactivos.']);
        }

        $total = 0.0;
        $detalle = [];
        foreach ($productos as $p) {
            $pid = (int) $p['id'];
            $qty = (float) $byId[$pid]['cantidad'];
            $precioVenta = (float) $byId[$pid]['precio_venta'];
            $precioCompra = (float) $byId[$pid]['precio_compra'];
            $stock = (float) $p['stock_actual'];
            if ($qty > $stock) {
                $db->rollBack();
                json_out(409, ['ok' => false, 'message' => "Stock insuficiente: {$p['codigo']} - {$p['nombre']} (stock: {$p['stock_actual']})."]);
            }
            $sub = $precioVenta * $qty;
            $total += $sub;
            $detalle[] = [
                'producto_id' => $pid,
                'cantidad' => $qty,
                'precio_unitario' => $precioVenta,
                'precio_compra' => $precioCompra,
                'subtotal' => $sub,
            ];
        }

        // Aplicar descuento global al total
        $total = max(0, $total - $descuento);

        // Insert venta (schema: ventas(id,total,usuario_id,id_caja,cliente_id,estado_pago,fecha))
        $idCaja = $_SESSION['id_caja'] ?? null;

        $stmtV = $db->prepare("
            INSERT INTO ventas (total, usuario_id, id_caja, cliente_id, estado_pago, descuento, fecha) 
            VALUES (:total, :usuario_id, :id_caja, :cliente_id, 'cobrado', :descuento, :fecha)
        ");
        $stmtV->execute([
            ':total' => $total,
            ':usuario_id' => $usuarioId,
            ':id_caja' => $idCaja,
            ':cliente_id' => $clienteId,
            ':descuento' => $descuento,
            ':fecha' => date('Y-m-d H:i:s')
        ]);
        $ventaId = (int) $db->lastInsertId();

        $stmtD = $db->prepare("
            INSERT INTO ventas_detalle (venta_id, producto_id, cantidad, precio_unitario, precio_compra, subtotal)
            VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :precio_compra, :subtotal)
        ");
        $stmtU = $db->prepare("UPDATE productos SET stock_actual = stock_actual - :cantidad WHERE id = :id LIMIT 1");

        foreach ($detalle as $d) {
            $stmtD->execute([
                ':venta_id' => $ventaId,
                ':producto_id' => $d['producto_id'],
                ':cantidad' => $d['cantidad'],
                ':precio_unitario' => $d['precio_unitario'],
                ':precio_compra' => $d['precio_compra'],
                ':subtotal' => $d['subtotal'],
            ]);
            $stmtU->execute([':cantidad' => $d['cantidad'], ':id' => $d['producto_id']]);
        }

        $db->commit();
        json_out(201, ['ok' => true, 'message' => 'Venta registrada.', 'venta_id' => $ventaId, 'total' => $total]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json_out(500, ['ok' => false, 'message' => 'Error registrando venta: ' . $e->getMessage()]);
    }
}

if ($action === 'confirm_payment') {
    require_csrf();

    $ventaId = (int) ($_POST['venta_id'] ?? 0);
    $efectivo = (float) ($_POST['efectivo'] ?? 0);
    $qr = (float) ($_POST['qr'] ?? 0);
    $metodo = trim((string) ($_POST['metodo_pago'] ?? 'efectivo'));
    $estado = trim((string) ($_POST['estado_pago'] ?? 'cobrado'));

    if ($ventaId <= 0) {
        json_out(422, ['ok' => false, 'message' => 'ID de venta inválido.']);
    }

    try {
        $stmt = $db->prepare("UPDATE ventas SET 
            pago_efectivo = :efectivo, 
            pago_qr = :qr, 
            metodo_pago = :metodo, 
            estado_pago = :estado 
            WHERE id = :id");
        $stmt->execute([
            ':efectivo' => $efectivo,
            ':qr' => $qr,
            ':metodo' => $metodo,
            ':estado' => $estado,
            ':id' => $ventaId
        ]);
        json_out(200, ['ok' => true, 'message' => 'Pago confirmado correctamente.']);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => 'Error al confirmar pago: ' . $e->getMessage()]);
    }
}

if ($action === 'update_estado_pago') {
    require_csrf();

    $ventaId = (int) ($_POST['venta_id'] ?? 0);
    $estadoPago = trim((string) ($_POST['estado_pago'] ?? ''));

    if ($ventaId <= 0 || !in_array($estadoPago, ['pendiente', 'cobrado'])) {
        json_out(422, ['ok' => false, 'message' => 'Datos inválidos.']);
    }

    try {
        $stmt = $db->prepare("UPDATE ventas SET estado_pago = :estado WHERE id = :id");
        $stmt->execute([':estado' => $estadoPago, ':id' => $ventaId]);
        json_out(200, ['ok' => true, 'message' => 'Estado actualizado.']);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => 'Error actualizando estado.']);
    }
}

if ($action === 'create_cotizacion') {
    require_csrf();

    $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
    if ($usuarioId <= 0) {
        json_out(401, ['ok' => false, 'message' => 'Sesión inválida.']);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_out(400, ['ok' => false, 'message' => 'JSON inválido.']);
    }

    $items = $payload['items'] ?? null;
    $clienteId = (int) ($payload['cliente_id'] ?? 0);
    if ($clienteId <= 0) $clienteId = null;
    $descuentoGlobal = (float) ($payload['descuento'] ?? 0);
    if ($descuentoGlobal < 0) $descuentoGlobal = 0;
    $notas = trim((string) ($payload['notas'] ?? ''));
    $diasValidez = (int) ($payload['dias_validez'] ?? 7);
    if ($diasValidez < 1) $diasValidez = 7;

    if (!is_array($items) || count($items) === 0) {
        json_out(422, ['ok' => false, 'message' => 'Agrega al menos 1 producto.']);
    }

    try {
        $db->beginTransaction();

        // Generar código único
        $stmtCod = $db->query("SELECT COUNT(*) as total FROM cotizaciones");
        $rowCod = $stmtCod->fetch();
        $num = ($rowCod['total'] ?? 0) + 1;
        $codigo = 'COT-' . str_pad($num, 6, '0', STR_PAD_LEFT);

        $total = 0.0;
        $detalle = [];
        foreach ($items as $it) {
            $pid = (int) ($it['producto_id'] ?? 0);
            $qty = (float) ($it['cantidad'] ?? 0);
            $precioUnitario = (float) ($it['precio_unitario'] ?? 0);
            $desc = (float) ($it['descuento'] ?? 0);
            $cod = trim((string) ($it['codigo'] ?? ''));
            $nom = trim((string) ($it['nombre'] ?? ''));

            if ($qty <= 0 || $precioUnitario < 0) {
                $db->rollBack();
                json_out(422, ['ok' => false, 'message' => 'Items inválidos.']);
            }

            $sub = $precioUnitario * $qty;
            $total += $sub;

            $detalle[] = [
                'producto_id' => $pid ?: null,
                'codigo' => $cod,
                'nombre' => $nom,
                'cantidad' => $qty,
                'precio_unitario' => $precioUnitario,
                'descuento' => $desc,
                'subtotal' => $sub
            ];
        }

        // Aplicar descuento global al total
        $total = max(0, $total - $descuentoGlobal);

        // Calcular fecha de validez
        $fechaValidez = date('Y-m-d', strtotime("+{$diasValidez} days"));

        $stmtC = $db->prepare("
            INSERT INTO cotizaciones (codigo, cliente_id, usuario_id, total, descuento_global, notas, fecha_validez, estado, created_at)
            VALUES (:codigo, :cliente_id, :usuario_id, :total, :descuento_global, :notas, :fecha_validez, 'activa', NOW())
        ");
        $stmtC->execute([
            ':codigo' => $codigo,
            ':cliente_id' => $clienteId,
            ':usuario_id' => $usuarioId,
            ':total' => $total,
            ':descuento_global' => $descuentoGlobal,
            ':notas' => $notas,
            ':fecha_validez' => $fechaValidez
        ]);
        $cotizacionId = (int) $db->lastInsertId();

        $stmtD = $db->prepare("
            INSERT INTO cotizaciones_detalle (cotizacion_id, producto_id, codigo, nombre, cantidad, precio_unitario, descuento, subtotal)
            VALUES (:cotizacion_id, :producto_id, :codigo, :nombre, :cantidad, :precio_unitario, :descuento, :subtotal)
        ");

        foreach ($detalle as $d) {
            $stmtD->execute([
                ':cotizacion_id' => $cotizacionId,
                ':producto_id' => $d['producto_id'],
                ':codigo' => $d['codigo'],
                ':nombre' => $d['nombre'],
                ':cantidad' => $d['cantidad'],
                ':precio_unitario' => $d['precio_unitario'],
                ':descuento' => $d['descuento'],
                ':subtotal' => $d['subtotal']
            ]);
        }

        $db->commit();
        json_out(201, ['ok' => true, 'message' => 'Cotización creada.', 'id' => $cotizacionId, 'codigo' => $codigo]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        json_out(500, ['ok' => false, 'message' => 'Error creando cotización: ' . $e->getMessage()]);
    }
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);

