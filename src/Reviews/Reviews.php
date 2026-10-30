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
			return '';
		}

		$all_reviews = get_option( 'webd_product_reviews' );
		$all_reviews = is_array( $all_reviews ) ? $all_reviews : array();

		// Remove the old option rather than carrying stored HTML into the new cache.
		foreach ( $all_reviews as $entry ) {
			if ( is_array( $entry ) && isset( $entry['reviews'] ) && is_string( $entry['reviews'] ) ) {
				delete_option( 'webd_product_reviews' );
				$all_reviews = array();
				break;
			}
		}

		$cached = isset( $all_reviews[ $plugin ] ) && is_array( $all_reviews[ $plugin ] )
			? $all_reviews[ $plugin ] : array();
		$reviews = isset( $cached['reviews'] ) && is_array( $cached['reviews'] ) ? $cached['reviews'] : array();
		$fresh = isset( $cached['time'], $cached['reviews'] )
			&& is_numeric( $cached['time'] )
			&& is_array( $cached['reviews'] )
			&& time() < ( (int) $cached['time'] + DAY_IN_SECONDS );
		if ( ! $fresh ) {
			$refreshed = self::fetch_reviews( $plugin );
			if ( false !== $refreshed ) {
				$reviews = $refreshed;
				$all_reviews[ $plugin ] = array( 'time' => time(), 'reviews' => $reviews );
				update_option( 'webd_product_reviews', $all_reviews, false );
			}
		}

		if ( ! $reviews ) {
			return '';
		}

		return self::render_reviews( $reviews );
	}

	private static function fetch_reviews( $plugin ) {
		if ( ! function_exists( 'fetch_feed' ) ) {
			include_once ABSPATH . WPINC . '/feed.php';
		}
		$feed = fetch_feed( 'https://wordpress.org/support/plugin/' . rawurlencode( $plugin ) . '/reviews/feed/' );
		if ( is_wp_error( $feed ) ) {
			return false;
		}

		$reviews = array();
		foreach ( $feed->get_items( 0, 20 ) as $item ) {
			$title = (string) $item->get_title();
			if ( ! preg_match( '/\(5 stars\)\s*$/i', $title ) ) {
				continue;
			}

			$url = (string) $item->get_permalink();
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'], $parts['path'] )
				|| 'https' !== $parts['scheme'] || 'wordpress.org' !== $parts['host']
				|| 0 !== strpos( $parts['path'], '/support/topic/' ) ) {
				continue;
			}

			$description = (string) $item->get_description();
			$description = preg_replace( '/^\s*<p>\s*Replies:.*?<\/p>\s*<p>\s*Rating:.*?<\/p>/si', '', $description );
			$description = preg_replace( '/<ul[^>]*class="[^"]*bbp-topic-revision-log[^"]*"[^>]*>.*?<\/ul>/si', '', $description );
			$author = $item->get_author();
			$text = html_entity_decode( wp_strip_all_tags( $description ), ENT_QUOTES, 'UTF-8' );
			$reviews[] = array(
				'title'  => sanitize_text_field( trim( preg_replace( '/\s*\(5 stars\)\s*$/i', '', $title ) ) ),
				'author' => $author ? sanitize_text_field( $author->get_name() ) : '',
				'date'   => absint( $item->get_date( 'U' ) ),
				'text'   => sanitize_textarea_field( $text ),
				'url'    => esc_url_raw( $url ),
			);
			if ( 3 === count( $reviews ) ) {
				break;
			}
		}

		return $reviews;
	}

	private static function render_reviews( $reviews ) {
		$markup = '<!-- wp:group {"className":"webd-reviews"} --><div class="wp-block-group webd-reviews">';
		foreach ( $reviews as $review ) {
			if ( ! is_array( $review ) || empty( $review['url'] ) || empty( $review['title'] ) ) {
				continue;
			}
			$title = esc_html( $review['title'] );
			$url = esc_url( $review['url'] );
			$markup .= '<!-- wp:group {"className":"webd-review"} --><div class="wp-block-group webd-review">';
			$markup .= '<!-- wp:heading {"level":3,"className":"webd-review__title"} --><h3 class="wp-block-heading webd-review__title"><a href="' . $url . '">' . $title . '</a></h3><!-- /wp:heading -->';
			if ( ! empty( $review['author'] ) || ! empty( $review['date'] ) ) {
				$byline = esc_html( isset( $review['author'] ) ? $review['author'] : '' );
				if ( ! empty( $review['date'] ) ) {
					$byline .= ( $byline ? ' · ' : '' ) . esc_html( wp_date( get_option( 'date_format' ), (int) $review['date'] ) );
				}
				$markup .= '<!-- wp:paragraph {"className":"webd-review__byline"} --><p class="webd-review__byline">' . $byline . '</p><!-- /wp:paragraph -->';
			}
			if ( ! empty( $review['text'] ) ) {
				$markup .= '<!-- wp:paragraph {"className":"webd-review__text"} --><p class="webd-review__text">' . esc_html( $review['text'] ) . '</p><!-- /wp:paragraph -->';
			}
			$markup .= '</div><!-- /wp:group -->';
		}
		$markup .= '</div><!-- /wp:group -->';

		$output = '';
		foreach ( parse_blocks( $markup ) as $block ) {
			$output .= render_block( $block );
		}
		return $output;
	}
}
