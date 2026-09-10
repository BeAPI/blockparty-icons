# Blockparty Icons

## Note about SVG

This plugin **doesn't run any sanitization on SVGs** before using them. Only use SVGs you know are safe. SVG sanitization should be handle beforehand.

## Changelog

### Unreleased

- Add a PHPUnit integration test suite covering every SVG source (folder, sprite, single file), the collection container, the registration API, front-end block rendering, both REST controllers, the object-cache wrapper and the KSES allowances — 176 tests. See `tests/README.md`
- Run the suite in CI on PHP 8.1 through 8.4

### 1.1.1 - 2026-08-26

- Fix deprecated warning for nullable string parameters in `CollectionItem`
- Align GitHub workflows with other blockparty repos and add release version consistency checks

### 1.1.0 - 2026-08-14

- Add WP-CLI command to convert `beapi/icon-block` / `beapi/icon-item` old contents to `blockparty/icon`

### 1.0.8 - 2026-06-26

- Fix icon padding values missing CSS units in the editor
- Fix `addDefaultUnit` utility handling of undefined, null, and empty values
- Fix icon color not applying to SVG icons using `fill="currentColor"` on the front end
- Fix SVG previews being incorrectly recolored in the icon selector and modal
- Fix inline `style` and `id` attribute handling when parsing SVG markup
- Replace regex-based SVG parser with recursive DOM traversal for more reliable SVG rendering
- Update translations

### 1.0.7 - 2026-05-26

- Fix icon color with CSS custom properties

### 1.0.6 - 2026-05-18

- Fix icon preview size styles

### 1.0.5 - 2026-02-23

- Set label collection on tabs item in icons selector modal instead of collection slug
- Add tooltip to icon selector buttons
- Remove quality js workflow on merge

### 1.0.4 - 2026-02-20

- Improve search result display in icons selector modal.
- Update translations

### 1.0.3 - 2026-02-19

- Add support for WordPress <= 6.8 for ServerSideRender component

### 1.0.2 - 2026-02-19

- Add support for WordPress <= 6.8 for ServerSideRender component

### 1.0.1 - 2026-02-19

- Exclude .wp-env directory from export.

### 1.0.0 - 2026-02-02

- Initial release.
