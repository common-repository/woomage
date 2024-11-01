=== Plugin Name ===
Contributors: Woomage
Requires at least: 4.1
Tested up to: 4.8.1
Tags: woocommerce, woomage, store manager, inventory management, rest api
Stable tag: 4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Woomage Store Manager API to WooCommerce

== Description ==

Woomage API plugin provides access for Woomage application to sync products with WooCommerce online store. Woomage is installable desktop application.

With Woomage Store Manager application, you can ease and speed up your inventory management, creation, editing or deleting of products and categories in your WooCommerce store. You can download Woomage at http://www.woomage.com.

== Installation ==

1. Upload the entire 'woomage-woocommerce' folder to the '/wp-content/plugins/' directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= 1. How to use Woomage? =
1. Install Woomage API plugin to you WooCommerce server.
2. Then you need to enable REST API at WooCommerce settings and generate API keys, see https://docs.woocommerce.com/document/woocommerce-rest-api/
3. To use API, You need to download and install Woomage application from www.woomage.com.
4. Add your server URL and API keys to Woomage desktop application and you can manage/sync products via Woomage app.

= 1. Can I edit product with Woomage API plugin? =
No, at the moment, it's not possible. To edit products, You need Woomage desktop application, which connects to your WooCommerce Store via this plugin.


== Changelog ==
= 0.10.7 =
* Fixed v3 to edit product type
= 0.10.6 =
* Correct version set to 0.10.6
= 0.10.5 =
* Fixed WC version parsing to select correct API for WooCommerce V3
= 0.10.4 =
* PHP 5.3 compatibility changes
= 0.10.3 =
* WooCommerce v3.x support
= 0.9.19 =
* SKu match corrected for bulk creation/editing
= 0.9.18 =
* Reviews allowed in post creation & existing variation SKU does not matter in update
= 0.9.17 =
* Product types delivered in index & grouped products
= 0.9.16 =
* Plugin version update
= 0.9.15 =
* Product attribute API changes
= 0.9.14 =
* Package attributes
= 0.9.13 =
* Package attributes
= 0.9.12 =
* Product sync fix
= 0.9.11 =
* Image sync fix
= 0.9.10 =
* Bi-directional sync with many other new features & bug fixes
= 0.9.9 =
* Corrections to update bulk and single products
= 0.9.8 =
* Category sync handling improved to support all platforms
= 0.9.7 =
* Category sync handling improved for two-way sync
= 0.9.6 =
* Initial Release
