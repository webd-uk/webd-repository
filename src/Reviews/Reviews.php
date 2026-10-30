<?php
/** WordPress.org reviews shown by the existing shortcode. */

namespace Webd\Repository\Reviews;

defined( 'ABSPATH' ) || exit;

final class Reviews {
	public static function register_hooks() {
		add_shortcode( 'show_reviews', 'webd_show_reviews_shortcode' );
	}

	public static function webd_show_reviews_shortcode( $atts, $content ) {
		global $post;

		$plugin = false;
		if ( isset( $atts['product'] ) ) {
			$plugin = preg_replace( '/(?:\-upgrade|\-donation)$/', '', $atts['product'] );
		} elseif ( is_object( $post ) && ! empty( $post->post_name ) ) {
			$plugin = preg_replace( '/(?:\-upgrade|\-donation)$/', '', $post->post_name );
		}

		$plugin = sanitize_title( $plugin );
		if ( ! $plugin ) {
			return;
		}

		$all_reviews = get_option( 'webd_product_reviews' );
		$all_reviews = is_array( $all_reviews ) ? $all_reviews : array();
		$cached = isset( $all_reviews[ $plugin ] ) && is_array( $all_reviews[ $plugin ] )
			? $all_reviews[ $plugin ] : array();
		$reviews = isset( $cached['reviews'] ) && is_string( $cached['reviews'] ) ? $cached['reviews'] : '';

		// An empty review list is still a valid result until its cache period ends.
		$fresh = isset( $cached['time'], $cached['reviews'] )
			&& is_numeric( $cached['time'] )
			&& time() < ( (int) $cached['time'] + DAY_IN_SECONDS );
		if ( ! $fresh ) {
			$refreshed = self::fetch_reviews( $plugin );
			if ( false !== $refreshed ) {
				$reviews = $refreshed;
				$all_reviews[ $plugin ] = array( 'time' => time(), 'reviews' => $reviews );
				update_option( 'webd_product_reviews', $all_reviews, false );
			}
		}

		return '<p>A few reviews from wordpress.org ...</p>' . $reviews;
	}

	private static function fetch_reviews( $plugin ) {
		if ( ! function_exists( 'fetch_feed' ) ) {
			include_once ABSPATH . WPINC . '/feed.php';
		}
		$feed = fetch_feed( 'https://wordpress.org/support/plugin/' . rawurlencode( $plugin ) . '/reviews/feed/' );
		if ( is_wp_error( $feed ) ) {
			return false;
		}

		$reviews = '';
		$count = 0;
		foreach ( $feed->get_items( 0, 20 ) as $item ) {
			$title = $item->get_title();
			if ( ! preg_match( '/\(5 stars\)\s*$/i', $title ) ) {
				continue;
			}

			$title = preg_replace( '/\s*\(5 stars\)\s*$/i', '', $title );
			$description = $item->get_description();
			$description = preg_replace( '/^\s*<p>\s*Replies:.*?<\/p>\s*<p>\s*Rating:.*?<\/p>/si', '', $description );
			$description = preg_replace( '/<ul[^>]*class="[^"]*bbp-topic-revision-log[^"]*"[^>]*>.*?<\/ul>/si', '', $description );
			$author = $item->get_author();
			$date = $item->get_date( 'U' );
			$reviews .= '<article class="plugin-review"><header><h3>' . esc_html( $title ) . '</h3>';
			if ( $author ) {
				$reviews .= '<span>' . esc_html( $author->get_name() ) . '</span>';
			}
			if ( $date ) {
				$reviews .= '<span class="review-date">' . esc_html( wp_date( get_option( 'date_format' ), (int) $date ) ) . '</span>';
			}
			$reviews .= '</header><div>' . wp_kses_post( $description ) . '</div></article>';
			$count++;
			if ( 3 === $count ) {
				break;
			}
		}

		return $reviews;
	}
}
