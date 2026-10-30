---
exclude: true
---

# Social comments — Developer guide

How the block is built, for people changing it, styling it in a theme or testing it.

## How it works

A comment belongs to a **page**: the context of the course or activity it was posted on
(`block_socialcomments_cmmnts.contextid`), not to the block instance. The block reads
`$PAGE->context`, so one block shown on many pages ("Display on page types: Any page") gives every
page its own comments. On category pages the block shows a note instead.

| Part | Where | What it does |
|---|---|---|
| Block | `block_socialcomments.php` | Checks `block/socialcomments:view`, hands the page context to the renderer |
| Data | `classes/local/comment.php`, `reply.php` | Records, permission checks (`can_create`, `can_edit`, `can_delete`, `can_view`, `can_view_context`) |
| Pages of comments | `classes/local/comments_helper.php` | Comments with replies and pins of the user, pins, subscriptions, clean-up, dates |
| Web services | `classes/external.php`, `db/services.php` | Everything the block does in the browser (AJAX only) |
| Rendering | `renderer.php`, `templates/` | `block`, `form`, `comments_page`, `post`, `links`; `overview`, `pinboard`, `digest` |
| Browser | `amd/src/comments.js` | Forms, menu actions, pins, subscription, paging; reloads the list through `get_commentspage` |
| Real time | `classes/local/realtime.php`, `classes/socket.php`, `amd/src/realtime.js` | Optional block_deft integration |
| Reports | `report/newsfeed.php`, `report/index.php`, `classes/local/report_helper.php` | New comments since the last login; all comments with filters |
| Pinboard | `pinboard/index.php` | The pins of the user in a course |
| Digest | `classes/local/digest.php`, `classes/task/process_digest_cron.php` | Notifications for subscribers |

## Web services

All are `ajax` functions for the block's JavaScript. The context comes from the record where there
is one (a reply always uses the context of its comment), and `validate_context()` runs the usual
course and activity access checks.

| Function | Does |
|---|---|
| `block_socialcomments_get_commentspage` | A page of comments as HTML (`pagenumber` -1 for the last page) |
| `block_socialcomments_save_comment` | Create (`id` 0) or edit a comment |
| `block_socialcomments_delete_comment` | Delete a comment with its replies and pins |
| `block_socialcomments_save_reply` | Create (`id` 0) or edit a reply |
| `block_socialcomments_delete_reply` | Delete a reply |
| `block_socialcomments_set_pinned` | Pin or unpin a comment, or the page (`commentid` 0) |
| `block_socialcomments_set_subscribed` | Subscribe to a page |
| `block_socialcomments_get_activity_options` | Activities of a section, for the report filter |
| `block_socialcomments_renew_token` | A new token for the real-time service |

## Data

| Table | Holds |
|---|---|
| `block_socialcomments_cmmnts` | Comments: `contextid` (page), `courseid`, `groupid` (0 = all groups), `userid`, `content`, `format` |
| `block_socialcomments_replies` | Replies: `commentid`, `userid`, `content`, `format` |
| `block_socialcomments_pins` | Pins: `itemtype` 10 = page (`itemid` = context id), 20 = comment (`itemid` = comment id) |
| `block_socialcomments_subscrs` | Subscriptions: `contextid`, `userid`, `timelastsent` of the digest |

`comments_helper::delete_comments()` and `delete_context_data()` delete with all dependent records.
Observers delete the data of deleted courses and activities and the pins and subscriptions of
deleted users.

Content is plain text written into a textarea and shown with `format_text()` (`FORMAT_MOODLE`).

## Real-time updates

`realtime::enabled()` is true when the setting is on, block_deft is installed, its *Enable updating*
is *Deft service* and the site is registered. Only then is `classes/socket.php` (which extends
`\block_deft\socket`) loaded. Saving or deleting a comment or reply calls `realtime::notify()`,
which dispatches an update for the page context (component `block_socialcomments`). The block loads
`block_socialcomments/realtime`, which listens with block_deft's socket and fires the DOM event
`block_socialcomments:changed` on the block; `comments.js` then reloads the current page of comments
unless a reply or edit form is open (it waits until it is closed).

## Backup and restore

The block's backup holds the comments of its page; a block on the course page those of the whole
course. Restoring skips comments another block of the same backup restored already. Comments,
subscriptions and page pins are inserted with context 0 and point to their new pages in
`after_restore()`, when all activity contexts are mapped; those of pages that were not restored are
deleted.

## Styling

The templates use `sc-*` classes, scoped by `.block_socialcomments` in `styles.css` (the pages wrap
their content in it too). Colours come from Bootstrap 5 variables with Bootstrap 4 fallbacks, so the
block looks the same on Moodle 4.5 and 5.x. The digest email uses inline styles only.

## Tests

- PHPUnit: `tests/` (access checks, web services, digest, privacy, backup and restore, clean-up,
  dates).
- Behat: `tests/behat/` (`@block_socialcomments`): commenting, replying, editing, deleting, pins and
  pinboard, report, separate groups, category pages.
- CI: `.github/workflows/moodle-ci.yml` runs moodle-plugin-ci on Moodle 5.3 first, then 5.2, 5.1 and
  4.5, on pull requests and on pushes to `main`.

## Releases

`version.php` holds `$plugin->release` (e.g. `1.5`). To release: merge `dev` into `main` with a
section `## 1.5 (2026102900)` in `CHANGES.md`, then push the tag `v1.5` (or `v1.5-r1`, `v1.5-r2` for
re-releases) on `main`. `.github/workflows/moodle-release.yml` checks the tag against `version.php`
and `CHANGES.md`, waits for CI on the tagged commit, publishes to the Moodle Marketplace and creates
the GitHub release with the ZIP. It needs the repository secret `MOODLE_MARKETPLACE_TOKEN`.
