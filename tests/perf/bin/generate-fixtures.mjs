#!/usr/bin/env node
/**
 * Generate deterministic SVG fixtures for the Blockparty Icons perf protocol.
 *
 * Two sets are produced:
 *   - theme-icons/  consumed by the fixture theme as a `folder` collection (local FS, like a VIP theme)
 *   - media-icons/  imported into the media library (remote FS on VIP, see the bpifs:// wrapper)
 *
 * Sizes follow a fixed ladder from ~10 KB to ~2 MB so that:
 *   - the aggregate of any collection blows past memcached's 1 MB per-item limit
 *   - a handful of *individual* icons also blow past it on their own
 *
 * Usage:
 *   node tests/perf/bin/generate-fixtures.mjs [--scale=full|small] [--out=DIR]
 */

import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const DEFAULT_OUT = resolve( HERE, '..', 'fixtures' );

const KB = 1024;
const MB = 1024 * 1024;

/**
 * Size ladder. Each bucket is [share, minBytes, maxBytes].
 * `share` values are relative weights within a set.
 */
const LADDER = [
	{ key: 'a', share: 0.55, min: 10 * KB, max: 50 * KB },
	{ key: 'b', share: 0.28, min: 50 * KB, max: 250 * KB },
	{ key: 'c', share: 0.12, min: 250 * KB, max: 1 * MB },
	{ key: 'd', share: 0.05, min: 1 * MB, max: 2 * MB },
];

const SCALES = {
	full: { theme: 200, media: 300 },
	small: { theme: 20, media: 30 },
};

/** Deterministic PRNG (mulberry32) so runs are byte-for-byte reproducible. */
function prng( seed ) {
	let a = seed >>> 0;
	return () => {
		a = ( a + 0x6d2b79f5 ) >>> 0;
		let t = a;
		t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
		t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );
		return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
	};
}

/** Assign a target byte size to every icon index, deterministically. */
function planSizes( count, rand ) {
	const plan = [];
	let assigned = 0;

	LADDER.forEach( ( bucket, i ) => {
		// Last bucket soaks up the rounding remainder.
		const n =
			i === LADDER.length - 1
				? count - assigned
				: Math.round( count * bucket.share );
		assigned += n;
		for ( let k = 0; k < n; k++ ) {
			plan.push( {
				bucket: bucket.key,
				bytes: Math.floor( bucket.min + rand() * ( bucket.max - bucket.min ) ),
			} );
		}
	} );

	// Interleave buckets so a paginated REST response (50 per page) sees a mix
	// of sizes rather than 50 tiny icons on page 1.
	const shuffled = [];
	const stride = Math.ceil( Math.sqrt( plan.length ) ) || 1;
	for ( let offset = 0; offset < stride; offset++ ) {
		for ( let i = offset; i < plan.length; i += stride ) {
			shuffled.push( plan[ i ] );
		}
	}

	return shuffled;
}

/**
 * Build a syntactically valid SVG padded with real path data up to `targetBytes`.
 *
 * Padding is genuine `<path d="...">` geometry rather than a comment blob, so that
 * strip_tags(), preg_match_all() and WP_HTML_Tag_Processor do representative work.
 */
function buildSvg( name, targetBytes, rand ) {
	const head =
		`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" ` +
		`role="img" aria-labelledby="t-${ name }">` +
		`<title id="t-${ name }">${ name }</title>` +
		`<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 3.5 2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7Z" fill="currentColor"/>`;
	const tail = `</svg>`;

	const parts = [ head ];
	let size = Buffer.byteLength( head ) + Buffer.byteLength( tail );

	// Each filler path is ~600-700 bytes of plausible curve data.
	while ( size < targetBytes ) {
		const coords = [];
		const segments = 24;
		let x = ( rand() * 24 ).toFixed( 2 );
		let y = ( rand() * 24 ).toFixed( 2 );
		coords.push( `M${ x } ${ y }` );
		for ( let s = 0; s < segments; s++ ) {
			const c1x = ( rand() * 24 ).toFixed( 2 );
			const c1y = ( rand() * 24 ).toFixed( 2 );
			const c2x = ( rand() * 24 ).toFixed( 2 );
			const c2y = ( rand() * 24 ).toFixed( 2 );
			x = ( rand() * 24 ).toFixed( 2 );
			y = ( rand() * 24 ).toFixed( 2 );
			coords.push( `C${ c1x } ${ c1y } ${ c2x } ${ c2y } ${ x } ${ y }` );
		}
		coords.push( 'Z' );

		const opacity = ( 0.1 + rand() * 0.9 ).toFixed( 3 );
		const piece = `<path d="${ coords.join( ' ' ) }" fill="currentColor" fill-opacity="${ opacity }"/>`;

		parts.push( piece );
		size += Buffer.byteLength( piece );
	}

	parts.push( tail );
	return parts.join( '' );
}

function generateSet( outDir, prefix, count, seed ) {
	rmSync( outDir, { recursive: true, force: true } );
	mkdirSync( outDir, { recursive: true } );

	const rand = prng( seed );
	const plan = planSizes( count, rand );
	const entries = [];

	plan.forEach( ( item, i ) => {
		const index = String( i + 1 ).padStart( 3, '0' );
		const name = `${ prefix }-${ index }`;
		const svg = buildSvg( name, item.bytes, rand );
		const bytes = Buffer.byteLength( svg );

		writeFileSync( resolve( outDir, `${ name }.svg` ), svg );
		entries.push( { name, file: `${ name }.svg`, bucket: item.bucket, bytes } );
	} );

	return entries;
}

function summarise( entries ) {
	const total = entries.reduce( ( acc, e ) => acc + e.bytes, 0 );
	const overLimit = entries.filter( ( e ) => e.bytes > MB );
	const byBucket = {};
	for ( const e of entries ) {
		byBucket[ e.bucket ] = byBucket[ e.bucket ] || { count: 0, bytes: 0 };
		byBucket[ e.bucket ].count++;
		byBucket[ e.bucket ].bytes += e.bytes;
	}
	return {
		count: entries.length,
		totalBytes: total,
		totalMB: +( total / MB ).toFixed( 2 ),
		minBytes: Math.min( ...entries.map( ( e ) => e.bytes ) ),
		maxBytes: Math.max( ...entries.map( ( e ) => e.bytes ) ),
		overOneMB: overLimit.length,
		byBucket,
	};
}

function main() {
	const args = Object.fromEntries(
		process.argv.slice( 2 ).map( ( a ) => {
			const [ k, v ] = a.replace( /^--/, '' ).split( '=' );
			return [ k, v ?? true ];
		} )
	);

	const scaleKey = args.scale === 'small' ? 'small' : 'full';
	const scale = SCALES[ scaleKey ];
	const out = args.out ? resolve( String( args.out ) ) : DEFAULT_OUT;

	mkdirSync( out, { recursive: true } );

	const theme = generateSet( resolve( out, 'theme-icons' ), 'theme-icon', scale.theme, 1337 );
	const media = generateSet( resolve( out, 'media-icons' ), 'media-icon', scale.media, 4242 );

	const manifest = {
		generatedAt: new Date().toISOString(),
		scale: scaleKey,
		ladder: LADDER,
		sets: {
			theme: { dir: 'theme-icons', ...summarise( theme ), entries: theme },
			media: { dir: 'media-icons', ...summarise( media ), entries: media },
		},
	};

	writeFileSync(
		resolve( out, 'manifest.json' ),
		JSON.stringify( manifest, null, '\t' )
	);

	const t = manifest.sets.theme;
	const m = manifest.sets.media;
	const fmt = ( s ) =>
		`${ s.count } icons, ${ s.totalMB } MB total, ${ ( s.minBytes / KB ).toFixed( 0 ) } KB → ` +
		`${ ( s.maxBytes / MB ).toFixed( 2 ) } MB, ${ s.overOneMB } over 1 MB`;

	process.stdout.write(
		`scale     : ${ scaleKey }\n` +
			`theme set : ${ fmt( t ) }\n` +
			`media set : ${ fmt( m ) }\n` +
			`total     : ${ ( ( t.totalBytes + m.totalBytes ) / MB ).toFixed( 2 ) } MB in ${ out }\n`
	);
}

main();
