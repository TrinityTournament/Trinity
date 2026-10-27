<?php

namespace Trinity\Services\GameAccounts;

use Trinity\Core\ApiException;
use Trinity\Core\Env;

/**
 * Free Fire — vincula la cuenta del jugador y devuelve sus datos a la página.
 *
 * Se conecta a TU API de Free Fire. Todo lo que depende de ella vive acá, en
 * dos lugares, para que adaptarla sea tocar un solo archivo:
 *
 *   · Configuración (variables de entorno, ver .env.example):
 *       FF_API_URL  URL base, sin barra final. Ej: https://mi-api.com/freefireinfo
 *       FF_API_KEY  Clave de la API (opcional; se envía como ?key=…)
 *
 *   · fetchPlayerInfo()  →  llama a la API y devuelve su JSON tal cual.
 *     normalizar()       →  convierte ese JSON en el formato fijo que usa el
 *                           front (el `perfil` de abajo). Si tu API cambia de
 *                           forma, solo se toca normalizar().
 *
 * Contrato que asume hoy (el del prototipo FF.js):
 *   GET {FF_API_URL}/bhau?uid=<UID>&region=<REGION>&key=<KEY>
 *   →  { basicInfo:     { nickname, level, accountId, region, maxRank, csMaxRank },
 *        clanBasicInfo: { clanName, clanLevel, clanId, memberNum, capacity } }
 *
 * Las estadísticas por modo (/stats?gamemode=br|cs&matchmode=RANKED) todavía
 * no se consultan: se agregan acá cuando se conozca la forma de esa respuesta.
 *
 * La cuenta vinculada se guarda como "UID:REGION" en cuentas_videojuego
 * (la API necesita las dos cosas para identificar a un jugador).
 */
class FreeFireService extends AbstractGameAccountService
{
    /** Ruta de "datos del jugador" dentro de FF_API_URL. */
    private const PLAYER_INFO_PATH = '/bhau';

    protected function gameKey(): string
    {
        return 'freefire';
    }

    /** @return array{uid:?string,region:?string} */
    public function getAccount(int $userId): array
    {
        [$uid, $region] = $this->parseIdentifier($this->getLinkedIdentifier($userId));
        return ['uid' => $uid, 'region' => $region];
    }

    /** @return array{uid:string,region:string,nombre:?string} */
    public function save(int $userId, string $uidCrudo, string $regionCrudo): array
    {
        [$uid, $region] = $this->validar($uidCrudo, $regionCrudo);

        $perfil = $this->normalizar($this->fetchPlayerInfo($uid, $region), $uid, $region);
        if ($perfil === null) {
            throw new ApiException('No se encontró ese UID de Free Fire en esa región.', 404);
        }

        $this->link($userId, $uid . ':' . $region);

        return ['uid' => $uid, 'region' => $region, 'nombre' => $perfil['nombre']];
    }

    /**
     * Sin parámetros usa la cuenta vinculada del usuario logueado; con
     * ?uid=…&region=… consulta esa cuenta puntual. Requiere sesión.
     *
     * @return array{perfil:array<string,mixed>}
     */
    public function stats(?int $userId, string $uidQuery, string $regionQuery): array
    {
        // Siempre con sesión: la API de Free Fire se consulta con la clave del
        // proyecto, y sin esto cualquiera podría gastar su cuota desde afuera.
        if (!$userId) {
            throw new ApiException('No autenticado.', 401);
        }

        if ($uidQuery !== '') {
            [$uid, $region] = $this->validar($uidQuery, $regionQuery);
        } else {
            [$uid, $region] = $this->parseIdentifier($this->getLinkedIdentifier($userId));
            if (!$uid || !$region) {
                throw new ApiException('Todavía no vinculaste tu cuenta de Free Fire.', 404);
            }
        }

        $perfil = $this->normalizar($this->fetchPlayerInfo($uid, $region), $uid, $region);
        if ($perfil === null) {
            throw new ApiException('No se encontró ese UID de Free Fire en esa región.', 404);
        }

        return ['perfil' => $perfil];
    }

    // ── Conexión con la API ────────────────────────────────────

    /**
     * Llama a la API y devuelve su JSON ya decodificado.
     * Nunca incluye la clave en mensajes de error ni en logs.
     *
     * @return array<string,mixed>
     */
    private function fetchPlayerInfo(string $uid, string $region): array
    {
        $base = rtrim(trim((string) Env::get('FF_API_URL', '')), '/');
        if ($base === '') {
            throw new ApiException('La integración con Free Fire todavía no está configurada.', 503);
        }

        $query = ['uid' => $uid, 'region' => $region];
        $key   = trim((string) Env::get('FF_API_KEY', ''));
        if ($key !== '') {
            $query['key'] = $key;
        }

        $ch = curl_init($base . self::PLAYER_INFO_PATH . '?' . http_build_query($query));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode === 0) {
            throw new ApiException('No se pudo contactar la API de Free Fire.', 502);
        }
        if ($httpCode === 401 || $httpCode === 403) {
            error_log('[FreeFireService] La API rechazó la clave (HTTP ' . $httpCode . '). Revisá FF_API_KEY.');
            throw new ApiException('La API de Free Fire rechazó la consulta.', 502);
        }
        if ($httpCode >= 500) {
            throw new ApiException('La API de Free Fire no está disponible ahora mismo.', 502);
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * JSON de la API → `perfil` que consume el front. Devuelve null si la API
     * no encontró al jugador (no trae basicInfo).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    private function normalizar(array $data, string $uid, string $region): ?array
    {
        $b = $data['basicInfo'] ?? null;
        if (!is_array($b) || empty($b['nickname'])) {
            return null;
        }

        $c    = is_array($data['clanBasicInfo'] ?? null) ? $data['clanBasicInfo'] : [];
        $clan = !empty($c['clanName'])
            ? [
                'nombre'    => $c['clanName'],
                'nivel'     => $c['clanLevel'] ?? null,
                'miembros'  => $c['memberNum'] ?? null,
                'capacidad' => $c['capacity']  ?? null,
            ]
            : null;

        return [
            'nombre'             => $b['nickname'],
            'uid'                => (string) ($b['accountId'] ?? $uid),
            'region'             => $b['region'] ?? $region,
            'nivel'              => $b['level']     ?? null,
            'rangoMax'           => $b['maxRank']   ?? null,
            'rangoMaxEscuadras'  => $b['csMaxRank'] ?? null,
            'clan'               => $clan,
        ];
    }

    // ── Validación / formato de la cuenta ──────────────────────

    /** @return array{0:string,1:string} [uid, region] */
    private function validar(string $uidCrudo, string $regionCrudo): array
    {
        $uid = trim($uidCrudo);
        if (!preg_match('/^\d{5,15}$/', $uid)) {
            throw new ApiException('El UID de Free Fire son solo números (entre 5 y 15 dígitos).', 400);
        }

        $region = strtoupper(trim($regionCrudo));
        if (!preg_match('/^[A-Z]{2,4}$/', $region)) {
            throw new ApiException('Elegí la región de tu cuenta de Free Fire.', 400);
        }

        return [$uid, $region];
    }

    /** "219110511:BR" → ['219110511', 'BR'] (o [null, null] si no hay cuenta). */
    private function parseIdentifier(?string $identificador): array
    {
        if (!$identificador || !str_contains($identificador, ':')) {
            return [null, null];
        }
        [$uid, $region] = explode(':', $identificador, 2);
        return [$uid, $region];
    }
}
