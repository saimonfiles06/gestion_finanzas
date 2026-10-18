ALTER TABLE bancos
    ADD COLUMN eliminado TINYINT(1) NOT NULL DEFAULT 0
    AFTER permite_gastos;
