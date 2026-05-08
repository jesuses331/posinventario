<?php

class Caja
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Abre una nueva caja para el usuario.
     */
    public function abrir(int $idUsuario, float $montoInicial): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cajas (id_usuario, fecha_apertura, monto_inicial, estado)
            VALUES (:id_usuario, NOW(), :monto_inicial, 'abierta')
        ");
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':monto_inicial' => $montoInicial
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Obtiene la caja abierta actual de un usuario.
     */
    public function obtenerEstado(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM cajas 
            WHERE id_usuario = :id_usuario AND estado = 'abierta' 
            LIMIT 1
        ");
        $stmt->execute([':id_usuario' => $idUsuario]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Realiza el cierre de caja.
     */
    public function cerrar(int $idCaja, float $montoFinalReal): bool
    {
        // 1. Obtener monto inicial
        $stmt = $this->db->prepare("SELECT monto_inicial FROM cajas WHERE id = :id");
        $stmt->execute([':id' => $idCaja]);
        $caja = $stmt->fetch();
        $montoInicial = $caja['monto_inicial'] ?? 0;

        // 2. SUM(pago_efectivo) y SUM(pago_qr) de ventas vinculadas a esta caja
        $stmtVentas = $this->db->prepare("SELECT SUM(pago_efectivo) as total_efectivo, SUM(pago_qr) as total_qr FROM ventas WHERE id_caja = :id_caja");
        $stmtVentas->execute([':id_caja' => $idCaja]);
        $ventas = $stmtVentas->fetch();
        $totalEfectivo = $ventas['total_efectivo'] ?? 0;
        $totalQr = $ventas['total_qr'] ?? 0;

        // 3. SUM(monto) de gastos_extra vinculados a esta caja
        $stmtGastos = $this->db->prepare("SELECT SUM(monto) as total_gastos FROM gastos_extra WHERE id_caja = :id_caja");
        $stmtGastos->execute([':id_caja' => $idCaja]);
        $gastos = $stmtGastos->fetch();
        $totalGastos = $gastos['total_gastos'] ?? 0;

        // 4. Cálculo: (monto_inicial + ventas_efectivo + ventas_qr) - gastos = monto_final_sistema
        $montoFinalSistema = ($montoInicial + $totalEfectivo + $totalQr) - $totalGastos;
        $diferencia = $montoFinalReal - $montoFinalSistema;

        // 5. Actualizar la tabla cajas
        $stmtUpdate = $this->db->prepare("
            UPDATE cajas SET 
                fecha_cierre = NOW(),
                total_efectivo = :total_efectivo,
                total_qr = :total_qr,
                monto_final_sistema = :monto_final_sistema,
                monto_final_real = :monto_final_real,
                diferencia = :diferencia,
                estado = 'cerrada'
            WHERE id = :id
        ");

        return $stmtUpdate->execute([
            ':total_efectivo' => $totalEfectivo,
            ':total_qr' => $totalQr,
            ':monto_final_sistema' => $montoFinalSistema,
            ':monto_final_real' => $montoFinalReal,
            ':diferencia' => $diferencia,
            ':id' => $idCaja
        ]);
    }
}
