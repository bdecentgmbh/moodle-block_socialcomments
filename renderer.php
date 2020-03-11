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
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

use block_socialcomments\local\comment;
use block_socialcomments\local\reply;

/**
 * Renderer class for block custom comments.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments_renderer extends plugin_renderer_base {

    protected function render_post_form($idtextarea, $idcancel, $idaction, $straction, $placeholder = '', $groupselector = '') {

        $html = '';

        // Print posting textarea.
        $textareaattrs = array(
            'name' => $idtextarea,
            'rows' => 2,
            'id' => $idtextarea,
            'placeholder' => $placeholder,
            'data-commentid' => 0,
        );

        $textareaattrs['class'] = 'fullwidth';

        $html .= html_writer::start_tag('div', array('class' => 'ccomment-form-textarea-wrap'));
        $html .= html_writer::start_tag('div', array('class' => 'form-control'));
        $html .= html_writer::tag('textarea', '', $textareaattrs);
        $html .= html_writer::end_tag('div');

        $buttons = $groupselector;
        $buttons .= html_writer::tag(
                'button',
                get_string('cancel'),
                array('id' => $idcancel, 'class' => 'btn')
            );
        $buttons .= html_writer::tag(
                'button',
                get_string($straction, 'block_socialcomments'),
                array('id' => $idaction, 'class' => 'btn')
            );

        $html .= html_writer::div($buttons, 'ccomment-form-action-buttons');

        $html .= html_writer::end_tag('div');

        $html .= html_writer::tag('div', '', array('class' => 'clearer'));

        return $html;
    }

    protected function render_slider_checkbox($name, $value, $checked, $label, $attributes) {

        $attributes = (array) $attributes;
        $output = '';

        if ($label !== '' and ! is_null($label)) {
            if (empty($attributes['id'])) {
                $attributes['id'] = self::random_id('checkbox_');
            }
        }
        $attributes['type'] = 'checkbox';
        $attributes['value'] = $value;
        $attributes['name'] = $name;
        $attributes['checked'] = $checked ? 'checked' : null;

        $checkbox = html_writer::empty_tag('input', $attributes);

        $yestag = html_writer::tag('div', get_string('yes'), array('class' => 'labelyes'));
        $notag = html_writer::tag('div', get_string('no'), array('class' => 'labelno'));

        if ($label !== '' and ! is_null($label)) {
            $slider = html_writer::tag('div', $yestag . $notag, array('class' => 'slider'));
            $output = html_writer::tag('div', $label, array('class' => 'cc-switch-label'));
            $label = html_writer::tag('label', $checkbox . $slider, array('for' => $attributes['id']));
        }

        $output .= html_writer::tag('div', $label, array('class' => 'cc-switch'));

        return $output;
    }

    protected function render_group_selector($groups) {

        if (count($groups) == 0) {
            return html_writer::empty_tag('input', array('type' => 'hidden', 'value' => -1, 'id' => 'ccomments-groupid'));
        }

        if (count($groups) == 1) {
            $group = reset($groups);
            return html_writer::empty_tag('input', array('type' => 'hidden', 'value' => $group->id, 'id' => 'ccomments-groupid'));
        }

        $choices = array();
        foreach ($groups as $group) {
            $choices[$group->id] = $group->name;
        }
        return html_writer::select($choices, 'groupid', '', false, array('id' => 'ccomments-groupid'));
    }

    /**
     * Render the post comment form.
     * Please note that the form is not wrapped into a form tag, its based on
     * AJAX submits.
     *
     * @param object $commentshelper
     * @param object $contentdata
     * @return string the HTML for the form
     */
    public function render_form_content($commentshelper, $contentdata) {
        global $COURSE;

        $context = $commentshelper->get_context();

        $html = '';
        $top = '';

        if ($commentshelper->can_subscribe()) {
            $url = new moodle_url('/blocks/socialcomments/report/newsfeed.php', array('courseid' => $COURSE->id));
            $params = array('title' => '');
            $text = $this->output->pix_icon(
                    'reportspage',
                    get_string('reportspage', 'block_socialcomments'),
                    'block_socialcomments', $params
                );
            $text .= html_writer::tag('span', get_string('reportspage', 'block_socialcomments'));
            $link = html_writer::link($url, $text, array('class' => 'ccomment-form-navlink cctooltip'));
            $top .= html_writer::div($link, 'ccomment-form-navlink-wrap');
        }
        if ($commentshelper->can_pin()) {
            $url = new moodle_url('/blocks/socialcomments/pinboard/index.php', array('courseid' => $COURSE->id));
            $text = $this->output->pix_icon(
                    'pinboard',
                    get_string('pinboard', 'block_socialcomments'),
                    'block_socialcomments',
                    $params
                );
            $text .= html_writer::tag('span', get_string('pinboard', 'block_socialcomments'));
            $link = html_writer::link($url, $text, array('class' => 'ccomment-form-navlink cctooltip'));
            $top .= html_writer::div($link, 'ccomment-form-navlink-wrap');
        }
        if ($commentshelper->can_pin()) {
            $pagepinned = !empty($contentdata->formdata->pagepinned);

            $params = array(
                'id' => 'ccomment-pinned-0',
            );

            $cb = html_writer::checkbox('pagepinned', 1, !empty($pagepinned), '#', $params);
            $tooltip = (!empty($pagepinned))
                    ? get_string('unpinpage', 'block_socialcomments')
                    : get_string('pinpage', 'block_socialcomments');
            $cb .= html_writer::div($tooltip, 'ccomments-pin-tooltip');

            $top .= html_writer::div($cb, 'ccomment-form-pagepinned-wrap');
        }

        $html .= html_writer::div($top, 'ccomment-form-top  clearfix');

        $groups = comment::get_accessible_groups($context);

        if (comment::can_create($context, $groups)) {

            $groupselector = $this->render_group_selector($groups);

            $strcourse = get_string('postcommentoncourse', 'block_socialcomments');

            $placeholder = ($context->contextlevel == CONTEXT_COURSE)
                    ? $strcourse
                    : get_string('postcommentonmod', 'block_socialcomments');

            $html .= $this->render_post_form(
                    'ccomment-form-textarea',
                    'ccomment-form-action-cancel',
                    'ccomment-form-action-post',
                    'post',
                    $placeholder,
                    $groupselector
            );
        }

        // Render two additional forms, one for editing a comment/reply and one for posting a reply.
        // Both are wrapped into a hidden container.
        $editform = $this->render_post_form(
            'ccomment-edit-textarea', 'ccomment-edit-action-cancel', 'ccomment-edit-action-save', 'save'
        );
        $editform = html_writer::div($editform, 'ccomment-editform', array('id' => 'ccomment-editform'));
        $html .= html_writer::div($editform, 'ccomment-editform hidden', array('id' => 'ccomment-editform-wrap'));

        $replyform = $this->render_post_form(
            'ccomment-reply-textarea', 'ccomment-reply-action-cancel', 'ccomment-reply-action-save', 'reply'
        );
        $replyform = html_writer::div($replyform, 'ccomment-replyform', array('id' => 'ccomment-replyform'));
        $html .= html_writer::div($replyform, 'ccomment-replyform hidden', array('id' => 'ccomment-replyform-wrap'));

        return $html;
    }

    /**
     * Render the part of block content, that is related to comments.
     * - Information
     * - Subscription
     * - Comments list
     *
     * @param object $commentshelper
     * @param object $contentdata
     * @return string
     */
    public function render_comments_content($commentshelper, $contentdata) {

        $commentscount = get_string('commentscount', 'block_socialcomments', $contentdata->comments->count);
        $top = html_writer::div($commentscount, 'ccomment-comments-count');

        $subscribed = !empty($contentdata->comments->subscribed);

        $params = array(
            'id' => 'ccomment-comments-subscribed'
        );

        if ($commentshelper->can_subscribe()) {
            $cb = $this->render_slider_checkbox(
                    'subscribed',
                    1,
                    $subscribed,
                    get_string('subscribed', 'block_socialcomments'),
                    $params
                );
            $top .= html_writer::div($cb, 'ccomment-comments-subscribed-wrap');
        }
        $visibleclass = "";
        if ((!$contentdata->comments->count) and ( $contentdata->comments->count == 0)) {
            $visibleclass = "hidden";
        }

        $html = html_writer::div($top, "clearfix $visibleclass", array('id' => 'ccomment-comments-content-top'));

        $commentspage = $this->render_comments_page($commentshelper, $contentdata);

        $html .= html_writer::div($commentspage, 'ccomment-commentspage', array('id' => 'ccomment-commentspage-wrap'));

        return $html;
    }

    /**
     * Render the pagingbar.
     *
     * @param object $commentshelper
     * @param object $contentdata
     * @return string
     */
    protected function render_paging_bar($commentshelper, $contentdata) {

        if ($contentdata->comments->maxpage == 0) {
            return '';
        }

        $html = '';
        for ($i = $contentdata->comments->minpage; $i < $contentdata->comments->maxpage; $i++) {
            $class = ($i == $contentdata->comments->currentpage) ? 'ccomment-pagingbar-curpagelink' : 'ccomment-pagingbar-pagelink';
            $html .= html_writer::link('#', $i + 1, array('id' => 'ccomment-pagelink-' . $i, 'class' => $class));
        }

        return html_writer::div($html, 'ccomment-paging-bar');
    }

    /**
     * Render the action menu for editing and deleting a comment or a reply.
     *
     * @param array $menulinks
     * @return string
     */
    protected function render_action_menu($menulinks) {

        if (!$menulinks) {
            return '';
        }

        $am = new action_menu($menulinks, 10);

        $am->set_menu_trigger(
            $this->output->pix_icon('options', get_string('actions', 'block_socialcomments'), 'block_socialcomments')
        );

        return $this->output->render($am);
    }

    /**
     * Render a post (a comment or a reply)
     *
     * @param object $author user object of author
     * @param object $post
     * @param array $menulinks for action buttons
     * @return string
     */
    public function render_post($author, $post, $menulinks = array()) {

        $h = $this->render_action_menu($menulinks);

        $up = $this->output->user_picture($author);
        $h .= html_writer::div($up, 'ccomment-post-userpicture');

        $h .= html_writer::div(fullname($author), 'ccomment-post-fullname');

        $ud = userdate(
                $post->timecreated,
                get_string('strftimedatefullshort', 'langconfig') . ' ' . get_string('strftimetime', 'langconfig')
            );
        $h .= html_writer::div($ud, 'ccomment-post-timeposted');

        $p = html_writer::div($h, 'ccomment-post-header clearfix');

        // Apply filters to content as done in block_comments.
        $content = format_text($post->content, FORMAT_MOODLE, array('para' => false));
        $p .= html_writer::div($content, 'ccomment-post-content');

        return $p;
    }

    /**
     * Render the list of replies.
     *
     * @param array $replies
     * @return string
     */
    protected function render_reply_list($context, $commentid, $replies) {

        $listitems = '';

        foreach ($replies as $post) {

            $menulinks = array();
            if (reply::can_edit($post->userid, $context)) {
                $menulinks[] = html_writer::link(
                        '#',
                        get_string('edit'),
                        array('id' => 'ccomment-reply-action-edit-' . $post->postid)
                    );
            }
            if (reply::can_delete($post->userid, $context)) {
                $menulinks[] = html_writer::link(
                        '#',
                        get_string('delete'),
                        array('id' => 'ccomment-reply-action-delete-' . $post->postid)
                    );
            }

            $p = $this->render_post($post, $post, $menulinks);
            $p = html_writer::div($p, 'ccomment-reply-wrap');
            $listitems .= html_writer::tag(
                    'li',
                    $p,
                    array('class' => 'ccomment-reply-item', 'id' => 'ccomment-reply-' . $post->postid)
                );
        }

        return html_writer::tag(
                'ul',
                $listitems,
                array('id' => 'ccomment-reply-list-' . $commentid, 'class' => 'ccomment-reply-list')
            );
    }

    /**
     * Render a comments page (= comments and pagingbar). This is used for AJAX-Requests too.
     *
     * @param object $commentshelper
     * @param object $contentdata
     * @return string
     */
    public function render_comments_page($commentshelper, $contentdata) {

        if (empty($contentdata->comments->posts)) {
            return '';
        }

        $context = $commentshelper->get_context();

        $commentslistitems = '';
        $canpin = $commentshelper->can_pin();
        $canreply = reply::can_create($context);

        foreach ($contentdata->comments->posts as $post) {

            $menulinks = array();

            if (comment::can_edit($post->userid, $context)) {
                $menulinks[] = html_writer::link(
                        '#',
                        get_string('edit'),
                        array('id' => 'ccomment-post-action-edit-' . $post->postid)
                    );
            }
            if (comment::can_delete($post->userid, $context)) {
                $menulinks[] = html_writer::link(
                        '#',
                        get_string('delete'),
                        array('id' => 'ccomment-post-action-delete-' . $post->postid)
                    );
            }
            $p = $this->render_post($post, $post, $menulinks);
            $p = html_writer::div($p, 'ccomment-post', array('id' => 'ccomment-post-' . $post->postid));

            $f = '';
            if ($canreply) {
                $f = html_writer::link(
                        '#',
                        get_string('reply', 'block_socialcomments'),
                        array('id' => 'ccomment-reply-' . $post->postid)
                    );
            }

            if ($canpin) {
                $attr = array('id' => 'ccomment-pinned-' . $post->postid);
                $cb = html_writer::checkbox('commentpinned-' . $post->postid, 1, !empty($post->pinned), '#', $attr);
                $tooltip = (!empty($post->pinned))
                        ? get_string('unpin', 'block_socialcomments')
                        : get_string('pin', 'block_socialcomments');
                $cb .= html_writer::div($tooltip, 'ccomments-pin-tooltip');
                $f .= html_writer::div($cb, 'ccomment-post-pinned-wrap');
            }

            $p .= html_writer::div($f, 'comments-post-footer clearfix');

            $p .= $this->render_reply_list($context, $post->postid, $post->replies);

            $commentslistitems .= html_writer::tag('li', $p, array('id' => 'ccomment-comment-listitem-' . $post->postid));
        }

        $html = html_writer::tag('ul', $commentslistitems, array('class' => 'ccomment-post-wrap'));
        $html .= $this->render_paging_bar($commentshelper, $contentdata);

        return $html;
    }

    /**
     * Render the whole content area of the block, which means:
     * - render the posting form
     * - render the comments content (= commentsinfo, subscribe and commentpages)
     *
     * @param object $commentshelper
     * @param object $contentdata
     * @return string
     */
    public function render_block_content($commentshelper, $contentdata) {
        $html = html_writer::start_tag('div', array('id' => 'ccomment-form-content'));
        $html .= $this->render_form_content($commentshelper, $contentdata);
        $html .= html_writer::end_tag('div');

        // Wrap this in div element for AJAX use, so not change id!
        $html .= html_writer::start_tag('div', array('id' => 'ccomment-comments-content'));
        $html .= $this->render_comments_content($commentshelper, $contentdata);
        $html .= html_writer::end_tag('div');

        $args = array();
        $args['contextid'] = $commentshelper->get_context()->id;
        $args['subscribed'] = $contentdata->comments->subscribed;
        $args['commentscount'] = $contentdata->comments->count;

        $this->page->requires->js_call_amd('block_socialcomments/comments', 'init', array($args));

        return $html;
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
            return $comment->activity;
        }

        $mod = $visiblemodinfo->modules[$comment->activityid];

        $icon = html_writer::empty_tag('img', array('src' => $mod->iconurl,
                'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .
            html_writer::tag('span', $mod->name, array('class' => 'instancename'));

        $link = html_writer::link($mod->url, $icon, array('target' => '_blank'));

        return html_writer::div($link, 'activity');
    }

    protected function render_new_activity_comments($course, $newcomments) {
        global $CFG;

        require_once($CFG->dirroot . '/course/format/lib.php');

        $course = course_get_format($course)->get_course();

        $modinfo = get_fast_modinfo($course);

        $html = '';
        foreach ($modinfo->get_section_info_all() as $section => $thissection) {

            if ($section > course_get_format($course)->get_last_section_number()) {
                continue;
            }

            if (!empty($modinfo->sections[$thissection->section])) {

                $sectioncommenhtml = '';

                foreach ($modinfo->sections[$thissection->section] as $modnumber) {

                    $mod = $modinfo->cms[$modnumber];

                    if (!isset($newcomments[$mod->context->id])) {
                        continue;
                    }

                    // Print mod.
                    $text = \html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                            'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation')) .
                        html_writer::tag('span', $mod->get_formatted_name(), array('class' => 'instancename'));

                    $link = html_writer::link($mod->url, $text, array('target' => '_blank'));

                    $sectioncommenhtml .= \html_writer::div($link, 'activity');

                    $commentslistitems = '';
                    // Print each comment.
                    foreach ($newcomments[$mod->context->id] as $commentdata) {

                        $post = $this->render_post($commentdata->comment, $commentdata->comment);
                        $commenthtml = html_writer::div($post, 'ccomment-post');

                        $listitems = '';
                        foreach ($commentdata->replies as $reply) {

                            $p = $this->render_post($reply, $reply);
                            $p = html_writer::div($p, 'ccomment-reply-wrap');
                            $listitems .= html_writer::tag('li', $p, array('class' => 'ccomment-reply-item'));
                        }
                        $commenthtml .= html_writer::tag('ul', $listitems, array('class' => 'ccomment-reply-list'));
                        $commentslistitems .= html_writer::tag('li', $commenthtml);
                    }
                    $sectioncommenhtml .= html_writer::tag('ul', $commentslistitems, array('class' => 'ccomment-post-wrap'));
                }

                if (!empty($sectioncommenhtml)) {

                    $sectionname = get_section_name($course, $thissection);
                    $sectionurl = new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $thissection->section]);
                    $link = \html_writer::link($sectionurl, $sectionname);

                    $sectioncommenhtml = \html_writer::tag('h3', $link) . $sectioncommenhtml;
                }

                $html .= $sectioncommenhtml;
            }
        }

        return $html;
    }

    protected function render_comments_overview($course, $commentsdata) {

        // Render activity comments.
        $html = $this->render_new_activity_comments($course, $commentsdata);

        $coursecontext = context_course::instance($course->id);

        // Render comment with course context.
        if (isset($commentsdata[$coursecontext->id])) {

            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            $courselink = \html_writer::link($url, $course->fullname);

            $html .= \html_writer::tag('h3', $courselink);
            $commentslistitems = '';

            // Print each comment.
            foreach ($commentsdata[$coursecontext->id] as $commentdata) {

                $post = $this->render_post($commentdata->comment, $commentdata->comment);
                $commenthtml = html_writer::div($post, 'ccomment-post');

                $replylistitems = '';
                foreach ($commentdata->replies as $reply) {

                    $post = $this->render_post($reply, $reply);

                    $p = html_writer::div($post, 'ccomment-reply-wrap');
                    $replylistitems .= html_writer::tag('li', $p, array('class' => 'ccomment-reply-item'));
                }
                $commenthtml .= html_writer::tag('ul', $replylistitems, array('class' => 'ccomment-reply-list'));
                $commentslistitems .= html_writer::tag('li', $commenthtml);
            }
            $html .= html_writer::tag('ul', $commentslistitems, array('class' => 'ccomment-post-wrap'));
        }

        return $html;
    }

    /**
     * Render the content of the new comments tab.
     *
     * @param array $newcomments
     * @param int $timesince
     * @return string
     */
    public function render_new_comments_tab($course, $newcommentsdata, $timesince) {

        $timesincestr = userdate($timesince, get_string('strftimedatetimeshort', 'langconfig'));

        if (empty($newcommentsdata)) {
            return get_string('nonewcommentssince', 'block_socialcomments', $timesincestr);
        }

        return $this->render_comments_overview($course, $newcommentsdata);
    }

    public function render_pinboard($course, $pinnedcommentsdata) {
        global $COURSE;

        $html = '';

        $mycourses = enrol_get_my_courses('format');

        $coursesmenu = array();

        foreach ($mycourses as $mycourse) {

            $coursecontext = context_course::instance($mycourse->id);

            if (has_capability('block/socialcomments:pinitems', $coursecontext)) {
                $url = new moodle_url('/blocks/socialcomments/pinboard/index.php', array('courseid' => $mycourse->id));
                $coursesmenu[$url->out()] = $mycourse->fullname;
            }
        }

        $selectedurl = new moodle_url('/blocks/socialcomments/pinboard/index.php', array('courseid' => $COURSE->id));

        $selecthtml = html_writer::tag('span', get_string('course'));

        $select = new url_select($coursesmenu, $selectedurl, null);
        $select->class = 'jumpmenu';
        $select->formid = 'coursesmenu';
        $selecthtml .= $this->output->render($select);

        $html .= html_writer::div($selecthtml, 'ccomments-courseselect');

        if (empty($pinnedcommentsdata)) {
            $html .= get_string('nopinnedcomments', 'block_socialcomments', $course->fullname);
            return $html;
        }

        $html .= $this->render_comments_overview($course, $pinnedcommentsdata);

        return $html;
    }

    public function render_digest($course, $newcommentsdata) {

        $html = html_writer::tag('h2', $course->fullname, array('class' => 'ccomment-digest-courseheader'));
        $html .= $this->render_comments_overview($course, $newcommentsdata);

        return $html;
    }

}
