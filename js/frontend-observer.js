/**
 * @file js/frontend-observer.js
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * MathJax for OJS — typesets content that is added to a reader-facing page
 * after it has loaded.
 *
 * MathJax typesets the page once, when it loads. Themes and plugins also insert
 * content later (for example the citation formats loaded by the "How to cite"
 * box, or live search results). This observer watches for such insertions and
 * typesets only the added elements, and only when they contain a formula
 * delimiter, so pages without late content cost nothing.
 *
 * MathJax's own output is ignored, nothing is observed while a typeset pass
 * runs, additions are batched, and a circuit breaker stops the observer if a
 * page keeps inserting content in a loop.
 */
(function () {
	'use strict';

	var DEBOUNCE_MS = 150;
	// Stop after this many typeset passes within the time window.
	var MAX_PASSES = 40;
	var WINDOW_MS = 10000;
	// Must match MathJaxRenderPlugin::IGNORE_CLASS (MathJax's ignoreHtmlClass).
	var IGNORE_CLASS = 'mathJaxRender_ignore';
	var DONE_ATTRIBUTE = 'data-mathjaxrender-done';
	var SKIP_SELECTOR = 'script, style, textarea, pre, code, noscript, [contenteditable="true"], [contenteditable=""], .' + IGNORE_CLASS;

	var pending = [];
	var timer = null;
	var passes = [];
	var stopped = false;
	var typesetting = false;
	var delimiters = [];

	function collectDelimiters() {
		var tex = (window.MathJax && window.MathJax.config && window.MathJax.config.tex) || {};
		var lists = [].concat(tex.inlineMath || [], tex.displayMath || []);
		for (var i = 0; i < lists.length; i++) {
			if (lists[i] && lists[i][0]) {
				delimiters.push(lists[i][0]);
			}
		}
	}

	function hasDelimiter(text) {
		for (var i = 0; i < delimiters.length; i++) {
			if (text.indexOf(delimiters[i]) !== -1) {
				return true;
			}
		}
		return false;
	}

	function isMathJaxNode(el) {
		if (el.tagName && el.tagName.lastIndexOf('MJX-', 0) === 0) {
			return true;
		}
		return !!(el.closest && el.closest('mjx-container, .MathJax, #MJX-CHTML-styles'));
	}

	// The element to typeset for an added node, or null when there is nothing
	// for MathJax to do.
	function candidate(node) {
		var el = node.nodeType === 1 ? node : node.parentElement;
		if (!el || !el.closest || isMathJaxNode(el) || el.closest(SKIP_SELECTOR)) {
			return null;
		}
		// A text node added to an element that has already been typeset would
		// make MathJax read that element's earlier text again.
		if (node.nodeType === 3 && el.closest('[' + DONE_ATTRIBUTE + ']')) {
			return null;
		}
		return hasDelimiter(node.nodeType === 3 ? node.data : (el.textContent || '')) ? el : null;
	}

	function flush() {
		timer = null;
		if (stopped || !pending.length || !window.MathJax || !window.MathJax.typesetPromise) {
			pending = [];
			return;
		}
		var now = new Date().getTime();
		passes = passes.filter(function (t) {
			return now - t < WINDOW_MS;
		});
		if (passes.length >= MAX_PASSES) {
			stopped = true;
			pending = [];
			return;
		}
		var nodes = pending.filter(function (el) {
			return document.documentElement.contains(el);
		});
		pending = [];
		if (!nodes.length) {
			return;
		}
		passes.push(now);
		typesetting = true;
		window.MathJax.typesetPromise(nodes).then(function () {
			for (var i = 0; i < nodes.length; i++) {
				nodes[i].setAttribute(DONE_ATTRIBUTE, '');
			}
		}).catch(function () {
			// Malformed TeX in late content must never break the page.
		}).then(function () {
			// Release on the next tick so that the mutation records produced by
			// this pass are delivered while the flag is still set.
			setTimeout(function () {
				typesetting = false;
			}, 0);
		});
	}

	function enqueue(el) {
		for (var i = 0; i < pending.length; i++) {
			if (pending[i] === el || pending[i].contains(el)) {
				return;
			}
		}
		pending.push(el);
		if (!timer) {
			timer = setTimeout(flush, DEBOUNCE_MS);
		}
	}

	function start() {
		if (!document.body || !window.MutationObserver) {
			return;
		}
		collectDelimiters();
		new MutationObserver(function (mutations) {
			if (stopped || typesetting) {
				return;
			}
			for (var i = 0; i < mutations.length; i++) {
				var added = mutations[i].addedNodes;
				for (var j = 0; j < added.length; j++) {
					if (added[j].nodeType === 1 || added[j].nodeType === 3) {
						var el = candidate(added[j]);
						if (el) {
							enqueue(el);
						}
					}
				}
			}
		}).observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	// The MathJax library is loaded before this script, but its startup
	// finishes asynchronously; start observing once it has.
	function whenReady(attempt) {
		var mj = window.MathJax;
		if (mj && mj.startup && mj.startup.promise) {
			mj.startup.promise.then(start);
		} else if (attempt < 100) {
			setTimeout(function () {
				whenReady(attempt + 1);
			}, 100);
		}
	}

	whenReady(0);
})();
