<?php
/**
 * Settings screen.
 *
 * @var array $settings Current settings, from Bssh_Admin::render_page().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$notices = isset( $_GET['bssh-notice'] )
    ? explode( ',', sanitize_text_field( wp_unslash( $_GET['bssh-notice'] ) ) )
    : [];
$updated = isset( $_GET['bssh-updated'] );

$probe = get_transient( Bssh_Admin::probe_key() );
$probe = is_array( $probe ) ? $probe : null;

$probe_url = wp_nonce_url(
    add_query_arg( 'action', Bssh_Plugin::PROBE_ACTION, admin_url( 'admin-post.php' ) ),
    Bssh_Plugin::PROBE_ACTION
);

// What build() would emit for a normal front-end page view right now. Shown
// verbatim so the screen never describes something different from what ships.
$preview_headers = Bssh_Headers::build( $settings, true, Bssh_Headers::CONTEXT_FRONTEND );

$labels   = Bssh_Plugin::feature_labels();
$risky    = Bssh_Plugin::risky_features();
$blocked  = (array) ( $settings['permissions_policy_block'] ?? [] );

$durations = [
    300      => __( '5 minutes — start here', 'bs-security-headers' ),
    86400    => __( '1 day', 'bs-security-headers' ),
    604800   => __( '1 week', 'bs-security-headers' ),
    2592000  => __( '30 days', 'bs-security-headers' ),
    31536000 => __( '1 year — the usual end state', 'bs-security-headers' ),
];

$on_count = count( $preview_headers );
?>
<div class="wrap bssh-wrap">

    <div class="bssh-header">
        <h1><?php esc_html_e( 'Security Headers', 'bs-security-headers' ); ?></h1>
        <span class="bssh-pill <?php echo $on_count ? 'is-on' : 'is-off'; ?>">
            <?php
            printf(
                /* translators: %d: number of headers being sent */
                esc_html( _n( '%d header on', '%d headers on', $on_count, 'bs-security-headers' ) ),
                (int) $on_count
            );
            ?>
        </span>
    </div>

    <?php if ( $updated ) : ?>
        <div class="bssh-notice is-success"><p><?php esc_html_e( 'Settings saved.', 'bs-security-headers' ); ?></p></div>
    <?php endif; ?>

    <?php if ( in_array( 'nocsp', $notices, true ) ) : ?>
        <div class="bssh-notice is-error">
            <p><?php esc_html_e( 'The Content-Security-Policy was left off: write a policy in the box first. An empty policy sends no header at all.', 'bs-security-headers' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! Bssh_Headers::is_https() ) : ?>
        <div class="bssh-notice is-warning">
            <p><?php esc_html_e( 'This site is not being served over HTTPS right now, so HSTS will not be sent even if it is switched on. Browsers are required to ignore it on an insecure connection. Everything else on this screen works normally.', 'bs-security-headers' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr( Bssh_Plugin::SAVE_ACTION ); ?>">
        <?php wp_nonce_field( Bssh_Plugin::SAVE_ACTION ); ?>

        <!-- Standard headers ------------------------------------------------>
        <div class="bssh-card">
            <div class="bssh-card-header"><h2><?php esc_html_e( 'Standard headers', 'bs-security-headers' ); ?></h2></div>
            <div class="bssh-card-body">

                <p class="bssh-help bssh-lede">
                    <?php esc_html_e( 'These are on as soon as the plugin is activated. None of them can break a normal site. They apply to front-end pages only, because WordPress already sends its own versions inside the dashboard and on the login screen.', 'bs-security-headers' ); ?>
                </p>

                <!-- Referrer-Policy -->
                <div class="bssh-field bssh-field-toggle">
                    <label class="bssh-toggle">
                        <input type="checkbox" name="referrer_policy" value="1" data-bssh-controls="bssh-referrer" <?php checked( ! empty( $settings['referrer_policy'] ) ); ?>>
                        <span class="bssh-toggle-track"><span class="bssh-toggle-thumb"></span></span>
                        <span class="bssh-toggle-label"><?php esc_html_e( 'Referrer-Policy', 'bs-security-headers' ); ?></span>
                    </label>
                    <p class="bssh-help">
                        <?php esc_html_e( 'Controls how much of the current address is handed to another site when a visitor clicks a link out. Without it, the full URL of the page they were on travels with them, which on a search results or account page can leak more than anyone intended.', 'bs-security-headers' ); ?>
                    </p>
                    <div class="bssh-dependent" id="bssh-referrer" <?php echo empty( $settings['referrer_policy'] ) ? 'hidden' : ''; ?>>
                        <label for="bssh-referrer-value" class="bssh-sublabel"><?php esc_html_e( 'How much to send', 'bs-security-headers' ); ?></label>
                        <select name="referrer_policy_value" id="bssh-referrer-value">
                            <?php foreach ( Bssh_Plugin::REFERRER_POLICIES as $policy ) : ?>
                                <option value="<?php echo esc_attr( $policy ); ?>" <?php selected( $settings['referrer_policy_value'] ?? '', $policy ); ?>>
                                    <?php echo esc_html( $policy ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="bssh-help">
                            <strong>strict-origin-when-cross-origin</strong>
                            <?php esc_html_e( 'sends the full address within this site, and only the domain name to anyone else. It is the modern browser default and the right answer almost always. The stricter options send less and can break analytics or an affiliate link that expects a referrer.', 'bs-security-headers' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Permissions-Policy -->
                <div class="bssh-field bssh-field-toggle">
                    <label class="bssh-toggle">
                        <input type="checkbox" name="permissions_policy" value="1" data-bssh-controls="bssh-permissions" <?php checked( ! empty( $settings['permissions_policy'] ) ); ?>>
                        <span class="bssh-toggle-track"><span class="bssh-toggle-thumb"></span></span>
                        <span class="bssh-toggle-label"><?php esc_html_e( 'Permissions-Policy', 'bs-security-headers' ); ?></span>
                    </label>
                    <p class="bssh-help">
                        <?php esc_html_e( 'Tells the browser which features this site is allowed to ask for. Anything ticked below is refused outright, so nothing that finds its way onto a page can prompt a visitor for it. Anything left unticked carries on working exactly as it does now.', 'bs-security-headers' ); ?>
                    </p>
                    <div class="bssh-dependent" id="bssh-permissions" <?php echo empty( $settings['permissions_policy'] ) ? 'hidden' : ''; ?>>
                        <fieldset class="bssh-checklist">
                            <legend class="bssh-sublabel"><?php esc_html_e( 'Features to block', 'bs-security-headers' ); ?></legend>
                            <?php foreach ( Bssh_Plugin::POLICY_FEATURES as $feature ) : ?>
                                <label class="bssh-check <?php echo isset( $risky[ $feature ] ) ? 'is-risky' : ''; ?>">
                                    <input type="checkbox" name="permissions_policy_block[]" value="<?php echo esc_attr( $feature ); ?>" <?php checked( in_array( $feature, $blocked, true ) ); ?>>
                                    <span class="bssh-check-text">
                                        <span class="bssh-check-label"><?php echo esc_html( $labels[ $feature ] ?? $feature ); ?></span>
                                        <code><?php echo esc_html( $feature ); ?></code>
                                        <?php if ( isset( $risky[ $feature ] ) ) : ?>
                                            <span class="bssh-check-warn"><?php echo esc_html( $risky[ $feature ] ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </div>

                <!-- X-Frame-Options -->
                <div class="bssh-field bssh-field-toggle">
                    <label class="bssh-toggle">
                        <input type="checkbox" name="frame_options" value="1" data-bssh-controls="bssh-frame" <?php checked( ! empty( $settings['frame_options'] ) ); ?>>
                        <span class="bssh-toggle-track"><span class="bssh-toggle-thumb"></span></span>
                        <span class="bssh-toggle-label"><?php esc_html_e( 'X-Frame-Options', 'bs-security-headers' ); ?></span>
                    </label>
                    <p class="bssh-help">
                        <?php esc_html_e( 'Stops another website displaying these pages inside a frame on their own site, which is how a clickjacking attack gets someone to click something they cannot see. Switch this off for a site that is deliberately embedded somewhere else.', 'bs-security-headers' ); ?>
                    </p>
                    <div class="bssh-dependent" id="bssh-frame" <?php echo empty( $settings['frame_options'] ) ? 'hidden' : ''; ?>>
                        <label for="bssh-frame-value" class="bssh-sublabel"><?php esc_html_e( 'Who may frame this site', 'bs-security-headers' ); ?></label>
                        <select name="frame_options_value" id="bssh-frame-value">
                            <option value="SAMEORIGIN" <?php selected( $settings['frame_options_value'] ?? '', 'SAMEORIGIN' ); ?>>
                                <?php esc_html_e( 'SAMEORIGIN — this site only', 'bs-security-headers' ); ?>
                            </option>
                            <option value="DENY" <?php selected( $settings['frame_options_value'] ?? '', 'DENY' ); ?>>
                                <?php esc_html_e( 'DENY — nobody at all, including this site', 'bs-security-headers' ); ?>
                            </option>
                        </select>
                        <p class="bssh-help">
                            <?php esc_html_e( 'SAMEORIGIN is what xCloud already sends and what WordPress uses in the dashboard. DENY is stricter but blocks this site framing its own pages, which some page builders and preview tools rely on.', 'bs-security-headers' ); ?>
                        </p>
                    </div>
                </div>

                <!-- X-Content-Type-Options -->
                <div class="bssh-field bssh-field-toggle">
                    <label class="bssh-toggle">
                        <input type="checkbox" name="content_type_options" value="1" <?php checked( ! empty( $settings['content_type_options'] ) ); ?>>
                        <span class="bssh-toggle-track"><span class="bssh-toggle-thumb"></span></span>
                        <span class="bssh-toggle-label"><?php esc_html_e( 'X-Content-Type-Options', 'bs-security-headers' ); ?></span>
                    </label>
                    <p class="bssh-help">
                        <?php esc_html_e( 'Stops the browser guessing at a file type and running something as a script that was not meant to be one. Off by default because Kinsta and xCloud both already send it, and there is no point two things sending the same header. Switch it on if the check below shows it missing.', 'bs-security-headers' ); ?>
                    </p>
                </div>

            </div>
        </div>

        <!-- HSTS ------------------------------------------------------------>
        <div class="bssh-card">
            <div class="bssh-card-header"><h2><?php esc_html_e( 'HSTS', 'bs-security-headers' ); ?></h2></div>
            <div class="bssh-card-body">

                <div class="bssh-callout is-warning">
                    <p><strong><?php esc_html_e( 'This is the one setting here that cannot be taken back.', 'bs-security-headers' ); ?></strong></p>
                    <p><?php esc_html_e( 'HSTS is a note the site hands a visitor\'s browser saying "only ever talk to me over the secure connection, and remember that". The browser obeys for however long the note says, and nothing can reach back and tell it to forget sooner. If the certificate later breaks, those visitors get a hard wall with no way past it.', 'bs-security-headers' ); ?></p>
                    <p><?php esc_html_e( 'That is why the duration starts at five minutes. Switch it on, check the site, then come back and lengthen it. Only the duration you have already lived with is safe to keep.', 'bs-security-headers' ); ?></p>
                </div>

                <div class="bssh-field bssh-field-toggle">
                    <label class="bssh-toggle">
                        <input type="checkbox" name="hsts" value="1" id="bssh-hsts" data-bssh-controls="bssh-hsts-opts" data-bssh-confirm="1" <?php checked( ! empty( $settings['hsts'] ) ); ?>>
                        <span class="bssh-toggle-track"><span class="bssh-toggle-thumb"></span></span>
                        <span class="bssh-toggle-label"><?php esc_html_e( 'Send Strict-Transport-Security', 'bs-security-headers' ); ?></span>
                    </label>
                    <p class="bssh-help">
                        <?php esc_html_e( 'Your host already redirects insecure requests to the secure address. HSTS closes the sliver that redirect cannot: the very first request, which still leaves the visitor\'s device before the redirect comes back.', 'bs-security-headers' ); ?>
                    </p>
                    <div class="bssh-dependent" id="bssh-hsts-opts" <?php echo empty( $settings['hsts'] ) ? 'hidden' : ''; ?>>
                        <label for="bssh-hsts-max-age" class="bssh-sublabel"><?php esc_html_e( 'How long browsers should remember', 'bs-security-headers' ); ?></label>
                        <select name="hsts_max_age" id="bssh-hsts-max-age">
                            <?php foreach ( $durations as $seconds => $label ) : ?>
                                <option value="<?php echo esc_attr( (string) $seconds ); ?>" <?php selected( (int) ( $settings['hsts_max_age'] ?? 0 ), $seconds ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bssh-field">
                    <p class="bssh-help">
                        <strong><?php esc_html_e( 'Subdomains and preloading are not offered, on purpose.', 'bs-security-headers' ); ?></strong>
                        <?php esc_html_e( 'Covering subdomains takes down any that still run on an insecure connection, and preloading puts the domain on a list built into the browsers themselves that takes months and a browser release to leave. Neither belongs on a checkbox on a client site. If one is genuinely needed, it is a server configuration and a conversation.', 'bs-security-headers' ); ?>
                    </p>
                </div>

            </div>
        </div>

        <!-- CSP ------------------------------------------------------------->
        <div class="bssh-card">
            <div class="bssh-card-header"><h2><?php esc_html_e( 'Content-Security-Policy', 'bs-security-headers' ); ?></h2></div>
            <div class="bssh-card-body">

                <p class="bssh-help bssh-lede">
                    <?php esc_html_e( 'The strongest protection on this screen and the easiest to get wrong. A policy lists where the browser is allowed to load scripts, styles, fonts and images from, and refuses everything else. Get it wrong and something stops working quietly, often somewhere nobody looks for weeks.', 'bs-security-headers' ); ?>
                </p>
                <p class="bssh-help">
                    <?php esc_html_e( 'There is no fleet-wide default here because there cannot be one. A policy has to be written against what a particular site actually loads: its fonts, its analytics, its embeds, its payment scripts. Leave this off until that audit has been done.', 'bs-security-headers' ); ?>
                </p>
                <p class="bssh-help">
                    <strong><?php esc_html_e( 'Front-end pages only.', 'bs-security-headers' ); ?></strong>
                    <?php esc_html_e( 'WordPress sends its own policy in the dashboard and on the login screen to stop those being framed. This one is never sent there, because replacing it would leave the dashboard less protected than before.', 'bs-security-headers' ); ?>
                </p>

                <div class="bssh-field bssh-field-toggle">
                    <label class="bssh-toggle">
                        <input type="checkbox" name="csp" value="1" data-bssh-controls="bssh-csp-opts" <?php checked( ! empty( $settings['csp'] ) ); ?>>
                        <span class="bssh-toggle-track"><span class="bssh-toggle-thumb"></span></span>
                        <span class="bssh-toggle-label"><?php esc_html_e( 'Send a Content-Security-Policy', 'bs-security-headers' ); ?></span>
                    </label>
                    <div class="bssh-dependent" id="bssh-csp-opts" <?php echo empty( $settings['csp'] ) ? 'hidden' : ''; ?>>
                        <label for="bssh-csp-value" class="bssh-sublabel"><?php esc_html_e( 'The policy', 'bs-security-headers' ); ?></label>
                        <textarea name="csp_value" id="bssh-csp-value" class="bssh-code" rows="6" spellcheck="false" placeholder="default-src 'self'; script-src 'self' https://www.googletagmanager.com; ..."><?php echo esc_textarea( (string) ( $settings['csp_value'] ?? '' ) ); ?></textarea>
                        <p class="bssh-help">
                            <?php esc_html_e( 'Paste the policy without the header name. Line breaks are fine and get folded into spaces when it is sent.', 'bs-security-headers' ); ?>
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <div class="bssh-actions">
            <button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'bs-security-headers' ); ?></button>
        </div>

    </form>

    <!-- What this site sends now --------------------------------------------->
    <div class="bssh-card" id="bssh-server-check">
        <div class="bssh-card-header"><h2><?php esc_html_e( 'What this site sends now', 'bs-security-headers' ); ?></h2></div>
        <div class="bssh-card-body">

            <p class="bssh-help bssh-lede">
                <?php esc_html_e( 'Fetches two things: a stylesheet, which the web server hands over without ever starting WordPress, and the home page. Any header on the stylesheet came from the server itself, so the two columns together show what your host does on its own and what this plugin is adding or overriding.', 'bs-security-headers' ); ?>
            </p>

            <?php // A link rather than a form: nested forms are dropped by the browser. ?>
            <p><a class="button" rel="nofollow" href="<?php echo esc_url( $probe_url ); ?>"><?php esc_html_e( 'Check what this site sends', 'bs-security-headers' ); ?></a></p>

            <?php if ( $probe ) : ?>

                <?php foreach ( (array) $probe['errors'] as $line ) : ?>
                    <div class="bssh-callout is-error"><p><?php echo esc_html( $line ); ?></p></div>
                <?php endforeach; ?>

                <?php if ( $probe['ok'] || $probe['static'] ) : ?>
                    <?php
                    $php_only = Bssh_Server_Check::from_php( $probe );
                    $rows     = array_unique( array_merge(
                        Bssh_Server_Check::REPORTED,
                        array_keys( $probe['static'] ),
                        array_keys( $probe['page'] )
                    ) );
                    ?>
                    <table class="bssh-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Header', 'bs-security-headers' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'From the server alone', 'bs-security-headers' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'What a visitor gets', 'bs-security-headers' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $rows as $name ) : ?>
                            <?php
                            $from_server = $probe['static'][ $name ] ?? '';
                            $from_page   = $probe['page'][ $name ] ?? '';
                            if ( '' === $from_server && '' === $from_page ) {
                                $state = 'is-absent';
                            } elseif ( in_array( $name, $php_only, true ) ) {
                                $state = 'is-php';
                            } elseif ( '' !== $from_server && '' !== $from_page && $from_server !== $from_page ) {
                                $state = 'is-overridden';
                            } else {
                                $state = 'is-server';
                            }
                            ?>
                            <tr class="<?php echo esc_attr( $state ); ?>">
                                <th scope="row"><code><?php echo esc_html( $name ); ?></code></th>
                                <td>
                                    <?php if ( '' === $from_server ) : ?>
                                        <span class="bssh-muted"><?php esc_html_e( 'not sent', 'bs-security-headers' ); ?></span>
                                    <?php else : ?>
                                        <code><?php echo esc_html( $from_server ); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( '' === $from_page ) : ?>
                                        <span class="bssh-muted"><?php esc_html_e( 'not sent', 'bs-security-headers' ); ?></span>
                                    <?php else : ?>
                                        <code><?php echo esc_html( $from_page ); ?></code>
                                        <?php if ( 'is-php' === $state ) : ?>
                                            <span class="bssh-tag"><?php esc_html_e( 'added by WordPress', 'bs-security-headers' ); ?></span>
                                        <?php elseif ( 'is-overridden' === $state ) : ?>
                                            <span class="bssh-tag is-warning"><?php esc_html_e( 'overriding the server', 'bs-security-headers' ); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="bssh-help">
                        <strong><?php esc_html_e( 'Reading this:', 'bs-security-headers' ); ?></strong>
                        <?php esc_html_e( 'anything in the first column is set by your host and applies to every file it serves, including scripts, stylesheets and images. Anything that appears only in the second column is set by WordPress, which means this plugin, the theme, or another plugin, and it reaches HTML pages only. Where both columns differ, WordPress is replacing the host\'s value.', 'bs-security-headers' ); ?>
                    </p>
                <?php endif; ?>

                <p class="bssh-result-meta">
                    <?php
                    printf(
                        /* translators: 1: time since the check ran, 2: the static file URL used */
                        esc_html__( 'Checked %1$s ago, using %2$s for the server-only reading.', 'bs-security-headers' ),
                        esc_html( human_time_diff( (int) $probe['checked_at'] ) ),
                        esc_html( (string) $probe['static_url'] )
                    );
                    ?>
                </p>
            <?php endif; ?>

        </div>
    </div>

    <!-- Exactly what goes out ------------------------------------------------>
    <div class="bssh-card">
        <div class="bssh-card-header"><h2><?php esc_html_e( 'Exactly what this plugin sends', 'bs-security-headers' ); ?></h2></div>
        <div class="bssh-card-body">
            <p class="bssh-help bssh-lede">
                <?php esc_html_e( 'The saved settings above, rendered as the headers they produce on a front-end page over HTTPS. This is generated by the same code that sends them, so it cannot drift from what actually ships.', 'bs-security-headers' ); ?>
            </p>
            <?php if ( $preview_headers ) : ?>
                <pre class="bssh-preview"><?php
                    foreach ( $preview_headers as $name => $value ) {
                        echo esc_html( $name . ': ' . $value ) . "\n";
                    }
                ?></pre>
            <?php else : ?>
                <p class="bssh-help"><em><?php esc_html_e( 'Nothing. Every header is switched off.', 'bs-security-headers' ); ?></em></p>
            <?php endif; ?>
        </div>
    </div>

</div>
