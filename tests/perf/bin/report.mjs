#!/usr/bin/env node
/**
 * Aggregate a perf log into a comparable report.
 *
 *   node tests/perf/bin/report.mjs --label=baseline
 *   node tests/perf/bin/report.mjs --label=after --compare=baseline
 *
 * Reads tests/perf/results/<label>.log (one JSON object per request) and writes
 * tests/perf/results/<label>.json, then prints a table.
 *
 * The first sample of each scenario is dropped as a warm-up; the rest are reduced
 * to a median so a single slow container hiccup cannot move the number.
 */

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const RESULTS = resolve( HERE, '..', 'results' );

/** Metrics that describe work done. Medians are reported for each. */
const METRICS = [
	{ key: 'items', label: 'icons', unit: '' },
	{ key: 'response_kb', label: 'response KB', unit: '' },
	{ key: 'resident_mb', label: 'resident', unit: 'MB' },
	{ key: 'peak_mem_mb', label: 'peak mem', unit: 'MB' },
	{ key: 'fs_reads', label: 'media reads', unit: '' },
	{ key: 'fs_mb', label: 'media read', unit: 'MB' },
	{ key: 'icons_oc_get', label: 'cache get', unit: '' },
	{ key: 'icons_oc_miss', label: 'cache miss', unit: '' },
	{ key: 'icons_oc_rejected', label: 'set >1MB refused', unit: '' },
	{ key: 'hook_ms', label: 'init hook', unit: 'ms' },
	{ key: 'wall_ms', label: 'wall', unit: 'ms' },
];

function median( values ) {
	const nums = values.filter( ( v ) => typeof v === 'number' && !Number.isNaN( v ) );
	if ( nums.length === 0 ) {
		return null;
	}
	const sorted = [ ...nums ].sort( ( a, b ) => a - b );
	const mid = Math.floor( sorted.length / 2 );
	const value =
		sorted.length % 2 === 0 ? ( sorted[ mid - 1 ] + sorted[ mid ] ) / 2 : sorted[ mid ];

	return Math.round( value * 1000 ) / 1000;
}

/** Response sizes are captured by curl, not by the in-PHP probe. */
function loadSizes( label ) {
	const path = resolve( RESULTS, `${ label }.sizes` );
	const sizes = {};
	if ( ! existsSync( path ) ) {
		return sizes;
	}
	for ( const line of readFileSync( path, 'utf8' ).split( '\n' ) ) {
		if ( ! line.trim() ) {
			continue;
		}
		const [ scenario, bytes ] = line.split( '\t' );
		sizes[ scenario ] = Math.round( Number( bytes ) / 1024 );
	}
	return sizes;
}

function load( label ) {
	const path = resolve( RESULTS, `${ label }.log` );
	if ( ! existsSync( path ) ) {
		throw new Error( `No log at ${ path }` );
	}

	const rows = readFileSync( path, 'utf8' )
		.split( '\n' )
		.filter( Boolean )
		.map( ( line ) => {
			try {
				return JSON.parse( line );
			} catch {
				return null;
			}
		} )
		.filter( Boolean );

	const byScenario = new Map();
	for ( const row of rows ) {
		const key = row.label || 'unlabelled';
		if ( ! byScenario.has( key ) ) {
			byScenario.set( key, [] );
		}
		byScenario.get( key ).push( row );
	}

	const sizes = loadSizes( label );
	const summary = {};
	for ( const [ scenario, samples ] of byScenario ) {
		// Drop the warm-up sample when we have more than one.
		const used = samples.length > 1 ? samples.slice( 1 ) : samples;

		const entry = {
			samples: used.length,
			ctx: used[ 0 ]?.ctx ?? '',
			response_kb: sizes[ scenario ] ?? null,
		};
		for ( const { key } of METRICS ) {
			if ( key === 'response_kb' ) {
				continue; // Comes from curl, not from the probe.
			}
			entry[ key ] = median( used.map( ( s ) => s[ key ] ) );
		}

		// Carry the set of oversized cache keys, deduplicated.
		const rejected = new Set();
		for ( const s of used ) {
			for ( const k of Object.keys( s.rejected_keys || {} ) ) {
				rejected.add( k );
			}
		}
		if ( rejected.size ) {
			entry.rejected_keys = [ ...rejected ].sort();
		}

		summary[ scenario ] = entry;
	}

	return summary;
}

function fmt( value, width ) {
	const s = value === null || value === undefined ? '—' : String( value );
	return s.padStart( width );
}

function printTable( summary, compare ) {
	const scenarios = Object.keys( summary );
	const nameWidth = Math.max( 20, ...scenarios.map( ( s ) => s.length ) );

	const cols = METRICS.map( ( m ) => ( {
		...m,
		width: Math.max( m.label.length, 9 ),
	} ) );

	const header =
		'scenario'.padEnd( nameWidth ) + '  ' + cols.map( ( c ) => fmt( c.label, c.width ) ).join( '  ' );
	console.log( '\n' + header );
	console.log( '-'.repeat( header.length ) );

	for ( const scenario of scenarios ) {
		const row = summary[ scenario ];
		const cells = cols.map( ( c ) => {
			const now = row[ c.key ];
			if ( ! compare || ! compare[ scenario ] ) {
				return fmt( now, c.width );
			}

			const before = compare[ scenario ][ c.key ];
			if ( before === null || now === null || before === now ) {
				return fmt( now, c.width );
			}

			const delta = before === 0 ? null : Math.round( ( ( now - before ) / before ) * 100 );
			const suffix = delta === null ? '' : ` (${ delta > 0 ? '+' : '' }${ delta }%)`;

			return fmt( `${ now }${ suffix }`, c.width );
		} );

		console.log( scenario.padEnd( nameWidth ) + '  ' + cells.join( '  ' ) );
	}

	const rejecting = scenarios.filter( ( s ) => ( summary[ s ].icons_oc_rejected || 0 ) > 0 );
	if ( rejecting.length ) {
		console.log(
			`\nCache entries refused for exceeding 1 MB (these never warm up, so the cost is paid on every request):`
		);
		const keys = new Set();
		for ( const s of rejecting ) {
			for ( const k of summary[ s ].rejected_keys || [] ) {
				keys.add( k );
			}
		}
		for ( const k of [ ...keys ].sort().slice( 0, 12 ) ) {
			console.log( `  ${ k }` );
		}
		if ( keys.size > 12 ) {
			console.log( `  … and ${ keys.size - 12 } more` );
		}
	}
}

function main() {
	const args = Object.fromEntries(
		process.argv.slice( 2 ).map( ( a ) => {
			const [ k, v ] = a.replace( /^--/, '' ).split( '=' );
			return [ k, v ?? true ];
		} )
	);

	if ( ! args.label ) {
		console.error( '--label is required' );
		process.exit( 1 );
	}

	const summary = load( String( args.label ) );
	writeFileSync(
		resolve( RESULTS, `${ args.label }.json` ),
		JSON.stringify( summary, null, '\t' )
	);

	let compare = null;
	if ( args.compare ) {
		const path = resolve( RESULTS, `${ args.compare }.json` );
		if ( existsSync( path ) ) {
			compare = JSON.parse( readFileSync( path, 'utf8' ) );
			console.log( `\ncomparing "${ args.label }" against "${ args.compare }"` );
		} else {
			console.error( `no baseline at ${ path }, showing absolute numbers` );
		}
	}

	printTable( summary, compare );
	console.log( `\nwritten: tests/perf/results/${ args.label }.json` );
}

main();
