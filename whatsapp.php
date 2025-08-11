<?php
/**
 * Plugin Name:       Decor - Botón de WhatsApp
 * Plugin URI:        https://hopewind.s2-tastewp.com/
 * Description:       Añade un botón flotante de WhatsApp con ícono personalizable y seguimiento de eventos.
 * Version:           1.5.1
 * Author:            Juvi | Automatizaciones Web 
 * Author URI:        https://instagram.com/juviweb
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       decor-whatsapp
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =================================================================================
// 1. GESTIÓN DE LA BASE DE DATOS
// =================================================================================
register_activation_hook(__FILE__, 'decor_whatsapp_activate');
function decor_whatsapp_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'decor_whatsapp_clicks';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name varchar(100) DEFAULT '' NOT NULL,
        phone varchar(50) DEFAULT '' NOT NULL,
        clicked_from_url text NOT NULL,
        ip_address varchar(100) DEFAULT '' NOT NULL,
        user_agent text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// =================================================================================
// 2. MENÚS DE ADMINISTRACIÓN Y PÁGINAS (Sin cambios)
// =================================================================================
add_action('admin_menu', function() { add_menu_page('Decor WhatsApp', 'Decor WhatsApp', 'manage_options', 'decor_whatsapp', 'decor_whatsapp_dashboard_page_html', 'dashicons-whatsapp'); add_submenu_page('decor_whatsapp', 'Dashboard de Clics', 'Dashboard', 'manage_options', 'decor_whatsapp', 'decor_whatsapp_dashboard_page_html'); add_submenu_page('decor_whatsapp', 'Ajustes', 'Ajustes', 'manage_options', 'decor_whatsapp_settings', 'decor_whatsapp_settings_page_html'); });
function decor_whatsapp_settings_page_html() { echo '<div class="wrap"><h1>Ajustes de Decor WhatsApp</h1><form action="options.php" method="post">'; settings_fields('decor_whatsapp_options_group'); do_settings_sections('decor_whatsapp_settings_page'); submit_button('Guardar Cambios'); echo '</form></div>'; }
add_action('admin_init', function() { register_setting('decor_whatsapp_options_group', 'decor_whatsapp_settings'); add_settings_section('decor_whatsapp_main_section', 'Configuraciones', null, 'decor_whatsapp_settings_page'); add_settings_field('phone_number', 'Número de WhatsApp (Tuyo)', function() { $options = get_option('decor_whatsapp_settings'); $value = isset($options['phone_number']) ? $options['phone_number'] : ''; echo "<input type='text' name='decor_whatsapp_settings[phone_number]' value='" . esc_attr($value) . "' style='width: 25em;' placeholder='Ej: 5491123456789'>"; }, 'decor_whatsapp_settings_page', 'decor_whatsapp_main_section'); add_settings_field('custom_icon', 'Ícono Personalizado', function() { $options = get_option('decor_whatsapp_settings'); $icon_id = isset($options['custom_icon_id']) ? $options['custom_icon_id'] : ''; $icon_url = $icon_id ? wp_get_attachment_image_url($icon_id, 'thumbnail') : ''; echo "<div><img id='icon-preview' src='" . esc_url($icon_url) . "' style='max-height: 100px; display:" . ($icon_id ? 'block' : 'none') . ";'></div><input type='button' class='button' id='upload_icon_button' value='Elegir Ícono'><input type='button' class='button' id='remove_icon_button' value='Quitar Ícono' style='display:" . ($icon_id ? 'inline-block' : 'none') . ";'><input type='hidden' name='decor_whatsapp_settings[custom_icon_id]' id='custom_icon_id' value='" . esc_attr($icon_id) . "'>"; }, 'decor_whatsapp_settings_page', 'decor_whatsapp_main_section'); });
add_action('admin_enqueue_scripts', function($hook) { if ($hook !== 'decor-whatsapp_page_decor_whatsapp_settings') return; wp_enqueue_media(); });

// =================================================================================
// 3. LA TABLA DEL DASHBOARD (WP_List_Table) - CORREGIDA
// =================================================================================
if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

class Decor_Clicks_List_Table extends WP_List_Table {
    function get_columns() {
        return ['time' => 'Fecha y Hora', 'name' => 'Nombre', 'phone' => 'Teléfono del Cliente', 'clicked_from_url' => 'Página de Origen'];
    }
    
    // --- INICIO DE LA CORRECCIÓN ---

    function column_name($item) {
        // Primero verificamos si el dato 'name' existe y no está vacío en esta fila
        if (!empty($item['name'])) {
            return esc_html($item['name']);
        }
        // Si no existe, devolvemos un texto amigable
        return '<em>No proporcionado</em>';
    }

    function column_phone($item) {
        // Hacemos la misma verificación para el teléfono
        if (!empty($item['phone'])) {
            $phone_number = preg_replace('/[^0-9]/', '', $item['phone']);
            return '<a href="https://wa.me/' . esc_attr($phone_number) . '" target="_blank">' . esc_html($item['phone']) . '</a>';
        }
        return '<em>No proporcionado</em>';
    }

    // --- FIN DE LA CORRECCIÓN ---

    function column_default($item, $column_name) {
        // Verificamos si la columna existe en el array antes de acceder a ella
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    function column_clicked_from_url($item) { return '<a href="' . esc_url($item['clicked_from_url']) . '" target="_blank">' . esc_html($item['clicked_from_url']) . '</a>'; }
    function prepare_items() { global $wpdb; $table_name = $wpdb->prefix . 'decor_whatsapp_clicks'; $per_page = 20; $this->_column_headers = [$this->get_columns(), [], []]; $current_page = $this->get_pagenum(); $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name"); $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]); $offset = ($current_page - 1) * $per_page; $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY time DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A); }
}

function decor_whatsapp_dashboard_page_html() { if (isset($_POST['decor_clear_log_nonce']) && wp_verify_nonce($_POST['decor_clear_log_nonce'], 'decor_clear_log_action')) { global $wpdb; $table_name = $wpdb->prefix . 'decor_whatsapp_clicks'; $wpdb->query("TRUNCATE TABLE $table_name"); echo '<div class="notice notice-success is-dismissible"><p>El registro de clics ha sido borrado.</p></div>'; } $clicks_table = new Decor_Clicks_List_Table(); $clicks_table->prepare_items(); echo '<div class="wrap"><h1 class="wp-heading-inline">Dashboard de Clics de WhatsApp</h1><form method="post" style="display:inline-block;margin-left:10px;">'; wp_nonce_field('decor_clear_log_action', 'decor_clear_log_nonce'); echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'¿Estás seguro?\');">Borrar Historial</button></form><p>Aquí se registran los contactos iniciados a través del botón de WhatsApp.</p>'; $clicks_table->display(); echo '</div>'; }

// =================================================================================
// 4. LÓGICA DE REGISTRO (AJAX) Y FRONTEND (Sin cambios)
// =================================================================================
add_action('wp_ajax_decor_log_whatsapp_click', 'decor_log_whatsapp_click_handler');
add_action('wp_ajax_nopriv_decor_log_whatsapp_click', 'decor_log_whatsapp_click_handler');
function decor_log_whatsapp_click_handler() { if (!check_ajax_referer('decor_whatsapp_nonce', 'security', false)) { wp_send_json_error('Nonce inválido.', 403); return; } global $wpdb; $table_name = $wpdb->prefix . 'decor_whatsapp_clicks'; $inserted = $wpdb->insert($table_name, ['time' => current_time('mysql'), 'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '', 'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '', 'clicked_from_url' => isset($_POST['clicked_from']) ? esc_url_raw($_POST['clicked_from']) : '', 'ip_address' => $_SERVER['REMOTE_ADDR'], 'user_agent' => $_SERVER['HTTP_USER_AGENT']]); if ($inserted) { wp_send_json_success('Clic registrado.'); } else { wp_send_json_error('Error al registrar el clic.'); } }
add_action('wp_footer', function() { $options = get_option('decor_whatsapp_settings'); $phone_number = isset($options['phone_number']) ? $options['phone_number'] : ''; if (empty($phone_number)) return; $whatsapp_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone_number); $custom_icon_id = isset($options['custom_icon_id']) ? $options['custom_icon_id'] : ''; $default_logo_url = 'https://hopewind.s2-tastewp.com/wp-content/uploads/2022/03/logo.svg'; $logo_url = $custom_icon_id ? wp_get_attachment_image_url($custom_icon_id, 'full') : $default_logo_url; if (empty($logo_url)) $logo_url = $default_logo_url; $button_content = "<img src='" . esc_url($logo_url) . "' alt='Contactar por WhatsApp'>"; echo "<div id='decor-whatsapp-button'>" . $button_content . "</div>"; ?><div id="decor-whatsapp-modal-overlay" style="display:none;"><div id="decor-whatsapp-modal-content"><span id="decor-whatsapp-modal-close">&times;</span><h3>¡Contactanos por WhatsApp!</h3><p>Dejanos tus datos para una atención más rápida y personalizada.</p><form id="decor-whatsapp-lead-form"><input type="text" id="decor-wa-name" placeholder="Tu nombre (opcional)"><input type="tel" id="decor-wa-phone" placeholder="Tu teléfono (opcional)"><button type="submit" id="decor-wa-submit">Chatear ahora</button></form></div></div><?php $css = "#decor-whatsapp-button{cursor:pointer;position:fixed;bottom:25px;right:25px;width:60px;height:60px;background-color:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 8px rgba(0,0,0,0.2);z-index:999;transition:transform .2s ease-in-out;overflow:hidden} #decor-whatsapp-button:hover{transform:scale(1.1)} #decor-whatsapp-button img{width:100%;height:100%;object-fit:cover" . ($logo_url === $default_logo_url ? ";filter:brightness(0) invert(1);width:35px;height:35px;" : ";") . "} #decor-whatsapp-modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;} #decor-whatsapp-modal-content{background:white;padding:20px 30px;border-radius:8px;box-shadow:0 5px 15px rgba(0,0,0,0.3);text-align:center;max-width:320px;position:relative;} #decor-whatsapp-modal-close{position:absolute;top:10px;right:15px;font-size:28px;font-weight:bold;cursor:pointer;color:#aaa;} #decor-whatsapp-modal-content h3{margin-top:0;} #decor-whatsapp-lead-form input{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;} #decor-whatsapp-lead-form button{width:100%;padding:10px;border:none;border-radius:4px;background-color:#25D366;color:white;font-size:16px;cursor:pointer;}"; wp_register_style('decor-whatsapp-inline-style', false); wp_enqueue_style('decor-whatsapp-inline-style'); wp_add_inline_style('decor-whatsapp-inline-style', $css); ?><script>document.addEventListener('DOMContentLoaded',function(){const waButton=document.getElementById('decor-whatsapp-button');const modalOverlay=document.getElementById('decor-whatsapp-modal-overlay');const modalClose=document.getElementById('decor-whatsapp-modal-close');const leadForm=document.getElementById('decor-whatsapp-lead-form');waButton.addEventListener('click',function(){modalOverlay.style.display='flex'});modalClose.addEventListener('click',function(){modalOverlay.style.display='none'});modalOverlay.addEventListener('click',function(e){if(e.target===modalOverlay){modalOverlay.style.display='none'}});leadForm.addEventListener('submit',function(e){e.preventDefault();const name=document.getElementById('decor-wa-name').value;const phone=document.getElementById('decor-wa-phone').value;const formData=new FormData();formData.append('action','decor_log_whatsapp_click');formData.append('security','<?php echo wp_create_nonce("decor_whatsapp_nonce"); ?>');formData.append('clicked_from',window.location.href);formData.append('name',name);formData.append('phone',phone);fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:formData});modalOverlay.style.display='none';window.open('<?php echo esc_js($whatsapp_url); ?>','_blank')})}); <?php if(is_admin()){$hook=get_current_screen()->id;if($hook==='decor-whatsapp_page_decor_whatsapp_settings'){?> jQuery(document).ready(function($){var mediaUploader;$('#upload_icon_button').on('click',function(e){e.preventDefault();if(mediaUploader){mediaUploader.open();return}mediaUploader=wp.media({title:'Elegir Ícono',button:{text:'Usar este Ícono'},multiple:false});mediaUploader.on('select',function(){var attachment=mediaUploader.state().get('selection').first().toJSON();$('#custom_icon_id').val(attachment.id);$('#icon-preview').attr('src',attachment.sizes.thumbnail.url).show();$('#remove_icon_button').show()});mediaUploader.open()});$('#remove_icon_button').on('click',function(e){e.preventDefault();$('#custom_icon_id').val('');$('#icon-preview').attr('src','').hide();$(this).hide()})}); <?php }}?></script><?php });