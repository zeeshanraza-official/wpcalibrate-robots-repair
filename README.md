# WPCalibrate Robots Repair

[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Release](https://img.shields.io/badge/Version-1.0.1-orange.svg)](https://github.com/zeeshanraza-official/wpcalibrate-robots-repair/releases)
[![RFC 9309](https://img.shields.io/badge/Standard-RFC%209309%20REP-success.svg)](https://www.rfc-editor.org/rfc/rfc9309.html)

**WPCalibrate Robots Repair** is a professional, production-ready WordPress plugin that diagnoses, explains, safely repairs, manages, and verifies WordPress `robots.txt` issues.

Unlike generic text editors, WPCalibrate is built around an end-to-end reliability workflow:
$$\text{Scan} \longrightarrow \text{Diagnose} \longrightarrow \text{Explain} \longrightarrow \text{Repair} \longrightarrow \text{Verify}$$

---

## Table of Contents
1. [About](#about)
2. [Why Ordinary Robots.txt Editors Fail](#why-ordinary-robotstxt-editors-fail)
3. [Key Features](#key-features)
4. [Architecture & Reliability Workflow](#architecture--reliability-workflow)
5. [Dashboard Tour & Sections](#dashboard-tour--sections)
6. [Preset Rules & Standards](#preset-rules--standards)
7. [Installation & Dashboard Updates](#installation--dashboard-updates)
8. [Documentation & FAQs](#documentation--faqs)
9. [Changelog](#changelog)
10. [Security & Privacy](#security--privacy)
11. [License & Support](#license--support)

---

## About

Search engines rely on your site's `/robots.txt` file (governed by [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309.html) - Robots Exclusion Protocol) to understand how to crawl your WordPress installation. A single malformed line or accidental `Disallow: /` directive can completely blind Google, Bing, and other crawlers to your entire website—costing rankings, traffic, and revenue.

**WPCalibrate Robots Repair** bridges the gap between complex RFC 9309 specifications and everyday WordPress administration. It analyzes your live public endpoint, translates technical directives into plain language, guides you through safe minimal repairs with automated backups, and performs mandatory post-repair verification to ensure search engines actually receive the corrected output.

---

## Why Ordinary Robots.txt Editors Fail

| Challenge | Ordinary Editor / Default WP | WPCalibrate Robots Repair |
|---|---|---|
| **Accidental Global Blocking** | Saves `Disallow: /` silently with no warning | Flags `Disallow: /` under `*` as **Critical**, explains crawler impact, and prevents accidental commits |
| **Asset Blocking (CSS/JS)** | Allows blocking of `/wp-content/themes/` | Detects blocked themes, plugins, and uploads that prevent search engines from rendering pages |
| **Physical vs. Virtual Confusion** | Cannot tell if a disk file is overriding WordPress rewrite rules | Accurately distinguishes physical files on disk from virtual filter output |
| **Destructive Overwrites** | Overwrites without backups | Automatically creates timestamped, SHA-256 hashed pre-repair backups before any write |
| **Verification Blind Spot** | Assumes local save means crawlers see it | Performs real-time HTTP fetch to verify public HTTP 200, response headers, CDN caching, and WAF rules |
| **Sitemap Validation** | Accepts broken sitemap URLs | Verifies HTTP reachability and content-type for all declared XML sitemaps |

---

## Key Features

- **Public Network Diagnostics (SSRF-Protected)**: Fetches canonical `/robots.txt` using WordPress HTTP APIs with strict scheme, host, and port validation.
- **RFC 9309 Compliant Rule Parser**: Accurately extracts User-agent groups, preserves comments, cleans directive values, and preserves vendor-specific extensions.
- **3-State Severity Diagnostics Engine**: Categorizes crawl health into **Healthy**, **Warning**, and **Critical** with actionable recommendations and evidence.
- **Source Attribution Service**: Identifies whether the active output is delivered by a physical file on disk, WordPress dynamic virtual output, or a third-party SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework).
- **Physical & Virtual Management**: Safely updates virtual rules via priority 99 filters, or writes directly to `ABSPATH . 'robots.txt'` using `WP_Filesystem` with explicit confirmation.
- **Automated Pre-Repair Backups**: Every repair, preset switch, or custom edit creates an atomic backup with SHA-256 checksums and configurable retention.
- **One-Click Safe Presets**:
  - **Recommended WordPress Baseline**: Allows crawlers to access public assets while protecting `/wp-admin/` and preserving `/wp-admin/admin-ajax.php`.
  - **WooCommerce Store Preset**: Dynamically protects transactional endpoints (`/cart/`, `/checkout/`, `/*add-to-cart=*`) without interfering with HPOS or store operations.
  - **Custom Safe Rules**: Full syntax checking with real-time dangerous rule detection.
- **Mandatory Post-Repair Verification**: Compares public HTTP fetch output against saved configuration to confirm CDN, cache, and WAF layers are not serving stale rules.
- **Search Engine Visibility Synchronization**: Detects if WordPress "Discourage search engines" (`blog_public = 0`) is active and clarifies the distinction between crawling restrictions and indexation tags (`noindex`).
- **Clean License & Update Architecture**: Seamless dashboard updates via GitHub Releases and GitHub Updater.

---

## Architecture & Reliability Workflow

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Live HTTP   │ ──> │   RFC 9309   │ ──> │   3-State    │
│  Public Scan │     │ Rule Parser  │     │  Validator   │
└──────────────┘     └──────────────┘     └──────────────┘
                                                 │
                                                 ▼
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Mandatory   │ <── │  Pre-Repair  │ <── │ Plain-Text   │
│ Verification │     │    Backup    │     │ Explanations │
└──────────────┘     └──────────────┘     └──────────────┘
```

1. **Scan**: Evaluates canonical URL HTTP response code, content size, response headers, and disk presence.
2. **Diagnose**: Parses lines, groups directives by `User-agent`, detects orphaned rules, and tests sitemap endpoints.
3. **Explain**: Translates RFC 9309 rules into human terms (e.g., explaining why blocking `/wp-content/plugins/` prevents mobile-friendliness audits).
4. **Repair**: Previews unified diffs before writing. Enforces explicit confirmation for physical disk file operations.
5. **Verify**: Makes an immediate background HTTP request to ensure the live public web server serves the exact new content.

---

## Dashboard Tour & Sections

The WPCalibrate admin portal is accessible under **WPCalibrate → Robots Repair** in the WordPress sidebar:

- **Overview**: Crawl health banner, public HTTP status, active source indicator, sitemap reachability, and live preview of public `/robots.txt`.
- **Diagnostics**: Detailed breakdown of Healthy, Warning, and Critical findings with impact explanations and one-click repair triggers.
- **Editor & Presets**: Line-aware custom editor with instant preset injection (WordPress Core, WooCommerce, Clean Slate) and syntax highlighting.
- **Backups**: Versioned backup history displaying timestamps, source types, MD5/SHA hashes, one-click preview modals, atomic restore, and safe deletion.
- **Settings**: Automated WP-Cron crawl health check scheduling, backup retention limits (3 to 50 versions), physical file handling preferences, and diagnostic logging.
- **License**: Subscription and API connection portal with masked keys and clean unconfigured-provider abstraction.
- **Support**: Verified documentation links, FAQs, and direct WPCalibrate support contact channels.

---

## Preset Rules & Standards

### Recommended WordPress Preset
```robots.txt
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: https://example.com/wp-sitemap.xml
```

### WooCommerce Store Preset (Dynamic)
```robots.txt
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php
Disallow: /cart/
Disallow: /checkout/
Disallow: /my-account/
Disallow: /*add-to-cart=*
Disallow: /*?add-to-cart=

Sitemap: https://example.com/wp-sitemap.xml
```

---

## Installation & Dashboard Updates

### Method 1: WordPress Dashboard Upload (Recommended)
1. Download the latest release ZIP [`wpcalibrate-robots-repair-1.0.1.zip`](https://github.com/zeeshanraza-official/wpcalibrate-robots-repair/releases/latest).
2. Go to **Plugins → Add New → Upload Plugin** in your WordPress dashboard.
3. Choose the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### Method 2: Git Clone
```bash
cd wp-content/plugins/
git clone https://github.com/zeeshanraza-official/wpcalibrate-robots-repair.git
```

### Automated Updates From WordPress Dashboard
This plugin includes standard GitHub update headers:
- `Update URI: https://github.com/zeeshanraza-official/wpcalibrate-robots-repair`
- `GitHub Plugin URI: zeeshanraza-official/wpcalibrate-robots-repair`
- `Primary Branch: main`
- `Release Asset: true`

Whenever a new version is released on GitHub, WordPress (via GitHub Updater or standard update checks) detects the new release and offers a one-click update directly in **Dashboard → Updates**.

---

## Documentation & FAQs

#### Does robots.txt remove pages from Google search results?
**No.** `robots.txt` governs crawler access (crawling), not search index inclusion (indexing). If external websites link to a URL blocked by `robots.txt`, search engines may still index the URL as an empty link snippet. To guarantee complete removal from search indexes, use `<meta name="robots" content="noindex">` or password protection.

#### What is the difference between physical and virtual robots.txt?
- **Physical**: A real file located at `ABSPATH . 'robots.txt'` on your server disk. Web servers (Nginx/Apache/LiteSpeed) serve this file directly before WordPress executes.
- **Virtual**: When no physical file exists, WordPress dynamically generates virtual output via rewrite rules and the `robots_txt` filter hook. WPCalibrate seamlessly detects and manages both.

#### Does this plugin require WooCommerce?
**No.** WooCommerce is 100% optional. If WooCommerce is installed and active, an optimized WooCommerce Store preset becomes available.

#### Will this plugin modify my database or WooCommerce orders?
**Never.** WPCalibrate strictly touches robots.txt rules and creates backups in `wp_options`. It never touches products, orders, customers, sessions, or custom tables.

---

## Changelog

### [1.0.1] - 2026-10-05
- **Fixed**: Admin sidebar menu icon display and scaling using official brand icon.
- **Fixed**: Backups history table row action buttons layout, alignment, and spacing.
- **Fixed**: `LicenseProviderInterface` autoloading resolution and template architecture.
- **Added**: Unit test suite for license provider abstraction and automated lifecycle.
- **Added**: GitHub Updater integration for automated dashboard updates.

### [1.0.0] - 2026-10-05
- Initial production release.
- Complete Scan → Diagnose → Explain → Repair → Verify workflow.
- RFC 9309 rule parser and 3-state severity diagnostics engine.
- Bounded backup repository and atomic restore manager.
- Physical file and virtual robots.txt management.
- Comprehensive presets for standard WordPress and WooCommerce stores.
- 12 automated test suites with 81 assertions passing 100%.

---

## Security & Privacy

- **Capability Checks**: All diagnostic scans, saves, and restorations require the `manage_options` capability (filterable via `wpcalibrate_robots_repair_capability`).
- **CSRF Defense**: All actions are validated against nonce tokens (`wcrr_nonce`).
- **SSRF Defense**: Live HTTP fetcher validates URL schemes (`http`, `https`), restricts requests strictly to the site's canonical home URL, and blocks private/loopback IP ranges in production.
- **Path Traversal Protection**: Filesystem writes are strictly constrained to `ABSPATH . 'robots.txt'`. Directory traversal (`../`) is blocked.
- **Atomic Operations**: Automated pre-repair backups ensure zero data loss during writes or restores.

---

## License & Support

- **License**: [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE)
- **Author**: WPCalibrate
- **Website**: [wpcalibrate.com](https://wpcalibrate.com)
- **Marketplace**: [marketplace.wpcalibrate.com](https://marketplace.wpcalibrate.com/)
- **Support Email**: [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
