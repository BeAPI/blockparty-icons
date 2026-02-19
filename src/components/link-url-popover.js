/**
 * WordPress dependencies
 */
import { DELETE, BACKSPACE } from '@wordpress/keycodes';
import { useDispatch } from '@wordpress/data';
import {
	URLPopover,
	URLInput,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { keyboardReturn } from '@wordpress/icons';

export default function SocialLinkURLPopover( {
	url,
	setAttributes,
	setPopover,
	popoverAnchor,
	clientId,
} ) {
	const { removeBlock } = useDispatch( blockEditorStore );
	return (
		<URLPopover
			anchor={ popoverAnchor }
			onClose={ () => setPopover( false ) }
		>
			<form
				className="block-editor-url-popover__link-editor"
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPopover( false );
				} }
			>
				<div className="block-editor-url-input">
					<URLInput
						__nextHasNoMarginBottom
						value={ url }
						onChange={ ( nextURL ) =>
							setAttributes( { url: nextURL } )
						}
						placeholder={ __( 'Enter address' ) }
						disableSuggestions={ true }
						onKeyDown={ ( event ) => {
							if (
								!! url ||
								event.defaultPrevented ||
								! [ BACKSPACE, DELETE ].includes(
									event.keyCode
								)
							) {
								return;
							}
							removeBlock( clientId );
						} }
					/>
				</div>
				<Button
					icon={ keyboardReturn }
					label={ __( 'Apply' ) }
					type="submit"
				/>
			</form>
		</URLPopover>
	);
}
