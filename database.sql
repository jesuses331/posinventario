-- Crear Base de Datos
CREATE DATABASE IF NOT EXISTS abdisoft_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abdisoft_core;

-- Tabla: usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'cajero') NOT NULL DEFAULT 'cajero',
    estado TINYINT(1) NOT NULL DEFAULT 1, -- 1: Activo, 0: Inactivo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla: configuracion
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_negocio VARCHAR(150) NOT NULL,
    moneda VARCHAR(10) DEFAULT 'Bs',
    usa_vencimientos BOOLEAN DEFAULT FALSE,
    logo_path VARCHAR(255) NULL,
    plan_sistema VARCHAR(50) DEFAULT 'demo',
    fecha_inicio_plan DATE NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla: clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    cedula VARCHAR(20) UNIQUE NULL,
    telefono VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    direccion VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla: productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    precio_compra DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    precio_venta DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 5,
    factor_conversion INT NOT NULL DEFAULT 1,
    fecha_vencimiento DATE NULL,
    lote VARCHAR(50) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla: ventas
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    usuario_id INT NOT NULL,
    cliente_id INT NULL,
    estado_pago ENUM('pendiente', 'cobrado') NOT NULL DEFAULT 'cobrado',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla: ventas_detalle
CREATE TABLE IF NOT EXISTS ventas_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- Datos de Prueba Iniciales
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES 
('Administrador', 'admin@abdisoft.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'); 
-- Password: password (por defecto)

INSERT INTO configuracion (nombre_negocio, moneda, usa_vencimientos) VALUES 
('AbdiSoft POS', 'Bs', TRUE);
