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
 * Cache configuration
 */
const CACHE_CONFIG = {
	STORAGE_KEY: '@beapi/icons-block-cache',
	TTL: 30 * 60 * 1000, // 30 minutes in milliseconds
};

/**
 * Cache utilities
 */

/**
 * Get cache key for API requests
 *
 * @param {string} endpoint The API endpoint
 * @param {Object} args     Query arguments
 * @return {string} Cache key
 */
function getCacheKey( endpoint, args = {} ) {
	const argsString = JSON.stringify( args );
	return `${ endpoint }_${ argsString }`;
}

/**
 * Get cached data from sessionStorage
 *
 * @param {string} key Cache key
 * @return {object|null} Cached data or null if not found/expired
 */
function getCachedData( key ) {
	try {
		if ( typeof window === 'undefined' || ! window.sessionStorage ) {
			return null;
		}

		const cached = window.sessionStorage.getItem( key );
		if ( ! cached ) {
			return null;
		}

		const { data, timestamp } = JSON.parse( cached );
		const now = Date.now();

		// Check if cache is expired
		if ( now - timestamp > CACHE_CONFIG.TTL ) {
			window.sessionStorage.removeItem( key );
			return null;
		}

		return data;
	} catch ( error ) {
		// Silently handle cache read errors
		return null;
	}
}

/**
 * Set data in sessionStorage cache
 *
 * @param {string} key  Cache key
 * @param {any}    data Data to cache
 */
function setCachedData( key, data ) {
	try {
		if ( typeof window === 'undefined' || ! window.sessionStorage ) {
			return;
		}

		const cacheData = {
			data,
			timestamp: Date.now(),
		};

		window.sessionStorage.setItem( key, JSON.stringify( cacheData ) );
	} catch ( error ) {
		// Silently handle cache write errors
	}
}

/**
 * Clear all cached data
 */
export function clearIconCache() {
	try {
		if ( typeof window === 'undefined' || ! window.sessionStorage ) {
			return;
		}

		const keys = Object.keys( window.sessionStorage );
		keys.forEach( ( key ) => {
			if ( key.startsWith( CACHE_CONFIG.STORAGE_KEY ) ) {
				window.sessionStorage.removeItem( key );
			}
		} );
	} catch ( error ) {
		// Silently handle cache clear errors
	}
}

/**
 * Get cache statistics
 *
 * @return {Object} Cache statistics
 */
export function getCacheStats() {
	try {
		if ( typeof window === 'undefined' || ! window.sessionStorage ) {
			return { total: 0, expired: 0, valid: 0 };
		}

		const keys = Object.keys( window.sessionStorage );
		const cacheKeys = keys.filter( ( key ) =>
			key.startsWith( CACHE_CONFIG.STORAGE_KEY )
		);

		let expired = 0;
		let valid = 0;
		const now = Date.now();

		cacheKeys.forEach( ( key ) => {
			try {
				const cached = window.sessionStorage.getItem( key );
				if ( cached ) {
					const { timestamp } = JSON.parse( cached );
					if ( now - timestamp > CACHE_CONFIG.TTL ) {
						expired++;
					} else {
						valid++;
					}
				}
			} catch ( error ) {
				expired++;
			}
		} );

		return {
			total: cacheKeys.length,
			expired,
			valid,
		};
	} catch ( error ) {
		// Silently handle cache stats errors
		return { total: 0, expired: 0, valid: 0 };
	}
}

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
 * Get icons collections with cache
 *
 * @param {Object} args Query arguments
 * @return {Promise<Object>} Promise resolving to collections object
 */
export async function getCollections( args = {} ) {
	const cacheKey = getCacheKey( 'collections', args );

	// Try to get from cache first
	const cached = getCachedData( cacheKey );
	if ( cached ) {
		return cached;
	}

	// If not in cache, fetch from API
	const path = addQueryArgs( 'icons/v1/collections', args );
	const collections = await apiFetch( { path } );

	// Store in cache
	setCachedData( cacheKey, collections );

	return collections;
}

/**
 * Get icon data with cache
 *
 * @param {string} collection icon collection
 * @param {Object} args       Query arguments
 * @return {Promise<{data: object, headers: object}>} Promise resolving to icon data with pagination headers
 */
export async function getIcons( collection, args = {} ) {
	const cacheKey = getCacheKey( `icons_${ collection }`, args );

	// Try to get from cache first
	const cached = getCachedData( cacheKey );
	if ( cached ) {
		return cached;
	}

	// If not in cache, fetch from API
	const pathBase = `icons/v1/${ collection }`;

	// Use fetch to access response headers for pagination info
	// WordPress REST API settings
	const wpApiSettings = window.wpApiSettings || {};
	const restUrl = wpApiSettings.root || '/wp-json/';
	const restNonce = wpApiSettings.nonce || '';

	// With index.php?rest_route= (e.g. WP Env), query params must use & not ?
	// so we get ?rest_route=/icons/v1/Bootstrap&context=edit&per_page=50
	const isRestRouteFormat = restUrl.includes( 'rest_route' );
	const path = isRestRouteFormat ? pathBase : addQueryArgs( pathBase, args );
	const queryString = isRestRouteFormat
		? addQueryArgs( '', args ).replace( /^\?/, '' )
		: '';
	const url =
		restUrl.replace( /\/$/, '' ) +
		'/' +
		path.replace( /^\//, '' ) +
		( queryString ? '&' + queryString : '' );

	const response = await fetch( url, {
		method: 'GET',
		headers: {
			'X-WP-Nonce': restNonce,
			'Content-Type': 'application/json',
		},
		credentials: 'include',
	} );

	if ( ! response.ok ) {
		throw new Error( `API request failed: ${ response.status }` );
	}

	const icons = await response.json();

	// Extract pagination headers
	const headers = {
		total: parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 ),
		totalPages: parseInt(
			response.headers.get( 'X-WP-TotalPages' ) || '0',
			10
		),
	};

	const result = {
		data: icons,
		headers,
	};

	// Store in cache
	setCachedData( cacheKey, result );

	return result;
}

/**
 * Get all icon data with cache
 *
 * @param {Object} args Query arguments
 * @return {Promise<Array>} Promise resolving to array of all icons
 */
export async function getAllIcons( args = {} ) {
	const cacheKey = getCacheKey( 'all_icons', args );

	// Try to get from cache first
	const cached = getCachedData( cacheKey );
	if ( cached ) {
		return cached;
	}

	// If not in cache, fetch from API
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

	const flatIcons = icons.flat();

	// Store in cache
	setCachedData( cacheKey, flatIcons );

	return flatIcons;
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
