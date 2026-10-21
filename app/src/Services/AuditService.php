<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Models\AuditModel;

/** Consulta de la auditoría para el administrador general (solo lectura). */
class AuditService
{
    public const TABLAS_AUDITADAS = [
        'usuarios', 'torneos', 'torneo_participantes', 'partidos',
        'equipos', 'equipo_miembros', 'noticias', 'solicitudes_organizador',
    ];
    private const ACCIONES = ['INSERT', 'UPDATE', 'DELETE'];
    private const POR_PAGINA_MAX = 100;

    private AuditModel $audit;

    public function __construct()
    {
        $this->audit = new AuditModel();
    }

    /**
     * @param array<string,string|null> $q parámetros de la query string
     * @return array<string,mixed>
     */
    public function listar(array $q): array
    {
        $filtros = [];

        $tabla = trim((string) ($q['tabla'] ?? ''));
        if ($tabla !== '') {
            if (!in_array($tabla, self::TABLAS_AUDITADAS, true)) {
                throw new ApiException('Tabla no auditada. Válidas: ' . implode(', ', self::TABLAS_AUDITADAS), 400);
            }
            $filtros['tabla'] = $tabla;
        }

        $accion = strtoupper(trim((string) ($q['accion'] ?? '')));
        if ($accion !== '') {
            if (!in_array($accion, self::ACCIONES, true)) {
                throw new ApiException('Acción inválida. Válidas: ' . implode(', ', self::ACCIONES), 400);
            }
            $filtros['accion'] = $accion;
        }

        foreach (['registro_id', 'usuario_id'] as $campo) {
            $v = trim((string) ($q[$campo] ?? ''));
            if ($v !== '') {
                if (!ctype_digit($v)) {
                    throw new ApiException("{$campo} tiene que ser un número.", 400);
                }
                $filtros[$campo] = (int) $v;
            }
        }

        foreach (['desde', 'hasta'] as $campo) {
            $v = trim((string) ($q[$campo] ?? ''));
            if ($v !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) {
                    throw new ApiException("{$campo} tiene que tener formato AAAA-MM-DD.", 400);
                }
                $filtros[$campo] = $v;
            }
        }

        $pagina    = max(1, (int) ($q['pagina'] ?? 1));
        $porPagina = (int) ($q['por_pagina'] ?? 25);
        $porPagina = min(self::POR_PAGINA_MAX, max(1, $porPagina));

        $res = $this->audit->search($filtros, $porPagina, ($pagina - 1) * $porPagina);

        $eventos = [];
        foreach ($res['filas'] as $f) {
            $eventos[] = [
                'id'             => (int) $f['id'],
                'usuario_id'     => $f['usuario_id'] !== null ? (int) $f['usuario_id'] : null,
                'usuario_nombre' => $f['usuario_nombre'],
                'tabla'          => $f['tabla'],
                'registro_id'    => (int) $f['registro_id'],
                'accion'         => $f['accion'],
                'datos_antes'    => $f['datos_antes']   !== null ? json_decode($f['datos_antes'], true)   : null,
                'datos_despues'  => $f['datos_despues'] !== null ? json_decode($f['datos_despues'], true) : null,
                'ip'             => $f['ip'],
                'creado_en'      => $f['creado_en'],
            ];
        }

        return [
            'eventos'    => $eventos,
            'total'      => $res['total'],
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => (int) max(1, ceil($res['total'] / $porPagina)),
            'tablas'     => self::TABLAS_AUDITADAS,
        ];
    }

    /** @return array<string,mixed> */
    public function resumen(): array
    {
        return ['resumen' => $this->audit->resumen()];
    }
}
