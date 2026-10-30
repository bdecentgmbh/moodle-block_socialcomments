# Changes

## 1.5 (2026103000)

- Moodle 4.5 to 5.3; Moodle 4.5 is now the minimum.
- New look: comments and replies with picture, name and date above the text, replies indented by a
  line only, compact enough for the block drawer on phones. Font Awesome icons instead of images, the
  standard action menu to edit and delete (deleting asks first), the core switch to subscribe, the
  core paging bar. In separate groups, comments show their group. The comments use the normal text
  colour instead of the muted one of Moodle 5 blocks.
- New setting "Date of comments": "5 minutes ago", "3 hours ago" for today, "Yesterday", "6 days ago",
  weeks, months and years before; or the date and time as before. The exact date and time are shown
  when pointing at it. Digests always show the date.
- New setting "Update in real time": with the updating service of the Deft response block
  (block_deft), new, edited and deleted comments appear for everybody who has the page open (#3).
  Forms being written are not interrupted. block_deft stays optional. The service gets the id of
  the page only, no comments and no user data.
- Security: the comments report showed the HTML of comments unescaped; names of sections and
  activities went into the report query unescaped (an apostrophe broke the report); replies and
  pins did not check access to their comment; the newsfeed, pinboard and digest showed comments of
  hidden activities, of other groups and of courses the user had left; in separate groups, the
  report counted the comments of other groups; pins and subscriptions were accepted for pages other
  than courses and activities.
- Privacy: export and deletion work per page, for activity pages too, and no longer delete the data
  of other users or of other pages.
- Backup and restore: comments are restored once and on their own page (they were restored once per
  block and all on the course page). Restored logs point to the restored comments and replies.
- Comments, replies, pins and subscriptions of deleted activities are deleted with them; the upgrade
  removes those left by activities deleted before.
- Editing a comment starts from the text as written (not from its HTML, which then got saved).
- Of many replies, the newest are shown and the earlier ones behind a button; they used to be cut
  off without a way to see them.
- The report includes activities in subsections (Moodle 5).
- The digest leaves out the user's own posts and is plain HTML for email clients.
- The digest task runs every 15 minutes (every minute since 1.4), unless its schedule was changed.
  Runs without new posts no longer write to the database, and a post saved while the digests were
  being sent can no longer be left out.
- New installations allow non-editing teachers what students may do.
- The JavaScript is an ES module without jQuery; the views are Mustache templates.
- Clearer setting texts; the report and pinboard pages show the course name as heading; removed the
  unlinked page `report/digest.php`.
- Tests and CI for Moodle 4.5 to 5.3; releases are published on the Moodle Marketplace.
- Documentation for teachers, students, administrators and developers in `docs/`, shown in the
  Documentation Center (tool_mdocs).

## 1.4 (2026030701)

- Moodle 5.0 and 5.1.
- The block can be added to category pages and shown in the courses of the category.
- The digest no longer fails when an activity with comments was deleted.
- The digest task runs every minute; "Users per cron" limits the users per run.

## 1.3 (2025040700)

- Moodle 4.5.

## 1.2 (2022052400)

- Moodle 4.0; maintained by bdecent gmbh.

## 3.8 (2020092100) and earlier

Developed by Synergy Learning for HWR Berlin; see the git history.
