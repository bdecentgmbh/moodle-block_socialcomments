# Social comments

[![Moodle plugin CI](https://github.com/bdecentgmbh/moodle-block_socialcomments/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/bdecentgmbh/moodle-block_socialcomments/actions/workflows/moodle-ci.yml)

Social comments is a Moodle block that lets participants talk about a course page right where it
is: ask a question about a reading, explain something to each other, organise group work, share
tips or ask for feedback. Add it to the course page, to activity pages, or to all pages of a course.

## Features

- **Comments and replies on each page**: every course and activity page has its own conversation.
  Edit and delete your own posts; teachers can delete all.
- **Groups**: in activities with separate groups, each group talks among itself; teachers can post to
  all groups.
- **Pins and the pinboard**: pin single comments, or all comments of a page, and find them on your
  pinboard, e.g. to revise before an exam.
- **Subscriptions**: follow a page and get a digest of new comments as a notification or email.
- **Comments report**: the new comments since your last login, and all comments of the course with
  filters.
- **Real-time updates** with the [Deft response block](https://moodle.org/plugins/block_deft)
  (optional): new comments appear without reloading the page.
- **"5 minutes ago"** or the date and time, as the administrator prefers.
- **Privacy API, backup and restore** with the comments of each page.

## Requirements

Moodle 4.5 to 5.3. Real-time updates need block_deft with its updating service.

## Installation

1. Download the plugin or clone it into `blocks/socialcomments` (`public/blocks/socialcomments` from
   Moodle 5.1).
2. Go to *Site administration > Notifications* to complete the installation.
3. Review the settings under *Site administration > Plugins > Blocks > Social Comments*.

## Documentation

- [Teacher guide](docs/teacher-guide.md): adding the block, ideas for using it, groups, pins,
  subscriptions, the report
- [Student guide](docs/student-guide.md)
- [Administrator guide](docs/admin-guide.md): installation, upgrading, settings, digests, real-time
  updates, capabilities, privacy
- [Developer guide](docs/developer-guide.md): how the block works, tests and releases

With the Documentation Center (`tool_mdocs`) installed, the guides are also offered on the course
page and the pages of the block.

## Credits

Social comments was developed by [Synergy Learning](https://www.synergy-learning.com) based on a
concept by [Björn Lefers](https://www.lefers.com) for a blended learning degree programme in
Business Administration at [HWR Berlin](https://www.hwr-berlin.de), with contributions by Paul
Steffen (EDU-Werkstatt GmbH). Since 2022 it is maintained by [bdecent gmbh](https://bdecent.de).

Issues and pull requests: https://github.com/bdecentgmbh/moodle-block_socialcomments

## License

Copyright (C) 2017 Andreas Wagner, Synergy Learning
Copyright (C) 2022 onwards bdecent gmbh <https://bdecent.de>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not,
see <https://www.gnu.org/licenses/>.
