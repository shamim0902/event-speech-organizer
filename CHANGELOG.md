# Changelog

All notable changes to Event Speech Organizer are documented here.

## 1.3.0 — 2026-08-21

### Added

- **Shareable event schedule.** A *Share schedule* button on the Slots page
  publishes a standalone, print-ready schedule page listing every slot with its
  speakers. Sharing is off by default — organisers can always preview it, and
  the secret link can be switched on, copied, and revoked by generating a new
  one. Print → Save as PDF turns it into a file you can attach anywhere.
- **Prev/Next paging on the applicant page.** The arrows walk the list you came
  from, honouring that list's status filter, search and sort. The ← / → arrow
  keys (or `j` / `k`) do the same, and the breadcrumb now returns to the list
  you started from instead of always to *All applicants*.
- **Confirmation before a status change** on the single applicant page, worded
  for the action being taken. A failed save now puts the previous status back
  instead of leaving the page showing one the server never stored.
- **Type-to-confirm delete for events.** Deleting an event takes its applicants
  and slots with it, so it now spells out what will be lost and asks you to type
  `delete`.
- **"Slot required" badge** on approved applicants who have not been assigned to
  any slot yet, in both the table and the applicant page.
- **Build command.** `npm run build:zip` produces a distributable
  `builds/event-speech-organizer.zip` containing runtime files only.

### Changed

- Row actions moved into 3-dot menus: Edit/Delete on the event cards, the
  status actions in the applicant table, and Edit/Delete on the single
  applicant page — which frees the table's Actions column from 280px to 90px.
- **Event cards** redesigned. The boxed stat grid left an empty cell whenever
  there were three stats; stats are now one divided row, joined by a meter
  showing how much of the approved line-up has been scheduled.
- **Applied dates** are readable: "2 days ago" / "19 Aug" with the time and
  application number beneath, and the full timestamp on hover. Raw MySQL
  timestamps used to wrap mid-value.
- **Slots page** rebuilt as an agenda with a time rail, type legend and totals.
  The slot dialog now leads with the speaker picker and fills in the topic,
  talk type and end time from that pick, all still editable.
- Single applicant page redesigned into a profile sidebar plus talk and bio
  cards, with status tiles in place of plain buttons.
- Applicants are listed in a table rather than cards, with clickable rows and an
  expandable detail row.

### Fixed

- Prev/Next labels overflowed their fixed-width buttons and collided.
- The active status pill rendered white-on-white.
- The search and sort controls beside the filter pills looked broken in the
  flex row.
- Admin assets are versioned by file modification time, so a plugin update no
  longer serves a stale cached bundle.

## 1.2.0

### Added

- **Incoming webhook per event** for speaker applications, with a secret token
  in the URL that can be regenerated from Settings.
- **Webhook field mapper** per event, with capture of the last received payload
  so incoming fields can be mapped to applicant columns — or ignored — without
  guesswork.
- Settings page listing each event's webhook URL.

### Changed

- Applicants and slots are scoped to real events; the tab system was replaced by
  an event-centric navigation.

## 1.1.0

### Added

- Fluent Forms integration that creates an applicant from a submission, plus a
  one-off importer for existing submissions.
- CSV import and export for applicants.

## 1.0.0

- Initial release: events, speaker applicants, statuses and schedule slots.
