-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 03-10-2026 a las 16:22:13
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `inventario_panchito`
--
CREATE DATABASE IF NOT EXISTS `inventario_panchito` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventario_panchito`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` bigint(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `activa`, `updated_at`) VALUES
(1, 'Abarrotes', 1, 1790217574776),
(2, 'Bebidas', 1, 1790215538306),
(3, 'Lácteos y refrigerados', 1, 1790215538306),
(4, 'Limpieza', 1, 1790215538306),
(5, 'Otros', 1, 1790215538306),
(6, 'Panadería', 1, 1790215538306),
(7, 'Videojuegos', 1, 1790215538306),
(8, 'Dulces', 1, 1790217564910);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `id` int(11) NOT NULL,
  `firebase_uid` varchar(128) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `dni` varchar(15) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `rol` enum('ADMINISTRADOR','EMPLEADO') NOT NULL DEFAULT 'EMPLEADO',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`id`, `firebase_uid`, `nombre`, `dni`, `telefono`, `correo`, `rol`, `activo`, `created_at`) VALUES
(1, NULL, 'Eduardo Vivanco Espinoza', '00000000', '000000000', 'eduvivancoespinoza@gmail.com', 'ADMINISTRADOR', 1, 1790139662000),
(2, '7nFn95cu9deVuM2YTHgdJu8CLX93', 'Samanta Burga', '42325223', '935123233', 'samanta@gmail.com', 'EMPLEADO', 1, 1790140311539);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` varchar(36) NOT NULL,
  `producto_id` varchar(36) NOT NULL,
  `creado_por_uid` varchar(128) NOT NULL DEFAULT '',
  `tipo` enum('ENTRADA','SALIDA') NOT NULL,
  `cantidad` int(10) UNSIGNED NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `fecha_hora` bigint(20) NOT NULL,
  `updated_at` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id`, `producto_id`, `creado_por_uid`, `tipo`, `cantidad`, `motivo`, `fecha_hora`, `updated_at`) VALUES
('156f23be-1c27-446d-a580-fac25e073baf', '87b9eace-b8a1-4f4f-ab3f-895eb3fb3eef', '981C5DgQFpXAT42VINcBa0sjHdL2', 'ENTRADA', 15, 'Compra', 1790227250826, 1790227250826),
('18355d0c-7f64-4d99-913f-6a472f558585', '87b9eace-b8a1-4f4f-ab3f-895eb3fb3eef', '7nFn95cu9deVuM2YTHgdJu8CLX93', 'ENTRADA', 10, 'Compra', 1790213657313, 1790213657313),
('34232952-5a17-4d64-9846-be241f828c8e', 'ed80ac7b-a319-46c2-ace4-5c4a27331aba', '7nFn95cu9deVuM2YTHgdJu8CLX93', 'SALIDA', 5, 'Devolucion a proveedor', 1790154801382, 1790154801382),
('5561d086-36b0-4c87-8fa0-58d78f6c30ef', 'ed80ac7b-a319-46c2-ace4-5c4a27331aba', '7nFn95cu9deVuM2YTHgdJu8CLX93', 'ENTRADA', 5, 'Compra', 1790213813498, 1790213813498),
('58c066a7-1e04-45a4-8ea2-c6326e080d1c', 'ed80ac7b-a319-46c2-ace4-5c4a27331aba', '981C5DgQFpXAT42VINcBa0sjHdL2', 'ENTRADA', 30, 'Compra', 1790153385616, 1790153385616),
('5b8a8fb4-7d92-4493-92fa-799e5f673d49', 'ed80ac7b-a319-46c2-ace4-5c4a27331aba', '981C5DgQFpXAT42VINcBa0sjHdL2', 'ENTRADA', 10, 'Compra', 1790154741599, 1790154741599),
('79f6ee5f-bd57-11f1-b4de-08bfb8c250e5', '79f51ea0-bd57-11f1-b4de-08bfb8c250e5', '', 'ENTRADA', 20, 'Compra', 1790831883000, 1790831883000),
('86624edf-78ac-4f14-a112-1657a0ddcf61', '87b9eace-b8a1-4f4f-ab3f-895eb3fb3eef', '981C5DgQFpXAT42VINcBa0sjHdL2', 'ENTRADA', 15, 'Ajuste de inventario', 1790212844269, 1790212844269),
('c18529dd-481a-4535-a716-7bd3dee2a1f2', 'baca509f-7e97-4ab8-b2b0-47b1a57b56c0', '981C5DgQFpXAT42VINcBa0sjHdL2', 'ENTRADA', 10, 'Compra', 1790318725488, 1790318725488),
('d5c6f8f5-fa35-40b9-a5e4-116972c29387', '87b9eace-b8a1-4f4f-ab3f-895eb3fb3eef', '981C5DgQFpXAT42VINcBa0sjHdL2', 'SALIDA', 10, 'Venta', 1790227424328, 1790227424328),
('d65d2814-b668-46a1-9a1e-160aa1ac1c66', 'baca509f-7e97-4ab8-b2b0-47b1a57b56c0', '981C5DgQFpXAT42VINcBa0sjHdL2', 'SALIDA', 10, 'Venta', 1790322642714, 1790322642714),
('f9ec20c0-7644-4297-8bb8-7f126080acc8', 'ed80ac7b-a319-46c2-ace4-5c4a27331aba', '981C5DgQFpXAT42VINcBa0sjHdL2', 'SALIDA', 10, 'Venta', 1790154287268, 1790154287268);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` char(36) NOT NULL,
  `owner_uid` varchar(128) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `categoria_id` bigint(20) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 0,
  `fecha_vencimiento` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` bigint(20) NOT NULL,
  `deleted_at` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `owner_uid`, `codigo`, `nombre`, `categoria_id`, `precio`, `stock`, `stock_minimo`, `fecha_vencimiento`, `activo`, `updated_at`, `deleted_at`, `created_at`) VALUES
('79f51ea0-bd57-11f1-b4de-08bfb8c250e5', '', 'PRO0004', 'Producto Workbench', 2, 5.00, 20, 5, NULL, 1, 1790831883000, NULL, '2026-10-01 05:18:03'),
('87b9eace-b8a1-4f4f-ab3f-895eb3fb3eef', '981C5DgQFpXAT42VINcBa0sjHdL2', 'PRO0002', 'Rambutan', 2, 2.50, 30, 5, '2026-09-30', 1, 1790227424328, NULL, '2026-09-24 01:19:57'),
('baca509f-7e97-4ab8-b2b0-47b1a57b56c0', '981C5DgQFpXAT42VINcBa0sjHdL2', 'PRO0003', 'Pie de Limon', 8, 17.00, 0, 4, NULL, 1, 1790322642714, NULL, '2026-09-25 06:45:27'),
('ed80ac7b-a319-46c2-ace4-5c4a27331aba', '7nFn95cu9deVuM2YTHgdJu8CLX93', 'PRO0001', 'Ajinomen', 5, 1.00, 30, 5, '2026-10-07', 1, 1790213813498, NULL, '2026-09-23 08:48:27');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_empleados_correo` (`correo`),
  ADD UNIQUE KEY `uq_empleados_dni` (`dni`),
  ADD UNIQUE KEY `uq_empleados_firebase_uid` (`firebase_uid`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_movimientos_producto_id` (`producto_id`),
  ADD KEY `idx_movimientos_updated_at` (`updated_at`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_codigo` (`owner_uid`,`codigo`),
  ADD KEY `idx_owner` (`owner_uid`),
  ADD KEY `idx_categoria` (`categoria_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_movimientos_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
