<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Core\BotStorageClient;
use Trinity\Models\NotificationModel;
use Trinity\Models\TeamModel;
use Trinity\Models\UserModel;

class TeamService
{
    private TeamModel $teams;
    private UserModel $users;
    private NotificationModel $notifications;

    public function __construct()
    {
        $this->teams         = new TeamModel();
        $this->users         = new UserModel();
        $this->notifications = new NotificationModel();
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(int $userId, array $body): array
    {
        $nombre      = trim((string) ($body['nombre'] ?? ''));
        $disciplina  = trim((string) ($body['disciplina'] ?? ''));
        $descripcion = trim((string) ($body['descripcion'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new ApiException('El nombre del equipo es obligatorio (máximo 100 caracteres).', 400);
        }
        if ($disciplina === '' || mb_strlen($disciplina) > 60) {
            throw new ApiException('Elegí una disciplina para el equipo.', 400);
        }
        if (mb_strlen($descripcion) > 500) {
            throw new ApiException('La descripción no puede superar los 500 caracteres.', 400);
        }

        $logoId = $this->subirLogoSiHay($body['logo'] ?? null);

        $id = $this->teams->create($nombre, $disciplina, $descripcion !== '' ? $descripcion : null, $logoId, $userId);

        return ['id' => $id, 'mensaje' => '¡Equipo creado!'];
    }

    /** @return array<string,mixed> */
    public function myTeams(int $userId): array
    {
        $equipos = $this->teams->listForUser($userId);
        foreach ($equipos as &$e) {
            $e['id']       = (int) $e['id'];
            $e['miembros'] = (int) $e['miembros'];
        }
        unset($e);

        return ['equipos' => $equipos];
    }

    /** @return array<string,mixed> */
    public function getDetail(int $equipoId, int $viewerId): array
    {
        $e = $this->teams->findById($equipoId);
        if (!$e) {
            throw new ApiException('No encontramos ese equipo.', 404);
        }

        $miembros  = $this->teams->listMembers($equipoId);
        $miMembresia = $this->teams->findMembership($equipoId, $viewerId);

        return [
            'id'               => (int) $e['id'],
            'nombre'           => $e['nombre'],
            'disciplina'       => $e['disciplina'],
            'descripcion'      => $e['descripcion'],
            'logo_url'         => $e['logo_url'],
            'capitan_id'       => (int) $e['capitan_id'],
            'capitan_nombre'   => $e['capitan_nombre'],
            'capitan_usuario'  => $e['capitan_usuario'],
            'creado_en'        => $e['creado_en'],
            'miembros'         => array_values(array_filter($miembros, fn ($m) => $m['estado'] === 'activo')),
            'invitados'        => array_values(array_filter($miembros, fn ($m) => $m['estado'] === 'invitado')),
            'soy_capitan'      => (int) $e['capitan_id'] === $viewerId,
            'mi_estado'        => $miMembresia['estado'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    public function invite(int $equipoId, int $capitanId, string $usuarioObjetivo, string $rol): array
    {
        $this->assertCapitan($equipoId, $capitanId);

        if (!in_array($rol, ['jugador', 'suplente'], true)) {
            $rol = 'jugador';
        }

        $usuario = $this->users->findByUsuario(ltrim($usuarioObjetivo, '@'));
        if (!$usuario) {
            throw new ApiException('No encontramos a ese usuario.', 404);
        }
        if ((int) $usuario['id'] === $capitanId) {
            throw new ApiException('Ya sos el capitán de este equipo.', 400);
        }
        if ($this->teams->findMembership($equipoId, (int) $usuario['id'])) {
            throw new ApiException('Ese usuario ya es miembro o ya tiene una invitación pendiente.', 409);
        }

        $this->teams->invite($equipoId, (int) $usuario['id'], $rol);

        $equipo = $this->teams->findById($equipoId);
        $this->notifications->create(
            (int) $usuario['id'],
            'equipo_invitacion',
            'Invitación a equipo',
            "Te invitaron a unirte a \"{$equipo['nombre']}\".",
            '/pages/teams/equipo.html?id=' . $equipoId
        );

        return ['mensaje' => "Invitación enviada a @{$usuario['usuario']}."];
    }

    /** @return array<string,mixed> */
    public function respondInvite(int $equipoId, int $userId, bool $aceptar): array
    {
        $membresia = $this->teams->findMembership($equipoId, $userId);
        if (!$membresia || $membresia['estado'] !== 'invitado') {
            throw new ApiException('No tenés una invitación pendiente para ese equipo.', 404);
        }

        if ($aceptar) {
            $this->teams->activateMembership($equipoId, $userId);
            return ['mensaje' => '¡Te uniste al equipo!'];
        }

        $this->teams->removeMember($equipoId, $userId);
        return ['mensaje' => 'Rechazaste la invitación.'];
    }

    /** El capitán expulsa a alguien, o un miembro se va por su cuenta.
      * @return array<string,mixed> */
    public function removeMember(int $equipoId, int $solicitanteId, int $objetivoId): array
    {
        $equipo = $this->teams->findById($equipoId);
        if (!$equipo) {
            throw new ApiException('No encontramos ese equipo.', 404);
        }

        $esCapitan = (int) $equipo['capitan_id'] === $solicitanteId;
        if (!$esCapitan && $solicitanteId !== $objetivoId) {
            throw new ApiException('Solo el capitán puede expulsar a otros miembros.', 403);
        }
        if ((int) $equipo['capitan_id'] === $objetivoId) {
            throw new ApiException('El capitán no puede salir del equipo. Si querés disolverlo, borrá el equipo.', 400);
        }

        $this->teams->removeMember($equipoId, $objetivoId);

        return ['mensaje' => $solicitanteId === $objetivoId ? 'Saliste del equipo.' : 'Expulsaste al miembro del equipo.'];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function update(int $equipoId, int $capitanId, array $body): array
    {
        $equipo = $this->assertCapitan($equipoId, $capitanId);

        $nombre      = trim((string) ($body['nombre'] ?? $equipo['nombre']));
        $disciplina  = trim((string) ($body['disciplina'] ?? $equipo['disciplina']));
        $descripcion = trim((string) ($body['descripcion'] ?? $equipo['descripcion'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new ApiException('El nombre del equipo es obligatorio (máximo 100 caracteres).', 400);
        }
        if ($disciplina === '' || mb_strlen($disciplina) > 60) {
            throw new ApiException('Elegí una disciplina para el equipo.', 400);
        }

        $logoId = $equipo['logo_url'];
        if (array_key_exists('logo', $body) && $body['logo'] !== null) {
            $nuevoLogo = $this->subirLogoSiHay($body['logo']);
            if ($nuevoLogo !== null) {
                if ($logoId) {
                    BotStorageClient::deletePhoto($logoId);
                }
                $logoId = $nuevoLogo;
            }
        }

        $this->teams->update($equipoId, $nombre, $disciplina, $descripcion !== '' ? $descripcion : null, $logoId);

        return ['mensaje' => 'Equipo actualizado.'];
    }

    /** @return array<string,mixed> */
    public function delete(int $equipoId, int $capitanId): array
    {
        $equipo = $this->assertCapitan($equipoId, $capitanId);

        if ($equipo['logo_url']) {
            BotStorageClient::deletePhoto($equipo['logo_url']);
        }
        $this->teams->delete($equipoId);

        return ['mensaje' => 'Equipo eliminado.'];
    }

    /** @return array<string,mixed> */
    private function assertCapitan(int $equipoId, int $userId): array
    {
        $equipo = $this->teams->findById($equipoId);
        if (!$equipo) {
            throw new ApiException('No encontramos ese equipo.', 404);
        }
        if ((int) $equipo['capitan_id'] !== $userId) {
            throw new ApiException('Solo el capitán del equipo puede hacer esto.', 403);
        }
        return $equipo;
    }

    private function subirLogoSiHay(?string $logo): ?string
    {
        if (empty($logo)) {
            return null;
        }
        if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,/', $logo)) {
            throw new ApiException('Formato de imagen inválido.', 400);
        }
        if (strlen($logo) > 2 * 1024 * 1024) {
            throw new ApiException('La imagen es demasiado grande.', 400);
        }
        $id = BotStorageClient::uploadPhoto($logo);
        if ($id === null) {
            throw new ApiException('No se pudo subir el logo ahora mismo. Probá de nuevo en un rato.', 502);
        }
        return $id;
    }
}
