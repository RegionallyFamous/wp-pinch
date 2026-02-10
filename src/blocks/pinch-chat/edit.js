/**
 * Pinch Chat — Editor component.
 *
 * @package WP_Pinch
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, UnitControl } from '@wordpress/components';

import './editor.css';

/**
 * Edit component for the Pinch Chat block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} Block editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { placeholder, showHeader, maxHeight } = attributes;
	const blockProps = useBlockProps( {
		className: 'wp-pinch-chat',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Chat Settings', 'wp-pinch' ) }>
					<TextControl
						label={ __( 'Placeholder text', 'wp-pinch' ) }
						value={ placeholder }
						onChange={ ( val ) => setAttributes( { placeholder: val } ) }
					/>
					<ToggleControl
						label={ __( 'Show header', 'wp-pinch' ) }
						checked={ showHeader }
						onChange={ ( val ) => setAttributes( { showHeader: val } ) }
					/>
					<UnitControl
						label={ __( 'Max height', 'wp-pinch' ) }
						value={ maxHeight }
						onChange={ ( val ) => setAttributes( { maxHeight: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ showHeader && (
					<div className="wp-pinch-chat__header">
						<span className="wp-pinch-chat__status-dot wp-pinch-chat__status-dot--preview" />
						<span className="wp-pinch-chat__header-title">
							{ __( 'Pinch Chat', 'wp-pinch' ) }
						</span>
					</div>
				) }

				<div
					className="wp-pinch-chat__messages"
					style={ { maxHeight } }
				>
					<div className="wp-pinch-chat__message wp-pinch-chat__message--system">
						{ __( 'Chat preview — messages will appear here on the frontend.', 'wp-pinch' ) }
					</div>
				</div>

				<div className="wp-pinch-chat__input-area">
					<input
						type="text"
						className="wp-pinch-chat__input"
						placeholder={ placeholder }
						disabled
					/>
					<button
						className="wp-pinch-chat__send"
						disabled
						aria-label={ __( 'Send', 'wp-pinch' ) }
					>
						&#9654;
					</button>
				</div>
			</div>
		</>
	);
}
