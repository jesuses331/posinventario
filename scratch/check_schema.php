<?php
require 'config/db.php';
require 'models/Database.php';
$db = (new Database())->getConnection();
$s = $db->query('DESCRIBE ventas');
print_r($s->fetchAll(PDO::FETCH_ASSOC));
