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
    const header = document.createElement("div");
    header.className = "bottom-report-header";
    const eyebrow = document.createElement("div");
    eyebrow.className = "bottom-report-eyebrow";
    eyebrow.textContent = "DULOGIX  /  DRIVER PERFORMANCE";
    const heading = document.createElement("h2");
    heading.textContent = "Bottom 10 · " + label;
    const note = document.createElement("p");
    note.textContent = key === "name" ? "Last 10 driver names alphabetically." : (key === "psb" || key === "qty") ? "Highest quantities indicate poorer performance." : "Lowest metric values indicate poorer performance.";
    header.append(eyebrow, heading, note);
    const tableReport = document.createElement("table");
    tableReport.className = "bottom-report-table";
    const head = document.createElement("thead");
    const headRow = document.createElement("tr");
    ["#", "Driver", key === "name" ? "Name" : "Result"].forEach(text => {
      const th = document.createElement("th");
      th.textContent = text;
      headRow.append(th);
    });
    head.append(headRow);
    const body = document.createElement("tbody");
    const shareLines = ["DULOGIX · Bottom 10 · " + label, note.textContent, ""];
    const formatMetric = metric => key === "qty" || key === "psb"
      ? String(Math.round(metric))
      : String(Number(metric.toFixed(2)));
    ranked.forEach(({ row, metric }, index) => {
      const name = row.cells[0]?.querySelector("strong, b")?.textContent?.trim() || row.cells[0]?.textContent?.trim() || "Driver";
      const tr = document.createElement("tr");
      [String(index + 1).padStart(2, "0"), name, formatMetric(metric)].forEach((text, cellIndex) => {
        const td = document.createElement("td");
        td.textContent = text;
        if (cellIndex === 2) td.className = "bottom-report-value";
        tr.append(td);
      });
      body.append(tr);
      shareLines.push((index + 1) + ". " + name + " — " + formatMetric(metric));
    });
    if (!ranked.length) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = 3;
      td.textContent = "No values available for this metric.";
      tr.append(td);
      body.append(tr);
    }
    tableReport.append(head, body);
    const footer = document.createElement("div");
    footer.className = "bottom-report-footer";
    footer.textContent = "Dulogix · Weekly driver performance";
    const actions = document.createElement("div");
    actions.className = "bottom-report-actions";
    function action(label, className, handler) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = className;
      button.textContent = label;
      button.addEventListener("click", handler);
      actions.append(button);
    }
    action("Print / Save PDF", "bottom-action-secondary", () => window.print());
    action("Copy list", "bottom-action-secondary", async () => {
      try {
        await navigator.clipboard.writeText(shareLines.join("\\n"));
        const button = actions.querySelectorAll("button")[1];
        button.textContent = "Copied!";
        setTimeout(() => { button.textContent = "Copy list"; }, 1800);
      } catch (error) {
        window.prompt("Copy the list:", shareLines.join("\\n"));
      }
    });
    action("Share on WhatsApp", "bottom-action-share", () => {
      window.open("https://wa.me/?text=" + encodeURIComponent(shareLines.join("\\n")), "_blank", "noopener,noreferrer");
    });
    action("Close", "bottom-action-close", () => bottomDialog.close());
    bottomDialog.append(header, tableReport, footer, actions);
    bottomDialog.showModal();
  }
  columns.forEach(({ key, label }, index) => {
    const cell = document.createElement("th");
    cell.scope = "col";
    if (index > 0 && index < 9) {
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
  const sortRow = table.tHead.insertRow(1);
  sortRow.className = "performance-sort-row";

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
