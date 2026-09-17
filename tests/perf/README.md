# Blockparty Icons — performance protocol

A reproducible local rig for the WordPress VIP performance problem: a site with
500+ SVG icons, some larger than 1 MB, where the plugin loads every icon's content
into memory on every request — front end included, whether or not any icon is used.

The rig exists to produce **comparable before/after numbers**, so that a fix can be
shown to work rather than asserted to.

---

## What it builds

An isolated WordPress instance on <http://localhost:8899> (the normal dev env on
8888 is untouched and can run at the same time):

| | |
|---|---|
| `perf-theme` | 200 SVG icons in a theme folder, registered with `type => folder` |
| `perf-media` | 300 SVG icons contributed through the back office (media library) |
| icon sizes | ~11 KB → ~1.95 MB, following a fixed ladder; **25 icons exceed 1 MB** |
| total payload | ~102 MB of SVG |
| object cache | persistent, and refuses any item over 1 MB — like VIP's memcached |
| pages | `/perf-empty/` (no icon), `/perf-single/` (1 icon), `/perf-many/` (20 icons) |

The two collections deliberately mirror the VIP topology: theme files are local,
media-library files are remote. `get_attached_file()` is routed through a `bpifs://`
stream wrapper that counts every open and can charge artificial latency.

---

## Prerequisites

- Docker running
- Node 22 (`volta` pins it)
- `composer` on the PATH — **the plugin resolves its classes through Composer's
  PSR-4 autoloader**, so without `vendor/` nothing loads

## Usage

```bash
# One-off: generate fixtures, boot the env, import 300 icons into the media library.
# Takes several minutes, mostly the media import.
npm run perf:setup

# Capture a run. --label names the result set.
npm run perf:bench -- --label=baseline

# After changing the plugin, capture again and diff against the baseline.
npm run perf:bench -- --label=after
node tests/perf/bin/report.mjs --label=after --compare=baseline

# Tear down (removes containers and the database, keeps the fixtures).
npm run perf:teardown
```

Useful flags:

| flag | effect |
|---|---|
| `--scale=small` (setup) | 50 icons instead of 500, for quick iteration on the rig itself |
| `--skip-fixtures` (setup) | reuse the SVGs already generated |
| `--runs=N` (bench) | samples per scenario, default 5; the first is dropped as warm-up |
| `--latency-us=2000` (bench) | charge 2 ms per media-library file open, emulating VIP Files |

---

## Metrics

The probe (`mu-plugins/bpi-perf-probe.php`) writes one JSON record per request.
`report.mjs` reduces each scenario to a **median** so one container hiccup cannot
move a number.

| column | meaning |
|---|---|
| `icons` | icons held in registered collections |
| `response KB` | bytes on the wire |
| `resident` | **MB of SVG content held in memory.** The direct measure of the problem |
| `peak mem` | `memory_get_peak_usage(true)` |
| `media reads` | media-library file opens, exact, via the `bpifs://` wrapper |
| `cache get` / `cache miss` | object-cache operations in the `blockparty-icons` group |
| `set >1MB refused` | cache writes memcached would reject. **These never warm up** |
| `init hook` | wall time inside `blockparty_icons_init` |
| `wall` | total request time |

`resident` is measured by reflecting on `CollectionItem`'s private properties, not
by calling `content()`. Calling the getter would force lazily-loaded icons to
resolve and destroy the very thing being measured — the number stays honest across
the refactor.

---

## Faithfulness, and where it stops

Deliberate choices worth knowing before trusting a number:

- **The object cache is file-backed, not memcached.** It reproduces the *semantics*
  exactly — persistence across requests, a 1 MB item ceiling, a silent `false` from
  `wp_cache_set()` — but not the latency. Local file I/O is far cheaper than a
  network round trip. **Treat operation counts as the transferable metric and wall
  time as indicative only.** To model VIP, multiply `cache get` by a memcached RTT.
- **Theme-folder file reads are not counted directly.** `glob()` bypasses PHP stream
  wrappers, so only media-library reads get exact counts. Folder reads are inferred
  from cache misses: a miss on `from_folder:*` means all 200 files were re-read.
  `resident` covers what actually ended up in memory either way.
- **Latency emulation is off by default** (`--latency-us=0`) so the baseline states
  only what was really measured. Turn it on for a VIP-shaped picture.
- **PHP memory limit is raised to 512 MB**, matching VIP's web limit. At PHP's
  common 128 MB default this dataset does not merely run slowly — see below.

---

## Baseline (2026-09-10, 500 icons, latency 0, 4 runs)

### At a 128 MB PHP memory limit, the plugin does not run at all

Activating the plugin against this dataset fatals outright:

```
PHP Fatal error: Allowed memory size of 134217728 bytes exhausted
(tried to allocate 31887360 bytes) in .../object-cache.php on line 216
```

The allocation that fails is `serialize()` on the 200-icon folder collection. The
collection is built in full (~40 MB), then serialized for the cache (~32 MB more).
Everything below therefore runs at 512 MB.

### Measured

| scenario | icons | response KB | resident MB | peak MB | media reads | cache get | cache miss | >1MB refused | init ms | wall ms |
|---|---|---|---|---|---|---|---|---|---|---|
| front-empty-warm | 500 | 20 | 101.7 | 154 | 15 | 301 | 16 | 16 | 602 | 640 |
| front-empty-cold | 500 | 20 | 101.7 | 154 | 300 | 301 | 301 | 16 | 2557 | 2658 |
| front-single-warm | 500 | 39 | 101.7 | 154 | 15 | 301 | 16 | 16 | 679 | 768 |
| front-single-cold | 500 | 39 | 101.7 | 154 | 300 | 301 | 301 | 16 | 860 | 934 |
| front-many-warm | 500 | 3513 | 101.7 | 154 | 15 | 301 | 16 | 16 | 305 | 363 |
| rest-collections | 500 | <1 | 101.7 | 173 | 15 | 301 | 16 | 16 | 539 | 601 |
| rest-theme-p1 | 500 | 12065 | 101.7 | 154 | 15 | 301 | 16 | 16 | 568 | 729 |
| rest-media-p1 | 500 | 11805 | 101.7 | 171 | 15 | 301 | 16 | 16 | 230 | 284 |
| rest-theme-search | 500 | 8818 | 101.7 | 154 | 15 | 301 | 16 | 16 | 459 | 558 |
| editor-new-page | 500 | **24530** | 101.7 | 239 | 15 | 301 | 16 | 16 | 506 | 1201 |

### What the numbers say

1. **`resident` is 101.7 MB on every single row**, including `front-empty-*`, a page
   with no icon block at all. The entire 102 MB icon corpus is loaded to render a
   paragraph. It never varies, because nothing about the request influences it.

2. **16 cache entries are permanently refused** for exceeding 1 MB:
   - `from_folder:.../theme-icons` — the whole 200-icon collection as one entry
   - 15 × `from_file:...` — each individual media icon above 1 MB

   These are not slow-to-warm; they *never* warm. `wp_cache_set()` returns `false`,
   no caller checks it, and the work is redone on every request forever. The
   `from_folder` rejection alone means all 200 theme SVGs are re-read from disk on
   every request.

3. **301 object-cache gets per request**, 300 of them from the media-library
   collection's per-file cache keys. On VIP each is a memcached round trip; at a
   conservative 0.3 ms that is ~90 ms of pure latency before any rendering.

4. **A cold cache costs 300 media reads and 61.8 MB** of remote reads. With
   `--latency-us=2000` that alone adds ~600 ms.

5. **The editor bootstraps 24.5 MB of HTML** because
   `block_editor_rest_api_preload_paths` inlines the first 50 icons *with content*
   for each collection. A single REST page is 12 MB.

These reproduce the three failure modes in the audit: eager whole-corpus loading
(point 1), cache entries too large to store (point 3), and the media-library
registration pattern (point 5).

---

## After the fix

Points 1, 3 and 5 applied: lazy payload loading, per-icon caching with a size
guard, and the `attachments` collection type. Same dataset, same 4 runs.

| scenario | resident MB | peak MB | media reads | cache get | >1MB refused | init ms | wall ms |
|---|---|---|---|---|---|---|---|
| front-empty-warm | 0.015 (−100%) | 12 (−92%) | 0 (−100%) | 2 (−99%) | 0 | 2.3 (−100%) | 58 (−91%) |
| front-empty-cold | 0.015 (−100%) | 16 (−90%) | 0 (−100%) | 2 (−99%) | 0 | 329 (−87%) | 388 (−85%) |
| front-single-warm | 0.032 (−100%) | 10 (−94%) | 0 (−100%) | 2 (−99%) | 0 | 1.3 (−100%) | 32 (−96%) |
| front-many-warm | 3.42 (−97%) | 31 (−80%) | 0 (−100%) | 2 (−99%) | 0 | 1.5 (−99%) | 95 (−74%) |
| rest-theme-p1 | 11.70 (−88%) | 33 (−79%) | 0 (−100%) | 2 (−99%) | 0 | 0.5 (−100%) | 55 (−92%) |
| rest-media-p1 | 9.11 (−91%) | 27 (−84%) | 3 (−80%) | 2 (−99%) | 0 | 0.8 (−100%) | 67 (−77%) |
| editor-new-page | 20.80 (−80%) | 147 (−38%) | 3 (−80%) | 2 (−99%) | 0 | 81 (−84%) | 432 (−64%) |

Reading the rows:

- **`resident` collapses from a flat 101.7 MB to what the request actually uses** —
  15 KB for a page with no icon, 32 KB for a page with one, 11.7 MB for an editor
  page that genuinely lists 50 icons.
- **`cache get` drops from 301 to 2**: one index per collection, instead of one
  lookup per media attachment.
- **No cache entry is refused any more.** Indexes are small enough to store, and
  payloads are cached individually — a 1.8 MB icon no longer poisons a collection.
- `response KB` is unchanged where the payload is genuinely wanted: the same icons
  are still delivered, byte for byte.

### With VIP-shaped latency (`--latency-us=2000`)

Charging 2 ms per media-library file open, which is what a VIP Files fetch costs:

| scenario | before | after |
|---|---|---|
| front-empty-warm | 402 ms | 25 ms (−94%) |
| front-empty-cold | **4546 ms** | 435 ms (−90%) |
| front-single-warm | 414 ms | 41 ms (−90%) |
| front-single-cold | 4739 ms | 493 ms (−90%) |
| editor-new-page | 834 ms | 364 ms (−56%) |

Both sides of this table were measured in the same session, on the same machine,
against the same 500 icons — the "before" column by checking the original classes
back out into the working tree, not by reusing an older run. Wall time moves by a
few tens of percent between sessions depending on what else the machine is doing;
the operation counts above do not move at all.

### What is not addressed

- **The editor still bootstraps ~22 MB.** `block_editor_rest_api_preload_paths`
  inlines the first 50 icons of every collection, with content. Cutting that means
  changing what the editor asks for — dropping `content` from the `view` context, or
  having the picker fetch payloads only for icons it draws. That was point 4 of the
  audit and is out of scope here.
- Rendering a page with 20 icons still resolves 20 payloads (3.4 MB). That is the
  work the page actually requires.

---

## Files

```
tests/perf/
  bin/
    _env.sh                 shared config; works around three wp-env quirks
    generate-fixtures.mjs   deterministic SVG generator (seeded, reproducible)
    setup.sh                boot the env, import media, create pages
    bench.sh                run the scenarios, capture metrics
    report.mjs              aggregate to medians, render the table, diff runs
    teardown.sh             destroy the env
  drop-ins/object-cache.php instrumented cache with memcached's 1 MB ceiling
  mu-plugins/
    bpi-perf-probe.php      metrics + the bpifs:// remote-filesystem wrapper
    bpi-perf-auth.php       test-only auth shim for REST scenarios
  theme/blockparty-perf/    fixture theme; registers both collections
  env/.wp-env.json          the isolated environment definition
  fixtures/                 generated SVGs (git-ignored, ~102 MB)
  results/                  captured runs (git-ignored)
```

### wp-env quirks worked around

`@wordpress/env` 10.39:

1. has **no `--config` flag** on any subcommand — it reads `.wp-env.json` from the
   working directory, which is why the perf env lives in `tests/perf/env/`;
2. ignores the `port` key, templating `${WP_ENV_PORT:-8888}` into docker-compose
   instead — so the ports are set through environment variables;
3. gives `wp-env run` no way to target a non-default env — so the harness talks to
   the containers with `docker exec`, resolving them by published port.

### Safety

`bpi-perf-auth.php` authenticates any request presenting the `BPI_PERF_TOKEN`
constant's value. It is inert unless that constant is defined, and it is defined
only in `tests/perf/env/.wp-env.json`. It must never be copied into a real site.
