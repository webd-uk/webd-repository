<?php
/** Migrated feature methods; behavior is retained during the structural refactor. */

namespace Webd\Repository\API;

defined( 'ABSPATH' ) || exit;

final class PluginRepository {
	public static function register_hooks() {
		add_action('wp_ajax_nopriv_plugin_version', 'webd_plugin_version');
		add_action('wp_ajax_nopriv_plugin_information', 'webd_plugin_information');
		add_shortcode('packagelink', 'webd_package_link');

		if (!is_admin()) {
			add_action('wp_enqueue_scripts', 'webd_enqueue_dashicons');
		}
	}

public static function webd_plugin_version() {

    if (isset($_REQUEST['plugin']) && sanitize_title($_REQUEST['plugin'])) {

        $plugin_slug = sanitize_title($_REQUEST['plugin']);

        $return_values = webd_update_stats($plugin_slug);

        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'webd_plugin_updates')) != $wpdb->prefix . 'webd_plugin_updates') {

            webd_create_plugin_updates_database();

        }

        $latest_version = $wpdb->get_row($wpdb->prepare(
            "SELECT {$wpdb->prefix}webd_plugin_updates.plugin_version,
{$wpdb->prefix}webd_plugin_updates.tested,
{$wpdb->prefix}webd_plugin_updates.changelog
FROM {$wpdb->prefix}webd_plugin_updates
WHERE {$wpdb->prefix}webd_plugin_updates.plugin_slug = %s
ORDER BY {$wpdb->prefix}webd_plugin_updates.ID DESC;",
            $plugin_slug
        ));

        if ($latest_version) {

            $response = new \stdClass();
            $response->slug = $plugin_slug;
            $response->new_version = $latest_version->plugin_version;
            $response->tested = $latest_version->tested;
            $response->changelog = $latest_version->changelog;

            if (!$return_values['local_or_ip']) {

                $response->trial_date = $return_values['trial_date'];
                $response->purchased = $return_values['purchased'];

            }

            wp_send_json($response);

        } else {

            wp_send_json_error();

        }

    } else {

        wp_send_json_error();

    }

    wp_die();

}

public static function webd_plugin_information() {

    if (isset($_REQUEST['plugin']) && sanitize_title($_REQUEST['plugin'])) {

        $plugin_slug = sanitize_title($_REQUEST['plugin']);

        $return_values = webd_update_stats($plugin_slug);

        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'webd_plugin_updates')) != $wpdb->prefix . 'webd_plugin_updates') {

            webd_create_plugin_updates_database();

        }

        $version_history = $wpdb->get_results($wpdb->prepare(
            "SELECT {$wpdb->prefix}webd_plugin_updates.updated_date,
{$wpdb->prefix}webd_plugin_updates.plugin_version,
{$wpdb->prefix}webd_plugin_updates.tested,
{$wpdb->prefix}webd_plugin_updates.requires,
{$wpdb->prefix}webd_plugin_updates.changelog
FROM {$wpdb->prefix}webd_plugin_updates
WHERE {$wpdb->prefix}webd_plugin_updates.plugin_slug = %s
ORDER BY {$wpdb->prefix}webd_plugin_updates.ID DESC;",
            $plugin_slug
        ));

        if ($version_history) {

            $response = new \stdClass();
            $response->slug = $plugin_slug;
            $response->last_updated = date("Y-m-d g:ia", strtotime($version_history[0]->updated_date)) . ' GMT';
            $response->version = $version_history[0]->plugin_version;
            $response->tested = $version_history[0]->tested;
            $response->requires = $version_history[0]->requires;
            $response->sections = array('changelog' => '');

            if (!$return_values['local_or_ip']) {

                $response->trial_date = $return_values['trial_date'];
                $response->purchased = $return_values['purchased'];

            }

            foreach ($version_history as $plugin_version) {

                $response->sections['changelog'] = $response->sections['changelog'] . "
<h4>{$plugin_version->plugin_version}</h4>
<ul>
<li>{$plugin_version->changelog}</li>
</ul>";

            }

            wp_send_json($response);

        } else {

            wp_send_json_error();

        }

    } else {

        wp_send_json_error();

    }

    wp_die();

}

public static function webd_update_stats($plugin_slug) {

    global $wpdb;

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'webd_plugin_stats')) != $wpdb->prefix . 'webd_plugin_stats') {

        webd_create_plugin_stats_database();

    }

    $request_values = array(
        'plugin_slug' => $plugin_slug,
        'plugin_version' => '',
        'trial_date' => 0,
        'purchased' => 0,
        'http_host' => ''
    );

    $return_values = array(
        'trial_date' => 0,
        'purchased' => 0,
        'local_or_ip' => false
    );

    if (isset($_REQUEST['version']) && preg_match('/^(\d+\.)?(\d+\.)?(\*|\d+)$/', $_REQUEST['version'])) {

        $request_values['plugin_version'] = $_REQUEST['version'];

    }

    if (isset($_REQUEST['trial_date']) && absint($_REQUEST['trial_date'])) {

        $request_values['trial_date'] = absint($_REQUEST['trial_date']);

    }

    if (isset($_REQUEST['purchased']) && $_REQUEST['purchased']) {

        $request_values['purchased'] = true;

    }

    $url = '';

    if (isset($_REQUEST['url']) && esc_url_raw($_REQUEST['url'])) {

        $url = esc_url_raw($_REQUEST['url']);

    } else {

        $url = explode('; ', strtolower($_SERVER['HTTP_USER_AGENT']));

        if (isset($url[1]) && esc_url_raw($url[1])) {

            $url = esc_url_raw($url[1]);

        } else {

            $url = '';

        }

    }

    if ($url) {

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

        $return_values['local_or_ip'] = webd_is_local($url);

        $prefixes_to_remove = array(
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

        $request_values['http_host'] = $url;

    }

    if ($request_values['http_host']) {

        $existing_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT {$wpdb->prefix}webd_plugin_stats.ID, {$wpdb->prefix}webd_plugin_stats.plugin_version, {$wpdb->prefix}webd_plugin_stats.trial_date, {$wpdb->prefix}webd_plugin_stats.purchased
FROM {$wpdb->prefix}webd_plugin_stats
WHERE {$wpdb->prefix}webd_plugin_stats.plugin_slug = %s
AND {$wpdb->prefix}webd_plugin_stats.http_host = %s
ORDER BY {$wpdb->prefix}webd_plugin_stats.ID DESC;",
            array(
                $request_values['plugin_slug'],
                $request_values['http_host']
            )
        ));

        if ($existing_stats) {

            $update_values = array(
                'request_date' => date('Y-m-d H:i:s', time())
            );

            if ($request_values['plugin_version'] !== '' && $request_values['plugin_version'] !== $existing_stats->plugin_version) {

                $update_values['plugin_version'] = $request_values['plugin_version'];

            }

            if ($request_values['trial_date'] !== 0 && $existing_stats->trial_date == 0) {

                $update_values['trial_date'] = $request_values['trial_date'];
                $return_values['trial_date'] = $request_values['trial_date'];

            }

            if ($existing_stats->trial_date != 0) {

                $return_values['trial_date'] = $existing_stats->trial_date;

            }

            if ($request_values['purchased'] !== 0 && $request_values['purchased'] !== $existing_stats->purchased) {

                $update_values['purchased'] = $request_values['purchased'];

            }

            $wpdb->update(
                "{$wpdb->prefix}webd_plugin_stats",
                $update_values,
                array(
                    'ID' => $existing_stats->ID
                )
            );

        } else {

    		$wpdb->insert(
    			$wpdb->prefix . 'webd_plugin_stats',
    			$request_values, 
    			array(
    				'%s',
    				'%s',
    				'%d',
    				'%d',
    				'%s'
    			)
    		);

        }

        $plugin_licence = $wpdb->get_row($wpdb->prepare(
            "SELECT {$wpdb->prefix}webd_plugin_licences.ID
FROM {$wpdb->prefix}webd_plugin_licences
WHERE {$wpdb->prefix}webd_plugin_licences.plugin_slug = %s
AND ({$wpdb->prefix}webd_plugin_licences.licenced_http_host = %s
OR {$wpdb->prefix}webd_plugin_licences.licenced_http_host = %s);",
            array(
                preg_replace('/\-premium$/', '', $request_values['plugin_slug']),
                $request_values['http_host'],
                '*' . strstr($request_values['http_host'], '.')
            )
        ));

        if ($plugin_licence) {

            $return_values['purchased'] = 1;

        } else {

            // $webd_version_check = array(
            //    'options-for-twenty-seventeen-premium' => '1.0.9',
            //    'options-for-twenty-twenty-premium' => '1.2.1',
            //    'options-for-twenty-nineteen-premium' => '1.1.3',
            //    'sidemenu-premium' => '1.0.4',
            //    'wp-accounts-premium' => '1.0.6'
            // );

            if (
                isset($_REQUEST['purchased'])
                && $_REQUEST['purchased']
                && !$return_values['local_or_ip']
                && isset($request_values['plugin_version'])
                // && isset($webd_version_check[$request_values['plugin_slug']])
                // && version_compare($webd_version_check[$request_values['plugin_slug']], $request_values['plugin_version']) < 1
                && !in_array($request_values['http_host'], array(
                    'oliobalestra.it' // Need to find a way to deactivate a plugin remotely if they've tried to fake purchase it too many times
                ))
            ) {

                // wp_mail('info@webd.uk', 'Plugin Activation Check: ' . $request_values['plugin_slug'], 'Unlicensed URL: ' . $request_values['http_host'], 'From: info@webd.uk');

            }

        }

    }

    return $return_values;

}

public static function webd_create_plugin_updates_database() {

    global $wpdb;

    $create_table_sql = "CREATE TABLE {$wpdb->prefix}webd_plugin_updates (
ID int(11) NOT NULL AUTO_INCREMENT,
plugin_slug varchar(255) NOT NULL,
plugin_version varchar(11) NOT NULL,
updated_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
tested varchar(11) NOT NULL,
requires varchar(11) NOT NULL,
changelog varchar(255) NOT NULL,
PRIMARY KEY (ID),
INDEX (plugin_slug, plugin_version)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($create_table_sql);

}

public static function webd_create_plugin_stats_database() {

    global $wpdb;

    $create_table_sql = "CREATE TABLE {$wpdb->prefix}webd_plugin_stats (
ID int(11) NOT NULL AUTO_INCREMENT,
plugin_slug varchar(255) NOT NULL,
plugin_version varchar(11) NOT NULL,
request_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
http_host varchar(512) NOT NULL,
trial_date int(11) NOT NULL,
purchased boolean NOT NULL,
PRIMARY KEY (ID),
INDEX (plugin_slug, http_host)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($create_table_sql);

}

public static function webd_package_link($atts, $content = false) {

    if (isset($atts['plugin']) && $atts['plugin']) {

        $atts['plugin'] = sanitize_title($atts['plugin']);

        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'webd_plugin_updates')) != $wpdb->prefix . 'webd_plugin_updates') {

            webd_create_plugin_updates_database();

        }

        if ($atts['plugin']) {

            if (!$content) { $content = ucwords(str_replace('-', ' ', $atts['plugin'])); }

            $latest_version = $wpdb->get_row($wpdb->prepare(
                "SELECT {$wpdb->prefix}webd_plugin_updates.plugin_version, {$wpdb->prefix}webd_plugin_updates.tested
FROM {$wpdb->prefix}webd_plugin_updates
WHERE {$wpdb->prefix}webd_plugin_updates.plugin_slug = %s
ORDER BY {$wpdb->prefix}webd_plugin_updates.ID DESC;",
                $atts['plugin']
            ));

            if ($latest_version) {

                $size = explode(' ', size_format(filesize(ABSPATH . 'downloads/' . $atts['plugin'] . '.' . $latest_version->plugin_version . '.zip')));

                $package_link = '<p><a title="Download ' . $content . '" href="https://webd.uk/downloads/' . $atts['plugin'] . '.' . $latest_version->plugin_version . '.zip"><span class="dashicons dashicons-download"></span> ' . $atts['plugin'] . '.' . $latest_version->plugin_version . '.zip</a>';
                $package_link .= '<br />(File size: ' . $size[0] . ucfirst(strtolower($size[1])) . ' / Tested up to: ' . $latest_version->tested;

                if (current_user_can('manage_options')) {

                    $active_installs = $wpdb->get_row($wpdb->prepare(
                        "SELECT COUNT(plugin_slug) as total_active_installs
FROM (SELECT plugin_slug FROM {$wpdb->prefix}webd_plugin_stats
WHERE {$wpdb->prefix}webd_plugin_stats.plugin_slug = %s AND
request_date > DATE_SUB(NOW(), INTERVAL 7 DAY)) AS active_installs;",
                        $atts['plugin']
                    ));
                    $package_link .=  ' / Active installations: ' . $active_installs->total_active_installs;

                }

                $package_link .=  ')</p>';

            } else {

                $package_link = 'Plugin not found!';

            }

        } else {

            $package_link = 'You need to specify the "plugin" slug!';

        }

    } else {

        $package_link = 'You need to specify the "plugin" slug!';

    }

    return $package_link;

}

public static function webd_is_local($client_host) {

    if (
        (filter_var($client_host, FILTER_VALIDATE_IP) && (
            preg_match('#^(^127\.)|(^10\.)|(^172\.1[6-9]\.)|(^172\.2[0-9]\.)|(^172\.3[0-1]\.)|(^192\.168\.).#', $client_host)
        )) ||
        !strpos($client_host, '.') ||
        preg_match('#.local$#', $client_host)
    ) {

        return true;

    } else {

        return false;

    }

}


public static function webd_enqueue_dashicons() {

    wp_enqueue_style('dashicons');

}
}
