<?php
// ============================================================================
// KutPod · Opciones del Plugin de Fediverso
// ============================================================================

// Evitar acceso directo
if (!defined('KUTPOD_VERSION')) {
    exit;
}

// Cargar las utilidades de federación si no están cargadas
require_once __DIR__ . '/../../activitypub.php';

// Procesar el guardado si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_form'] ?? '';

    if ($action === 'fediverse_settings') {
        $handle = trim($_POST['admin_mastodon_handle'] ?? '');
        $url = trim($_POST['admin_mastodon_url'] ?? '');
        
        // Si el usuario pegó la etiqueta HTML entera desde Mastodon, extraer solo la URL
        if (preg_match('/href=["\']([^"\']+)["\']/', $url, $m)) {
            $url = $m[1];
        }
        
        kp_update_setting('admin_mastodon_url', $url);

        if ($handle) {
            $actor = kp_ap_resolve_webfinger($handle);
            if ($actor && !empty($actor['inbox'])) {
                kp_update_setting('admin_mastodon_handle', $handle);
                kp_update_setting('admin_mastodon_actor', $actor['id']);
                kp_update_setting('admin_mastodon_inbox', $actor['inbox']);
                $msg = __('fedi_msg_linked', 'Cuenta Mastodon enlazada correctamente');
            } else {
                $msg = __('fedi_msg_resolve_err', 'Error: No se pudo resolver la cuenta de Mastodon en el Fediverso');
            }
        } else {
            kp_update_setting('admin_mastodon_handle', '');
            kp_update_setting('admin_mastodon_actor', '');
            kp_update_setting('admin_mastodon_inbox', '');
            $msg = $url ? __('fedi_msg_verified_updated', 'Ajustes de verificación actualizados') : __('fedi_msg_unlinked', 'Cuenta Mastodon desvinculada');
        }
        
        setcookie('kp_flash', $msg, time() + 30, '/');
        header('Location: ' . admin_url('plugins?action=settings&plugin=fediverse'));
        exit;
    }

    if ($action === 'fediverse_reset') {
        $pdo = kp_db();
        $pdo->exec("DELETE FROM ap_followers");
        $pdo->exec("DELETE FROM ap_outbox");
        $pdo->exec("DELETE FROM ap_delivery");
        $pdo->exec("DELETE FROM ap_keys");
        
        setcookie('kp_flash', __('fedi_msg_reset', 'Fediverso reiniciado (claves, seguidores y bandeja de salida eliminados)'), time() + 30, '/');
        header('Location: ' . admin_url('plugins?action=settings&plugin=fediverse'));
        exit;
    }
}

$admin_mastodon_handle = kp_setting('admin_mastodon_handle', '');
$admin_mastodon_url = kp_setting('admin_mastodon_url', '');
?>

<div class="col" style="gap:24px; max-width:800px; margin:0 auto">

  <!-- Card: Ajustes Mastodon/Admin -->
  <div class="card span-12">
    <div class="card-head" style="margin-bottom:18px">
      <div>
        <h2 style="margin:0"><?= __('fedi_admin_link_title', 'Enlace de Administrador (Mastodon)') ?></h2>
        <p class="help" style="margin:4px 0 0 0"><?= __('fedi_admin_link_sub', 'Permite recibir notificaciones privadas y verificar la propiedad en la red federada.') ?></p>
      </div>
    </div>
    
    <form method="POST">
      <input type="hidden" name="_form" value="fediverse_settings">
      
      <div class="field">
        <label class="label"><?= __('fedi_masto_address', 'Tu dirección de Mastodon') ?></label>
        <input class="input" name="admin_mastodon_handle" id="admin_mastodon_handle" value="<?= e($admin_mastodon_handle) ?>" placeholder="@nombre_usuario@mastodon.social">
        <div class="help" style="margin-top:4px">
          <?= __('fedi_masto_address_help', 'KutPod te enviará un Mensaje Directo privado cada vez que alguien comente en tus episodios federados.') ?>
        </div>
      </div>
      
      <div class="field" style="margin-top:16px">
        <label class="label"><?= __('fedi_masto_url', 'URL de tu Perfil de Mastodon') ?></label>
        <input class="input" name="admin_mastodon_url" value="<?= e($admin_mastodon_url) ?>" placeholder="https://mastodon.social/@nombre_usuario">
        <div class="help" style="margin-top:4px">
          <?= __('fedi_masto_url_help', 'Sirve para añadir la etiqueta invisible :tag en la cabecera pública de KutPod, logrando la verificación verde de tu sitio en Mastodon.', ['tag' => '<code>rel="me"</code>']) ?>
        </div>
      </div>
      
      <div style="margin-top:24px; display:flex; gap:12px; align-items:center">
        <button class="btn btn-primary" type="submit"><?= __('fedi_btn_save_link', 'Guardar y Vincular') ?></button>
        <?php if ($admin_mastodon_handle): ?>
          <button class="btn btn-ghost" type="button" onclick="document.getElementById('admin_mastodon_handle').value=''; this.form.submit();" style="color:var(--text-3)">
            <?= __('fedi_btn_unlink', 'Desvincular Cuenta') ?>
          </button>
        <?php endif; ?>
        <a class="btn" href="<?= admin_url('plugins') ?>"><?= __('btn_back_plugins', 'Volver a Plugins') ?></a>
      </div>
    </form>
  </div>

  <!-- Card: Peligro y Reset -->
  <div class="card span-12" style="border-color:rgba(248, 113, 113, 0.2)">
    <div class="card-head" style="margin-bottom:12px">
      <div>
        <h2 style="margin:0; color:var(--red)"><?= __('fedi_danger_title', 'Zona de Peligro: Reiniciar Federación') ?></h2>
        <p class="help" style="margin:4px 0 0 0"><?= __('fedi_danger_sub', 'Esta acción purga todos los datos de red de los podcasts y seguidores.') ?></p>
      </div>
    </div>
    
    <p style="font-size:12.5px; color:var(--text-2); line-height:1.5; margin-bottom:16px">
      <?= __('fedi_danger_list', "Si reinicias el Fediverso:\n<br>• Se eliminarán permanentemente todos tus seguidores actuales en otras instancias.\n<br>• Se borrará el historial de envíos de la bandeja de salida.\n<br>• Se regenerarán las claves criptográficas RSA para todos los podcasts.") ?>
    </p>

    <form method="POST" onsubmit="return confirm(<?= json_encode(__('fedi_danger_confirm', '¡PELIGRO! ¿Seguro que deseas reiniciar el Fediverso? Esta acción no se puede deshacer y perderás la conexión con tus seguidores reales.')) ?>);">
      <input type="hidden" name="_form" value="fediverse_reset">
      <button class="btn btn-danger" type="submit" style="font-weight:600">
        <?= icon('shield', 13) ?> <?= __('fedi_btn_reset_full', 'Resetear Fediverso Completo') ?>
      </button>
    </form>
  </div>

</div>
