<?php
namespace Penthouse\LiveCarts;

defined('ABSPATH') || exit;

class StatsController extends \Automattic\WooCommerce\Admin\API\Reports\GenericStatsController {
		
	protected $rest_base = 'reports/phplugins-carts/stats';

	public static function register() {
		add_filter('woocommerce_admin_reports', function($reports) {
			$reports[] = [
				'slug' => 'phplugins-carts/stats',
				'description' => __('Stats about carts.', 'phplugins_live_carts')
			];
			return $reports;
		});
		
		add_filter('woocommerce_admin_rest_controllers', function($controllers) {
			$controllers[] = self::class;
			return $controllers;
		});
	}

	public function get_items($request) {
		global $wpdb;

		$start = get_gmt_from_date($request['after']);
		$end = get_gmt_from_date($request['before']);

		$counts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS count
				FROM '.$wpdb->prefix.'phplugins_carts
				WHERE created BETWEEN %s AND %s
				GROUP BY status',
				$start,
				$end
			)
		);

		$countsByStatus = array_column($counts, 'count', 'status');

		$countsTotal = array_sum($countsByStatus);

		$totals = [
			'convert_rate' => isset($countsByStatus['converted']) ? round($countsByStatus['converted'] / $countsTotal, 6) : 0,
			'abandon_rate' => isset($countsByStatus['abandoned']) ? round($countsByStatus['abandoned'] / $countsTotal, 6) : 0
		];

		$totals['cart_value'] = round((double) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT AVG(value)
				FROM '.$wpdb->prefix.'phplugins_carts
				WHERE created BETWEEN %s AND %s',
				$start,
				$end
			)
		), 6);

		return ['totals' => $totals];
	}
	
	protected function get_item_properties_schema() {
		return [
			'convert_rate' => [
				'description' => __('Cart conversion rate', 'phplugins_live_carts'),
				'type' => 'number',
				'context' => ['view', 'edit'],
				'readonly' => true
			],
			'abandon_rate' => [
				'description' => __('Cart abandonment rate', 'phplugins_live_carts'),
				'type' => 'number',
				'context' => ['view', 'edit'],
				'readonly' => true
			],
			'cart_value' => [
				'description' => __('Average cart value', 'phplugins_live_carts'),
				'type' => 'number',
				'context' => ['view', 'edit'],
				'readonly' => true,
				'format' => 'currency'
			],
		];
	}
}