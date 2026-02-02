/**
 * Deprecated versions for blockparty/icon.
 *
 * v3: block saved with full markup inside <li> (inner HTML).
 * Migrates to v4 (attributes content + icon, save outputs from attributes).
 */

/**
 * Deprecated v3: <li> with inner HTML (span + svg).
 * Ensures validation passes and migrates to v4 attributes.
 */
const v3 = {
	attributes: {
		innerHTML: {
			type: 'string',
			source: 'html',
			selector: 'li',
		},
	},
	supports: {
		html: false,
	},
	isEligible( attributes ) {
		// Old format has full markup in <li>; when parsed with current schema, content/icon are missing.
		return ! attributes.content || ! attributes.icon;
	},
	save( { attributes } ) {
		const html = attributes.innerHTML || '';
		return (
			<li
				className="wp-block-blockparty-icon"
				dangerouslySetInnerHTML={ { __html: html } }
			/>
		);
	},
	migrate( attributes ) {
		const html = attributes.innerHTML || '';
		const migrated = {
			borderRadius: 0,
			className: '',
			content: '',
			icon: null,
			iconColorValue: '',
			label: '',
			padding: undefined,
			rel: '',
			size: 24,
			text: '',
			url: '',
		};

		// Parse use href (sprite URL) and icon name from SVG class (e.g. icon-wheelchair).
		const useMatch = html.match( /<use\s+href=["']([^"']+)["']/i );
		if ( useMatch ) {
			migrated.content = useMatch[ 1 ];
		}

		const svgClassMatch = html.match( /class=["'][^"']*\bicon-(\S+)\b/ );
		if ( svgClassMatch ) {
			const name = svgClassMatch[ 1 ].replace( /\s.*$/, '' );
			const label = name
				.split( '-' )
				.map(
					( part ) => part.charAt( 0 ).toUpperCase() + part.slice( 1 )
				)
				.join( ' ' );
			migrated.icon = {
				label,
				name,
				type: 'sprite',
			};
		}

		// If we have content but no icon from class, still set a minimal icon for sprite.
		if ( migrated.content && ! migrated.icon ) {
			const hashPart = migrated.content.split( '#' )[ 1 ] || '';
			const name = hashPart || 'icon';
			const label = name
				.split( '-' )
				.map(
					( part ) => part.charAt( 0 ).toUpperCase() + part.slice( 1 )
				)
				.join( ' ' );
			migrated.icon = {
				label,
				name: name || 'icon',
				type: 'sprite',
			};
		}

		return migrated;
	},
};

const deprecated = [ v3 ];

export default deprecated;
