/**
 * Fanfic Recent Activity - localStorage-based activity tracker
 *
 * Tracks user actions (browse, read, rate, like, bookmark, comment, login, logout)
 * entirely in localStorage. No server storage. Renders to the dashboard widget.
 *
 * @package FanfictionManager
 * @since 2.2.0
 */

(function() {
	'use strict';

	var STORAGE_KEY = 'fanfic_recent_activity';
	var MAX_ENTRIES = 200;
	var PAGE_SIZE = 20;

	/**
	 * Get the WordPress date format string from localized config.
	 * Falls back to 'Y-m-d' if not available.
	 */
	function getDateFormat() {
		return (typeof fanficActivity !== 'undefined' && fanficActivity.dateFormat) ? fanficActivity.dateFormat : 'F j, Y';
	}

	/**
	 * Get the WordPress time format string from localized config.
	 * Falls back to 'H:i' if not available.
	 */
	function getTimeFormat() {
		return (typeof fanficActivity !== 'undefined' && fanficActivity.timeFormat) ? fanficActivity.timeFormat : 'H:i';
	}

	/**
	 * Format a timestamp into a WordPress-style date + HH:MM string.
	 * Implements a subset of PHP date() tokens used by WordPress defaults.
	 *
	 * @param {number} timestamp Unix timestamp in milliseconds.
	 * @return {string} Formatted date string.
	 */
	function formatTimestamp(timestamp) {
		var d = new Date(timestamp);
		var datePart = wpDateFormat(d, getDateFormat());
		var timePart = wpDateFormat(d, getTimeFormat());
		return datePart + ' ' + timePart;
	}

	/**
	 * Minimal PHP date() compatible formatter for common WordPress tokens.
	 */
	function wpDateFormat(date, format) {
		var monthsFull = [
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
		];
		var monthsShort = [
			'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
			'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
		];
		var daysFull = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		var daysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

		var result = '';
		var escaped = false;
		for (var i = 0; i < format.length; i++) {
			var ch = format[i];
			if (ch === '\\') {
				escaped = true;
				continue;
			}
			if (escaped) {
				result += ch;
				escaped = false;
				continue;
			}
			switch (ch) {
				// Day
				case 'd': result += pad(date.getDate()); break;
				case 'j': result += date.getDate(); break;
				case 'D': result += daysShort[date.getDay()]; break;
				case 'l': result += daysFull[date.getDay()]; break;
				case 'S':
					var day = date.getDate();
					if (day >= 11 && day <= 13) { result += 'th'; }
					else if (day % 10 === 1) { result += 'st'; }
					else if (day % 10 === 2) { result += 'nd'; }
					else if (day % 10 === 3) { result += 'rd'; }
					else { result += 'th'; }
					break;
				// Month
				case 'F': result += monthsFull[date.getMonth()]; break;
				case 'M': result += monthsShort[date.getMonth()]; break;
				case 'm': result += pad(date.getMonth() + 1); break;
				case 'n': result += (date.getMonth() + 1); break;
				// Year
				case 'Y': result += date.getFullYear(); break;
				case 'y': result += String(date.getFullYear()).slice(-2); break;
				// Time
				case 'H': result += pad(date.getHours()); break;
				case 'G': result += date.getHours(); break;
				case 'i': result += pad(date.getMinutes()); break;
				case 's': result += pad(date.getSeconds()); break;
				case 'g':
					var h12 = date.getHours() % 12;
					result += (h12 === 0 ? 12 : h12);
					break;
				case 'A': result += (date.getHours() >= 12 ? 'PM' : 'AM'); break;
				case 'a': result += (date.getHours() >= 12 ? 'pm' : 'am'); break;
				// Separators / literals
				default: result += ch; break;
			}
		}
		return result;
	}

	function pad(n) {
		return n < 10 ? '0' + n : '' + n;
	}

	/**
	 * Generate a simple unique ID.
	 */
	function generateId() {
		return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
	}

	function normalizeKeyPart(value) {
		return String(value || '').trim().toLowerCase();
	}

	function getHourBucket(timestamp) {
		var d = new Date(timestamp);
		d.setMinutes(0, 0, 0);
		return d.getTime();
	}

	function normalizeEntry(entry) {
		if (!entry || typeof entry !== 'object') {
			return null;
		}

		var normalized = {
			id: entry.id ? String(entry.id) : generateId(),
			type: entry.type ? String(entry.type) : '',
			data: (entry.data && typeof entry.data === 'object') ? entry.data : {},
			timestamp: parseInt(entry.timestamp, 10) || Date.now()
		};

		if (!normalized.type || normalized.type === 'login') {
			return null;
		}

		return normalized;
	}

	function getCleanElementText(selector, removeSelectors) {
		var el = document.querySelector(selector);
		if (!el) {
			return '';
		}

		var clone = el.cloneNode(true);
		(removeSelectors || []).forEach(function(removeSelector) {
			var matches = clone.querySelectorAll(removeSelector);
			for (var i = 0; i < matches.length; i++) {
				matches[i].remove();
			}
		});

		return String(clone.textContent || '').replace(/\s+/g, ' ').trim();
	}

	function getTargetKey(entry) {
		var d = (entry && entry.data) || {};
		var storyKey = normalizeKeyPart(d.storyUrl) || normalizeKeyPart(d.storyTitle);
		var chapterKey = normalizeKeyPart(d.chapterUrl) || normalizeKeyPart(d.chapterTitle);

		if (chapterKey) {
			return 'chapter:' + chapterKey + '|story:' + storyKey;
		}

		if (storyKey) {
			return 'story:' + storyKey;
		}

		return '';
	}

	function getMergeKey(entry) {
		var targetKey = getTargetKey(entry);

		switch (entry.type) {
			case 'read':
			case 'browse':
				return targetKey ? entry.type + ':' + targetKey + ':hour:' + getHourBucket(entry.timestamp) : '';
			case 'like':
			case 'dislike':
				return targetKey ? 'reaction:' + targetKey : '';
			case 'rate':
				return targetKey ? 'rating:' + targetKey : '';
			default:
				return '';
		}
	}

	function compactEntries(entries) {
		var normalizedEntries = Array.isArray(entries) ? entries.map(normalizeEntry).filter(Boolean) : [];
		var mergedEntries = [];
		var mergedIndexByKey = {};
		var seenIds = {};

		for (var i = 0; i < normalizedEntries.length; i++) {
			var entry = normalizedEntries[i];

			if (seenIds[entry.id]) {
				continue;
			}
			seenIds[entry.id] = true;

			var mergeKey = getMergeKey(entry);
			if (!mergeKey) {
				mergedEntries.push(entry);
				continue;
			}

			if (typeof mergedIndexByKey[mergeKey] === 'undefined') {
				mergedIndexByKey[mergeKey] = mergedEntries.length;
				mergedEntries.push(entry);
				continue;
			}

			var existingIndex = mergedIndexByKey[mergeKey];
			if (entry.timestamp >= mergedEntries[existingIndex].timestamp) {
				mergedEntries[existingIndex] = entry;
			}
		}

		mergedEntries.sort(function(a, b) {
			return b.timestamp - a.timestamp;
		});

		if (mergedEntries.length > MAX_ENTRIES) {
			mergedEntries = mergedEntries.slice(0, MAX_ENTRIES);
		}

		return mergedEntries;
	}

	/**
	 * Safely read the activity array from localStorage.
	 */
	function readStore() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			if (!raw) { return []; }
			var parsed = JSON.parse(raw);
			var compacted = compactEntries(Array.isArray(parsed) ? parsed : []);
			if (JSON.stringify(compacted) !== JSON.stringify(parsed)) {
				writeStore(compacted);
			}
			return compacted;
		} catch (e) {
			return [];
		}
	}

	/**
	 * Safely write the activity array to localStorage.
	 */
	function writeStore(entries) {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(compactEntries(entries)));
		} catch (e) {
			// Storage full or unavailable - silently fail
		}
	}

	/**
	 * Escape HTML to prevent XSS in rendered activity items.
	 */
	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	/**
	 * Get the dashicon class for an activity type.
	 */
	function getIcon(type) {
		var icons = {
			browse:           'dashicons-visibility',
			read:             'dashicons-book-alt',
			rate:             'dashicons-star-filled',
			like:             'dashicons-thumbs-up',
			dislike:          'dashicons-thumbs-down',
			bookmark_story:   'dashicons-heart',
			bookmark_chapter: 'dashicons-bookmark',
			comment:          'dashicons-admin-comments',
			login:            'dashicons-admin-users',
			logout:           'dashicons-migrate'
		};
		return icons[type] || 'dashicons-info';
	}

	/**
	 * Build the human-readable message HTML for an activity entry.
	 */
	function buildMessage(entry) {
		var d = entry.data || {};
		var storyLink = '';
		var chapterLink = '';

		if (d.storyTitle && d.storyUrl) {
			storyLink = '<a href="' + escHtml(d.storyUrl) + '">' + escHtml(d.storyTitle) + '</a>';
		} else if (d.storyTitle) {
			storyLink = '<strong>' + escHtml(d.storyTitle) + '</strong>';
		}

		if (d.chapterTitle && d.chapterUrl) {
			chapterLink = '<a href="' + escHtml(d.chapterUrl) + '">' + escHtml(d.chapterTitle) + '</a>';
		} else if (d.chapterTitle) {
			chapterLink = '<strong>' + escHtml(d.chapterTitle) + '</strong>';
		}

		switch (entry.type) {
			case 'browse':
				return 'You browsed ' + storyLink;

			case 'read':
				if (chapterLink && storyLink) {
					return 'You read ' + chapterLink + ', part of ' + storyLink;
				}
				return 'You read ' + (chapterLink || storyLink);

			case 'rate':
				var stars = d.ratingValue ? d.ratingValue : '?';
				if (chapterLink) {
					return 'You rated (' + escHtml(String(stars)) + ' stars) ' + chapterLink;
				}
				return 'You rated (' + escHtml(String(stars)) + ' stars) ' + storyLink;

			case 'like':
				return 'You liked ' + (chapterLink || storyLink);

			case 'dislike':
				return 'You disliked ' + (chapterLink || storyLink);

			case 'bookmark_story':
				return 'You bookmarked ' + storyLink;

			case 'bookmark_chapter':
				if (chapterLink && storyLink) {
					return 'You bookmarked ' + chapterLink + ' of ' + storyLink;
				}
				return 'You bookmarked ' + (chapterLink || storyLink);

			case 'comment':
				if (chapterLink && storyLink) {
					return 'You commented on ' + chapterLink + ' of ' + storyLink;
				}
				return 'You commented on ' + (chapterLink || storyLink);

			case 'login':
				return 'You logged in';

			case 'logout':
				return 'You logged out';

			default:
				return 'Activity recorded';
		}
	}

	/**
	 * The global FanficRecentActivity object.
	 */
	var FanficRecentActivity = {

		/**
		 * Log a new activity entry.
		 *
		 * @param {string} type  Activity type (browse, read, rate, like, etc.)
		 * @param {Object} data  Context data (storyTitle, storyUrl, chapterTitle, chapterUrl, ratingValue, etc.)
		 */
		log: function(type, data) {
			var entries = readStore();
			var entry = {
				id: generateId(),
				type: type,
				data: data || {},
				timestamp: Date.now()
			};

			// Prepend (newest first)
			entries.unshift(entry);

			// Cap at MAX_ENTRIES
			if (entries.length > MAX_ENTRIES) {
				entries = entries.slice(0, MAX_ENTRIES);
			}

			writeStore(entries);

			// Dispatch a custom event so the dashboard can update in real-time
			try {
				window.dispatchEvent(new CustomEvent('fanfic-activity-logged', { detail: entry }));
			} catch (e) {
				// IE fallback - no real-time update
			}
		},

		/**
		 * Get all activity entries (newest first).
		 */
		getAll: function() {
			return readStore();
		},

		/**
		 * Remove a single entry by ID.
		 */
		remove: function(id) {
			var entries = readStore();
			entries = entries.filter(function(e) { return e.id !== id; });
			writeStore(entries);
		},

		/**
		 * Clear all activity entries.
		 */
		clearAll: function() {
			writeStore([]);
		},

		/**
		 * Get total entry count.
		 */
		getCount: function() {
			return readStore().length;
		},

		/**
		 * Render the activity widget into a container element.
		 *
		 * @param {HTMLElement} container The widget container element.
		 */
		renderWidget: function(container) {
			if (!container) { return; }

			var entries = readStore();
			var currentPage = 1;

			function render() {
				var visibleCount = currentPage * PAGE_SIZE;
				var visible = entries.slice(0, visibleCount);
				var hasMore = entries.length > visibleCount;

				if (entries.length === 0) {
					container.innerHTML =
						'<div class="fanfic-notifications-empty">' +
							'<p>' + escHtml(getStr('emptyMessage')) + '</p>' +
						'</div>';
					return;
				}

				var html = '<div class="fanfic-notifications-list">';
				for (var i = 0; i < visible.length; i++) {
					var entry = visible[i];
					html +=
						'<div class="fanfic-notification-item" data-activity-id="' + escHtml(entry.id) + '">' +
							'<div class="fanfic-notification-icon" aria-hidden="true">' +
								'<span class="dashicons ' + escHtml(getIcon(entry.type)) + '"></span>' +
							'</div>' +
							'<div class="fanfic-notification-content">' +
								'<p class="fanfic-notification-message">' + buildMessage(entry) + '</p>' +
								'<time class="fanfic-notification-timestamp">' + escHtml(formatTimestamp(entry.timestamp)) + '</time>' +
							'</div>' +
							'<div class="fanfic-notification-actions">' +
								'<button type="button" class="fanfic-notification-dismiss fanfic-activity-dismiss" data-activity-id="' + escHtml(entry.id) + '" aria-label="' + escHtml(getStr('dismiss')) + '" title="' + escHtml(getStr('dismiss')) + '">' +
									'<span class="dashicons dashicons-no-alt"></span>' +
								'</button>' +
							'</div>' +
						'</div>';
				}
				html += '</div>';

				// Footer
				html += '<div class="fanfic-notifications-footer">';
				if (hasMore) {
					html += '<button type="button" class="fanfic-notification-show-more fanfic-activity-show-more">' + escHtml(getStr('showMore')) + '</button>';
				} else {
					html += '<span></span>';
				}
				if (entries.length > 0) {
					html += '<button type="button" class="fanfic-notification-clear-all fanfic-activity-clear-all">' + escHtml(getStr('clearAll')) + '</button>';
				}
				html += '</div>';

				container.innerHTML = html;
			}

			function getStr(key) {
				var strings = (typeof fanficActivity !== 'undefined' && fanficActivity.strings) ? fanficActivity.strings : {};
				var defaults = {
					emptyMessage: 'No recent activity',
					showMore: 'Show more',
					clearAll: 'Clear all',
					dismiss: 'Dismiss'
				};
				return strings[key] || defaults[key] || key;
			}

			// Event delegation
			container.addEventListener('click', function(e) {
				var dismissBtn = e.target.closest('.fanfic-activity-dismiss');
				if (dismissBtn) {
					e.preventDefault();
					var id = dismissBtn.getAttribute('data-activity-id');
					var item = dismissBtn.closest('.fanfic-notification-item');
					if (item) {
						item.classList.add('dismissing');
						setTimeout(function() {
							FanficRecentActivity.remove(id);
							entries = readStore();
							render();
						}, 300);
					}
					return;
				}

				var showMoreBtn = e.target.closest('.fanfic-activity-show-more');
				if (showMoreBtn) {
					e.preventDefault();
					currentPage++;
					render();
					return;
				}

				var clearBtn = e.target.closest('.fanfic-activity-clear-all');
				if (clearBtn) {
					e.preventDefault();
					FanficRecentActivity.clearAll();
					entries = [];
					render();
					return;
				}
			});

			render();

			// Listen for new activity events to update in real-time (if on dashboard)
			window.addEventListener('fanfic-activity-logged', function() {
				entries = readStore();
				render();
			});

			// Cross-tab sync
			window.addEventListener('storage', function(e) {
				if (e.key === STORAGE_KEY) {
					entries = readStore();
					render();
				}
			});
		},

		/**
		 * Helper: extract page context data from the current DOM.
		 * Returns { storyId, storyTitle, storyUrl, chapterId, chapterTitle, chapterUrl } or null.
		 */
		getPageContext: function() {
			// Try share button first (most reliable for titles/URLs)
			var shareBtn = document.querySelector('.fanfic-button-share[data-share-title]');
			var ctx = {};

			// Get story/chapter IDs from any action button
			var actionBtn = document.querySelector('[data-story-id]');
			if (actionBtn) {
				ctx.storyId = parseInt(actionBtn.getAttribute('data-story-id'), 10) || 0;
				ctx.chapterId = parseInt(actionBtn.getAttribute('data-chapter-id'), 10) || 0;
			}

			if (shareBtn) {
				ctx.shareTitle = shareBtn.getAttribute('data-share-title') || '';
				ctx.shareUrl = shareBtn.getAttribute('data-share-url') || '';
			}

			// Detect if we're on a chapter page (chapterId > 0) vs story page
			if (ctx.chapterId > 0) {
				// Chapter page
				ctx.chapterTitle = getCleanElementText('.fanfic-chapter-title', [
					'.fanfic-badge',
					'.fanfic-read-indicator',
					'.screen-reader-text'
				]) || (ctx.shareTitle || document.title || '').replace(/\s*[|\-–]\s.+$/, '');
				ctx.chapterUrl = ctx.shareUrl || window.location.href;

				// Try to find the story title from the story title element
				var storyTitleEl = document.querySelector('.fanfic-chapter-story-context a, .fanfic-story-title-link, .fanfic-chapter-story-context');
				if (storyTitleEl) {
					ctx.storyTitle = storyTitleEl.textContent.trim();
					if (storyTitleEl.tagName === 'A') {
						ctx.storyUrl = storyTitleEl.href || '';
					}
				}
			} else if (ctx.storyId > 0) {
				// Story page
				ctx.storyTitle = (ctx.shareTitle || document.title || '').replace(/\s*[|\-–]\s.+$/, '');
				ctx.storyUrl = ctx.shareUrl || window.location.href;
			}

			if (!ctx.storyId && !ctx.chapterId) {
				return null;
			}

			return ctx;
		}
	};

	// Expose globally
	window.FanficRecentActivity = FanficRecentActivity;

	// =====================================================================
	// AUTO-TRACKING
	// Hooks into user actions via DOM event listeners (no modifications to
	// the existing fanfiction-interactions.js file).
	// =====================================================================

	/**
	 * Helper: get the interaction context from a button's data attributes.
	 * Resolves story/chapter titles and URLs from the DOM.
	 */
	function getContextFromButton(button) {
		var ctx = {};
		ctx.storyId = parseInt(button.getAttribute('data-story-id') || button.dataset.storyId || 0, 10) || 0;
		ctx.chapterId = parseInt(button.getAttribute('data-chapter-id') || button.dataset.chapterId || 0, 10) || 0;

		// Try share button for canonical title/URL
		var shareBtn = document.querySelector('.fanfic-button-share[data-share-title]');
		if (shareBtn) {
			ctx.shareTitle = shareBtn.getAttribute('data-share-title') || '';
			ctx.shareUrl = shareBtn.getAttribute('data-share-url') || '';
		}

		if (ctx.chapterId > 0) {
			ctx.chapterTitle = getCleanElementText('.fanfic-chapter-title', [
				'.fanfic-badge',
				'.fanfic-read-indicator',
				'.screen-reader-text'
			]) || (ctx.shareTitle || document.title || '').replace(/\s*[|\-–]\s.+$/, '');
			ctx.chapterUrl = ctx.shareUrl || window.location.href;

			var storyTitleEl = document.querySelector('.fanfic-chapter-story-context a, .fanfic-story-title-link, .fanfic-chapter-story-context');
			if (storyTitleEl) {
				ctx.storyTitle = storyTitleEl.textContent.trim();
				if (storyTitleEl.tagName === 'A') {
					ctx.storyUrl = storyTitleEl.href || '';
				}
			}
		} else if (ctx.storyId > 0) {
			ctx.storyTitle = (ctx.shareTitle || document.title || '').replace(/\s*[|\-–]\s.+$/, '');
			ctx.storyUrl = ctx.shareUrl || window.location.href;
		}

		return ctx;
	}

	/**
	 * Deduplicate check: don't log the same type+target within a short window.
	 */
	var _recentLogCache = {};
	function shouldLog(type, targetKey) {
		var key = type + ':' + targetKey;
		var now = Date.now();
		if (_recentLogCache[key] && (now - _recentLogCache[key]) < 2000) {
			return false;
		}
		_recentLogCache[key] = now;
		return true;
	}

	document.addEventListener('DOMContentLoaded', function() {
		writeStore(readStore());

		// ----- Dashboard widget -----
		var widgetContainer = document.getElementById('fanfic-recent-activity-list');
		if (widgetContainer) {
			FanficRecentActivity.renderWidget(widgetContainer);
		}

		// ----- Browse / Read tracking (page load) -----
		var pageCtx = FanficRecentActivity.getPageContext();
		if (pageCtx) {
			if (pageCtx.chapterId > 0) {
				// Chapter page = "You read chapter X, part of story Y"
				FanficRecentActivity.log('read', {
					storyTitle: pageCtx.storyTitle || '',
					storyUrl: pageCtx.storyUrl || '',
					chapterTitle: pageCtx.chapterTitle || '',
					chapterUrl: pageCtx.chapterUrl || ''
				});
			} else if (pageCtx.storyId > 0) {
				// Story page = "You browsed story X"
				FanficRecentActivity.log('browse', {
					storyTitle: pageCtx.storyTitle || '',
					storyUrl: pageCtx.storyUrl || ''
				});
			}
		}

		// ----- Like / Dislike tracking -----
		document.addEventListener('click', function(e) {
			var likeBtn = e.target.closest('.fanfic-like-button, .fanfic-button-like');
			if (likeBtn) {
				var ctx = getContextFromButton(likeBtn);
				// Defer to let the interactions handler update localStorage first
				setTimeout(function() {
					if (typeof FanficLocalStore !== 'undefined') {
						var entry = FanficLocalStore.getChapter(ctx.storyId, ctx.chapterId) || {};
						if (entry.like) {
							if (shouldLog('like', ctx.storyId + ':' + ctx.chapterId)) {
								FanficRecentActivity.log('like', {
									chapterTitle: ctx.chapterTitle || '',
									chapterUrl: ctx.chapterUrl || '',
									storyTitle: ctx.storyTitle || '',
									storyUrl: ctx.storyUrl || ''
								});
							}
						}
					}
				}, 50);
				return;
			}

			var dislikeBtn = e.target.closest('.fanfic-dislike-button, .fanfic-button-dislike');
			if (dislikeBtn) {
				var dCtx = getContextFromButton(dislikeBtn);
				setTimeout(function() {
					if (typeof FanficLocalStore !== 'undefined') {
						var dEntry = FanficLocalStore.getChapter(dCtx.storyId, dCtx.chapterId) || {};
						if (dEntry.dislike) {
							if (shouldLog('dislike', dCtx.storyId + ':' + dCtx.chapterId)) {
								FanficRecentActivity.log('dislike', {
									chapterTitle: dCtx.chapterTitle || '',
									chapterUrl: dCtx.chapterUrl || '',
									storyTitle: dCtx.storyTitle || '',
									storyUrl: dCtx.storyUrl || ''
								});
							}
						}
					}
				}, 50);
				return;
			}
		}, true);

		// ----- Rating tracking -----
		document.addEventListener('click', function(e) {
			var starHit = e.target.closest('.fanfic-rating-stars-half .fanfic-star-hit');
			if (!starHit) { return; }

			var root = starHit.closest('[data-story-id][data-chapter-id]');
			if (!root) { return; }

			// Left half of star 1 = remove rating
			var isRemove = starHit.classList.contains('fanfic-star-hit-left') &&
				starHit.closest('.fanfic-star-wrap') &&
				starHit.closest('.fanfic-star-wrap').getAttribute('data-star') === '1';

			if (isRemove) { return; } // Don't log rating removals

			var ratingValue = parseFloat(starHit.getAttribute('data-value'));
			if (!ratingValue) { return; }

			var rCtx = getContextFromButton(root);
			if (shouldLog('rate', rCtx.storyId + ':' + rCtx.chapterId)) {
				FanficRecentActivity.log('rate', {
					ratingValue: ratingValue,
					chapterTitle: rCtx.chapterTitle || '',
					chapterUrl: rCtx.chapterUrl || '',
					storyTitle: rCtx.storyTitle || '',
					storyUrl: rCtx.storyUrl || ''
				});
			}
		}, true);

		// ----- Follow / Bookmark tracking -----
		document.addEventListener('click', function(e) {
			var followBtn = e.target.closest('.fanfic-follow-button, .fanfic-button-follow');
			if (!followBtn) { return; }

			var fCtx = getContextFromButton(followBtn);
			setTimeout(function() {
				if (typeof FanficLocalStore !== 'undefined') {
					var fEntry = FanficLocalStore.getChapter(fCtx.storyId, fCtx.chapterId) || {};
					if (fEntry.follow) {
						var isChapter = fCtx.chapterId > 0;
						var logType = isChapter ? 'bookmark_chapter' : 'bookmark_story';
						if (shouldLog(logType, fCtx.storyId + ':' + fCtx.chapterId)) {
							FanficRecentActivity.log(logType, {
								storyTitle: fCtx.storyTitle || '',
								storyUrl: fCtx.storyUrl || '',
								chapterTitle: isChapter ? (fCtx.chapterTitle || '') : '',
								chapterUrl: isChapter ? (fCtx.chapterUrl || '') : ''
							});
						}
					}
				}
			}, 50);
		}, true);

		// ----- Comment tracking -----
		// WordPress native comment form posts to wp-comments-post.php.
		// Intercept submission to log activity before navigation.
		var commentForm = document.getElementById('commentform');
		if (commentForm) {
			commentForm.addEventListener('submit', function() {
				var cCtx = FanficRecentActivity.getPageContext();
				if (!cCtx) { return; }

				var data = {
					storyTitle: cCtx.storyTitle || '',
					storyUrl: cCtx.storyUrl || ''
				};
				if (cCtx.chapterId > 0) {
					data.chapterTitle = cCtx.chapterTitle || '';
					data.chapterUrl = cCtx.chapterUrl || '';
				}

				FanficRecentActivity.log('comment', data);
			});
		}

		// ----- Logout tracking -----
		// Intercept clicks on WordPress logout links.
		document.addEventListener('click', function(e) {
			var link = e.target.closest('a[href*="wp-login.php?action=logout"]');
			if (!link) {
				link = e.target.closest('a[href*="action=logout"]');
			}
			if (link) {
				FanficRecentActivity.log('logout', {});
			}
		}, true);
	});

})();
