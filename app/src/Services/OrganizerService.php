<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Core\WhatsAppClient;
use Trinity\Models\OrganizerRequestModel;
use Trinity\Models\UserModel;

/** Flujo para pasar de 'participante' a 'organizador':
  *   1. El usuario ya tiene que tener un teléfono verificado en su
  *      cuenta (si no, se lo manda primero a Credenciales — ver
  *      wireCredentials() en edit.js, se reusa tal cual).
  *   2. Acepta los términos y condiciones (request() se llama recién
  *      ahí, así que "llamar a request()" ES "aceptar los términos").
  *   3. Se les avisa por WhatsApp a todos los admins con teléfono
  *      cargado, con instrucciones para responder /aprobar N o
  *      /rechazar N.
  *   4. El bot de WhatsApp (WhatsApp/main.js) detecta ese comando y
  *      llama a resolveByPhone() vía app/tournaments/organizer/resolve.php,
  *      autenticado con el mismo WA_SECRET que ya usa el resto del sistema. */
class OrganizerService
{
    private UserModel $users;
    private OrganizerRequestModel $requests;

    public function __construct()
    {
        $this->users    = new UserModel();
        $this->requests = new OrganizerRequestModel();
    }

    /** @return array{id:int, estado:string} */
    public function request(int $userId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new ApiException('Usuario no encontrado.', 404);
        }

        $rol = $user['rol'] ?? 'participante';
        if (in_array($rol, ['organizador', 'admin'], true)) {
            throw new ApiException('Ya tenés permisos para crear torneos.', 409);
        }

        if (empty($user['telefono'])) {
            throw new ApiException(
                'Necesitás un teléfono verificado en tu cuenta antes de solicitar ser organizador.',
                400
            );
        }

        $pendiente = $this->requests->findPendienteDeUsuario($userId);
        if ($pendiente) {
            throw new ApiException('Ya tenés una solicitud pendiente de aprobación.', 409);
        }

        $id = $this->requests->create($userId);

        $adminPhones = $this->users->adminPhones();
        if (!empty($adminPhones)) {
            $msg = "🛡️ *TRINITY* — Nueva solicitud de organizador\n\n"
                 . "Usuario: {$user['nombre']} (@{$user['usuario']})\n\n"
                 . "Para aprobar, respondé:\n/aprobar {$id}\n\n"
                 . "Para rechazar:\n/rechazar {$id}";
            WhatsAppClient::broadcast($adminPhones, $msg);
        }

        return ['id' => $id, 'estado' => 'pendiente'];
    }

    /** @return array{rol:string, solicitud: array{id:int,estado:string,creado_en:string}|null} */
    public function status(int $userId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new ApiException('Usuario no encontrado.', 404);
        }

        $ultima = $this->requests->findUltimaDeUsuario($userId);

        return [
            'rol'       => $user['rol'] ?? 'participante',
            'telefono'  => $user['telefono'] ?? null,
            'solicitud' => $ultima ? [
                'id'        => (int) $ultima['id'],
                'estado'    => $ultima['estado'],
                'creado_en' => $ultima['creado_en'],
            ] : null,
        ];
    }

    // Llamado por resolve.php cuando el bot detecta /aprobar o /rechazar.
    // Devuelve el texto que el bot le contesta al admin en WhatsApp.
    public function resolveByPhone(string $telefono, string $accion, int $solicitudId): string
    {
        if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
            return 'Comando no reconocido.';
        }

        $telNorm = preg_replace('/[^0-9]/', '', $telefono);
        $admin   = $this->users->findByTelefono($telNorm);

        if (!$admin || ($admin['rol'] ?? '') !== 'admin') {
            return 'No tenés permisos de administrador en Trinity.';
        }

        return $this->aplicarResolucion((int) $admin['id'], $solicitudId, $accion)['mensaje'];
    }

    /** Lista para el panel web de administración (pendientes primero).
      *
      * @return array{solicitudes: array<int,array<string,mixed>>, pendientes:int} */
    public function listForAdmin(): array
    {
        $filas = $this->requests->listForAdmin();

        $pendientes = 0;
        foreach ($filas as &$f) {
            $f['id']         = (int) $f['id'];
            $f['usuario_id'] = (int) $f['usuario_id'];
            $f['torneos_jugados'] = (int) $f['torneos_jugados'];
            if ($f['estado'] === 'pendiente') {
                $pendientes++;
            }
        }
        unset($f);

        return ['solicitudes' => $filas, 'pendientes' => $pendientes];
    }

    /** Misma resolución que hace el bot, pero desde el panel web: el admin
      * ya está autenticado por sesión, así que no hace falta buscarlo por teléfono.
      *
      * @return array{mensaje:string, estado:string} */
    public function resolveByAdmin(int $adminId, int $solicitudId, string $accion): array
    {
        if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
            throw new ApiException('Acción inválida. Usá "aprobar" o "rechazar".', 400);
        }
        if ($solicitudId <= 0) {
            throw new ApiException('Se requiere el "id" de la solicitud.', 400);
        }

        $r = $this->aplicarResolucion($adminId, $solicitudId, $accion);
        if (!$r['ok']) {
            throw new ApiException($r['mensaje'], $r['codigo']);
        }

        return ['mensaje' => $r['mensaje'], 'estado' => (string) $r['estado']];
    }

    private function aplicarResolucion(int $adminId, int $solicitudId, string $accion): array
    {
        $solicitud = $this->requests->find($solicitudId);
        if (!$solicitud) {
            return ['ok' => false, 'mensaje' => "No encontré la solicitud #{$solicitudId}.", 'codigo' => 404, 'estado' => null];
        }
        if ($solicitud['estado'] !== 'pendiente') {
            return ['ok' => false, 'mensaje' => "La solicitud #{$solicitudId} ya había sido " . $solicitud['estado'] . '.', 'codigo' => 409, 'estado' => null];
        }

        $nuevoEstado = $accion === 'aprobar' ? 'aprobado' : 'rechazado';

        // resolver() solo actualiza si la solicitud sigue 'pendiente': si dos
        // admins responden a la vez (web y WhatsApp), solo gana uno.
        if (!$this->requests->resolver($solicitudId, $nuevoEstado, $adminId)) {
            return ['ok' => false, 'mensaje' => "La solicitud #{$solicitudId} ya había sido resuelta.", 'codigo' => 409, 'estado' => null];
        }

        $solicitante = $this->users->findById((int) $solicitud['usuario_id']);
        $usuarioTag  = $solicitante ? '@' . $solicitante['usuario'] : "usuario #{$solicitud['usuario_id']}";

        if ($nuevoEstado === 'aprobado') {
            // Solo se asciende a quien sigue siendo 'participante': si mientras
            // tanto lo hicieron admin, aprobar no tiene que degradarlo.
            if ($solicitante && ($solicitante['rol'] ?? 'participante') === 'participante') {
                $this->users->setRole((int) $solicitud['usuario_id'], 'organizador');
            }
            if ($solicitante && !empty($solicitante['telefono'])) {
                WhatsAppClient::send(
                    $solicitante['telefono'],
                    '*TRINITY* — ¡Tu solicitud para ser organizador fue aprobada! Ya podés crear torneos.'
                );
            }
            return ['ok' => true, 'mensaje' => "Aprobado. {$usuarioTag} ya puede crear torneos.", 'codigo' => 200, 'estado' => $nuevoEstado];
        }

        if ($solicitante && !empty($solicitante['telefono'])) {
            WhatsAppClient::send(
                $solicitante['telefono'],
                '*TRINITY* — Tu solicitud para ser organizador fue rechazada. Podés volver a solicitarlo más adelante.'
            );
        }
        return ['ok' => true, 'mensaje' => "Rechazado. Se avisó a {$usuarioTag}.", 'codigo' => 200, 'estado' => $nuevoEstado];
    }
}