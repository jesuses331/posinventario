<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Caja.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$cajaModel = new Caja($db);

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'abrir':
            // Verificar si ya tiene una caja abierta para evitar duplicados
            $cajaExistente = $cajaModel->obtenerEstado($_SESSION['user_id']);
            if ($cajaExistente) {
                echo json_encode(['ok' => false, 'message' => 'Ya tienes una caja abierta actualmente.']);
                break;
            }

            $montoInicial = floatval($_POST['monto_inicial'] ?? 0);
            $idCaja = $cajaModel->abrir($_SESSION['user_id'], $montoInicial);
            echo json_encode(['ok' => true, 'id' => $idCaja]);
            break;

        case 'cerrar':
            $idCaja = intval($_POST['id_caja'] ?? 0);
            $montoReal = floatval($_POST['monto_final_real'] ?? 0);

            $success = $cajaModel->cerrar($idCaja, $montoReal);

            if ($success) {
                unset($_SESSION['id_caja']); // Limpiar de la sesión
                echo json_encode(['ok' => true]);
            } else {
                echo json_encode(['ok' => false, 'message' => 'Error al cerrar la caja']);
            }
            break;

        case 'registrar_gasto':
            $idCaja = intval($_POST['id_caja'] ?? 0);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $monto = floatval($_POST['monto'] ?? 0);

            if (empty($descripcion) || $monto <= 0) {
                echo json_encode(['ok' => false, 'message' => 'Datos de gasto inválidos']);
                break;
            }

            $stmt = $db->prepare("INSERT INTO gastos_extra (id_caja, descripcion, monto) VALUES (?, ?, ?)");
            $success = $stmt->execute([$idCaja, $descripcion, $monto]);

            echo json_encode(['ok' => $success]);
            break;

        case 'obtener_gastos':
            $idCaja = intval($_GET['id_caja'] ?? 0);

            $stmt = $db->prepare("SELECT descripcion, monto FROM gastos_extra WHERE id_caja = ? ORDER BY fecha DESC");
            $stmt->execute([$idCaja]);
            $gastos = $stmt->fetchAll();

            echo json_encode(['ok' => true, 'gastos' => $gastos]);
            break;

        case 'obtener_detalles_cierre':
            $idCaja = intval($_GET['id_caja'] ?? 0);

            // Obtener info de la caja
            $stmtInfo = $db->prepare("
                SELECT c.*, u.nombre as usuario_nombre
                FROM cajas c
                JOIN usuarios u ON c.id_usuario = u.id
                WHERE c.id = ?
            ");
            $stmtInfo->execute([$idCaja]);
            $info = $stmtInfo->fetch();

            // Obtener ventas
            $stmtVentas = $db->prepare("
                SELECT v.*, cl.nombre as cliente
                FROM ventas v
                LEFT JOIN clientes cl ON v.cliente_id = cl.id
                WHERE v.id_caja = ?
                ORDER BY v.fecha DESC
            ");
            $stmtVentas->execute([$idCaja]);
            $ventas = $stmtVentas->fetchAll();

            // Obtener gastos
            $stmtGastos = $db->prepare("SELECT descripcion, monto FROM gastos_extra WHERE id_caja = ? ORDER BY fecha DESC");
            $stmtGastos->execute([$idCaja]);
            $gastos = $stmtGastos->fetchAll();

            echo json_encode([
                'ok' => true,
                'info' => $info,
                'ventas' => $ventas,
                'gastos' => $gastos
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'message' => 'Acción no reconocida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
