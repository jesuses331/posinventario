-- Migration: Cotizaciones (Quotes) support
-- Run this SQL to add cotizaciones and cotizaciones_detalle tables

CREATE TABLE IF NOT EXISTS cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL,
    cliente_id INT NULL,
    usuario_id INT NOT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descuento_global DECIMAL(5,2) DEFAULT 0.00,
    notas TEXT,
    estado ENUM('activa','aceptada','vencida','cancelada') DEFAULT 'activa',
    fecha_validez DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cotizaciones_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id INT NOT NULL,
    producto_id INT NULL,
    codigo VARCHAR(50),
    nombre VARCHAR(255),
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(5,2) DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
