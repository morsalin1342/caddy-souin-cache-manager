# Caddy Souin Cache Manager for WordPress

[![Release](https://img.shields.io/github/v/release/morsalin1342/caddy-souin-cache-manager?style=for-the-badge&logo=github)](https://github.com/morsalin1342/caddy-souin-cache-manager/releases)
[![GitHub Stars](https://img.shields.io/github/stars/morsalin1342/caddy-souin-cache-manager?style=for-the-badge&logo=github)](https://github.com/morsalin1342/caddy-souin-cache-manager)
[![License](https://img.shields.io/badge/license-GPLv2%20or%20later-blue?style=for-the-badge)](license.txt)
[![Requires PHP](https://img.shields.io/badge/php-8.1%2B-blue?style=for-the-badge&logo=php)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?style=for-the-badge&logo=wordpress)](https://wordpress.org/)

A simple yet powerful plugin to manage the Souin cache for your Caddy web server directly from your WordPress admin dashboard.

## ✨ Why This Plugin?

| Task | Without it | With it |
|------|-----------|---------|
| **See what is cached** | `curl` the Souin API and read raw JSON | ✅ Browsable key list in WP Admin |
| **Purge one entry** | Hand-built API call with the exact key | ✅ One click, or `wp souin purge-key` |
| **Purge by pattern** | ❌ Not practical by hand | ✅ Regex purge |
| **Invalidate on content change** | ❌ Manual, every time | ✅ Automatic on post, page and comment changes |
| **Redis backend** | Separate tooling | ✅ Direct connection |
| **Automation / CI** | Shell scripts around `curl` | ✅ Full `wp souin` command suite |

## Description

The Caddy Souin Cache Manager plugin provides a seamless integration between your WordPress site and your Caddy server's Souin caching layer. It empowers you to take full control of your site's cache without ever leaving the WordPress admin area.

No more command-line access needed for routine cache management. The plugin provides a clean and intuitive interface to view all cached items, purge the entire cache, or selectively clear items by key, selection, or regular expression.

It also features intelligent automatic purging, which clears the cache for posts, pages, and the homepage whenever your content is updated, ensuring your visitors always see the latest version of your site.

## Key Features

*   **View Cache Keys:** See all Souin cache keys directly in your WP Admin dashboard.
*   **One-Click Full Purge:** Clear the entire cache with a single click.
*   **Selective Purging:**
    *   Purge individual cache items.
    *   Bulk purge selected items.
    *   Purge by a flexible regular expression.
*   **Multiple Backends:** Connect to the Souin cache via the HTTP API or directly to a Redis backend.
*   **Automatic Cache Invalidation:** Automatically purges the cache for posts, pages, or comments when they are created, updated, or deleted.
*   **WP-CLI Integration:** A full suite of `wp souin` commands for power users and automation.
*   **Secure:** All actions are protected by WordPress nonces and require administrator privileges (`manage_options` capability).
*   **Lightweight & Compatible:** The plugin is designed to be developer-friendly and makes no assumptions about your cache key structure, making it compatible with a wide range of Caddy and Souin configurations.

## Requirements

*   WordPress 5.8 or later
*   PHP 8.1 or later
*   Caddy web server with the [Souin](https://souin.io/) caching module configured.
*   The Souin API must be enabled in your Caddyfile.

## Installation

1.  Download the latest release from the WordPress.org plugin repository.
2.  Upload the `caddy-souin-cache-manager` folder to your `/wp-content/plugins/` directory.
3.  Activate the plugin through the **Plugins** menu in WordPress.
4.  Proceed to the configuration steps below.

## Configuration

1.  In your WordPress admin dashboard, navigate to **Tools > Souin Cache**.
2.  In the settings section, enter your **Souin API URL**.
3.  Click **Save Settings**.

By default, the URL is set to `http://127.0.0.1:2019/souin-api/souin`, which is a common default for local Caddy instances. Adjust the protocol, domain, port, and base path to match your server's Caddy and Souin configuration.

## Usage

### WordPress Dashboard

All UI-based cache management is done from the **Tools > Souin Cache** screen. Here, you will find a list of all cached keys and options to purge them.

### WP-CLI Commands

This plugin provides a full suite of WP-CLI commands for managing the Souin cache from the command line.

#### `wp souin status`

Displays the current plugin configuration and tests the connection to the Souin API.

```shell
$ wp souin status
+-------------------+------------------------------------------+
| Key               | Value                                    |
+-------------------+------------------------------------------+
| Souin API URL     | http://127.0.0.1:2019/souin-api/souin      |
| Auto Purge        | Enabled                                  |
| API Connection    | Connection successful! Found 42 keys.    |
+-------------------+------------------------------------------+
```

#### `wp souin list`

Lists all keys currently in the Souin cache. Supports different output formats.

**Example: List all keys as a table (default)**
```shell
$ wp souin list
```

**Example: List all keys in JSON format**
```shell
$ wp souin list --format=json
```

#### `wp souin purge-all`

Purges the entire Souin cache. A confirmation prompt will be shown before proceeding.

```shell
$ wp souin purge-all
Are you sure you want to purge the entire Souin cache? [y/n] y
Success: The entire Souin cache has been purged.
```

To bypass the confirmation prompt, use the `--yes` flag:
```shell
$ wp souin purge-all --yes
```

#### `wp souin purge-key <key>...`

Purges one or more specific keys from the cache. You can pass multiple keys separated by spaces.

```shell
# Purge a single key
$ wp souin purge-key "KEY-GET-https-example.com-/"

# Purge multiple keys
$ wp souin purge-key "KEY-GET-https-example.com-/" "KEY-GET-https-example.com-/about/"
```

#### `wp souin purge-regex <pattern>`

Purges all cache keys that match a given regular expression pattern. The pattern should be a valid PCRE expression.

**Example: Purge all keys related to a specific product page**
```shell
$ wp souin purge-regex ".*\/product\/cool-widget\/.*"
```

## ❓ FAQ

**How do I find my Souin API URL?**

Your Souin API URL is defined in your `Caddyfile`. The API is part of Caddy's admin endpoint, which is configured in the global options at the top of your `Caddyfile`. The Souin-specific path is then configured within the `souin` directive.

**Example `Caddyfile`:**
```
# Global options - defines the admin API port
{
    admin :2019
}

# Site configuration
example.com {
    # Other directives...
    
    # Souin configuration
    souin {
        # Enable the Souin API and set its base path
        api {
            basepath /souin-api
        }
    }
    
    # Other directives...
}
```

Based on the example above:
*   Your Caddy admin server is running on port `:2019`.
*   The Souin API base path is `/souin-api`.
*   The final part of the path is always `/souin`.

Therefore, the full API URL for the plugin would be: `http://127.0.0.1:2019/souin-api/souin`. Remember to replace `127.0.0.1` with your server's domain or IP if Caddy is not running on the same machine as WordPress.

**Is this plugin secure?**

Yes. All purge actions are protected with WordPress nonces and require the `manage_options` user capability, which is typically reserved for Administrators. This prevents unauthorized users from performing cache operations.

**Will this work with any cache key format?**

Yes. The plugin sends the literal, unmodified cache key for all manual purge operations, making it compatible with any key structure Souin might use. The automatic purge feature uses a broad regex pattern to ensure all variants of a URL are purged.

## Changelog

### 1.8.14
*   **Fix**: Implemented a dynamic header positioning script to prevent the sticky header from overlapping with WordPress admin notices.
*   **Fix**: Removed the footer credit line and added vertical margin to the main content area for better spacing.

### 1.8.13
*   Complete architecture refactor.
*   New Vue.js powered admin interface.
*   Tailwind CSS v4 integration.
*   REST API support.

## Related Images & Tools

This plugin manages the **Souin** cache, which ships in these two images. Either one gives you
a Caddy with Souin already compiled in, so the only setup left is enabling the API in your
Caddyfile.

| Repository | Images | Description |
|---|---|---|
| [caddy-docker](https://github.com/morsalin1342/caddy-docker) | `morsalin1342/caddy` · `easydigital/caddy` | Standalone Caddy — Souin with Redis, Otter and SimpleFS backends |
| [frankenphp-docker](https://github.com/morsalin1342/frankenphp-docker) | `morsalin1342/frankenphp` · `easydigital/frankenphp` | Caddy + PHP app server, same Souin stack compiled in |

Also in this family, though neither ships Souin:
[php-docker](https://github.com/morsalin1342/php-docker) (PHP-FPM & CLI) and
[nginx-docker](https://github.com/morsalin1342/nginx-docker) (nginx with ModSecurity 3, Brotli and GeoIP2).

---

## Feedback and Issues

If you have suggestions, find a bug, or want to request a feature, please [open an issue](https://github.com/morsalin1342/caddy-souin-cache-manager/issues) on the GitHub repository.

---

## License

This plugin is licensed under the GPLv2 or later. See the [license.txt](license.txt) file for more details.

---

⭐ **If this project helps you, consider giving it a star!**
