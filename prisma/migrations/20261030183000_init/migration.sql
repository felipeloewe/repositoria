-- CreateTable
CREATE TABLE `empresas` (
    `id` VARCHAR(191) NOT NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `activa` BOOLEAN NOT NULL DEFAULT true,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `empresas_slug_key`(`slug`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `sucursales` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `codigo` VARCHAR(191) NULL,
    `activa` BOOLEAN NOT NULL DEFAULT true,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `sucursales_empresaId_idx`(`empresaId`),
    UNIQUE INDEX `sucursales_empresaId_nombre_key`(`empresaId`, `nombre`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `departamentos` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `sucursalId` VARCHAR(191) NOT NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `departamentos_empresaId_idx`(`empresaId`),
    INDEX `departamentos_sucursalId_idx`(`sucursalId`),
    UNIQUE INDEX `departamentos_sucursalId_nombre_key`(`sucursalId`, `nombre`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `usuarios` (
    `id` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `nombre` VARCHAR(191) NOT NULL,
    `passwordHash` VARCHAR(191) NULL,
    `activo` BOOLEAN NOT NULL DEFAULT true,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `usuarios_email_key`(`email`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `membresias` (
    `id` VARCHAR(191) NOT NULL,
    `empresaId` VARCHAR(191) NOT NULL,
    `usuarioId` VARCHAR(191) NOT NULL,
    `rol` ENUM('ADMINISTRADOR_MASTER', 'JEFE_SUCURSAL', 'CONTADOR', 'ABOGADO', 'PREVENCIONISTA') NOT NULL,
    `sucursalId` VARCHAR(191) NULL,
    `activa` BOOLEAN NOT NULL DEFAULT true,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    INDEX `membresias_empresaId_idx`(`empresaId`),
    INDEX `membresias_usuarioId_idx`(`usuarioId`),
    INDEX `membresias_sucursalId_idx`(`sucursalId`),
    UNIQUE INDEX `membresias_empresaId_usuarioId_rol_key`(`empresaId`, `usuarioId`, `rol`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `sucursales` ADD CONSTRAINT `sucursales_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `departamentos` ADD CONSTRAINT `departamentos_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `departamentos` ADD CONSTRAINT `departamentos_sucursalId_fkey` FOREIGN KEY (`sucursalId`) REFERENCES `sucursales`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `membresias` ADD CONSTRAINT `membresias_empresaId_fkey` FOREIGN KEY (`empresaId`) REFERENCES `empresas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `membresias` ADD CONSTRAINT `membresias_usuarioId_fkey` FOREIGN KEY (`usuarioId`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `membresias` ADD CONSTRAINT `membresias_sucursalId_fkey` FOREIGN KEY (`sucursalId`) REFERENCES `sucursales`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

