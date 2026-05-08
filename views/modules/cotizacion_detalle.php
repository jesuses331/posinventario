<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido');
}

$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/';

try {
    $db = (new Database())->getConnection();

    $stmtC = $db->prepare("
        SELECT c.*, u.nombre as usuario_nombre, cl.nombre as cliente_nombre, cl.cedula as cliente_cedula
        FROM cotizaciones c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        LEFT JOIN clientes cl ON c.cliente_id = cl.id
        WHERE c.id = ?
    ");
    $stmtC->execute([$id]);
    $c = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        die('Cotización no encontrada');
    }

    $stmtD = $db->prepare("SELECT * FROM cotizaciones_detalle WHERE cotizacion_id = ?");
    $stmtD->execute([$id]);
    $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $moneda = 'Bs';
    $stmtCfg = $db->query("SELECT moneda, nombre_negocio FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $rowCfg = $stmtCfg->fetch();
    if ($rowCfg) {
        $moneda = $rowCfg['moneda'];
        $nombreNegocio = $rowCfg['nombre_negocio'] ?? 'AbdiSoft CORE';
    }

} catch (Throwable $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización <?= htmlspecialchars($c['codigo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #fff; padding: 2rem; }
        .header { border-bottom: 2px solid #6c757d; padding-bottom: 1rem; margin-bottom: 2rem; }
        .title { font-size: 1.5rem; font-weight: 800; color: #6c757d; }
        .badge-estado { font-size: 0.8rem; padding: 0.4rem 1rem; }
        .table thead th { background: #f8f9fa; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .total-row { font-size: 1.2rem; font-weight: 800; }
        .footer { border-top: 1px solid #dee2e6; padding-top: 1rem; margin-top: 2rem; font-size: 0.85rem; color: #6c757d; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="text-end no-print mb-3">
        <button class="btn btn-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <button class="btn btn-light" onclick="window.close()">Cerrar</button>
    </div>

    <div class="header d-flex justify-content-between align-items-center">
        <div>
            <div class="title"><?= htmlspecialchars($nombreNegocio) ?></div>
            <div class="text-muted">Cotización</div>
        </div>
        <div class="text-end">
            <div class="fw-bold" style="font-size:1.3rem"><?= htmlspecialchars($c['codigo']) ?></div>
            <div class="text-muted"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <strong>Cliente:</strong>
            <?= htmlspecialchars($c['cliente_nombre'] ?? 'Sin cliente') ?>
            <?php if ($c['cliente_cedula']): ?><br><span class="text-muted">C.I.: <?= htmlspecialchars($c['cliente_cedula']) ?></span><?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <strong>Válido hasta:</strong> <?= $c['fecha_validez'] ? date('d/m/Y', strtotime($c['fecha_validez'])) : 'No definida' ?><br>
            <span class="badge badge-<?= $c['estado'] ?> bg-secondary"><?= strtoupper($c['estado']) ?></span>
            <br>
            <span class="text-muted">Usuario: <?= htmlspecialchars($c['usuario_nombre']) ?></span>
        </div>
    </div>

    <?php if ($c['notas']): ?>
        <div class="alert alert-light border mb-4"><?= nl2br(htmlspecialchars($c['notas'])) ?></div>
    <?php endif; ?>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="text-center">Cant.</th>
                <th class="text-end">Precio Unit.</th>
                <th class="text-end">Desc.</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalle as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['nombre']) ?><br><small class="text-muted"><?= htmlspecialchars($d['codigo'] ?? '') ?></small></td>
                    <td class="text-center"><?= (int) $d['cantidad'] ?></td>
                    <td class="text-end"><?= $moneda ?> <?= number_format((float) $d['precio_unitario'], 2) ?></td>
                    <td class="text-end"><?= (float) ($d['descuento'] ?? 0) > 0 ? (float) $d['descuento'] . '%' : '-' ?></td>
                    <td class="text-end fw-bold"><?= $moneda ?> <?= number_format((float) $d['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php
                $subtotalCot = (float) $c['total'] + (float) ($c['descuento_global'] ?? 0);
                $descuentoCot = (float) ($c['descuento_global'] ?? 0);
            ?>
            <tr>
                <td colspan="4" class="text-end fw-bold">SUBTOTAL</td>
                <td class="text-end fw-bold"><?= $moneda ?> <?= number_format($subtotalCot, 2) ?></td>
            </tr>
            <?php if ($descuentoCot > 0): ?>
            <tr>
                <td colspan="4" class="text-end text-danger">DESCUENTO GLOBAL</td>
                <td class="text-end text-danger">-<?= $moneda ?> <?= number_format($descuentoCot, 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="4" class="text-end">TOTAL</td>
                <td class="text-end"><?= $moneda ?> <?= number_format((float) $c['total'], 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer text-center">
        <p class="mb-0"><?= htmlspecialchars($nombreNegocio) ?> - Cotización generada el <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></p>
        <p class="mb-0 text-muted">Documento sin valor fiscal. Cotización válida hasta <?= $c['fecha_validez'] ? date('d/m/Y', strtotime($c['fecha_validez'])) : 'fecha por definir' ?>.</p>
    </div>
</body>
</html>
