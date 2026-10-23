<?php
/** Reviews for plugins listed on webd.uk. */



if (!defined('ABSPATH')) {
    exit('This isn\'t the page you\'re looking for. Move along, move along.');
}



function webd_show_reviews_shortcode($atts, $content) {

    global $post;
    $plugin = false;
    $content = '';

    if (isset($atts['product'])) {

        $plugin = preg_replace('/(?:\-upgrade|\-donation)$/', '', $atts['product']);

    } elseif (is_object($post) && isset($post->post_name) && $post->post_name) {

        $plugin = preg_replace('/(?:\-upgrade|\-donation)$/', '', $post->post_name);

    }

    if ($plugin) {

        $all_reviews = get_option('webd_product_reviews');

        $this_plugin_reviews = '';

        if (is_array($all_reviews) && isset($all_reviews[$plugin])) {

            if (time() < (strtotime('+1 day', $all_reviews[$plugin]['time']))) {

                $this_plugin_reviews = $all_reviews[$plugin]['reviews'];

            }

        }

        if (empty($this_plugin_reviews)) {

            $page = file_get_contents("https://wordpress.org/plugins/$plugin/");
            preg_match_all('/<article class="plugin-review">(.*?)<\/article>/si', $page, $reviews);
            $i = 0;

            foreach($reviews[1] as $review) {

                if ($i < 3 && strpos($review, '"5 out of 5 stars"') !== false) {

                    preg_match('/<span class="review-date">(.*)<\/span>/', $review, $review_date);
                    $review_date[0] = DateTime::createFromFormat('F j, Y', $review_date[1]);
                    $this_plugin_reviews .= '<article class="plugin-review">' . strip_tags(preg_replace(array('/<span class="review-date">(.*)<\/span>/', '/<span class="review-replies">(.*)<\/span>/', '/ avatar-60 photo/', '/ class="wporg-ratings"/'), array('<span class="review-date">' . $review_date[0]->format(get_option('date_format')) . '</span>', '', '', ''), $review), '<article><div><img><header><span><h3>') . '</article>';
                    $i++;

                }

            }

            $all_reviews[$plugin] = array(
                'time' => time(),
                'reviews' => $this_plugin_reviews
            );

            update_option('webd_product_reviews', $all_reviews, false);

        }

        return '<p>A few reviews from wordpress.org ...</p>' . $this_plugin_reviews;

    }

}

add_shortcode('show_reviews', 'webd_show_reviews_shortcode');

?>
