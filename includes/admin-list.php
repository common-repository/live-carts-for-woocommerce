<?php
namespace Penthouse\LiveCarts;

defined('ABSPATH') || exit;

class CartsListTable extends \WP_List_Table {

	private $statuses;

	public function __construct() {
		parent::__construct();
		$this->statuses = LiveCarts::instance()->getCartStatuses();
	}

	public function get_views() {
		$status = empty($_REQUEST['status']) ? 'all' : sanitize_key($_REQUEST['status']);
		$views = [
			'all' => '<a href="'.esc_url(remove_query_arg('status')).'"'.($status == 'all' ? ' class="current"' : '').'>'.esc_html__('All', 'live-carts-for-woocommerce').'</a>'
		];
		foreach ($this->statuses as $statusId => $statusDisplay) {
			$views[$statusId] = '<a href="'.esc_url(add_query_arg('status', rawurlencode($statusId))).'"'.($status == $statusId ? ' class="current"' : '').'>'.esc_html($statusDisplay).'</a>';
		}
		return $views;
	}
	
	public function get_columns() {
		return [
			'cart_id' => esc_html__('Cart ID', 'live-carts-for-woocommerce'),
			'status' => esc_html__('Status', 'live-carts-for-woocommerce'),
			'user' => esc_html__('User', 'live-carts-for-woocommerce'),
			'last_seen' => esc_html__('Last Seen', 'live-carts-for-woocommerce'),
			'coupon' => esc_html__('Coupon(s)', 'live-carts-for-woocommerce'),
			'value' => esc_html__('Cart Value', 'live-carts-for-woocommerce')
		];
	}

	public function prepare_items() {
		global $wpdb;
		$perPage = $this->get_items_per_page('woocommerce_page_live-carts-for-woocommerce_per_page');
		$filterStatus = isset($_REQUEST['status']) && isset($this->statuses[ $_REQUEST['status'] ]);
		$sql = 'SELECT cart_id, status, user_id, last_seen, coupon, last_url, value
				FROM '.$wpdb->prefix.'phplugins_carts
				WHERE archived=0'
				.($filterStatus ? ' AND status=%s' : '')
				.(empty($_REQUEST['s']) ? '' : ' AND cart_id=%s').'
				ORDER BY last_seen DESC
				LIMIT %d,%d';
		
		$params = $filterStatus ? [ sanitize_key($_REQUEST['status']) ] : [];
		if (!empty($_REQUEST['s'])) {
			$params[] = LiveCarts::unformatCartId(sanitize_text_field($_REQUEST['s']));
		}
		$params[] = ($this->get_pagenum() - 1) * $perPage;
		$params[] = $perPage;

		array_unshift($params, $sql);
	
		$query = call_user_func_array([$wpdb, 'prepare'], $params);
		$this->items = $wpdb->get_results($query, ARRAY_A);
	
		$count = $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->prefix.'phplugins_carts WHERE archived=0');
		$this->set_pagination_args([
			'total_items' => (int) $count,
			'per_page' => $perPage
		]);
	}

	protected function column_cart_id($row) {
		return '<a href="'.esc_url(add_query_arg('cart_id', (int) $row['cart_id'])).'">'.LiveCarts::formatCartId($row['cart_id']).'</a>';
	}

	protected function _column_status($row, $classNames, $dataAttrs, $isPrimary) {
		return '<td class="'.esc_attr($classNames.' phplugins-live-carts-'.$row['status']).'" '
					// values in $dataAttrs were already escaped
					.$dataAttrs
				.'>'
					.esc_html(
						isset($this->statuses[$row['status']])
							? $this->statuses[$row['status']]
							: $row['status']
					)
				.'</td>';
	}
	
	protected function column_last_seen($row) {
		return $row['last_url']
					? sprintf(
						// translators: first %s is a timestamp, second %s is a URL
						esc_html__('%s at %s', 'live-carts-for-woocommerce'),
						esc_html(get_date_from_gmt($row['last_seen'], LiveCarts::instance()->getTimestampFormat())),
						'<a href="'.esc_url($row['last_url']).'" target="_blank">'.esc_url($row['last_url']).'</a>'
					)
					: esc_html(get_date_from_gmt($row['last_seen'], LiveCarts::instance()->getTimestampFormat()));
	}
	
	protected function column_coupon($row) {
		return esc_html($row['coupon']);
	}
	
	protected function column_user($row) {
		if ($row['user_id']) {
			$user = get_userdata($row['user_id']);
			if ($user) {
				$userDisplayName = trim($user->first_name.' '.$user->last_name);
				if (empty($userDisplayName)) {
					$userDisplayName = $user->user_login;
				}
			}
		}
		return empty($user) ? esc_html_e('Guest/Unknown', 'live-carts-for-woocommerce') : '<a href="'.esc_url(get_edit_profile_url($user->ID)).'" target="_blank">'.esc_html($userDisplayName).'</a>';
	}

	protected function column_value($row) {
		return wc_price((float) $row['value']);
	}

}