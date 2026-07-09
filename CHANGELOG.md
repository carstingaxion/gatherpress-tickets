# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased](https://github.com/carstingaxion/gatherpress-tickets/compare/0.1.0...HEAD)

## [0.1.0](https://github.com/carstingaxion/gatherpress-tickets/compare/0.1.0...0.1.0) - 2026-07-09

- **Block variation** — `gatherpress/tickets`, a `core/button` variation with full native styling controls, identified by the `is-style-gatherpress-tickets` CSS class.
- **Post meta** — `gatherpress_tickets_url` on `gatherpress_event` posts, REST-API-exposed and editor-bound.
- **URL fallback chain** — resolves to the GatherPress venue website, then the venue term archive, then renders as an inert `<span role="button">` with an adjusted label when nothing is available.
- **Ticket URL field** — shared `TicketUrlField` component used in both the block inspector panel and the always-visible document setting panel; draft-based input with blur-commit and inline `Notice` validation (rejects non-`http/https` URLs including bare strings like `"123"`).
- **Pre-publish panel** — surfaces the same URL field in the pre-publish checklist so editors are reminded before publishing.
- **Admin list-table column** — dashicon ticket column on the event list screen; linked green check mark when a URL is set, em-dash otherwise.
- **Dashboard widget** — lists upcoming events missing a ticket URL with an inline "Add URL" overlay form; URL validated server-side before save, results cached in a one-hour transient that is busted automatically on any meta change.
