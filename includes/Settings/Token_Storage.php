<?php
/**
 * Encrypt/decrypt API token for storage. Used by Settings for wp_pinch_api_token and network token.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Token encryption at rest (sodium secretbox).
 */
class Token_Storage {

	/**
	 * Prefix for encrypted API token in wp_options (encrypted at rest).
	 *
	 * @var string
	 */
	public const PREFIX = 'wp_pinch_enc_v1:';

	/**
	 * Derive a 32-byte key from WordPress auth salts for token encryption.
	 *
	 * @return string
	 */
	public static function get_encryption_key(): string {
		$key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
		return hash( 'sha256', $key . $salt, true );
	}

	/**
	 * Encrypt the API token for storage.
	 *
	 * @param string $token Plaintext token.
	 * @return string|null Encrypted blob with prefix, or null on failure.
	 */
	public static function encrypt_token( string $token ): ?string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return null;
		}
		$key        = self::get_encryption_key();
		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $token, $nonce, $key );
		return self::PREFIX . base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Decrypt the API token from storage.
	 *
	 * @param string $stored Value from wp_options (must start with PREFIX).
	 * @return string|null Plaintext token or null on failure.
	 */
	public static function decrypt_token( string $stored ): ?string {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return null;
		}
		$payload = substr( $stored, strlen( self::PREFIX ) );
		$decoded = base64_decode( $payload, true );
		if ( false === $decoded || strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key        = self::get_encryption_key();
		$plain      = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		return false !== $plain ? $plain : null;
	}
}
