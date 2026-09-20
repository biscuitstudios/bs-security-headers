<?php
/**
 * Plugin Name:       Security Headers
 * Plugin URI:        https://github.com/biscuitstudios/bs-security-headers
 * Description:       Sends the response security headers that managed hosts and WordPress leave off: Referrer-Policy, Permissions-Policy, X-Frame-Options, and optionally HSTS and a Content-Security-Policy. Shows what the server already sends so you can see what you are overriding.
 * Version:           0.1.1
 * Requires at least: 6.3
 * Requires PHP:      8.2
 * Author:            Biscuit Studios
 * Author URI:        https://biscuitstudios.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bs-security-headers
 * Update URI:        https://github.com/biscuitstudios/bs-security-headers
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BSSH_VERSION',  '0.1.1' );
define( 'BSSH_FILE',     __FILE__ );
define( 'BSSH_DIR',      plugin_dir_path( __FILE__ ) );
define( 'BSSH_URL',      plugin_dir_url( __FILE__ ) );
define( 'BSSH_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register( function ( $class ) {
    if ( 0 !== strpos( $class, 'Bssh_' ) ) return;
    $base = strtolower( str_replace( '_', '-', $class ) );
    foreach ( [ 'includes/', 'admin/' ] as $dir ) {
        foreach ( [ "class-$base.php", "trait-$base.php" ] as $file ) {
            $path = BSSH_DIR . $dir . $file;
            if ( file_exists( $path ) ) { require_once $path; return; }
        }
    }
} );

register_activation_hook( __FILE__, [ 'Bssh_Activator', 'activate' ] );

// Updates are served from the repo's GitHub Releases. Without this the plugin's
// Update URI header points at GitHub and nothing ever answers, so the plugins
// screen silently never offers a new version.
( new Bssh_Updater( __FILE__ ) )->init();

add_action( 'plugins_loaded', function () {
    // The emitter must init outside is_admin() — its main job is front-end
    // responses, and it also hooks login_init, which is neither admin nor a
    // normal front-end request.
    ( new Bssh_Headers() )->init();

    if ( is_admin() ) {
        ( new Bssh_Admin() )->init();
    }
} );
