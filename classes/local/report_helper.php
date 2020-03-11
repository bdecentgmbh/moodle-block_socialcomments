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
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\local;

defined('MOODLE_INTERNAL') || die;

class report_helper {

    private $visiblemodinfo;
    private $sqlgrouprestriction = array();
    private $grouprestriction = array();

    /**
     * Get an instance of an object of this class. Create as a singleton.
     *
     * @staticvar report_helper $reporthelper
     * @param boolean $forcenewinstance true, when a new instance should be created.
     * @return report_helper
     */
    public static function get_instance($forcenewinstance = false) {
        static $reporthelper;

        if (isset($reporthelper) && !$forcenewinstance) {
            return $reporthelper;
        }

        $reporthelper = new report_helper();
        return $reporthelper;
    }

    /**
     * Get all the modules this user might see.
     */
    public function get_visible_modinfo() {
        global $COURSE, $CFG;

        if (isset($this->visiblemodinfo)) {
            return $this->visiblemodinfo;
        }

        require_once($CFG->dirroot . '/lib/modinfolib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $course = course_get_format($COURSE)->get_course();
        $modinfo = get_fast_modinfo($course);

        $visiblesections = array();
        $visiblemodules = array();
        $visiblecontexts = array();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {

            if ($section > course_get_format($course)->get_last_section_number()) {
                continue;
            }
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available &&
                !empty($thissection->availableinfo));

            if (!$showsection) {
                continue;
            }

            $visiblesection = new \stdClass();
            $visiblesection->name = get_section_name($course, $section);
            $visiblesection->modids = array();

            if (!empty($modinfo->sections[$thissection->section])) {

                foreach ($modinfo->sections[$thissection->section] as $modnumber) {

                    $mod = $modinfo->cms[$modnumber];

                    if (!$mod->uservisible) {
                        continue;
                    }

                    $visiblemodule = new \stdClass();
                    $visiblemodule->name = $mod->get_formatted_name();
                    $visiblemodule->sectionid = $thissection->id;
                    $visiblemodule->contextid = $mod->context->id;
                    $visiblemodule->iconurl = $mod->get_icon_url();
                    $visiblemodule->altname = $mod->modfullname;
                    $visiblemodule->url = $mod->url;
                    $visiblemodules[$mod->id] = $visiblemodule;

                    $visiblesection->modids[$mod->id] = $mod->id;
                    $visiblesection->contextids[$mod->context->id] = $mod->context->id;

                    $visiblecontexts[$mod->context->id] = $mod->context;
                }

                $visiblesections[$thissection->id] = $visiblesection;
            }
        }

        $this->visiblemodinfo = new \stdClass();
        $this->visiblemodinfo->sections = $visiblesections;
        $this->visiblemodinfo->modules = $visiblemodules;
        $this->visiblemodinfo->contexts = $visiblecontexts;

        return $this->visiblemodinfo;
    }

    protected function get_group_restriction_sql($context) {

        if (!isset($this->sqlgrouprestriction[$context->id])) {
            $this->sqlgrouprestriction[$context->id] = comment::get_group_restriction_sql($context);
        }

        return $this->sqlgrouprestriction[$context->id];
    }

    /**
     * Get a subselect for each context to retrieve the comments per course.
     * Each subselect would retrieve only a few item, but there might be as many
     * subselects, as commented contexts in course.
     *
     * @param object $args, data needed for the sql
     * @param object $filterdata, data from the filter form
     * @return string SQL for one subselect.
     */
    private function get_module_comments_sql($args, $filterdata) {
        global $DB;

        $context = $args->context;

        $select = "SELECT cc.*, {$args->usernamefields}, '{$args->activity}' as activity,
                   '{$args->activityid}' as activityid, {$args->commentscount} as commentscount,
                   '{$args->topicname}' as topicname ";

        $selectcount = "SELECT cc.id ";

        $from = "FROM {block_socialcomments_cmmnts} cc
                 JOIN {user} u ON u.id = cc.userid ";

        $fromcount = "FROM {block_socialcomments_cmmnts} cc ";

        $params = array();

        $cond = array();
        $cond[] = " cc.contextid = {$context->id} ";

        if (!empty($filterdata->content)) {
            $cond[] = $DB->sql_like('cc.content', ':content' . $context->id, false);
            $params['content' . $context->id] = '%' . $filterdata->content . '%';
        }

        if (!empty($filterdata->author)) {

            $cond1 = $DB->sql_like('u.firstname', ':firstname' . $context->id, false);
            $params['firstname' . $context->id] = $filterdata->author . '%';
            $cond2 = $DB->sql_like('u.lastname', ':lastname' . $context->id, false);
            $params['lastname' . $context->id] = $filterdata->author . '%';

            $cond[] = "($cond1 OR $cond2)";

            $fromcount .= "JOIN {user} u ON u.id = cc.userid ";
        }

        if (!empty($filterdata->fromdate)) {
            $cond[] = 'cc.timecreated >= :fromdate' . $context->id;
            $params['fromdate' . $context->id] = $filterdata->fromdate;
        }

        if (!empty($filterdata->todate)) {
            $cond[] = 'cc.timecreated <= :todate' . $context->id;
            $params['todate' . $context->id] = $filterdata->todate + DAYSECS;
        }

        $where = 'WHERE ' . implode(' AND ', $cond);

        // Control visibility of group.
        list($ingroupsql, $ingroupparam) = $this->get_group_restriction_sql($context);
        $where .= $ingroupsql;
        $params += $ingroupparam;

        $sql = $select . $from . $where;
        $sqlcount = $selectcount . $fromcount . $where;
        return array($sql, $sqlcount, $params);
    }

    /**
     * Retrieve comments from all contexts within the course is a little bit tricky.
     * Unfortunately we cannot retrieve this without using multiple unions, because
     * we must take group mode into account and mix in sectionname and modenames.
     *
     * So we reduce the number of unions as far as possible.
     *
     * @param object $filterdata, data from the filterform.
     * @param \flexible_table $table
     * @param int $perpage
     * @param boolean $download
     * @return array
     */
    public function get_course_comments($filterdata, $table = null, $perpage = 0, $download = false) {
        global $DB, $COURSE;

        $usernamefields = get_all_user_name_fields(true, 'u');

        // Get visible contexts.
        $modinfo = $this->get_visible_modinfo();

        // Get commentscount per context.
        if (!empty($filterdata->activityid)) {
            $contextids = array($modinfo->modules[$filterdata->activityid]->contextid);
        }

        if (empty($filterdata->activityid) && (!empty($filterdata->sectionid))) {
            $contextids = $modinfo->sections[$filterdata->sectionid]->contextids;
        }

        if (empty($filterdata->activityid) && (empty($filterdata->sectionid))) {
            $contextids = array_keys($modinfo->contexts);
        }

        $params = array();
        $subselect = array();
        $subselectcount = array();

        if (!empty($contextids)) {

            list($incontext, $incontextparam) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

            // Get counts per context.
            $sql = "SELECT cc.contextid, count(cc.id) as count
                FROM {block_socialcomments_cmmnts} cc
                WHERE cc.contextid {$incontext}
                GROUP BY cc.contextid ";

            $countpercontext = $DB->get_records_sql($sql, $incontextparam);

            foreach ($modinfo->sections as $sectioninfo) {

                $topicname = $sectioninfo->name;

                foreach ($sectioninfo->modids as $modid) {

                    if (empty($modinfo->modules[$modid])) {
                        continue;
                    }

                    $module = $modinfo->modules[$modid];
                    if (empty($countpercontext[$module->contextid])) {
                        continue;
                    }

                    $sqlvalues = (object) array(
                            'usernamefields' => $usernamefields,
                            'topicname' => $topicname,
                            'activity' => $module->name,
                            'activityid' => $modid,
                            'commentscount' => $countpercontext[$module->contextid]->count,
                            'context' => $modinfo->contexts[$module->contextid],
                    );

                    list($subsql, $countsql, $subparam) = $this->get_module_comments_sql($sqlvalues, $filterdata);

                    $subselect[] = "($subsql)";
                    $subselectcount[] = "($countsql)";
                    $params += $subparam;
                }
            }
        }

        if ((empty($filterdata->sectionid)) && empty($filterdata->activityid)) {

            $coursecontext = \context_course::instance($filterdata->courseid);
            $commentscount = $DB->count_records('block_socialcomments_cmmnts', array('contextid' => $coursecontext->id));

            $sqlvalues = (object) array(
                    'usernamefields' => $usernamefields,
                    'topicname' => get_string('course'),
                    'activity' => '',
                    'activityid' => 0,
                    'commentscount' => $commentscount,
                    'context' => $coursecontext
            );

            list($subsql, $countsql, $subparam) = $this->get_module_comments_sql($sqlvalues, $filterdata);

            $subselect[] = "($subsql)";
            $subselectcount[] = "($countsql)";
            $params += $subparam;
        }

        $sql = implode(' UNION ALL ', $subselect);
        if (empty($sql)) {
            return array();
        }

        // Page size of table.
        $sqlcount = implode(' UNION ALL ', $subselectcount);
        $total = $DB->count_records_sql("SELECT count(id) FROM ($sqlcount) as uc ", $params);

        if (!$download and isset($table)) {
            $tableperpage = ($perpage == 0) ? $total : $perpage;
            $table->pagesize($tableperpage, $total);
            $limitfrom = $table->get_page_start();
        } else {
            $limitfrom = 0;
            $perpage = 0;
        }

        $orderby = '';
        if ($table) {
            $orderby = " ORDER BY " . $table->get_sql_sort() . ", id DESC";
        }

        return $DB->get_records_sql($sql . $orderby, $params, $limitfrom, $perpage);
    }

    /**
     * Get an array of all sectionnames, that are visible to this user, indexed by
     * id of section.
     *
     * @return array
     */
    public function get_visible_section_menu() {

        $modinfo = $this->get_visible_modinfo();

        $sectionmenu = array();
        foreach ($modinfo->sections as $sectionid => $section) {
            $sectionmenu[$sectionid] = $section->name;
        }

        return $sectionmenu;
    }

    /**
     * Get all the mod names indexed by the id of the moduls in the section
     * that are visible to this user.
     *
     * @param int $sectionid , if 0 return all available modnames.
     */
    public function get_visible_mods_menu($sectionid) {

        $modinfo = $this->get_visible_modinfo();

        if ($sectionid > 0) {

            if (!isset($modinfo->sections[$sectionid])) {
                return array();
            }

            $modids = $modinfo->sections[$sectionid]->modids;
        } else {
            if (empty($modinfo->modules)) {
                return array();
            }
            $modids = array_keys($modinfo->modules);
        }

        $modnames = array();
        foreach ($modids as $modid) {
            if (isset($modinfo->modules[$modid])) {
                $modnames[$modid] = $modinfo->modules[$modid]->name;
            }
        }

        return $modnames;
    }

    public function get_options($sectionid) {

        $choices = array(0 => get_string('selectactivity', 'block_socialcomments'));
        $choices += $this->get_visible_mods_menu($sectionid);

        $html = '';
        foreach ($choices as $value => $text) {
            $html .= \html_writer::tag('option', $text, array('value' => $value));
        }

        return $html;
    }

    /**
     * Prepare the tabtree object.
     *
     * @param int $courseid id of course
     * @return \tabobject
     */
    public function get_tab_tree($courseid) {

        $rows = array();

        $reporturl = new \moodle_url('/blocks/socialcomments/report/newsfeed.php', array('courseid' => $courseid));
        $tablabel = get_string('tabnewcomments', 'block_socialcomments');
        $rows[] = new \tabobject('newsfeed', $reporturl, $tablabel, $tablabel);

        $reporturl = new \moodle_url('/blocks/socialcomments/report/index.php', array('courseid' => $courseid));
        $tablabel = get_string('taballcomments', 'block_socialcomments');
        $rows[] = new \tabobject('report', $reporturl, $tablabel, $tablabel);

        return $rows;
    }

    /**
     * Check, whether the context restricts visibility by group for this user.
     *
     * @param context $context
     * @return boolean|array false, when there is no restriction, array of groupids otherwise
     */
    private function is_restricted_to_groupids($context) {

        if (isset($this->grouprestriction[$context->id])) {
            return $this->grouprestriction[$context->id];
        }

        $this->grouprestriction[$context->id] = comment::is_restricted_to_groupids($context);
        return $this->grouprestriction[$context->id];
    }

    private function check_visiblity_and_add_replies($comments, $repliesgroupedbycomments) {

        // Group comments by contextid and add replies.
        $groupedcomments = array();

        foreach ($comments as $comment) {

            $context = \context_helper::instance_by_id($comment->postcontextid);
            $restrictedtogroups = $this->is_restricted_to_groupids($context);

            if (($restrictedtogroups) and ( !in_array($comment->groupid, $restrictedtogroups))) {
                continue;
            }

            if (!isset($groupedcomments[$comment->postcontextid])) {
                $groupedcomments[$comment->postcontextid] = array();
            }

            $commentdata = new \stdClass();
            $commentdata->comment = $comment;
            $commentdata->replies = array();

            if (isset($repliesgroupedbycomments[$comment->postid])) {
                $commentdata->replies = $repliesgroupedbycomments[$comment->postid];
            }

            $groupedcomments[$comment->postcontextid][$comment->postid] = $commentdata;
        }
        return $groupedcomments;
    }

    /**
     * Get all new comments and replies since given timestamp.
     * If a new reply is found, its comment is retrieved.
     *
     * @param int $courseid int id of course
     * @param int $timesince unix timesramp
     * @return array()
     */
    public function get_course_new_comments_and_replies($courseid, $timesince) {
        global $DB;

        $coursecontext = \context_course::instance($courseid);

        $authorfields = get_all_user_name_fields(true, 'u');
        $authorpicturefields = \user_picture::fields('u');

        // Get new replies.
        $sql = "SELECT r.id as postid, r.commentid, r.content, r.timecreated, r.userid,
                $authorfields, $authorpicturefields, bc.contextid as postcontextid
                FROM {block_socialcomments_replies} r
                JOIN {user} u ON r.userid = u.id
                JOIN {block_socialcomments_cmmnts} bc ON bc.id = r.commentid
                JOIN {context} ctx ON ctx.id = bc.contextid ";

        $cond = array();
        $params = array();

        $cond[] = " r.timemodified >= :timesince ";
        $params['timesince'] = $timesince;

        // Search in all subcontexts of course (contextpath1) and in coursecontext itsself (contextpath2).
        $cond[] = "((" . $DB->sql_like('ctx.path', ':contextpath1', false, false) . ") OR (ctx.path = :contextpath2)) ";
        $params['contextpath1'] = $coursecontext->path . '/%';
        $params['contextpath2'] = $coursecontext->path;

        $where = 'WHERE ' . implode(" AND ", $cond);
        $orderby = 'ORDER BY r.timemodified ASC';

        $replies = $DB->get_records_sql($sql . $where . $orderby, $params);

        $neededcommentsid = array();
        $repliesgroupedbycomments = array();

        foreach ($replies as $reply) {

            if (!isset($repliesgroupedbycomments[$reply->commentid])) {
                $repliesgroupedbycomments[$reply->commentid] = array();
            }

            $repliesgroupedbycomments[$reply->commentid][$reply->postid] = $reply;
            $neededcommentsid[$reply->commentid] = $reply->commentid;
        }

        // Get new comments or needed comments.
        $sql = "SELECT bc.id as postid, bc.content, bc.timecreated, bc.userid,
                u.id, $authorfields, $authorpicturefields, bc.contextid as postcontextid, bc.groupid
                FROM {block_socialcomments_cmmnts} bc
                JOIN {user} u ON bc.userid = u.id
                JOIN {context} ctx ON ctx.id = bc.contextid ";

        $cond = array();
        $params = array();

        $cond[] = " bc.timemodified >= :timesince ";
        $params['timesince'] = $timesince;

        // Search in all subcontexts of course (contextpath1) and in coursecontext itsself (contextpath2).
        $cond[] = "((" . $DB->sql_like('ctx.path', ':contextpath1', false, false) . ") OR (ctx.path = :contextpath2)) ";
        $params['contextpath1'] = $coursecontext->path . '/%';
        $params['contextpath2'] = $coursecontext->path;

        $where = 'WHERE (' . implode(" AND ", $cond) . ') ';

        // Add comments that are needed for replies.
        if (!empty($neededcommentsid)) {
            list($incommentids, $incommentidparam) = $DB->get_in_or_equal($neededcommentsid, SQL_PARAMS_NAMED);
            $where .= " OR (bc.id {$incommentids}) ";
            $params += $incommentidparam;
        }

        $orderby = 'ORDER BY bc.timemodified ASC';
        $comments = $DB->get_records_sql($sql . $where . $orderby, $params);

        if (count($comments) == 0) {
            return array();
        }

        return $this->check_visiblity_and_add_replies($comments, $repliesgroupedbycomments);
    }

    /**
     * Get all the comments in the course, that are pinned and are visible for this user.
     *
     * @param type $courseid
     * @return type
     */
    public function get_course_comments_pinned($courseid) {
        global $DB, $USER;

        // Get all contexts of this course, this user has pinned.
        $coursecontext = \context_course::instance($courseid);

        $authorfields = get_all_user_name_fields(true, 'u');
        $authorpicturefields = \user_picture::fields('u');

        // Get pinned page comments.
        $sql = "SELECT bc.id as postid, bc.content, bc.timecreated, bc.userid,
                u.id, $authorfields, $authorpicturefields, bc.contextid as postcontextid, bc.groupid
                FROM {block_socialcomments_cmmnts} bc
                JOIN {block_socialcomments_pins} p ON p.itemid = bc.contextid AND p.itemtype = :itemtype AND p.userid = :thisuserid
                JOIN {user} u ON bc.userid = u.id
                JOIN {context} ctx ON ctx.id = bc.contextid ";

        $cond = array();
        $params = array();
        $params['thisuserid'] = $USER->id;

        $params['itemtype'] = comments_helper::PINNED_PAGE;

        // Search in all subcontexts of course (contextpath1) and in coursecontext itsself (contextpath2).
        $cond[] = "((" . $DB->sql_like('ctx.path', ':contextpath1', false, false) . ") OR (ctx.path = :contextpath2)) ";
        $params['contextpath1'] = $coursecontext->path . '/%';
        $params['contextpath2'] = $coursecontext->path;
        $where = 'WHERE (' . implode(" AND ", $cond) . ') ';
        $orderby = "ORDER BY bc.timecreated ASC ";

        $pagepinnedcomments = $DB->get_records_sql($sql . $where . $orderby, $params);

        // Get all comments of this course this user has individually pinned.
        $sql = "SELECT bc.id as postid, bc.content, bc.timecreated, bc.userid,
                u.id, $authorfields, $authorpicturefields, bc.contextid as postcontextid, bc.groupid
                FROM {block_socialcomments_cmmnts} bc
                JOIN {block_socialcomments_pins} p ON p.itemid = bc.id AND p.itemtype = :itemtype AND p.userid = :thisuserid
                JOIN {user} u ON bc.userid = u.id
                JOIN {context} ctx ON ctx.id = bc.contextid ";

        $params['itemtype'] = comments_helper::PINNED_COMMENT;
        $singlepinnedcomments = $DB->get_records_sql($sql . $where . $orderby, $params);

        $pinnedcomments = $pagepinnedcomments + $singlepinnedcomments;

        if (count($pinnedcomments) == 0) {
            return array();
        }

        list($incommentid, $inparams) = $DB->get_in_or_equal(array_keys($pinnedcomments), SQL_PARAMS_NAMED);

        // Get replies for given comments.
        $sql = "SELECT r.id as postid, r.commentid, r.content, r.timecreated, r.userid,
                $authorfields, $authorpicturefields
                FROM {block_socialcomments_replies} r
                JOIN {user} u ON r.userid = u.id
                WHERE r.commentid {$incommentid} ORDER BY r.timecreated ASC";

        $replies = $DB->get_records_sql($sql, $inparams);

        // Group by commentid.
        $repliesgroupedbycomments = array();
        foreach ($replies as $reply) {

            if (!isset($repliesgroupedbycomments[$reply->commentid])) {
                $repliesgroupedbycomments[$reply->commentid] = array();
            }

            $repliesgroupedbycomments[$reply->commentid][$reply->postid] = $reply;
        }

        return $this->check_visiblity_and_add_replies($pinnedcomments, $repliesgroupedbycomments);
    }
}
