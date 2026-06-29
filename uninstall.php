<?php
// Wird nur ausgeführt wenn das Plugin über WordPress deinstalliert wird
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'fgr_hide_login_slug' );
delete_option( 'fgr_hide_login_redirect' );
