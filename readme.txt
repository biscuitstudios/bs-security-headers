=== Security Headers ===
Contributors: biscuitstudios
Tags: security headers, csp, hsts, permissions policy, referrer policy
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends the response security headers managed hosts and WordPress leave off, and shows you which ones came from where.

== Description ==

See the README on GitHub for the full description, requirements, and known
limitations: https://github.com/biscuitstudios/bs-security-headers

Built and maintained by Biscuit Studios for our own client sites. Published
as-is, with no support. Forks welcome.

== Installation ==

1. Download the zip from the Releases page on GitHub.
2. Plugins > Add New > Upload Plugin.
3. Activate. The three safe headers start sending straight away.

== Changelog ==

= 0.1.0 =
* New: sends Referrer-Policy, Permissions-Policy and X-Frame-Options from the
  moment the plugin is activated, with no configuration. None of the three can
  break a normal site, so installing the plugin is the whole rollout.
* New: optional Strict-Transport-Security, with a duration that starts at five
  minutes so it can be switched on and checked before it is lengthened. There is
  no setting for includeSubDomains or preload, and the code cannot assemble
  either token. Both are effectively permanent once a browser has seen them.
* New: optional Content-Security-Policy, off by default, entered per site. There
  is no fleet-wide default because a policy has to be written against what a
  particular site actually loads.
* New: optional X-Content-Type-Options, off by default because Kinsta and xCloud
  already send it. Included so the plugin still does the right thing if a site
  moves to a host that does not.
* New: a check that fetches a static file and the home page and reports the
  headers side by side. The static file never starts WordPress, so anything on
  it came from the server, which is what separates "the host does this" from
  "we do this".
* Note: the plugin stays out of WordPress's way in wp-admin and on the login
  screen, where core already sends X-Frame-Options, Referrer-Policy and a
  frame-ancestors policy. The site's own CSP is never sent there, because PHP
  replaces headers rather than merging them and doing so would delete core's
  protection against the dashboard being framed.
* Note: X-XSS-Protection and Expect-CT are not implemented. Both are dead, and
  the filter the first one enabled was itself an attack vector. The check screen
  reports them if something else on the site is still sending them.
