<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

Auth::checkAccess('admin');

$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$usuario_id = (int)($_GET['usuario_id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'general';

try {
    $db = (new Database())->getConnection();
} catch (Throwable $e) {
    die('Error de conexión');
}

$where = "WHERE DATE(v.fecha) BETWEEN :d AND :h";
$params = [':d' => $desde, ':h' => $hasta];

if ($usuario_id > 0) {
    $where .= " AND v.usuario_id = :uid";
    $params[':uid'] = $usuario_id;
}
if ($estado !== '') {
    $where .= " AND v.estado_pago = :st";
    $params[':st'] = $estado;
}

// Obtener nombre de negocio
$stmtCfg = $db->query("SELECT nombre_negocio FROM configuracion ORDER BY updated_at DESC LIMIT 1");
$cfg = $stmtCfg->fetch();
$nombreNegocio = $cfg['nombre_negocio'] ?? 'AbdiSoft POS';

function money($n) {
    return number_format($n ?? 0, 2, '.', ',');
}

// Cargar dompdf
require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$html = '<style>
    body { font-family: Arial, sans-serif; font-size: 12px; }
    h1 { font-size: 18px; margin-bottom: 5px; }
    .subtitle { color: #666; font-size: 11px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th { background: #f0f0f0; padding: 8px; text-align: left; font-size: 11px; border-bottom: 2px solid #ccc; }
    td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 11px; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .fw-bold { font-weight: bold; }
    .badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
    .bg-success { background: #198754; color: white; }
    .bg-warning { background: #ffc107; color: #000; }
    .kpi-box { display: inline-block; width: 23%; margin: 10px 1%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    .kpi-label { font-size: 10px; color: #666; }
    .kpi-value { font-size: 16px; font-weight: bold; margin-top: 5px; }
</style>';

$html .= '<h1>' . htmlspecialchars($nombreNegocio) . '</h1>';
$html .= '<div class="subtitle">Reporte: ' . ucfirst($tipo) . ' | Del ' . $desde . ' al ' . $hasta . '</div>';

if ($tipo === 'general') {
    // KPIs
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as total_ventas,
            SUM(v.total) as total,
            SUM(v.pago_efectivo) as efectivo,
            SUM(v.pago_qr) as qr,
            SUM(v.descuento) as descuentos
        FROM ventas v $where
    ");
    $stmt->execute($params);
    $kpis = $stmt->fetch();

    $html .= '<div>';
    $html .= '<div class="kpi-box"><div class="kpi-label">Total Ventas</div><div class="kpi-value">' . money($kpis['total']) . '</div></div>';
    $html .= '<div class="kpi-box"><div class="kpi-label">Efectivo</div><div class="kpi-value">' . money($kpis['efectivo']) . '</div></div>';
    $html .= '<div class="kpi-box"><div class="kpi-label">QR</div><div class="kpi-value">' . money($kpis['qr']) . '</div></div>';
    $html .= '<div class="kpi-box"><div class="kpi-label">Descuentos</div><div class="kpi-value" style="color:#dc3545;">-' . money($kpis['descuentos'] ?? 0) . '</div></div>';
    $html .= '<div class="kpi-box"><div class="kpi-label">N° Ventas</div><div class="kpi-value">' . $kpis['total_ventas'] . '</div></div>';
    $html .= '</div>';

    // Lista de ventas
    $html .= '<table><thead><tr>
        <th>ID</th><th>Fecha</th><th>Cliente</th>
        <th class="text-end">Subtotal</th><th class="text-end">Desc.</th>
        <th class="text-end">Total</th><th class="text-end">Efectivo</th>
        <th class="text-end">QR</th><th>Estado</th>
    </tr></thead><tbody>';

    $stmt = $db->prepare("
        SELECT v.id, v.fecha, v.total, (v.total + COALESCE(v.descuento,0)) as subtotal, v.descuento, v.pago_efectivo, v.pago_qr, v.estado_pago,
               c.nombre as cliente_nombre
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        $where ORDER BY v.id DESC
    ");
    $stmt->execute($params);
    $ventas = $stmt->fetchAll();

    foreach ($ventas as $v) {
        $estadoBadge = $v['estado_pago'] === 'cobrado'
            ? '<span class="badge bg-success">Pagado</span>'
            : '<span class="badge bg-warning">Pendiente</span>';
        $descuentoHtml = $v['descuento'] > 0 ? '-Bs ' . number_format($v['descuento'], 2) : '-';
        $html .= '<tr>
            <td>' . $v['id'] . '</td>
            <td>' . $v['fecha'] . '</td>
            <td>' . htmlspecialchars($v['cliente_nombre'] ?? 'Venta Directa') . '</td>
            <td class="text-end">' . money($v['subtotal']) . '</td>
            <td class="text-end">' . $descuentoHtml . '</td>
            <td class="text-end fw-bold">' . money($v['total']) . '</td>
            <td class="text-end">' . money($v['pago_efectivo']) . '</td>
            <td class="text-end">' . money($v['pago_qr']) . '</td>
            <td>' . $estadoBadge . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';

} elseif ($tipo === 'usuarios') {
    $html .= '<table><thead><tr>
        <th>Usuario</th><th class="text-end">Ventas</th>
        <th class="text-end">Total</th><th class="text-end">Efectivo</th>
        <th class="text-end">QR</th>
    </tr></thead><tbody>';

    $stmt = $db->prepare("
        SELECT u.nombre as usuario, COUNT(DISTINCT v.id) as num_ventas,
               SUM(v.total) as total, SUM(v.pago_efectivo) as efectivo, SUM(v.pago_qr) as qr
        FROM ventas v LEFT JOIN usuarios u ON v.usuario_id = u.id
        $where GROUP BY u.id, u.nombre ORDER BY total DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    foreach ($data as $u) {
        $html .= '<tr>
            <td class="fw-bold">' . htmlspecialchars($u['usuario'] ?? 'N/A') . '</td>
            <td class="text-end">' . $u['num_ventas'] . '</td>
            <td class="text-end fw-bold">' . money($u['total']) . '</td>
            <td class="text-end">' . money($u['efectivo']) . '</td>
            <td class="text-end">' . money($u['qr']) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';

} elseif ($tipo === 'productos') {
    $html .= '<table><thead><tr>
        <th>Producto</th><th>Código</th>
        <th class="text-end">Unidades</th><th class="text-end">Total</th>
    </tr></thead><tbody>';

    $stmt = $db->prepare("
        SELECT p.nombre as producto, p.codigo,
               SUM(vd.cantidad) as unidades, SUM(vd.subtotal) as total
        FROM ventas_detalle vd
        INNER JOIN ventas v ON vd.venta_id = v.id
        INNER JOIN productos p ON vd.producto_id = p.id
        $where GROUP BY p.id, p.nombre, p.codigo ORDER BY unidades DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    foreach ($data as $p) {
        $html .= '<tr>
            <td class="fw-bold">' . htmlspecialchars($p['producto']) . '</td>
            <td>' . htmlspecialchars($p['codigo']) . '</td>
            <td class="text-end fw-bold">' . $p['unidades'] . '</td>
            <td class="text-end">' . money($p['total']) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'reporte_' . $tipo . '_' . $desde . '_' . $hasta . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit();
