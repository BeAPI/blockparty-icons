# Test suite

Integration tests against a real WordPress install — not isolated unit tests. The
plugin leans on `WP_Query`, the object cache, `WP_HTML_Tag_Processor`,
`WP_REST_Controller` and a set of filters; mocking that surface would test the
mocks rather than the plugin.

## Running

Through wp-env, which supplies WordPress, the test library and a database:

```bash
WP_ENV_PORT=8890 WP_ENV_TESTS_PORT=8891 npx wp-env start
npm run test:php
```

Anywhere else — CI, or a local run against the Composer copy of WordPress — you
supply the database:

```bash
cp tests/wp-tests-config-sample.php wp-tests-config.php
# edit the credentials, pointing at a throwaway database
composer test
```

Arguments pass straight through:

```bash
npm run test:php -- --filter BlockRendererTest
composer test -- --filter test_search
```

> The suite **drops and recreates** the tables in the configured database. Never
> point it at anything you want to keep.

## What is covered

| file | subject |
|---|---|
| `Icon/CollectionItemsFactoryTest.php` | every SVG source: folder, sprite, single file |
| `Icon/CollectionTest.php` | the collection container: lookup, count, search, named constructors |
| `Icon/CollectionItemTest.php` | a single icon, and what survives the object cache |
| `BlockRendererTest.php` | front-end markup: raw and sprite icons, size, radius, link, colour, escaping |
| `RegistrationTest.php` | the public API a theme calls, plus editor preload paths |
| `Rest/CollectionsControllerTest.php` | collection discovery endpoint and its permissions |
| `Rest/IconsControllerTest.php` | icon listing, pagination, search, single item, permissions |
| `Helpers/CacheTest.php` | the object-cache wrapper every source goes through |
| `Helpers/KsesTest.php` | the KSES allowances that keep saved block markup valid |

### These are characterization tests

They describe **observable behaviour** — how many icons come back, what their
names, labels, types and contents are, what markup reaches a page — and say
nothing about *when* or *how often* files are read.

That is deliberate. It means the suite keeps its meaning across a refactor of how
loading and caching work: if a change alters what a caller can observe, a test
fails; if it only alters the mechanism, the suite stays green.

## Known gaps

- **Development-mode cache bypass.** WordPress resolves `wp_is_development_mode()`
  from the `WP_DEVELOPMENT_MODE` constant behind a static cache, and only lets its
  own core suite vary it. The suite therefore runs with caching active, which is the
  production path anyway. See the docblock in `Helpers/CacheTest.php`.
- **`Collection`'s Iterator.** It is broken — positions are integers, keys are
  names, so `foreach` over a populated collection yields nothing. Nothing in the
  plugin iterates a collection, so this is latent. It is recorded by
  `test_known_defect_iteration_yields_nothing_even_when_populated`, which will fail
  when someone fixes it. Update the test then; do not delete it.
- **The WP-CLI migration command** (`includes/Cli`, `includes/Migration`) has no
  coverage and is excluded from the coverage report.
- **JavaScript.** The editor sources under `src/` are not covered here.

## Fixtures

`TestCase` builds fixtures on demand and removes them afterwards:

- `make_icon_folder( [ 'name.svg' => $contents ] )` — a folder of SVGs
- `make_file( $name, $contents )` — a single file
- `svg( $marker )` — a minimal valid SVG, with a marker so icons can be told apart
- `sprite( [ $id, … ] )` — a sprite containing those symbol ids

They live under `WP_CONTENT_DIR` on purpose: `CollectionItemsFactory` derives a
sprite's public URL by swapping `WP_CONTENT_DIR` for `WP_CONTENT_URL`, so fixtures
placed anywhere else would make that path untestable.

`TestCase::set_up()` also empties the collections global and flushes the object
cache, so no test can be influenced by another.
