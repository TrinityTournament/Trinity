<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Core\SessionManager;
use Trinity\Core\WhatsAppClient;
use Trinity\Models\ContactModel;
use Trinity\Models\UserModel;

class ContactService
{
    private ContactModel $contact;
    private UserModel $users;

    public function __construct()
    {
        $this->contact = new ContactModel();
        $this->users   = new UserModel();
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function send(array $body): array
    {
        $nombre   = trim((string) ($body['nombre'] ?? ''));
        $contacto = trim((string) ($body['contacto'] ?? ''));
        $asunto   = trim((string) ($body['asunto'] ?? ''));
        $mensaje  = trim((string) ($body['mensaje'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 120) {
            throw new ApiException('Ingresá tu nombre.', 400);
        }
        if ($contacto === '' || mb_strlen($contacto) > 180) {
            throw new ApiException('Ingresá un correo o teléfono de contacto.', 400);
        }
        if ($asunto === '' || mb_strlen($asunto) > 160) {
            throw new ApiException('Ingresá el asunto de tu consulta.', 400);
        }
        if ($mensaje === '' || mb_strlen($mensaje) > 2000) {
            throw new ApiException('Escribí tu mensaje (hasta 2000 caracteres).', 400);
        }

        $usuario   = SessionManager::user();
        $usuarioId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        $this->contact->create($usuarioId, $nombre, $contacto, $asunto, $mensaje);
        $this->avisarAdmins($nombre, $asunto);

        return ['mensaje' => '¡Gracias! Recibimos tu mensaje y te vamos a responder a la brevedad.'];
    }

    private function avisarAdmins(string $nombre, string $asunto): void
    {
        $adminPhones = $this->users->adminPhones();
        if (empty($adminPhones)) {
            return;
        }
        $msg = "✉️ *TRINITY* — Nuevo mensaje de contacto\n\n"
             . "De: {$nombre}\n"
             . "Asunto: {$asunto}\n\n"
             . "Revisalo en la base de datos (tabla mensajes_contacto).";
        WhatsAppClient::broadcast($adminPhones, $msg);
    }
}
