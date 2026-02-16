<?php
/**
 * Shared PHPStan array shape type aliases for WP Pinch.
 *
 * Use in @param and @return docblocks across Ability, Rest, and other modules.
 * Example: @return PhpStanTypes::AbilityResult
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * PHPStan type aliases. Reference as PhpStanTypes::AbilityResult etc.
 *
 * @phpstan-type AbilityResult array{error?: string, posts?: array<int, array<string, mixed>>, post?: array<string, mixed>, total?: int, items?: array<int, array<string, mixed>>, message?: string}
 * @phpstan-type HookPayload array{event: string, payload?: array<string, mixed>}
 */
final class PhpStanTypes {
}
