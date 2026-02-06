/**
 * WordPress dependencies
 */
import { memo } from '@wordpress/element';
import { Button } from '@wordpress/components';

import IconContent from './icon-content';
import { capitalize } from '../utils';

function IconSelector( {
	handleIconSelectButtonClick,
	icon,
	iconColorValue,
	padding,
	size,
} ) {
	return (
		<Button
			className="block-editor-block-types-list__item"
			onClick={ () => handleIconSelectButtonClick( icon ) }
		>
			<span className="block-editor-block-types-list__item-icon">
				<span className="block-editor-block-icon has-colors">
					<IconContent
						iconData={ {
							content: icon.content,
							icon,
							iconColor: iconColorValue,
							padding,
							size,
						} }
						type={ icon.type }
					/>
				</span>
			</span>
			<span className="block-editor-block-types-list__item-title">
				{ capitalize( icon.label || icon.name ) }
			</span>
		</Button>
	);
}

export default memo( IconSelector );
