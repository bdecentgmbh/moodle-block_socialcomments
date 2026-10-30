<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderer class for block custom comments.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_socialcomments\local\comment;
use block_socialcomments\local\comments_helper;
use block_socialcomments\local\realtime;
use block_socialcomments\local\reply;

/**
 * Renderer class for block custom comments.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments_renderer extends plugin_renderer_base {
    /**
     * Render the whole content of the block: the form, the subscription and the first page of comments.
     *
     * @param comments_helper $commentshelper
     * @param stdClass $contentdata
     * @param stdClass|null $config
     * @return string
     */
    public function render_block_content($commentshelper, $contentdata, $config) {
        $context = $commentshelper->get_context();
        $courseid = $this->page->course->id;
        $groups = comment::get_accessible_groups($context);
        $pagepinned = !empty($contentdata->formdata->pagepinned);
        $uniqid = html_writer::random_id('block_socialcomments_');

        $data = [
            'uniqid' => $uniqid,
            'reporturl' => has_capability('block/socialcomments:viewreport', context_course::instance($courseid))
                ? (new moodle_url('/blocks/socialcomments/report/newsfeed.php', ['courseid' => $courseid]))->out(false)
                : null,
            'pinboardurl' => $commentshelper->can_pin()
                ? (new moodle_url('/blocks/socialcomments/pinboard/index.php', ['courseid' => $courseid]))->out(false)
                : null,
            'canpin' => $commentshelper->can_pin(),
            'pagepinned' => $pagepinned,
            'pinpagelabel' => get_string($pagepinned ? 'unpinpage' : 'pinpage', 'block_socialcomments'),
            'form' => null,
            'cansubscribe' => $commentshelper->can_subscribe(),
            'subscribe' => [
                'id' => $uniqid . '_subscribe',
                'checked' => !empty($contentdata->comments->subscribed),
                'label' => get_string('subscribe', 'block_socialcomments'),
                'title' => get_string('subscribetitle', 'block_socialcomments'),
                'dataattributes' => [['name' => 'action', 'value' => 'subscribe']],
            ],
            'countlabel' => self::count_label($contentdata->comments->count),
            'commentspage' => $this->render_comments_page($commentshelper, $contentdata),
        ];
        $data['haslinks'] = $data['reporturl'] || $data['pinboardurl'] || $data['canpin'];

        if (comment::can_create($context, $groups)) {
            $data['form'] = [
                'type' => 'comment',
                'label' => get_string('writecomment', 'block_socialcomments'),
                'placeholder' => get_string(
                    $context->contextlevel == CONTEXT_COURSE ? 'postcommentoncourse' : 'postcommentonmod',
                    'block_socialcomments'
                ),
                'submitlabel' => get_string('post', 'block_socialcomments'),
                'groups' => count($groups) > 1 ? array_values(array_map(fn($group) => [
                    'id' => $group->id,
                    'name' => format_string($group->name, true, ['context' => $context]),
                ], $groups)) : [],
                'groupid' => count($groups) == 1 ? reset($groups)->id : null,
                'grouplabel' => get_string('group'),
            ];
        }

        $this->page->requires->js_call_amd('block_socialcomments/comments', 'init', [
            $uniqid, $context->id, (int) !empty($config->hidepins),
        ]);
        if ($token = realtime::get_token($context)) {
            $this->page->requires->js_call_amd('block_socialcomments/realtime', 'init', [
                $uniqid, $context->id, $token, (int) get_config('block_deft', 'throttle'),
            ]);
        }

        return $this->render_from_template('block_socialcomments/block', $data);
    }

    /**
     * Render a page of comments with the paging bar. This is used for AJAX requests too.
     *
     * @param comments_helper $commentshelper
     * @param stdClass $contentdata
     * @return string
     */
    public function render_comments_page($commentshelper, $contentdata) {
        $context = $commentshelper->get_context();
        $canpin = $commentshelper->can_pin();
        $canreply = reply::can_create($context);
        $groupnames = $this->group_names($context);

        $comments = [];
        foreach ($contentdata->comments->posts ?? [] as $post) {
            $actions = [];
            if (comment::can_edit($post->userid, $context)) {
                $actions['edit'] = ['i/edit', get_string('edit')];
            }
            if (comment::can_delete($post->userid, $context)) {
                $actions['delete'] = ['i/delete', get_string('delete')];
            }
            // Only the newest replies are shown, the earlier ones behind a button.
            $limit = (int) get_config('block_socialcomments', 'limitreplies');
            $hidden = $limit > 0 ? max(0, count($post->replies) - $limit) : 0;
            $replies = [];
            foreach ($post->replies as $replypost) {
                $replyactions = [];
                if (reply::can_edit($replypost->userid, $context)) {
                    $replyactions['edit'] = ['i/edit', get_string('edit')];
                }
                if (reply::can_delete($replypost->userid, $context)) {
                    $replyactions['delete'] = ['i/delete', get_string('delete')];
                }
                $replies[] = [
                    'id' => $replypost->postid,
                    'post' => $this->export_post($replypost, $context, $replyactions),
                    'hidden' => count($replies) < $hidden,
                ];
            }
            $comments[] = [
                'id' => $post->postid,
                'post' => $this->export_post($post, $context, $actions) + [
                    'groupname' => $groupnames[$post->groupid] ?? null,
                ],
                'canreply' => $canreply,
                'canpin' => $canpin,
                'pinned' => !empty($post->pinned),
                'pinlabel' => get_string(empty($post->pinned) ? 'pin' : 'unpin', 'block_socialcomments'),
                'replies' => $replies,
                'earlierreplies' => $hidden ? get_string('earlierreplies', 'block_socialcomments', $hidden) : null,
            ];
        }

        $pages = [];
        if ($contentdata->comments->maxpage > 1) {
            for ($page = 0; $page < $contentdata->comments->maxpage; $page++) {
                $pages[] = [
                    'page' => $page,
                    'label' => $page + 1,
                    'current' => $page == $contentdata->comments->currentpage,
                ];
            }
        }

        return $this->render_from_template('block_socialcomments/comments_page', [
            'comments' => $comments,
            'pages' => $pages,
            'currentpage' => $contentdata->comments->currentpage,
            'count' => $contentdata->comments->count,
            'countlabel' => self::count_label($contentdata->comments->count),
        ]);
    }

    /**
     * "3 comments".
     *
     * @param int $count
     * @return string
     */
    public static function count_label(int $count): string {
        return $count == 1
            ? get_string('onecomment', 'block_socialcomments')
            : get_string('commentscount', 'block_socialcomments', $count);
    }

    /**
     * Names of the groups of a context in separate groups mode, by id.
     *
     * @param \context $context
     * @return string[]
     */
    protected function group_names(\context $context): array {
        [$unused, $course, $cm] = get_context_info_array($context->id);
        if (comment::get_group_mode($context, $course, $cm) != SEPARATEGROUPS) {
            return [];
        }
        return array_map(
            fn($group) => format_string($group->name, true, ['context' => $context]),
            groups_get_all_groups($course->id)
        );
    }

    /**
     * Data of a comment or reply for the template block_socialcomments/post.
     *
     * @param stdClass $post with postid, userid, content, timecreated and the fields of the author
     * @param \context $context
     * @param array $actions [action => [icon, label]] for the menu of the post
     * @param bool $email for the digest: no picture, no menu, always the date
     * @return array
     */
    protected function export_post(stdClass $post, \context $context, array $actions = [], bool $email = false): array {
        $author = clone $post;
        $author->id = $post->userid;
        $time = comments_helper::format_time($post->timecreated, $email ? false : null);
        $courseid = $context->get_course_context()->instanceid;

        return [
            'fullname' => fullname($author, has_capability('moodle/site:viewfullnames', $context)),
            'userpicture' => $email ? null : $this->output->user_picture($author, ['courseid' => $courseid, 'size' => 35]),
            'time' => $time['display'],
            'exacttime' => $time['exact'],
            'datetime' => $time['iso'],
            'content' => format_text($post->content, $post->format ?? FORMAT_MOODLE, ['context' => $context, 'para' => false]),
            'raw' => isset($actions['edit']) ? $post->content : null,
            'actions' => $actions ? $this->render_actions($actions) : null,
        ];
    }

    /**
     * Render the menu with the actions for a comment or a reply.
     *
     * @param array $actions [action => [icon, label]]
     * @return string
     */
    protected function render_actions(array $actions): string {
        $menu = new action_menu();
        $menu->set_kebab_trigger(get_string('actions', 'block_socialcomments'));
        $menu->set_additional_classes('sc-post-actions');
        foreach ($actions as $action => [$icon, $label]) {
            $menu->add(new action_menu_link_secondary(
                new moodle_url('#'),
                new pix_icon($icon, ''),
                $label,
                ['data-action' => $action, 'role' => 'button']
            ));
        }
        return $this->output->render($menu);
    }

    /**
     * Render a single post, e.g. to confirm its deletion.
     *
     * @param stdClass $author user record of the author
     * @param stdClass $post the comment or reply
     * @return string
     */
    public function render_post($author, $post) {
        $data = (object) ((array) $author + (array) $post);
        $data->userid = $author->id;
        $context = context::instance_by_id($post->contextid);
        return html_writer::div(
            $this->render_from_template('block_socialcomments/post', $this->export_post($data, $context)),
            'block_socialcomments'
        );
    }

    /**
     * Render activity name and link.
     *
     * @param object $comment report data for one comment
     * @param object $reporthelper helper class for retrieving cached data.
     * @return string HTML
     */
    public function render_activity($comment, $reporthelper) {
        if (empty($comment->activityid)) {
            return '';
        }

        $visiblemodinfo = $reporthelper->get_visible_modinfo();
        if (!isset($visiblemodinfo->modules[$comment->activityid])) {
            return s($comment->activity);
        }

        $mod = $visiblemodinfo->modules[$comment->activityid];
        $icon = html_writer::img($mod->iconurl, '', ['class' => 'icon activityicon']);
        return html_writer::link($mod->url, $icon . html_writer::span($mod->name, 'instancename'));
    }

    /**
     * Data of the comments of a course, by page, for the template block_socialcomments/overview.
     *
     * @param stdClass $course
     * @param array $commentsdata [contextid => [commentid => {comment, replies}]]
     * @param bool $email for the digest
     * @return array
     */
    protected function export_overview(stdClass $course, array $commentsdata, bool $email = false): array {
        $modinfo = get_fast_modinfo($course);
        $format = course_get_format($course);
        $coursecontext = context_course::instance($course->id);

        $pages = [];
        if (!empty($commentsdata[$coursecontext->id])) {
            $pages[] = [
                'name' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'iconurl' => null,
                'sectionname' => null,
                'comments' => $this->export_threads($commentsdata[$coursecontext->id], $coursecontext, $email),
            ];
        }
        foreach ($modinfo->get_section_info_all() as $section) {
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (empty($commentsdata[$cm->context->id])) {
                    continue;
                }
                $pages[] = [
                    'name' => $cm->get_formatted_name(),
                    'url' => $cm->url ? $cm->url->out(false) : null,
                    'iconurl' => $email ? null : $cm->get_icon_url()->out(false),
                    'sectionname' => $format->get_section_name($section),
                    'comments' => $this->export_threads($commentsdata[$cm->context->id], $cm->context, $email),
                ];
            }
        }
        return $pages;
    }

    /**
     * Data of comments with their replies, without actions.
     *
     * @param array $threads [commentid => {comment, replies}]
     * @param \context $context
     * @param bool $email
     * @return array
     */
    protected function export_threads(array $threads, \context $context, bool $email): array {
        $groupnames = $this->group_names($context);
        $comments = [];
        foreach ($threads as $thread) {
            $comments[] = [
                'id' => $thread->comment->postid,
                'post' => $this->export_post($thread->comment, $context, [], $email) + [
                    'groupname' => $groupnames[$thread->comment->groupid] ?? null,
                ],
                'replies' => array_values(array_map(fn($reply) => [
                    'id' => $reply->postid,
                    'post' => $this->export_post($reply, $context, [], $email),
                ], $thread->replies)),
            ];
        }
        return $comments;
    }

    /**
     * Render the content of the new comments tab.
     *
     * @param object $course
     * @param array $newcommentsdata
     * @param int $timesince
     * @return string
     */
    public function render_new_comments_tab($course, $newcommentsdata, $timesince) {
        if (empty($newcommentsdata)) {
            $timesincestr = userdate($timesince, get_string('strftimedatetimeshort', 'langconfig'));
            return $this->output->notification(
                get_string('nonewcommentssince', 'block_socialcomments', $timesincestr),
                \core\output\notification::NOTIFY_INFO,
                false
            );
        }
        return $this->render_from_template('block_socialcomments/overview', [
            'pages' => $this->export_overview($course, $newcommentsdata),
        ]);
    }

    /**
     * Render the pinboard.
     *
     * @param object $course
     * @param array $pinnedcommentsdata
     * @return string
     */
    public function render_pinboard($course, $pinnedcommentsdata) {
        $courses = [];
        foreach (enrol_get_my_courses() as $mycourse) {
            $coursecontext = context_course::instance($mycourse->id);
            if (has_capability('block/socialcomments:pinitems', $coursecontext)) {
                $url = new moodle_url('/blocks/socialcomments/pinboard/index.php', ['courseid' => $mycourse->id]);
                $courses[$url->out(false)] = format_string($mycourse->fullname, true, ['context' => $coursecontext]);
            }
        }
        $selected = new moodle_url('/blocks/socialcomments/pinboard/index.php', ['courseid' => $course->id]);
        $select = new url_select($courses, $selected->out(false), null, 'block_socialcomments_courses');
        $select->set_label(get_string('course'));

        $coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
        return $this->render_from_template('block_socialcomments/pinboard', [
            'courseselect' => count($courses) > 1 ? $this->output->render($select) : null,
            'pages' => $pinnedcommentsdata ? $this->export_overview($course, $pinnedcommentsdata) : [],
            'nopins' => get_string('nopinnedcomments', 'block_socialcomments', $coursename),
        ]);
    }

    /**
     * Render the digest of a course.
     *
     * @param object $course
     * @param array $newcommentsdata
     * @return string
     */
    public function render_digest($course, $newcommentsdata) {
        return $this->render_from_template('block_socialcomments/digest', [
            'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
            'pages' => $this->export_overview($course, $newcommentsdata, true),
        ]);
    }
}
