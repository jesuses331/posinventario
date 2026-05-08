<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Producto.php';

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
    $producto = new Producto($db);
} catch (Throwable $e) {
    json_out(500, ['ok' => false, 'message' => 'No se pudo conectar a la base de datos.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list_for_pos') {
    // Permitir cajeros para esta acción
    if (!isset($_SESSION['user_id'])) {
        json_out(403, ['ok' => false, 'message' => 'Acceso denegado.']);
    }
    try {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 10);
        $search = trim((string)($_GET['search'] ?? ''));

        if ($page < 1) $page = 1;
        if ($perPage < 1 || $perPage > 100) $perPage = 10;

        $offset = ($page - 1) * $perPage;

        $total = $producto->countAll($search);
        $rows = $producto->listSimple($offset, $perPage, $search, 'nombre', 'asc');

        $totalPages = ceil($total / $perPage);

        json_out(200, [
            'ok' => true,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
    }
}

if ($action === 'list') {
    try {
        $draw = (int)($_GET['draw'] ?? 0);
        $start = (int)($_GET['start'] ?? 0);
        $length = (int)($_GET['length'] ?? 25);
        $search = (string)(($_GET['search']['value'] ?? '') ?: '');

        $columns = [
            0 => 'id',
            // 1 => 'codigo',
            2 => 'nombre',
            3 => 'precio_compra1',
            4 => 'precio_compra2',
            5 => 'precio_venta',
            6 => 'stock_actual',
            7 => 'stock_minimo',
            // 8 => 'fecha_vencimiento',
            9 => 'estado',
        ];
        $orderIdx = (int)($_GET['order'][0]['column'] ?? 0);
        $orderBy = $columns[$orderIdx] ?? 'id';
        $orderDir = (string)($_GET['order'][0]['dir'] ?? 'desc');

        $recordsTotal = $producto->countAll('');
        $recordsFiltered = $producto->countAll($search);
        $rows = $producto->listDataTables($start, $length, $search, $orderBy, $orderDir);

        json_out(200, [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    } catch (Throwable $e) {
        json_out(500, [
            'draw' => (int)($_GET['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Error interno del servidor: ' . $e->getMessage(),
        ]);
    }
}

if ($action === 'get') {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_out(400, ['ok' => false, 'message' => 'ID inválido.']);
        }
        $row = $producto->findById($id);
        if (!$row) {
            json_out(404, ['ok' => false, 'message' => 'Producto no encontrado.']);
        }
        json_out(200, ['ok' => true, 'data' => $row]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
    }
}

if ($action === 'save') {
    require_csrf();
    try {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $precioCompra1 = (float)($_POST['precio_compra1'] ?? 0);
        $precioCompra2 = (float)($_POST['precio_compra2'] ?? 0);
        $precioVenta = (float)($_POST['precio_venta'] ?? 0);
        $stockActual = (float)($_POST['stock_actual'] ?? 0);
        $stockMinimo = (float)($_POST['stock_minimo'] ?? 5);
        $estado = (int)($_POST['estado'] ?? 1);

        if ($nombre === '') {
            json_out(422, ['ok' => false, 'message' => 'Nombre son obligatorios.']);
        }
        if ($estado !== 0) $estado = 1;

        $payload = [
            'nombre' => $nombre,
            'precio_compra1' => $precioCompra1,
            'precio_compra2' => $precioCompra2,
            'precio_venta' => $precioVenta,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'estado' => $estado,
        ];

        // Procesar imagen si se subió
        $imagenPath = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/uploads/productos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['imagen'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed)) {
                $newFileName = uniqid('prod_', true) . '.webp';
                $targetPath = $uploadDir . $newFileName;

                // Convertir a WebP
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo) {
                    switch ($imageInfo[2]) {
                        case IMAGETYPE_JPEG:
                            $source = imagecreatefromjpeg($file['tmp_name']);
                            break;
                        case IMAGETYPE_PNG:
                            $source = imagecreatefrompng($file['tmp_name']);
                            break;
                        case IMAGETYPE_GIF:
                            $source = imagecreatefromgif($file['tmp_name']);
                            break;
                        case IMAGETYPE_WEBP:
                            $source = imagecreatefromwebp($file['tmp_name']);
                            break;
                        default:
                            $source = false;
                    }

                    if ($source) {
                        // Redimensionar si es muy grande (máximo 800px de ancho)
                        $maxWidth = 800;
                        $origWidth = imagesx($source);
                        $origHeight = imagesy($source);
                        
                        if ($origWidth > $maxWidth) {
                            $ratio = $maxWidth / $origWidth;
                            $newWidth = $maxWidth;
                            $newHeight = $origHeight * $ratio;
                            $resized = imagecreatetruecolor($newWidth, $newHeight);
                            
                            // Preservar transparencia para PNG
                            if ($imageInfo[2] == IMAGETYPE_PNG) {
                                imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                                imagealphablending($resized, false);
                                imagesavealpha($resized, true);
                            }
                            
                            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                            imagedestroy($source);
                            $source = $resized;
                        }
                        
                        // Guardar como WebP con calidad 80
                        if (imagewebp($source, $targetPath, 80)) {
                            $imagenPath = 'assets/uploads/productos/' . $newFileName;
                        }
                        imagedestroy($source);
                    }
                }
            }
        }

        if ($id > 0) {
            if ($imagenPath) {
                $payload['imagen'] = $imagenPath;
                // Eliminar imagen anterior si existe
                $old = $producto->findById($id);
                if ($old && $old['imagen'] && file_exists(__DIR__ . '/../../' . $old['imagen'])) {
                    unlink(__DIR__ . '/../../' . $old['imagen']);
                }
            }
            $producto->update($id, $payload);
            json_out(200, ['ok' => true, 'message' => 'Producto actualizado.']);
        }
        
        if ($imagenPath) {
            $payload['imagen'] = $imagenPath;
        }
        $newId = $producto->create($payload);
        json_out(201, ['ok' => true, 'message' => 'Producto creado.', 'id' => $newId]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => 'Error guardando producto.']);
    }
}

if ($action === 'bulk_save') {
    require_csrf();
    try {
        $data = json_decode($_POST['data'] ?? '[]', true);
        if (!is_array($data)) {
            json_out(400, ['ok' => false, 'message' => 'Datos inválidos.']);
        }

        $created = 0;
        $updated = 0;

        foreach ($data as $item) {
            $nombre = trim((string)($item['nombre'] ?? ''));
            if ($nombre === '') continue;

            $precioCompra1 = (float)($item['precio_compra1'] ?? ($item['precio_compra'] ?? 0));
            $precioCompra2 = (float)($item['precio_compra2'] ?? 0);
            $precioVenta = (float)($item['precio_venta'] ?? 0);
            $cantidad = (float)($item['stock_actual'] ?? 0);

            // Buscar si ya existe por nombre exacto (case insensitive)
            $existente = $producto->findByName($nombre);

            if ($existente) {
                // Actualizar stock
                $nuevoStock = (float)$existente['stock_actual'] + $cantidad;
                $producto->update((int)$existente['id'], [
                    'nombre' => $existente['nombre'],
                    'precio_compra1' => $precioCompra1 > 0 ? $precioCompra1 : $existente['precio_compra1'],
                    'precio_compra2' => $precioCompra2 > 0 ? $precioCompra2 : $existente['precio_compra2'],
                    'precio_venta' => $precioVenta > 0 ? $precioVenta : $existente['precio_venta'],
                    'stock_actual' => $nuevoStock,
                    'stock_minimo' => $existente['stock_minimo'],
                    'estado' => $existente['estado'],
                ]);
                $updated++;
            } else {
                // Crear nuevo
                $producto->create([
                    'nombre' => $nombre,
                    'precio_compra1' => $precioCompra1,
                    'precio_compra2' => $precioCompra2,
                    'precio_venta' => $precioVenta,
                    'stock_actual' => $cantidad,
                    'stock_minimo' => 5,
                    'estado' => 1,
                ]);
                $created++;
            }
        }

        json_out(200, [
            'ok' => true, 
            'message' => "Proceso completado. Creados: $created, Actualizados: $updated",
            'created' => $created,
            'updated' => $updated
        ]);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'message' => 'Error en guardado masivo: ' . $e->getMessage()]);
    }
}

json_out(400, ['ok' => false, 'message' => 'Acción inválida.']);

