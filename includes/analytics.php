<?php
namespace Penthouse\LiveCarts;

defined('ABSPATH') || exit;

class Analytics {
	
	public function __construct() {
		add_filter('woocommerce_analytics_report_menu_items', [$this, 'registerMenuItem']);
	}

	public function registerMenuItem($menuItems) {
		$menuItems[] = [
			'id' => 'phplugins-carts-analytics',
			'title' => __('Carts', 'phplugins_live_carts'),
			'parent' => 'woocommerce-analytics',
			'path' => '/analytics/phplugins-carts',
			'nav_args' => [
				'parent' => 'woocommmerce-analytics'
			]
		];
		return $menuItems;
	}

}