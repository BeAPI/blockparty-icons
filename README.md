# Be API — Blockparty Icons

[![Test with WordPress Playground](https://img.shields.io/badge/Test%20with-WordPress%20Playground-0073aa?style=for-the-badge&logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/BeAPI/blockparty-icons/refs/heads/develop/.wordpress-org/blueprints/blueprint.json)

Blockparty Icons enhances the WordPress editor by adding an extra block. This block enables users to integrate custom SVG icons directly from their theme or a WordPress plugin. Users can choose these icons from an SVG sprite or a folder containing multiple SVG files. This feature offers enhanced flexibility for customizing content, thereby improving the design and aesthetics of WordPress pages and posts.

## Installation

Install this plugin by placing it in your `plugins` directory and activating it through your WordPress dashboard. Afterward, incorporate custom icons for an enhanced experience.

## How to add custom icons?

In your `functions.php` file, add the following code:

```php
function register_collection() {
    \Blockparty\Icons\register_icon_collection(
        'my_collection',
        [
            'label'   => 'My custom icons collection',
            'type'    => 'sprite',
            'source'  => get_stylesheet_directory() . '/dist/icons/sprite.svg',
            'version' => wp_get_theme()->get( 'Version' ), // Optional: cache busting for sprite URL
        ]
    );
}

add_action( 'blockparty_icons_init', 'register_collection' );
```

This is an example for adding a SVG sprite as a source. If you want to add icons from a folder, change the `type` value to `folder` and the path of your `source` to `get_stylesheet_directory() . '/dist/icons/'` for example.

## Icons contributed in the back office

To offer the SVG files uploaded to the media library as a collection, use the `attachments` type:

```php
\Blockparty\Icons\register_icon_collection(
    'mediatheque',
    [
        'label' => 'Media library',
        'type'  => 'attachments',
    ]
);
```

This runs a single query and caches one compact index, invalidated as soon as any
attachment changes. Pass extra `WP_Query` arguments through `query` to narrow the
selection:

```php
\Blockparty\Icons\register_icon_collection(
    'mediatheque',
    [
        'label' => 'Media library',
        'type'  => 'attachments',
        'query' => [ 'posts_per_page' => 1000 ],
    ]
);
```

Do **not** build such a collection by looping over attachments and calling
`CollectionItemsFactory::from_file()` for each one. That costs a database query and
one object-cache round trip per icon on every request, front end included.

## Performance notes

An icon's SVG payload is read only when it is actually needed — one icon when a block
renders, one page's worth when the editor lists a collection. Registering a collection
builds a lightweight index and reads no SVG at all.

Payloads are cached individually rather than inside the collection, and payloads larger
than 900 KB are not sent to the object cache: memcached (WordPress VIP and most managed
hosts) refuses items over 1 MB and signals it only through a return value that nothing
checks, which would otherwise mean rebuilding the same entry on every request forever.
Raise or disable that ceiling on Redis or APCu:

```php
add_filter( 'blockparty_icons_cache_max_item_bytes', fn() => 5 * MB_IN_BYTES );
```

A reproducible benchmark for all of this lives in [`tests/perf`](tests/perf/README.md).

## Params

| param     | description               |
|-----------|---------------------------|
| `name`    | Name of the collection.   |
| `options` | Options array. See below. |

## Options

| param     | description               |
|-----------|---------------------------|
| `label`   | Label of the collection.  |
| `source`  | Path to the SVG sprite file or folder containing SVG files. |
| `type`    | <ul><li>`sprite` for SVG sprite source.</li><li>`folder` for a folder containing SVG files.</li><li>`attachments` for the SVG files in the media library.</li></ul> |
| `query`   | Optional. For `attachments`, extra `WP_Query` arguments. |
| `version` | Optional. Version string used for cache busting (e.g. theme version). When set, the sprite URL is appended with a `?v=...` query parameter. |

## How to develop

- Setup the working environment :

```bash
npm run start:env
```

- Compile CSS/JS on edit :

```bash
npm start
```

- Activate plugin and develop on <http://localhost:8888/> (admin/password)

## Icons collection does not display / Clear cache

Icons collections are stored locally in session storage for better perfomances.

When adding a new collection, if it does not appears in the block editor, clear your browser session storage.

## How to generate the languages

To generate the pot file

```bash
npm run make:pot
```

To generate the JSON files for Gutenberg from the po files

```bash
npm run make:json
```
