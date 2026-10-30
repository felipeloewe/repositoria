-- CreateTable
CREATE TABLE `fiscalizacion_solicitudes` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `departamentoId` VARCHAR(191) NULL,
    `sucursalId` VARCHAR(191) NULL,
    `tipo` ENUM('LIQUIDACION', 'CONTRATO') NOT NULL,
    `titulo` VARCHAR(191) NOT NULL,
    `motivo` TEXT NULL,
    `estado` ENUM('BORRADOR', 'PENDIENTE', 'APROBADA', 'RECHAZADA', 'ENVIADA') NOT NULL DEFAULT 'BORRADOR',
    `solicitadoPorId` VARCHAR(191) NOT NULL,
    `revisadoPorId` VARCHAR(191) NULL,
    `revisadoAt` DATETIME(3) NULL,
    `comentarioRevision` TEXT NULL,
    `enviadoAt` DATETIME(3) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `fiscalizacion_solicitudes_empresaId_estado_idx`(`empresaId`, `estado`),
    INDEX `fiscalizacion_solicitudes_solicitadoPorId_idx`(`solicitadoPorId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `fiscalizacion_documentos` (
    `id` VARCHAR(191) NOT NULL,
    `solicitudId` VARCHAR(191) NOT NULL,
    `documentoId` VARCHAR(191) NOT NULL,

    INDEX `fiscalizacion_documentos_documentoId_idx`(`documentoId`),
    UNIQUE INDEX `fiscalizacion_documentos_solicitudId_documentoId_key`(`solicitudId`, `documentoId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `fiscalizacion_auditoria` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `solicitudId` VARCHAR(191) NOT NULL,
    `actorId` VARCHAR(191) NOT NULL,
    `accion` VARCHAR(191) NOT NULL,
    `detalle` TEXT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX `fiscalizacion_auditoria_empresaId_createdAt_idx`(`empresaId`, `createdAt`),
    INDEX `fiscalizacion_auditoria_solicitudId_idx`(`solicitudId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `canal_paquetes` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `titulo` VARCHAR(191) NOT NULL,
    `descripcion` TEXT NULL,
    `destinatarioId` VARCHAR(191) NOT NULL,
    `creadoPorId` VARCHAR(191) NOT NULL,
    `estado` ENUM('ACTIVO', 'REVOCADO') NOT NULL DEFAULT 'ACTIVO',
    `expiraAt` DATETIME(3) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `canal_paquetes_empresaId_estado_idx`(`empresaId`, `estado`),
    INDEX `canal_paquetes_destinatarioId_idx`(`destinatarioId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `canal_paquete_documentos` (
    `id` VARCHAR(191) NOT NULL,
    `paqueteId` VARCHAR(191) NOT NULL,
    `documentoId` VARCHAR(191) NOT NULL,

    INDEX `canal_paquete_documentos_documentoId_idx`(`documentoId`),
    UNIQUE INDEX `canal_paquete_documentos_paqueteId_documentoId_key`(`paqueteId`, `documentoId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `canal_paquete_auditoria` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `paqueteId` VARCHAR(191) NOT NULL,
    `actorId` VARCHAR(191) NOT NULL,
    `accion` VARCHAR(191) NOT NULL,
    `detalle` TEXT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX `canal_paquete_auditoria_empresaId_createdAt_idx`(`empresaId`, `createdAt`),
    INDEX `canal_paquete_auditoria_paqueteId_idx`(`paqueteId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `fiscalizacion_solicitudes` ADD CONSTRAINT `fiscalizacion_solicitudes_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_solicitudes` ADD CONSTRAINT `fiscalizacion_solicitudes_departamentoId_fkey` FOREIGN KEY (`departamentoId`) REFERENCES `departamentos`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_solicitudes` ADD CONSTRAINT `fiscalizacion_solicitudes_sucursalId_fkey` FOREIGN KEY (`sucursalId`) REFERENCES `sucursales`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_solicitudes` ADD CONSTRAINT `fiscalizacion_solicitudes_solicitadoPorId_fkey` FOREIGN KEY (`solicitadoPorId`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_solicitudes` ADD CONSTRAINT `fiscalizacion_solicitudes_revisadoPorId_fkey` FOREIGN KEY (`revisadoPorId`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `fiscalizacion_documentos` ADD CONSTRAINT `fiscalizacion_documentos_solicitudId_fkey` FOREIGN KEY (`solicitudId`) REFERENCES `fiscalizacion_solicitudes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_documentos` ADD CONSTRAINT `fiscalizacion_documentos_documentoId_fkey` FOREIGN KEY (`documentoId`) REFERENCES `documentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `fiscalizacion_auditoria` ADD CONSTRAINT `fiscalizacion_auditoria_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_auditoria` ADD CONSTRAINT `fiscalizacion_auditoria_solicitudId_fkey` FOREIGN KEY (`solicitudId`) REFERENCES `fiscalizacion_solicitudes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `fiscalizacion_auditoria` ADD CONSTRAINT `fiscalizacion_auditoria_actorId_fkey` FOREIGN KEY (`actorId`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `canal_paquetes` ADD CONSTRAINT `canal_paquetes_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `canal_paquetes` ADD CONSTRAINT `canal_paquetes_destinatarioId_fkey` FOREIGN KEY (`destinatarioId`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `canal_paquetes` ADD CONSTRAINT `canal_paquetes_creadoPorId_fkey` FOREIGN KEY (`creadoPorId`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `canal_paquete_documentos` ADD CONSTRAINT `canal_paquete_documentos_paqueteId_fkey` FOREIGN KEY (`paqueteId`) REFERENCES `canal_paquetes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `canal_paquete_documentos` ADD CONSTRAINT `canal_paquete_documentos_documentoId_fkey` FOREIGN KEY (`documentoId`) REFERENCES `documentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `canal_paquete_auditoria` ADD CONSTRAINT `canal_paquete_auditoria_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `canal_paquete_auditoria` ADD CONSTRAINT `canal_paquete_auditoria_paqueteId_fkey` FOREIGN KEY (`paqueteId`) REFERENCES `canal_paquetes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `canal_paquete_auditoria` ADD CONSTRAINT `canal_paquete_auditoria_actorId_fkey` FOREIGN KEY (`actorId`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
