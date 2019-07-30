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
 * Javascript controller for the socialcomments block.
 *
 * @module     block_socialcomments/comments
 * @package    block_socialcomments
 * @copyright  2017 Andreas Wagner.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, ajax, notification, corestr) {

    var LASTPAGE = -1;

    var params = null; // ...contextid, subscribed, commentscount.

    var editcommentid = 0; // Current edited comment.
    var replycommentid = 0; // Id of comment user is currently replying.
    var replyid = 0; // Current edited reply;
    var menunumber = -1; // Current opened menu.
    var pagenumber = 0; // Current loaded page.

    var $formtextarea = null;
    var $formactionpost = null;

    var $edittextarea = null;
    var $editactionsave = null;

    var $replytextarea = null;
    var $replyactionsave = null;

    var $commentscontent = null; // Container wrapping the comments area.

    /**
     * Check post content and do an alert when empty.
     *
     * @param {string} content - Content of the post.
     * @returns {boolean} - Result is true when the post content is empty.
     */
    function alertPostEmpty(content) {

        if (!content) {
            corestr.get_strings([
                {'key': 'error'},
                {'key': 'pleaseinputtext', component: 'block_socialcomments'}
            ]).done(function(s) {
                notification.alert(s[0], s[1]);
            }
            ).fail(notification.exception);
            return true;
        }

        return false;
    }

    /**
     * Move form.
     */
    function moveForms() {
        $('#ccomment-editform-wrap').append($('#ccomment-editform'));
        resetEditComment();

        $('#ccomment-replyform-wrap').append($('#ccomment-replyform'));
        resetReplyComment();
    }

    /**
     * Get HTML of a page with pagenumber 0..maxpage via AJAX.
     *
     * @param {int} newpagenumber Number of the page.
     */
    function loadCommentsPage(newpagenumber) {

        moveForms();

        ajax.call([
            {
                methodname: 'block_socialcomments_get_commentspage',
                args: {
                    contextid: params.contextid,
                    pagenumber: newpagenumber
                },
                done: function(response) {
                    pagenumber = response.pagenumber;
                    $('#ccomment-commentspage-wrap').html(response.commentspage);
                },
                fail: notification.exception
            }
        ], false);
    }

    /**
     * Create a new comment, when commentid == 0 or update a comment.
     *
     * @param {Object} $textarea
     * @param {int} commentid
     */
    function saveComment($textarea, commentid) {

        var content = $textarea.val();
        var groupid = $('#ccomments-groupid').val();

        if (!alertPostEmpty(content)) {

            ajax.call([
                {
                    methodname: 'block_socialcomments_save_comment',
                    args: {
                        contextid: params.contextid,
                        content: content,
                        groupid: groupid,
                        id: commentid
                    },
                    done: function(response) {
                        // Update display.
                        $('.ccomment-comments-count').html(response.count);
                        $('#ccomment-comments-content-top').removeClass('hidden');
                        $textarea.val('');
                        // Load the Page the comment is visible.
                        if (commentid === 0) {
                            loadCommentsPage(LASTPAGE);
                            // When user posts new comment, set subscribed checkbox to true.
                            $('#ccomment-comments-subscribed').prop('checked', true);
                        } else {
                            loadCommentsPage(pagenumber);
                        }
                        // Check, whether element is visible, if not scroll.
                        var comment = $("#ccomment-comment-listitem-" + response.id);
                        var win = $(window);
                        if (comment.offset().top > win.scrollTop() + win.height()) {
                            $('html, body').animate({
                                scrollTop: comment.offset().top - comment.outerHeight()
                            }, 1000);
                        }
                    },
                    fail: notification.exception
                }
            ], false);
        }
    }

    /**
     * Save a reply, create when replyid == 0 or update otherwise.
     *
     * @param {Object} $textarea jQuery object of textarea.
     * @param {int} replyid ID of the reply.
     */
    function saveReply($textarea, replyid) {

        var content = $textarea.val();

        if (!alertPostEmpty(content)) {

            ajax.call([
                {
                    methodname: 'block_socialcomments_save_reply',
                    args: {
                        contextid: params.contextid,
                        content: content,
                        commentid: replycommentid,
                        id: replyid
                    },
                    done: function() {
                        // Update display.
                        $textarea.val('');
                        // Load the Page the where the comment is visible.
                        loadCommentsPage(pagenumber);
                    },
                    fail: notification.exception
                }
            ], false);
        }
    }

    /**
     * Display a the deleted message and refresh commentslist after some secs.
     *
     * @param {Object} response of AJAX call.
     */
    function onCommentDeleteDone(response) {

        // Before delete DOM Elements move forms to safe place.
        moveForms();

        corestr.get_string('commentdeleted', 'block_socialcomments').done(function(s) {
            $('#ccomment-comment-listitem-' + response.deletedcommentid).addClass('ccomment-highlight');
            $('#ccomment-comment-listitem-' + response.deletedcommentid).html(s).fadeOut(2000, function() {
                loadCommentsPage(pagenumber);
            });
        });

        corestr.get_string('commentscount', 'block_socialcomments', response.count).done(function(s) {
            $('.ccomment-comments-count').html(s);
        });

        // Probably hide the comments top content (Count and subscribed checkbox).
        if ((response.count === 0) && (!params.subscribed)) {
            $('#ccomment-comments-content-top').addClass('hidden');
        }
    }

    /**
     * Delete a comment in the database.
     *
     * @param {Object} $href jQuery object of ancor.
     */
    function onCommentDeleteClicked($href) {

        ajax.call([
            {
                methodname: 'block_socialcomments_delete_comment',
                args: {
                    commentid: Number($href.attr('id').split('-')[4])
                },
                done: onCommentDeleteDone,
                fail: notification.exception
            }
        ]);
    }

    /**
     * Display a the deleted message and refresh commentslist after some secs.
     *
     * @param {Object} response of AJAX call.
     */
    function onReplyDeleteDone(response) {

        // Before delete DOM Elements move forms to safe place.
        moveForms();

        corestr.get_string('replydeleted', 'block_socialcomments').done(function(s) {
            $('#ccomment-reply-' + response.deletedreplyid).addClass('ccomment-highlight');
            $('#ccomment-reply-' + response.deletedreplyid).html(s).fadeOut(2000, function() {
                loadCommentsPage(pagenumber);
            });
        });
    }

    /**
     * Delete a reply in the database.
     *
     * @param {Object} $href jQuery object of ancor.
     */
    function onReplyDeleteClicked($href) {

        ajax.call([
            {
                methodname: 'block_socialcomments_delete_reply',
                args: {
                    replyid: Number($href.attr('id').split('-')[4])
                },
                done: onReplyDeleteDone,
                fail: notification.exception
            }
        ]);
    }

    /**
     * Save the pin status for comment or page in database.
     *
     * @param {Object} $checkbox
     */
    function onPinnedClicked($checkbox) {

        ajax.call([
            {
                methodname: 'block_socialcomments_set_pinned',
                args: {
                    contextid: params.contextid,
                    checked: $checkbox.prop('checked'),
                    commentid: Number($checkbox.attr('id').split('-')[2])
                },
                done: function(response) {
                    $checkbox.prop('checked', response.checked);
                    // Set correct tooltip.
                    var $tooltipdiv = $checkbox.parent().find('.ccomments-pin-tooltip');
                    $tooltipdiv.html(response.tooltip);
                },
                fail: notification.exception
            }
        ]);
    }

    /**
     * Save the subscribed status for the page in database.
     *
     * @param {Object} $checkbox
     */
    function onSubscribeClicked($checkbox) {

        ajax.call([
            {
                methodname: 'block_socialcomments_set_subscribed',
                args: {
                    contextid: params.contextid,
                    checked: $checkbox.prop('checked')
                },
                done: function(response) {
                    params.subscribed = response.checked;
                    $checkbox.prop('checked').prop('checked', params.subscribed);
                },
                fail: notification.exception
            }
        ]);
    }

    /**
     * Load the form and note the commentid.
     *
     * @param {Object} $href
     */
    function startCreateReply($href) {

        cancelReplyComment();

        // Note the parent comment id.
        replycommentid = Number($href.attr('id').split('-')[2]);
        $replytextarea.attr('data-commentid', replycommentid);

        // Load the form.
        var $replylist = $('#ccomment-reply-list-' + replycommentid);
        $replylist.before($('#ccomment-replyform'));
    }

    /**
     * Load the form into the comment and fill textarea with comments text.
     *
     * @param {Object} $href
     */
    function startEditReply($href) {

        cancelReplyComment();

        // Note the replyid.
        replyid = Number($href.attr('id').split('-')[4]);
        $replytextarea.attr('data-replyid', replyid);

        // Load the form.
        var $contentdiv = $('#ccomment-reply-' + replyid + ' .ccomment-post-content');
        $replytextarea.val($contentdiv.html());
        $contentdiv.after($('#ccomment-replyform'));
        $contentdiv.hide();
    }

    /**
     * Cancel editing or creating a reply.
     */
    function cancelReplyComment() {
        $('#ccomment-replyform-wrap').append($('#ccomment-replyform'));
        $('#ccomment-reply-' + replyid + ' .ccomment-post-content').show();
        resetReplyComment();
    }

    /**
     * Resetting reply form to default
     */
    function resetReplyComment() {
        replyid = 0;
        $replytextarea.attr('data-replyid', 0);
        replycommentid = 0;
        $replytextarea.attr('data-commentid', 0);
        $replytextarea.val('');
    }

    /**
     * Load the form into the comment and fill textarea with comments text.
     *
     * @param {Objec} $href
     */
    function startEditComment($href) {

        cancelEditComment();

        // Note the commentid.
        editcommentid = Number($href.attr('id').split('-')[4]);
        $edittextarea.attr('data-commentid', editcommentid);

        // Load the form.
        var $contentdiv = $('#ccomment-post-' + editcommentid + ' .ccomment-post-content');
        $edittextarea.val($contentdiv.html());
        $contentdiv.after($('#ccomment-editform'));
        $contentdiv.hide();
    }

    /**
     * Resetting edit comment form to default and show existing comment.
     */
    function cancelEditComment() {
        $('#ccomment-editform-wrap').append($('#ccomment-editform'));
        $('#ccomment-post-' + editcommentid + ' .ccomment-post-content').show();
        resetEditComment();
    }

    /**
     * Resetting edit comment form to default
     */
    function resetEditComment() {
        editcommentid = 0;
        $edittextarea.attr('data-commentid', 0);
        $edittextarea.val('');
    }

    /**
     * Load the page of comments.
     *
     * @param {Object} $href
     */
    function onPagingClicked($href) {
        pagenumber = Number($href.attr('id').split('-')[2]);
        loadCommentsPage(pagenumber);
    }

    /**
     * Show action menu.
     *
     * @param {Object} href link to trigger menu
     */
    function showMenu(href) {

        hideMenu();

        menunumber = Number(href.attr('id').split('-')[3]);
        $('#ccomment-comments-content #action-menu-' + menunumber).addClass('show');
        $('#ccomment-comments-content #action-menu-' + menunumber).attr('data-enhanced', 1);
    }

    /**
     * Hide action menu.
     */
    function hideMenu() {

        if (menunumber >= 0) {
            $('#ccomment-comments-content #action-menu-' + menunumber).removeClass('show');
            $('#ccomment-comments-content #action-menu-' + menunumber).removeAttr('data-enhanced');
            menunumber = -1;
        }
    }

    /**
     * Hightlight the button.
     *
     * @param {Object} $actionbutton
     * @param {String} content
     */
    function highlightActionButton($actionbutton, content) {
        if (!content) {
            $actionbutton.removeClass('ccomment-highlight-button');
        } else {
            $actionbutton.addClass('ccomment-highlight-button');
        }
    }

    /**
     * Attach eventlisteners for comments.
     */
    function initCommentForms() {

        $formtextarea = $('#ccomment-form-textarea');
        $formactionpost = $('#ccomment-form-action-post');

        $formactionpost.click(function() {
            saveComment($formtextarea, 0);
            highlightActionButton($formactionpost, $formtextarea.val());
        });

        $formtextarea.keyup(function() {
            highlightActionButton($formactionpost, $formtextarea.val());
        });

        $('#ccomment-form-action-cancel').click(function() {
            $formtextarea.val('');
            $formactionpost.removeClass('ccomment-highlight-button');
        });

        $edittextarea = $('#ccomment-edit-textarea');
        $editactionsave = $('#ccomment-edit-action-save');

        // Edit comment.
        $editactionsave.click(function() {

            editcommentid = $edittextarea.attr('data-commentid');

            if (editcommentid > 0) {
                saveComment($edittextarea, editcommentid);
                highlightActionButton($editactionsave, $edittextarea.val());
            }
        });

        // Cancel editing comment.
        $('#ccomment-edit-action-cancel').click(function() {
            if (editcommentid > 0) {
                cancelEditComment();
                resetEditComment();
            }
        });

        $edittextarea.keyup(function() {
            highlightActionButton($editactionsave, $edittextarea.val());
        });

        // Action menu.
        $commentscontent.delegate('a[id^="ccomment-post-action-delete-"]', 'click', function(e) {
            e.preventDefault();
            onCommentDeleteClicked($(this));
        });

        $commentscontent.delegate('a[id^="ccomment-post-action-edit-"]', 'click', function(e) {
            e.preventDefault();
            startEditComment($(this));
        });

        // Reply-Link.
        $commentscontent.delegate('a[id^="ccomment-reply-"]', 'click', function(e) {
            e.preventDefault();
            startCreateReply($(this));
        });
    }

    /**
     * Attach eventlisteners for replies.
     */
    function initReplyForms() {

        $replytextarea = $('#ccomment-reply-textarea');
        $replyactionsave = $('#ccomment-reply-action-save');

        $replyactionsave.click(function() {
            saveReply($replytextarea, replyid);
            highlightActionButton($replyactionsave, $replytextarea.val());
        });

        // Cancel editing reply.
        $('#ccomment-reply-action-cancel').click(function() {
            cancelReplyComment();
            resetReplyComment();
        });

        $replytextarea.keyup(function() {
            highlightActionButton($replyactionsave, $replytextarea.val());
        });

        // Action menu.
        $commentscontent.delegate('a[id^="ccomment-reply-action-delete-"]', 'click', function(e) {
            e.preventDefault();
            onReplyDeleteClicked($(this));
        });

        $commentscontent.delegate('a[id^="ccomment-reply-action-edit-"]', 'click', function(e) {
            e.preventDefault();
            startEditReply($(this));
        });
    }

    return {
        init: function(initparams) {

            // Params.
            params = initparams;

            // Container for all AJAX-loaded objects for delegations.
            $commentscontent = $('#ccomment-comments-content');

            // Creating and editing a comment.
            initCommentForms();

            // Creating and editing a reply.
            initReplyForms();

            // Pin page or comment.
            $('.block_socialcomments').delegate('input[id^="ccomment-pinned-"]', 'click', function() {
                onPinnedClicked($(this));
            });
            // Subscribe.
            $('#ccomment-comments-subscribed').click(function() {
                onSubscribeClicked($(this));
            });
            // Open menu.
            $commentscontent.delegate('a[id^="action-menu-toggle-"]', 'click', function(e) {
                e.preventDefault();
                showMenu($(this));
            });
            // Pagination.
            $commentscontent.delegate('a[id^="ccomment-pagelink-"]', 'click', function(e) {
                e.preventDefault();
                onPagingClicked($(this));
            });
            // Hide menu.
            $(window).click(function() {
                hideMenu();
            });

        }
    };
});
