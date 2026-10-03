<?php
/**
 * productos.php
 *
 * API REST de productos del inventario. Reemplaza a la API externa de
 * cosméticos (DummyJSON) que usaba la app.
 *
 * ENDPOINTS (Fase 2: INVENTARIO COMPARTIDO — ya no se filtra por owner_uid):
 *   GET    /inventario_api/productos.php[?desde=<updated_at>]     -> lista de TODOS los productos
 *   GET    /inventario_api/productos.php/{id}                     -> un producto puntual
 *   POST   /inventario_api/productos.php                          -> crea un producto (JSON en el body)
 *   PUT    /inventario_api/productos.php/{id}                     -> actualiza un producto (JSON en el body)
 *   DELETE /inventario_api/productos.php/{id}                     -> elimina (borrado lógico)
 *
 * owner_uid = UID de Firebase Authentication del usuario que CREÓ/editó el producto. Se sigue
 * guardando en la fila como dato de trazabilidad, pero YA NO se usa para filtrar ni autorizar:
 * el inventario del Minimarket Panchito es el mismo para el Administrador y para todos los
 * Empleados (ver informe, "Inventario compartido").
 *
 * Todas las respuestas son JSON. Los errores devuelven {"error": "..."} con
 * el código HTTP correspondiente (400 datos inválidos, 404 no encontrado,
 * 409 código duplicado, 500 error del servidor).
 */

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Database.php';

// --- CORS: permite que la app Android (y Postman/navegador) llamen a la API ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = Database::obtenerConexion();

// --- Determina si vino un {id} en la URL (productos.php/ese-id) ---
$id = null;
if (!empty($_SERVER['PATH_INFO'])) {
    $id = trim($_SERVER['PATH_INFO'], '/');
}

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    switch ($metodo) {
        case 'GET':
            if ($id !== null && $id !== '') {
                obtenerProducto($pdo, $id);
            } else {
                listarProductos($pdo);
            }
            break;

        case 'POST':
            crearProducto($pdo);
            break;

        case 'PUT':
            if ($id === null || $id === '') {
                Response::error('Falta el id del producto en la URL (/productos.php/{id}).', 400);
                break;
            }
            actualizarProducto($pdo, $id);
            break;

        case 'DELETE':
            if ($id === null || $id === '') {
                Response::error('Falta el id del producto en la URL (/productos.php/{id}).', 400);
                break;
            }
            eliminarProducto($pdo, $id);
            break;

        default:
            Response::error('Método no soportado.', 405);
    }
} catch (Throwable $e) {
    // Red de seguridad: cualquier error no controlado responde 500 en JSON
    // (nunca un error HTML de PHP, que rompería el parseo en Android).
    error_log('productos.php error: ' . $e->getMessage());
    Response::error('Error interno del servidor.', 500);
}

// =====================================================================
// GET /productos.php?owner_uid=XXX  -> lista de productos activos e inactivos
// (no eliminados) del usuario. El catálogo (solo activos) lo filtra Android,
// igual que hace hoy con Room; aquí se manda todo para que la sincronización
// de "actualizar/eliminar" también pueda aplicar sobre productos desactivados.
// =====================================================================
function listarProductos(PDO $pdo): void
{
    // INVENTARIO COMPARTIDO: ya no se filtra por owner_uid. Todos los dispositivos (Administrador
    // o Empleado) ven el mismo catálogo de productos del minimarket. owner_uid se conserva en la
    // fila solo como dato de trazabilidad (quién creó el producto), nunca como filtro de acceso.
    //
    // Sincronización incremental opcional: si Android manda ?desde=<updated_at>, solo se
    // devuelven productos modificados después de esa marca. Si no se manda, se devuelven todos
    // (primera sincronización / carga completa).
    $desde = isset($_GET['desde']) ? (int) $_GET['desde'] : null;

    if ($desde !== null) {
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE updated_at > :desde ORDER BY nombre ASC');
        $stmt->execute(['desde' => $desde]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM productos ORDER BY nombre ASC');
        $stmt->execute();
    }

    $filas = $stmt->fetchAll();
    $productos = array_map('filaAJson', $filas);
    Response::json($productos, 200);
}

// =====================================================================
// GET /productos.php/{id}?owner_uid=XXX -> un producto puntual
// =====================================================================
function obtenerProducto(PDO $pdo, string $id): void
{
    // INVENTARIO COMPARTIDO: cualquier usuario puede consultar cualquier producto, sin filtrar
    // por owner_uid.
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $fila = $stmt->fetch();

    if (!$fila) {
        Response::error('Producto no encontrado.', 404);
        return;
    }
    Response::json(filaAJson($fila), 200);
}

// =====================================================================
// POST /productos.php -> crea un producto. El id (UUID) lo genera Android
// y viene en el JSON; aquí NO se autogenera, para que sea el mismo id en
// Room y en MySQL (igual que hoy pasa con Firestore).
// =====================================================================
function crearProducto(PDO $pdo): void
{
    $datos = leerJsonBody();
    $errores = validarProducto($datos, requiereId: true);
    if ($errores) {
        Response::error(implode(' ', $errores), 400);
        return;
    }

    // INVENTARIO COMPARTIDO: el código es único a nivel de TODO el inventario (ya no por
    // owner_uid), porque ahora el catálogo es el mismo para Administrador y Empleados. El código
    // en sí ya lo genera Android automáticamente (PRO0001, PRO0002, ...); esta validación es solo
    // una red de seguridad del lado servidor por si dos dispositivos, sin Internet al mismo
    // tiempo, generaron el mismo código.
    $stmtDup = $pdo->prepare(
        'SELECT COUNT(*) FROM productos WHERE codigo = :codigo AND deleted_at IS NULL AND id != :id'
    );
    $stmtDup->execute([
        'codigo' => $datos['codigo'],
        'id' => $datos['id'],
    ]);
    if ((int) $stmtDup->fetchColumn() > 0) {
        Response::error('Ya existe un producto con ese código.', 409);
        return;
    }

    $sql = 'INSERT INTO productos
                (id, owner_uid, codigo, nombre, categoria_id, precio, stock, stock_minimo,
                 fecha_vencimiento, activo, updated_at, deleted_at)
            VALUES
                (:id, :owner_uid, :codigo, :nombre, :categoria_id, :precio, :stock, :stock_minimo,
                 :fecha_vencimiento, :activo, :updated_at, :deleted_at)
            ON DUPLICATE KEY UPDATE
                codigo = VALUES(codigo), nombre = VALUES(nombre), categoria_id = VALUES(categoria_id),
                precio = VALUES(precio), stock = VALUES(stock), stock_minimo = VALUES(stock_minimo),
                fecha_vencimiento = VALUES(fecha_vencimiento), activo = VALUES(activo),
                updated_at = VALUES(updated_at), deleted_at = VALUES(deleted_at)';
    // ON DUPLICATE KEY UPDATE: si Android reintenta el mismo POST (por ejemplo porque la
    // respuesta anterior se perdió al cortarse la conexión justo después de guardar), no falla
    // con un error de clave duplicada: simplemente confirma el mismo estado. Esto evita perder
    // datos ni duplicar productos en reintentos de sincronización.

    $stmt = $pdo->prepare($sql);
    $stmt->execute(datosParaSql($datos));

    obtenerProducto($pdo, $datos['id']);
}

// =====================================================================
// PUT /productos.php/{id} -> actualiza un producto existente
// =====================================================================
function actualizarProducto(PDO $pdo, string $id): void
{
    $datos = leerJsonBody();
    $datos['id'] = $id; // el id de la URL manda, se ignora cualquier id distinto que venga en el body
    $errores = validarProducto($datos, requiereId: false);
    if ($errores) {
        Response::error(implode(' ', $errores), 400);
        return;
    }

    // INVENTARIO COMPARTIDO: cualquier usuario puede actualizar cualquier producto (por ejemplo,
    // el stock que cambió un Empleado). owner_uid ya NO se usa para autorizar la operación.
    $stmtExiste = $pdo->prepare('SELECT id FROM productos WHERE id = :id LIMIT 1');
    $stmtExiste->execute(['id' => $id]);
    if (!$stmtExiste->fetch()) {
        Response::error('Producto no encontrado.', 404);
        return;
    }

    $stmtDup = $pdo->prepare(
        'SELECT COUNT(*) FROM productos WHERE codigo = :codigo AND deleted_at IS NULL AND id != :id'
    );
    $stmtDup->execute(['codigo' => $datos['codigo'], 'id' => $id]);
    if ((int) $stmtDup->fetchColumn() > 0) {
        Response::error('Ya existe un producto con ese código.', 409);
        return;
    }

    $sql = 'UPDATE productos SET
                owner_uid = :owner_uid, codigo = :codigo, nombre = :nombre, categoria_id = :categoria_id,
                precio = :precio, stock = :stock, stock_minimo = :stock_minimo,
                fecha_vencimiento = :fecha_vencimiento, activo = :activo, updated_at = :updated_at,
                deleted_at = :deleted_at
            WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(datosParaSql($datos));

    obtenerProducto($pdo, $id);
}

// =====================================================================
// DELETE /productos.php/{id}?owner_uid=XXX -> borrado LÓGICO (deleted_at),
// igual que ya hace Room hoy: la fila se conserva (por los movimientos
// históricos que la referencian) pero deja de aparecer en el catálogo.
// =====================================================================
function eliminarProducto(PDO $pdo, string $id): void
{
    // INVENTARIO COMPARTIDO: cualquier usuario puede eliminar (borrado lógico) cualquier
    // producto; owner_uid ya no se exige como parámetro de autorización.
    $stmtExiste = $pdo->prepare('SELECT id FROM productos WHERE id = :id LIMIT 1');
    $stmtExiste->execute(['id' => $id]);
    if (!$stmtExiste->fetch()) {
        Response::error('Producto no encontrado.', 404);
        return;
    }

    $ahoraMillis = (int) (microtime(true) * 1000);
    $stmt = $pdo->prepare('UPDATE productos SET deleted_at = :ahora1, updated_at = :ahora2 WHERE id = :id');
    // :ahora1 y :ahora2 llevan el mismo valor pero necesitan nombres distintos: con
    // PDO::ATTR_EMULATE_PREPARES en false (ver Database.php) MySQL usa "prepared statements"
    // reales, que no admiten repetir el mismo placeholder con nombre dos veces en una consulta
    // (por eso fallaba con "SQLSTATE[HY093]: Invalid parameter number").
    $stmt->execute(['ahora1' => $ahoraMillis, 'ahora2' => $ahoraMillis, 'id' => $id]);

    Response::json(['id' => $id, 'eliminado' => true], 200);
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

/**
 * Valida los campos obligatorios de un producto. Devuelve un array de
 * mensajes de error (vacío = todo OK). Refleja las mismas reglas que ya
 * valida ProductoRepositoryImpl.validar() en Android.
 */
function validarProducto(array $datos, bool $requiereId): array
{
    // owner_uid ya NO es obligatorio (inventario compartido): si viene, se guarda como dato de
    // trazabilidad de quién creó/editó el producto; si no viene, se guarda como cadena vacía.
    $errores = [];
    if ($requiereId && empty($datos['id'])) $errores[] = 'Falta el id.';
    if (empty($datos['codigo'])) $errores[] = 'El código es obligatorio.';
    if (empty($datos['nombre'])) $errores[] = 'El nombre es obligatorio.';
    if (!isset($datos['categoria_id'])) $errores[] = 'Falta categoria_id.';
    if (isset($datos['precio']) && $datos['precio'] < 0) $errores[] = 'El precio no puede ser negativo.';
    if (isset($datos['stock']) && $datos['stock'] < 0) $errores[] = 'El stock no puede ser negativo.';
    if (isset($datos['stock_minimo']) && $datos['stock_minimo'] < 0) $errores[] = 'El stock mínimo no puede ser negativo.';
    return $errores;
}

/** Arma el array de parámetros con nombre exacto de las columnas SQL, con valores por defecto. */
function datosParaSql(array $datos): array
{
    return [
        'id' => $datos['id'],
        'owner_uid' => $datos['owner_uid'] ?? '',
        'codigo' => trim((string) $datos['codigo']),
        'nombre' => trim((string) $datos['nombre']),
        'categoria_id' => (int) $datos['categoria_id'],
        'precio' => (float) ($datos['precio'] ?? 0),
        'stock' => (int) ($datos['stock'] ?? 0),
        'stock_minimo' => (int) ($datos['stock_minimo'] ?? 0),
        'fecha_vencimiento' => !empty($datos['fecha_vencimiento']) ? $datos['fecha_vencimiento'] : null,
        'activo' => isset($datos['activo']) ? (int) (bool) $datos['activo'] : 1,
        'updated_at' => isset($datos['updated_at']) ? (int) $datos['updated_at'] : (int) (microtime(true) * 1000),
        'deleted_at' => isset($datos['deleted_at']) && $datos['deleted_at'] !== null ? (int) $datos['deleted_at'] : null,
    ];
}

/** Convierte una fila de MySQL al mismo "shape" de JSON que espera el ProductoApiDto en Android. */
function filaAJson(array $fila): array
{
    return [
        'id' => $fila['id'],
        'owner_uid' => $fila['owner_uid'],
        'codigo' => $fila['codigo'],
        'nombre' => $fila['nombre'],
        'categoria_id' => (int) $fila['categoria_id'],
        'precio' => (float) $fila['precio'],
        'stock' => (int) $fila['stock'],
        'stock_minimo' => (int) $fila['stock_minimo'],
        'fecha_vencimiento' => $fila['fecha_vencimiento'], // "YYYY-MM-DD" o null
        'activo' => (bool) $fila['activo'],
        'updated_at' => (int) $fila['updated_at'],
        'deleted_at' => $fila['deleted_at'] !== null ? (int) $fila['deleted_at'] : null,
    ];
}
