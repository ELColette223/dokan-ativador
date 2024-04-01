<?php
/**
 * Plugin Name: Dokan Ativador
 * Plugin URI: https://github.com/ELColette223/dokan-ativador
 * Description: Ativador de Licença para os plugins Dokan PRO e Dokan Lite.
 * Version: 1.2.0
 * Author: ELColette223
 * Author URI: https://github.com/ELColette223/dokan-ativador
 * Text Domain: dokan-ativador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
