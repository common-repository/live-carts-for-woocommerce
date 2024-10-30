<?php
/*
Plugin Name: Live Carts for WooCommerce
Version: 1.0.10
Description: Monitor your customers' current and past WooCommerce shopping carts via the WordPress admin.
License: GNU General Public License version 3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires Plugins: woocommerce
Text Domain: live-carts-for-woocommerce
*/

namespace Penthouse\LiveCarts;

class LiveCarts {
	const VERSION = '1.0.10', CART_ABANDON_TIME = 7200, CART_ARCHIVE_DAYS = 30, ADMIN_CAPABILITY = 'manage_woocommerce';

	private $currentCart, $currentCartId;
	private static $instance;

	public static function instance() {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action('woocommerce_cart_loaded_from_session', [$this, 'onCartLoaded']);
		add_action('woocommerce_after_calculate_totals', [$this, 'updateCartContents']);
		add_action('current_screen', [$this, 'onCurrentScreen']);
		add_filter('set_screen_option_woocommerce_page_live-carts-for-woocommerce_per_page', [$this, 'filterPerPageScreenOption'], 10, 3);
		add_action('admin_menu', [$this, 'onAdminMenu']);
		if (isset($_POST['phplugins_carts_settings_save'])) {
			add_action('admin_init', [$this, 'saveSettings']);
		}
		add_action('phplugins_livecarts_hourly', [$this, 'hourlyScheduledTasks']);

		add_action('woocommerce_order_status_changed', [$this, 'onOrderStatusChanged'], 10, 4);
		add_action('admin_enqueue_scripts', [$this, 'adminScripts']);
		
		add_action('rest_api_init', function() {
			require_once(__DIR__.'/includes/stats-controller.php');
			StatsController::register();
		});

		if (is_admin()) {
			require_once(__DIR__.'/includes/analytics.php');
			new Analytics();
		}
		
		$lastSeenVersion = get_option('phplugins_carts_version', '1.0.5');
		if ($lastSeenVersion !== self::VERSION) {
			add_action('init', [$this, 'handleVersionUpgrade'], 1);
		}
		
		// Improve compatibility with woocommerce-paypal-payments plugin
		add_action('wc_ajax_ppc-simulate-cart', [$this, 'disableCartProcessing'], 1);
	}
	
	public function handleVersionUpgrade() {
		global $wpdb;
		$allTables = $wpdb->get_col('SHOW TABLES');
		if (in_array($wpdb->prefix.'phplugins_carts', $allTables)) {
			require_once(__DIR__.'/includes/setup.php');
			Setup::upgradeDatabaseTables( get_option('phplugins_carts_version', '1.0.5') );
			update_option('phplugins_carts_version', self::VERSION);
		}
	}
	
	public function addCartIdDisplayClass($classes) {
		$classes[] = 'phplugins-live-carts-show-id';
		return $classes;
	}
	
	public function outputCartId() {
		echo( $this->getCartIdFrontendDisplay() );
	}
	
	public function getCartIdFrontendDisplay($before='') {
		return $before.apply_filters('phplugins_live_carts_frontend_cart_id_html',
			'<div class="phplugins-live-carts-cart-id">
				<span>'.esc_html__('Cart ID:', 'live-carts-for-woocommerce').'</span>
				<span>'.self::formatCartId($this->currentCartId).'</span>
			</div>', $this->currentCartId);
	}
	
	public static function formatCartId($cartId) {
		// Formatted cart IDs MUST be HTML-safe!
		return str_pad( strtoupper(dechex($cartId)), 8, '0', STR_PAD_LEFT );
	}
	
	public static function unformatCartId($cartId) {
		return hexdec($cartId);
	}
	
	public function disableCartProcessing() {
		remove_action('woocommerce_cart_loaded_from_session', [$this, 'onCartLoaded']);
		remove_action('woocommerce_after_calculate_totals', [$this, 'updateCartContents']);
	}
	
	public function saveSettings() {
		global $wpdb;
		
		if (current_user_can(self::ADMIN_CAPABILITY)) {
			check_admin_referer('phplugins-carts-settings-save', 'phplugins_carts_settings_save');
			
			if (empty($_POST['phplugins_carts_debug'])) {
				$log = get_option('phplugins_carts_debug');
				if ($log) {
					@unlink(__DIR__.'/debug-'.$log.'.txt');
				}
				delete_option('phplugins_carts_debug');
			} else {
				update_option('phplugins_carts_debug', sanitize_key(wp_generate_password(20, false)), false);
			}
			
			if (empty($_POST['phplugins_carts_no_ip'])) {
				delete_option('phplugins_carts_no_ip');
			} else {
				$wpdb->query('UPDATE '.$wpdb->prefix.'phplugins_carts SET ip_address=""');
				update_option('phplugins_carts_no_ip', 1);
			}
			
			if (empty($_POST['phplugins_carts_no_url'])) {
				delete_option('phplugins_carts_no_url');
			} else {
				$wpdb->query('UPDATE '.$wpdb->prefix.'phplugins_carts SET last_url=""');
				update_option('phplugins_carts_no_url', 1);
			}
			
			if (empty($_POST['phplugins_carts_show_id'])) {
				delete_option('phplugins_carts_show_id');
			} else {
				update_option('phplugins_carts_show_id', 1);
			}
		}
	}
	
	public function adminScripts() {
		$currentScreen = get_current_screen();
		if ($currentScreen && ($currentScreen->id == 'woocommerce_page_live-carts-for-woocommerce' || $currentScreen->id == 'woocommerce_page_wc-admin')) {
			wp_enqueue_script('phplugins-live-carts-admin', plugins_url('assets/js/admin.js', __FILE__), ['wp-hooks', 'wp-i18n', 'react'], self::VERSION);
			wp_set_script_translations('phplugins-live-carts-admin', 'live-carts-for-woocommerce');
			wp_enqueue_style('phplugins-live-carts-admin', plugins_url('assets/css/admin.css', __FILE__), null, self::VERSION);
		}
	}
	
	public function frontendScripts() {
		wp_enqueue_style('phplugins-live-carts', plugins_url('assets/css/frontend.css', __FILE__), null, self::VERSION);
	}
	
	public function debugLog($message, $file, $line) {
		try {
			$log = get_option('phplugins_carts_debug');
			if ($log && $message) {
				file_put_contents(__DIR__.'/debug-'.$log.'.txt', wp_date('c')."\t".basename($file).':'.$line."\t".$message."\n", FILE_APPEND);
			}
		} catch (\Exception $ex) {
		} catch (\Error $err) { }
	}

	public function onOrderStatusChanged($orderId, $from, $to, $order) {
		global $wpdb;
		try {
			if ($this->currentCart && $from == 'pending' && in_array($to, array_merge(wc_get_is_paid_statuses(), ['on-hold'])) && $order->get_cart_hash() == $this->currentCart->get_cart_hash()) {
				$wpdb->update(
					$wpdb->prefix.'phplugins_carts',
					[
						'status' => 'converted',
						'order_id' => $order->get_id()
					],
					[ 'cart_id' => $this->currentCartId ],
					[
						'status' => '%s',
						'order_id' => '%d'
					],
					[ 'cart_id' => '%d' ]
				);
				do_action('phplugins_live_carts_cart_converted', $this->currentCartId);
			}
		
		} catch (\Exception $ex) {
			$this->debugLog($ex->getMessage(), __FILE__, __LINE__);
		} catch (\Error $err) {
			$this->debugLog($err->getMessage(), __FILE__, __LINE__);
		}
	}
	public function filterPerPageScreenOption($f, $screenOption, $perPage) {
		return min(absint($perPage), 999);
	}

	public function getTimestampFormat() {
		return get_option('date_format').' '.get_option('time_format');
	}

	public function onCurrentScreen($screen) {
		if ($screen->id == 'woocommerce_page_live-carts-for-woocommerce' && current_user_can(self::ADMIN_CAPABILITY)) {
			$this->hourlyScheduledTasks();

			add_screen_option('per_page');
			require_once(__DIR__.'/includes/admin-page.php');
			new AdminPage();
		}
	}
	
	public function onAdminMenu() {
		add_submenu_page('woocommerce', __('Live Carts for WooCommerce', 'live-carts-for-woocommerce'), __('Live Carts', 'live-carts-for-woocommerce'), self::ADMIN_CAPABILITY, 'live-carts-for-woocommerce', '__return_empty_string');
	}

	public function getCartStatuses() {
		return [
			'active' => __('Active', 'live-carts-for-woocommerce'),
			'converted' => __('Converted', 'live-carts-for-woocommerce'),
			'abandoned' => __('Abandoned', 'live-carts-for-woocommerce'),
		];
	}

	public function onCartLoaded($cart) {
		try {
			$this->currentCart = $cart;
			$this->currentCartId = WC()->session->get( 'phplugins_cart_id', null );
			if (empty($this->currentCartId) || !$this->validateCurrentCart()) {
				if ($cart->is_empty()) {
					return;
				}
				try {
					$this->createNewCart($cart);
					$this->updateCartContents();
				} catch (\Exception $ex) {
					$this->debugLog($ex->getMessage(), __FILE__, __LINE__);
					return;
				}
			}
			
			add_action('wp_footer', [$this, 'cartSeen']);
		
			if ( get_option('phplugins_carts_show_id') ) {
				add_action('woocommerce_after_cart_table', [$this, 'outputCartId']);
				add_filter('render_block_woocommerce/cart', [$this, 'getCartIdFrontendDisplay']);
				add_filter('render_block_woocommerce/mini-cart-contents', [$this, 'getCartIdFrontendDisplay']);
				add_action('body_class', [$this, 'addCartIdDisplayClass']);
				add_action('wp_enqueue_scripts', [$this, 'frontendScripts']);
			}
		} catch (\Exception $ex) {
			$this->debugLog($ex->getMessage(), __FILE__, __LINE__);
		} catch (\Error $err) {
			$this->debugLog($err->getMessage(), __FILE__, __LINE__);
		}
	}

	public function validateCurrentCart() {
		global $wpdb;
		$result = $wpdb->get_row( $wpdb->prepare('SELECT status FROM '.$wpdb->prefix.'phplugins_carts WHERE cart_id=%d', $this->currentCartId) );
		$valid = $result && $result->status != 'converted';
		if (!$valid) {
			unset($this->currentCartId);
		}
		return $valid;
	}

	public function createNewCart($cart) {
		global $wpdb;
		
		do {
			$cartId = random_int(1, hexdec('FFFFFFFF'));
			$foundCartId = $wpdb->get_col('SELECT cart_id FROM '.$wpdb->prefix.'phplugins_carts WHERE cart_id='.((int) $cartId));
		} while ($foundCartId);

		$result = $wpdb->insert(
			$wpdb->prefix.'phplugins_carts',
			[
				'cart_id' => $cartId,
				'user_id' => get_current_user_id(),
				'created' => current_time('mysql', true),
				'ip_address' => get_option('phplugins_carts_no_ip') ? '' : apply_filters('phplugins_live_carts_cart_ip_address', sanitize_text_field($_SERVER['REMOTE_ADDR'])),
				'status' => 'active'
			],
			[
				'cart_id' => '%d',
				'user_id' => '%d',
				'created' => '%s',
				'ip_address' => '%s',
				'status' => '%s'
			]
		);
		
		if (!$result) {
			throw new \Exception( __('Cart could not be created', 'live-carts-for-woocommerce') );
		}
		
		$this->currentCartId = $cartId;
		WC()->session->set( 'phplugins_cart_id', $this->currentCartId );
		
		do_action('phplugins_live_carts_cart_created', $cartId);
	}

	public function cartSeen() {
		global $wpdb;
		
		try {
			$updateValues = [
				'last_seen' => current_time('mysql', true),
				'status' => 'active',
				'archived' => 0
			];
		
			$updateFormats = [
				'last_seen' => '%s',
				'status' => '%s',
				'archived' => '%d'
			];
			
			if (!get_option('phplugins_carts_no_url') && !is_404() && !is_admin()) {
				$updateValues['last_url'] = add_query_arg([]);
				$updateFormats['last_url'] = '%s';
			}
			
			$result = $wpdb->update(
				$wpdb->prefix.'phplugins_carts',
				$updateValues,
				[ 'cart_id' => $this->currentCartId ],
				$updateFormats,
				[ 'cart_id' => '%d' ]
			);
			
			do_action('phplugins_live_carts_cart_seen', $this->currentCartId);

			if ($result === false) {
				throw new \Exception( __('Cart seen could not be updated', 'live-carts-for-woocommerce') );
			}
		} catch (\Exception $ex) {
			$this->debugLog($ex->getMessage(), __FILE__, __LINE__);
		}
	}
	
	public function updateCartContents() {
		global $wpdb;
		
		try {
			if (empty($this->currentCartId)) {
				try {
					$this->createNewCart($this->currentCart);
					$this->cartSeen();
				} catch (\Exception $ex) {
					$this->debugLog($ex->getMessage(), __FILE__, __LINE__);
					return;
				}
			}
			
			$result = $wpdb->update(
				$wpdb->prefix.'phplugins_carts',
				[
					'value' => $this->currentCart->get_total('edit') - $this->currentCart->get_total_tax(),
					'coupon' => implode(', ', $this->currentCart->get_applied_coupons())
				],
				[ 'cart_id' => $this->currentCartId ],
				[
					'value' => '%f',
					'coupon' => '%s'
				],
				[ 'cart_id' => '%d' ]
			);
			
			if ($result === false) {
				throw new \Exception( __('Cart could not be updated', 'live-carts-for-woocommerce') );
			}

			$currentContents = wp_json_encode($this->currentCart->get_cart_contents());
			$lastContents = $wpdb->get_var( $wpdb->prepare('SELECT contents FROM '.$wpdb->prefix.'phplugins_cart_contents WHERE cart_id=%d ORDER BY ts DESC LIMIT 1', $this->currentCartId) );
			
			if ($lastContents !== $currentContents) {
				$result = $wpdb->insert(
					$wpdb->prefix.'phplugins_cart_contents',
					[
						'cart_id' => $this->currentCartId,
						'contents' => $currentContents,
						'ts' => current_time('mysql', true)
					],
					[
						'cart_id' => '%d',
						'contents' => '%s',
						'ts' => '%s'
					]
				);

				if ($result === false) {
					throw new \Exception( __('Cart contents could not be updated', 'live-carts-for-woocommerce') );
				}
				
				do_action('phplugins_live_carts_cart_contents_updated', $this->currentCartId);
			}
		} catch (\Exception $ex) {
			$this->debugLog($ex->getMessage(), __FILE__, __LINE__);
		} catch (\Error $err) {
			$this->debugLog($err->getMessage(), __FILE__, __LINE__);
		}
	}

	public function hourlyScheduledTasks() {
		global $wpdb;

		// Abandoned carts
		$abandonedCarts = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT cart_id
				FROM '.$wpdb->prefix.'phplugins_carts
				WHERE status="active"
					AND last_seen < %s',
				wp_date('Y-m-d H:i:s', time() - apply_filters('phplugins_live_carts_abandon_time', self::CART_ABANDON_TIME), new \DateTimeZone('UTC'))
			)
		);
		foreach (array_chunk($abandonedCarts, 20) as $abandonedCartsChunk) {
			$wpdb->query(
				call_user_func_array(
					[$wpdb, 'prepare'],
					array_merge(
						[
							'UPDATE '.$wpdb->prefix.'phplugins_carts
							SET status="abandoned"
							WHERE cart_id IN ('.implode(',', array_fill(0, count($abandonedCartsChunk), '%d')).')'
						],
						$abandonedCartsChunk
					)
				)
			);
			if (has_action('phplugins_live_carts_cart_abandoned')) {
				array_walk($abandonedCartsChunk, function($cartId) {
					do_action('phplugins_live_carts_cart_abandoned', $cartId);
				});
			}
		}

		// Archived carts
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE '.$wpdb->prefix.'phplugins_carts
				SET archived=1
				WHERE last_seen < %s',
				wp_date('Y-m-d H:i:s', time() - apply_filters('phplugins_live_carts_archive_time', (self::CART_ARCHIVE_DAYS * 86400)), new \DateTimeZone('UTC'))
			)
		);

		// Delete archived/orphaned cart contents
		$wpdb->query(
			'DELETE FROM '.$wpdb->prefix.'phplugins_cart_contents
			WHERE NOT EXISTS(
				SELECT 1 FROM '.$wpdb->prefix.'phplugins_carts c
				WHERE c.cart_id='.$wpdb->prefix.'phplugins_cart_contents.cart_id AND NOT c.archived
			)'
		);
	}

	public static function onPluginActivate() {
		global $wpdb;
		$allTables = $wpdb->get_col('SHOW TABLES');
		if (!in_array($wpdb->prefix.'phplugins_carts', $allTables)) {
			require_once(__DIR__.'/includes/setup.php');
			Setup::createDatabaseTables();
			Setup::upgradeDatabaseTables('1.0.0');
			update_option('phplugins_carts_version', self::VERSION);
		}

		wp_schedule_event(time() + 3600, 'hourly', 'phplugins_livecarts_hourly');
	}

	public static function onPluginDeactivate() {
		$nextHourlyEventTime = wp_next_scheduled('phplugins_livecarts_hourly');
		if ($nextHourlyEventTime) {
			wp_unschedule_event($nextHourlyEventTime, 'phplugins_livecarts_hourly');
		}
	}
}

LiveCarts::instance();

register_activation_hook(__FILE__, ['Penthouse\LiveCarts\LiveCarts', 'onPluginActivate']);
register_deactivation_hook(__FILE__, ['Penthouse\LiveCarts\LiveCarts', 'onPluginDeactivate']);