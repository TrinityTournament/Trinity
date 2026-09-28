<?php

namespace Trinity\Services;

use Trinity\Core\ApiException;
use Trinity\Core\BotStorageClient;
use Trinity\Core\WhatsAppClient;
use Trinity\Models\NewsModel;
use Trinity\Models\UserModel;

/** Flujo de noticias:
  *   - Un admin que crea una noticia la publica directo (estado='publicada').
  *   - Un organizador que crea una noticia queda en estado='pendiente'
  *     hasta que un admin la aprueba o rechaza (mismo patrón que
  *     solicitudes_organizador: resuelto_por / resuelto_en).
  *   - Cualquier otro rol no puede crear noticias. */
class NewsService
{
    private const CATEGORIAS_VALIDAS = ['torneos', 'actualizaciones', 'resultados', 'comunidad', 'anuncios'];

    private NewsModel $news;
    private UserModel $users;

    public function __construct()
    {
        $this->news  = new NewsModel();
        $this->users = new UserModel();
    }

    /** @return array<string,mixed> */
    public function listPublished(?string $categoria): array
    {
        if ($categoria !== null && !in_array($categoria, self::CATEGORIAS_VALIDAS, true)) {
            $categoria = null;
        }
        return ['noticias' => $this->news->listPublished($categoria, 60)];
    }

    /** @return array<string,mixed> */
    public function getPublished(int $id): array
    {
        $n = $this->news->findById($id);
        if (!$n || $n['estado'] !== 'publicada') {
            throw new ApiException('No encontramos esa noticia.', 404);
        }
        return $this->format($n);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(int $autorId, string $rol, array $body): array
    {
        if (!in_array($rol, ['admin', 'organizador'], true)) {
            throw new ApiException('No tenés permiso para publicar noticias.', 403);
        }

        $titulo    = trim((string) ($body['titulo'] ?? ''));
        $resumen   = trim((string) ($body['resumen'] ?? ''));
        $contenido = trim((string) ($body['contenido'] ?? ''));
        $categoria = (string) ($body['categoria'] ?? 'anuncios');

        if ($titulo === '' || mb_strlen($titulo) > 160) {
            throw new ApiException('El título es obligatorio (máximo 160 caracteres).', 400);
        }
        if ($contenido === '') {
            throw new ApiException('El contenido no puede estar vacío.', 400);
        }
        if (!in_array($categoria, self::CATEGORIAS_VALIDAS, true)) {
            throw new ApiException('Categoría inválida.', 400);
        }
        if (mb_strlen($resumen) > 280) {
            throw new ApiException('El resumen no puede superar los 280 caracteres.', 400);
        }

        $imagenId = null;
        if (!empty($body['imagen'])) {
            if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,/', (string) $body['imagen'])) {
                throw new ApiException('Formato de imagen inválido.', 400);
            }
            if (strlen((string) $body['imagen']) > 2 * 1024 * 1024) {
                throw new ApiException('La imagen es demasiado grande.', 400);
            }
            $imagenId = BotStorageClient::uploadPhoto((string) $body['imagen']);
            if ($imagenId === null) {
                throw new ApiException('No se pudo subir la imagen ahora mismo. Probá de nuevo en un rato.', 502);
            }
        }

        $estado = $rol === 'admin' ? 'publicada' : 'pendiente';

        $id = $this->news->create([
            'autor_id'   => $autorId,
            'titulo'     => $titulo,
            'resumen'    => $resumen !== '' ? $resumen : null,
            'contenido'  => $contenido,
            'categoria'  => $categoria,
            'imagen_url' => $imagenId,
            'estado'     => $estado,
        ]);

        if ($estado === 'pendiente') {
            $this->avisarAdmins($id, $titulo);
        }

        return [
            'id'     => $id,
            'estado' => $estado,
            'mensaje' => $estado === 'publicada'
                ? 'Noticia publicada.'
                : 'Tu noticia quedó a la espera de aprobación de un admin.',
        ];
    }

    /** @return array<string,mixed> */
    public function myRequests(int $autorId): array
    {
        return ['noticias' => $this->news->listByAuthor($autorId)];
    }

    /** @return array<string,mixed> */
    public function pending(): array
    {
        return ['noticias' => $this->news->listPending()];
    }

    /** @return array<string,mixed> */
    public function resolve(int $id, int $adminId, bool $aprobar): array
    {
        $n = $this->news->findById($id);
        if (!$n || $n['estado'] !== 'pendiente') {
            throw new ApiException('No encontramos esa solicitud pendiente.', 404);
        }

        $this->news->resolve($id, $aprobar ? 'publicada' : 'rechazada', $adminId);

        return ['mensaje' => $aprobar ? 'Noticia publicada.' : 'Noticia rechazada.'];
    }

    private function avisarAdmins(int $noticiaId, string $titulo): void
    {
        $adminPhones = $this->users->adminPhones();
        if (empty($adminPhones)) {
            return;
        }
        $msg = "📰 *TRINITY* — Nueva noticia para aprobar\n\n"
             . "\"{$titulo}\"\n\n"
             . "Revisala en el panel de administración (Noticias → Pendientes).";
        WhatsAppClient::broadcast($adminPhones, $msg);
    }

    /** @return array<string,mixed> */
    private function format(array $n): array
    {
        return [
            'id'            => (int) $n['id'],
            'titulo'        => $n['titulo'],
            'resumen'       => $n['resumen'],
            'contenido'     => $n['contenido'],
            'categoria'     => $n['categoria'],
            'imagen_url'    => $n['imagen_url'],
            'publicado_en'  => $n['publicado_en'],
            'autor_nombre'  => $n['autor_nombre'],
            'autor_usuario' => $n['autor_usuario'],
        ];
    }
}
