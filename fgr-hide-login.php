<?php
/**
 * Plugin Name:  FGR Hide Login
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Ändert die WordPress-Login-URL zu einer eigenen, individuellen URL und blockiert den direkten Zugriff auf wp-login.php.
 * Version:      1.0.0
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Text Domain:  fgr-hide-login
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_HIDE_LOGIN_VERSION', '1.0.0' );
define( 'FGR_HIDE_LOGIN_BASENAME', plugin_basename( __FILE__ ) );

// Update-Checker: prüft GitHub auf neue Versionen
require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_hide_login_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/FreieGestalterischeRepublik/fgr-hide-login/',
    __FILE__,
    'fgr-hide-login'
);
$fgr_hide_login_updater->setBranch( 'main' );
$fgr_hide_login_updater->getVcsApi()->enableReleaseAssets();

// Warnung wenn Plugin im falschen Ordner installiert ist (z. B. "fgr-hide-login-main")
if ( is_admin() && str_ends_with( untrailingslashit( plugin_dir_path( __FILE__ ) ), '-main' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>FGR Hide Login:</strong> Das Plugin ist im falschen Ordner installiert '
            . '(<code>' . esc_html( basename( plugin_dir_path( __FILE__ ) ) ) . '</code>). '
            . 'Bitte das Plugin <strong>deaktivieren → löschen → neu installieren</strong>.'
            . '</p></div>';
    } );
}

// ── Gemeinsamer FGR-Admin-Menüpunkt ──────────────────────────────────────────
// function_exists-Guard verhindert Doppelung wenn mehrere FGR-Plugins aktiv sind

if ( ! function_exists( 'fgr_register_admin_menu' ) ) {

    function fgr_register_admin_menu(): void {
        add_menu_page(
            'FGR Plugins',
            'FGR Plugins',
            'manage_options',
            'fgr-plugins',
            'fgr_render_plugins_overview',
            'dashicons-shield',
            65
        );
        // Den automatisch erzeugten doppelten "FGR Plugins"-Untermenüeintrag
        // durch einen sauberen "Übersicht"-Eintrag ersetzen
        add_submenu_page(
            'fgr-plugins',
            'FGR Plugins',
            'Übersicht',
            'manage_options',
            'fgr-plugins',
            'fgr_render_plugins_overview'
        );
    }
    add_action( 'admin_menu', 'fgr_register_admin_menu', 5 );

    function fgr_render_plugins_overview(): void {
        $plugins = [
            [
                'file' => 'fgr-mail-smtp/fgr-mail-smtp.php',
                'name' => 'FGR Mail SMTP',
                'desc' => 'E-Mails über SMTP oder Microsoft 365 versenden',
                'page' => 'fgr-mail-smtp',
            ],
            [
                'file' => 'fgr-hide-login/fgr-hide-login.php',
                'name' => 'FGR Hide Login',
                'desc' => 'Login-URL individuell anpassen und schützen',
                'page' => 'fgr-hide-login',
            ],
        ];
        ?>
        <div class="wrap">
            <h1>FGR Plugins</h1>
            <p style="color:#888;margin-top:-8px">von der <em>Freien Gestalterischen Republik</em></p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:20px">
            <?php foreach ( $plugins as $p ) :
                if ( ! is_plugin_active( $p['file'] ) ) continue;
            ?>
                <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;min-width:240px;max-width:320px">
                    <h2 style="margin-top:0"><?php echo esc_html( $p['name'] ); ?></h2>
                    <p><?php echo esc_html( $p['desc'] ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $p['page'] ) ); ?>" class="button button-primary">Einstellungen</a>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

// ── Settings-Klasse laden ─────────────────────────────────────────────────────

add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-fgr-hide-login-settings.php';
        new FGR_Hide_Login_Settings();
    }
} );

// ── Login-URL-Schutz ─────────────────────────────────────────────────────────

new FGR_Hide_Login();

class FGR_Hide_Login {

    private bool $wp_login_php = false;

    public function __construct() {
        add_action( 'plugins_loaded',   [ $this, 'plugins_loaded' ], 9999 );
        add_action( 'wp_loaded',        [ $this, 'wp_loaded' ] );
        add_filter( 'site_url',         [ $this, 'filter_site_url' ], 10, 4 );
        add_filter( 'network_site_url', [ $this, 'filter_network_site_url' ], 10, 3 );
        add_filter( 'wp_redirect',      [ $this, 'filter_wp_redirect' ], 10, 2 );
        add_filter( 'login_url',        [ $this, 'filter_login_url' ], 10, 3 );
        remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
    }

    private function login_slug(): string {
        return get_option( 'fgr_hide_login_slug', 'fgr-login' );
    }

    private function redirect_slug(): string {
        return get_option( 'fgr_hide_login_redirect', '404' );
    }

    public function new_login_url( $scheme = null ): string {
        $home = apply_filters( 'fgr_hide_login_home_url', home_url( '/', $scheme ) );
        if ( get_option( 'permalink_structure' ) ) {
            return trailingslashit( $home . $this->login_slug() );
        }
        return $home . '?' . $this->login_slug();
    }

    public function new_redirect_url( $scheme = null ): string {
        if ( get_option( 'permalink_structure' ) ) {
            return trailingslashit( home_url( '/', $scheme ) . $this->redirect_slug() );
        }
        return home_url( '/', $scheme ) . '?' . $this->redirect_slug();
    }

    public function plugins_loaded(): void {
        global $pagenow;

        $request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );
        $path    = $request['path'] ?? '';

        // Direkter Aufruf von wp-login.php → blockieren
        if (
            ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-login.php' ) !== false
                || ( $path && untrailingslashit( $path ) === site_url( 'wp-login', 'relative' ) ) )
            && ! is_admin()
        ) {
            $this->wp_login_php     = true;
            $_SERVER['REQUEST_URI'] = '/' . str_repeat( '-/', 10 );
            $pagenow                = 'index.php';
            return;
        }

        // Neuer Login-Slug aufgerufen → wp-login.php intern laden
        $login_path = home_url( $this->login_slug(), 'relative' );
        if (
            ( $path && untrailingslashit( $path ) === $login_path )
            || ( ! get_option( 'permalink_structure' )
                && isset( $_GET[ $this->login_slug() ] )
                && '' === $_GET[ $this->login_slug() ] )
        ) {
            $_SERVER['SCRIPT_NAME'] = $this->login_slug();
            $pagenow                = 'wp-login.php';
        }
    }

    public function wp_loaded(): void {
        global $pagenow;

        $request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

        // Nicht eingeloggte Benutzer aus wp-admin herausleiten
        if (
            is_admin()
            && ! is_user_logged_in()
            && ! defined( 'WP_CLI' )
            && ! defined( 'DOING_AJAX' )
            && ! defined( 'DOING_CRON' )
            && 'admin-post.php' !== $pagenow
            && ( $request['path'] ?? '' ) !== '/wp-admin/options.php'
        ) {
            wp_safe_redirect( $this->new_redirect_url() );
            die();
        }

        if ( isset( $_GET['action'] ) && 'postpass' === $_GET['action'] && isset( $_POST['post_password'] ) ) {
            return;
        }

        // wp-login.php direkt aufgerufen → Theme-404 anzeigen
        if ( $this->wp_login_php ) {
            $pagenow = 'index.php';
            if ( ! defined( 'WP_USE_THEMES' ) ) {
                define( 'WP_USE_THEMES', true );
            }
            wp();
            require_once ABSPATH . WPINC . '/template-loader.php';
            die;
        }

        // Neuer Login-Slug → original wp-login.php ausführen
        if ( 'wp-login.php' === $pagenow ) {
            if ( is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) {
                wp_safe_redirect( admin_url() );
                die();
            }
            require_once ABSPATH . 'wp-login.php';
            die;
        }
    }

    private function replace_login_url( string $url, $scheme = null ): string {
        if ( strpos( $url, 'wp-login.php' ) === false ) {
            return $url;
        }
        // Passwortgeschützte Beiträge nicht umleiten
        if ( strpos( $url, 'wp-login.php?action=postpass' ) !== false ) {
            return $url;
        }

        if ( is_ssl() ) {
            $scheme = 'https';
        }

        $parts = explode( '?', $url );
        if ( isset( $parts[1] ) ) {
            parse_str( $parts[1], $args );
            if ( isset( $args['login'] ) ) {
                $args['login'] = rawurlencode( $args['login'] );
            }
            return add_query_arg( $args, $this->new_login_url( $scheme ) );
        }

        return $this->new_login_url( $scheme );
    }

    public function filter_site_url( $url, $path, $scheme, $blog_id ): string {
        return $this->replace_login_url( $url, $scheme );
    }

    public function filter_network_site_url( $url, $path, $scheme ): string {
        return $this->replace_login_url( $url, $scheme );
    }

    public function filter_wp_redirect( $location, $status ): string {
        // WordPress.com-Login nicht anfassen
        if ( strpos( $location, 'https://wordpress.com/wp-login.php' ) !== false ) {
            return $location;
        }
        return $this->replace_login_url( $location );
    }

    public function filter_login_url( $login_url, $redirect, $force_reauth ): string {
        if ( is_404() ) {
            return '#';
        }
        if ( ! $force_reauth || empty( $redirect ) ) {
            return $login_url;
        }
        $parts = explode( '?', $redirect );
        if ( $parts[0] === admin_url( 'options.php' ) ) {
            return admin_url();
        }
        return $login_url;
    }
}
