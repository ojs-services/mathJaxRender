/**
 * @file js/backend-observer.js
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * MathJax for OJS — backend re-typeset observer (EXPERIMENTAL).
 *
 * The OJS 3.3 backend is a Vue.js SPA: list contents (e.g. submission titles)
 * arrive via AJAX after the page has loaded, so MathJax's one-shot automatic
 * typeset misses them. This observer watches for DOM changes and re-runs the
 * typesetter, debounced.
 *
 * Typesetting itself mutates the DOM (measurement probes, text-node splits,
 * container insertion), so mutations are ignored both while a typeset is in
 * flight and whenever they originate from MathJax's own output — otherwise the
 * observer would keep re-triggering itself.
 */
(function () {
	'use strict';

	var DEBOUNCE_MS = 300;
	// Must match MathJaxRenderPlugin::IGNORE_CLASS, which is configured as
	// MathJax's ignoreHtmlClass.
	var IGNORE_CLASS = 'mathJaxRender_ignore';
	// Editing surfaces: rich-text editors (TinyMCE runs inline, so its editing
	// area is a contenteditable element in this very document) must never be
	// typeset — an editor saving the field would otherwise store MathJax's
	// markup instead of the author's LaTeX.
	var EDITABLE_SELECTOR = '[contenteditable="true"], [contenteditable=""], .mce-content-body, .tox';
	var pending = null;
	var typesetting = false;

	// Mark every editing surface currently in the document. New editors are
	// created by Vue as the user navigates, so this runs before each pass.
	function protectEditableAreas() {
		var nodes = document.querySelectorAll(EDITABLE_SELECTOR);
		for (var i = 0; i < nodes.length; i++) {
			if (!nodes[i].classList.contains(IGNORE_CLASS)) {
				nodes[i].classList.add(IGNORE_CLASS);
			}
		}
	}

	function runTypeset() {
		pending = null;
		if (!window.MathJax || !window.MathJax.typesetPromise) {
			return;
		}
		typesetting = true;
		protectEditableAreas();
		window.MathJax.typesetPromise()
			.catch(function () {
				// A failed typeset (e.g. malformed TeX) must never break the UI.
			})
			.then(function () {
				// Release on the next tick so the mutation records produced by
				// this typeset are delivered while the flag is still set.
				setTimeout(function () {
					typesetting = false;
				}, 0);
			});
	}

	function scheduleTypeset() {
		if (pending) {
			clearTimeout(pending);
		}
		pending = setTimeout(runTypeset, DEBOUNCE_MS);
	}

	// True for MathJax's own output and its measurement/style helpers, all of
	// which use the mjx- element prefix or MathJax's own class/id names.
	function isMathJaxNode(node) {
		if (!node) {
			return false;
		}
		if (node.nodeType === 1 && node.tagName && node.tagName.lastIndexOf('MJX-', 0) === 0) {
			return true;
		}
		var el = node.nodeType === 1 ? node : node.parentElement;
		return !!(el && el.closest && el.closest('mjx-container, .MathJax, #MJX-CHTML-styles'));
	}

	function relevantMutation(m) {
		if (isMathJaxNode(m.target)) {
			return false;
		}
		if (m.type === 'characterData') {
			return true;
		}
		for (var i = 0; i < m.addedNodes.length; i++) {
			var node = m.addedNodes[i];
			if (isMathJaxNode(node)) {
				continue;
			}
			// Only new text-bearing content can contain formulas.
			if (node.nodeType === 3 || node.nodeType === 1) {
				return true;
			}
		}
		return false;
	}

	function start() {
		if (!document.body || !window.MutationObserver) {
			return;
		}
		var observer = new MutationObserver(function (mutations) {
			if (typesetting) {
				return;
			}
			for (var i = 0; i < mutations.length; i++) {
				if (relevantMutation(mutations[i])) {
					scheduleTypeset();
					return;
				}
			}
		});
		observer.observe(document.body, {
			childList: true,
			subtree: true,
			characterData: true
		});
		// Protect anything already on the page before the first pass, then
		// typeset the content present at load time.
		protectEditableAreas();
		scheduleTypeset();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
