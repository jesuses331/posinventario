<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';

Auth::checkAccess('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_out_cfg(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function require_csrf_cfg(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_out_cfg(403, ['ok' => false, 'message' => 'CSRF inválido.']);
    }
}

function get_config_columns(PDO $db): array {
    $cols = [];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM configuracion");
        foreach (($stmt->fetchAll() ?: []) as $row) {
            $name = (string)($row['Field'] ?? '');
            if ($name !== '') {
                $cols[$name] = true;
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    return $cols;
}

function get_kv_schema(array $cols): array {
    $keyCandidates = ['clave', 'config_key', 'llave', 'nombre', 'key', 'parametro', 'codigo'];
    $valueCandidates = ['valor', 'config_value', 'value', 'dato', 'contenido'];
    $idCandidates = ['id', 'config_id'];

    $keyCol = null;
    foreach ($keyCandidates as $c) {
        if (isset($cols[$c])) {
            $keyCol = $c;
            break;
        }
    }

    $valueCol = null;
    foreach ($valueCandidates as $c) {
        if (isset($cols[$c])) {
            $valueCol = $c;
            break;
        }
    }

    $idCol = null;
    foreach ($idCandidates as $c) {
        if (isset($cols[$c])) {
            $idCol = $c;
            break;
        }
    }

    return [
        'key' => $keyCol,
        'value' => $valueCol,
        'id' => $idCol,
        'is_kv' => $keyCol !== null && $valueCol !== null,
    ];
}

function qi(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

try {
    $db = (new Database())->getConnection();
} catch (Throwable $e) {
    json_out_cfg(500, ['ok' => false, 'message' => 'Error de conexión.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

if ($action === 'get') {
    $cols = get_config_columns($db);
    if (!$cols) {
        json_out_cfg(500, ['ok' => false, 'message' => 'No se encontró la tabla de configuración.']);
    }

    $kv = get_kv_schema($cols);
    if ($kv['is_kv']) {
        $stmt = $db->query("SELECT " . qi($kv['key']) . " AS cfg_key, " . qi($kv['value']) . " AS cfg_value FROM configuracion");
        $pairs = $stmt->fetchAll() ?: [];
        $map = [];
        foreach ($pairs as $p) {
            $k = strtolower(trim((string)($p['cfg_key'] ?? '')));
            if ($k !== '') {
                $map[$k] = $p['cfg_value'];
            }
        }

        $row = [
            'id' => null,
            'nombre_negocio' => (string)($map['nombre_negocio'] ?? $map['negocio'] ?? 'AbdiSoft POS'),
            'moneda' => (string)($map['moneda'] ?? 'Bs'),
            'usa_vencimientos' => (int)($map['usa_vencimientos'] ?? $map['vencimientos'] ?? 1) === 1 ? 1 : 0,
            'logo_path' => (string)($map['logo_path'] ?? ''),
            'plan_sistema' => (string)($map['plan_sistema'] ?? 'demo'),
            'fecha_inicio_plan' => (string)($map['fecha_inicio_plan'] ?? ''),
        ];
    } else {
        $orderBy = isset($cols['updated_at']) ? 'updated_at DESC' : (isset($cols['id']) ? 'id DESC' : '1');



        $sql = sprintf(
            "SELECT %s AS id, %s AS nombre_negocio, %s AS moneda, %s AS usa_vencimientos, %s AS logo_path, %s AS plan_sistema, %s AS fecha_inicio_plan FROM configuracion ORDER BY %s LIMIT 1",
            isset($cols['id']) ? 'id' : 'NULL',
            isset($cols['nombre_negocio']) ? 'nombre_negocio' : "'AbdiSoft POS'",
            isset($cols['moneda']) ? 'moneda' : "'Bs'",
            isset($cols['usa_vencimientos']) ? 'usa_vencimientos' : '1',
            isset($cols['logo_path']) ? 'logo_path' : "''",
            isset($cols['plan_sistema']) ? 'plan_sistema' : "'demo'",
            isset($cols['fecha_inicio_plan']) ? 'fecha_inicio_plan' : "NULL",
            $orderBy
        );

        $stmt = $db->query($sql);
        $row = $stmt->fetch();
        if (!$row) {
            $row = [
                'id' => null,
                'nombre_negocio' => 'AbdiSoft POS',
                'moneda' => 'Bs',
                'usa_vencimientos' => 1,
            ];
        }
    }
    json_out_cfg(200, ['ok' => true, 'data' => $row]);
}

if ($action === 'save') {
    require_csrf_cfg();

    $cols = get_config_columns($db);
    if (!$cols) {
        json_out_cfg(500, ['ok' => false, 'message' => 'No se encontró la tabla de configuración.']);
    }

    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nombre = trim((string)($_POST['nombre_negocio'] ?? ''));
    $moneda = trim((string)($_POST['moneda'] ?? 'Bs'));
    $usaV = (int)($_POST['usa_vencimientos'] ?? 0) === 1 ? 1 : 0;
    $plan = trim((string)($_POST['plan_sistema'] ?? ''));
    
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/svg+xml'];
        if (!in_array($_FILES['logo']['type'], $allowed)) {
            json_out_cfg(422, ['ok' => false, 'message' => 'Tipo de imagen no permitido (JPG, PNG, SVG).']);
        }
        
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../assets/uploads/logos/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $target = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            $logoPath = 'assets/uploads/logos/' . $filename;
            
            // Eliminar logo anterior si existe
            $stmtOld = $db->query("SELECT logo_path FROM configuracion LIMIT 1");
            $old = $stmtOld->fetch();
            if ($old && !empty($old['logo_path'])) {
                $oldFile = __DIR__ . '/../../' . $old['logo_path'];
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }
    }

    if (isset($cols['nombre_negocio']) && $nombre === '') {
        json_out_cfg(422, ['ok' => false, 'message' => 'El nombre del negocio es obligatorio.']);
    }
    if ($moneda === '') $moneda = 'Bs';

    try {
        $kv = get_kv_schema($cols);
        if ($kv['is_kv']) {
            $items = [
                'nombre_negocio' => $nombre !== '' ? $nombre : 'AbdiSoft POS',
                'moneda' => $moneda,
                'usa_vencimientos' => (string)$usaV,
            ];
            if ($logoPath) $items['logo_path'] = $logoPath;
            if ($plan !== '' && ($_SESSION['user_usuario'] ?? '') === 'desarrollador') {
                $items['plan_sistema'] = $plan;
                $items['fecha_inicio_plan'] = date('Y-m-d');
            }

            foreach ($items as $k => $v) {
                // Usa INSERT ... ON DUPLICATE KEY UPDATE para evitar errores de clave única
                $stmtIn = $db->prepare(
                    "INSERT INTO configuracion (" . qi($kv['key']) . ", " . qi($kv['value']) . ") VALUES (:cfg_key, :val) " .
                    "ON DUPLICATE KEY UPDATE " . qi($kv['value']) . " = VALUES(" . qi($kv['value']) . ")"
                );
                $stmtIn->execute([':cfg_key' => $k, ':val' => $v]);
            }
            json_out_cfg(200, ['ok' => true, 'message' => 'Configuración guardada.']);
        }

        $set = [];
        $params = [];

        if (isset($cols['nombre_negocio'])) {
            $set[] = 'nombre_negocio = :nombre';
            $params[':nombre'] = $nombre;
        }
        if (isset($cols['moneda'])) {
            $set[] = 'moneda = :moneda';
            $params[':moneda'] = $moneda;
        }
        if (isset($cols['usa_vencimientos'])) {
            $set[] = 'usa_vencimientos = :usa';
            $params[':usa'] = $usaV;
        }
        if ($logoPath && isset($cols['logo_path'])) {
            $set[] = 'logo_path = :logo';
            $params[':logo'] = $logoPath;
        }
        if ($plan !== '' && isset($cols['plan_sistema']) && ($_SESSION['user_usuario'] ?? '') === 'desarrollador') {
            $set[] = 'plan_sistema = :plan';
            $params[':plan'] = $plan;
            if (isset($cols['fecha_inicio_plan'])) {
                $set[] = 'fecha_inicio_plan = :fecha_p';
                $params[':fecha_p'] = date('Y-m-d');
            }
        }

        if (!$set) {
            json_out_cfg(500, ['ok' => false, 'message' => 'La tabla configuración no tiene columnas compatibles.']);
        }

        $updated = false;
        if ($id && isset($cols['id'])) {
            $stmt = $db->prepare("UPDATE configuracion SET " . implode(', ', $set) . " WHERE id = :id LIMIT 1");
            $stmt->execute($params + [':id' => $id]);
            $updated = $stmt->rowCount() > 0;
        } else {
            if (isset($cols['id'])) {
                $stmtLast = $db->query("SELECT id FROM configuracion ORDER BY id DESC LIMIT 1");
                $lastId = (int)($stmtLast->fetch()['id'] ?? 0);
                if ($lastId > 0) {
                    $stmt = $db->prepare("UPDATE configuracion SET " . implode(', ', $set) . " WHERE id = :id LIMIT 1");
                    $stmt->execute($params + [':id' => $lastId]);
                    $updated = true;
                }
            } else {
                $stmt = $db->prepare("UPDATE configuracion SET " . implode(', ', $set) . " LIMIT 1");
                $stmt->execute($params);
                $updated = $stmt->rowCount() > 0;
            }
        }

        if (!$updated) {
            $insertCols = [];
            $insertVals = [];
            $insertParams = [];
            if (isset($cols['nombre_negocio'])) {
                $insertCols[] = 'nombre_negocio';
                $insertVals[] = ':nombre';
                $insertParams[':nombre'] = $nombre;
            }
            if (isset($cols['moneda'])) {
                $insertCols[] = 'moneda';
                $insertVals[] = ':moneda';
                $insertParams[':moneda'] = $moneda;
            }
            if (isset($cols['usa_vencimientos'])) {
                $insertCols[] = 'usa_vencimientos';
                $insertVals[] = ':usa';
                $insertParams[':usa'] = $usaV;
            }
            if ($logoPath && isset($cols['logo_path'])) {
                $insertCols[] = 'logo_path';
                $insertVals[] = ':logo';
                $insertParams[':logo'] = $logoPath;
            }

            if ($insertCols) {
                $stmt = $db->prepare(
                    "INSERT INTO configuracion (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")"
                );
                $stmt->execute($insertParams);
            }
        }
        json_out_cfg(200, ['ok' => true, 'message' => 'Configuración guardada.', 'logo_path' => $logoPath]);
    } catch (Throwable $e) {
        json_out_cfg(500, ['ok' => false, 'message' => 'Error guardando configuración: ' . $e->getMessage()]);
    }
}

json_out_cfg(400, ['ok' => false, 'message' => 'Acción inválida.']);

