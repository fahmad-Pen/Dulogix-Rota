// Give dynamically rendered rota controls a stable DOM identifier for accessibility audits.
(() => {
  let nextId = 0;
  const assign = root => {
    if (root.nodeType !== 1) return;
    const controls = root.matches?.('input,select,textarea') ? [root] : [];
    root.querySelectorAll?.('input,select,textarea').forEach(control => controls.push(control));
    for (const control of controls) {
      if (!control.id && !control.name) {
        let id;
        do { id = 'rota-control-' + (++nextId); } while (document.getElementById(id));
        control.id = id;
      }
    }
  };
  const start = () => {
    const main = document.querySelector('main');
    if (!main) return;
    assign(main);
    const observer = new MutationObserver(records => {
      for (const record of records) for (const node of record.addedNodes) assign(node);
    });
    observer.observe(main, {childList:true, subtree:true});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
