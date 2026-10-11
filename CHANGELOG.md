# Changelog — MathJax for OJS (mathJaxRender)

This branch supports **OJS 3.4 and 3.5**. OJS 3.3 is served by the 1.x releases.

## 2.0.0.10 — 2026-10-11

First public release.

- Renders inline `\( ... \)` and display `$$ ... $$` / `\[ ... \]` TeX formulas in article titles, abstracts,
  references, the *How to cite* box, issue and search listings and HTML full texts.
- Runs MathJax in safe mode: links written in formulas are limited to http and https, and the
  class, id and style macros are restricted.
- Per-journal settings: the pages MathJax is loaded on, a bundled self-hosted MathJax 3.2.2 copy
  (default) or the jsDelivr CDN, optional single-dollar inline math, optional MathML and AsciiMath
  input.
- Typesets content that a page inserts after it has loaded, such as the citation formats of the
  *How to cite* box.
- Optional rendering in the editorial interface (experimental, off by default); rich-text editors
  are never typeset.
- Settings form and plugin description in English, Spanish and Turkish.
- Readme in English and Turkish, with screenshots, tested versions, external requests and upgrade
  notes.
