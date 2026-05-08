<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';

$database = new Database();
$db = $database->getConnection();

$idCaja = intval($_GET['id'] ?? 0);

// Obtener info de la caja
$stmtInfo = $db->prepare("
    SELECT c.*, u.nombre as usuario_nombre
    FROM cajas c
    JOIN usuarios u ON c.id_usuario = u.id
    WHERE c.id = ?
");
$stmtInfo->execute([$idCaja]);
$info = $stmtInfo->fetch();

if (!$info) {
    die('Arqueo no encontrado');
}

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
$stmtGastos = $db->prepare("SELECT * FROM gastos_extra WHERE id_caja = ? ORDER BY fecha DESC");
$stmtGastos->execute([$idCaja]);
$gastos = $stmtGastos->fetchAll();

$totalGastos = array_sum(array_column($gastos, 'monto'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arqueo #<?= $idCaja ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .text-end { text-align: right; }
        .total { font-weight: bold; font-size: 1.1em; }
    </style>
</head>
<body>
    <h1>Arqueo de Caja #<?= $idCaja ?></h1>

    <div>
        <p><strong>Usuario:</strong> <?= htmlspecialchars($info['usuario_nombre']) ?></p>
        <p><strong>Apertura:</strong> <?= date('d/m/Y H:i', strtotime($info['fecha_apertura'])) ?></p>
        <p><strong>Cierre:</strong> <?= date('d/m/Y H:i', strtotime($info['fecha_cierre'])) ?></p>
    </div>

    <h3>Resumen</h3>
    <table>
        <tr>
            <td>Monto Inicial</td>
            <td class="text-end">Bs <?= number_format($info['monto_inicial'], 2) ?></td>
        </tr>
        <tr>
            <td>Total Efectivo</td>
            <td class="text-end">Bs <?= number_format($info['total_efectivo'], 2) ?></td>
        </tr>
        <tr>
            <td>Total QR</td>
            <td class="text-end">Bs <?= number_format($info['total_qr'], 2) ?></td>
        </tr>
        <tr>
            <td>Total Gastos</td>
            <td class="text-end">Bs <?= number_format($totalGastos, 2) ?></td>
        </tr>
        <tr class="total">
            <td>Monto Sistema</td>
            <td class="text-end">Bs <?= number_format($info['monto_final_sistema'], 2) ?></td>
        </tr>
        <tr class="total">
            <td>Monto Real</td>
            <td class="text-end">Bs <?= number_format($info['monto_final_real'], 2) ?></td>
        </tr>
        <tr class="<?= $info['diferencia'] < 0 ? 'text-danger' : 'text-success' ?>">
            <td>Diferencia</td>
            <td class="text-end">Bs <?= number_format($info['diferencia'], 2) ?></td>
        </tr>
    </table>

    <h3>Ventas</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th class="text-end">Efectivo</th>
                <th class="text-end">QR</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ventas as $v): ?>
            <tr>
                <td><?= $v['id'] ?></td>
                <td><?= htmlspecialchars($v['cliente'] ?? 'N/A') ?></td>
                <td class="text-end">Bs <?= number_format($v['pago_efectivo'], 2) ?></td>
                <td class="text-end">Bs <?= number_format($v['pago_qr'], 2) ?></td>
                <td class="text-end">Bs <?= number_format($v['total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Gastos Extra</h3>
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-end">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gastos as $g): ?>
            <tr>
                <td><?= htmlspecialchars($g['descripcion']) ?></td>
                <td class="text-end">Bs <?= number_format($g['monto'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
