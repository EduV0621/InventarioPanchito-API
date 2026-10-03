<?php
/**
 * htdocs/inventario_api/categorias.php
 *
 * GET  categorias.php  -> lista todas las categorias (activas e inactivas).
 * POST categorias.php  -> crea la categoria si ese id no existe, o la actualiza si ya existe
 *                         (alta, edicion y cambio activa/inactiva). Es idempotente.
 *
 * JSON (mismos nombres que CategoriaDto en la app):
 *   { "id": 7, "nombre": "Snacks", "activa": true, "updated_at": 1727150000000 }
 *
 * IMPORTANTE: este archivo es autonomo (no depende de tu config.php ni de tu clase Response,
 * porque no las conozco). Ajusta el bloque "CONEXION" con los mismos datos que usa tu
 * productos.php, o reemplazalo por tu propia conexion.
 */

header('Content-Type: application/json; charset=utf-8');

// ===================== CONEXION (ajusta estos datos) =====================
const DB_HOST = '127.0.0.1';
const DB_PORT = 3307;                    // el puerto de MySQL que configuraste en XAMPP
const DB_NAME = 'inventario_panchito';   // <-- pon el nombre real de tu base de datos
const DB_USER = 'root';
const DB_PASS = '';
// =========================================================================

function responder(int $codigo, $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function fallar(int $codigo, string $mensaje): void
{
    responder($codigo, ['error' => $mensaje]);
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fallar(500, 'No se pudo conectar con la base de datos.');
}

function filaAJson(array $fila): array
{
    return [
        'id'         => (int) $fila['id'],
        'nombre'     => $fila['nombre'],
        'activa'     => (bool) $fila['activa'],      // 1/0 en MySQL -> true/false en el JSON
        'updated_at' => (int) $fila['updated_at'],
    ];
}

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        $filas = $pdo->query('SELECT id, nombre, activa, updated_at FROM categorias ORDER BY nombre')->fetchAll();
        responder(200, array_map('filaAJson', $filas));
    }

    if ($metodo === 'POST') {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            fallar(400, 'El cuerpo de la solicitud no es un JSON valido.');
        }

        $id        = (int) ($datos['id'] ?? 0);
        $nombre    = trim((string) ($datos['nombre'] ?? ''));
        $activa    = filter_var($datos['activa'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $updatedAt = (int) ($datos['updated_at'] ?? 0);

        if ($id <= 0)       fallar(400, 'El id de la categoria es obligatorio.');
        if ($nombre === '') fallar(400, 'El nombre de la categoria es obligatorio.');

        $existe = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id = :id');
        $existe->execute([':id' => $id]);

        if ((int) $existe->fetchColumn() > 0) {
            $sql = 'UPDATE categorias SET nombre = :nombre, activa = :activa, updated_at = :u WHERE id = :id';
        } else {
            $sql = 'INSERT INTO categorias (id, nombre, activa, updated_at) VALUES (:id, :nombre, :activa, :u)';
        }
        $sentencia = $pdo->prepare($sql);
        $sentencia->execute([':id' => $id, ':nombre' => $nombre, ':activa' => $activa, ':u' => $updatedAt]);

        responder(200, [
            'id'         => $id,
            'nombre'     => $nombre,
            'activa'     => (bool) $activa,
            'updated_at' => $updatedAt,
        ]);
    }

    fallar(405, 'Metodo no permitido.');
} catch (PDOException $e) {
    // 1062 = clave duplicada (por ejemplo, otra categoria con el mismo nombre si tu tabla lo exige unico).
    if (($e->errorInfo[1] ?? 0) === 1062) {
        fallar(409, 'Ya existe una categoria con ese nombre.');
    }
    fallar(500, 'Error al guardar la categoria en el servidor.');
}
