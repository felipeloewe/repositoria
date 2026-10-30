-- CreateTable
CREATE TABLE `documento_secciones` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `sucursalId` VARCHAR(191) NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `codigo` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `documento_secciones_empresaId_idx`(`empresaId`),
    INDEX `documento_secciones_sucursalId_idx`(`sucursalId`),
    UNIQUE INDEX `documento_secciones_empresaId_sucursalId_codigo_key`(`empresaId`, `sucursalId`, `codigo`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `documentos` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `seccionId` VARCHAR(191) NOT NULL,
    `sucursalId` VARCHAR(191) NULL,
    `titulo` VARCHAR(191) NOT NULL,
    `nombreArchivo` VARCHAR(191) NOT NULL,
    `mimeType` VARCHAR(191) NOT NULL,
    `tamanioBytes` INTEGER NOT NULL,
    `storageKey` VARCHAR(191) NOT NULL,
    `subidoPorId` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `documentos_empresaId_idx`(`empresaId`),
    INDEX `documentos_seccionId_idx`(`seccionId`),
    INDEX `documentos_sucursalId_idx`(`sucursalId`),
    INDEX `documentos_subidoPorId_idx`(`subidoPorId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `documento_autorizaciones` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `documentoId` VARCHAR(191) NOT NULL,
    `usuarioId` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX `documento_autorizaciones_empresaId_idx`(`empresaId`),
    INDEX `documento_autorizaciones_usuarioId_idx`(`usuarioId`),
    UNIQUE INDEX `documento_autorizaciones_documentoId_usuarioId_key`(`documentoId`, `usuarioId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `documento_secciones` ADD CONSTRAINT `documento_secciones_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documento_secciones` ADD CONSTRAINT `documento_secciones_sucursalId_fkey` FOREIGN KEY (`sucursalId`) REFERENCES `sucursales`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documentos` ADD CONSTRAINT `documentos_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documentos` ADD CONSTRAINT `documentos_seccionId_fkey` FOREIGN KEY (`seccionId`) REFERENCES `documento_secciones`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documentos` ADD CONSTRAINT `documentos_sucursalId_fkey` FOREIGN KEY (`sucursalId`) REFERENCES `sucursales`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documentos` ADD CONSTRAINT `documentos_subidoPorId_fkey` FOREIGN KEY (`subidoPorId`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documento_autorizaciones` ADD CONSTRAINT `documento_autorizaciones_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documento_autorizaciones` ADD CONSTRAINT `documento_autorizaciones_documentoId_fkey` FOREIGN KEY (`documentoId`) REFERENCES `documentos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `documento_autorizaciones` ADD CONSTRAINT `documento_autorizaciones_usuarioId_fkey` FOREIGN KEY (`usuarioId`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
