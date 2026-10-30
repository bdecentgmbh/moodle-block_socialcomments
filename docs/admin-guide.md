---
pagetypes:
  - admin-setting-blocksettingsocialcomments
  - blocks-socialcomments-*
---

# Social comments — Administrator guide

The Social comments block (`block_socialcomments`) adds comments, replies, pins and subscriptions to
course and activity pages. This guide covers installing, upgrading, the settings, digests,
real-time updates and permissions. For using the block in a course, see the
[teacher guide](teacher-guide.md).

## Requirements

Moodle 4.5 to 5.3. Real-time updates need the Deft response block (`block_deft`), which is
optional.

## Installation

1. Download the plugin or clone it into `blocks/socialcomments` (`public/blocks/socialcomments`
   from Moodle 5.1).
2. Go to *Site administration > Notifications* to complete the installation.

## Upgrading to 1.5

- Moodle 4.5 is now the minimum.
- The block got a new look: templates, Font Awesome icons instead of images, the core switch to
  subscribe. Themes that styled the old classes (`ccomment-*`) need new styles (`sc-*`).
- Comments of activities that were deleted earlier are removed by the upgrade; from now on they are
  deleted with their activity.
- Two new settings: **Date of comments** and **Update in real time** (see below).
- New installations allow non-editing teachers what students may do. On existing sites, allow it
  for the *Non-editing teacher* role if they should take part.
- Security and privacy fixes; see `CHANGES.md`.

## Settings

*Site administration > Plugins > Blocks > Social Comments*:

| Setting | Default | Meaning |
|---|---|---|
| Comments per page | 10 | Comments on each page of the block |
| Replies shown per comment | 10 | The newest replies shown below each comment; earlier ones behind a button. 0 for all. |
| Comments per report page | 25 | Rows on each page of the *All comments* report |
| Date of comments | Time ago | "5 minutes ago", "Yesterday", "3 weeks ago"; or the date and time. The exact date is always shown when pointing at it. Digests always show the date. |
| Update in real time | Yes | With block_deft's updating service set up, comments appear for everybody who has the page open |
| Users per cron | 0 | How many users get their digest in each run of the task; 0 for all |
| Type of digest | Digest per site | One digest with all courses, or one digest per course |

## Digests

Users who subscribe to a page (posting a comment subscribes them) get the new comments and replies
of others as a notification with the message provider *Digest of new comments on pages you
subscribed to*. Users choose in their notification preferences whether it comes by email, in Moodle
or in the app.

The scheduled task **Process digests** (`\block_socialcomments\task\process_digest_cron`) sends them,
by default every minute, so a digest usually holds a single new comment. To send real digests, run
the task less often, e.g. hourly or daily, under *Site administration > Server > Tasks > Scheduled
tasks*. On large sites, limit **Users per cron**.

A digest leaves out pages the user can no longer see: courses they left, hidden or restricted
activities, other groups in separate groups mode.

## Real-time updates

With **Update in real time** on, the block uses the updating service of the
[Deft response block](https://moodle.org/plugins/block_deft):

1. Install block_deft.
2. In its settings, set **Enable updating** to *Deft service*, save, and press **Activate**. This
   registers the site with deftly.us as an External tool (LTI).

When a comment is posted, edited or deleted, the server tells the service that the comments of that
page changed; the service tells the browsers that have the page open, and they load the comments
with the permissions of their user. The service never gets the comments or user data; it gets the
context id of the page, and the browsers connect to `wss://deftly.us`. Without block_deft, or with
the setting off, the block works as before.

## Permissions

| Capability | Default roles | Allows |
|---|---|---|
| `block/socialcomments:addinstance` | Editing teacher, Manager | Adding the block |
| `block/socialcomments:view` | Student, Non-editing teacher, Editing teacher, Manager | Seeing the comments of a page |
| `block/socialcomments:postcomments` | Student, Non-editing teacher, Editing teacher, Manager | Commenting, replying and editing own posts |
| `block/socialcomments:deleteowncomments` | Student, Non-editing teacher, Editing teacher, Manager | Deleting own comments |
| `block/socialcomments:deleteownreplies` | Student, Non-editing teacher, Editing teacher, Manager | Deleting own replies |
| `block/socialcomments:deletecomments` | Editing teacher, Manager | Deleting all comments |
| `block/socialcomments:deletereplies` | Editing teacher, Manager | Deleting all replies |
| `block/socialcomments:pinitems` | Student, Non-editing teacher, Editing teacher, Manager | Pinning, and the pinboard |
| `block/socialcomments:subscribe` | Student, Non-editing teacher, Editing teacher, Manager | Subscribing to pages |
| `block/socialcomments:viewreport` | Student, Non-editing teacher, Editing teacher, Manager | The comments report of the course |

Non-editing teachers have the same capabilities as students since version 1.5. Sites that
installed an earlier version have to allow them for the *Non-editing teacher* role themselves. In
separate groups mode, `moodle/site:accessallgroups` lets users see and post to all groups.

## Privacy and data

The block stores comments, replies, pins and subscriptions with the user who made them. The privacy
API exports and deletes them per page. Deleting a user removes their pins and subscriptions; their
comments stay, as in forums, until the user's data is deleted through a data request.

Backups with user data include the comments: a block on the course page backs up the comments of
the whole course, a block on an activity page those of its activity. Restored comments return to
their pages.
