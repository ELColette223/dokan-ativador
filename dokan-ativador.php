<?php
/**
 * Plugin Name: Dokan Ativador
 * Plugin URI: https://github.com/ELColette223/dokan-ativador
 * Description: Ativador de Licença para os plugins Dokan PRO e Dokan Lite.
 * Version: 1.3.0
 * Author: ELColette223
 * Author URI: https://github.com/ELColette223/dokan-ativador
 * Text Domain: dokan-ativador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DOKAN_ACTIVATOR_VERSION', '1.3.0' );

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
 * Verifica se o plugin Dokan Lite está ativo.
 *
 * @return bool True se o plugin Dokan Lite estiver ativo, false caso contrário.
 */
function check_dokan_lite_is_active() {
    if ( is_plugin_active( 'dokan-lite/dokan.php' ) ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Verifica se o plugin Dokan PRO está ativo.
 *
 * @return bool True se o plugin Dokan PRO estiver ativo, false caso contrário.
 */
function check_dokan_pro_is_active() {
    if ( is_plugin_active( 'dokan-pro/dokan-pro.php' ) ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Exibe um aviso de administrador se o plugin Dokan Lite ou Dokan PRO não estiverem ativos.
 */
function dokan_not_active_admin_notice() {
    if ( !check_dokan_lite_is_active() || !check_dokan_pro_is_active()) {
        $class = 'notice notice-error';
        $message = __( 'O Ativador Dokan precisa que o plugin Dokan Lite e Dokan PRO estejam ativados!', 'dokan-activator' );

        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
}
add_action( 'admin_notices', 'dokan_not_active_admin_notice' );

if (check_dokan_lite_is_active()) {
    add_filter( 'pre_http_request', 'custom_pre_http_request', 10, 3 );
    /**
     * Ignora a verificação de licença do Appsero.
     *
     * @param bool   $preempt Se deve antecipar o valor de retorno de uma solicitação HTTP. Padrão: false.
     * @param array  $r       Argumentos da solicitação HTTP.
     * @param string $url     A URL da solicitação.
     * @return mixed|array|bool A resposta HTTP ou false se a solicitação não deve ser antecipada.
     */
    function custom_pre_http_request( $preempt, $r, $url ) {
        if ( strpos( $url, 'https://api.appsero.com/public/license/' ) !== false ) {
            $custom_response = [
                'headers'       => [],
                'body'          => json_encode([
                    'success'           => true,
                    'remaining'         => true,
                    'activation_limit'  => '999',
                    'expiry_days'       => 'Lifetime',
                    'title'             => true,
                    'source_identifier' => 'enterprise',
                    'recurring'         => true,
                ]),
                'response'      => [
                    'code'    => 200,
                    'message' => 'OK'
                ],
                'cookies'       => [],
                'http_response' => null,
            ];
            return $custom_response;
        }
        return false;
    }
}

/**
 * Ativa a chave de licença do Dokan PRO.
 */
function active_dokan_pro_key() {
    $dokan_license = [
        'key'               => '********************************',
        'status'            => 'active',
        'remaining'         => true,
        'activation_limit'  => '999',
        'expiry_days'       => 'Lifetime',
        'source_identifier' => 'enterprise',
        'recurring'         => true,
    ];

    update_option( 'appsero_' . md5( 'dokan-pro' ) . '_manage_license', $dokan_license );
    update_option( 'dokan_pro_license', $dokan_license );
}

/**
 * Ativa o arquivo do Dokan PRO.
 *
 * @param usando regex para substituir o valor da chave source_identifier no arquivo dokan-pro.php.
 * @return bool True se o arquivo do Dokan PRO for ativado com sucesso, false caso contrário.
 * @since 1.1.0
 */
function active_dokan_pro_file() {
    $filePath = WP_PLUGIN_DIR . '/dokan-pro/dokan-pro.php';

    if (!file_exists($filePath)) {
        echo "O arquivo dokan-pro.php não foi encontrado.";
        return;
    }

    $fileContent = file_get_contents($filePath);
    $valuesToReplace = ['unlicensed', 'starter', 'liquidweb', 'professional', 'business'];
    $replacementValue = "'enterprise'";

    $count = 0;

    foreach ($valuesToReplace as $value) {
        $pattern = "/'$value'/";

        if (preg_match($pattern, $fileContent)) {
            $fileContent = preg_replace($pattern, $replacementValue, $fileContent, -1, $tempCount);
            $count += $tempCount;
        }
    }

    if ($count > 0) {
        if (file_put_contents($filePath, $fileContent)) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
* Ativa a chave de licença e o arquivo do Dokan PRO se tanto o Dokan PRO quanto o Dokan Lite estiverem ativos.
*/
add_action('admin_menu', function() {
    if (check_dokan_pro_is_active() && check_dokan_lite_is_active()) {
        add_action('admin_notices', 'dokan_activation_notice');
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'dokan_activator_action_links');
    }
});

/**
 * Exibe um aviso de administrador para ativar a chave de licença e o arquivo Dokan PRO.
 */
function dokan_activation_notice() {
    global $pagenow;
    if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'dokan_updates') {
        echo '<div class="notice notice-success"><p><strong>' . esc_html__('Ativador Dokan:', 'dokan-activator') . '</strong> ' . esc_html__('Caso seu Dokan não esteja ativado, clique em:', 'dokan-activator') . ' <strong>' . esc_html__('Refresh License', 'dokan-activator') . '</strong></p></div>';
        active_dokan_pro_key();
        active_dokan_pro_file();
    }
}

/**
 * Adiciona um link para a página de ativação da chave de licença do Dokan PRO.
 */
function dokan_activator_action_links($links) {
    $mylinks = array(
        '<a href="' . admin_url('admin.php?page=dokan_updates') . '">' . esc_html__('Atualizar Licença', 'dokan-activator') . '</a>',
    );
    return array_merge($links, $mylinks);
}

/**
 * Verifica se há atualizações para o plugin.
 *
 * @param object $transient O objeto de atualização do plugin.
 * @return object O objeto de atualização do plugin.
 */
function check_for_plugin_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $current_version = DOKAN_ACTIVATOR_VERSION;
    $remote_version = '0.0.0';

    // Certifique-se de usar a URL da API do GitHub corretamente. A URL abaixo é apenas um exemplo.
    $request = wp_remote_get('https://api.github.com/repos/ELColette223/dokan-ativador/releases/latest', array(
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress + https://github.com/ELColette223/dokan-ativador'
        ),
    ));

    if (!is_wp_error($request) && is_array($request) && isset($request['response']['code']) && $request['response']['code'] == 200) {
        $response = json_decode(wp_remote_retrieve_body($request));
        // Verifica se $response é um objeto e se a propriedade tag_name existe
        if (is_object($response) && isset($response->tag_name)) {
            if (version_compare($current_version, $response->tag_name, '<')) {
                $remote_version = $response->tag_name;
                $package = $response->zipball_url;

                error_log('Nova versão disponível: ' . $remote_version);

                $obj = new stdClass();
                $obj->slug = 'dokan-ativador';
                $obj->new_version = $remote_version;
                $obj->url = $response->html_url; // URL da página da release
                $obj->package = $package; // URL do download do zip
                
                error_log('URL da página da release: ' . $obj->url);
                error_log('URL do download do zip: ' . $obj->package);

                $transient->response['dokan-ativador/dokan-ativador.php'] = $obj;
                error_log('Plugin Dokan Ativador atualizado para a versão ' . $remote_version);
            }
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'check_for_plugin_update');