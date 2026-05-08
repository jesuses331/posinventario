<?php
try {
    $drivers = PDO::getAvailableDrivers();
    echo "Drivers disponibles: " . implode(", ", $drivers) . "\n";
    if (in_array('mysql', $drivers)) {
        echo "MySQL driver is OK.\n";
    } else {
        echo "MySQL driver is MISSING.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
