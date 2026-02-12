/**
 * Pinch Chat block — editor entry point.
 *
 * Registers the block on the client side and imports styles.
 *
 * @package
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

import './style.css';
import './editor.css';

registerBlockType( 'wp-pinch/chat', {
	edit: Edit,
} );
