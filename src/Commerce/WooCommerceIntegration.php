<?php
/** Migrated feature methods; behavior is retained during the structural refactor. */

namespace Webd\Repository\Commerce;

defined( 'ABSPATH' ) || exit;

final class WooCommerceIntegration {
	public static function register_hooks() {
		add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
		add_action('woocommerce_product_query', 'webd_woocommerce_product_query');
		add_action('woocommerce_after_single_product', 'webd_get_url_action', 5);
		add_action('woocommerce_order_status_completed', 'webd_activate_plugin');
		add_filter('woocommerce_checkout_fields' , 'webd_override_checkout_fields');
		add_action('woocommerce_order_status_processing', 'webd_auto_complete_order');
		add_filter('woocommerce_add_to_cart_validation', 'webd_prevent_duplicate_orders', 10, 5);
		add_action('wp', 'webd_wp');
		add_filter('woocommerce_single_product_image_thumbnail_html', 'webd_woocommerce_single_product_image_thumbnail_html' );
	}

public static function disable_wc_endpoint_v1() {
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, '/wp-json/wc/store/v1/checkout') !== false) {
//        wp_redirect(home_url('/404.php'));
//        exit;
// Looks like this was last used for a real one
error_log('/v1/checkout Endpoint Used: ' . print_r($_SERVER, true));
    }
}

public static function disable_wc_endpoint() {
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, '/wp-json/wc/store/checkout') !== false) {
//        wp_redirect(home_url('/404.php'));
//        exit;
error_log('/store/checkout Endpoint Used: ' . print_r($_SERVER, true));
    }
}





public static function webd_wp() {

    remove_theme_support('wc-product-gallery-zoom');
    remove_theme_support('wc-product-gallery-lightbox');
    remove_theme_support('wc-product-gallery-slider');

}

public static function webd_woocommerce_single_product_image_thumbnail_html($html) {
    return strip_tags($html, '<div><img>');
}

public static function webd_after_setup_theme() {

    remove_theme_support('wc-product-gallery-zoom');
    remove_theme_support('wc-product-gallery-lightbox');

}

public static function webd_woocommerce_product_query($q) {
	
	if(!is_admin() && is_shop()) {

	    $tax_query = (array) $q->get('tax_query');

	    $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => array('development-support', 'services'),
            'operator' => 'NOT IN'
	    );

	    $q->set('tax_query', $tax_query);

	}

}

public static function webd_get_url_action() {
?>
<script type="text/javascript">
    jQuery(document).ready(function() {
        if (getUrlParameter('url')) {
            jQuery('#alg_wc_pif_local_1').val(getUrlParameter('url'));
            // jQuery('#alg-product-input-fields-table').css('display', 'none');
        }
        // if (getUrlParameter('plugin')) {
        //     jQuery('#alg_wc_pif_local_1').val(getUrlParameter('plugin'));
        //     jQuery('#alg-product-input-fields-table').css('display', 'none');
        // }
        if ('text' === jQuery('#alg_wc_pif_local_1').prop('type')) {
            jQuery('#alg_wc_pif_local_1, #subdomains').change(function() {
                jQuery('#alg_wc_pif_local_1').val(jQuery('#alg_wc_pif_local_1').val().toLowerCase());
                jQuery('.webd-subdomains').remove();
                var licenseHostname = '';
                if (jQuery('#alg_wc_pif_local_1').val()) {
                    licenseHostname = jQuery('#alg_wc_pif_local_1').val().trim();
                    if (licenseHostname) {
                        if (licenseHostname.indexOf('http://') == 0) {
                            licenseHostname = licenseHostname.slice('http://'.length);
                        }
                        if (licenseHostname.indexOf('https://') == 0) {
                            licenseHostname = licenseHostname.slice('https://'.length);
                        }
                        if (licenseHostname.indexOf('www.') == 0) {
                            licenseHostname = licenseHostname.slice('www.'.length);
                        } else {
                            if (licenseHostname.indexOf('test.') == 0) {
                                licenseHostname = licenseHostname.slice('test.'.length);
                            } else {
                                if (licenseHostname.indexOf('dev.') == 0) {
                                    licenseHostname = licenseHostname.slice('dev.'.length);
                                } else {
                                    if (licenseHostname.indexOf('staging.') == 0) {
                                        licenseHostname = licenseHostname.slice('staging.'.length);
                                    }
                                }
                            }
                        }
                    }
                }
                if (licenseHostname.indexOf('.') > 0 && (jQuery('#subdomains option:selected').val() === 'Standard' || jQuery('#subdomains option:selected').val() === 'Transferable')) {
                    jQuery('.single_variation_wrap').prepend(jQuery('<div class="webd-subdomains"><ul><li>' + licenseHostname + '</li><li>www.' + licenseHostname + '</li><li>test.' + licenseHostname + '</li><li>dev.' + licenseHostname + '</li><li>staging.' + licenseHostname + '</li></ul></div>'));
                } else if (licenseHostname.indexOf('.') > 0 && jQuery('#subdomains option:selected').val() === 'Unlimited Subdomains') {
                    jQuery('.single_variation_wrap').prepend(jQuery('<div class="webd-subdomains"><ul><li>' + licenseHostname + '</li><li>www.' + licenseHostname + '</li><li>*.' + licenseHostname + ' (all subdomains)</li></ul></div>'));
                }
            });
            jQuery('#alg_wc_pif_local_1').trigger('change');
        }
    });
    function getUrlParameter(name) {
    	name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    	var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    	var results = regex.exec(location.search);
    	return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
	};
</script>
<?php
}

public static function webd_activate_plugin($order_id) {

    global $wpdb;

    $order = wc_get_order($order_id);

	foreach ($order->get_items() as $item_key => $item) {

        $product_name = $item['name'];
        $unlimited_subdomains = false;

        if (preg_match('/ - Unlimited Subdomains$/', $product_name)) {

            $unlimited_subdomains = true;
            $product_name = preg_replace('/ - Unlimited Subdomains$/', '', $product_name);

        } elseif (preg_match('/ - Standard/', $product_name)) {

            $product_name = preg_replace('/ - Standard$/', '', $product_name);

        } elseif (preg_match('/ - Transferable/', $product_name)) {

            $product_name = preg_replace('/ - Transferable$/', '', $product_name);

        }
error_log($product_name);
		if (preg_match('/ Upgrade/', $product_name) || preg_match('/^WordPress Maintenance/', $product_name)) {

            $product_name = preg_replace('/ Premium Upgrade$/', '', $product_name);
            $product_name = preg_replace('/ Upgrade$/', '', $product_name);
error_log($product_name);
    		foreach (wc_get_order_item_meta($item_key, '_alg_wc_pif_local') as $values) { 

                if (isset($values['_value'])) {

    				$url = webd_get_licensed_url($values['_value']);

                    $existing_licence = $wpdb->get_row(
                        $wpdb->prepare("
SELECT {$wpdb->prefix}webd_plugin_licences.ID
FROM {$wpdb->prefix}webd_plugin_licences
WHERE {$wpdb->prefix}webd_plugin_licences.plugin_slug = %s
AND {$wpdb->prefix}webd_plugin_licences.licenced_http_host = %s;
",
                            array(
                                sanitize_title($product_name),
                                $url
                            )
                        )
                    );

                    if (!$existing_licence) {

        				$wpdb->insert(
        					$wpdb->prefix . 'webd_plugin_licences',
        					array(
        						'plugin_slug' => sanitize_title($product_name),
        						'licenced_http_host' => $url
        					), 
        					array(
        						'%s',
        						'%s'
        					)
        				);

                    }

                    if ($unlimited_subdomains) {

                        $existing_licence = $wpdb->get_row(
                            $wpdb->prepare("
SELECT {$wpdb->prefix}webd_plugin_licences.ID
FROM {$wpdb->prefix}webd_plugin_licences
WHERE {$wpdb->prefix}webd_plugin_licences.plugin_slug = %s
AND {$wpdb->prefix}webd_plugin_licences.licenced_http_host = %s;
",
                                array(
                                    sanitize_title($product_name),
                                    '*.' . $url
                                )
                            )
                        );

                        if (!$existing_licence) {

            				$wpdb->insert(
            					$wpdb->prefix . 'webd_plugin_licences',
            					array(
            						'plugin_slug' => sanitize_title($product_name),
            						'licenced_http_host' => '*.' . $url
            					), 
            					array(
            						'%s',
            						'%s'
            					)
            				);

                        }

                    }

                } else {

error_log('Order ' . $order_id . ' URL not found in _alg_wc_pif_local $values: ' . print_r($values, true));

                }

            }

        }

    }

}

public static function webd_override_checkout_fields($fields) {

    // unset($fields['billing']['billing_first_name']);
    // unset($fields['billing']['billing_last_name']);
    // unset($fields['billing']['billing_company']);
    // unset($fields['billing']['billing_address_1']);
    // unset($fields['billing']['billing_address_2']);
    // unset($fields['billing']['billing_city']);
    // unset($fields['billing']['billing_postcode']);
    // unset($fields['billing']['billing_country']);
    // unset($fields['billing']['billing_state']);
    // unset($fields['billing']['billing_phone']);
    unset($fields['order']['order_comments']);
    // unset($fields['billing']['billing_email']);
    return $fields;

}

public static function webd_auto_complete_order($order_id) { 

	if (!$order_id) {

        return;

    }

	$order = wc_get_order($order_id);

    if ('completed' !== $order->get_status()) {

    	$order->update_status('completed');

    }

}

public static function webd_prevent_duplicate_orders($passed, $product_id, $quantity, $variation_id = false, $variations = false) {

    $url = false;

    if (true === $passed && isset($_REQUEST['alg_wc_pif_local_1'])) {

        $variation = wc_get_product($variation_id);

        if ($variation) {

            global $wpdb;

            $url = webd_get_licensed_url($_REQUEST['alg_wc_pif_local_1']);
            $product_name = $variation->get_name();
            $unlimited_subdomains = false;

            if (preg_match('/ - Unlimited Subdomains$/', $product_name)) {

                $unlimited_subdomains = true;
                $product_name = preg_replace('/ - Unlimited Subdomains$/', '', $product_name);

            } elseif (preg_match('/ - Standard/', $product_name)) {

                $product_name = preg_replace('/ - Standard$/', '', $product_name);

            } elseif (preg_match('/ - Transferable/', $product_name)) {

                $product_name = preg_replace('/ - Transferable$/', '', $product_name);

            }

    		if (preg_match('/ Upgrade/', $product_name)) {

                if ($url && sanitize_title($url)) {

                    $product_name = preg_replace('/ Premium Upgrade$/', '', $product_name);
                    $product_name = preg_replace('/ Upgrade$/', '', $product_name);

                    if (!$unlimited_subdomains) {

                        $existing_licence = $wpdb->get_row(
                            $wpdb->prepare("
SELECT {$wpdb->prefix}webd_plugin_licences.ID
FROM {$wpdb->prefix}webd_plugin_licences
WHERE {$wpdb->prefix}webd_plugin_licences.plugin_slug = %s
AND {$wpdb->prefix}webd_plugin_licences.licenced_http_host = %s;
",
                                array(
                                    sanitize_title($product_name),
                                    $url
                                )
                            )
                        );

                        if ($existing_licence) {

                            wc_add_notice('There is already a standard license for this website!', 'error');
                            $passed = false;

                        }

                    } else {

                        $existing_licence = $wpdb->get_row(
                            $wpdb->prepare("
SELECT {$wpdb->prefix}webd_plugin_licences.ID
FROM {$wpdb->prefix}webd_plugin_licences
WHERE {$wpdb->prefix}webd_plugin_licences.plugin_slug = %s
AND {$wpdb->prefix}webd_plugin_licences.licenced_http_host = %s;
",
                              array(
                                    sanitize_title($product_name),
                                    '*.' . $url
                                )
                            )
                        );

                        if ($existing_licence) {

                            wc_add_notice('There is already a infinite sub-domains license for this website!', 'error');
                            $passed = false;

                        }

                    }

                } else {

                    wc_add_notice('You have to enter a valid website address!', 'error');
                    $passed = false;

                }

    		}

        }

    }

    if (true === $passed && $url) {

        $_GET['alg_wc_pif_local_1'] = webd_get_basket_approved_url($_REQUEST['alg_wc_pif_local_1']);
        $_POST['alg_wc_pif_local_1'] = webd_get_basket_approved_url($_REQUEST['alg_wc_pif_local_1']);

    }

    return $passed;

}

public static function webd_get_basket_approved_url($url) {

    $url = trim($url);

    $prefixes_to_remove = array(
		'http://',
        'https://'
    );

    foreach ($prefixes_to_remove as $prefix) {

        if (substr($url, 0, strlen($prefix)) == $prefix) {

            $url = substr($url, strlen($prefix));

        } 

    }

    if (strpos($url, ':')) {

        $url = substr($url, 0, strpos($url, ':'));

    }

    if (strpos($url, '/')) {

        $url = substr($url, 0, strpos($url, '/'));

    }

    return $url;

}

public static function webd_get_licensed_url($url) {

    $url = trim(strtolower($url));

    $prefixes_to_remove = array(
		'http://',
        'https://',
        'www.',
        'test.',
        'dev.',
        'staging.'
    );

    foreach ($prefixes_to_remove as $prefix) {

        if (substr($url, 0, strlen($prefix)) == $prefix) {

            $url = substr($url, strlen($prefix));

        } 

    }

    if (strpos($url, ':')) {

        $url = substr($url, 0, strpos($url, ':'));

    }

    if (strpos($url, '/')) {

        $url = substr($url, 0, strpos($url, '/'));

    }

    if (!preg_match('/^(?:[a-z0-9][\-a-z0-9]*\.)*[a-z0-9][\-a-z0-9]*$/', $url)) {

        $url = false;

    }

    return $url;

}
}
