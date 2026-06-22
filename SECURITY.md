# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in this plugin, please report it by opening an issue on GitHub:

- [Report a vulnerability](https://github.com/morsalin1342/caddy-souin-cache-manager/issues/new)

Please include:
- A clear description of the vulnerability
- Steps to reproduce
- The plugin version affected

We aim to respond within 48 hours and publish a fix as soon as possible.

## Scope

This plugin is a WordPress admin interface for the Caddy Souin cache API. Security concerns may involve:
- Unauthorized cache access or manipulation
- Cross-site scripting (XSS) in the admin UI
- CSRF vulnerabilities in cache management actions
- Information disclosure through cache key exposure

All admin actions are protected by WordPress nonces and require the `manage_options` capability.
