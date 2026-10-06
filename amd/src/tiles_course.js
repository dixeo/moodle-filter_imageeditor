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
 * Image editor control on tiles course sections.
 *
 * Photo tiles use a CSS background. Icon tiles have no stored file. Both
 * open the same editor modal. The first generated or uploaded image is
 * stored as the section photo and replaces the icon.
 *
 * @module     filter_dixeo_imageeditor/tiles_course
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str'], function(Str) {
    'use strict';

    /**
     * Read a tiles pluginfile URL from a section tile background.
     *
     * @param {HTMLElement} tile
     * @returns {Object|null}
     */
    const photoFromTile = (tile) => {
        const style = tile.getAttribute('style') || '';
        const match = style.match(/background-image:\s*url\(\s*(['"]?)([^'")]+)\1\s*\)/i);
        if (!match) {
            return null;
        }
        const parsed = match[2].match(
            /pluginfile\.php\/(\d+)\/format_tiles\/tilephoto\/(\d+)\/tilephoto\/([^?]+)/
        );
        if (!parsed) {
            return null;
        }
        return {
            contextid: parsed[1],
            itemid: parsed[2],
            filename: decodeURIComponent(parsed[3]),
        };
    };

    /**
     * Add an editor button to each numbered section tile.
     *
     * @param {number} courseid
     * @param {number} contextid
     * @param {string} label
     */
    const attach = (courseid, contextid, label) => {
        document.querySelectorAll('ul.tiles > li.tile[data-true-sectionid]').forEach((tile) => {
            if (!(tile instanceof HTMLElement) || tile.querySelector('.dixeo-tiles-imageeditor')) {
                return;
            }
            const sectionNumber = parseInt(tile.dataset.section || '0', 10);
            const sectionid = parseInt(tile.dataset.trueSectionid || '0', 10);
            if (sectionNumber < 1 || sectionid < 1) {
                return;
            }

            const photo = photoFromTile(tile);
            if (tile.classList.contains('phototile') && !photo) {
                return;
            }

            const wrap = document.createElement('span');
            wrap.className = 'dixeo-imageeditor-wrap dixeo-tiles-imageeditor';
            wrap.dataset.dixeoImageeditor = '1';
            wrap.dataset.tilesSection = '1';
            wrap.dataset.contextid = photo ? photo.contextid : String(contextid);
            wrap.dataset.component = 'format_tiles';
            wrap.dataset.filearea = 'tilephoto';
            wrap.dataset.itemid = photo ? photo.itemid : String(sectionid);
            wrap.dataset.filepath = '/tilephoto/';
            wrap.dataset.filename = photo ? photo.filename : 'tile-photo-' + sectionid + '.jpg';
            wrap.dataset.courseid = String(courseid);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dixeo-imageeditor-editbtn';
            button.dataset.action = 'open-editor';
            button.setAttribute('aria-label', label);
            button.textContent = label;
            wrap.appendChild(button);
            tile.appendChild(wrap);
        });
    };

    return {
        /**
         * @param {{courseid: number, contextid: number}} config
         */
        init: (config) => {
            const courseid = parseInt(config.courseid, 10);
            const contextid = parseInt(config.contextid, 10);
            Str.getString('editimage', 'filter_dixeo_imageeditor').then((label) => {
                const run = () => attach(courseid, contextid, label);
                run();
                const root = document.querySelector('#page-content') || document.body;
                let frame = 0;
                const observer = new MutationObserver(() => {
                    if (frame) {
                        return;
                    }
                    frame = window.requestAnimationFrame(() => {
                        frame = 0;
                        run();
                    });
                });
                observer.observe(root, {childList: true, subtree: true});
                return label;
            }).catch(() => {
                return null;
            });
        },
    };
});
