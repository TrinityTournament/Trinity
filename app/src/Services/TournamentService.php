<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Core\Env;
use Trinity\Core\Mailer;
use Trinity\Core\WhatsAppClient;
use Trinity\Models\TournamentModel;
use Trinity\Models\UserModel;

class TournamentService
{
    private const ESTADOS_VALIDOS = ['en_creacion', 'abierto', 'en_curso', 'finalizado', 'cancelado'];

    private const ESTADO_LABELS = [
        'en_creacion' => 'En creación',
        'abierto'     => 'Abierto',
        'en_curso'    => 'En curso',
        'finalizado'  => 'Finalizado',
        'cancelado'   => 'Cancelado',
    ];

    private const FORMATOS_VALIDOS = ['liga', 'eliminacion', 'suizo'];

    // El form de crear.html manda las etiquetas visibles (radios/<select>
    // sin `value`), no los códigos internos — se traducen acá.
    private const FORMATO_LABELS = [
        'liga'                  => 'liga',
        'eliminación directa'   => 'eliminacion',
        'eliminacion directa'   => 'eliminacion',
        'sistema suizo'         => 'suizo',
    ];

    private const VISIBILIDAD_LABELS = [
        'público'                          => 'publico',
        'publico'                          => 'publico',
        'privado (solo con invitación)'    => 'privado',
        'privado (solo con invitacion)'    => 'privado',
        'privado'                          => 'privado',
    ];

    private TournamentModel $tournaments;
    private UserModel $users;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->tournaments   = new TournamentModel();
        $this->users         = new UserModel();
        $this->notifications = new NotificationService();
    }

    /** Crea un torneo. $publicar=true → queda 'abierto' y (si es público)
      * dispara el aviso por email/WhatsApp a los usuarios interesados en
      * ese deporte. $publicar=false → 'en_creacion' (borrador, el
      * organizador puede invitar gente antes de abrirlo).
      *
      * @return array<string,mixed> */
    public function create(array $organizador, string $titulo, string $deporte, string $descripcion, string $formatoInput, int $cupo, string $fecha, string $visibilidadInput, ?string $bannerUrl, bool $publicar): array
    {
        $titulo = trim($titulo);
        if (mb_strlen($titulo) < 3) {
            throw new ApiException('El nombre del torneo tiene que tener al menos 3 caracteres.', 400);
        }
        if (mb_strlen($titulo) > 120) {
            throw new ApiException('El nombre del torneo es demasiado largo (máximo 120 caracteres).', 400);
        }

        $deporte = trim($deporte);
        if (!$deporte) {
            throw new ApiException('Elegí una disciplina.', 400);
        }

        if ($cupo < 2) {
            throw new ApiException('El cupo mínimo es de 2 participantes.', 400);
        }
        if ($cupo > 1000) {
            throw new ApiException('El cupo máximo es de 1000 participantes.', 400);
        }

        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            throw new ApiException('La fecha de inicio no es válida.', 400);
        }
        if ($fechaObj < new \DateTime('today')) {
            throw new ApiException('La fecha de inicio no puede ser en el pasado.', 400);
        }

        $formato = self::FORMATO_LABELS[mb_strtolower(trim($formatoInput))] ?? null;
        if (!$formato || !in_array($formato, self::FORMATOS_VALIDOS, true)) {
            throw new ApiException('El formato de competencia no es válido.', 400);
        }

        $visibilidad = self::VISIBILIDAD_LABELS[mb_strtolower(trim($visibilidadInput))] ?? 'publico';

        $descripcion = trim($descripcion);
        if (mb_strlen($descripcion) > 1000) {
            throw new ApiException('La descripción es demasiado larga (máximo 1000 caracteres).', 400);
        }

        $estado = $publicar ? 'abierto' : 'en_creacion';

        $torneoId = $this->tournaments->create([
            'organizador_id'    => $organizador['id'],
            'titulo'            => $titulo,
            'deporte'           => $deporte,
            'descripcion'       => $descripcion ?: null,
            'formato'           => $formato,
            'max_participantes' => $cupo,
            'fecha_inicio'      => $fecha,
            'visibilidad'       => $visibilidad,
            'banner_url'        => $bannerUrl ?: null,
            'estado'            => $estado,
        ]);

        $resultado = [
            'id'      => $torneoId,
            'estado'  => $estado,
            'mensaje' => $publicar ? 'Torneo creado y publicado.' : 'Torneo guardado como borrador.',
        ];

        // Solo se avisa por email/WhatsApp si quedó público y abierto —
        // un torneo privado se maneja por invitación (ver invite()).
        if ($publicar && $visibilidad === 'publico') {
            try {
                $resultado['notificaciones'] = $this->notify(
                    $titulo,
                    $descripcion ?: "Nuevo torneo de {$deporte}.",
                    $fecha,
                    $deporte,
                    'deporte',
                    ''
                );
            } catch (\Throwable $e) {
                // Un fallo al notificar no debe tirar abajo la creación ya confirmada.
                error_log('[TournamentService::create] Error notificando: ' . $e->getMessage());
                $resultado['notificaciones'] = ['ok' => false, 'mensaje' => 'El torneo se creó pero no se pudo avisar a los usuarios.'];
            }
        }

        return $resultado;
    }

    /** Ficha completa de un torneo para detalle.html: info + lista de
      * participantes + flags de estado relativos a quien la pide
      * (¿ya está inscripto? ¿es el organizador? ¿hay cupo?).
      * @return array<string,mixed> */
    public function getDetail(int $torneoId, ?int $viewerId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }

        if ($t['visibilidad'] === 'privado' && (!$viewerId || (int) $t['organizador_id'] !== $viewerId)) {
            // Un torneo privado solo lo puede ver su organizador (o alguien
            // invitado — de momento no distinguimos eso acá, ver nota en
            // CAMBIOS.md sobre el alcance de las invitaciones).
            throw new ApiException('Este torneo es privado.', 403);
        }

        $participantes = $this->tournaments->listParticipants($torneoId);
        $inscritos     = count($participantes);
        $esOrganizador = $viewerId !== null && (int) $t['organizador_id'] === $viewerId;
        $yaInscripto   = $viewerId !== null && $this->tournaments->alreadyParticipant($torneoId, $viewerId);

        return [
            'id'                 => (int) $t['id'],
            'titulo'             => $t['titulo'],
            'deporte'            => $t['deporte'],
            'descripcion'        => $t['descripcion'],
            'formato'            => $t['formato'],
            'formato_label'      => self::FORMATO_DISPLAY[$t['formato']] ?? ucfirst($t['formato']),
            'max_participantes'  => $t['max_participantes'] !== null ? (int) $t['max_participantes'] : null,
            'fecha_inicio'       => $t['fecha_inicio'],
            'visibilidad'        => $t['visibilidad'],
            'banner_url'         => $t['banner_url'],
            'estado'             => $t['estado'],
            'estado_label'       => self::ESTADO_LABELS[$t['estado']] ?? ucfirst($t['estado']),
            'organizador_id'     => (int) $t['organizador_id'],
            'organizador_nombre' => $t['organizador_nombre'],
            'organizador_usuario' => $t['organizador_usuario'],
            'emoji'              => $this->emojiForSport($t['deporte']),
            'inscritos'          => $inscritos,
            'participantes'      => $participantes,
            'es_organizador'     => $esOrganizador,
            'ya_inscripto'       => $yaInscripto,
            'logueado'           => $viewerId !== null,
            'cupo_lleno'         => $t['max_participantes'] !== null && $inscritos >= (int) $t['max_participantes'],
            'inscripcion_abierta' => $t['estado'] === 'abierto',
        ];
    }

    /** El organizador saca a un participante antes de iniciar el torneo.
      * @return array<string,mixed> */
    public function removeParticipant(int $torneoId, int $organizerId, int $participantId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ((int) $t['organizador_id'] !== $organizerId) {
            throw new ApiException('Solo el organizador puede hacer esto.', 403);
        }
        if (!in_array($t['estado'], ['en_creacion', 'abierto'], true)) {
            throw new ApiException('El torneo ya empezó: no se puede sacar participantes.', 409);
        }

        $ok = $this->tournaments->unregisterParticipant($torneoId, $participantId);
        if (!$ok) {
            throw new ApiException('Ese usuario no estaba inscripto.', 404);
        }

        return ['mensaje' => 'Participante eliminado del torneo.'];
    }

    /** @return array<string,mixed> */
    public function register(int $torneoId, int $userId): array
    {
        $t = $this->tournaments->findById($torneoId);
        if (!$t) {
            throw new ApiException('No encontramos ese torneo.', 404);
        }
        if ($t['estado'] !== 'abierto') {
            throw new ApiException('Este torneo no está abierto para inscripciones.', 409);
        }
        if ((int) $t['organizador_id'] === $userId) {
            throw new ApiException('Sos el organizador de este torneo: no podés inscribirte como participante.', 400);
        }
        if ($this->tournaments->alreadyParticipant($torneoId, $userId)) {
            throw new ApiException('Ya estás inscripto en este torneo.', 409);
        }
        if ($t['max_participantes'] !== null && $this->tournaments->countParticipants($torneoId) >= (int) $t['max_participantes']) {
            throw new ApiException('El cupo de este torneo ya está completo.', 409);
        }

        $this->tournaments->registerParticipant($torneoId, $userId);

        $this->notifications->create(
            (int) $t['organizador_id'],
            'torneo_inscripcion',
            'Nueva inscripción',
            "Un nuevo participante se inscribió en \"{$t['titulo']}\".",
            '/pages/nav/tournament/detalle.html?id=' . $torneoId
        );

        return ['mensaje' => '¡Listo! Quedaste inscripto en el torneo.'];
    }

    /** @return array<string,mixed> */
    public function unregister(int $torneoId, int $userId): array
    {
        $ok = $this->tournaments->unregisterParticipant($torneoId, $userId);
        if (!$ok) {
            throw new ApiException('No estabas inscripto en ese torneo.', 404);
        }
        return ['mensaje' => 'Cancelaste tu inscripción.'];
    }

    /** Torneos en los que el usuario participa (no organiza).
      * @return array<string,mixed> */
    public function myParticipations(int $userId): array
    {
        $torneos = $this->tournaments->forParticipant($userId);

        foreach ($torneos as &$t) {
            $t['id']                = (int) $t['id'];
            $t['inscritos']         = (int) $t['inscritos'];
            $t['max_participantes'] = $t['max_participantes'] !== null ? (int) $t['max_participantes'] : null;
            $t['estado_label']      = self::ESTADO_LABELS[$t['estado']] ?? ucfirst($t['estado']);
            $t['formato_label']     = self::FORMATO_DISPLAY[$t['formato']] ?? ucfirst($t['formato']);
            $t['emoji']             = $this->emojiForSport($t['deporte']);
        }
        unset($t);

        return ['torneos' => $torneos];
    }

    /** @return array<string,mixed> */
    public function myInvitations(int $userId): array
    {
        $invitaciones = $this->tournaments->pendingInvitationsForUser($userId);
        foreach ($invitaciones as &$i) {
            $i['id']        = (int) $i['id'];
            $i['torneo_id'] = (int) $i['torneo_id'];
            $i['emoji']     = $this->emojiForSport($i['deporte']);
        }
        unset($i);

        return ['invitaciones' => $invitaciones];
    }

    /** El invitado acepta o rechaza una invitación a torneo.
      * @return array<string,mixed> */
    public function resolveInvitation(int $invitacionId, int $userId, bool $aceptar): array
    {
        $inv = $this->tournaments->findInvitation($invitacionId);
        if (!$inv || (int) $inv['invitado_id'] !== $userId) {
            throw new ApiException('No encontramos esa invitación.', 404);
        }
        if ($inv['estado'] !== 'pendiente') {
            throw new ApiException('Esa invitación ya fue respondida.', 409);
        }

        if (!$aceptar) {
            $this->tournaments->resolveInvitation($invitacionId, 'rechazada');
            return ['mensaje' => 'Rechazaste la invitación.'];
        }

        if ($inv['torneo_estado'] !== 'abierto' && $inv['torneo_estado'] !== 'en_creacion') {
            throw new ApiException('Ese torneo ya no admite inscripciones.', 409);
        }
        if ($this->tournaments->alreadyParticipant((int) $inv['torneo_id'], $userId)) {
            $this->tournaments->resolveInvitation($invitacionId, 'aceptada');
            return ['mensaje' => 'Ya estabas inscripto en ese torneo.'];
        }

        $this->tournaments->registerParticipant((int) $inv['torneo_id'], $userId);
        $this->tournaments->resolveInvitation($invitacionId, 'aceptada');

        return ['mensaje' => "¡Te uniste a \"{$inv['torneo_titulo']}\"!"];
    }

    /**
     * @return array<string,mixed>
     */
    public function myTournaments(int $userId, ?string $estado): array
    {
        $estadoFiltro = ($estado && in_array($estado, self::ESTADOS_VALIDOS, true)) ? $estado : null;
        $torneos      = $this->tournaments->forOrganizer($userId, $estadoFiltro);

        foreach ($torneos as &$t) {
            $t['id']            = (int) $t['id'];
            $t['inscritos']     = (int) $t['inscritos'];
            $t['max_participantes'] = $t['max_participantes'] !== null ? (int) $t['max_participantes'] : null;
            $t['estado_label']  = self::ESTADO_LABELS[$t['estado']] ?? ucfirst($t['estado']);
            $t['formato_label'] = self::FORMATO_DISPLAY[$t['formato']] ?? ucfirst($t['formato']);
        }
        unset($t);

        return ['torneos' => $torneos];
    }

    /**
     * @return array<string,mixed>
     */
    public function allForAdmin(): array
    {
        $torneos = $this->tournaments->allWithOrganizer();

        foreach ($torneos as &$t) {
            $t['id']           = (int) $t['id'];
            $t['estado_label'] = self::ESTADO_LABELS[$t['estado']] ?? ucfirst($t['estado']);
        }
        unset($t);

        return ['torneos' => $torneos];
    }

    /** @return array<string,mixed> */
    public function invite(int $organizadorId, string $orgNombre, string $orgUsuario, int $torneoId, int $invitadoId): array
    {
        if (!$torneoId || !$invitadoId) {
            throw new ApiException('Se requieren torneo_id e invitado_id.', 400);
        }
        if ($invitadoId === $organizadorId) {
            throw new ApiException('No podés invitarte a vos mismo.', 400);
        }

        $torneo = $this->tournaments->findOwnedByOrganizer($torneoId, $organizadorId);
        if (!$torneo) {
            throw new ApiException('No encontramos ese torneo entre los que estás organizando.', 404);
        }

        if ($torneo['estado'] !== 'en_creacion') {
            $labels = [
                'abierto'    => 'abierto para inscripciones',
                'en_curso'   => 'en curso',
                'finalizado' => 'finalizado',
                'cancelado'  => 'cancelado',
            ];
            $label = $labels[$torneo['estado']] ?? $torneo['estado'];
            throw new ApiException("Solo podés invitar cuando el torneo está en proceso de creación. Este torneo está {$label}.", 409);
        }

        $invitado = $this->users->findById($invitadoId);
        if (!$invitado) {
            throw new ApiException('El usuario al que querés invitar no existe.', 404);
        }

        if ($this->tournaments->alreadyInvited($torneoId, $invitadoId)) {
            throw new ApiException('Este usuario ya fue invitado a ese torneo.', 409);
        }
        if ($this->tournaments->alreadyParticipant($torneoId, $invitadoId)) {
            throw new ApiException('Este usuario ya está inscripto en ese torneo.', 409);
        }

        $this->tournaments->insertInvitation($torneoId, $organizadorId, $invitadoId);

        $this->notifications->create(
            $invitadoId,
            'torneo_invitacion',
            "Te invitaron al torneo \"{$torneo['titulo']}\"",
            "{$orgNombre} (@{$orgUsuario}) te envió una invitación. ¡Ingresá para aceptarla o rechazarla!"
        );

        return ['mensaje' => "Invitación enviada a {$invitado['nombre']}."];
    }

    /** Anuncio masivo de torneo (no depende de la tabla `torneos`: solo
      * necesita la lista de usuarios objetivo y sus preferencias).
      * @return array<string,mixed> */
    public function notify(string $titulo, string $desc, string $fecha, string $deporte, string $target, string $testPhone): array
    {
        if (!$titulo || !$desc) {
            throw new ApiException('Se requieren "titulo" y "descripcion".', 400);
        }

        $emoji = $this->emojiForSport($deporte);

        if ($target === 'test') {
            if (!$testPhone) {
                throw new ApiException('Indicá "test_phone" para el modo test.', 400);
            }

            $waMensaje = $this->buildWhatsAppMessage($emoji, $titulo, $desc, $fecha, $deporte);
            $ok = WhatsAppClient::send($testPhone, $waMensaje);

            return [
                'ok'      => $ok,
                'mensaje' => $ok ? "Mensaje de prueba enviado a {$testPhone}." : 'El bot de WhatsApp no respondió.',
                'mode'    => 'test',
            ];
        }

        $usuarios = $target === 'deporte' && $deporte
            ? $this->users->findTargetedBySport($deporte)
            : $this->users->allForBroadcast();

        if (empty($usuarios)) {
            return ['mensaje' => 'No hay usuarios que cumplan el criterio.', 'enviados' => 0];
        }

        $mensajeBase = $desc;
        if ($fecha) {
            $mensajeBase .= " — Fecha: {$fecha}";
        }
        if ($deporte) {
            $mensajeBase .= " — Disciplina: {$deporte}.";
        }

        $linkHtml  = "<p style='margin-top:16px;'><a href='" . Env::get('APP_URL', '') . "' style='color:#c0000a;font-weight:bold;'>Ver torneos en Trinity →</a></p>";
        $htmlEmail = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#0f0000;color:#f5f0f0;border-radius:10px;overflow:hidden;'>
                <div style='background:#c0000a;padding:16px 24px;'>
                    <h1 style='margin:0;font-size:20px;letter-spacing:3px;'>TRINITY</h1>
                </div>
                <div style='padding:24px;'>
                    <h2 style='margin:0 0 8px;font-size:17px;'>{$emoji} {$titulo}</h2>
                    <p style='margin:0;color:#c0a0a0;font-size:14px;'>{$mensajeBase}</p>
                    {$linkHtml}
                </div>
                <div style='padding:12px 24px;border-top:1px solid rgba(180,0,0,0.2);font-size:11px;color:#8a6a6a;'>
                    Recibiste esta notificación de Trinity. Para administrar tus preferencias, ingresá a Configuración.
                </div>
            </div>
        ";

        $waMensaje = $this->buildWhatsAppMessage($emoji, $titulo, $desc, $fecha, $deporte);

        $phonesWa = [];
        $ok       = 0;

        foreach ($usuarios as $u) {
            try {
                $this->notifications->create((int) $u['id'], 'torneo_anuncio', $titulo, $mensajeBase);
                $ok++;

                if (!empty($u['email'])) {
                    Mailer::send($u['email'], "{$emoji} {$titulo}", $htmlEmail);
                }

                if (!empty($u['notif_whatsapp']) && !empty($u['telefono'])) {
                    $phoneClean = preg_replace('/[^0-9]/', '', $u['telefono']);
                    if (strlen($phoneClean) >= 7) {
                        $phonesWa[] = $phoneClean;
                    }
                }
            } catch (\Throwable $e) {
                error_log('[TournamentService::notify] Error usuario ' . $u['id'] . ': ' . $e->getMessage());
            }
        }

        $waSent = !empty($phonesWa) && WhatsAppClient::broadcast($phonesWa, $waMensaje);

        return [
            'enviados'   => $ok,
            'wa_enviado' => $waSent,
            'wa_count'   => count($phonesWa),
            'mensaje'    => "Notificación enviada a {$ok} usuario(s). WhatsApp: " . count($phonesWa) . ' opt-in(s).',
        ];
    }

    private function emojiForSport(string $deporte): string
    {
        return match (strtolower($deporte)) {
            'fútbol', 'futbol'  => '⚽',
            'brawl stars'       => '🎯',
            'clash royale'      => '⚔️',
            'fortnite'          => '🏗️',
            'free fire'         => '🔥',
            'minecraft'         => '🧱',
            default             => '🏆',
        };
    }

    private function buildWhatsAppMessage(string $emoji, string $titulo, string $desc, string $fecha, string $deporte): string
    {
        $msg = "{$emoji} *TRINITY* — Nuevo torneo\n\n*{$titulo}*\n{$desc}";
        if ($fecha) {
            $msg .= "\n📅 Fecha: {$fecha}";
        }
        if ($deporte) {
            $msg .= "\n🎮 Disciplina: {$deporte}";
        }
        $msg .= "\n\n¡Inscribite ahora en " . Env::get('APP_URL', '') . '!';
        return $msg;
    }

    // ── Reverso de FORMATO_LABELS (código interno -> etiqueta visible) ──
    private const FORMATO_DISPLAY = [
        'liga'        => 'Liga',
        'eliminacion' => 'Eliminación directa',
        'suizo'       => 'Sistema suizo',
    ];

    /** Búsqueda pública de torneos (pages/nav/tournament/buscar.html).
      * $formatoInput/$estadoInput llegan como texto visible (lo que manda
      * el <select> del form), no como código interno — se traducen acá,
      * igual que ya hace create() con FORMATO_LABELS. */
    public function searchPublic(
        string $texto,
        string $deporte,
        string $formatoInput,
        string $estadoInput,
        int $page
    ): array {
        $page    = max(1, $page);
        $perPage = 12;

        $formato = $formatoInput !== ''
            ? (self::FORMATO_LABELS[mb_strtolower(trim($formatoInput))] ?? '')
            : '';

        $estadoMap = array_flip(array_map('mb_strtolower', self::ESTADO_LABELS));
        $estado = $estadoInput !== ''
            ? ($estadoMap[mb_strtolower(trim($estadoInput))] ?? '')
            : '';

        $resultado = $this->tournaments->searchPublic(
            trim($texto),
            trim($deporte),
            $formato,
            $estado,
            $page,
            $perPage
        );

        foreach ($resultado['torneos'] as &$t) {
            $t['id']              = (int) $t['id'];
            $t['max_participantes'] = $t['max_participantes'] !== null ? (int) $t['max_participantes'] : null;
            $t['inscritos']       = (int) $t['inscritos'];
            $t['estado_label']    = self::ESTADO_LABELS[$t['estado']] ?? ucfirst($t['estado']);
            $t['formato_label']   = self::FORMATO_DISPLAY[$t['formato']] ?? ucfirst($t['formato']);
            $t['emoji']           = $this->emojiForSport($t['deporte']);
        }
        unset($t);

        return [
            'torneos'  => $resultado['torneos'],
            'total'    => $resultado['total'],
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }
}
