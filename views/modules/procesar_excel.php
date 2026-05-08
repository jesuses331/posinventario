<?php
header('Content-Type: application/json; charset=utf-8');

// Ajustar ruta según ubicación (estamos en views/modules/)
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Autoload no encontrado en ' . $autoloadPath]);
    exit;
}
require_once $autoloadPath;
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $db = (new Database())->getConnection();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $file = $_FILES['excel_file']['tmp_name'];
        if (!$file)
            throw new Exception("No se cargó el archivo correctamente.");

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // SQL: Si el nombre ya existe, SUMA el stock nuevo al actual y ACTUALIZA precios
        // El campo 'codigo' es UNIQUE, hay que manejarlo. Si no hay código en el Excel, podemos generar uno o usar el nombre.
        $sql = "INSERT INTO productos (codigo, nombre, precio_compra1, precio_compra2, precio_venta, stock_actual, stock_minimo, estado) 
                VALUES (:codigo, :nombre, :c1, :c2, :v, :s, 0, 1)
                ON DUPLICATE KEY UPDATE 
                stock_actual = stock_actual + VALUES(stock_actual),
                precio_compra1 = VALUES(precio_compra1),
                precio_compra2 = VALUES(precio_compra2),
                precio_venta = VALUES(precio_venta)";

        $stmt = $db->prepare($sql);
        $db->beginTransaction();

        $insertados = 0;
        foreach ($rows as $index => $row) {
            // Saltar cabecera (index 0) o filas vacías
            if ($index === 0 || (empty($row[0]) && empty($row[1])))
                continue;

            // Concatenación según sugerencia del usuario: [0]MARCA + [1]MODELO + [2]CALIDAD
            $marca = trim((string) ($row[0] ?? ''));
            $modelo = trim((string) ($row[1] ?? ''));
            $calidad = trim((string) ($row[2] ?? ''));
            $nombre = trim($marca . " " . $modelo . " " . $calidad);
            if ($nombre === '')
                continue;

            // Generar un código único basado en el nombre si no existe columna de código
            $codigo = substr(md5($nombre), 0, 10);

            // Mapping de precios sugerido por el usuario
            $c1 = (float) ($row[4] ?? 0);
            $c2 = (float) ($row[5] ?? 0);
            $venta = (float) ($row[6] ?? 0);

            // Stock (Índice 7)
            $stock = (isset($row[7]) && $row[7] !== '' && $row[7] !== null) ? (float) $row[7] : 0;

            $stmt->execute([
                ':codigo' => $codigo,
                ':nombre' => $nombre,
                ':c1' => $c1,
                ':c2' => $c2,
                ':v' => $venta,
                ':s' => $stock
            ]);
            $insertados++;
        }

        $db->commit();
        echo json_encode(['status' => 'success', 'message' => "Procesados $insertados productos exitosamente."]);

    } catch (Throwable $e) {
        if ($db->inTransaction())
            $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Solicitud inválida.']);
}
