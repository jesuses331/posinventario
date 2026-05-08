<?php
// 1. Incluimos las constantes
include("../config/db.php");

// 2. CREAR LA CONEXIÓN (Como db.php solo tiene defines, la creamos aquí)
$conexion = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conexion) {
    http_response_code(500);
    die(json_encode(["error" => "Error de conexión: " . mysqli_connect_error()]));
}

mysqli_set_charset($conexion, DB_CHAR);

// 3. CAPTURAR TOKEN (Basado en tu log exitoso)
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$token_recibido = "";

if (isset($headers['authorization'])) {
    // Limpiamos "Bearer " (case insensitive) para obtener solo el token
    $token_recibido = trim(str_ireplace('bearer ', '', $headers['authorization']));
}

$token_esperado = "abdisoftpos";

if ($token_recibido !== $token_esperado) {
    http_response_code(401);
    die(json_encode([
        "error" => "No autorizado",
        "recibi" => $token_recibido
    ]));
}

// 4. LEER DATOS DEL CUERPO
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if ($data) {
    $plan = mysqli_real_escape_string($conexion, $data['plan_sistema']);
    $fecha = mysqli_real_escape_string($conexion, $data['fecha_inicio_plan']);
    $nombre = mysqli_real_escape_string($conexion, $data['nombre_negocio']);

    // 5. ACTUALIZAR (Usando las variables que llegan del Core)
    $query = "UPDATE configuracion SET 
              plan_sistema = '$plan', 
              fecha_inicio_plan = '$fecha',
              nombre_negocio = '$nombre'
              WHERE id = 1";

    if (mysqli_query($conexion, $query)) {
        echo json_encode(["status" => "success", "message" => "Sincronizado en demopos"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => mysqli_error($conexion)]);
    }
} else {
    echo json_encode(["error" => "JSON no válido o vacío"]);
}

// Opcional: Cerrar conexión
mysqli_close($conexion);