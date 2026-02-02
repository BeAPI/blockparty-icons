/**
 * WordPress dependencies
 */
import { SVG } from '@wordpress/primitives';
import { getSpriteUrlWithHash, SvgComponent } from '../utils';

export default function IconContent( {
	iconData: { content, icon, iconColor, padding, size },
	type,
} ) {
	const style = {
		color: iconColor,
	};

	if ( padding ) {
		style.padding = `${ padding.top } ${ padding.right } ${ padding.bottom } ${ padding.left }`;
	}

	if ( type === 'raw' ) {
		return (
			<span className="wp-block-blockparty-icons__icon-component wp-block-blockparty-icons__icon-component--raw">
				<SvgComponent
					svgText={ content }
					size={ size }
					style={ style }
				/>
			</span>
		);
	}

	let href = content;

	if ( typeof href === 'string' && href.startsWith( '<use' ) ) {
		const tmp = document.createElement( 'svg' );
		tmp.insertAdjacentHTML( 'afterbegin', href );
		href = tmp.querySelector( 'use' ).getAttribute( 'href' );
	}

	if ( type === 'sprite' && typeof href === 'string' ) {
		href = getSpriteUrlWithHash( href );
	}

	return (
		<SVG
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			version="1.1"
			xmlns="http://www.w3.org/2000/svg"
			className={ `wp-block-blockparty-icons__icon-component wp-block-blockparty-icons__icon-component--sprite ${ icon.name }` }
			style={ style }
		>
			<use href={ href } />
		</SVG>
	);
}
