<?php
/**
 * Response.php
 *
 * Helper para responder siempre en JSON con el código HTTP correcto.
 * Centralizar esto evita que cada endpoint arme el header/json a mano.
 */

class Response
{
    public static function json($datos, int $codigoHttp = 200): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $mensaje, int $codigoHttp = 400): void
    {
        self::json(['error' => $mensaje], $codigoHttp);
    }
}
