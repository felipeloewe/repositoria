-- Complementary tax ID attributes (not ACL primary keys)
ALTER TABLE `empresas` ADD COLUMN `rut` VARCHAR(191) NULL;
ALTER TABLE `usuarios` ADD COLUMN `rut` VARCHAR(191) NULL;

CREATE INDEX `empresas_rut_idx` ON `empresas`(`rut`);
CREATE INDEX `usuarios_rut_idx` ON `usuarios`(`rut`);

-- One membership per (usuarioId, empresaId)
DROP INDEX `membresias_empresaId_usuarioId_rol_key` ON `membresias`;
CREATE UNIQUE INDEX `membresias_usuarioId_empresaId_key` ON `membresias`(`usuarioId`, `empresaId`);
