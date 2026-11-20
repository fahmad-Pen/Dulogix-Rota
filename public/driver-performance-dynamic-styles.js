/* Apply server-selected scoring colours through CSSOM properties.
   Avoid inline style attributes while preserving configurable metric palettes. */
(() => {
  'use strict';
  const sheet = new CSSStyleSheet();
  document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
  const ruleCache = new Map();
  const apply = (root) => {
    const nodes = [];
    if (root.nodeType === 1 && root.hasAttribute('data-perf-style')) nodes.push(root);
    if (root.querySelectorAll) nodes.push(...root.querySelectorAll('[data-perf-style]'));
    for (const node of nodes) {
      const declaration = node.getAttribute('data-perf-style') || '';
      // Only validated rich-text declarations or server-defined score colours reach here.
      const safe = declaration.split(';').map(part => {
        const separator = part.indexOf(':');
        if (separator < 1) return '';
        const property = part.slice(0, separator).trim().toLowerCase();
        const value = part.slice(separator + 1).trim();
        if (!/^(color|background-color|font-size|text-align|background)$/.test(property)) return '';
        if (!/^(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|[a-z-]{2,25}|\d{1,3}px)$/i.test(value)) return '';
        return property + ':' + value;
      }).filter(Boolean).join(';');
      if (safe) {
        let cls = ruleCache.get(safe);
        if (!cls) {
          cls = 'perf-dynamic-' + ruleCache.size;
          sheet.insertRule('.' + cls + '{' + safe + '}', sheet.cssRules.length);
          ruleCache.set(safe, cls);
        }
        node.classList.add(cls);
      }
      node.removeAttribute('data-perf-style');
    }
  };
  apply(document);
})();
