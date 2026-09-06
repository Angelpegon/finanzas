/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP DATABASE IF EXISTS finanzas;
CREATE DATABASE finanzas;
USE finanzas;

--
-- Table structure for table `asientos`
--

DROP TABLE IF EXISTS `asientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asientos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen_tipo` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen_id` bigint unsigned NOT NULL,
  `es_reverso` tinyint(1) NOT NULL DEFAULT '0',
  `asiento_reversado_id` bigint unsigned DEFAULT NULL,
  `hash_integridad` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asientos_origen_unico` (`usuario_id`,`origen_tipo`,`origen_id`),
  KEY `asientos_usuario_id_fecha_index` (`usuario_id`,`fecha`),
  KEY `asientos_origen_tipo_origen_id_index` (`origen_tipo`,`origen_id`),
  KEY `asientos_asiento_reversado_id_foreign` (`asiento_reversado_id`),
  CONSTRAINT `asientos_asiento_reversado_id_foreign` FOREIGN KEY (`asiento_reversado_id`) REFERENCES `asientos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asientos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asientos`
--

LOCK TABLES `asientos` WRITE;
/*!40000 ALTER TABLE `asientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `asientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `cuenta_contable_id` bigint unsigned NOT NULL,
  `padre_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categorias_cuenta_contable_id_foreign` (`cuenta_contable_id`),
  KEY `categorias_padre_id_foreign` (`padre_id`),
  KEY `categorias_usuario_id_tipo_index` (`usuario_id`,`tipo`),
  CONSTRAINT `categorias_cuenta_contable_id_foreign` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `categorias_padre_id_foreign` FOREIGN KEY (`padre_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `categorias_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ciclos_facturacion`
--

DROP TABLE IF EXISTS `ciclos_facturacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ciclos_facturacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `tarjeta_credito_id` bigint unsigned NOT NULL,
  `fecha_corte` date NOT NULL,
  `fecha_pago` date NOT NULL,
  `saldo_corte_centavos` bigint NOT NULL DEFAULT '0',
  `pago_minimo_centavos` bigint NOT NULL DEFAULT '0',
  `interes_centavos` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ciclos_facturacion_usuario_id_foreign` (`usuario_id`),
  KEY `ciclos_facturacion_tarjeta_credito_id_foreign` (`tarjeta_credito_id`),
  CONSTRAINT `ciclos_facturacion_tarjeta_credito_id_foreign` FOREIGN KEY (`tarjeta_credito_id`) REFERENCES `tarjetas_credito` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ciclos_facturacion_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ciclos_facturacion`
--

LOCK TABLES `ciclos_facturacion` WRITE;
/*!40000 ALTER TABLE `ciclos_facturacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `ciclos_facturacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras_tarjeta`
--

DROP TABLE IF EXISTS `compras_tarjeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras_tarjeta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `tarjeta_credito_id` bigint unsigned NOT NULL,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `monto_centavos` bigint NOT NULL,
  `cuotas` smallint unsigned NOT NULL,
  `tasa_interes_porcentaje` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `compras_tarjeta_tarjeta_credito_id_foreign` (`tarjeta_credito_id`),
  KEY `compras_tarjeta_categoria_id_foreign` (`categoria_id`),
  KEY `compras_tarjeta_usuario_id_index` (`usuario_id`),
  CONSTRAINT `compras_tarjeta_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `compras_tarjeta_tarjeta_credito_id_foreign` FOREIGN KEY (`tarjeta_credito_id`) REFERENCES `tarjetas_credito` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compras_tarjeta_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras_tarjeta`
--

LOCK TABLES `compras_tarjeta` WRITE;
/*!40000 ALTER TABLE `compras_tarjeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras_tarjeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas_contables`
--

DROP TABLE IF EXISTS `cuentas_contables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_contables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `codigo` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `naturaleza` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuentas_contables_usuario_id_codigo_unique` (`usuario_id`,`codigo`),
  KEY `cuentas_contables_usuario_id_naturaleza_index` (`usuario_id`,`naturaleza`),
  CONSTRAINT `cuentas_contables_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas_contables`
--

LOCK TABLES `cuentas_contables` WRITE;
/*!40000 ALTER TABLE `cuentas_contables` DISABLE KEYS */;
/*!40000 ALTER TABLE `cuentas_contables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas_liquidas`
--

DROP TABLE IF EXISTS `cuentas_liquidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_liquidas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `cuenta_contable_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institucion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_cuenta_enmascarado` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saldo_inicial_centavos` bigint NOT NULL DEFAULT '0',
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COP',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cuentas_liquidas_cuenta_contable_id_foreign` (`cuenta_contable_id`),
  KEY `cuentas_liquidas_usuario_id_index` (`usuario_id`),
  CONSTRAINT `cuentas_liquidas_cuenta_contable_id_foreign` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuentas_liquidas_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas_liquidas`
--

LOCK TABLES `cuentas_liquidas` WRITE;
/*!40000 ALTER TABLE `cuentas_liquidas` DISABLE KEYS */;
/*!40000 ALTER TABLE `cuentas_liquidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuotas_prestamo`
--

DROP TABLE IF EXISTS `cuotas_prestamo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuotas_prestamo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `prestamo_id` bigint unsigned NOT NULL,
  `numero` smallint unsigned NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `capital_centavos` bigint NOT NULL,
  `interes_centavos` bigint NOT NULL,
  `seguro_centavos` bigint NOT NULL DEFAULT '0',
  `otros_cargos_centavos` bigint NOT NULL DEFAULT '0',
  `total_centavos` bigint NOT NULL DEFAULT '0',
  `saldo_capital_centavos` bigint NOT NULL,
  `pagada` tinyint(1) NOT NULL DEFAULT '0',
  `pagada_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuotas_prestamo_prestamo_id_numero_unique` (`prestamo_id`,`numero`),
  KEY `cuotas_prestamo_usuario_id_fecha_vencimiento_index` (`usuario_id`,`fecha_vencimiento`),
  CONSTRAINT `cuotas_prestamo_prestamo_id_foreign` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuotas_prestamo_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuotas_prestamo`
--

LOCK TABLES `cuotas_prestamo` WRITE;
/*!40000 ALTER TABLE `cuotas_prestamo` DISABLE KEYS */;
/*!40000 ALTER TABLE `cuotas_prestamo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuotas_tarjeta`
--

DROP TABLE IF EXISTS `cuotas_tarjeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuotas_tarjeta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `compra_tarjeta_id` bigint unsigned NOT NULL,
  `tarjeta_credito_id` bigint unsigned NOT NULL,
  `numero` smallint unsigned NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `capital_centavos` bigint NOT NULL,
  `interes_centavos` bigint NOT NULL,
  `pagada` tinyint(1) NOT NULL DEFAULT '0',
  `pagada_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cuotas_tarjeta_compra_tarjeta_id_foreign` (`compra_tarjeta_id`),
  KEY `cuotas_tarjeta_tarjeta_credito_id_foreign` (`tarjeta_credito_id`),
  KEY `cuotas_tarjeta_usuario_id_fecha_vencimiento_index` (`usuario_id`,`fecha_vencimiento`),
  CONSTRAINT `cuotas_tarjeta_compra_tarjeta_id_foreign` FOREIGN KEY (`compra_tarjeta_id`) REFERENCES `compras_tarjeta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuotas_tarjeta_tarjeta_credito_id_foreign` FOREIGN KEY (`tarjeta_credito_id`) REFERENCES `tarjetas_credito` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuotas_tarjeta_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuotas_tarjeta`
--

LOCK TABLES `cuotas_tarjeta` WRITE;
/*!40000 ALTER TABLE `cuotas_tarjeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `cuotas_tarjeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hechos_tesoreria`
--

DROP TABLE IF EXISTS `hechos_tesoreria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hechos_tesoreria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `tipo` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_gasto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuenta_liquida_id` bigint unsigned DEFAULT NULL,
  `cuenta_destino_id` bigint unsigned DEFAULT NULL,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `meta_ahorro_id` bigint unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `monto_centavos` bigint NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash_fila` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hechos_tesoreria_cuenta_liquida_id_foreign` (`cuenta_liquida_id`),
  KEY `hechos_tesoreria_cuenta_destino_id_foreign` (`cuenta_destino_id`),
  KEY `hechos_tesoreria_categoria_id_foreign` (`categoria_id`),
  KEY `hechos_tesoreria_usuario_id_fecha_index` (`usuario_id`,`fecha`),
  KEY `hechos_tesoreria_usuario_id_hash_fila_index` (`usuario_id`,`hash_fila`),
  KEY `hechos_tesoreria_meta_ahorro_id_foreign` (`meta_ahorro_id`),
  CONSTRAINT `hechos_tesoreria_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hechos_tesoreria_cuenta_destino_id_foreign` FOREIGN KEY (`cuenta_destino_id`) REFERENCES `cuentas_liquidas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hechos_tesoreria_cuenta_liquida_id_foreign` FOREIGN KEY (`cuenta_liquida_id`) REFERENCES `cuentas_liquidas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hechos_tesoreria_meta_ahorro_id_foreign` FOREIGN KEY (`meta_ahorro_id`) REFERENCES `metas_ahorro` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hechos_tesoreria_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hechos_tesoreria`
--

LOCK TABLES `hechos_tesoreria` WRITE;
/*!40000 ALTER TABLE `hechos_tesoreria` DISABLE KEYS */;
/*!40000 ALTER TABLE `hechos_tesoreria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `importaciones`
--

DROP TABLE IF EXISTS `importaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `importaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'procesada',
  `filas_ok` int unsigned NOT NULL DEFAULT '0',
  `filas_omitidas` int unsigned NOT NULL DEFAULT '0',
  `filas_error` int unsigned NOT NULL DEFAULT '0',
  `errores` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `importaciones_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `importaciones_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `importaciones`
--

LOCK TABLES `importaciones` WRITE;
/*!40000 ALTER TABLE `importaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `importaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metas_ahorro`
--

DROP TABLE IF EXISTS `metas_ahorro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metas_ahorro` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `cuenta_liquida_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objetivo_centavos` bigint NOT NULL,
  `monto_actual_centavos` bigint NOT NULL DEFAULT '0',
  `fecha_objetivo` date DEFAULT NULL,
  `aporte_mensual_centavos` bigint NOT NULL DEFAULT '0',
  `prioridad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `metas_ahorro_cuenta_liquida_id_foreign` (`cuenta_liquida_id`),
  KEY `metas_ahorro_usuario_id_index` (`usuario_id`),
  CONSTRAINT `metas_ahorro_cuenta_liquida_id_foreign` FOREIGN KEY (`cuenta_liquida_id`) REFERENCES `cuentas_liquidas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `metas_ahorro_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metas_ahorro`
--

LOCK TABLES `metas_ahorro` WRITE;
/*!40000 ALTER TABLE `metas_ahorro` DISABLE KEYS */;
/*!40000 ALTER TABLE `metas_ahorro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2026_09_03_000001_create_finanzas_core',1),(6,'2026_09_04_000001_expand_cuentas_liquidas',1),(7,'2026_09_05_000001_add_periodicidad_recurrencias',1),(8,'2026_09_06_000001_add_clasificacion_gastos',1),(9,'2026_09_07_000001_expand_obligaciones',1),(10,'2026_09_08_000001_expand_tarjetas',1),(11,'2026_09_09_000001_expand_pagos',1),(12,'2026_09_10_000001_expand_presupuestos',1),(13,'2026_09_11_000001_expand_metas_ahorro',1),(14,'2026_09_12_000001_ledger_integrity_constraints',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos`
--

DROP TABLE IF EXISTS `movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `asiento_id` bigint unsigned NOT NULL,
  `cuenta_contable_id` bigint unsigned NOT NULL,
  `debe_centavos` bigint NOT NULL DEFAULT '0',
  `haber_centavos` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movimientos_asiento_id_foreign` (`asiento_id`),
  KEY `movimientos_cuenta_contable_id_foreign` (`cuenta_contable_id`),
  KEY `movimientos_usuario_id_cuenta_contable_id_index` (`usuario_id`,`cuenta_contable_id`),
  CONSTRAINT `movimientos_asiento_id_foreign` FOREIGN KEY (`asiento_id`) REFERENCES `asientos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movimientos_cuenta_contable_id_foreign` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movimientos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos`
--

LOCK TABLES `movimientos` WRITE;
/*!40000 ALTER TABLE `movimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prestamo_id` bigint unsigned DEFAULT NULL,
  `tarjeta_credito_id` bigint unsigned DEFAULT NULL,
  `cuenta_liquida_id` bigint unsigned NOT NULL,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `destino` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `monto_centavos` bigint NOT NULL,
  `capital_centavos` bigint NOT NULL DEFAULT '0',
  `interes_centavos` bigint NOT NULL DEFAULT '0',
  `extraordinario` tinyint(1) NOT NULL DEFAULT '0',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pagos_usuario_id_referencia_unique` (`usuario_id`,`referencia`),
  KEY `pagos_prestamo_id_foreign` (`prestamo_id`),
  KEY `pagos_tarjeta_credito_id_foreign` (`tarjeta_credito_id`),
  KEY `pagos_cuenta_liquida_id_foreign` (`cuenta_liquida_id`),
  KEY `pagos_usuario_id_fecha_index` (`usuario_id`,`fecha`),
  KEY `pagos_categoria_id_foreign` (`categoria_id`),
  CONSTRAINT `pagos_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pagos_cuenta_liquida_id_foreign` FOREIGN KEY (`cuenta_liquida_id`) REFERENCES `cuentas_liquidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagos_prestamo_id_foreign` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pagos_tarjeta_credito_id_foreign` FOREIGN KEY (`tarjeta_credito_id`) REFERENCES `tarjetas_credito` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pagos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prestamos`
--

DROP TABLE IF EXISTS `prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `cuenta_contable_id` bigint unsigned NOT NULL,
  `cuenta_liquida_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_obligacion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prestamo_bancario',
  `principal_centavos` bigint NOT NULL,
  `ea_porcentaje` decimal(8,4) NOT NULL,
  `tipo_tasa` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ea',
  `plazo_meses` smallint unsigned NOT NULL,
  `fecha_desembolso` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `dia_pago` tinyint unsigned NOT NULL,
  `periodicidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensual',
  `metodo_amortizacion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'frances',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vigente',
  `cuota_centavos` bigint NOT NULL,
  `cuotas_pagadas` smallint unsigned NOT NULL DEFAULT '0',
  `seguro_centavos` bigint NOT NULL DEFAULT '0',
  `otros_cargos_centavos` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prestamos_cuenta_contable_id_foreign` (`cuenta_contable_id`),
  KEY `prestamos_cuenta_liquida_id_foreign` (`cuenta_liquida_id`),
  KEY `prestamos_usuario_id_index` (`usuario_id`),
  CONSTRAINT `prestamos_cuenta_contable_id_foreign` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prestamos_cuenta_liquida_id_foreign` FOREIGN KEY (`cuenta_liquida_id`) REFERENCES `cuentas_liquidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prestamos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prestamos`
--

LOCK TABLES `prestamos` WRITE;
/*!40000 ALTER TABLE `prestamos` DISABLE KEYS */;
/*!40000 ALTER TABLE `prestamos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_lineas`
--

DROP TABLE IF EXISTS `presupuesto_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto_lineas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `presupuesto_id` bigint unsigned NOT NULL,
  `categoria_id` bigint unsigned NOT NULL,
  `tope_centavos` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `presupuesto_lineas_presupuesto_id_categoria_id_unique` (`presupuesto_id`,`categoria_id`),
  KEY `presupuesto_lineas_usuario_id_foreign` (`usuario_id`),
  KEY `presupuesto_lineas_categoria_id_foreign` (`categoria_id`),
  CONSTRAINT `presupuesto_lineas_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_lineas_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_lineas_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_lineas`
--

LOCK TABLES `presupuesto_lineas` WRITE;
/*!40000 ALTER TABLE `presupuesto_lineas` DISABLE KEYS */;
/*!40000 ALTER TABLE `presupuesto_lineas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuestos`
--

DROP TABLE IF EXISTS `presupuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuestos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `anio` smallint unsigned NOT NULL,
  `mes` tinyint unsigned NOT NULL,
  `umbrales_alerta` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `presupuestos_usuario_id_anio_mes_unique` (`usuario_id`,`anio`,`mes`),
  CONSTRAINT `presupuestos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuestos`
--

LOCK TABLES `presupuestos` WRITE;
/*!40000 ALTER TABLE `presupuestos` DISABLE KEYS */;
/*!40000 ALTER TABLE `presupuestos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recurrencias`
--

DROP TABLE IF EXISTS `recurrencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurrencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodicidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensual',
  `tipo_gasto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `cuenta_liquida_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto_centavos` bigint NOT NULL,
  `dia_del_mes` tinyint unsigned NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurrencias_categoria_id_foreign` (`categoria_id`),
  KEY `recurrencias_cuenta_liquida_id_foreign` (`cuenta_liquida_id`),
  KEY `recurrencias_usuario_id_index` (`usuario_id`),
  CONSTRAINT `recurrencias_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recurrencias_cuenta_liquida_id_foreign` FOREIGN KEY (`cuenta_liquida_id`) REFERENCES `cuentas_liquidas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recurrencias_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recurrencias`
--

LOCK TABLES `recurrencias` WRITE;
/*!40000 ALTER TABLE `recurrencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `recurrencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seguridad_logs`
--

DROP TABLE IF EXISTS `seguridad_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seguridad_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `accion` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_afectado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seguridad_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `seguridad_logs_usuario_id_created_at_index` (`usuario_id`,`created_at`),
  CONSTRAINT `seguridad_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `seguridad_logs_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seguridad_logs`
--

LOCK TABLES `seguridad_logs` WRITE;
/*!40000 ALTER TABLE `seguridad_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `seguridad_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tarjetas_credito`
--

DROP TABLE IF EXISTS `tarjetas_credito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarjetas_credito` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `cuenta_contable_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_obligacion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tarjeta_credito',
  `cupo_centavos` bigint NOT NULL,
  `dia_corte` tinyint unsigned NOT NULL,
  `dia_pago` tinyint unsigned NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `periodicidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensual',
  `cuotas` smallint unsigned DEFAULT NULL,
  `cuotas_pagadas` smallint unsigned NOT NULL DEFAULT '0',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `ea_porcentaje` decimal(8,4) NOT NULL,
  `tipo_tasa` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ea',
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tarjetas_credito_cuenta_contable_id_foreign` (`cuenta_contable_id`),
  KEY `tarjetas_credito_usuario_id_index` (`usuario_id`),
  CONSTRAINT `tarjetas_credito_cuenta_contable_id_foreign` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tarjetas_credito_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tarjetas_credito`
--

LOCK TABLES `tarjetas_credito` WRITE;
/*!40000 ALTER TABLE `tarjetas_credito` DISABLE KEYS */;
/*!40000 ALTER TABLE `tarjetas_credito` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
