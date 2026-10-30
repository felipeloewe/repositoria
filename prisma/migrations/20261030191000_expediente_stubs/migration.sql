-- CreateTable
CREATE TABLE `expedientes_stub` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `sucursalId` VARCHAR(191) NULL,
    `titulo` VARCHAR(191) NOT NULL,
    `tipo` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `expedientes_stub_empresaId_idx`(`empresaId`),
    INDEX `expedientes_stub_sucursalId_idx`(`sucursalId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `expediente_autorizaciones` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `expedienteId` VARCHAR(191) NOT NULL,
    `usuarioId` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX `expediente_autorizaciones_empresaId_idx`(`empresaId`),
    INDEX `expediente_autorizaciones_usuarioId_idx`(`usuarioId`),
    UNIQUE INDEX `expediente_autorizaciones_expedienteId_usuarioId_key`(`expedienteId`, `usuarioId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `expedientes_stub` ADD CONSTRAINT `expedientes_stub_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `expedientes_stub` ADD CONSTRAINT `expedientes_stub_sucursalId_fkey` FOREIGN KEY (`sucursalId`) REFERENCES `sucursales`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `expediente_autorizaciones` ADD CONSTRAINT `expediente_autorizaciones_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `expediente_autorizaciones` ADD CONSTRAINT `expediente_autorizaciones_expedienteId_fkey` FOREIGN KEY (`expedienteId`) REFERENCES `expedientes_stub`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `expediente_autorizaciones` ADD CONSTRAINT `expediente_autorizaciones_usuarioId_fkey` FOREIGN KEY (`usuarioId`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

