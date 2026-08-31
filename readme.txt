=== Caddy Souin Cache Manager ===
Contributors: morsalin1342
Tags: caddy, souin, cache, purge, performance, http-cache, caching, reverse-proxy
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.8.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Souin cache for your Caddy web server directly from the WordPress admin dashboard — view cache keys, purge cache, and automate invalidation.

== Description ==

The Caddy Souin Cache Manager plugin provides seamless integration between your WordPress site and your Caddy server's Souin caching layer. Take full control of your site's cache without ever leaving the WordPress admin area.

**Key Features:**

* View all Souin cache keys directly in WP Admin
* One-click full cache purge
* Selective purging by key, selection, or regular expression
* Multiple backends: HTTP API or direct Redis connection
* Automatic cache invalidation on content updates
* Full WP-CLI integration (`wp souin` commands)
* All actions protected by WordPress nonces (admin only)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/caddy-souin-cache-manager/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Souin Cache → Settings to configure your Souin API URL

== Configuration ==

The Souin API must be enabled in your Caddyfile. Add to your Caddy config:

```
# Global options - defines the admin API port
{
    admin :2019
}

# Site configuration
example.com {
    souin {
        api {
            basepath /souin-api
        }
    }
}
```

With the example above the plugin's API URL is
`http://127.0.0.1:2019/souin-api/souin` - the admin port, the base path, then
`/souin`.

== Changelog ==

See `changelog.txt` for the full changelog.
