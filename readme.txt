=== Blockparty Icons ===
Contributors:      beapi
Tags:              block, icons, svg, gutenberg, editor
Requires at least: 6.2
Tested up to:      6.8
Requires PHP:      8.1
Stable tag:        1.1.2
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Provides a block to add custom SVG icons from your theme or plugin in the WordPress editor.

== Description ==

Blockparty Icons adds an icon block to the WordPress editor. Users can pick custom SVG icons from a registered collection, either from an SVG sprite or a folder of SVG files.

Register icon collections from your theme or plugin using the `blockparty_icons_init` action and the `register_icon_collection()` API.

**SVG security note:** this plugin does not sanitize SVG files before using them. Only use SVGs you trust. Sanitize SVG sources beforehand.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/blockparty-icons` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Register at least one icon collection from your theme or plugin (see the plugin README for code examples).

== Frequently Asked Questions ==

= My icon collection does not appear in the block editor =

Icon collections are cached in the browser session storage for performance. After registering a new collection, clear your browser session storage and reload the editor.

= Can I use icons from a folder instead of a sprite? =

Yes. Register a collection with `type` set to `folder` and `source` pointing to the folder that contains your SVG files.

== Changelog ==

= 1.1.2 =
* Resolve missing `icon.collection` during `beapi/icon-block` migration via registry lookup and a generic fallback.

= 1.1.1 =
* Fix deprecated warning for nullable string parameters in `CollectionItem`.
* Align GitHub workflows with other blockparty repos and add release version consistency checks.

= 1.1.0 =
* Add WP-CLI command to convert `beapi/icon-block` / `beapi/icon-item` old content to `blockparty/icon`.

= 1.0.8 =
* Fix icon padding values missing CSS units in the editor.
* Fix `addDefaultUnit` utility handling of undefined, null, and empty values.
* Fix icon color not applying to SVG icons using `fill="currentColor"` on the front end.
* Fix SVG previews being incorrectly recolored in the icon selector and modal.
* Fix inline `style` and `id` attribute handling when parsing SVG markup.
* Replace regex-based SVG parser with recursive DOM traversal for more reliable SVG rendering.
* Update translations.

= 1.0.7 =
* Fix icon color with CSS custom properties.

= 1.0.6 =
* Fix icon preview size styles.

= 1.0.5 =
* Set label collection on tabs item in icons selector modal instead of collection slug.
* Add tooltip to icon selector buttons.
* Remove quality JS workflow on merge.

= 1.0.4 =
* Improve search result display in icons selector modal.
* Update translations.

= 1.0.3 =
* Add support for WordPress <= 6.8 for ServerSideRender component.

= 1.0.2 =
* Add support for WordPress <= 6.8 for ServerSideRender component.

= 1.0.1 =
* Exclude `.wp-env` directory from export.

= 1.0.0 =
* Initial release.
