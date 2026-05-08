<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Producto.php';
require_once __DIR__ . '/../../models/Compra.php';

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
    $productoModel = new Producto($db);
    $compraModel = new Compra($db);
} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'search_products') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        json_out(200, ['ok' => true, 'data' => []]);
    }
    try {
        $rows = $productoModel->listSimple(0, 20, $q, 'nombre', 'asc');
        json_out(200, ['ok' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'save_purchase') {
    require_csrf();
    $data = json_decode(file_get_contents('php://input'), true);
    $items = $data['items'] ?? [];
    $total = (float)($data['total'] ?? 0);

    if (empty($items)) {
        json_out(400, ['ok' => false, 'message' => 'No hay ítems en la compra.']);
    }

    try {
        $db->beginTransaction();

        $compraId = $db->prepare("INSERT INTO compras (total, usuario_id) VALUES (?, ?)");
        $compraId->execute([$total, $_SESSION['user_id']]);
        $idCompra = $db->lastInsertId();

        foreach ($items as $it) {
            $prodId = (int)($it['producto_id'] ?? 0);
            $nombre = trim((string)($it['nombre'] ?? ''));
            $codigo = isset($it['codigo']) && $it['codigo'] !== '' && $it['codigo'] !== null ? trim((string)$it['codigo']) : null;
            $categoriaId = isset($it['categoria_id']) && $it['categoria_id'] !== '' && $it['categoria_id'] !== null ? (int)$it['categoria_id'] : null;
            $p1 = (float)($it['precio_compra1'] ?? 0);
            $p2 = (float)($it['precio_compra2'] ?? 0);
            $venta = (float)($it['precio_venta'] ?? 0);
            $cantidad = (float)($it['cantidad'] ?? 0);
            $subtotal = $venta * $cantidad;
            $subtotalCompra = $p1 * $cantidad;

            if ($prodId > 0) {
                // Producto existente
                $existente = $productoModel->findById($prodId);
                if ($existente) {
                    $nuevoStock = (float)$existente['stock_actual'] + $cantidad;
                    $updateData = [
                        'nombre' => $nombre ?: $existente['nombre'],
                        'codigo' => $codigo,
                        'precio_compra1' => $p1,
                        'precio_compra2' => $p2,
                        'precio_venta' => $venta,
                        'stock_actual' => $nuevoStock,
                        'stock_minimo' => $existente['stock_minimo'],
                        'estado' => 1,
                        'categoria_id' => $categoriaId,
                    ];
                    $productoModel->update($prodId, $updateData);
                }
            } else {
                // Producto nuevo
                $createData = [
                    'nombre' => $nombre,
                    'codigo' => $codigo,
                    'categoria_id' => $categoriaId,
                    'precio_compra1' => $p1,
                    'precio_compra2' => $p2,
                    'precio_venta' => $venta,
                    'stock_actual' => $cantidad,
                    'stock_minimo' => 5,
                    'estado' => 1,
                ];
                $prodId = $productoModel->create($createData);
            }

            // Registro en detalle
            $stmtDet = $db->prepare("
                INSERT INTO compras_detalle
                (compra_id, producto_id, cantidad, precio_compra1, precio_compra2, precio_venta, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtDet->execute([$idCompra, $prodId, $cantidad, $p1, $p2, $venta, $subtotalCompra]);
        }

        $db->commit();
        json_out(200, ['ok' => true, 'message' => 'Compra procesada correctamente e inventario actualizado.', 'compra_id' => $idCompra]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        json_out(500, ['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);
