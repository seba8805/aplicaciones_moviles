<?php


// Representa error de la API, tiene codigo HTTP, mensaje y detalles del error
class ApiError extends Exception
{
    public function __construct(public int $codigoHttp, string $mensaje, public array $detalles = [])
    {
        parent::__construct($mensaje);
    }
}