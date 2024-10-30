<?php
namespace Penthouse\LiveCarts;

defined('ABSPATH') || exit;

class Setup {
	
	static function createDatabaseTables() {
		global $wpdb;
		$sql = [
			'CREATE TABLE '.$wpdb->prefix.'phplugins_carts (
			  cart_id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  order_id bigint(20) NOT NULL,
			  user_id bigint(20) NOT NULL,
			  created datetime NOT NULL,
			  last_seen datetime NOT NULL,
			  status varchar(24) NOT NULL,
			  ip_address varchar(39) NOT NULL,
			  value double(12,2) NOT NULL,
			  archived tinyint(1) NOT NULL
			)',
			'CREATE TABLE '.$wpdb->prefix.'phplugins_cart_contents (
			  cart_id bigint(20) NOT NULL KEY,
			  ts datetime NOT NULL,
			  contents varchar(2048) NOT NULL
			)',
		];
		
		array_map([$wpdb, 'query'], $sql);
	}
	
	static function upgradeDatabaseTables($fromVersion) {
		global $wpdb;
		$sql = [
			'1.0.5' => [
				'ALTER TABLE '.$wpdb->prefix.'phplugins_carts ADD coupon varchar(128) NOT NULL AFTER value, ADD last_url varchar(4096) NOT NULL AFTER ip_address'
			]
		];
		
		foreach ($sql as $sqlFromVersion => $versionSql) {
			if (version_compare($fromVersion, $sqlFromVersion) == 1) {
				break;
			}
			array_map([$wpdb, 'query'], $versionSql);
		}
	}

}