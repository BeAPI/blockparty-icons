import { createElement } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Capitalize string and replace all hyphens with blank space
 *
 * @param {string} str source
 *
 * @return {string} Capitalized string with hyphens replaced by spaces
 */
export function capitalize( str ) {
	if ( str === undefined || str === null ) {
		return __( 'No name icon', 'blockparty-icons' );
	}

	const s = String( str );
	if ( ! s.length ) {
		return s;
	}

	return ( s[ 0 ].toUpperCase() + s.slice( 1 ) ).replaceAll( '-', ' ' );
}

/**
 * In-memory API cache (one entry per page load).
 * Stored on window so all callers share the same cache until page reload.
 * In-flight requests are deduplicated: same key reuses the same promise until resolved.
 */
const CACHE_NS = 'blockpartyIconsApiCache';
const IN_FLIGHT_NS = 'blockpartyIconsApiCacheInFlight';

function getCache() {
	if ( typeof window === 'undefined' ) {
		return null;
	}
	if ( ! window[ CACHE_NS ] ) {
		window[ CACHE_NS ] = Object.create( null );
	}
	return window[ CACHE_NS ];
}

function getInFlight() {
	if ( typeof window === 'undefined' ) {
		return null;
	}
	if ( ! window[ IN_FLIGHT_NS ] ) {
		window[ IN_FLIGHT_NS ] = Object.create( null );
	}
	return window[ IN_FLIGHT_NS ];
}

function cacheKey( prefix, ...parts ) {
	return (
		prefix +
		'_' +
		parts.map( ( p ) => JSON.stringify( p ?? {} ) ).join( '_' )
	);
}

/**
 * Get icons collections (cached per page load, requests deduplicated)
 *
 * @param {Object} args Query arguments
 * @return {Promise<Object>} Promise resolving to collections object
 */
export async function getCollections( args = {} ) {
	const cache = getCache();
	const key = cacheKey( 'collections', args );

	if ( cache && key in cache ) {
		return cache[ key ];
	}

	const inFlight = getInFlight();
	if ( inFlight && key in inFlight ) {
		return inFlight[ key ];
	}

	const promise = apiFetch( {
		path: addQueryArgs( '/icons/v1/collections', args ),
	} )
		.then( ( data ) => {
			if ( cache ) {
				cache[ key ] = data;
			}
			if ( inFlight && key in inFlight ) {
				delete inFlight[ key ];
			}
			return data;
		} )
		.catch( ( err ) => {
			if ( inFlight && key in inFlight ) {
				delete inFlight[ key ];
			}
			throw err;
		} );

	if ( inFlight ) {
		inFlight[ key ] = promise;
	}
	return promise;
}

/**
 * Get icon data (cached per page load, requests deduplicated)
 *
 * @param {string} collection icon collection
 * @param {Object} args       Query arguments
 * @return {Promise<{data: object, headers: object}>} Promise resolving to icon data with pagination headers
 */
export async function getIcons( collection, args = {} ) {
	const cache = getCache();
	const key = cacheKey( 'icons', collection, args );

	if ( cache && key in cache ) {
		return cache[ key ];
	}

	const inFlight = getInFlight();
	if ( inFlight && key in inFlight ) {
		return inFlight[ key ];
	}

	const promise = apiFetch( {
		path: addQueryArgs( `/icons/v1/${ collection }`, args ),
		parse: false,
	} )
		.then( ( response ) => {
			if ( ! response.ok ) {
				throw new Error( `API request failed: ${ response.status }` );
			}
			return response;
		} )
		.then( async ( response ) => {
			const icons = await response.json();
			const headers = {
				total: parseInt(
					response.headers.get( 'X-WP-Total' ) || '0',
					10
				),
				totalPages: parseInt(
					response.headers.get( 'X-WP-TotalPages' ) || '0',
					10
				),
			};
			return { data: icons, headers };
		} )
		.then( ( result ) => {
			if ( cache ) {
				cache[ key ] = result;
			}
			if ( inFlight && key in inFlight ) {
				delete inFlight[ key ];
			}
			return result;
		} )
		.catch( ( err ) => {
			if ( inFlight && key in inFlight ) {
				delete inFlight[ key ];
			}
			throw err;
		} );

	if ( inFlight ) {
		inFlight[ key ] = promise;
	}
	return promise;
}

/**
 * Get all icon data
 *
 * @param {Object} args Query arguments
 * @return {Promise<Array>} Promise resolving to array of all icons
 */
export async function getAllIcons( args = {} ) {
	const collections = await getCollections( args );
	const icons = [];

	for ( const key of Object.keys( collections ) ) {
		const collection = collections[ key ];
		const result = await getIcons( collection.name, args );
		const nextIcons = result.data || result; // Handle both old and new format

		icons.push(
			Object.keys( nextIcons ).map( ( icon ) => nextIcons[ icon ] )
		);
	}

	return icons.flat();
}

/**
 * Marker attribute that can be set on the root <svg> to opt into keeping its
 * own styling without passing a prop. Accepted tokens (space/comma separated):
 * `style`, `class`, `all`. Example: <svg data-blockparty-preserve="style class">.
 * The attribute is kept on the rendered <svg> output.
 */
export const PRESERVE_ATTRIBUTE = 'data-blockparty-preserve';

/**
 * Convert an SVG string into a DOM element.
 *
 * Parsing through the HTML parser keeps the foreign-content adjustments for
 * SVG, so attribute casing (viewBox, gradientUnits, ...) and tag casing
 * (linearGradient, radialGradient, ...) are preserved.
 *
 * Uses querySelector('svg') rather than firstChild/firstElementChild so markup
 * that does not start with the <svg> element (e.g. a leading HTML comment or
 * XML/doctype prologue) still resolves to the actual root SVG node.
 *
 * @param {string} svgText Raw SVG markup.
 * @return {Element|null} The root SVG element, or null when the markup is empty/invalid.
 */
export function svgTextToElement( svgText ) {
	if ( typeof document === 'undefined' || typeof svgText !== 'string' ) {
		return null;
	}

	const div = document.createElement( 'div' );
	div.innerHTML = svgText.trim();

	return div.querySelector( 'svg' );
}

/**
 * Parse an inline `style` attribute string into a React style object.
 *
 * @param {string} styleString The raw value of a `style` attribute.
 * @return {Object} A React-compatible style object.
 */
function parseStyleString( styleString ) {
	const styleObject = {};

	if ( typeof styleString !== 'string' ) {
		return styleObject;
	}

	styleString.split( ';' ).forEach( ( declaration ) => {
		const [ rawProperty, ...rawValue ] = declaration.split( ':' );
		const property = rawProperty.trim();
		const value = rawValue.join( ':' ).trim();

		if ( ! property || ! value ) {
			return;
		}

		// Keep CSS custom properties as-is, camelCase the rest for React.
		const reactProperty = property.startsWith( '--' )
			? property
			: property.replace( /-([a-z])/g, ( _, letter ) =>
					letter.toUpperCase()
			  );

		styleObject[ reactProperty ] = value;
	} );

	return styleObject;
}

/**
 * Convert a DOM attribute name to its React-compatible counterpart.
 *
 * React expects presentation attributes in camelCase (`fill-rule` -> `fillRule`,
 * `clip-path` -> `clipPath`) and namespaced ones too (`xlink:href` ->
 * `xlinkHref`). `data-*` and `aria-*` attributes must keep their hyphenated
 * form, and `class` maps to `className`.
 *
 * @param {string} name Raw DOM attribute name.
 * @return {string} The React-compatible attribute name.
 */
function reactAttributeName( name ) {
	if ( name === 'class' ) {
		return 'className';
	}

	// React keeps data-* and aria-* attributes in their hyphenated form.
	if ( name.startsWith( 'data-' ) || name.startsWith( 'aria-' ) ) {
		return name;
	}

	return name.replace( /[-:]([a-z])/g, ( _, letter ) =>
		letter.toUpperCase()
	);
}

/**
 * Render an SVG string as React elements, preserving the original tag nesting.
 *
 * Per-SVG opt-in: the root <svg> may carry a marker attribute to enable the
 * same behaviour without a prop, e.g. `data-blockparty-preserve="style class"`.
 * Accepted tokens (space/comma separated): `style`, `class`, `all`.
 *
 * @param {Object}  props                Props object
 * @param {string}  props.svgText        Raw SVG markup.
 * @param {number}  props.size           Width/height applied to the root <svg> (ignored when null).
 * @param {Object}  props.style          Inline style merged onto the root <svg> (e.g. from Gutenberg).
 * @param {boolean} props.allowStyling   When true, keep inline `style` attributes and inline `<style>` tags (required for SVGs that carry their own styling, e.g. media library uploads). `id` attributes are always kept so internal `url(#…)` references resolve.
 * @param {boolean} props.allowClassName When true, keep `class` attributes (mapped to `className`).
 * @param {boolean} props.preview        When true, mark the rendered root <svg> with `data-blockparty-preserve="preview"` so preview styles do not recolor it (e.g. media library previews).
 */
export const SvgComponent = ( {
	svgText,
	size,
	style,
	allowStyling = false,
	allowClassName = false,
	preview = false,
} ) => {
	const svgElement = svgTextToElement( svgText );

	if ( ! svgElement ) {
		return null;
	}

	// Per-SVG opt-in via a marker attribute on the root <svg>.
	const preserveTokens = (
		svgElement.getAttribute( PRESERVE_ATTRIBUTE ) || ''
	)
		.toLowerCase()
		.split( /[\s,]+/ )
		.filter( Boolean );
	const preserveAll = preserveTokens.includes( 'all' );
	const effectiveAllowStyling =
		allowStyling || preserveAll || preserveTokens.includes( 'style' );
	const effectiveAllowClassName =
		allowClassName || preserveAll || preserveTokens.includes( 'class' );

	const disallowedAttributes = [ 'class', 'style' ].filter( ( attribute ) => {
		if ( attribute === 'class' ) {
			return ! effectiveAllowClassName;
		}
		// Only `style` is gated by allowStyling. `id` is always kept because
		// internal `url(#…)` references (gradients, clip paths, filters) rely
		// on it to resolve, matching the behaviour of the previous parser.
		return ! effectiveAllowStyling;
	} );
	const disallowedTags = effectiveAllowStyling
		? [ 'script' ]
		: [ 'style', 'script' ];

	// Recursively walk the DOM tree so the original nesting of the SVG tags
	// (defs > linearGradient > stop, g > path, ...) is preserved.
	const traverse = ( element, id = 0 ) => {
		const tagName = element.tagName;

		if ( disallowedTags.includes( tagName ) ) {
			return null;
		}

		const attributes = { key: `icon-${ id }` };
		for ( const attribute of element.attributes ) {
			if ( disallowedAttributes.includes( attribute.name ) ) {
				continue;
			}
			if ( attribute.name === 'style' ) {
				attributes.style = parseStyleString( attribute.value );
				continue;
			}
			attributes[ reactAttributeName( attribute.name ) ] =
				attribute.value;
		}

		// <style> tags carry their CSS as text content, which is required for
		// SVGs that rely on internal CSS classes (kept only when allowStyling).
		if ( tagName === 'style' ) {
			attributes.dangerouslySetInnerHTML = {
				__html: element.textContent,
			};
			return createElement( tagName, attributes );
		}

		let children = [];
		if ( element.children.length > 0 ) {
			children = Array.from( element.children )
				.map( ( child, index ) =>
					traverse( child, `${ id }-${ index }` )
				)
				.filter( Boolean );
		}

		// On the root <svg>, apply the dimensions and inline style coming from Gutenberg.
		if ( tagName === 'svg' ) {
			if ( size !== null ) {
				attributes.width = size;
				attributes.height = size;
			}
			attributes.viewBox =
				element.getAttribute( 'viewBox' ) || '0 0 24 24';
			attributes.style = { ...attributes.style, ...style };

			// Mark previews so the recolor styles skip them (the SVG keeps its own colors).
			if ( preview ) {
				const existingPreserve = attributes[ PRESERVE_ATTRIBUTE ];
				attributes[ PRESERVE_ATTRIBUTE ] = existingPreserve
					? `${ existingPreserve } preview`
					: 'preview';
			}
		}

		return createElement( tagName, attributes, children );
	};

	return traverse( svgElement );
};

/**
 * Update icons localStorage
 *
 * @typedef {Object} IconObject
 * @property {string}     content Icon SVG content
 * @property {string}     label   Icon label
 * @property {string}     name    Icon name
 * @property {string}     type    Icon type
 *
 * @param    {IconObject} icon    Icon object to store
 */
export function setStoredIcons( icon ) {
	const STORAGE_KEY = `@beapi/icons-block`;
	const storeIcons = JSON.parse(
		window.localStorage.getItem( STORAGE_KEY ) || '[]'
	);

	if ( ! storeIcons.some( ( i ) => i.name === icon.name ) ) {
		storeIcons.unshift( icon );
	}

	if ( storeIcons.length > 9 ) {
		storeIcons.pop();
	}

	window.localStorage.setItem( STORAGE_KEY, JSON.stringify( storeIcons ) );
}

/**
 * Get icons localStorage
 *
 * @return {IconObject[]} Array of stored icon objects
 */
export function getStoredIcons() {
	try {
		if ( typeof window === 'undefined' || ! window.localStorage ) {
			return [];
		}
		const storedData = window.localStorage.getItem( `@beapi/icons-block` );
		if ( ! storedData ) {
			return [];
		}
		const parsedData = JSON.parse( storedData );
		return Array.isArray( parsedData ) ? parsedData : [];
	} catch ( error ) {
		return [];
	}
}

/**
 * Append cache-busting hash to sprite URL when sprite hashes are available (from PHP).
 * Used in the icon selector/modal so previews use the same cache-busted URLs as the front.
 *
 * @param {string} spriteUrl Full sprite URL (e.g. https://example.com/.../dist/icons/social.svg#icon-id).
 * @return {string} URL with ?v=hash if hash found, unchanged otherwise.
 */
export function getSpriteUrlWithHash( spriteUrl ) {
	const hashes =
		typeof window !== 'undefined' &&
		window.blockpartyIconsConfig?.spriteHashes;
	if ( ! hashes || typeof spriteUrl !== 'string' ) {
		return spriteUrl;
	}
	try {
		const url = new URL( spriteUrl, window.location.origin );
		const path = url.pathname || '';
		// Match JSON keys like "icons/social.svg" - path segment after "dist/".
		const distMatch = path.match( /\/dist\/(.+)$/ );
		const key = distMatch
			? distMatch[ 1 ]
			: path.split( '/' ).filter( Boolean ).slice( -2 ).join( '/' );
		const hash = hashes[ key ];
		if ( ! hash ) {
			return spriteUrl;
		}
		url.searchParams.set( 'v', hash );
		return url.toString();
	} catch {
		return spriteUrl;
	}
}

/**
 * Add default unit to a value if it's not a number
 *
 * @param {number | string} value Value to normalize.
 * @param {string}          unit  CSS unit suffix.
 * @return {string} Value with unit when needed.
 */
export function addDefaultUnit( value, unit = 'px' ) {
	if ( value === undefined || value === null || value === '' ) {
		return '';
	}

	if ( isNumber( value ) ) {
		return `${ value }${ unit }`;
	}

	// get the last char and check if it's a number
	const lastChar = value.charAt( value.length - 1 );

	if ( isNumber( lastChar ) ) {
		return `${ value }${ unit }`;
	}

	return value;
}

/**
 * Check if a value is a number
 *
 * @param {*} value Value to test.
 * @return {boolean} Whether the value is numeric.
 */
export function isNumber( value ) {
	return ! isNaN( parseFloat( value ) ) && isFinite( value );
}
