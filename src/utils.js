import {
	Circle,
	G,
	Line,
	Path,
	Polygon,
	Rect,
	Defs,
	RadialGradient,
	LinearGradient,
	Stop,
	SVG,
} from '@wordpress/primitives';
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

export const SvgComponent = ( { svgText, size, style } ) => {
	const parseSvg = ( svgTextContent ) => {
		const regex = /<([a-z]+)([^>]*)\/?>/g;
		const matches = svgTextContent.matchAll( regex );
		let svgAttributes = {};
		const svgElements = Array.from( matches, ( match, index ) => {
			const [ , tagName, attributes ] = match;
			const parsedAttributes = (
				attributes.match( /([a-zA-Z][a-zA-Z-]*)="([^"]*)"/g ) || []
			)
				.filter(
					( attr ) =>
						! attr.includes( 'style' ) && ! attr.includes( 'class' )
				)
				.map( ( attr ) => {
					const [ name, value ] = attr.split( '=' );

					return {
						name:
							name.includes( '-' ) && ! name.includes( 'aria' )
								? name.replace( /-([a-z])/g, ( _, letter ) =>
										letter.toUpperCase()
								  )
								: name,
						value: value.slice( 1, -1 ),
					};
				} )
				.reduce(
					( acc, { name, value } ) => ( { ...acc, [ name ]: value } ),
					{}
				);

			if ( tagName.toLowerCase() === 'svg' ) {
				// Si la balise est <svg>, conserve ses attributs pour l'utiliser comme attributs de la balise <svg> parente
				svgAttributes = parsedAttributes;
				return null; // Ignore la balise <svg> en tant que composant React
			}

			// Convertissons les balises en composants React
			const DynamicComponent = ( {
				tagName: componentTagName,
				...props
			} ) => {
				switch ( componentTagName ) {
					case 'circle':
						return <Circle { ...props } />;
					case 'g':
						return <G { ...props } />;
					case 'line':
						return <Line { ...props } />;
					case 'path':
						return <Path { ...props } />;
					case 'polygon':
						return <Polygon { ...props } />;
					case 'rect':
						return <Rect { ...props } />;
					case 'defs':
						return <Defs { ...props } />;
					case 'radialGradient':
						return <RadialGradient { ...props } />;
					case 'linearGradient':
						return <LinearGradient { ...props } />;
					case 'stop':
						return <Stop { ...props } />;
				}
			};

			return (
				<DynamicComponent
					key={ index }
					tagName={ tagName }
					{ ...parsedAttributes }
				/>
			);
		} );

		return { svgAttributes, svgElements };
	};

	const { svgAttributes, svgElements } = parseSvg( svgText );

	svgAttributes.style = { ...svgAttributes.style, ...style };
	if ( size !== null ) {
		svgAttributes.width = size;
		svgAttributes.height = size;
	}
	svgAttributes.viewBox = svgAttributes.viewBox || '0 0 24 24';

	return <SVG { ...svgAttributes }>{ svgElements }</SVG>;
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
