<?php

namespace Trinity\Services;

use Trinity\Models\RankingModel;

class RankingService
{
    private const DISCIPLINAS_VALIDAS = [
        'Fútbol', 'Brawl Stars', 'Clash Royale', 'Fortnite', 'Free Fire', 'Minecraft',
    ];

    private RankingModel $rankings;

    public function __construct()
    {
        $this->rankings = new RankingModel();
    }

    /** @return array<string,mixed> */
    public function top(?string $disciplina): array
    {
        if ($disciplina !== null && !in_array($disciplina, self::DISCIPLINAS_VALIDAS, true)) {
            $disciplina = null;
        }

        $jugadores = $this->rankings->top($disciplina, 50);

        $posicion = 0;
        foreach ($jugadores as &$j) {
            $posicion++;
            $j['posicion']         = $posicion;
            $j['id']               = (int) $j['id'];
            $j['torneos_jugados']  = (int) $j['torneos_jugados'];
            $j['torneos_ganados']  = (int) $j['torneos_ganados'];
        }
        unset($j);

        return [
            'disciplina' => $disciplina,
            'jugadores'  => $jugadores,
        ];
    }
}
