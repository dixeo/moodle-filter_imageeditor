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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Shared helpers for syncing embedded and modal preview images.
 *
 * @module     filter_dixeo_imageeditor/image_sync
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Build location args from a wrapper element.
     *
     * @param {HTMLElement} wrap
     * @returns {Object}
     */
    const getLocationArgs = (wrap) => ({
        contextid: parseInt(wrap.dataset.contextid, 10),
        component: wrap.dataset.component,
        filearea: wrap.dataset.filearea,
        itemid: parseInt(wrap.dataset.itemid, 10),
        filepath: wrap.dataset.filepath || '/',
        filename: wrap.dataset.filename,
        courseid: parseInt(wrap.dataset.courseid, 10),
    });

    /**
     * Stable key for polling maps.
     *
     * @param {HTMLElement} wrap
     * @returns {string}
     */
    const getLocationKey = (wrap) => [
        wrap.dataset.contextid,
        wrap.dataset.component,
        wrap.dataset.filearea,
        wrap.dataset.itemid,
        wrap.dataset.filepath,
        wrap.dataset.filename,
    ].join('|');

    /**
     * Append a stable cache-busting rev query param (contenthash when available).
     *
     * @param {string} url
     * @param {string} [contenthash]
     * @returns {string}
     */
    const appendImageRev = (url, contenthash = '') => {
        if (!url) {
            return url;
        }
        let cleaned = url.replace(/([?&])rev=[^&]*/g, '');
        cleaned = cleaned.replace(/[?&]$/, '');
        const rev = contenthash || String(Date.now());
        const separator = cleaned.includes('?') ? '&' : '?';
        return `${cleaned}${separator}rev=${encodeURIComponent(rev)}`;
    };

    /**
     * Update the embedded image src after a successful job.
     *
     * @param {HTMLElement} wrap
     * @param {string} imageurl
     * @param {string} [contenthash]
     * @param {Object} [options]
     * @param {boolean} [options.failed=false] Keep/set the failed class instead of clearing it.
     */
    const applyImageToWrap = (wrap, imageurl, contenthash = '', options = {}) => {
        const img = wrap.querySelector('img');
        if (!(img instanceof HTMLImageElement) || !imageurl) {
            return;
        }
        const hash = contenthash || wrap.dataset.contenthash || '';
        const failed = !!options.failed;
        img.src = appendImageRev(imageurl, hash);
        img.classList.remove('dixeo-img-gen-pending');
        if (failed) {
            img.classList.add('dixeo-img-gen-failed');
        } else {
            img.classList.remove('dixeo-img-gen-failed');
        }
        if (hash) {
            wrap.dataset.contenthash = hash;
            img.setAttribute('data-dixeo-contenthash', hash);
        } else {
            img.removeAttribute('data-dixeo-contenthash');
        }
        wrap.querySelectorAll(':scope > .dixeo-img-gen-status').forEach((el) => el.remove());
    };

    /**
     * Update the modal preview image.
     *
     * @param {HTMLElement} root
     * @param {string} imageurl
     * @param {string} [contenthash]
     */
    const updatePreviewImage = (root, imageurl, contenthash = '') => {
        const previewImage = root.querySelector('[data-region="preview-image"]');
        if (previewImage instanceof HTMLImageElement && imageurl) {
            previewImage.src = appendImageRev(imageurl, contenthash);
        }
    };

    /**
     * Apply a server image-replacement response to the page and modal preview.
     *
     * @param {Object} response
     * @param {Object} context
     * @param {HTMLElement} context.wrap
     * @param {HTMLElement} context.root
     * @param {Object|null} [context.previewEditor]
     * @param {Function} [context.syncHistory]
     * @returns {Promise<Object>}
     */
    const applyImageResponse = async(response, {wrap, root, previewEditor = null, syncHistory}) => {
        const contenthash = response.current_contenthash || '';
        const imageurl = appendImageRev(response.imageurl || '', contenthash);
        const manualEditing = typeof previewEditor?.isEditing === 'function' && previewEditor.isEditing();
        const manualSaving = typeof previewEditor?.isSaving === 'function' && previewEditor.isSaving();
        const skipPreviewSync = manualEditing && !manualSaving;

        if (!skipPreviewSync) {
            applyImageToWrap(wrap, response.imageurl || '', contenthash);
            updatePreviewImage(root, response.imageurl || '', contenthash);
            if (previewEditor?.commitSavedImage) {
                previewEditor.commitSavedImage(imageurl);
            }
        }

        if (syncHistory) {
            await syncHistory(response.history || [], contenthash);
        }
        return response;
    };

    return {
        getLocationArgs,
        getLocationKey,
        appendImageRev,
        applyImageToWrap,
        updatePreviewImage,
        applyImageResponse,
    };
});
