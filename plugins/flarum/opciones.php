<?php
// ============================================================================
// KutPod · Opciones del Plugin de Flarum
// ============================================================================

// Evitar acceso directo
if (!defined('KUTPOD_VERSION')) {
    exit;
}

// Procesar el guardado si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kp_update_setting('flarum_url', trim($_POST['flarum_url'] ?? ''));
    kp_update_setting('flarum_api_key', trim($_POST['flarum_api_key'] ?? ''));
    kp_update_setting('flarum_user_id', (int)($_POST['flarum_user_id'] ?? 1));
    
    setcookie('kp_flash', __('flarum_saved', 'Configuración de Flarum guardada'), time() + 30, '/');
    header('Location: ' . admin_url('plugins?action=settings&plugin=flarum'));
    exit;
}

$flarum_url = kp_setting('flarum_url', '');
$flarum_api_key = kp_setting('flarum_api_key', '');
$flarum_user_id = kp_setting('flarum_user_id', '1');
?>

<div class="card span-12" style="max-width:800px; margin:0 auto">
  <div class="card-head" style="margin-bottom:18px">
    <div>
      <h2 style="margin:0"><?= __('flarum_title', 'Configuración del Foro Flarum') ?></h2>
      <p class="help" style="margin:4px 0 0 0"><?= __('flarum_sub', 'Configura la vinculación de tu foro para publicar discusiones de episodios automáticamente.') ?></p>
    </div>
  </div>
  
  <form method="POST">
    <?= kp_csrf_field() ?>
    <div class="field-row">
      <div class="field">
        <label class="label"><?= __('flarum_url_label', 'URL del foro Flarum') ?></label>
        <input class="input" name="flarum_url" value="<?= e($flarum_url) ?>" placeholder="https://foro.ernestoacosta.org" required>
        <div class="help" style="margin-top:4px"><?= __('flarum_url_help', 'La URL base de tu foro Flarum sin barra final.') ?></div>
      </div>
    </div>
    
    <div class="field-row" style="margin-top:14px">
      <div class="field">
        <label class="label"><?= __('flarum_key_label', 'API Key de Flarum') ?></label>
        <input class="input" name="flarum_api_key" value="<?= e($flarum_api_key) ?>" placeholder="<?= __('flarum_key_ph', 'Tu API key de Flarum') ?>" style="font-family:ui-monospace,monospace" required>
        <div class="help" style="margin-top:4px"><?= __('flarum_key_help', 'API Key de Flarum (generada directamente en la tabla :table de tu base de datos Flarum).', ['table' => '<code>api_keys</code>']) ?></div>
      </div>
    </div>

    <div class="field-row" style="margin-top:14px">
      <div class="field">
        <label class="label"><?= __('flarum_user_id_label', 'User ID del Autor (Flarum)') ?></label>
        <input class="input" type="number" name="flarum_user_id" value="<?= e($flarum_user_id) ?>" placeholder="1" min="1" required style="max-width:180px">
        <div class="help" style="margin-top:4px"><?= __('flarum_user_id_help', 'ID del usuario de Flarum que aparecerá como autor de los hilos de discusión.') ?></div>
      </div>
    </div>
    
    <div style="font-size:12.5px;color:var(--text-3);margin-top:18px;line-height:1.5">
      <?= __('flarum_notice', 'Una vez configurado aquí, asegúrate de activar la opción <strong>"Publicar en Flarum"</strong> en los ajustes avanzados de cada Podcast individualmente.') ?>
    </div>

    <div style="margin-top:24px; display:flex; gap:12px">
      <button class="btn btn-primary" type="submit"><?= __('btn_save_settings', 'Guardar Configuración') ?></button>
      <a class="btn" href="<?= admin_url('plugins') ?>"><?= __('btn_back_plugins', 'Volver a Plugins') ?></a>
    </div>
  </form>
</div>
