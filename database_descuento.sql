-- Migration: Add descuento column to ventas table
-- Run this to enable global discount tracking on sales (fixed amount, not percentage)

ALTER TABLE ventas ADD COLUMN descuento DECIMAL(12,2) DEFAULT 0.00 AFTER cliente_id;

-- Update existing records to have 0 discount
UPDATE ventas SET descuento = 0 WHERE descuento IS NULL;
