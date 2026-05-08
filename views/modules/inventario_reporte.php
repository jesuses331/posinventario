<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

Auth::checkAccess('admin');

try {
    $db = (new Database())->getConnection();
    
    // Configuración general
    $moneda = 'Bs';
    $stmtCfg = $db->query("SELECT moneda FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $rowCfg = $stmtCfg->fetch();
    if ($rowCfg && isset($rowCfg['moneda'])) {
        $moneda = (string)$rowCfg['moneda'];
    }

    // Filtrar productos con stock > 0
    $stmt = $db->prepare("SELECT * FROM productos WHERE stock_actual > 0 AND estado = 1 ORDER BY nombre ASC");
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generar HTML para el PDF
    $html = '
    <html>
    <head>
        <style>
            body { font-family: sans-serif; font-size: 12px; color: #333; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
            .header h1 { margin: 0; color: #1e3a8a; font-size: 20px; }
            .footer { position: fixed; bottom: -20px; left: 0; right: 0; height: 30px; text-align: center; font-size: 10px; color: #777; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background-color: #2563eb; color: white; padding: 8px; text-align: left; }
            td { border-bottom: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background-color: #f8fafc; }
            .text-end { text-align: right; }
            .total-row { font-weight: bold; background-color: #f1f5f9 !important; }
            .badge-stock { color: #059669; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Reporte de Inventario (Stock Disponible)</h1>
            <p>Generado el: ' . date('d/m/Y H:i') . '</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Producto</th>
                    <th class="text-end" style="width: 80px;">P. Compra 1</th>
                    <th class="text-end" style="width: 80px;">P. Compra 2</th>
                    <th class="text-end" style="width: 80px;">P. Venta</th>
                    <th class="text-end" style="width: 60px;">Stock</th>
                </tr>
            </thead>
            <tbody>';

    $totalValComp1 = 0;
    foreach ($productos as $p) {
        $stock = (float)$p['stock_actual'];
        $p1 = (float)$p['precio_compra1'];
        $totalValComp1 += ($stock * $p1);

        $html .= '
                <tr>
                    <td>' . $p['id'] . '</td>
                    <td>' . htmlspecialchars($p['nombre']) . '</td>
                    <td class="text-end">' . number_format($p['precio_compra1'], 2) . '</td>
                    <td class="text-end">' . number_format($p['precio_compra2'], 2) . '</td>
                    <td class="text-end">' . number_format($p['precio_venta'], 2) . '</td>
                    <td class="text-end" class="badge-stock">' . $p['stock_actual'] . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>

        <div style="margin-top: 20px; text-align: right;">
            <p><strong>Total Productos:</strong> ' . count($productos) . '</p>
            <p><strong>Valor Estimado (P. Compra 1):</strong> ' . $moneda . ' ' . number_format($totalValComp1, 2) . '</p>
        </div>

        <div class="footer">
            Página {PAGE_NUM} de {PAGE_COUNT} - Filacell POS
        </div>
    </body>
    </html>';

    // Configurar Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Enviar PDF al navegador
    $dompdf->stream("Reporte_Stock_" . date('Ymd_His') . ".pdf", ["Attachment" => false]);

} catch (Throwable $e) {
    echo "Error generando PDF: " . $e->getMessage();
}
