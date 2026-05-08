-- Migración para Arqueo de Caja

-- 1. Crear tabla de cajas
CREATE TABLE IF NOT EXISTS cajas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha_apertura DATETIME NOT NULL,
    fecha_cierre DATETIME NULL,
    monto_inicial DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_efectivo DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_qr DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    monto_final_sistema DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    monto_final_real DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    diferencia DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    estado ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 2. Modificar tabla de ventas para incluir id_caja
ALTER TABLE ventas ADD COLUMN id_caja INT NULL AFTER usuario_id;
ALTER TABLE ventas ADD CONSTRAINT fk_ventas_caja FOREIGN KEY (id_caja) REFERENCES cajas(id);

-- 3. Crear tabla de gastos_extra
CREATE TABLE IF NOT EXISTS gastos_extra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_caja INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    monto DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_caja) REFERENCES cajas(id)
) ENGINE=InnoDB;
