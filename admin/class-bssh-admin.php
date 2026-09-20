<?php
/**
 * Admin menu, settings screen, and form handlers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Bssh_Admin {

    use Bssh_Guards;

    public const SLUG = 'bssh-security-headers';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_post_' . Bssh_Plugin::SAVE_ACTION, [ $this, 'handle_save' ] );
        add_action( 'admin_post_' . Bssh_Plugin::PROBE_ACTION, [ $this, 'handle_probe' ] );
        add_filter( 'plugin_action_links_' . BSSH_BASENAME, [ $this, 'action_links' ] );
    }

    /**
     * Settings submenu rather than a top-level menu.
     *
     * This is configured once and then left alone, which is what Settings is
     * for, and it puts the screen in the same place WP Force SSL used.
     */
    public function register_menu(): void {
        add_options_page(
            __( 'Security Headers', 'bs-security-headers' ),
            __( 'Security Headers', 'bs-security-headers' ),
            Bssh_Plugin::CAPABILITY,
            self::SLUG,
            [ $this, 'render_page' ]
        );
    }

    /** @param string[] $links */
    public function action_links( $links ): array {
        $links = is_array( $links ) ? $links : [];
        array_unshift( $links, sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
            esc_html__( 'Settings', 'bs-security-headers' )
        ) );
        return $links;
    }

    public function enqueue( string $hook ): void {
        if ( 'settings_page_' . self::SLUG !== $hook ) return;

        wp_enqueue_style( 'bssh-admin', BSSH_URL . 'assets/css/bssh-admin.css', [], BSSH_VERSION );

        // Match the admin color scheme the user picked in their profile.
        wp_add_inline_style(
            'bssh-admin',
            '.bssh-wrap{--bssh-accent:' . $this->admin_accent_color() . ';}'
        );

        wp_enqueue_script( 'bssh-admin', BSSH_URL . 'assets/js/bssh-admin.js', [], BSSH_VERSION, true );
        wp_localize_script( 'bssh-admin', 'bsshAdmin', [
            'hstsConfirm' => __(
                "Switching HSTS on cannot be fully undone.\n\nBrowsers that see it will refuse to load this site over an insecure connection for as long as the duration you chose, and there is no way to tell them to forget sooner.\n\nStart with 5 minutes unless you have already run this site at a shorter duration without trouble.",
                'bs-security-headers'
            ),
        ] );
    }

    /**
     * The accent color from the current user's admin color scheme.
     *
     * Core registers each scheme with a four-color palette; index 2 is the
     * accent in every bundled scheme (#2271b1 in the default "fresh"). Falls
     * back through the palette, then to the WP admin blue, so an unregistered
     * or malformed custom scheme can never break the stylesheet.
     */
    private function admin_accent_color(): string {
        $fallback = '#2271b1';

        $scheme = get_user_option( 'admin_color' );
        if ( ! $scheme ) return $fallback;

        $schemes = $GLOBALS['_wp_admin_css_colors'] ?? [];
        $colors  = $schemes[ $scheme ]->colors ?? null;
        if ( ! is_array( $colors ) ) return $fallback;

        foreach ( [ 2, 3, 1, 0 ] as $i ) {
            $candidate = $colors[ $i ] ?? '';
            // Validate before it reaches a stylesheet — this ends up in CSS.
            if ( is_string( $candidate ) && preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $candidate ) ) {
                return $candidate;
            }
        }
        return $fallback;
    }

    public function render_page(): void {
        $settings = Bssh_Plugin::settings();
        require BSSH_DIR . 'admin/views/page-settings.php';
    }

    // -------------------------------------------------------------- handlers

    public function handle_save(): void {
        $this->guard_post( Bssh_Plugin::SAVE_ACTION, 'save', 2 );

        $raw      = wp_unslash( $_POST );  // phpcs:ignore WordPress.Security.NonceVerification -- guard_post above
        $settings = Bssh_Plugin::sanitize( is_array( $raw ) ? $raw : [] );

        $notices = [];

        // Refuse to switch the CSP on with nothing in the box. An empty policy
        // would send no header at all while the screen said it was enabled,
        // which is the kind of quiet disagreement this plugin exists to end.
        if ( $settings['csp'] && '' === $settings['csp_value'] ) {
            $settings['csp'] = false;
            $notices[]       = 'nocsp';
        }

        Bssh_Plugin::save( $settings );

        // The parked probe describes the site as it was a moment ago. Showing
        // it beside settings that just changed would claim the site is sending
        // something it no longer sends, so drop it and let them re-run.
        delete_transient( self::probe_key() );

        $args = [ 'page' => self::SLUG, 'bssh-updated' => '1' ];
        if ( $notices ) $args['bssh-notice'] = implode( ',', $notices );

        wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
        exit;
    }

    /**
     * Measure what this site is actually sending.
     *
     * Throttled harder than the save handler because it makes two outbound HTTP
     * requests to the site's own public URL.
     */
    public function handle_probe(): void {
        $this->guard_post( Bssh_Plugin::PROBE_ACTION, 'probe', 10 );

        set_transient( self::probe_key(), Bssh_Server_Check::run(), 15 * MINUTE_IN_SECONDS );

        wp_safe_redirect( add_query_arg(
            [ 'page' => self::SLUG, 'bssh-checked' => '1' ],
            admin_url( 'options-general.php' )
        ) . '#bssh-server-check' );
        exit;
    }

    /** Probe results are per-user, so two people looking at once do not fight. */
    public static function probe_key(): string {
        return Bssh_Server_Check::TRANSIENT . '_' . get_current_user_id();
    }
}
