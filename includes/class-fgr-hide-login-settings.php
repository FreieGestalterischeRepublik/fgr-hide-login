<?php
defined( 'ABSPATH' ) || exit;

class FGR_Hide_Login_Settings {

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_menu' ] );
        add_action( 'admin_init',    [ $this, 'handle_save' ] );
        add_action( 'admin_notices', [ $this, 'show_notices' ] );
        add_filter( 'plugin_action_links_' . FGR_HIDE_LOGIN_BASENAME, [ $this, 'action_links' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'fgr-plugins',
            'FGR Hide Login',
            'Hide Login',
            'manage_options',
            'fgr-hide-login',
            [ $this, 'render_page' ]
        );
    }

    public function action_links( array $links ): array {
        array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=fgr-hide-login' ) . '">Einstellungen</a>' );
        return $links;
    }

    public function handle_save(): void {
        if ( ! isset( $_POST['fgr_hide_login_save'] ) ) return;
        check_admin_referer( 'fgr_hide_login_save', 'fgr_hide_login_nonce' );

        $slug = sanitize_title_with_dashes( $_POST['fgr_hide_login_slug'] ?? 'fgr-login' );
        if ( '' === $slug || 'wp-login' === $slug || $this->is_forbidden( $slug ) ) {
            set_transient( 'fgr_hide_login_notice', 'invalid_slug', 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=fgr-hide-login' ) );
            exit;
        }

        $redirect = sanitize_title_with_dashes( $_POST['fgr_hide_login_redirect'] ?? '404' );
        if ( '' === $redirect ) {
            $redirect = '404';
        }

        update_option( 'fgr_hide_login_slug', $slug );
        update_option( 'fgr_hide_login_redirect', $redirect );

        set_transient( 'fgr_hide_login_notice', 'saved', 30 );
        wp_safe_redirect( admin_url( 'admin.php?page=fgr-hide-login' ) );
        exit;
    }

    private function is_forbidden( string $slug ): bool {
        $wp = new WP();
        $forbidden = array_merge( $wp->public_query_vars, $wp->private_query_vars );
        return in_array( $slug, $forbidden, true );
    }

    public function show_notices(): void {
        if ( ( $_GET['page'] ?? '' ) !== 'fgr-hide-login' ) return;

        $n = get_transient( 'fgr_hide_login_notice' );
        if ( ! $n ) return;
        delete_transient( 'fgr_hide_login_notice' );

        if ( 'saved' === $n ) {
            $login_url = esc_url( ( new FGR_Hide_Login() )->new_login_url() );
            echo '<div class="notice notice-success is-dismissible"><p>'
                . '<strong>Einstellungen gespeichert.</strong> '
                . 'Neue Login-URL: <a href="' . $login_url . '">' . $login_url . '</a> — '
                . '<strong>Bitte als Lesezeichen speichern!</strong>'
                . '</p></div>';
        } elseif ( 'invalid_slug' === $n ) {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . '<strong>Ungültige URL:</strong> Der eingegebene Slug ist nicht erlaubt.'
                . '</p></div>';
        }
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $slug     = get_option( 'fgr_hide_login_slug', 'fgr-login' );
        $redirect = get_option( 'fgr_hide_login_redirect', '404' );
        $base     = trailingslashit( home_url() );
        ?>
        <div class="wrap">
            <h1>FGR Hide Login</h1>
            <p style="color:#888;margin-top:-8px">aus der <em>Freien Gestalterischen Republik</em></p>

            <form method="post">
                <?php wp_nonce_field( 'fgr_hide_login_save', 'fgr_hide_login_nonce' ); ?>

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><label for="fgr_hide_login_slug">Login-URL</label></th>
                        <td>
                            <code><?php echo esc_html( $base ); ?></code>
                            <input type="text" id="fgr_hide_login_slug" name="fgr_hide_login_slug"
                                   value="<?php echo esc_attr( $slug ); ?>"
                                   style="width:200px">
                            <code>/</code>
                            <p class="description">
                                Unter dieser URL ist die WordPress-Login-Seite erreichbar.
                                Der direkte Aufruf von <code>wp-login.php</code> wird geblockt.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="fgr_hide_login_redirect">Weiterleitungs-URL</label></th>
                        <td>
                            <code><?php echo esc_html( $base ); ?></code>
                            <input type="text" id="fgr_hide_login_redirect" name="fgr_hide_login_redirect"
                                   value="<?php echo esc_attr( $redirect ); ?>"
                                   style="width:200px">
                            <code>/</code>
                            <p class="description">
                                Hierhin werden nicht eingeloggte Benutzer weitergeleitet,
                                wenn sie direkt auf <code>wp-admin</code> oder <code>wp-login.php</code> zugreifen.
                                <code>404</code> zeigt die 404-Seite des Themes.
                            </p>
                        </td>
                    </tr>

                </table>

                <p class="submit">
                    <button type="submit" name="fgr_hide_login_save" class="button button-primary">
                        Einstellungen speichern
                    </button>
                </p>
            </form>

            <hr>

            <h2>Aktuelle Login-URL</h2>
            <?php
            $login_url = esc_url( ( new FGR_Hide_Login() )->new_login_url() );
            echo '<p><strong><a href="' . $login_url . '" target="_blank">' . $login_url . '</a></strong></p>';
            echo '<p class="description">Bitte diese URL als Lesezeichen speichern, bevor du die Einstellungen änderst.</p>';
            ?>
        </div>
        <?php
    }
}
