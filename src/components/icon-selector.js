/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';

import IconContent from './icon-content';
import { capitalize } from '../utils';

export default function IconSelector( {
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
				{ capitalize( icon.label ) }
			</span>
		</Button>
	);
}
