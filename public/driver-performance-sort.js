(function () {
  "use strict";
  const table = document.getElementById("performance-drivers");
  if (!table || !table.tBodies.length) return;
  const tbody = table.tBodies[0];
  const rows = Array.from(tbody.rows);
  const columns = [
    { header: table.tHead.rows[0].cells[0], key: "name", label: "Driver name" },
    { header: table.tHead.rows[0].cells[1], key: "score", label: "Total Score" },
    { header: table.tHead.rows[1].cells[0], key: "fico", label: "Ementor Score" },
    { header: table.tHead.rows[1].cells[1], key: "net", label: "Netradyne Deductions" },
    { header: table.tHead.rows[1].cells[2], key: "dwc", label: "Delivery Workflow Compliance" },
    { header: table.tHead.rows[1].cells[3], key: "psb", label: "PSB Collection Fails" },
    { header: table.tHead.rows[1].cells[4], key: "qty", label: "DSC DPMO Qty" },
    { header: table.tHead.rows[1].cells[5], key: "pod", label: "POD" },
    { header: table.tHead.rows[1].cells[6], key: "cc", label: "CC" },
    { header: table.tHead.rows[1].cells[7], key: "ce", label: "CE" },
    { header: table.tHead.rows[1].cells[8], key: "cdf", label: "Negative Feedback" }
  ];
  function value(row, key) {
    if (key === "name") return (row.cells[0]?.querySelector("strong, b")?.textContent || row.cells[0]?.textContent || "").trim().toLocaleLowerCase();
    const report = row.querySelector(".driver-report-open");
    if (!report) return null;
    const raw = report.dataset[key];
    if (raw == null || !raw.trim() || /^(?:n\/a|pending|—|-)$/i.test(raw.trim())) return null;
    const number = Number(raw.replace(/[% ,]/g, ""));
    return Number.isFinite(number) ? number : null;
  }
  let currentKey = null;
  let currentDirection = null;
  const controls = [];
  function sort(key, direction) {
    currentKey = key;
    currentDirection = direction;
    const sorted = rows.map((row, index) => ({ row, index, number: value(row, key) }));
    sorted.sort((a, b) => {
      if (a.number === null) return b.number === null ? a.index - b.index : 1;
      if (b.number === null) return -1;
      return direction * (key === "name" ? a.number.localeCompare(b.number) : a.number - b.number) || a.index - b.index;
    });
    tbody.append(...sorted.map(item => item.row));
    controls.forEach(({ button, key: buttonKey, direction: buttonDirection }) => {
      const active = buttonKey === currentKey && buttonDirection === currentDirection;
      button.setAttribute("aria-pressed", String(active));
      button.classList.toggle("active", active);
    });
  }
  // Bottom-ten shortcuts are intentionally unavailable for CE and feedback.
  const bottomRow = table.tHead.insertRow(0);
  bottomRow.className = "performance-bottom-row";
  const bottomDialog = document.createElement("dialog");
  bottomDialog.className = "performance-bottom-dialog";
  bottomDialog.setAttribute("aria-label", "Bottom 10 drivers");
  document.body.append(bottomDialog);
  function showBottom(key, label) {
    const ranked = rows.map((row, index) => ({ row, index, metric: value(row, key) }))
      .filter(item => item.metric !== null)
      .sort((a, b) => key === "name"
        ? b.metric.localeCompare(a.metric) || a.index - b.index
        : ((key === "psb" || key === "qty") ? b.metric - a.metric : a.metric - b.metric) || a.index - b.index)
      .slice(0, 10);
    bottomDialog.replaceChildren();
    const heading = document.createElement("h2");
    heading.textContent = "Bottom 10 · " + label;
    const note = document.createElement("p");
    note.textContent = key === "name" ? "Last 10 driver names in alphabetical order." : (key === "psb" || key === "qty") ? "Highest quantities first (worse performance). Missing values are excluded." : "Lowest 10 values for this metric. Missing values are excluded.";
    const list = document.createElement("ol");
    ranked.forEach(({ row, metric }) => {
      const item = document.createElement("li");
      const name = row.cells[0]?.querySelector("strong, b")?.textContent?.trim() || row.cells[0]?.textContent?.trim() || "Driver";
      const person = document.createElement("strong");
      person.textContent = name;
      const number = document.createElement("span");
      number.textContent = String(metric);
      item.append(person, number);
      list.append(item);
    });
    if (!ranked.length) note.textContent = "No values available for this metric.";
    const close = document.createElement("button");
    close.type = "button";
    close.textContent = "Close";
    close.addEventListener("click", () => bottomDialog.close());
    bottomDialog.append(heading, note, list, close);
    bottomDialog.showModal();
  }
  columns.forEach(({ key, label }, index) => {
    const cell = document.createElement("th");
    cell.scope = "col";
    if (index < 9) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "performance-bottom-button";
      button.textContent = "Bottom 10";
      button.title = "Show bottom 10: " + label;
      button.setAttribute("aria-label", button.title);
      button.addEventListener("click", () => showBottom(key, label));
      cell.append(button);
    }
    bottomRow.append(cell);
  });
  bottomDialog.addEventListener("click", event => {
    if (event.target === bottomDialog) bottomDialog.close();
  });
  // A dedicated sorting row keeps the group and metric headings aligned.
  const sortRow = table.tHead.insertRow(0);
  sortRow.className = "performance-sort-row";
  const style = document.createElement("style");
  style.textContent = "#performance-drivers thead tr.performance-sort-row th{background:#aebbc9;color:#102d48;border:1px solid #8395a8;padding:5px 2px!important;height:38px;min-width:0;text-align:center;vertical-align:middle}#performance-drivers thead tr:nth-child(3) th{background:#aebbc9;color:#102d48;text-align:center;border:1px solid #8395a8;padding:15px 10px;font-size:12px}#performance-drivers thead tr:nth-child(4) th{background:#d8e1eb;color:#102d48;text-align:center;border:1px solid #a5b4c4;padding:8px 5px;font-size:13px;line-height:1.4}#performance-drivers thead tr:nth-child(4) th small{display:block;color:#bb2437;font-weight:800;margin-top:5px}#performance-drivers .performance-sort-controls{display:flex;justify-content:center;gap:4px;margin:0}#performance-drivers .performance-sort-button{font-size:14px;line-height:1.2;padding:2px 6px}";
  style.textContent += "#performance-drivers thead tr.performance-bottom-row th{background:#aebbc9;border:1px solid #8395a8;padding:7px 2px;height:43px;text-align:center}#performance-drivers .performance-bottom-button{background:#17456c;color:#fff;border:1px solid #17456c;border-radius:5px;padding:5px 7px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap}#performance-drivers .performance-bottom-button:hover{background:#0e304d}.performance-bottom-dialog{border:1px solid #bed0e1;border-radius:14px;padding:24px;width:min(540px,94vw);max-height:85vh;color:#173b60;box-shadow:0 16px 60px #001b3540}.performance-bottom-dialog::backdrop{background:#102a45a8}.performance-bottom-dialog h2{margin:0 0 8px}.performance-bottom-dialog p{color:#55718c}.performance-bottom-dialog ol{padding-left:28px;max-height:55vh;overflow:auto}.performance-bottom-dialog li{padding:9px 5px;border-bottom:1px solid #d8e1eb}.performance-bottom-dialog li span{float:right;font-weight:700}.performance-bottom-dialog>button{margin-top:12px;padding:9px 20px;border-radius:6px;background:#17456c;color:white;border:0;cursor:pointer}";
  document.head.append(style);
  columns.forEach(({ key, label }) => {
    const cell = document.createElement("th");
    cell.scope = "col";
    cell.setAttribute("aria-label", "Sort " + label);
    sortRow.append(cell);

    const wrapper = document.createElement("span");
    wrapper.className = "performance-sort-controls";
    [
      { direction: 1, glyph: "↑", description: "lowest to highest" },
      { direction: -1, glyph: "↓", description: "highest to lowest" }
    ].forEach(({ direction, glyph, description }) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "performance-sort-button";
      button.textContent = glyph;
      button.title = "Sort " + label + " " + description;
      button.setAttribute("aria-label", button.title);
      button.setAttribute("aria-pressed", "false");
      button.addEventListener("click", () => sort(key, direction));
      wrapper.append(button);
      controls.push({ button, key, direction });
    });
    cell.append(wrapper);
  });
})();
