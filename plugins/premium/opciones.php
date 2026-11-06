<?php
// ============================================================================
// KutPod · Opciones del Módulo Premium
// ============================================================================

// Evitar acceso directo
if (!defined('KUTPOD_VERSION')) {
    exit;
}

// ── Procesamiento de Peticiones POST ────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kp_csrf_verify();
    $action = $_POST['_form'] ?? '';

    // Guardar ajustes generales, Stripe y PayPal
    if ($action === 'premium_settings') {
        kp_update_setting('premium_stripe_secret_key', trim($_POST['premium_stripe_secret_key'] ?? ''));
        kp_update_setting('premium_stripe_publishable_key', trim($_POST['premium_stripe_publishable_key'] ?? ''));
        kp_update_setting('premium_stripe_webhook_secret', trim($_POST['premium_stripe_webhook_secret'] ?? ''));
        
        kp_update_setting('premium_paypal_client_id', trim($_POST['premium_paypal_client_id'] ?? ''));
        kp_update_setting('premium_paypal_client_secret', trim($_POST['premium_paypal_client_secret'] ?? ''));
        kp_update_setting('premium_paypal_webhook_id', trim($_POST['premium_paypal_webhook_id'] ?? ''));
        kp_update_setting('premium_paypal_mode', trim($_POST['premium_paypal_mode'] ?? 'sandbox'));
        
        kp_update_setting('premium_nginx_accel_dir', trim($_POST['premium_nginx_accel_dir'] ?? ''));
        kp_update_setting('premium_apache_sendfile', isset($_POST['premium_apache_sendfile']) ? 1 : 0);
        
        $limit = isset($_POST['premium_abuse_ip_limit']) ? (int)$_POST['premium_abuse_ip_limit'] : 5;
        kp_update_setting('premium_abuse_ip_limit', $limit);

        setcookie('kp_flash', __('prem_flash_saved', 'Ajustes actualizados correctamente.'), time() + 30, '/');
        header('Location: ' . admin_url('plugins?action=settings&plugin=premium'));
        exit;
    }

    // Agregar suscriptor manual (de cortesía)
    if ($action === 'premium_add_manual') {
        $email = trim($_POST['email'] ?? '');
        $podcast_id = (int)($_POST['podcast_id'] ?? 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setcookie('kp_flash', __('prem_err_invalid_email', 'Error: Correo electrónico inválido.'), time() + 30, '/');
        } elseif ($podcast_id <= 0) {
            setcookie('kp_flash', __('prem_err_invalid_podcast', 'Error: Selecciona un podcast válido.'), time() + 30, '/');
        } else {
            $tokenStr = bin2hex(random_bytes(16));
            try {
                kp_exec("INSERT INTO premium_tokens (podcast_id, subscriber_email, token, provider, status) VALUES (?, ?, ?, 'manual', 'active')", [
                    $podcast_id, $email, $tokenStr
                ]);
                setcookie('kp_flash', __('prem_flash_manual_added', 'Suscriptor manual añadido con éxito.'), time() + 30, '/');
                if (function_exists('kp_premium_send_status_email')) {
                    kp_premium_send_status_email($email, $podcast_id, 'welcome', $tokenStr);
                }
            } catch (Throwable $e) {
                setcookie('kp_flash', __('prem_err_manual_exists', 'Error: El suscriptor ya existe o ocurrió un fallo en la BD.'), time() + 30, '/');
            }
        }
        header('Location: ' . admin_url('plugins?action=settings&plugin=premium'));
        exit;
    }

    // Cambiar estado o eliminar suscriptor
    if (in_array($action, ['premium_suspend', 'premium_activate', 'premium_delete'])) {
        $token_id = (int)($_POST['token_id'] ?? 0);
        if ($token_id > 0) {
            if ($action === 'premium_suspend') {
                kp_exec("UPDATE premium_tokens SET status = 'suspended' WHERE id = ?", [$token_id]);
                $msg = __('prem_flash_suspended', 'Suscripción suspendida temporalmente.');
            } elseif ($action === 'premium_activate') {
                kp_exec("UPDATE premium_tokens SET status = 'active' WHERE id = ?", [$token_id]);
                // Reiniciar logs recientes de abuso al reactivar manual
                kp_exec("DELETE FROM premium_access_logs WHERE token_id = ?", [$token_id]);
                $msg = __('prem_flash_reactivated', 'Suscripción reactivada correctamente.');
            } else {
                kp_exec("DELETE FROM premium_tokens WHERE id = ?", [$token_id]);
                $msg = __('prem_flash_deleted', 'Suscripción eliminada de forma permanente.');
            }
            setcookie('kp_flash', $msg, time() + 30, '/');
        }
        header('Location: ' . admin_url('plugins?action=settings&plugin=premium'));
        exit;
    }
}

// ── Obtención de Datos de la BD ──────────────────────────────────────────────

$stripe_secret = kp_setting('premium_stripe_secret_key', '');
$stripe_publishable = kp_setting('premium_stripe_publishable_key', '');
$stripe_webhook = kp_setting('premium_stripe_webhook_secret', '');

$paypal_client_id = kp_setting('premium_paypal_client_id', '');
$paypal_client_secret = kp_setting('premium_paypal_client_secret', '');
$paypal_webhook_id = kp_setting('premium_paypal_webhook_id', '');
$paypal_mode = kp_setting('premium_paypal_mode', 'sandbox');

$nginx_accel = kp_setting('premium_nginx_accel_dir', '');
$apache_sendfile = (int)kp_setting('premium_apache_sendfile', 0);
$abuse_limit = (int)kp_setting('premium_abuse_ip_limit', 5);

$podcasts = kp_q("SELECT id, title, slug FROM podcasts ORDER BY title ASC") ?: [];
$subscribers = kp_q("SELECT t.*, p.title as podcast_title, p.slug as podcast_slug FROM premium_tokens t JOIN podcasts p ON p.id = t.podcast_id ORDER BY t.id DESC") ?: [];
?>

<div class="grid-12" style="gap:24px">

  <!-- Columna Izquierda: Ajustes Generales y Stripe -->
  <div class="span-8 col" style="gap:24px">
    
    <div class="card">
      <div class="card-head" style="margin-bottom:18px">
        <div>
          <h2 style="margin:0; font-size:16px"><?= __('prem_stripe_title', 'Configuración de Stripe') ?></h2>
          <p class="help" style="margin:4px 0 0 0"><?= __('prem_stripe_sub', 'Credenciales de API para procesar pagos automatizados.') ?></p>
        </div>
      </div>
      
      <form method="POST">
        <input type="hidden" name="_form" value="premium_settings">
        <?= kp_csrf_field() ?>
        
        <div class="field">
          <label class="label"><?= __('prem_stripe_pub_key', 'Stripe Publicable Key') ?></label>
          <input class="input" name="premium_stripe_publishable_key" value="<?= e($stripe_publishable) ?>" placeholder="pk_test_...">
        </div>
        
        <div class="field" style="margin-top:14px">
          <label class="label"><?= __('prem_stripe_sec_key', 'Stripe Secret Key') ?></label>
          <input class="input" type="password" name="premium_stripe_secret_key" value="<?= e($stripe_secret) ?>" placeholder="sk_test_...">
        </div>

        <div class="field" style="margin-top:14px">
          <label class="label"><?= __('prem_stripe_wh_sec', 'Stripe Webhook Secret') ?></label>
          <input class="input" type="password" name="premium_stripe_webhook_secret" value="<?= e($stripe_webhook) ?>" placeholder="whsec_...">
          <div class="help" style="margin-top:4px">
            <?= __('prem_stripe_wh_help', 'Apunta tu Webhook de Stripe hacia la URL:') ?><br>
            <code style="word-break:break-all; font-size:11px; background:rgba(0,0,0,0.15); padding:3px 6px; border-radius:4px">https://<?= e($_SERVER['HTTP_HOST'] ?? 'tudominio.com') ?>/premium/webhook/stripe</code>
          </div>
        </div>

        <hr style="border:0; border-top:1px solid var(--border); margin:24px 0">

        <div class="card-head" style="margin-bottom:18px">
          <div>
            <h2 style="margin:0; font-size:16px"><?= __('prem_paypal_title', 'Configuración de PayPal') ?></h2>
            <p class="help" style="margin:4px 0 0 0"><?= __('prem_paypal_sub', 'Credenciales de API de PayPal REST para procesar suscripciones automatizadas.') ?></p>
          </div>
        </div>

        <div class="field">
          <label class="label"><?= __('prem_paypal_mode_label', 'Entorno (Mode)') ?></label>
          <select class="select" name="premium_paypal_mode">
            <option value="sandbox" <?= $paypal_mode === 'sandbox' ? 'selected' : '' ?>><?= __('prem_mode_sandbox', 'Sandbox (Pruebas)') ?></option>
            <option value="live" <?= $paypal_mode === 'live' ? 'selected' : '' ?>><?= __('prem_mode_live', 'Live (Producción)') ?></option>
          </select>
        </div>

        <div class="field" style="margin-top:14px">
          <label class="label"><?= __('prem_paypal_client_id', 'PayPal Client ID') ?></label>
          <input class="input" name="premium_paypal_client_id" value="<?= e($paypal_client_id) ?>" placeholder="A...B...">
        </div>
        
        <div class="field" style="margin-top:14px">
          <label class="label"><?= __('prem_paypal_client_secret', 'PayPal Client Secret') ?></label>
          <input class="input" type="password" name="premium_paypal_client_secret" value="<?= e($paypal_client_secret) ?>" placeholder="E...F...">
        </div>

        <div class="field" style="margin-top:14px">
          <label class="label"><?= __('prem_paypal_wh_id', 'PayPal Webhook ID') ?></label>
          <input class="input" type="password" name="premium_paypal_webhook_id" value="<?= e($paypal_webhook_id) ?>" placeholder="WH-...">
          <div class="help" style="margin-top:4px">
            <?= __('prem_paypal_wh_help', 'Apunta tu Webhook de PayPal hacia la URL:') ?><br>
            <code style="word-break:break-all; font-size:11px; background:rgba(0,0,0,0.15); padding:3px 6px; border-radius:4px">https://<?= e($_SERVER['HTTP_HOST'] ?? 'tudominio.com') ?>/premium/webhook/paypal</code>
          </div>
        </div>

        <hr style="border:0; border-top:1px solid var(--border); margin:24px 0">
        
        <div class="card-head" style="margin-bottom:14px">
          <div>
            <h2 style="margin:0; font-size:15px"><?= __('prem_server_opt_title', 'Optimización del Servidor (Audio Secure)') ?></h2>
            <p class="help" style="margin:4px 0 0 0"><?= __('prem_server_opt_sub', 'Evita el consumo excesivo de memoria en PHP delegando la transmisión del audio.') ?></p>
          </div>
        </div>

        <div class="field">
          <label class="label"><?= __('prem_nginx_accel_label', 'Ruta interna Nginx (X-Accel-Redirect)') ?></label>
          <input class="input" name="premium_nginx_accel_dir" value="<?= e($nginx_accel) ?>" placeholder="/protected_media/">
          <div class="help" style="margin-top:4px"><?= __('prem_nginx_accel_help', 'Define el alias Nginx interno de descarga protegida. Déjalo en blanco si no utilizas Nginx.') ?></div>
        </div>

        <label class="toggle-row" style="margin-top:14px">
          <span><strong><?= __('prem_xsendfile_label', 'Usar X-Sendfile (Apache)') ?></strong><br><span class="help"><?= __('prem_xsendfile_help', 'Debe estar habilitado mod_xsendfile en Apache.') ?></span></span>
          <label class="toggle">
            <input type="checkbox" name="premium_apache_sendfile" value="1" <?= $apache_sendfile ? 'checked' : '' ?>>
            <span class="toggle-track"></span>
          </label>
        </label>

        <hr style="border:0; border-top:1px solid var(--border); margin:24px 0">

        <div class="field">
          <label class="label"><?= __('prem_abuse_limit_label', 'Límite de IPs sospechosas (24 horas)') ?></label>
          <input class="input tabular" type="number" min="0" name="premium_abuse_ip_limit" value="<?= e($abuse_limit) ?>">
          <div class="help" style="margin-top:4px"><?= __('prem_abuse_limit_help', 'Número máximo de direcciones IP únicas que pueden usar un mismo token en 24 horas antes de suspenderlo. Pon 0 para desactivar el bloqueo.') ?></div>
        </div>

        <div style="margin-top:24px; display:flex; gap:12px; align-items:center">
          <button class="btn btn-primary" type="submit"><?= __('btn_save_settings', 'Guardar Ajustes') ?></button>
          <a class="btn" href="<?= admin_url('plugins') ?>"><?= __('btn_back_plugins', 'Volver a Plugins') ?></a>
        </div>
      </form>
    </div>

  </div>

  <!-- Alta Manual de Suscriptor -->
  <div class="span-4 col" style="gap:24px">
    <div class="card">
      <div class="card-head" style="margin-bottom:18px">
        <div>
          <h2 style="margin:0; font-size:16px"><?= __('prem_manual_title', 'Dar de Alta Suscriptor de Cortesía') ?></h2>
          <p class="help" style="margin:4px 0 0 0"><?= __('prem_manual_sub', 'Genera un acceso Premium gratuito sin pasar por Stripe o PayPal.') ?></p>
        </div>
      </div>
      
      <form method="POST">
        <input type="hidden" name="_form" value="premium_add_manual">
        <?= kp_csrf_field() ?>
        
        <div class="field">
          <label class="label"><?= __('prem_user_email_label', 'Correo electrónico del usuario') ?></label>
          <input class="input" type="email" name="email" placeholder="usuario@correo.com" required>
        </div>
        
        <div class="field" style="margin-top:14px">
          <label class="label"><?= __('prem_associated_podcast', 'Podcast asociado') ?></label>
          <select class="select" name="podcast_id" required>
            <option value=""><?= __('prem_select_podcast_ph', 'Selecciona un Podcast...') ?></option>
            <?php foreach ($podcasts as $pod): ?>
              <option value="<?= (int)$pod['id'] ?>"><?= e($pod['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn" type="submit" style="margin-top:18px; width:100%; justify-content:center; color:var(--accent); border-color:var(--accent)">
          <?= icon('plus', 14) ?> <?= __('prem_btn_create_courtesy', 'Crear Acceso de Cortesía') ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Columna Derecha: Tabla y Gestión de Suscriptores -->
  <div class="span-12 col">
    
    <div class="card" style="padding:0">
      <div class="card-head" style="padding:24px 24px 18px 24px">
        <div>
          <h2 style="margin:0; font-size:16px"><?= __('prem_active_subs_title', 'Suscriptores Premium Activos') ?></h2>
          <p class="help" style="margin:4px 0 0 0"><?= __('prem_active_subs_sub', 'Gestión de accesos y tokens privados para feeds RSS.') ?></p>
        </div>
      </div>

      <div class="list" style="overflow-x:auto">
        <?php if (empty($subscribers)): ?>
          <div style="text-align:center; padding:48px 0; color:var(--text-3)">
            <?= icon('users', 32) ?>
            <p style="margin-top:12px; font-size:14px"><?= __('prem_no_subscribers', 'Aún no hay suscriptores premium registrados.') ?></p>
          </div>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px">
            <thead>
              <tr style="border-bottom:1px solid var(--border); background:var(--surface-2); color:var(--text-2)">
                <th style="padding:12px 16px; font-weight:600"><?= __('prem_th_subscriber', 'Suscriptor / Podcast') ?></th>
                <th style="padding:12px 16px; font-weight:600"><?= __('prem_th_origin_token', 'Origen / Token') ?></th>
                <th style="padding:12px 16px; font-weight:600"><?= __('prem_th_status', 'Estado') ?></th>
                <th style="padding:12px 16px; font-weight:600; text-align:right"><?= __('prem_th_actions', 'Acciones') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subscribers as $sub): 
                  $is_active = $sub['status'] === 'active';
                  $is_suspended = $sub['status'] === 'suspended';
                  $feed_url = admin_url('../feed.php?slug=' . urlencode($sub['podcast_slug']) . '&token=' . urlencode($sub['token']));
              ?>
                <tr style="border-bottom:1px solid var(--border)">
                  <td style="padding:14px 16px; vertical-align:top">
                    <strong style="color:var(--text-1); font-size:13.5px"><?= e($sub['subscriber_email']) ?></strong>
                    <div class="help" style="margin-top:2px"><?= e($sub['podcast_title']) ?></div>
                  </td>
                  <td style="padding:14px 16px; vertical-align:top">
                    <span class="tag" style="background:<?= $sub['provider'] === 'stripe' ? 'var(--accent-soft)' : ($sub['provider'] === 'paypal' ? '#fff9e6' : 'var(--surface-3)') ?>; color:<?= $sub['provider'] === 'stripe' ? 'var(--accent)' : ($sub['provider'] === 'paypal' ? '#d97706' : 'var(--text-2)') ?>; font-size:10.5px">
                      <?= strtoupper(e($sub['provider'])) ?>
                    </span>
                    <div style="margin-top:6px; font-family:ui-monospace,monospace; font-size:11px; display:flex; gap:6px; align-items:center">
                      <code style="background:rgba(0,0,0,0.12); padding:2px 4px; border-radius:4px; max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap" title="<?= e($sub['token']) ?>"><?= e($sub['token']) ?></code>
                      <a href="<?= e($feed_url) ?>" target="_blank" class="pub-link" style="font-size:11px" title="<?= __('prem_copy_feed_title', 'Copiar feed privado') ?>"><?= __('prem_copy_feed', 'Copiar Feed') ?></a>
                    </div>
                  </td>
                  <td style="padding:14px 16px; vertical-align:top">
                    <span class="tag <?= $is_active ? 'published' : ($is_suspended ? 'draft' : 'red') ?>" style="font-size:11px">
                      <?= $is_active ? __('prem_status_active', 'Activo') : ($is_suspended ? __('prem_status_suspended', 'Suspendido') : __('prem_status_cancelled', 'Cancelado')) ?>
                    </span>
                  </td>
                  <td style="padding:14px 16px; text-align:right; vertical-align:top">
                    <div style="display:inline-flex; gap:6px">
                      <?php if ($is_active): ?>
                        <form method="POST" style="margin:0">
                          <input type="hidden" name="_form" value="premium_suspend">
                          <input type="hidden" name="token_id" value="<?= (int)$sub['id'] ?>">
                          <?= kp_csrf_field() ?>
                          <button class="btn btn-ghost" type="submit" style="padding:4px 8px; font-size:11.5px; color:var(--text-3)"><?= __('prem_btn_suspend', 'Suspender') ?></button>
                        </form>
                      <?php else: ?>
                        <form method="POST" style="margin:0">
                          <input type="hidden" name="_form" value="premium_activate">
                          <input type="hidden" name="token_id" value="<?= (int)$sub['id'] ?>">
                          <?= kp_csrf_field() ?>
                          <button class="btn" type="submit" style="padding:4px 8px; font-size:11.5px; color:var(--accent); border-color:var(--accent)"><?= __('prem_btn_activate', 'Activar') ?></button>
                        </form>
                      <?php endif; ?>
                      
                      <form method="POST" style="margin:0" onsubmit="return confirm(<?= json_encode(__('prem_confirm_delete_sub', '¿Estás seguro de que quieres eliminar este suscriptor?')) ?>);">
                        <input type="hidden" name="_form" value="premium_delete">
                        <input type="hidden" name="token_id" value="<?= (int)$sub['id'] ?>">
                        <?= kp_csrf_field() ?>
                        <button class="btn btn-danger" type="submit" style="padding:4px 8px; font-size:11.5px" title="<?= __('btn_delete', 'Eliminar') ?>">
                          <?= icon('trash', 12) ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>
