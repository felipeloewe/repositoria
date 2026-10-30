-- Invert hierarchy: Empresa → Departamentos → Sucursales
-- Add Actividad / TemaInteres tags on Departamento
-- Link DocumentoSeccion / Documento to Departamento

-- Wipe demo rows that cannot map cleanly (seed recreates).
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `documento_autorizaciones`;
DELETE FROM `documentos`;
DELETE FROM `documento_secciones`;
DELETE FROM `expediente_autorizaciones`;
DELETE FROM `expedientes_stub`;
DELETE FROM `membresias`;
DELETE FROM `departamentos`;
DELETE FROM `sucursales`;
SET FOREIGN_KEY_CHECKS = 1;

-- Departamentos: drop sucursal FK / unique / column; unique by empresa+nombre
ALTER TABLE `departamentos` DROP FOREIGN KEY `departamentos_sucursalId_fkey`;
ALTER TABLE `departamentos` DROP INDEX `departamentos_sucursalId_nombre_key`;
ALTER TABLE `departamentos` DROP INDEX `departamentos_sucursalId_idx`;
ALTER TABLE `departamentos` DROP COLUMN `sucursalId`;
CREATE UNIQUE INDEX `departamentos_empresaId_nombre_key` ON `departamentos`(`empresaId`, `nombre`);

-- Sucursales: under Departamento; unique by departamento+nombre
ALTER TABLE `sucursales` DROP INDEX `sucursales_empresaId_nombre_key`;
ALTER TABLE `sucursales` ADD COLUMN `departamentoId` VARCHAR(191) NOT NULL;
CREATE UNIQUE INDEX `sucursales_departamentoId_nombre_key` ON `sucursales`(`departamentoId`, `nombre`);
CREATE INDEX `sucursales_departamentoId_idx` ON `sucursales`(`departamentoId`);
ALTER TABLE `sucursales` ADD CONSTRAINT `sucursales_departamentoId_fkey` FOREIGN KEY (`departamentoId`) REFERENCES `departamentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Actividades (tag on Departamento)
CREATE TABLE `actividades` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `departamentoId` VARCHAR(191) NOT NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `actividades_departamentoId_nombre_key`(`departamentoId`, `nombre`),
    INDEX `actividades_empresaId_idx`(`empresaId`),
    INDEX `actividades_departamentoId_idx`(`departamentoId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `actividades` ADD CONSTRAINT `actividades_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `actividades` ADD CONSTRAINT `actividades_departamentoId_fkey` FOREIGN KEY (`departamentoId`) REFERENCES `departamentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Temas de Interés (tag on Departamento; independent of Actividades)
CREATE TABLE `temas_interes` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `departamentoId` VARCHAR(191) NOT NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `temas_interes_departamentoId_nombre_key`(`departamentoId`, `nombre`),
    INDEX `temas_interes_empresaId_idx`(`empresaId`),
    INDEX `temas_interes_departamentoId_idx`(`departamentoId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `temas_interes` ADD CONSTRAINT `temas_interes_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `temas_interes` ADD CONSTRAINT `temas_interes_departamentoId_fkey` FOREIGN KEY (`departamentoId`) REFERENCES `departamentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- DocumentoSeccion: require departamentoId; unique per depto+sucursal+codigo
ALTER TABLE `documento_secciones` DROP INDEX `documento_secciones_empresaId_sucursalId_codigo_key`;
ALTER TABLE `documento_secciones` ADD COLUMN `departamentoId` VARCHAR(191) NOT NULL;
CREATE UNIQUE INDEX `documento_secciones_departamentoId_sucursalId_codigo_key` ON `documento_secciones`(`departamentoId`, `sucursalId`, `codigo`);
CREATE INDEX `documento_secciones_departamentoId_idx` ON `documento_secciones`(`departamentoId`);
ALTER TABLE `documento_secciones` ADD CONSTRAINT `documento_secciones_departamentoId_fkey` FOREIGN KEY (`departamentoId`) REFERENCES `departamentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Documento: require departamentoId
ALTER TABLE `documentos` ADD COLUMN `departamentoId` VARCHAR(191) NOT NULL;
CREATE INDEX `documentos_departamentoId_idx` ON `documentos`(`departamentoId`);
ALTER TABLE `documentos` ADD CONSTRAINT `documentos_departamentoId_fkey` FOREIGN KEY (`departamentoId`) REFERENCES `departamentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
