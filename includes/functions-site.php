<?php
/** Site features for the webd.uk repository. */



if (!defined('ABSPATH')) {
    exit('This isn\'t the page you\'re looking for. Move along, move along.');
}



// Animate site titles
add_action('wp_footer', 'webd_wp_footer');
// Filter featured images
add_filter('post_thumbnail_id', 'webd_post_thumbnail_id', 10, 2);
// Inject Open Graph and SEO tags
add_filter('wp_head', 'webd_wp_head');
// Block /scan/ in robots.txt
add_filter('robots_txt', 'webd_robots_txt');
// Filter category descriptions
add_filter('category_description', 'webd_category_description', 10, 2);

if (!is_admin()) {

    // Replace Block Logic plugin ... !
    add_filter('render_block', 'webd_render_block', 10, 3);

}

// Add shortcode to thank recent contributors
add_shortcode('webd-recent-contributions', 'webd_recent_contributions');




function webd_recent_contributions($atts, $content = '') {

    $product = wc_get_product(get_queried_object_id());

    if ($product) {

        global $wpdb;

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "
SELECT DISTINCT opl.order_id
FROM {$wpdb->prefix}wc_order_product_lookup opl
JOIN {$wpdb->prefix}wc_orders o ON opl.order_id = o.id
WHERE o.type = 'shop_order'
AND o.status = 'wc-completed'
AND (opl.product_id = %d)
ORDER BY opl.order_item_id DESC
LIMIT 0,50;
",
            $product->get_id())
        );

        $is_variable = $product->is_type('variable');
        $product_name = $product->get_name();
        $orders_details = array();

        if ($order_ids) {

            foreach ($order_ids as $order_id) {

                $order = wc_get_order($order_id);
                $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

                if (preg_match('/^(?:mr|mrs|miss|ms|dr)\.?\s+([^\s]+)/i', $customer_name, $matches)) {

                    $customer_name = $matches[1]; // Result: "Jane"

                } else {

                    preg_match('/^([^\s]+)/', $customer_name, $matches);
                    $customer_name = $matches[1];

                }

                $country_code = $order->get_billing_country();
                $country = '';

                if ($country_code) {

                    $country = preg_replace('/\s*[\(\[].*?[\)\]]\s*$/', '', WC()->countries->countries[$order->get_billing_country()]);

                }

                foreach ($order->get_items() as $item_id => $item) {

                   if ($product->get_id() === $item->get_product_id()) {

                        if ($is_variable) {

                            $child_product = $item->get_product();

                            $orders_details[] = array(
                                'name' => $customer_name,
                                'product' => substr($child_product->get_name(), (strlen($product_name) + 3)),
                                'country' => $country
                            );

                        } else {

                            $orders_details[] = array(
                                'name' => $customer_name,
                                'product' => $product_name,
                                'country' => $country
                            );

                        }
       
                    }

                    if (count($orders_details) > 9) {

                        break;

                    }

                }

                if (count($orders_details) > 9) {

                    break;

                }

            }

            if ($orders_details) {

                $content = '<p>Our gratitude to all the lovely people who\'ve recently bought us something ... !</p>
<ul>
';

                foreach ($orders_details as $orders_detail) {

                    $content .= '<li>' . esc_html(['Many thanks', 'Cheers to', 'Very much appreciate', 'So grateful to'][rand(0, 3)] . ' "' . $orders_detail['name'] . '"' . ($orders_detail['country'] ? ' in ' . $orders_detail['country'] : '') . ' for the ' . $orders_detail['product']) . ' ' . ['🥰', '😊', '😘', '❤️'][rand(0, 3)] . '</li>
';

                }

                $content .= '</ul>
';

            }

        }

    }

    return $content;

}

function webd_wp_footer() {

?>
<style>
h1.wp-block-site-tagline, h1.wp-block-heading, h1.wp-block-post-title, .wp-block-query-title {
    visibility: hidden;
}
</style>
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", () => {
    const symbols = ["$", "%", "&", "<", ">", "?", "*", "#", "?", "!"];
    const animationSpeed = 50;
    const letterDelay = 50;

    const animateTitle = (element) => {
        if (element.dataset.animating === "true") return;
        element.dataset.animating = "true";

        // Store original title if not already stored
        const originalTitle = element.dataset.originalTitle || element.textContent;
        element.dataset.originalTitle = originalTitle;

        // Get current height before clearing text
        const originalHeight = element.offsetHeight + "px";
        element.style.height = originalHeight;
        element.style.overflow = "hidden"; // Prevent content shift
        element.textContent = "";
        element.style.visibility = "visible";

        const revealLetter = (index) => {
            const currentLetter = originalTitle[index];
            let randomSymbolIndex = 0;

            const symbolInterval = setInterval(() => {
                if (randomSymbolIndex < 5) {
                    element.textContent =
                        element.textContent.slice(0, index) + symbols[Math.floor(Math.random() * symbols.length)];
                    randomSymbolIndex++;
                } else {
                    clearInterval(symbolInterval);
                    element.textContent =
                        element.textContent.slice(0, index) + currentLetter;
                }
            }, animationSpeed);
        };

        for (let i = 0; i < originalTitle.length; i++) {
            setTimeout(() => revealLetter(i), i * letterDelay);
        }

        // Calculate when animation ends
        const totalDuration = originalTitle.length * letterDelay + 5 * animationSpeed;

        setTimeout(() => {
            element.style.height = "auto"; // Let it become responsive again
            element.style.overflow = "";    // Clean up inline style
            element.dataset.animating = "false";
        }, totalDuration);
    };

    const elements = document.querySelectorAll("h1.wp-block-site-tagline, h1.wp-block-heading, h1.wp-block-post-title, .wp-block-query-title, .contact-header");

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateTitle(entry.target);
                }
            });
        },
        {
            threshold: 0.1,
        }
    );

    elements.forEach((element) => {
        observer.observe(element);
    });
});
</script>
<?php
/*
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", () => {
    const symbols = ["$", "%", "&", "<", ">", "?", "*", "#", "?", "!"];
    const animationSpeed = 50;
    const letterDelay = 50;

    const animateTitle = (element) => {
        if (element.dataset.animating === "true") return;
        element.dataset.animating = "true";

        const originalTitle = element.dataset.originalTitle || element.textContent;
        element.dataset.originalTitle = originalTitle;
        element.textContent = "";
        element.style.visibility = "visible";

        const revealLetter = (index) => {
            const currentLetter = originalTitle[index];
            let randomSymbolIndex = 0;

            const symbolInterval = setInterval(() => {
                if (randomSymbolIndex < 5) {
                    element.textContent =
                        element.textContent.slice(0, index) + symbols[Math.floor(Math.random() * symbols.length)];
                    randomSymbolIndex++;
                } else {
                    clearInterval(symbolInterval);
                    element.textContent =
                        element.textContent.slice(0, index) + currentLetter;
                }
            }, animationSpeed);
        };

        for (let i = 0; i < originalTitle.length; i++) {
            setTimeout(() => revealLetter(i), i * letterDelay);
        }

        setTimeout(() => {
            element.dataset.animating = "false";
        }, originalTitle.length * letterDelay + 5 * animationSpeed);
    };

    const elements = document.querySelectorAll("h1.wp-block-site-tagline, h1.wp-block-post-title, .wp-block-query-title, .contact-header");

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateTitle(entry.target);
                }
            });
        },
        {
            threshold: 0.1,
        }
    );

    elements.forEach((element) => {
        observer.observe(element);
    });
});
</script>
*/

}

function webd_has_own_thumbnail_id() {

    global $wpdb, $post;

    $term_thumbnail_ids = $wpdb->get_col("SELECT meta_value FROM $wpdb->termmeta WHERE meta_key = '_thumbnail_id' GROUP BY meta_value;");
    $post_thumbnail_id = (string) get_post_thumbnail_id($post);

    if (
        $term_thumbnail_ids &&
        $post_thumbnail_id &&
        !in_array($post_thumbnail_id, $term_thumbnail_ids, true)
    ) {

        return true;

    }

    return false;

}

function webd_post_thumbnail_id($thumbnail_id, $post) {

    if (
        !$thumbnail_id &&
        isset($post->post_type) &&
        'post' === $post->post_type
    ) {

        $categories = get_the_terms($post, 'category');

        if ($categories) {

            foreach ($categories as $category) {

                $term_thumbnail_id = get_term_meta($category->term_id, '_thumbnail_id', true);

                if ($term_thumbnail_id) {

                    return $term_thumbnail_id;

                }

                while ($category->parent) {

                    $category = get_term($category->parent, 'category');

                    $term_thumbnail_id = get_term_meta($category->term_id, '_thumbnail_id', true);

                    if ($term_thumbnail_id) {

                        return $term_thumbnail_id;

                    }

                }

            }

        }

    }

    return $thumbnail_id;

}

function webd_wp_head() {

    $meta_title = '';
    $meta_description = '';
    $meta_image = '';

    if (is_home() || is_front_page()) {

        $meta_title = 'Web:D - design + development';
        $meta_description = 'Web:D are a full stack web design agency with years of design & development experience.';
        $meta_image = 'https://webd.uk/wp-content/uploads/2024/12/webd-design-development.jpg';

    }

    if ($meta_title) {

?>
<meta property="og:title" content="<?php echo esc_attr($meta_title); ?>">
<meta name="twitter:title" content="<?php echo esc_attr($meta_title); ?>">
<meta property="og:type" content="website">
<?php

    }

    if ($meta_description) {

?>
<meta name="description" content="<?php echo esc_attr($meta_description); ?>">
<meta property="og:description" content="<?php echo esc_attr($meta_description); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($meta_description); ?>">
<?php

    }

    if ($meta_image) {

?>
<meta property="og:image" content="<?php echo esc_url($meta_image); ?>">
<meta name="twitter:image" content="<?php echo esc_url($meta_image); ?>">
<?php

    }

}

function webd_robots_txt($output) {

	$path = (!empty( $site_url['path'])) ? $site_url['path'] : '';
	$lines = preg_split('/\r\n|\r|\n/', $output);
	$agent_index = array_search('User-agent: *', $lines, true);

	if ( false !== $agent_index ) {

		$above = array_slice($lines, 0, $agent_index + 1);
		$below = array_slice($lines, $agent_index + 1);

	} else {

		$above = $lines;
		$below = array();
		$above[] = '';
		$above[] = 'User-agent: *';

	}

	$above[] = "Disallow: $path/scan/";

	$lines = array_merge($above, $below);

	return implode(PHP_EOL, $lines);

}

function webd_render_block($block_content, $block, $instance) {

    $class_names = array();

    if (
        isset($block['attrs']['className']) &&
        $block['attrs']['className']
    ) {

        $class_names = explode(' ', $block['attrs']['className']);

    }

    if (
        $class_names &&
        in_array('has-wordpress-servicing-category', $class_names, true) &&
        !has_category('wordpress-servicing')
    ) {

        return '';

    }

    if (
        $class_names &&
        is_single() &&
        in_array('has-own-thumbnail-id', $class_names, true) &&
        !webd_has_own_thumbnail_id()
    ) {

        return '';

    }

    if (
        $class_names &&
        is_single() &&
        in_array('not-has-own-thumbnail-id', $class_names, true) &&
        webd_has_own_thumbnail_id()
    ) {

        return '';

    }

    if (
        $class_names &&
        is_single() &&
        in_array('has-comments', $class_names, true) &&
        !webd_has_comments()
    ) {

        return '';

    }

    if (
        $class_names &&
        in_array('is-user-logged-in', $class_names, true) &&
        !is_user_logged_in()
    ) {

        return '';

    }

    return $block_content;

}

function webd_has_comments() {

    global $post;

    $comments_count = wp_count_comments($post->ID);

    if (
        $comments_count->approved ||
        is_user_logged_in()
    ) {

        return true;

    }

    return false;

}

function webd_category_description($description, $category) {

    $category = get_term($category);

    foreach ([$category->term_id, $category->parent] as $term_id) {
        if (!$term_id) {
            continue;
        }

        $posts = get_posts([
            'post_type' => 'taxonomy-header',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => $category->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => [$term_id],
                ]
            ]
        ]);

        if ($posts) {

            if (is_admin()) {

                return "Description managed by Tax Headers.";

            } else {

                return do_blocks(serialize_blocks(parse_blocks($posts[0]->post_content)));

            }

        }

    }

    return $description;
}

?>
