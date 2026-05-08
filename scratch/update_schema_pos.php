<?php
require 'config/db.php';
require 'models/Database.php';
$db = (new Database())->getConnection();
try {
    $db->exec("ALTER TABLE ventas 
        ADD COLUMN pago_efectivo DECIMAL(12,2) DEFAULT 0, 
        ADD COLUMN pago_qr DECIMAL(12,2) DEFAULT 0, 
        ADD COLUMN metodo_pago VARCHAR(50) DEFAULT 'efectivo'");
    echo "Schema updated successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
