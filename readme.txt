=== Live Carts for WooCommerce: Track Real-Time, Abandoned, and Converted Carts! ===
Contributors: penthouseplugins
Tags: woocommerce, cart, basket, realtime, ecommerce
Requires at least: 6.0
Tested up to: 6.5.2
Requires PHP: 7.0
Stable tag: 1.0.10
License: GNU General Public License version 3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Monitor your customers' current and past WooCommerce shopping carts via the WordPress admin.

== Description ==

This plugins adds a "Live Carts" item to the WooCommerce menu in the WordPress admin, which provides a listing of customer shopping carts including statuses (active, abandoned, or converted) and values. Clicking a cart ID provides more details including listing the products in the cart.

Optionally, the plugin can display a cart ID on the frontend, visible to the customer. The admin interface supports searching for carts based on this ID. This allows store support to look up a customer cart if the customer provides their cart ID.

The plugin also calculates some basic cart statistics at Analytics > Carts, with date filtering based on the cart creation date.

=== Features ===

* List real-time and past carts
* Filter cart list by status (Active/Converted/Abandoned)
* See the name of the logged-in user (if any) associated with each cart
* See the most recent URL that each cart was loaded on
* View a list of products in the cart and the total cart value, along with the coupon code that has been added to the cart, if any
* Search for carts by unique cart ID
* Optionally display the cart ID on the frontend to easily match to a cart record in the backend
* Optionally disable collection of IP addresses and/or last visited URLs in this plugin's cart database
* Automatically archive older cart records
* Get cart conversion rate, cart abandonment rate, and average cart value metrics for a specified period of time (under Analytics > Carts)
* Cart data collected by this plugin is stored in custom database tables to avoid cluttering default WordPress tables
* Built-in extensibility via filter and action hooks

== Frequently Asked Questions ==

== Installation ==

This plugin can be installed under Plugins > Add New Plugin, either by searching for the plugin title or by downloading the plugin zip file and uploading it via the Upload Plugin feature.

== Changelog ==

=== 1.0.10 ===

* Improve compatibility with woocommerce-paypal-payments plugin

=== 1.0.9 ===

* Fix PHP warnings when no cart exists
* Database query improvement

=== 1.0.8 ===

* Add hooks to improve extensibility

=== 1.0.7 ===

* Fix fatal error after upgrade from version 1.0.5

=== 1.0.6 ===

* Add separate plugin settings page
* Add option not to save site visitor IP addresses
* Add feature to record the last frontend URL that the cart was seen on (can be disabled in plugin settings)
* Add tracking of coupons in carts
* Add color indicators to cart status column for quick status recognition
* Add WooCommerce as plugin dependency

=== 1.0.5 ===

* Add additional permissions checks for admin functionality

=== 1.0.4 ===

* Fix: The admin stylesheet was not being loaded
* Change cart IDs to be random instead of sequential
* Display the cart ID in the cart details admin page
* Add option to display the cart ID in the frontend (to make it easier to identify a specific customer cart in the backend)

=== 1.0.3 ===

* Fix: The cart value wasn't displaying in the carts list
* Fix: Missing escaping on cart view page
* Add a User column to the carts list
* Hide the cart contents table on the cart view page when there are no contents to show

=== 1.0.2 ===

* Fix: Various Exceptions may not have been caught successfully
* Add debug mode (enable under WooCommerce > Live Carts; the log is saved in this plugin's directory)
* Add additional Exception and Error handling for 3 critical WooCommerce hooks

=== 1.0.1 ===

* Add Analytics functionality
* Miscellaneous improvement(s)

=== 1.0.0 ===

* Initial release