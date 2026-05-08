<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'posinventario');
define('DB_USER', 'root');
define('DB_PASS', ''); // Ajustar según tu configuración
define('DB_CHAR', 'utf8mb4');

// URL Base del proyecto (Ajustar según tu entorno)
define('BASE_URL', 'http://localhost/POSInventario/');

date_default_timezone_set('America/La_Paz');

// 2. Definir configuración regional para idioma (nombres de meses y días en español)
setlocale(LC_TIME, 'es_BO.UTF-8', 'esp');

// Opcional: Crear una constante por si la necesitas en otros procesos
define('TIMEZONE', 'America/La_Paz');
