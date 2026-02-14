<?php
/**
 * Prompt Sanitizer — mitigates instruction injection in content sent to LLMs.
 *
 * Sanitizes WordPress content (posts, comments) before it reaches the AI
 * gateway. Strips or neutralizes lines that resemble injected instructions.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes content to reduce prompt injection risk.
 */
class Prompt_Sanitizer {

	/**
	 * Default patterns that indicate possible instruction injection.
	 *
	 * Matches entire lines. Case-insensitive. Lines matching are replaced with [redacted].
	 *
	 * @var string[]
	 */
	const DEFAULT_PATTERNS = array(
		'/ignore\s+(all\s+)?(previous|prior|above)\s+instructions/i',
		'/disregard\s+(all\s+)?(previous|prior|above)\s+instructions/i',
		'/forget\s+(all\s+)?(previous|prior|above)\s+(instructions|context)/i',
		'/new\s+instructions\s*:/i',
		'/override\s+(previous|prior)\s+instructions/i',
		'/you\s+are\s+now\s+(a|an)\s+\w+\s*[,;]/i',
		'/^system\s*:\s*.{10,}/im',
		'/^\[\s*INST\s*\].{20,}/im',
	);

	/**
	 * Patterns for short strings (titles, slugs, names) that may contain injection attempts.
	 *
	 * Applied when sanitizing title/slug-style content. Case-insensitive.
	 *
	 * @var string[]
	 */
	const TITLE_PATTERNS = array(
		'/^system\s*:\s*/i',
		'/^\[\s*INST\s*\]/i',
		'/^\[\s*SYSTEM\s*\]/i',
		'/ignore\s+(all\s+)?(previous|prior|above)\s+instructions/i',
		'/disregard\s+(all\s+)?(previous|prior|above)\s+instructions/i',
		'/new\s+instructions\s*:/i',
	);

	/**
	 * Sanitize content before sending to an LLM.
	 *
	 * Replaces lines matching instruction-injection patterns with [redacted].
	 * Applies the wp_pinch_prompt_sanitizer_patterns filter so themes/plugins
	 * can add or remove patterns.
	 *
	 * @param string $content Raw content (e.g. post content, comment text).
	 * @return string Sanitized content.
	 */
	public static function sanitize( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		/**
		 * Filter patterns used to detect instruction-like lines.
		 *
		 * @since 2.5.0
		 * @param string[] $patterns Array of regex patterns. Lines matching are redacted.
		 */
		$patterns = apply_filters( 'wp_pinch_prompt_sanitizer_patterns', self::DEFAULT_PATTERNS );

		if ( empty( $patterns ) ) {
			return $content;
		}

		$lines = preg_split( '/\r\n|\r|\n/', $content );
		if ( false === $lines ) {
			return $content;
		}

		$redacted = __( '[redacted]', 'wp-pinch' );
		foreach ( $lines as $i => $line ) {
			foreach ( $patterns as $pattern ) {
				if ( @preg_match( $pattern, $line ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$lines[ $i ] = $redacted;
					break;
				}
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Sanitize a short string (title, slug, name) for injection risk.
	 *
	 * Uses TITLE_PATTERNS; if any match, returns [redacted]. For multi-line content use sanitize().
	 *
	 * @param string $content Raw content (e.g. post title, author name).
	 * @return string Sanitized content.
	 */
	public static function sanitize_string( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		/**
		 * Filter patterns for short-string injection detection.
		 *
		 * @since 2.5.0
		 * @param string[] $patterns Array of regex patterns.
		 */
		$patterns = apply_filters( 'wp_pinch_prompt_sanitizer_title_patterns', self::TITLE_PATTERNS );

		foreach ( $patterns as $pattern ) {
			if ( @preg_match( $pattern, $content ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return __( '[redacted]', 'wp-pinch' );
			}
		}

		return $content;
	}

	/**
	 * Recursively sanitize arrays/objects for prompt injection.
	 *
	 * Walks arrays and objects; sanitizes string values with sanitize() for multi-line
	 * or sanitize_string() for short strings. Applied to governance findings and
	 * webhook payloads before sending to the AI gateway.
	 *
	 * @param mixed $data Data to sanitize (array, object, or scalar).
	 * @param int   $depth Current recursion depth (internal; max 10).
	 * @return mixed Sanitized data.
	 */
	public static function sanitize_recursive( $data, int $depth = 0 ): mixed {
		if ( $depth > 10 ) {
			return $data;
		}

		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				$out[ $k ] = self::sanitize_recursive( $v, $depth + 1 );
			}
			return $out;
		}

		if ( is_object( $data ) ) {
			$out = new \stdClass();
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$out->{$k} = self::sanitize_recursive( $v, $depth + 1 );
			}
			return $out;
		}

		if ( is_string( $data ) ) {
			// Use sanitize_string for short strings (titles, names); sanitize for longer content.
			$trimmed = trim( $data );
			if ( strlen( $trimmed ) < 200 && strpos( $trimmed, "\n" ) === false ) {
				return self::sanitize_string( $data );
			}
			return self::sanitize( $data );
		}

		return $data;
	}

	/**
	 * Whether prompt sanitization is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		/**
		 * Filter to enable or disable prompt sanitization.
		 *
		 * @since 2.5.0
		 * @param bool $enabled Whether to sanitize content before sending to LLMs.
		 */
		return (bool) apply_filters( 'wp_pinch_prompt_sanitizer_enabled', true );
	}
}
