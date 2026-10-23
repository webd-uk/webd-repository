<?php
/** Original global entry points retained for callers and WordPress hook identity. */

defined( 'ABSPATH' ) || exit;

function webd_recent_contributions($atts, $content = '') {
	return \Webd\Repository\Site\SiteFeatures::webd_recent_contributions($atts, $content);
}

function webd_wp_footer() {
	return \Webd\Repository\Site\SiteFeatures::webd_wp_footer();
}

function webd_has_own_thumbnail_id() {
	return \Webd\Repository\Site\SiteFeatures::webd_has_own_thumbnail_id();
}

function webd_post_thumbnail_id($thumbnail_id, $post) {
	return \Webd\Repository\Site\SiteFeatures::webd_post_thumbnail_id($thumbnail_id, $post);
}

function webd_wp_head() {
	return \Webd\Repository\Site\SiteFeatures::webd_wp_head();
}

function webd_robots_txt($output) {
	return \Webd\Repository\Site\SiteFeatures::webd_robots_txt($output);
}

function webd_render_block($block_content, $block, $instance) {
	return \Webd\Repository\Site\SiteFeatures::webd_render_block($block_content, $block, $instance);
}

function webd_has_comments() {
	return \Webd\Repository\Site\SiteFeatures::webd_has_comments();
}

function webd_category_description($description, $category) {
	return \Webd\Repository\Site\SiteFeatures::webd_category_description($description, $category);
}

function webd_plugin_version() {
	return \Webd\Repository\API\PluginRepository::webd_plugin_version();
}

function webd_plugin_information() {
	return \Webd\Repository\API\PluginRepository::webd_plugin_information();
}

function webd_update_stats($plugin_slug) {
	return \Webd\Repository\API\PluginRepository::webd_update_stats($plugin_slug);
}

function webd_create_plugin_updates_database() {
	return \Webd\Repository\API\PluginRepository::webd_create_plugin_updates_database();
}

function webd_create_plugin_stats_database() {
	return \Webd\Repository\API\PluginRepository::webd_create_plugin_stats_database();
}

function webd_package_link($atts, $content = false) {
	return \Webd\Repository\API\PluginRepository::webd_package_link($atts, $content);
}

function webd_is_local($client_host) {
	return \Webd\Repository\API\PluginRepository::webd_is_local($client_host);
}

function webd_enqueue_dashicons() {
	return \Webd\Repository\API\PluginRepository::webd_enqueue_dashicons();
}

function webd_show_reviews_shortcode($atts, $content) {
	return \Webd\Repository\Reviews\Reviews::webd_show_reviews_shortcode($atts, $content);
}

function disable_wc_endpoint_v1() {
	return \Webd\Repository\Commerce\WooCommerceIntegration::disable_wc_endpoint_v1();
}

function disable_wc_endpoint() {
	return \Webd\Repository\Commerce\WooCommerceIntegration::disable_wc_endpoint();
}

function webd_wp() {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_wp();
}

function webd_woocommerce_single_product_image_thumbnail_html($html) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_woocommerce_single_product_image_thumbnail_html($html);
}

function webd_after_setup_theme() {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_after_setup_theme();
}

function webd_woocommerce_product_query($q) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_woocommerce_product_query($q);
}

function webd_get_url_action() {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_get_url_action();
}

function webd_activate_plugin($order_id) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_activate_plugin($order_id);
}

function webd_override_checkout_fields($fields) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_override_checkout_fields($fields);
}

function webd_auto_complete_order($order_id) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_auto_complete_order($order_id);
}

function webd_prevent_duplicate_orders($passed, $product_id, $quantity, $variation_id = false, $variations = false) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_prevent_duplicate_orders($passed, $product_id, $quantity, $variation_id, $variations);
}

function webd_get_basket_approved_url($url) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_get_basket_approved_url($url);
}

function webd_get_licensed_url($url) {
	return \Webd\Repository\Commerce\WooCommerceIntegration::webd_get_licensed_url($url);
}
