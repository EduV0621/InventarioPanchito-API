<?php
/**
 * Database.php
 *
 * Conexión única a MySQL mediante PDO. Se usa PDO (en vez de mysqli) porque
 * permite "prepared statements" con parámetros nombrados, lo que evita
 * inyección SQL de forma simple y ordenada.
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $conexion = null;

    public static function obtenerConexion(): PDO
    {
        if (self::$conexion === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$conexion = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // No exponemos el mensaje real de PDO (puede incluir datos sensibles);
                // Response::error se encarga de responder algo genérico y con código 500.
                Response::error('No se pudo conectar a la base de datos.', 500);
                exit;
            }
        }
        return self::$conexion;
    }
}
