<?php
class Compra {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO compras (total, usuario_id) 
            VALUES (:total, :usuario_id)
        ");
        $stmt->execute([
            ':total' => $config['total'] ?? 0,
            ':usuario_id' => $data['usuario_id']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function createDetail(array $data): void {
        $stmt = $this->db->prepare("
            INSERT INTO compras_detalle 
                (compra_id, producto_id, cantidad, precio_compra1, precio_compra2, precio_venta, subtotal)
            VALUES 
                (:compra_id, :producto_id, :cantidad, :p1, :p2, :venta, :subtotal)
        ");
        $stmt->execute([
            ':compra_id' => $data['compra_id'],
            ':producto_id' => $data['producto_id'],
            ':cantidad' => $data['cantidad'],
            ':p1' => $data['precio_compra1'],
            ':p2' => $data['precio_compra2'],
            ':venta' => $data['precio_venta'],
            ':subtotal' => $data['subtotal']
        ]);
    }
}
