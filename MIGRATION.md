# Migrating from `beapi/icon-block` to `blockparty-icons`

This document describes how to move from the abandoned [`beapi/icon-block`](https://github.com/BeAPI/beapi-icon-block) package to `beapi/blockparty-icons`.

## Package swap

```bash
composer remove beapi/icon-block
composer require beapi/blockparty-icons
```

Activate `blockparty-icons` and deactivate `icon-block`.

## Theme API changes

| `beapi/icon-block` | `blockparty-icons` |
|---|---|
| Namespace `Beapi\IconBlock\` | Namespace `Blockparty\Icons\` |
| Constant `BEAPI_ICON_DIR` | Constant `BLOCKPARTY_ICONS_DIR` |
| Action `icon_block_init` | Action `blockparty_icons_init` |
| `register_icon_collection()` | Same helper name under the new namespace |

Example:

```php
use Blockparty\Icons\Icon\Collection;
use function Blockparty\Icons\register_icon_collection;

add_action(
	'blockparty_icons_init',
	static function () {
		register_icon_collection(
			'my_collection',
			[
				'label'  => 'My custom icons collection',
				'type'   => 'sprite',
				'source' => get_stylesheet_directory() . '/dist/icons/sprite.svg',
			]
		);
	}
);
```

You can also pass a `Collection` instance built with `Collection::from_sprite()` / `Collection::from_folder()`.

## Block model changes

| Old | New |
|---|---|
| Parent `beapi/icon-block` + child `beapi/icon-item` | Single dynamic block `blockparty/icon` |
| Saved markup `ul` / `li` (v3) or `div.icon-container` (legacy) | Server-rendered `div.wp-block-blockparty-icon` |
| Allowlist / block styles on `beapi/icon-block` | Register against `blockparty/icon` |
| CSS `.wp-block-beapi-icon-block` | CSS `.wp-block-blockparty-icon` |

### Attribute mapping

| Old (`icon-item` / legacy parent) | New (`blockparty/icon`) |
|---|---|
| `icon` (`name`, `type`, `label`, optional `collection`) | `icon` (requires `collection` + `name`) |
| Parent `collection.name` (legacy object) | `icon.collection` |
| `iconColorValue` / legacy `iconColor.color` | `iconColor` (value kept as-is, including `inherit`) |
| `size` (int) | `size` (int) |
| `borderRadius` (int) | `borderRadius` (string, e.g. `0px`) |
| `url`, `label`, `className` | same |
| Custom attrs (e.g. `sharedBlockId`) | preserved |

Empty parent shells (no icon selected) become an empty `blockparty/icon` with preserved `className` / custom attrs.

Multiple `beapi/icon-item` children become sibling `blockparty/icon` blocks.

## Content migration (WP-CLI)

After activating `blockparty-icons` and updating theme collections:

```bash
# Preview (current site)
wp blockparty-icons migrate-from-icon-block --dry-run

# Apply on the current site
wp blockparty-icons migrate-from-icon-block

# Limit post types
wp blockparty-icons migrate-from-icon-block --post-type=post,page
```

The command always targets **one site only**. On multisite, repeat it yourself with `--url=` for each site:

```bash
wp blockparty-icons migrate-from-icon-block --url=https://example.com/
wp blockparty-icons migrate-from-icon-block --url=https://example.com/site-2/
```

The command:

1. Scans `post_content` for `beapi/icon-block` / `beapi/icon-item`
2. Parses blocks, converts markup recursively, serializes back (attrs only — no asset resolution that aborts conversion)
3. Updates the post with `wp_slash()` on content (required so `\u002d` escapes for `--` in block comments are not stripped by `wp_update_post()`)
4. Logs migrated / skipped counts
5. Lists missing icon assets (`collection/name`) so you can add them manually to theme assets / collections

When `collection` is missing on the icon object (and not on the parent attrs), the block is skipped — there is no default collection. Missing files among converted icons are reported, not skipped.

## Front-end checklist

- Re-register custom block styles (e.g. `is-style-inline`) on `blockparty/icon`
- Update theme SCSS selectors from `.wp-block-beapi-icon-block` to `.wp-block-blockparty-icon`
- Update block patterns / allowlists
- Smoke-test sprite vs raw icons, colors, sizes, and shared blocks
