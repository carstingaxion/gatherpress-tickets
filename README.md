# GatherPress Tickets Block

**Contributors:** carstenbach & WordPress Telex  
**Tags:** block, tickets, theater, events  
**Tested up to:** 6.8  
**Stable tag:** 0.1.1  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

[![Playground Demo Link](https://img.shields.io/badge/WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-tickets/main/.wordpress-org/blueprints/blueprint.json) [![Build, test & measure](https://github.com/carstingaxion/gatherpress-tickets/actions/workflows/build-test-measure.yml/badge.svg?branch=main)](https://github.com/carstingaxion/gatherpress-tickets/actions/workflows/build-test-measure.yml)

A block variation of `core/button` for GatherPress event tickets, with post meta integration and intelligent URL fallback.

## Description

The GatherPress Tickets Block registers a **block variation** of the WordPress core Button block, giving event organizers a dedicated ticket button that integrates with GatherPress event data — without reimplementing any button functionality.

### Architecture

This plugin demonstrates the recommended pattern for extending core blocks via block variations. Instead of creating a custom block that duplicates `core/button`, it:

1. Registers a **block variation** of `core/button` with a custom class, default label, and a placeholder URL.
2. Adds an **InspectorControls panel** via the `editor.BlockEdit` filter, providing a "Ticket URL" field that stores its value in post meta.
3. Uses the **`render_block_core/button` filter** on the frontend to replace the placeholder URL with the resolved ticket URL from post meta (with fallback logic).

This means the variation inherits **all** `core/button` features for free: color, typography, border, border radius, spacing, width, alignment, and any future improvements to `core/button`.

### Key Features

- **Zero Duplication** — Inherits all `core/button` styling controls and markup natively.
- **Post Meta Integration** — Stores the ticket URL in `gatherpress_tickets_url` post meta, available via the REST API.
- **Intelligent Fallback Logic** — Falls back to GatherPress venue website URL, then to a "Get tickets at the venue" message linking to the venue term archive.
- **Frontend Render Filter** — Uses `render_block_core/button` to inject the real URL at render time.
- **Variation Detection** — Uses `isActive` callback with a CSS class identifier (`is-style-gatherpress-tickets`).
- **Admin Column** — Displays a green check mark in the post list table when a ticket URL is set.
- **Singleton Pattern** — Plugin class uses the Singleton pattern for clean architecture.

### Editor Experience

- Appears in the inserter as "GatherPress Tickets" with a ticket icon.
- Editable button with default label "Get Tickets".
- Full `core/button` controls: color, typography, border, spacing, width, alignment.
- Sidebar "Ticket Settings" panel with a URL field stored in post meta.

### Frontend Behavior

- Renders a standard `core/button` anchor with the resolved ticket URL.
- Falls back to venue URL from GatherPress post meta.
- Falls back to the venue taxonomy term archive URL.
- Uses "Get tickets at the venue" as the button label for all fallback scenarios.
- Converts the link to a non-interactive `<span>` when no URL is available at all.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/telex-gatherpress-tickets` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Add the **GatherPress Tickets** block (found under the core Button variations) to any post or page via the block editor.

## Frequently Asked Questions

### Why a block variation instead of a custom block?

Block variations extend existing core blocks without duplicating their code. This means you get all of `core/button`'s features — current and future — for free. It's the WordPress-recommended approach for blocks that are conceptually the same as a core block but with customized defaults or additional behavior.

### How does the URL fallback work?

The block stores a placeholder URL (`#gatherpress-tickets`) in the saved content. On the frontend, a `render_block` filter replaces this with the actual ticket URL from post meta. If no ticket URL is set, it falls back to:

1. The GatherPress venue website URL (from `_gatherpress_venue` post meta).
2. The venue taxonomy term archive URL (from the `_gatherpress_venue` taxonomy).
3. A non-interactive `<span>` element if no URL is available at all.

In all fallback cases, the button label is replaced with "Get tickets at the venue".

### Can I customize the button appearance?

Yes. The variation inherits all `core/button` styling controls including colors, typography, border, border radius, spacing, width, and alignment.

### Is the ticket URL available via the REST API?

Yes. The `gatherpress_tickets_url` meta field is registered with `show_in_rest` enabled.

## Changelog

### 0.1.0

- Initial release as a `core/button` block variation with post meta integration and render filter.
- Intelligent three-tier fallback logic (ticket URL → venue website → venue term archive).
- Admin list table column with ticket URL indicator.

## License

This project is licensed under the GPLv2 or later — see the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html) for details.
