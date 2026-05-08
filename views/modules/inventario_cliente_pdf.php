<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Accesible para usuarios con acceso al sistema (vendedores o admin)
Auth::checkAccess();

try {
    $db = (new Database())->getConnection();
    
    // Configuración general
    $moneda = 'Bs';
    $stmtCfg = $db->query("SELECT moneda FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $rowCfg = $stmtCfg->fetch();
    if ($rowCfg && isset($rowCfg['moneda'])) {
        $moneda = (string)$rowCfg['moneda'];
    }

    // Filtrar productos con stock > 0 para clientes
    $stmt = $db->prepare("SELECT nombre, precio_venta, stock_actual FROM productos WHERE stock_actual > 0 AND estado = 1 ORDER BY nombre ASC");
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generar HTML optimizado para clientes
    $html = '
    <html>
    <head>
        <style>
            body { font-family: "Helvetica", sans-serif; font-size: 13px; color: #1a202c; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { margin: 0; color: #2d3748; font-size: 24px; letter-spacing: -0.02em; }
            .header p { color: #718096; margin-top: 5px; font-size: 14px; }
            table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
            th { background-color: #f7fafc; color: #4a5568; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
            td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
            tr:nth-child(even) { background-color: #fbfcfd; }
            .text-end { text-align: right; }
            .price { font-weight: 700; color: #2d3748; }
            .stock { font-weight: 600; color: #3182ce; }
            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 11px; color: #a0aec0; padding: 20px 0; border-top: 1px solid #edf2f7; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Catálogo de Productos Disponibles</h1>
            <p>Lista de precios y existencias actualizada al ' . date('d/m/Y') . '</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Descripción del Producto</th>
                    <th class="text-end" style="width: 120px;">Precio (' . $moneda . ')</th>
                    <th class="text-end" style="width: 100px;">Disponible</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($productos as $p) {
        $html .= '
                <tr>
                    <td style="font-size: 14px; font-weight: 500;">' . htmlspecialchars($p['nombre']) . '</td>
                    <td class="text-end price">' . number_format($p['precio_venta'], 2) . '</td>
                    <td class="text-end stock">' . $p['stock_actual'] . ' u.</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>

        <div class="footer">
            Consulte disponibilidad antes de realizar su pedido. Filacell POS &copy; ' . date('Y') . '
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
    $dompdf->stream("Catalogo_Filacell_" . date('Ymd') . ".pdf", ["Attachment" => false]);

} catch (Throwable $e) {
    echo "Error generando PDF: " . $e->getMessage();
}
