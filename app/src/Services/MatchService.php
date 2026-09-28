<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Models\MatchModel;
use Trinity\Models\NotificationModel;
use Trinity\Models\TournamentModel;
use Trinity\Models\UserModel;

/** Genera y gestiona partidos (calendario, llaves, resultados, posiciones).
  *
  * - Eliminación directa: se genera el cuadro COMPLETO al iniciar (todas
  *   las rondas). Los "byes" (cuando el número de inscriptos no es
  *   potencia de 2) se resuelven solos en ese momento. Cada resultado
  *   cargado empuja automáticamente al ganador a la ronda siguiente.
  * - Liga: se genera el calendario completo al iniciar con el método
  *   del círculo (todos contra todos, sin que nadie juegue dos veces
  *   la misma fecha).
  * - Suizo: se genera una ronda a la vez. La ronda siguiente empareja
  *   por puntaje acumulado evitando repetir rivales cuando es posible
  *   (una simplificación razonable del sistema suizo "oficial", que
  *   usa además desempates tipo Buchholz que acá no se implementan).
  */
class MatchService
{
    private MatchModel $matches;
    private TournamentModel $tournaments;
    private UserModel $users;
    private NotificationModel $notifications;

    public function __construct()
    {
        $this->matches        = new MatchModel();
        $this->tournaments    = new TournamentModel();
        $this->users          = new UserModel();
        $this->notifications  = new NotificationModel();
    }

    // ═══════════════════════════════════════════════════════════
    //  INICIAR TORNEO
    // ═══════════════════════════════════════════════════════════

    /** @return array<string,mixed> */
    public function start(int $torneoId, int $organizerId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ((int) $t['organizador_id'] !== $organizerId) {
            throw new ApiException('Solo el organizador puede iniciar el torneo.', 403);
        }
        if (!in_array($t['estado'], ['en_creacion', 'abierto'], true)) {
            throw new ApiException('Este torneo ya fue iniciado.', 409);
        }

        $participantes = $this->tournaments->listParticipants($torneoId);
        $ids = array_map(fn ($p) => (int) $p['id'], $participantes);
        if (count($ids) < 2) {
            throw new ApiException('Necesitás al menos 2 participantes para iniciar el torneo.', 400);
        }

        shuffle($ids);

        $descripcion = match ($t['formato']) {
            'eliminacion' => 'el cuadro de eliminación',
            'liga'        => 'el calendario de la liga',
            'suizo'       => 'la primera ronda',
            default       => 'el torneo',
        };

        match ($t['formato']) {
            'eliminacion' => $this->generarEliminacion($torneoId, $ids),
            'liga'        => $this->generarLiga($torneoId, $ids),
            'suizo'       => $this->generarRondaSuiza($torneoId, $ids, 1),
            default       => throw new ApiException('Formato de torneo desconocido.', 500),
        };

        $this->tournaments->updateEstado($torneoId, 'en_curso');

        foreach ($ids as $uid) {
            $this->notifications->create(
                $uid,
                'torneo_inicio',
                'El torneo arrancó',
                "\"{$t['titulo']}\" ya está en curso. Se generó {$descripcion}.",
                '/pages/nav/tournament/detalle.html?id=' . $torneoId
            );
        }

        return ['mensaje' => "¡Torneo iniciado! Se generó {$descripcion}."];
    }

    private function generarEliminacion(int $torneoId, array $ids): void
    {
        $n = count($ids);
        $bracketSize = 1;
        while ($bracketSize < $n) {
            $bracketSize *= 2;
        }
        $numRondas = (int) log($bracketSize, 2);

        $slots = $ids;
        while (count($slots) < $bracketSize) {
            $slots[] = null; // byes al final — el orden ya viene mezclado al azar
        }

        $filas = [];
        $byeWinners = []; // orden (ronda 1) => id del que avanza sin jugar

        $numPartidosR1 = intdiv($bracketSize, 2);
        for ($i = 0; $i < $numPartidosR1; $i++) {
            $p1 = $slots[$i * 2];
            $p2 = $slots[$i * 2 + 1];

            if ($p1 === null && $p2 === null) {
                continue;
            }
            if ($p1 === null || $p2 === null) {
                $ganador = $p1 ?? $p2;
                $filas[] = [
                    'torneo_id' => $torneoId, 'ronda' => 1,
                    'ronda_etiqueta' => $this->etiquetaRonda(1, $numRondas), 'orden' => $i,
                    'participante1_id' => $p1, 'participante2_id' => $p2,
                    'ganador_id' => $ganador, 'estado' => 'wo',
                ];
                $byeWinners[$i] = $ganador;
            } else {
                $filas[] = [
                    'torneo_id' => $torneoId, 'ronda' => 1,
                    'ronda_etiqueta' => $this->etiquetaRonda(1, $numRondas), 'orden' => $i,
                    'participante1_id' => $p1, 'participante2_id' => $p2, 'estado' => 'pendiente',
                ];
            }
        }

        for ($r = 2; $r <= $numRondas; $r++) {
            $numPartidos = intdiv($bracketSize, 2 ** $r);
            for ($i = 0; $i < $numPartidos; $i++) {
                $filas[] = [
                    'torneo_id' => $torneoId, 'ronda' => $r,
                    'ronda_etiqueta' => $this->etiquetaRonda($r, $numRondas), 'orden' => $i,
                    'estado' => 'pendiente',
                ];
            }
        }

        $this->matches->insertMany($filas);

        foreach ($byeWinners as $orden => $ganadorId) {
            $this->avanzarGanador($torneoId, 1, $orden, $ganadorId, $numRondas);
        }
    }

    private function etiquetaRonda(int $ronda, int $totalRondas): string
    {
        return match ($totalRondas - $ronda) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Cuartos de final',
            3 => 'Octavos de final',
            4 => '16avos de final',
            5 => '32avos de final',
            default => "Ronda {$ronda}",
        };
    }

    private function avanzarGanador(int $torneoId, int $rondaActual, int $ordenActual, int $ganadorId, int $numRondasTotal): void
    {
        if ($rondaActual >= $numRondasTotal) {
            return; // era la final
        }
        $siguiente = $this->matches->findByTorneoRondaOrden($torneoId, $rondaActual + 1, intdiv($ordenActual, 2));
        if (!$siguiente) {
            return;
        }
        $slot = $ordenActual % 2 === 0 ? 1 : 2;
        $this->matches->setParticipantSlot((int) $siguiente['id'], $slot, $ganadorId);
    }

    /** Método del círculo: todos contra todos, sin que nadie repita
      * fecha (cada "ronda" acá es una fecha de la liga). */
    private function generarLiga(int $torneoId, array $ids): void
    {
        $jugadores = $ids;
        if (count($jugadores) % 2 !== 0) {
            $jugadores[] = null; // bye ficticio para parejar
        }
        $n = count($jugadores);
        $numFechas = $n - 1;
        $filas = [];

        for ($fecha = 1; $fecha <= $numFechas; $fecha++) {
            $orden = 0;
            for ($i = 0; $i < $n / 2; $i++) {
                $local = $jugadores[$i];
                $visita = $jugadores[$n - 1 - $i];
                if ($local !== null && $visita !== null) {
                    $filas[] = [
                        'torneo_id' => $torneoId, 'ronda' => $fecha,
                        'ronda_etiqueta' => "Fecha {$fecha}", 'orden' => $orden++,
                        'participante1_id' => $local, 'participante2_id' => $visita, 'estado' => 'pendiente',
                    ];
                }
            }
            $ultimo = array_pop($jugadores);
            array_splice($jugadores, 1, 0, [$ultimo]);
        }

        $this->matches->insertMany($filas);
    }

    private function numRondasSuizo(int $n): int
    {
        return max(1, (int) ceil(log(max($n, 2), 2)));
    }

    private function generarRondaSuiza(int $torneoId, array $participantIds, int $ronda): void
    {
        $puntos      = $this->calcularPuntosSuizo($torneoId, $participantIds);
        $yaJugaron   = $this->calcularEnfrentamientos($torneoId);
        $descansaron = $this->calcularByes($torneoId);

        $ordenados = $participantIds;
        usort($ordenados, fn ($a, $b) => ($puntos[$b] ?? 0) <=> ($puntos[$a] ?? 0) ?: $a <=> $b);

        $filas = [];
        $orden = 0;

        if (count($ordenados) % 2 !== 0) {
            $candidato = null;
            for ($i = count($ordenados) - 1; $i >= 0; $i--) {
                if (!in_array($ordenados[$i], $descansaron, true)) {
                    $candidato = $ordenados[$i];
                    break;
                }
            }
            $candidato ??= end($ordenados);
            $ordenados = array_values(array_diff($ordenados, [$candidato]));

            $filas[] = [
                'torneo_id' => $torneoId, 'ronda' => $ronda, 'ronda_etiqueta' => "Ronda {$ronda}",
                'orden' => $orden++, 'participante1_id' => $candidato, 'participante2_id' => null,
                'ganador_id' => $candidato, 'estado' => 'wo',
            ];
        }

        $usados = [];
        foreach ($ordenados as $a) {
            if (in_array($a, $usados, true)) {
                continue;
            }
            $rival = null;
            foreach ($ordenados as $b) {
                if ($b === $a || in_array($b, $usados, true)) {
                    continue;
                }
                if (!in_array($b, $yaJugaron[$a] ?? [], true)) {
                    $rival = $b;
                    break;
                }
            }
            if ($rival === null) {
                // no queda nadie sin enfrentar antes — aceptamos repetir cruce
                foreach ($ordenados as $b) {
                    if ($b !== $a && !in_array($b, $usados, true)) {
                        $rival = $b;
                        break;
                    }
                }
            }
            if ($rival === null) {
                continue;
            }

            $usados[] = $a;
            $usados[] = $rival;
            $filas[] = [
                'torneo_id' => $torneoId, 'ronda' => $ronda, 'ronda_etiqueta' => "Ronda {$ronda}",
                'orden' => $orden++, 'participante1_id' => $a, 'participante2_id' => $rival, 'estado' => 'pendiente',
            ];
        }

        $this->matches->insertMany($filas);
    }

    /** @return array<int,int> id de usuario => puntos acumulados (3/1/0) */
    private function calcularPuntosSuizo(int $torneoId, array $participantIds): array
    {
        $puntos = array_fill_keys($participantIds, 0);
        foreach ($this->matches->listByTournament($torneoId) as $p) {
            if ($p['estado'] === 'pendiente') {
                continue;
            }
            $ganador = $p['ganador_id'] ? (int) $p['ganador_id'] : null;
            $p1 = $p['participante1_id'] ? (int) $p['participante1_id'] : null;
            $p2 = $p['participante2_id'] ? (int) $p['participante2_id'] : null;

            if ($ganador !== null) {
                $puntos[$ganador] = ($puntos[$ganador] ?? 0) + 3;
            } elseif ($p1 !== null && $p2 !== null) {
                $puntos[$p1] = ($puntos[$p1] ?? 0) + 1;
                $puntos[$p2] = ($puntos[$p2] ?? 0) + 1;
            }
        }
        return $puntos;
    }

    /** @return array<int,array<int,int>> id de usuario => ids de rivales ya enfrentados */
    private function calcularEnfrentamientos(int $torneoId): array
    {
        $rivales = [];
        foreach ($this->matches->listByTournament($torneoId) as $p) {
            if ($p['participante1_id'] && $p['participante2_id']) {
                $a = (int) $p['participante1_id'];
                $b = (int) $p['participante2_id'];
                $rivales[$a][] = $b;
                $rivales[$b][] = $a;
            }
        }
        return $rivales;
    }

    /** @return array<int,int> ids de usuario que ya tuvieron un bye */
    private function calcularByes(int $torneoId): array
    {
        $byes = [];
        foreach ($this->matches->listByTournament($torneoId) as $p) {
            if ($p['estado'] === 'wo' && $p['participante2_id'] === null && $p['participante1_id']) {
                $byes[] = (int) $p['participante1_id'];
            }
        }
        return $byes;
    }

    // ═══════════════════════════════════════════════════════════
    //  CARGAR RESULTADOS
    // ═══════════════════════════════════════════════════════════

    /** @return array<string,mixed> */
    public function recordResult(int $torneoId, int $organizerId, int $partidoId, ?int $r1, ?int $r2): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ((int) $t['organizador_id'] !== $organizerId) {
            throw new ApiException('Solo el organizador puede cargar resultados.', 403);
        }
        if ($t['estado'] !== 'en_curso') {
            throw new ApiException('Este torneo no está en curso.', 409);
        }

        $partido = $this->matches->findById($partidoId);
        if (!$partido || (int) $partido['torneo_id'] !== $torneoId) {
            throw new ApiException('No encontramos ese partido.', 404);
        }
        if ($partido['estado'] !== 'pendiente') {
            throw new ApiException('Ese partido ya tiene un resultado cargado.', 409);
        }
        if (!$partido['participante1_id'] || !$partido['participante2_id']) {
            throw new ApiException('Ese partido todavía no tiene los dos rivales definidos.', 409);
        }
        if ($r1 === null || $r2 === null || $r1 < 0 || $r2 < 0) {
            throw new ApiException('Ingresá un resultado válido para ambos participantes.', 400);
        }
        if ($r1 === $r2 && $t['formato'] === 'eliminacion') {
            throw new ApiException('En eliminación directa no puede haber empates: alguien tiene que ganar.', 400);
        }

        $ganadorId = $r1 > $r2 ? (int) $partido['participante1_id'] : ($r2 > $r1 ? (int) $partido['participante2_id'] : null);

        $this->matches->updateResult($partidoId, $r1, $r2, $ganadorId, 'jugado');

        if ($t['formato'] === 'eliminacion' && $ganadorId) {
            $numRondas = $this->matches->maxRonda($torneoId);
            $this->avanzarGanador($torneoId, (int) $partido['ronda'], (int) $partido['orden'], $ganadorId, $numRondas);
        }

        $this->revisarFinalizacion($torneoId, $t);

        return ['mensaje' => 'Resultado cargado.'];
    }

    /** @return array<string,mixed> */
    public function generateNextRound(int $torneoId, int $organizerId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ((int) $t['organizador_id'] !== $organizerId) {
            throw new ApiException('Solo el organizador puede generar la siguiente ronda.', 403);
        }
        if ($t['formato'] !== 'suizo') {
            throw new ApiException('Esta acción es solo para torneos con formato suizo.', 400);
        }
        if ($t['estado'] !== 'en_curso') {
            throw new ApiException('Este torneo no está en curso.', 409);
        }

        $rondaActual = $this->matches->maxRonda($torneoId);
        if ($this->matches->hayPendientes($torneoId, $rondaActual)) {
            throw new ApiException('Todavía hay partidos sin resultado en la ronda actual.', 409);
        }

        $participantes = $this->tournaments->listParticipants($torneoId);
        $ids = array_map(fn ($p) => (int) $p['id'], $participantes);
        $totalRondas = $this->numRondasSuizo(count($ids));

        if ($rondaActual >= $totalRondas) {
            throw new ApiException('Ya se jugaron todas las rondas de este torneo.', 409);
        }

        $this->generarRondaSuiza($torneoId, $ids, $rondaActual + 1);
        $this->revisarFinalizacion($torneoId, $t);

        return ['mensaje' => 'Siguiente ronda generada.'];
    }

    private function revisarFinalizacion(int $torneoId, array $t): void
    {
        if ($t['formato'] === 'suizo') {
            $participantes = $this->tournaments->listParticipants($torneoId);
            $totalRondas   = $this->numRondasSuizo(count($participantes));
            $rondaActual   = $this->matches->maxRonda($torneoId);
            if ($rondaActual >= $totalRondas && !$this->matches->hayPendientes($torneoId)) {
                $this->finalizar($torneoId, $t);
            }
            return;
        }

        if (!$this->matches->hayPendientes($torneoId)) {
            $this->finalizar($torneoId, $t);
        }
    }

    private function finalizar(int $torneoId, array $t): void
    {
        $this->tournaments->updateEstado($torneoId, 'finalizado');

        $participantes = $this->tournaments->listParticipants($torneoId);
        $ids = array_map(fn ($p) => (int) $p['id'], $participantes);
        $this->users->incrementTorneosJugados($ids);

        $campeon = $this->determinarCampeon($torneoId, $t, $participantes);
        if ($campeon) {
            $this->users->incrementTorneosGanados($campeon['id']);
            $this->notifications->create(
                $campeon['id'],
                'torneo_campeon',
                '¡Ganaste el torneo! 🏆',
                "Te consagraste campeón de \"{$t['titulo']}\".",
                '/pages/nav/tournament/detalle.html?id=' . $torneoId
            );
        }
    }

    /** @return array{id:int,nombre:string}|null */
    private function determinarCampeon(int $torneoId, array $t, array $participantesRows): ?array
    {
        if ($t['formato'] === 'eliminacion') {
            $numRondas = $this->matches->maxRonda($torneoId);
            foreach ($this->matches->listByTournament($torneoId) as $p) {
                if ((int) $p['ronda'] === $numRondas && $p['ganador_id']) {
                    return ['id' => (int) $p['ganador_id'], 'nombre' => $p['ganador_nombre']];
                }
            }
            return null;
        }

        $tabla = $this->tablaPosiciones($torneoId, $participantesRows);
        if (empty($tabla)) {
            return null;
        }
        return ['id' => $tabla[0]['id'], 'nombre' => $tabla[0]['nombre']];
    }

    // ═══════════════════════════════════════════════════════════
    //  LECTURA (calendario / llaves / posiciones / resultados)
    // ═══════════════════════════════════════════════════════════

    /** Vista combinada para detalle.html y las páginas de formatos/.
      * @return array<string,mixed> */
    public function getMatchesView(int $torneoId, ?int $viewerId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ($t['visibilidad'] === 'privado' && (!$viewerId || (int) $t['organizador_id'] !== $viewerId)) {
            throw new ApiException('Este torneo es privado.', 403);
        }

        $rondas = $this->agruparPorRonda($torneoId);

        $resultado = [
            'formato'            => $t['formato'],
            'estado'             => $t['estado'],
            'rondas'             => $rondas,
            'campeon'            => null,
            'standings'          => null,
            'ronda_actual'       => empty($rondas) ? 0 : end($rondas)['numero'],
            'total_rondas_suizo' => null,
        ];

        if ($t['formato'] === 'eliminacion') {
            if (!empty($rondas)) {
                $ultima = end($rondas);
                if (count($ultima['partidos']) === 1 && $ultima['partidos'][0]['estado'] !== 'pendiente') {
                    $p = $ultima['partidos'][0];
                    if ($p['ganador_id']) {
                        $resultado['campeon'] = ['id' => $p['ganador_id'], 'nombre' => $p['ganador_nombre'] ?? null];
                    }
                }
            }
            return $resultado;
        }

        $participantes = $this->tournaments->listParticipants($torneoId);
        $tabla = $this->tablaPosiciones($torneoId, $participantes);
        $resultado['standings'] = $tabla;

        if ($t['formato'] === 'suizo') {
            $resultado['total_rondas_suizo'] = $this->numRondasSuizo(count($participantes));
        }
        if ($t['estado'] === 'finalizado' && !empty($tabla)) {
            $resultado['campeon'] = ['id' => $tabla[0]['id'], 'nombre' => $tabla[0]['nombre']];
        }

        return $resultado;
    }

    /** Para resultados.html: partidos listos para cargar resultado +
      * historial de los ya decididos. Solo lo puede pedir el organizador.
      * @return array<string,mixed> */
    public function getResultsManager(int $torneoId, int $organizerId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ((int) $t['organizador_id'] !== $organizerId) {
            throw new ApiException('Solo el organizador puede gestionar los resultados.', 403);
        }

        $pendientes = [];
        $historial  = [];
        foreach ($this->matches->listByTournament($torneoId) as $p) {
            $f = $this->formatPartido($p);
            if ($p['estado'] === 'pendiente' && $p['participante1_id'] && $p['participante2_id']) {
                $pendientes[] = $f;
            } elseif ($p['estado'] !== 'pendiente') {
                $historial[] = $f;
            }
        }
        usort($historial, fn ($a, $b) => $b['id'] <=> $a['id']);

        $puedeGenerarSiguienteRonda = false;
        if ($t['formato'] === 'suizo' && $t['estado'] === 'en_curso') {
            $rondaActual = $this->matches->maxRonda($torneoId);
            if (!$this->matches->hayPendientes($torneoId, $rondaActual)) {
                $participantes = $this->tournaments->listParticipants($torneoId);
                $puedeGenerarSiguienteRonda = $rondaActual < $this->numRondasSuizo(count($participantes));
            }
        }

        return [
            'titulo'                        => $t['titulo'],
            'formato'                       => $t['formato'],
            'estado'                        => $t['estado'],
            'pendientes'                    => $pendientes,
            'historial'                     => $historial,
            'puede_generar_siguiente_ronda' => $puedeGenerarSiguienteRonda,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function agruparPorRonda(int $torneoId): array
    {
        $porRonda = [];
        foreach ($this->matches->listByTournament($torneoId) as $p) {
            $porRonda[(int) $p['ronda']][] = $this->formatPartido($p);
        }
        ksort($porRonda);

        $rondas = [];
        foreach ($porRonda as $numero => $lista) {
            usort($lista, fn ($a, $b) => $a['orden'] <=> $b['orden']);
            $rondas[] = [
                'numero'   => $numero,
                'etiqueta' => $lista[0]['ronda_etiqueta'] ?? "Ronda {$numero}",
                'partidos' => $lista,
            ];
        }
        return $rondas;
    }

    /** Liga y suizo comparten esta tabla (3 por victoria, 1 por empate).
      * @param array<int,array<string,mixed>> $participantesRows filas con al menos 'id','nombre','usuario'
      * @return array<int,array<string,mixed>> */
    private function tablaPosiciones(int $torneoId, array $participantesRows): array
    {
        $tabla = [];
        foreach ($participantesRows as $p) {
            $tabla[(int) $p['id']] = [
                'id' => (int) $p['id'], 'nombre' => $p['nombre'], 'usuario' => $p['usuario'],
                'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'pts' => 0,
            ];
        }

        foreach ($this->matches->listByTournament($torneoId) as $p) {
            if ($p['estado'] === 'pendiente') {
                continue;
            }
            $ganador = $p['ganador_id'] ? (int) $p['ganador_id'] : null;
            $p1 = $p['participante1_id'] ? (int) $p['participante1_id'] : null;
            $p2 = $p['participante2_id'] ? (int) $p['participante2_id'] : null;

            foreach ([$p1, $p2] as $lado) {
                if ($lado === null || !isset($tabla[$lado])) {
                    continue;
                }
                $tabla[$lado]['pj']++;
                if ($ganador === $lado) {
                    $tabla[$lado]['pg']++;
                    $tabla[$lado]['pts'] += 3;
                } elseif ($ganador === null && $p1 !== null && $p2 !== null) {
                    $tabla[$lado]['pe']++;
                    $tabla[$lado]['pts'] += 1;
                } else {
                    $tabla[$lado]['pp']++;
                }
            }
        }

        $tabla = array_values($tabla);
        usort($tabla, fn ($a, $b) => $b['pts'] <=> $a['pts'] ?: $b['pg'] <=> $a['pg'] ?: $a['id'] <=> $b['id']);

        return $tabla;
    }

    /** @return array<string,mixed> */
    private function formatPartido(array $p): array
    {
        return [
            'id'                    => (int) $p['id'],
            'ronda'                 => (int) $p['ronda'],
            'ronda_etiqueta'        => $p['ronda_etiqueta'],
            'orden'                 => (int) $p['orden'],
            'participante1_id'      => $p['participante1_id'] ? (int) $p['participante1_id'] : null,
            'participante1_nombre'  => $p['p1_nombre'] ?? null,
            'participante1_usuario' => $p['p1_usuario'] ?? null,
            'participante2_id'      => $p['participante2_id'] ? (int) $p['participante2_id'] : null,
            'participante2_nombre'  => $p['p2_nombre'] ?? null,
            'participante2_usuario' => $p['p2_usuario'] ?? null,
            'resultado1'            => $p['resultado1'] !== null ? (int) $p['resultado1'] : null,
            'resultado2'            => $p['resultado2'] !== null ? (int) $p['resultado2'] : null,
            'ganador_id'            => $p['ganador_id'] ? (int) $p['ganador_id'] : null,
            'ganador_nombre'        => $p['ganador_nombre'] ?? null,
            'estado'                => $p['estado'],
            'fecha_programada'      => $p['fecha_programada'],
        ];
    }
}
