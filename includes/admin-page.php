<?php
namespace Penthouse\LiveCarts;

defined('ABSPATH') || exit;

class AdminPage {

	protected $table, $cartData;

	function __construct() {
		add_action(get_plugin_page_hookname('live-carts-for-woocommerce', 'woocommerce'), [$this, isset($_GET['cart_id']) ? 'cartPage' : (isset($_GET['settings']) ? 'settingsPage' : 'listPage')]);
		
		if (isset($_GET['cart_id'])) {
			global $wpdb;
			$this->cartData = $wpdb->get_row( $wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'phplugins_carts LEFT JOIN '.$wpdb->prefix.'phplugins_cart_contents cc USING (cart_id) WHERE cart_id=%d ORDER BY cc.ts DESC LIMIT 1', (int) $_GET['cart_id']) );
		} else {
			require_once(__DIR__.'/admin-list.php');
			$this->table = new CartsListTable();
			$this->table->prepare_items();
		}
	}

	public function listPage() {
?>
<div class="wrap">
	<h1><?php esc_html_e('Live Carts', 'live-carts-for-woocommerce'); ?></h1>
	<form method="get">
		<input type="hidden" name="page" value="live-carts-for-woocommerce">
		<?php $this->table->views(); $this->table->search_box(esc_html__('Search Cart ID', 'live-carts-for-woocommerce'), 'phplugins-live-carts-search'); $this->table->display(); ?>
	</form>
	<p>
		<a class="button button-seconary" href="?page=live-carts-for-woocommerce&amp;settings=1"><?php esc_html_e('Live Carts Settings', 'live-carts-for-woocommerce'); ?></a>
		<a class="button button-seconary" href="?page=wc-admin&amp;path=/analytics/phplugins-carts"><?php esc_html_e('Analytics', 'live-carts-for-woocommerce'); ?></a>
		<a class="button button-seconary" href="https://wordpress.org/support/plugin/live-carts-for-woocommerce/" target="_blank"><?php esc_html_e('Support (External Link)', 'live-carts-for-woocommerce'); ?></a>
		<a class="button button-seconary" href="https://wordpress.org/support/plugin/live-carts-for-woocommerce/" target="_blank"><?php esc_html_e('Make a Feature Request (External Link)', 'live-carts-for-woocommerce'); ?></a>
		<a class="button button-seconary" href="https://wordpress.org/support/plugin/live-carts-for-woocommerce/" target="_blank"><?php esc_html_e('Leave a Review (External Link)', 'live-carts-for-woocommerce'); ?></a>
	</p>
	<p class="instuctions"><?php esc_html_e('Carts are removed from this page approximately 30 days after they were last seen. Some cart data is retained in the database after this time.', 'live-carts-for-woocommerce'); ?></p>
</div>
<?php
	}

	public function settingsPage() {
?>
<div id="phplugins-live-carts-settings" class="wrap">
	<h1><?php esc_html_e('Live Carts Settings', 'live-carts-for-woocommerce'); ?></h1>
	<form action="" method="post">
		<?php wp_nonce_field('phplugins-carts-settings-save', 'phplugins_carts_settings_save'); ?>
		
		<h2><?php esc_html_e('Frontend', 'live-carts-for-woocommerce'); ?></h2>
		<p>
			<label>
				<input type="checkbox" name="phplugins_carts_show_id" value="1"<?php checked(get_option('phplugins_carts_show_id')); ?>>
				<?php esc_html_e('Show cart ID on the frontend', 'live-carts-for-woocommerce'); ?>
			</label>
			<p class="description"><?php esc_html_e('Note: It may be necessary to refresh the page after adding the first item to cart to see the cart ID.', 'live-carts-for-woocommerce'); ?></p>
		</p>
		
		<h2><?php esc_html_e('Privacy', 'live-carts-for-woocommerce'); ?></h2>
		<p>
			<label>
				<input type="checkbox" name="phplugins_carts_no_ip" value="1"<?php checked(get_option('phplugins_carts_no_ip')); ?>>
				<?php esc_html_e('Don\'t collect site visitors\' IP addresses', 'live-carts-for-woocommerce'); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="phplugins_carts_no_url" value="1"<?php checked(get_option('phplugins_carts_no_url')); ?>>
				<?php esc_html_e('Don\'t collect visited URLs', 'live-carts-for-woocommerce'); ?>
			</label>
		</p>
		
		<h2><?php esc_html_e('Advanced', 'live-carts-for-woocommerce'); ?></h2>
		<p>
			<label>
				<input type="checkbox" name="phplugins_carts_debug" value="1"<?php checked(get_option('phplugins_carts_debug') !== false); ?>>
				<?php esc_html_e('Enable debug mode', 'live-carts-for-woocommerce'); ?>
			</label>
		</p>
		
		<button class="button-primary"><?php esc_html_e('Save Settings', 'live-carts-for-woocommerce'); ?></button>
	</form>
</div>
<?php
	}

	public function cartPage() {
		$tsFormat = LiveCarts::instance()->getTimestampFormat();
		$statuses = LiveCarts::instance()->getCartStatuses();
		
		if (!empty($this->cartData)) {
			if (!empty($this->cartData->user_id)) {
				$user = get_userdata($this->cartData->user_id);
				if ($user) {
					$userDisplayName = trim($user->first_name.' '.$user->last_name);
					if (empty($userDisplayName)) {
						$userDisplayName = $user->user_login;
					}
				}
			}
			if (!empty($this->cartData->contents)) {
				$contents = json_decode($this->cartData->contents);
			}
			if (!empty($this->cartData->order_id)) {
				$order = wc_get_order($this->cartData->order_id);
			}
		}
?>
		<div id="phplugins-live-carts-details" class="wrap">
			<?php if (empty($this->cartData)) { ?>
			<h1><?php esc_html_e('Cart Not Found', 'live-carts-for-woocommerce'); ?></h1>
			<?php } else { ?>
			<h1><?php printf(esc_html__('Cart #%d', 'live-carts-for-woocommerce'), (int) $_GET['cart_id']); ?></h1>
			<div id="phplugins-live-carts-details-card">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Cart ID:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php echo(LiveCarts::formatCartId($this->cartData->cart_id)); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Created at:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php echo(esc_html(get_date_from_gmt($this->cartData->created, $tsFormat))); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Last seen:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php
									echo(
										$this->cartData->last_url
											? sprintf(
												// translators: first %s is a timestamp, second %s is a URL
												esc_html__('%s at %s', 'live-carts-for-woocommerce'),
												esc_html(get_date_from_gmt($this->cartData->last_seen, $tsFormat)),
												'<a href="'.esc_url($this->cartData->last_url).'" target="_blank">'.esc_url($this->cartData->last_url).'</a>'
											)
											: esc_html(get_date_from_gmt($this->cartData->last_seen, $tsFormat))
									);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Status:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td class="phplugins-live-carts-status phplugins-live-carts-<?php echo(esc_attr($this->cartData->status)); ?>">
								<?php echo( esc_html(isset($statuses[$this->cartData->status]) ? $statuses[$this->cartData->status] : $this->cartData->status) ); ?>
							</td>
						</tr>
						<?php if (!empty($order)) { ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Order:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<a href="<?php echo(esc_url($order->get_edit_order_url())); ?>" target="_blank"><?php echo( (int) $order->get_id() ); ?></a>
							</td>
						</tr>
						<?php } ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('User:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php echo( empty($user) ? esc_html_e('Guest/Unknown', 'live-carts-for-woocommerce') : '<a href="'.esc_url(get_edit_profile_url($user->ID)).'" target="_blank">'.esc_html($userDisplayName).'</a>' ); ?>
							</td>
						</tr>
						<?php if ($this->cartData->ip_address) { ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('IP address:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php echo( esc_html($this->cartData->ip_address) ); ?>
							</td>
						</tr>
						<?php } ?>
						<?php if ($this->cartData->coupon) { ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Cart coupon(s):', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php echo( esc_html($this->cartData->coupon) ); ?>
							</td>
						</tr>
						<?php } ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Cart value:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<?php echo( wc_price($this->cartData->value) ); ?>
							</td>
						</tr>
						
						<?php do_action('phplugins_live_carts_admin_cart_details', $this->cartData->cart_id); ?>
						
						<?php if (isset($contents)) { ?>
						<tr>
							<th scope="row">
								<label><?php esc_html_e('Cart contents:', 'live-carts-for-woocommerce'); ?></label>
							</th>
							<td>
								<table>
									<thead>
										<tr>
											<th><?php esc_html_e('Item Name', 'live-carts-for-woocommerce'); ?></th>
											<th><?php esc_html_e('Quantity', 'live-carts-for-woocommerce'); ?></th>
											<th><?php esc_html_e('Line Total', 'live-carts-for-woocommerce'); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										foreach ($contents as $item) {
											if (empty($item->product_id)) {
												unset($product);
											} else {
												$product = wc_get_product(empty($item->variation_id) ? $item->product_id : $item->variation_id);
											}
										?>
										<tr>
											<td>
												<?php if (empty($product)) { ?>
													<?php esc_html_e('Unknown item', 'live-carts-for-woocommerce'); ?>
												<?php } else { ?>
													<a href="<?php echo(esc_url(get_permalink())); ?>" target="_blank">
														<?php echo(esc_html($product->get_title())); ?>
													</a>
												<?php } ?>
											</td>
											<td><?php if (isset($item->quantity)) echo((double) $item->quantity); ?></td>
											<td><?php if (isset($item->line_total)) echo(wc_price($item->line_total)); ?></td>
										</tr>
										<?php } ?>
									</tbody>
								</table>
							</td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
			<?php } ?>
			<a href="<?php echo(esc_url(remove_query_arg('cart_id'))); ?>"><?php esc_html_e('Back to carts list', 'live-carts-for-woocommerce'); ?></a>
		</div>
<?php
	}
}