# Be API — Blockparty Icons

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
| `type`    | <ul><li>`sprite` for SVG sprite source.</li><li>`folder` for a folder containing SVG files.</li></ul> |
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
