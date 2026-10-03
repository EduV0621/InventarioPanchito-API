<?php
/**
 * empleados.php
 *
 * Fase 1: API REST de empleados y roles. Complementa a productos.php (que no se toca en esta fase).
 *
 * ENDPOINTS:
 *   GET  /inventario_api/empleados.php?correo=XXX  -> datos y rol del empleado ACTIVO con ese correo
 *                                                      (404 si no existe o esta inactivo)
 *   POST /inventario_api/empleados.php              -> registra un empleado nuevo (JSON en el body)
 *
 * El correo es la clave de busqueda para el login (Android obtiene el correo de Firebase
 * Authentication y consulta aqui su rol). firebase_uid se guarda como referencia/trazabilidad
 * pero no es obligatorio: el administrador inicial (ver empleados.sql) se siembra sin uid y el
 * login lo sigue resolviendo por correo.
 *
 * NOTA DE SEGURIDAD (heredada de productos.php, ver informe seccion I): todavia no hay
 * verificacion del ID token de Firebase en el servidor. Cualquiera que sepa la URL puede leer o
 * crear filas. Queda pendiente para una fase posterior.
 */

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Database.php';

// --- CORS: permite que la app Android (y Postman/navegador) llamen a la API ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
            obtenerEmpleadoPorCorreo($pdo);
            break;

        case 'POST':
            crearEmpleado($pdo);
            break;

        case 'PUT':
            // Fase 1 (corrección pendiente): asocia el firebase_uid REAL del usuario despues de
            // que inicia sesion correctamente. Se usa sobre todo para el Administrador inicial,
            // sembrado por SQL con firebase_uid = NULL (ver empleados.sql).
            actualizarFirebaseUid($pdo);
            break;

        default:
            Response::error('Método no soportado.', 405);
    }
} catch (Throwable $e) {
    // Red de seguridad: cualquier error no controlado responde 500 en JSON.
    error_log('empleados.php error: ' . $e->getMessage());
    Response::error('Error interno del servidor.', 500);
}

// =====================================================================
// GET /empleados.php?correo=XXX -> empleado activo con ese correo (login)
// =====================================================================
function obtenerEmpleadoPorCorreo(PDO $pdo): void
{
    $correo = $_GET['correo'] ?? null;
    if (!$correo) {
        Response::error('Falta el parámetro correo.', 400);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM empleados WHERE correo = :correo AND activo = 1 LIMIT 1');
    $stmt->execute(['correo' => $correo]);
    $fila = $stmt->fetch();

    if (!$fila) {
        Response::error('No existe un empleado activo con ese correo.', 404);
        return;
    }
    Response::json(filaAJson($fila), 200);
}

// =====================================================================
// POST /empleados.php -> registra un empleado nuevo (lo llama Android
// DESPUES de crear la cuenta en Firebase Authentication; firebase_uid
// viaja en el body con el uid recien creado).
// =====================================================================
function crearEmpleado(PDO $pdo): void
{
    $datos = leerJsonBody();
    $errores = validarEmpleado($datos);
    if ($errores) {
        Response::error(implode(' ', $errores), 400);
        return;
    }

    $rol = strtoupper(trim((string) $datos['rol']));
    if (!in_array($rol, ['ADMINISTRADOR', 'EMPLEADO'], true)) {
        Response::error('El rol debe ser ADMINISTRADOR o EMPLEADO.', 400);
        return;
    }

    // correo y dni son unicos (ver empleados.sql); se valida aqui como red de seguridad del
    // lado servidor, igual que hace productos.php con el codigo de producto.
    $stmtDup = $pdo->prepare('SELECT COUNT(*) FROM empleados WHERE correo = :correo OR dni = :dni');
    $stmtDup->execute(['correo' => $datos['correo'], 'dni' => $datos['dni']]);
    if ((int) $stmtDup->fetchColumn() > 0) {
        Response::error('Ya existe un empleado registrado con ese correo o DNI.', 409);
        return;
    }

    $sql = 'INSERT INTO empleados (firebase_uid, nombre, dni, telefono, correo, rol, activo, created_at)
            VALUES (:firebase_uid, :nombre, :dni, :telefono, :correo, :rol, 1, :created_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'firebase_uid' => !empty($datos['firebase_uid']) ? $datos['firebase_uid'] : null,
        'nombre' => trim((string) $datos['nombre']),
        'dni' => trim((string) $datos['dni']),
        'telefono' => trim((string) $datos['telefono']),
        'correo' => trim((string) $datos['correo']),
        'rol' => $rol,
        'created_at' => (int) (microtime(true) * 1000),
    ]);

    $id = (int) $pdo->lastInsertId();
    $stmtNuevo = $pdo->prepare('SELECT * FROM empleados WHERE id = :id');
    $stmtNuevo->execute(['id' => $id]);
    Response::json(filaAJson($stmtNuevo->fetch()), 201);
}

// =====================================================================
// PUT /empleados.php?correo=XXX -> asocia el firebase_uid REAL con el empleado de ese correo.
// Fase 1 (corrección pendiente): el Administrador inicial se siembra en empleados.sql con
// firebase_uid = NULL (el login funciona buscando por correo). La primera vez que ese
// Administrador inicia sesión con éxito, Android llama aquí con el UID real que le devolvió
// Firebase Authentication, y este endpoint lo guarda en MySQL.
//
// Es INTENCIONAL que solo escriba el firebase_uid cuando la fila todavía no tiene uno (está NULL
// o vacío): así no se sobreescribe por error un uid ya asociado si se repite la llamada, y no se
// implementa ninguna verificación adicional de identidad (eso queda fuera de esta etapa, ver
// REGLAS IMPORTANTES del pedido: "no seguridad avanzada").
// =====================================================================
function actualizarFirebaseUid(PDO $pdo): void
{
    $correo = $_GET['correo'] ?? null;
    if (!$correo) {
        Response::error('Falta el parámetro correo.', 400);
        return;
    }

    $datos = leerJsonBody();
    $firebaseUid = trim((string) ($datos['firebase_uid'] ?? ''));
    if ($firebaseUid === '') {
        Response::error('Falta firebase_uid en el body.', 400);
        return;
    }

    $stmt = $pdo->prepare('SELECT id, firebase_uid FROM empleados WHERE correo = :correo AND activo = 1 LIMIT 1');
    $stmt->execute(['correo' => $correo]);
    $fila = $stmt->fetch();
    if (!$fila) {
        Response::error('No existe un empleado activo con ese correo.', 404);
        return;
    }

    // Ya tiene un firebase_uid asociado: no se pisa (idempotente).
    if (!empty($fila['firebase_uid'])) {
        Response::json(filaAJsonConId($pdo, (int) $fila['id']), 200);
        return;
    }

    $stmtUpdate = $pdo->prepare('UPDATE empleados SET firebase_uid = :firebase_uid WHERE id = :id');
    $stmtUpdate->execute(['firebase_uid' => $firebaseUid, 'id' => $fila['id']]);

    Response::json(filaAJsonConId($pdo, (int) $fila['id']), 200);
}

/** Relee una fila de empleados por id y la devuelve como JSON (reutilizado por actualizarFirebaseUid). */
function filaAJsonConId(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM empleados WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return filaAJson($stmt->fetch());
}

// =====================================================================
// Helpers
// =====================================================================

/** Lee y decodifica el body JSON de la solicitud; [] si viene vacío o inválido. */
function leerJsonBody(): array
{
    $crudo = file_get_contents('php://input');
    $decodificado = json_decode($crudo, true);
    return is_array($decodificado) ? $decodificado : [];
}

/** Valida los campos obligatorios de un empleado. Devuelve un array de mensajes (vacío = OK). */
function validarEmpleado(array $datos): array
{
    $errores = [];
    if (empty($datos['nombre'])) $errores[] = 'El nombre es obligatorio.';
    if (empty($datos['dni'])) $errores[] = 'El DNI es obligatorio.';
    if (empty($datos['telefono'])) $errores[] = 'El teléfono es obligatorio.';
    if (empty($datos['correo'])) $errores[] = 'El correo es obligatorio.';
    if (empty($datos['rol'])) $errores[] = 'El rol es obligatorio.';
    return $errores;
}

/** Convierte una fila de MySQL al mismo "shape" de JSON que espera el EmpleadoDto en Android. */
function filaAJson(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'firebase_uid' => $fila['firebase_uid'],
        'nombre' => $fila['nombre'],
        'dni' => $fila['dni'],
        'telefono' => $fila['telefono'],
        'correo' => $fila['correo'],
        'rol' => $fila['rol'],
        'activo' => (bool) $fila['activo'],
    ];
}
