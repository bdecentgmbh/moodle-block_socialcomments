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
 * Comments, replies, pins and the subscription of the Social comments block.
 *
 * @module     block_socialcomments/comments
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {getStrings} from 'core/str';

/** Page number of the last page, to show a new comment. */
const LASTPAGE = -1;

/** Forms to edit or reply, which a refresh would throw away. */
const OPENFORMS = '[data-region="form"]:not([data-type="comment"])';

/** Event the real-time module sends when the comments of the page changed. */
export const CHANGED = 'block_socialcomments:changed';

/**
 * Call a web service of the block.
 *
 * @param {string} methodname
 * @param {Object} args
 * @returns {Promise}
 */
const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

/**
 * Start the block.
 *
 * @param {string} uniqid Id of the block content
 * @param {number} contextid Context of the page the comments belong to
 * @param {number} hidepins Whether the block hides the pins
 */
export const init = (uniqid, contextid, hidepins) => {
    const root = document.getElementById(uniqid);
    if (!root) {
        return;
    }
    const comments = root.querySelector('[data-region="comments"]');
    const currentList = () => comments.querySelector('[data-region="list"]');
    let page = Number(currentList().dataset.page);
    let refreshwaiting = false;

    /**
     * Load a page of comments.
     *
     * @param {number} newpage
     */
    const load = async(newpage) => {
        const pending = new Pending('block_socialcomments/load');
        try {
            const response = await call('block_socialcomments_get_commentspage', {
                contextid,
                pagenumber: newpage,
                hidepins,
            });
            page = response.pagenumber;
            await Templates.replaceNodeContents(comments, response.commentspage, '');
            root.querySelector('[data-region="count"]').textContent = currentList().dataset.countlabel;
        } finally {
            pending.resolve();
        }
    };

    /**
     * Load the comments again, unless the user is editing or replying: then once the form is closed.
     */
    const refresh = () => {
        if (root.querySelector(OPENFORMS)) {
            refreshwaiting = true;
            return;
        }
        refreshwaiting = false;
        load(page).catch(Notification.exception);
    };

    /**
     * Close a form: empty the comment form, remove the forms to edit or reply.
     *
     * @param {HTMLElement} form
     */
    const close = (form) => {
        if (form.dataset.type === 'comment') {
            form.querySelector('[data-region="text"]').value = '';
            return;
        }
        const content = form.previousElementSibling;
        if (content && content.matches('[data-region="content"]')) {
            content.hidden = false;
        }
        form.remove();
        if (refreshwaiting) {
            refresh();
        }
    };

    /**
     * Show a form to edit or reply after an element.
     *
     * @param {HTMLElement} element
     * @param {Object} context of block_socialcomments/form
     * @returns {HTMLElement} the form
     */
    const openForm = async(element, context) => {
        root.querySelectorAll(OPENFORMS).forEach((form) => {
            const content = form.previousElementSibling;
            if (content && content.matches('[data-region="content"]')) {
                content.hidden = false;
            }
            form.remove();
        });
        const {html, js} = await Templates.renderForPromise('block_socialcomments/form', context);
        element.insertAdjacentHTML('afterend', html);
        Templates.runTemplateJS(js);
        const form = element.nextElementSibling;
        form.querySelector('[data-region="text"]').focus();
        return form;
    };

    /**
     * Edit a comment or a reply in place.
     *
     * @param {HTMLElement} action
     */
    const edit = async(action) => {
        const reply = action.closest('[data-region="reply"]');
        const post = reply || action.closest('[data-region="comment"]');
        const content = post.querySelector('[data-region="content"]');
        const [submitlabel, label] = await getStrings([
            {key: 'save', component: 'block_socialcomments'},
            {key: reply ? 'writereply' : 'writecomment', component: 'block_socialcomments'},
        ]);
        const form = await openForm(content, {
            type: reply ? 'editreply' : 'editcomment',
            text: content.dataset.raw,
            label,
            submitlabel,
        });
        form.dataset.id = reply ? reply.dataset.replyid : post.dataset.commentid;
        content.hidden = true;
    };

    /**
     * Reply to a comment.
     *
     * @param {HTMLElement} action
     */
    const reply = async(action) => {
        const comment = action.closest('[data-region="comment"]');
        const [submitlabel, label] = await getStrings([
            {key: 'reply', component: 'block_socialcomments'},
            {key: 'writereply', component: 'block_socialcomments'},
        ]);
        const form = await openForm(comment.querySelector('[data-region="replies"]'), {
            type: 'reply',
            label,
            placeholder: label,
            submitlabel,
        });
        form.dataset.commentid = comment.dataset.commentid;
    };

    /**
     * Send a form.
     *
     * @param {HTMLElement} form
     */
    const submit = async(form) => {
        const textarea = form.querySelector('[data-region="text"]');
        const content = textarea.value.trim();
        if (!content) {
            textarea.focus();
            return;
        }
        const buttons = form.querySelectorAll('button');
        buttons.forEach((button) => {
            button.disabled = true;
        });
        const pending = new Pending('block_socialcomments/save');
        try {
            if (form.dataset.type === 'comment') {
                const group = form.querySelector('[data-region="group"]');
                await call('block_socialcomments_save_comment', {
                    contextid,
                    content,
                    groupid: group ? Number(group.value) : 0,
                    id: 0,
                });
                textarea.value = '';
                // Writing a comment subscribes to the page.
                const subscription = root.querySelector('[data-action="subscribe"]');
                if (subscription) {
                    subscription.checked = true;
                }
                await load(LASTPAGE);
            } else if (form.dataset.type === 'editcomment') {
                await call('block_socialcomments_save_comment', {contextid, content, groupid: 0, id: Number(form.dataset.id)});
                form.remove();
                await load(page);
            } else {
                await call('block_socialcomments_save_reply', {
                    contextid,
                    content,
                    commentid: Number(form.dataset.commentid || 0),
                    id: form.dataset.type === 'editreply' ? Number(form.dataset.id) : 0,
                });
                form.remove();
                await load(page);
            }
        } finally {
            buttons.forEach((button) => {
                button.disabled = false;
            });
            pending.resolve();
        }
    };

    /**
     * Delete a comment or a reply, after asking.
     *
     * @param {HTMLElement} action
     */
    const remove = async(action) => {
        const reply = action.closest('[data-region="reply"]');
        const comment = action.closest('[data-region="comment"]');
        const [title, question] = await getStrings([
            {key: 'delete', component: 'core'},
            {key: reply ? 'deletereplycheck' : 'deletecommentcheck', component: 'block_socialcomments'},
        ]);
        try {
            await Notification.deleteCancelPromise(title, question, title);
        } catch (cancelled) {
            return;
        }
        if (reply) {
            await call('block_socialcomments_delete_reply', {replyid: Number(reply.dataset.replyid)});
        } else {
            await call('block_socialcomments_delete_comment', {commentid: Number(comment.dataset.commentid)});
        }
        await load(page);
    };

    /**
     * Pin or unpin a comment, or all comments of the page.
     *
     * @param {HTMLElement} button
     */
    const pin = async(button) => {
        const response = await call('block_socialcomments_set_pinned', {
            contextid,
            checked: button.getAttribute('aria-pressed') !== 'true',
            commentid: Number(button.dataset.commentid),
        });
        button.setAttribute('aria-pressed', response.checked ? 'true' : 'false');
        button.setAttribute('aria-label', response.tooltip);
        button.title = response.tooltip;
    };

    root.addEventListener('submit', (e) => {
        const form = e.target.closest('[data-region="form"]');
        if (form) {
            e.preventDefault();
            submit(form).catch(Notification.exception);
        }
    });

    root.addEventListener('click', (e) => {
        const action = e.target.closest('[data-action]');
        if (!action || !root.contains(action)) {
            return;
        }
        const handlers = {
            cancel: () => close(action.closest('[data-region="form"]')),
            edit: () => edit(action),
            delete: () => remove(action),
            reply: () => reply(action),
            pin: () => pin(action),
            page: () => load(Number(action.dataset.page)),
            earlierreplies: () => {
                action.closest('[data-region="comment"]').querySelectorAll('[data-region="reply"][hidden]').forEach((reply) => {
                    reply.hidden = false;
                });
                action.remove();
            },
        };
        if (handlers[action.dataset.action]) {
            e.preventDefault();
            Promise.resolve(handlers[action.dataset.action]()).catch(Notification.exception);
        }
    });

    root.addEventListener('change', (e) => {
        if (e.target.matches('[data-action="subscribe"]')) {
            call('block_socialcomments_set_subscribed', {contextid, checked: e.target.checked})
                .then((response) => {
                    e.target.checked = response.checked;
                    return response;
                })
                .catch(Notification.exception);
        }
    });

    root.addEventListener(CHANGED, refresh);
};
