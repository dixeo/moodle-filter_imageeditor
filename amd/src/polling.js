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
 * Client-side polling for async image job status.
 *
 * @module     filter_dixeo_imageeditor/polling
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/notification',
    'core/str',
    'filter_dixeo_imageeditor/image_sync',
    'filter_dixeo_imageeditor/toast',
], function(Ajax, Notification, Str, imageSync, toast) {
    'use strict';

    const GENERATING_CLASS = 'is-generating';
    const POLL_INTERVAL_MS = 4000;
    /** Align with job_repository::TIMEOUT_SECONDS (1 hour). */
    const POLL_TIMEOUT_MS = 3600000;

    /** @type {Map<string, number>} */
    const pollTimers = new Map();

    /** @type {Map<string, number>} */
    const pollStartedAt = new Map();

    /**
     * Toggle generating overlay on a wrapper.
     *
     * @param {HTMLElement} wrap
     * @param {boolean} active
     * @param {string} label
     */
    const setGeneratingOverlay = (wrap, active, label = '') => {
        if (active) {
            wrap.classList.add(GENERATING_CLASS);
            wrap.dataset.dixeoImageGeneratingLabel = label;
            return;
        }
        wrap.classList.remove(GENERATING_CLASS);
        delete wrap.dataset.dixeoImageGeneratingLabel;
    };

    /**
     * Stop polling for one location.
     *
     * @param {string} key
     */
    const stopPolling = (key) => {
        const timer = pollTimers.get(key);
        if (timer) {
            window.clearInterval(timer);
            pollTimers.delete(key);
        }
        pollStartedAt.delete(key);
    };

    /**
     * Best-effort acknowledgement that clears terminal lock state server-side.
     *
     * @param {HTMLElement} wrap
     * @returns {void}
     */
    const acknowledgeTerminalStatus = (wrap) => {
        Ajax.call([{
            methodname: 'filter_dixeo_imageeditor_get_location_status',
            args: Object.assign({}, imageSync.getLocationArgs(wrap), {acknowledge: true}),
        }])[0].catch(() => {
            // Acknowledge is best-effort cleanup.
        });
    };

    /**
     * Show a success or error toast for a completed job.
     *
     * @param {HTMLElement} wrap
     * @param {'success'|'error'} type
     * @param {string} [message]
     * @returns {Promise<void>}
     */
    const notifyJobResult = async(wrap, type, message = '') => {
        let text = message;
        if (!text) {
            text = await Str.getString(
                type === 'success' ? 'image_updated' : 'error_job_failed',
                'filter_dixeo_imageeditor'
            );
        }
        toast.showJobResult(wrap, text, type);
    };

    /**
     * Poll lock status for UX overlay only.
     *
     * @param {HTMLElement} wrap
     * @param {Object} [callbacks]
     * @param {Function} [callbacks.onApplied]
     * @param {Function} [callbacks.onFailed]
     * @param {Function} [callbacks.onTimeout]
     */
    const startStatusPolling = (wrap, callbacks = {}) => {
        const key = imageSync.getLocationKey(wrap);
        stopPolling(key);
        pollStartedAt.set(key, Date.now());

        const poll = async() => {
            const started = pollStartedAt.get(key) || Date.now();
            if (Date.now() - started > POLL_TIMEOUT_MS) {
                stopPolling(key);
                setGeneratingOverlay(wrap, false);
                if (callbacks.onTimeout) {
                    callbacks.onTimeout();
                }
                try {
                    const message = await Str.getString('error_job_failed', 'filter_dixeo_imageeditor');
                    await notifyJobResult(wrap, 'error', message);
                } catch (error) {
                    Notification.exception(error);
                }
                return;
            }

            try {
                const status = await Ajax.call([{
                    methodname: 'filter_dixeo_imageeditor_get_location_status',
                    args: imageSync.getLocationArgs(wrap),
                }])[0];

                if (!status?.status) {
                    return;
                }

                if (status.status === 'pending' || status.status === 'processing') {
                    return;
                }

                stopPolling(key);

                if (status.status === 'applied') {
                    imageSync.applyImageToWrap(wrap, status.imageurl || '', status.current_contenthash || '');
                    setGeneratingOverlay(wrap, false);
                    await notifyJobResult(wrap, 'success');
                    if (callbacks.onApplied) {
                        callbacks.onApplied(status);
                    }
                    acknowledgeTerminalStatus(wrap);
                    return;
                }

                if (status.status === 'failed') {
                    setGeneratingOverlay(wrap, false);
                    const message = status.errormessage
                        || await Str.getString('error_job_failed', 'filter_dixeo_imageeditor');
                    await notifyJobResult(wrap, 'error', message);
                    if (callbacks.onFailed) {
                        callbacks.onFailed(status);
                    }
                    acknowledgeTerminalStatus(wrap);
                }
            } catch (error) {
                Notification.exception(error);
            }
        };

        pollTimers.set(key, window.setInterval(() => {
            poll().catch(Notification.exception);
        }, POLL_INTERVAL_MS));
        poll().catch(Notification.exception);
    };

    /**
     * Resume overlays for in-flight jobs after page load.
     *
     * Only wrappers flagged server-side (data-dixeo-pending="1") are checked, so
     * pages full of idle images make no status requests at all.
     *
     * @param {string} wrapSelector
     */
    const resumePendingOverlays = (wrapSelector) => {
        document.querySelectorAll(wrapSelector).forEach((wrap) => {
            if (!(wrap instanceof HTMLElement)) {
                return;
            }
            if (wrap.dataset.dixeoPending !== '1') {
                return;
            }

            // Claim the label immediately so local_dixeo status pills never flash.
            Str.getString('generating_status', 'filter_dixeo_imageeditor').then((label) => {
                setGeneratingOverlay(wrap, true, label);
                return;
            }).catch(() => {
                setGeneratingOverlay(wrap, true, '');
            });

            const resume = async() => {
                try {
                    const status = await Ajax.call([{
                        methodname: 'filter_dixeo_imageeditor_get_location_status',
                        args: imageSync.getLocationArgs(wrap),
                    }])[0];

                    if (status?.status === 'pending' || status?.status === 'processing') {
                        startStatusPolling(wrap);
                    } else {
                        setGeneratingOverlay(wrap, false);
                    }
                } catch {
                    setGeneratingOverlay(wrap, false);
                    // Ignore resume failures on pages without webservice access.
                }
            };

            resume().catch(() => {
                // Ignore resume failures on pages without webservice access.
            });
        });
    };

    return {
        GENERATING_CLASS,
        setGeneratingOverlay,
        stopPolling,
        startStatusPolling,
        resumePendingOverlays,
        notifyJobResult,
    };
});
