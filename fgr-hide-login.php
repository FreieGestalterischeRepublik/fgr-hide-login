<?php
/**
 * Plugin Name:  FGR Hide Login
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Ändert die WordPress-Login-URL zu einer eigenen, individuellen URL und blockiert den direkten Zugriff auf wp-login.php.
 * Version:      1.3.0
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Text Domain:  fgr-hide-login
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_HIDE_LOGIN_VERSION', '1.3.0' );
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
if ( is_admin() && substr( untrailingslashit( plugin_dir_path( __FILE__ ) ), -5 ) === '-main' ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>FGR Hide Login:</strong> Das Plugin ist im falschen Ordner installiert '
            . '(<code>' . esc_html( basename( plugin_dir_path( __FILE__ ) ) ) . '</code>). '
            . 'Bitte das Plugin <strong>deaktivieren → löschen → neu installieren</strong>.'
            . '</p></div>';
    } );
}

// ── MU-Plugin-Sync ────────────────────────────────────────────────────────────
// Installiert/aktualisiert das MU-Plugin von GitHub (function_exists-Guard: MU-Plugin definiert dieselbe Funktion)

if ( ! function_exists( 'fgr_mu_sync' ) ) {
    function fgr_mu_sync(): void {
        $url      = 'https://raw.githubusercontent.com/FreieGestalterischeRepublik/fgr-plugin-overview/main/fgr-plugin-overview.php';
        $dest_dir = WPMU_PLUGIN_DIR;
        $dest     = $dest_dir . '/fgr-plugin-overview.php';

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        ] );

        if ( is_wp_error( $response ) ) return;
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) return;

        $remote_content = wp_remote_retrieve_body( $response );
        if ( empty( $remote_content ) ) return;

        preg_match( '/\*\s+Version:\s+([\d.]+)/i', $remote_content, $matches );
        $remote_version = $matches[1] ?? '0';

        // Installierte Version lesen
        $installed_version = '0';
        if ( file_exists( $dest ) ) {
            $contents = file_get_contents( $dest );
            preg_match( '/\*\s+Version:\s+([\d.]+)/i', $contents, $m );
            $installed_version = $m[1] ?? '0';
        }

        if ( ! file_exists( $dest ) || version_compare( $remote_version, $installed_version, '>' ) ) {
            if ( ! is_dir( $dest_dir ) ) {
                wp_mkdir_p( $dest_dir );
            }
            file_put_contents( $dest, $remote_content );
            delete_transient( 'fgr_mu_update_info' );
        }
    }
}

// MU-Plugin bei Plugin-Aktivierung installieren/aktualisieren
register_activation_hook( __FILE__, 'fgr_mu_sync' );

// MU-Plugin nach Update eines FGR-Plugins aktualisieren
add_action( 'upgrader_process_complete', function ( $upgrader, array $hook_extra ): void {
    if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) return;
    if ( ( $hook_extra['action'] ?? '' ) !== 'update' ) return;

    $fgr_plugins = [
        'fgr-mail-smtp/fgr-mail-smtp.php',
        'fgr-hide-login/fgr-hide-login.php',
        'fgr-maintenance/fgr-maintenance.php',
    ];

    $updated = array_merge(
        isset( $hook_extra['plugin'] )  ? (array) $hook_extra['plugin']  : [],
        isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : []
    );

    foreach ( $updated as $plugin_file ) {
        if ( in_array( $plugin_file, $fgr_plugins, true ) ) {
            fgr_mu_sync();
            return;
        }
    }
}, 10, 2 );

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
                'slug' => 'fgr-mail-smtp',
                'file' => 'fgr-mail-smtp/fgr-mail-smtp.php',
                'name' => 'FGR Mail SMTP',
                'desc' => 'E-Mails über SMTP oder Microsoft 365 versenden',
                'page' => 'fgr-mail-smtp',
            ],
            [
                'slug' => 'fgr-hide-login',
                'file' => 'fgr-hide-login/fgr-hide-login.php',
                'name' => 'FGR Hide Login',
                'desc' => 'Login-URL individuell anpassen und schützen',
                'page' => 'fgr-hide-login',
            ],
            [
                'slug' => 'fgr-maintenance',
                'file' => 'fgr-maintenance/fgr-maintenance.php',
                'name' => 'FGR Maintenance',
                'desc' => 'Under-Construction- oder Wartungsseite anzeigen',
                'page' => 'fgr-maintenance',
            ],
        ];
        ?>
        <div class="wrap">
            <h1>FGR Plugins</h1>
            <p style="color:#888;margin-top:-8px">von der <em>Freien Gestalterischen Republik</em></p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:20px">
            <?php foreach ( $plugins as $p ) :
                $active    = is_plugin_active( $p['file'] );
                $installed = file_exists( WP_PLUGIN_DIR . '/' . $p['file'] );
                if ( $active ) {
                    $badge = '<span style="color:#46b450;font-size:12px">&#9679; Aktiv</span>';
                } elseif ( $installed ) {
                    $badge = '<span style="color:#888;font-size:12px">&#9679; Inaktiv</span>';
                } else {
                    $badge = '<span style="color:#dc3545;font-size:12px">&#9679; Nicht installiert</span>';
                }
            ?>
                <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;min-width:240px;max-width:320px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
                        <h2 style="margin:0"><?php echo esc_html( $p['name'] ); ?></h2>
                        <?php echo $badge; ?>
                    </div>
                    <p style="color:#555;margin-bottom:16px"><?php echo esc_html( $p['desc'] ); ?></p>
                    <?php if ( $active ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $p['page'] ) ); ?>"
                           class="button button-primary">Einstellungen</a>
                    <?php elseif ( $installed ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $p['file'] ) ),
                            'activate-plugin_' . $p['file']
                        ) ); ?>" class="button button-primary">Aktivieren</a>
                    <?php else : ?>
                        <button type="button" class="button button-primary fgr-install-btn"
                                data-slug="<?php echo esc_attr( $p['slug'] ); ?>">
                            Installieren
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <script>
        document.querySelectorAll( '.fgr-install-btn' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var self = this;
                self.disabled    = true;
                self.textContent = 'Installiere…';
                fetch( ajaxurl, {
                    method: 'POST',
                    body:   new URLSearchParams( {
                        action:      'fgr_install_plugin',
                        slug:        self.dataset.slug,
                        _ajax_nonce: '<?php echo wp_create_nonce( 'fgr_install_plugin' ); ?>'
                    } )
                } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( data ) {
                    if ( data.success ) {
                        location.reload();
                    } else {
                        alert( 'Fehler: ' + ( data.data || 'Unbekannter Fehler' ) );
                        self.disabled    = false;
                        self.textContent = 'Installieren';
                    }
                } )
                .catch( function () {
                    alert( 'Verbindungsfehler.' );
                    self.disabled    = false;
                    self.textContent = 'Installieren';
                } );
            } );
        } );
        </script>
        <?php
    }

    add_action( 'wp_ajax_fgr_install_plugin', 'fgr_install_plugin_handler' );

    function fgr_install_plugin_handler(): void {
        check_ajax_referer( 'fgr_install_plugin' );

        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $slug    = sanitize_key( $_POST['slug'] ?? '' );
        $allowed = [ 'fgr-mail-smtp', 'fgr-hide-login', 'fgr-maintenance' ];

        if ( ! in_array( $slug, $allowed, true ) ) {
            wp_send_json_error( 'Unbekanntes Plugin.' );
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // GitHub-Source-ZIP des main-Branches laden
        $zip_url  = "https://github.com/FreieGestalterischeRepublik/{$slug}/archive/refs/heads/main.zip";
        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $zip_url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        if ( false === $result ) {
            wp_send_json_error( 'Installation fehlgeschlagen. Bitte Dateisystem-Berechtigungen prüfen.' );
        }

        // GitHub-ZIP entpackt in "{slug}-main/" → zum korrekten Ordnernamen umbenennen
        $wrong_dir   = WP_PLUGIN_DIR . '/' . $slug . '-main';
        $correct_dir = WP_PLUGIN_DIR . '/' . $slug;
        if ( is_dir( $wrong_dir ) && ! is_dir( $correct_dir ) ) {
            rename( $wrong_dir, $correct_dir );
        }

        wp_send_json_success( [ 'message' => 'Plugin erfolgreich installiert.' ] );
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
