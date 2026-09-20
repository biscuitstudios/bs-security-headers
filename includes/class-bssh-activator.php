<?php
/**
 * Activation.
 *
 * Writing the defaults here is what makes "install it and the safe headers are
 * on" true. Without it the option would not exist until someone visited the
 * settings screen and pressed Save, and a plugin rolled out across 62 sites
 * would be doing nothing on all of them.
 *
 * Existing settings are never overwritten, so reactivating after an update, or
 * after a deactivate while debugging something, does not silently reset a site
 * that had been configured by hand.
 *
 * There is no deactivation hook and no capability to strip. Deactivating stops
 * the headers by itself, because the hooks stop firing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Bssh_Activator {

    public static function activate(): void {
        if ( false === get_option( Bssh_Plugin::OPTION, false ) ) {
            Bssh_Plugin::save( Bssh_Plugin::defaults() );
        }
    }
}
