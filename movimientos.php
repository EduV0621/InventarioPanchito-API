<?php
/**
 * movimientos.php
 *
 * Fase 3: API REST de MOVIMIENTOS de inventario (entradas/salidas). Sigue exactamente la misma
 * estructura que productos.php (mismo estilo de Response/Database/CORS/manejo de errores).
 *
 * Los movimientos son INMUTABLES una vez creados (no se editan ni se borran desde la app), así
 * que este endpoint solo necesita crear y listar:
 *
 * ENDPOINTS:
 *   GET  /inventario_api/movimientos.php[?desde=<updated_at>]  -> lista de movimientos (todos,
 *                                                                  o solo los posteriores a
 *                                                                  "desde" para sincronizacion
 *                                                                  incremental)
 *   POST /inventario_api/movimientos.php                        -> crea un movimiento (JSON en
 *                                                                  el body). El id (UUID) lo
 *                                                                  genera Android, igual que con
 *                                                                  productos.
 *
 * IMPORTANTE: este endpoint NO recalcula ni valida stock. El stock ya se actualizó localmente en
 * Room (y se sincroniza aparte, como una actualización normal de productos.php); aquí solo se
 * guarda el REGISTRO HISTÓRICO del movimiento para que otros dispositivos puedan descargarlo y
 * mostrarlo en su propio historial. Esto respeta la regla del pedido: "Room siempre manda, la
 * sincronización solo refleja lo que ya pasó".
 *
 * INVENTARIO COMPARTIDO: igual que productos.php, no se filtra por owner_uid. creado_por_uid se
 * guarda solo como dato de trazabilidad (quién hizo el movimiento).
 */

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = Database::obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    switch ($metodo) {
        case 'GET':
            listarMovimientos($pdo);
            break;

        case 'POST':
            crearMovimiento($pdo);
            break;

        default:
            Response::error('Método no soportado.', 405);
    }
} catch (Throwable $e) {
    error_log('movimientos.php error: ' . $e->getMessage());
    Response::error('Error interno del servidor.', 500);
}

// =====================================================================
// GET /movimientos.php[?desde=<updated_at>] -> lista de movimientos (inventario compartido)
// =====================================================================
function listarMovimientos(PDO $pdo): void
{
    $desde = isset($_GET['desde']) ? (int) $_GET['desde'] : null;

    if ($desde !== null) {
        $stmt = $pdo->prepare('SELECT * FROM movimientos WHERE updated_at > :desde ORDER BY fecha_hora DESC');
        $stmt->execute(['desde' => $desde]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM movimientos ORDER BY fecha_hora DESC');
        $stmt->execute();
    }

    $filas = $stmt->fetchAll();
    Response::json(array_map('filaAJson', $filas), 200);
}

// =====================================================================
// POST /movimientos.php -> crea un movimiento. El id (UUID) lo genera Android, igual que con
// productos. ON DUPLICATE KEY UPDATE evita duplicados si Android reintenta el mismo POST.
// =====================================================================
function crearMovimiento(PDO $pdo): void
{
    $datos = leerJsonBody();
    $errores = validarMovimiento($datos);
    if ($errores) {
        Response::error(implode(' ', $errores), 400);
        return;
    }

    // El producto referenciado debe existir (evita movimientos huérfanos).
    $stmtProducto = $pdo->prepare('SELECT id FROM productos WHERE id = :producto_id LIMIT 1');
    $stmtProducto->execute(['producto_id' => $datos['producto_id']]);
    if (!$stmtProducto->fetch()) {
        Response::error('El producto del movimiento no existe.', 404);
        return;
    }

    $sql = 'INSERT INTO movimientos
                (id, producto_id, creado_por_uid, tipo, cantidad, motivo, fecha_hora, updated_at)
            VALUES
                (:id, :producto_id, :creado_por_uid, :tipo, :cantidad, :motivo, :fecha_hora, :updated_at)
            ON DUPLICATE KEY UPDATE
                producto_id = VALUES(producto_id), creado_por_uid = VALUES(creado_por_uid),
                tipo = VALUES(tipo), cantidad = VALUES(cantidad), motivo = VALUES(motivo),
                fecha_hora = VALUES(fecha_hora), updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(datosParaSql($datos));

    $stmtNuevo = $pdo->prepare('SELECT * FROM movimientos WHERE id = :id LIMIT 1');
    $stmtNuevo->execute(['id' => $datos['id']]);
    Response::json(filaAJson($stmtNuevo->fetch()), 201);
}

// =====================================================================
// Helpers
// =====================================================================

function leerJsonBody(): array
{
    $crudo = file_get_contents('php://input');
    $decodificado = json_decode($crudo, true);
    return is_array($decodificado) ? $decodificado : [];
}

function validarMovimiento(array $datos): array
{
    $errores = [];
    if (empty($datos['id'])) $errores[] = 'Falta el id.';
    if (empty($datos['producto_id'])) $errores[] = 'Falta producto_id.';
    if (empty($datos['tipo']) || !in_array($datos['tipo'], ['ENTRADA', 'SALIDA'], true)) {
        $errores[] = 'El tipo debe ser ENTRADA o SALIDA.';
    }
    if (!isset($datos['cantidad']) || (int) $datos['cantidad'] <= 0) {
        $errores[] = 'La cantidad debe ser mayor a 0.';
    }
    if (empty($datos['motivo'])) $errores[] = 'El motivo es obligatorio.';
    if (empty($datos['fecha_hora'])) $errores[] = 'Falta fecha_hora.';
    return $errores;
}

function datosParaSql(array $datos): array
{
    return [
        'id' => $datos['id'],
        'producto_id' => $datos['producto_id'],
        'creado_por_uid' => $datos['creado_por_uid'] ?? '',
        'tipo' => $datos['tipo'],
        'cantidad' => (int) $datos['cantidad'],
        'motivo' => trim((string) $datos['motivo']),
        'fecha_hora' => (int) $datos['fecha_hora'],
        'updated_at' => isset($datos['updated_at']) ? (int) $datos['updated_at'] : (int) (microtime(true) * 1000),
    ];
}

/** Convierte una fila de MySQL al mismo "shape" de JSON que espera MovimientoDto en Android. */
function filaAJson(array $fila): array
{
    return [
        'id' => $fila['id'],
        'producto_id' => $fila['producto_id'],
        'creado_por_uid' => $fila['creado_por_uid'],
        'tipo' => $fila['tipo'],
        'cantidad' => (int) $fila['cantidad'],
        'motivo' => $fila['motivo'],
        'fecha_hora' => (int) $fila['fecha_hora'],
        'updated_at' => (int) $fila['updated_at'],
    ];
}
