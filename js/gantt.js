/**
 * APPALTI PUBBLICI SAAS - Gantt Chart Engine
 *
 * Diagramma di Gantt interattivo custom.
 * Implementazione pura Canvas + HTML senza librerie esterne.
 *
 * Funzionalità:
 * - Zoom: giornaliero / settimanale / mensile
 * - Barre con avanzamento
 * - Milestone (rombo)
 * - Dipendenze (frecce SVG)
 * - Linea TODAY
 * - Tooltip al passaggio mouse
 * - Drag resize barre (modifica data fine)
 * - Click per aprire dettaglio task
 * - Sincronizzazione scroll albero ↔ canvas
 *
 * @version 1.0.0
 */

'use strict';

class GanttChart {

  constructor(containerId, options = {}) {
    this.containerId = containerId;
    this.container   = document.getElementById(containerId);
    if (!this.container) throw new Error(`Gantt container #${containerId} non trovato`);

    // Opzioni
    this.opts = Object.assign({
      rowHeight:      40,
      headerHeight:   48,
      colWidthDay:    30,    // larghezza colonna giorno (zoom daily)
      colWidthWeek:   60,    // larghezza colonna settimana
      colWidthMonth:  80,    // larghezza colonna mese
      zoom:           'month', // day | week | month
      showWeekends:   true,
      showDependencies: true,
      editable:       true,
      onTaskClick:    null,  // callback(task)
      onTaskUpdate:   null,  // callback(taskId, { data_fine_prevista })
    }, options);

    this.tasks      = [];
    this.flatTasks  = []; // tasks appiattiti (no gerarchia) per rendering
    this.collapsed  = new Set();
    this.startDate  = null;
    this.endDate    = null;
    this.tooltip    = null;
    this._dragging  = null;

    this._buildDOM();
    this._bindEvents();
  }

  /* =====================================================================
     DOM SETUP
  ===================================================================== */
  _buildDOM() {
    this.container.innerHTML = `
      <div class="gantt-wrapper">

        <!-- Toolbar -->
        <div class="gantt-toolbar">
          <span class="toolbar-label">Vista:</span>
          <button class="gantt-zoom-btn ${this.opts.zoom === 'day'   ? 'active' : ''}" data-zoom="day">Giornaliero</button>
          <button class="gantt-zoom-btn ${this.opts.zoom === 'week'  ? 'active' : ''}" data-zoom="week">Settimanale</button>
          <button class="gantt-zoom-btn ${this.opts.zoom === 'month' ? 'active' : ''}" data-zoom="month">Mensile</button>
          <div class="ms-3 d-flex align-items-center gap-2">
            <span class="toolbar-label">Periodo:</span>
            <button class="btn btn-sm btn-outline-secondary" id="ganttScrollToday">
              <i class="bi bi-calendar-check"></i> Oggi
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="ganttScrollStart">
              <i class="bi bi-skip-backward-fill"></i> Inizio
            </button>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <small class="text-muted" id="ganttInfo"></small>
            <button class="btn btn-sm btn-primary" id="ganttAddTask" style="display:none;">
              <i class="bi bi-plus-lg"></i> Nuova attività
            </button>
          </div>
        </div>

        <!-- Layout principale -->
        <div class="gantt-container">

          <!-- Albero sinistra -->
          <div class="gantt-tree" id="ganttTree">
            <div class="gantt-tree-header">
              <i class="bi bi-list-ul me-2"></i>
              <span>ATTIVITÀ</span>
              <div class="ms-auto d-flex gap-1">
                <button class="btn btn-xs btn-link p-0 text-muted" id="ganttExpandAll" title="Espandi tutto">
                  <i class="bi bi-arrows-expand"></i>
                </button>
                <button class="btn btn-xs btn-link p-0 text-muted" id="ganttCollapseAll" title="Comprimi tutto">
                  <i class="bi bi-arrows-collapse"></i>
                </button>
              </div>
            </div>
            <div class="gantt-tree-body" id="ganttTreeBody"></div>
          </div>

          <!-- Area grafico destra -->
          <div class="gantt-chart-area" id="ganttChartArea">
            <div class="gantt-canvas-container" id="ganttCanvasContainer">
              <canvas id="ganttCanvas"></canvas>
              <svg class="gantt-link-svg" id="ganttLinkSvg"></svg>
              <div class="gantt-today-marker" id="ganttTodayMarker"></div>
            </div>
          </div>

        </div>

        <!-- Legenda -->
        <div class="gantt-legend">
          <div class="legend-item"><div class="legend-bar done"></div><span>Completato</span></div>
          <div class="legend-item"><div class="legend-bar in-corso"></div><span>In corso</span></div>
          <div class="legend-item"><div class="legend-bar late"></div><span>In ritardo</span></div>
          <div class="legend-item"><div class="legend-bar future"></div><span>Futuro</span></div>
          <div class="legend-item"><div class="legend-bar milestone"></div><span>Milestone</span></div>
        </div>

      </div>

      <!-- Tooltip -->
      <div class="gantt-tooltip" id="ganttTooltip"></div>
    `;

    this.treeBody      = document.getElementById('ganttTreeBody');
    this.canvas        = document.getElementById('ganttCanvas');
    this.ctx           = this.canvas.getContext('2d');
    this.linkSvg       = document.getElementById('ganttLinkSvg');
    this.todayMarker   = document.getElementById('ganttTodayMarker');
    this.chartArea     = document.getElementById('ganttChartArea');
    this.canvasWrapper = document.getElementById('ganttCanvasContainer');
    this.tooltip       = document.getElementById('ganttTooltip');
    this.infoEl        = document.getElementById('ganttInfo');
  }

  /* =====================================================================
     CARICAMENTO DATI
  ===================================================================== */
  load(tasksTree, options = {}) {
    this.tasks     = tasksTree;
    this.flatTasks = [];
    this._flattenTasks(tasksTree, 0);
    this._computeDateRange();
    this.render();
  }

  _flattenTasks(nodes, depth) {
    nodes.forEach(task => {
      this.flatTasks.push({ ...task, _depth: depth });
      if (task.subtasks?.length && !this.collapsed.has(task.id)) {
        this._flattenTasks(task.subtasks, depth + 1);
      }
    });
  }

  _computeDateRange() {
    let minDate = null, maxDate = null;

    this.flatTasks.forEach(t => {
      const s = t.data_inizio_prevista ? new Date(t.data_inizio_prevista) : null;
      const e = t.data_fine_prevista   ? new Date(t.data_fine_prevista)   : null;
      if (s && (!minDate || s < minDate)) minDate = s;
      if (e && (!maxDate || e > maxDate)) maxDate = e;
    });

    if (!minDate) minDate = new Date();
    if (!maxDate) maxDate = new Date(minDate.getTime() + 90 * 86400000);

    // Padding
    this.startDate = new Date(minDate.getTime() - 7 * 86400000);
    this.endDate   = new Date(maxDate.getTime() + 14 * 86400000);

    // Arrotonda a inizio settimana/mese
    this.startDate.setDate(1);

    this._totalDays = Math.ceil((this.endDate - this.startDate) / 86400000);
  }

  /* =====================================================================
     RENDERING
  ===================================================================== */
  render() {
    this._renderTree();
    this._setupCanvas();
    this._renderCanvas();
    this._renderDependencies();
    this._positionTodayMarker();
    this._updateInfo();
  }

  _renderTree() {
    if (!this.treeBody) return;
    this.treeBody.innerHTML = '';

    this.flatTasks.forEach((task, idx) => {
      const hasChildren = task.subtasks?.length > 0;
      const isCollapsed = this.collapsed.has(task.id);
      const indent      = task._depth * 16;
      const typeIcon    = this._taskTypeIcon(task.tipo);

      const row = document.createElement('div');
      row.className    = `gantt-tree-row ${task.tipo === 'MILESTONE' ? 'milestone' : ''} ${task.tipo === 'FASE' ? 'fase' : ''}`;
      row.dataset.idx  = idx;
      row.dataset.id   = task.id;
      row.style.height = this.opts.rowHeight + 'px';

      row.innerHTML = `
        <div style="width:${indent}px; flex-shrink:0;"></div>
        <button class="expand-btn ${hasChildren ? '' : 'invisible'} ${isCollapsed ? '' : 'expanded'}"
                data-id="${task.id}">
          <i class="bi bi-chevron-right"></i>
        </button>
        <i class="bi ${typeIcon} task-icon task-icon-${(task.tipo || '').toLowerCase()}"></i>
        <span class="task-wbs">${escapeHtml(task.codice_wbs || '')}</span>
        <span class="task-name ${task.tipo === 'FASE' ? 'fase' : ''}" title="${escapeHtml(task.nome)}">
          ${escapeHtml(task.nome)}
        </span>
        <span class="task-perc">${parseFloat(task.percentuale_completamento || 0).toFixed(0)}%</span>`;

      // Expand/collapse
      row.querySelector('.expand-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (this.collapsed.has(task.id)) {
          this.collapsed.delete(task.id);
        } else {
          this.collapsed.add(task.id);
        }
        this.flatTasks = [];
        this._flattenTasks(this.tasks, 0);
        this.render();
      });

      // Click riga → seleziona / apri dettaglio
      row.addEventListener('click', () => {
        this.treeBody.querySelectorAll('.gantt-tree-row').forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        this.opts.onTaskClick?.(task);
      });

      this.treeBody.appendChild(row);
    });
  }

  _taskTypeIcon(tipo) {
    return tipo === 'MILESTONE' ? 'bi-diamond-fill'
         : tipo === 'FASE'      ? 'bi-folder-fill'
         : tipo === 'SOMMARIO'  ? 'bi-collection-fill'
         : 'bi-check2-square';
  }

  _getColWidth() {
    return this.opts.zoom === 'day'   ? this.opts.colWidthDay
         : this.opts.zoom === 'week'  ? this.opts.colWidthWeek
         : this.opts.colWidthMonth;
  }

  _dateToX(date) {
    const d    = new Date(date);
    const days = (d - this.startDate) / 86400000;

    if (this.opts.zoom === 'month') {
      // Posizione proporzionale nel mese
      const daysTotal = (this.endDate - this.startDate) / 86400000;
      return (days / daysTotal) * this._totalWidth;
    }
    const cw = this._getColWidth();
    return days * cw;
  }

  _setupCanvas() {
    const cw = this._getColWidth();
    let totalW;

    if (this.opts.zoom === 'month') {
      // Raggruppa per mese
      this._months = this._getMonths();
      totalW = this._months.reduce((acc, m) => acc + m.width, 0);
    } else {
      totalW = this._totalDays * cw;
    }

    this._totalWidth   = Math.max(totalW, this.chartArea.clientWidth);
    const totalH       = this.flatTasks.length * this.opts.rowHeight + this.opts.headerHeight;

    this.canvas.width  = this._totalWidth;
    this.canvas.height = totalH;
    this.canvas.style.width  = this._totalWidth + 'px';
    this.canvas.style.height = totalH + 'px';
    this.linkSvg.setAttribute('width',  this._totalWidth);
    this.linkSvg.setAttribute('height', totalH);
    this.linkSvg.style.width  = this._totalWidth + 'px';
    this.linkSvg.style.height = totalH + 'px';
    this.canvasWrapper.style.height = (this.flatTasks.length * this.opts.rowHeight + this.opts.headerHeight) + 'px';
  }

  _renderCanvas() {
    const ctx = this.ctx;
    const cw  = this._totalWidth;
    const ch  = this.canvas.height;
    const rh  = this.opts.rowHeight;
    const hh  = this.opts.headerHeight;

    ctx.clearRect(0, 0, cw, ch);

    // Sfondo
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, cw, ch);

    // Header scale
    this._drawHeader(ctx, cw, hh);

    // Righe alternare
    this.flatTasks.forEach((task, i) => {
      const y = hh + i * rh;
      if (i % 2 === 0) {
        ctx.fillStyle = 'rgba(0,0,0,0.012)';
        ctx.fillRect(0, y, cw, rh);
      }
      // Linea separatrice
      ctx.strokeStyle = '#e9ecef';
      ctx.lineWidth   = 0.5;
      ctx.beginPath();
      ctx.moveTo(0, y + rh - 0.5);
      ctx.lineTo(cw, y + rh - 0.5);
      ctx.stroke();
    });

    // Weekend evidenziati
    if (this.opts.showWeekends && this.opts.zoom === 'day') {
      this._drawWeekends(ctx, hh, ch);
    }

    // Barre task
    this.flatTasks.forEach((task, i) => {
      const y = hh + i * rh;
      this._drawTaskBar(ctx, task, y, rh);
    });
  }

  _drawHeader(ctx, cw, hh) {
    // Sfondo header
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, cw, hh);

    // Bordo inferiore header
    ctx.strokeStyle = '#dee2e6';
    ctx.lineWidth   = 1;
    ctx.beginPath();
    ctx.moveTo(0, hh);
    ctx.lineTo(cw, hh);
    ctx.stroke();

    if (this.opts.zoom === 'month') {
      this._drawHeaderMonths(ctx, hh);
    } else if (this.opts.zoom === 'week') {
      this._drawHeaderWeeks(ctx, hh);
    } else {
      this._drawHeaderDays(ctx, hh);
    }
  }

  _drawHeaderMonths(ctx, hh) {
    let x = 0;
    const today = new Date();

    this._months.forEach(m => {
      const isToday = today >= m.start && today <= m.end;

      // Sfondo mese
      ctx.fillStyle = isToday ? '#fff9c4' : '#f8f9fa';
      ctx.fillRect(x, 0, m.width, hh);

      // Bordo
      ctx.strokeStyle = '#dee2e6';
      ctx.lineWidth   = 1;
      ctx.beginPath();
      ctx.moveTo(x + m.width, 0);
      ctx.lineTo(x + m.width, hh);
      ctx.stroke();

      // Label
      ctx.fillStyle  = isToday ? '#856404' : '#0d47a1';
      ctx.font       = `600 12px 'Segoe UI', sans-serif`;
      ctx.textAlign  = 'center';
      ctx.textBaseline = 'middle';
      if (m.width > 30) {
        ctx.fillText(m.label, x + m.width / 2, hh / 2);
      }
      x += m.width;
    });
  }

  _drawHeaderWeeks(ctx, hh) {
    const cw  = this.opts.colWidthWeek;
    const cur = new Date(this.startDate);
    let   x   = 0;

    while (cur < this.endDate) {
      const weekStart = new Date(cur);
      const label     = `Sett. ${this._getWeekNumber(cur)} - ${cur.getFullYear()}`;
      const isCurrentWeek = this._isSameWeek(cur, new Date());

      ctx.fillStyle = isCurrentWeek ? '#fff9c4' : (x % (2 * cw) < cw ? '#f8f9fa' : '#ffffff');
      ctx.fillRect(x, 0, cw, hh);

      ctx.strokeStyle = '#dee2e6';
      ctx.lineWidth   = 1;
      ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, hh); ctx.stroke();

      ctx.fillStyle    = isCurrentWeek ? '#856404' : '#495057';
      ctx.font         = '11px Segoe UI, sans-serif';
      ctx.textAlign    = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(label, x + cw / 2, hh / 2);

      x += cw;
      cur.setDate(cur.getDate() + 7);
    }
  }

  _drawHeaderDays(ctx, hh) {
    const cw  = this.opts.colWidthDay;
    const cur = new Date(this.startDate);
    let   x   = 0;
    const today = new Date();
    today.setHours(0,0,0,0);

    while (cur < this.endDate) {
      const isToday   = cur.toDateString() === today.toDateString();
      const isWeekend = cur.getDay() === 0 || cur.getDay() === 6;
      const isMonthStart = cur.getDate() === 1;

      ctx.fillStyle = isToday ? '#fff9c4' : isWeekend ? '#f5f5f5' : '#ffffff';
      ctx.fillRect(x, 0, cw, hh);

      // Bordo
      ctx.strokeStyle = isMonthStart ? '#6c757d' : '#dee2e6';
      ctx.lineWidth   = isMonthStart ? 1.5 : 0.5;
      ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, hh); ctx.stroke();

      if (cw >= 24) {
        ctx.fillStyle    = isToday ? '#856404' : isWeekend ? '#adb5bd' : '#495057';
        ctx.font         = `${isToday ? '700' : '500'} 10px Segoe UI`;
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(cur.getDate(), x + cw / 2, hh * 0.65);
        if (isMonthStart) {
          ctx.font = '600 9px Segoe UI';
          ctx.fillStyle = '#0d47a1';
          ctx.fillText(cur.toLocaleDateString('it-IT', { month: 'short' }), x + cw / 2, hh * 0.25);
        }
      }

      x += cw;
      cur.setDate(cur.getDate() + 1);
    }
  }

  _drawWeekends(ctx, hh, totalH) {
    const cw  = this.opts.colWidthDay;
    const cur = new Date(this.startDate);
    let   x   = 0;

    while (cur < this.endDate) {
      if (cur.getDay() === 0 || cur.getDay() === 6) {
        ctx.fillStyle = 'rgba(0,0,0,0.03)';
        ctx.fillRect(x, hh, cw, totalH - hh);
      }
      x += cw;
      cur.setDate(cur.getDate() + 1);
    }
  }

  _drawTaskBar(ctx, task, y, rh) {
    if (!task.data_inizio_prevista || !task.data_fine_prevista) return;

    const x1  = this._dateToX(task.data_inizio_prevista);
    const x2  = this._dateToX(task.data_fine_prevista);
    const barW = Math.max(x2 - x1, 4);
    const barH = rh * 0.55;
    const barY = y + (rh - barH) / 2;
    const perc = Math.min(100, parseFloat(task.percentuale_completamento) || 0) / 100;

    if (task.tipo === 'MILESTONE') {
      // Rombo
      const cx = x1;
      const cy = y + rh / 2;
      const s  = rh * 0.3;
      ctx.fillStyle = '#6f42c1';
      ctx.beginPath();
      ctx.moveTo(cx, cy - s);
      ctx.lineTo(cx + s, cy);
      ctx.lineTo(cx, cy + s);
      ctx.lineTo(cx - s, cy);
      ctx.closePath();
      ctx.fill();
      // Label
      ctx.fillStyle  = '#212529';
      ctx.font       = '10px Segoe UI, sans-serif';
      ctx.textAlign  = 'left';
      ctx.textBaseline = 'middle';
      ctx.fillText(task.nome.substring(0, 20), cx + s + 5, y + rh / 2);
      return;
    }

    if (task.tipo === 'FASE' || task.tipo === 'SOMMARIO') {
      // Barra sommario (a forma di cappello)
      ctx.fillStyle = 'rgba(13, 71, 161, 0.8)';
      ctx.fillRect(x1, barY, barW, barH * 0.5);
      // Completamento
      if (perc > 0) {
        ctx.fillStyle = 'rgba(25,135,84,0.7)';
        ctx.fillRect(x1, barY, barW * perc, barH * 0.5);
      }
      return;
    }

    // Task normale: colore per stato
    const today = new Date();
    const fine  = new Date(task.data_fine_prevista);
    let barColor;
    if (task.stato === 'COMPLETATO') {
      barColor = '#198754';
    } else if (task.stato === 'IN_RITARDO' || (fine < today && perc < 1)) {
      barColor = '#dc3545';
    } else if (task.stato === 'IN_CORSO') {
      barColor = '#0d6efd';
    } else {
      barColor = '#6ea8fe';
    }

    // Sfondo barra (colore attenuato)
    ctx.fillStyle = barColor + '33';
    this._roundRect(ctx, x1, barY, barW, barH, 4);
    ctx.fill();

    // Avanzamento
    if (perc > 0) {
      ctx.fillStyle = barColor;
      this._roundRect(ctx, x1, barY, barW * perc, barH, 4);
      ctx.fill();
    }

    // Testo nome task (se c'è abbastanza spazio)
    if (barW > 40) {
      ctx.fillStyle    = barW * perc > 30 ? '#ffffff' : '#212529';
      ctx.font         = '11px Segoe UI, sans-serif';
      ctx.textAlign    = 'left';
      ctx.textBaseline = 'middle';
      ctx.save();
      ctx.rect(x1 + 4, barY, barW - 8, barH);
      ctx.clip();
      ctx.fillText(task.nome, x1 + 4, barY + barH / 2);
      ctx.restore();
    }

    // Data fine (a destra della barra)
    if (barW < 60) {
      ctx.fillStyle    = '#6c757d';
      ctx.font         = '10px Segoe UI, sans-serif';
      ctx.textAlign    = 'left';
      ctx.textBaseline = 'middle';
      const dateStr = new Date(task.data_fine_prevista).toLocaleDateString('it-IT', { day: '2-digit', month: 'short' });
      ctx.fillText(dateStr, x2 + 4, y + rh / 2);
    }
  }

  _roundRect(ctx, x, y, w, h, r) {
    if (w < 2 * r) r = w / 2;
    if (h < 2 * r) r = h / 2;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  _renderDependencies() {
    if (!this.opts.showDependencies) return;
    this.linkSvg.innerHTML = '';

    const hh  = this.opts.headerHeight;
    const rh  = this.opts.rowHeight;
    const idxMap = {};
    this.flatTasks.forEach((t, i) => { idxMap[t.id] = i; });

    this.flatTasks.forEach(task => {
      if (!task.dipendenze?.length) return;
      task.dipendenze.forEach(dep => {
        const predIdx = idxMap[dep.task_pred_id];
        const succIdx = idxMap[task.id];
        if (predIdx === undefined || succIdx === undefined) return;

        const pred = this.flatTasks[predIdx];
        const x1   = this._dateToX(pred.data_fine_prevista);
        const y1   = hh + predIdx * rh + rh / 2;
        const x2   = this._dateToX(task.data_inizio_prevista);
        const y2   = hh + succIdx * rh + rh / 2;

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const mx   = (x1 + x2) / 2;
        path.setAttribute('d', `M ${x1} ${y1} C ${mx} ${y1}, ${mx} ${y2}, ${x2} ${y2}`);
        path.setAttribute('stroke', '#fd7e14');
        path.setAttribute('stroke-width', '1.5');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke-dasharray', '4 2');
        path.setAttribute('opacity', '0.7');
        path.setAttribute('marker-end', 'url(#arrowhead)');
        this.linkSvg.appendChild(path);
      });
    });

    // Marker freccia
    const defs   = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
    marker.setAttribute('id', 'arrowhead');
    marker.setAttribute('markerWidth', '8');
    marker.setAttribute('markerHeight', '6');
    marker.setAttribute('refX', '6');
    marker.setAttribute('refY', '3');
    marker.setAttribute('orient', 'auto');
    const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    poly.setAttribute('points', '0 0, 8 3, 0 6');
    poly.setAttribute('fill', '#fd7e14');
    marker.appendChild(poly);
    defs.appendChild(marker);
    this.linkSvg.prepend(defs);
  }

  _positionTodayMarker() {
    const today = new Date();
    const x     = this._dateToX(today);
    if (x >= 0 && x <= this._totalWidth) {
      this.todayMarker.style.display = 'block';
      this.todayMarker.style.left    = x + 'px';
      this.todayMarker.style.top     = this.opts.headerHeight + 'px';
    } else {
      this.todayMarker.style.display = 'none';
    }
  }

  _updateInfo() {
    if (!this.infoEl) return;
    const start = this.startDate.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
    const end   = this.endDate.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
    this.infoEl.textContent = `${this.flatTasks.length} attività · ${start} – ${end}`;
  }

  /* =====================================================================
     EVENTI
  ===================================================================== */
  _bindEvents() {
    // Zoom
    this.container.querySelectorAll('.gantt-zoom-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        this.container.querySelectorAll('.gantt-zoom-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this.opts.zoom = btn.dataset.zoom;
        this._computeDateRange();
        this.render();
      });
    });

    // Scroll to today
    document.getElementById('ganttScrollToday')?.addEventListener('click', () => {
      const today = new Date();
      const x     = this._dateToX(today);
      this.chartArea.scrollLeft = Math.max(0, x - this.chartArea.clientWidth / 2);
    });

    // Scroll to start
    document.getElementById('ganttScrollStart')?.addEventListener('click', () => {
      this.chartArea.scrollLeft = 0;
    });

    // Expand/Collapse all
    document.getElementById('ganttExpandAll')?.addEventListener('click', () => {
      this.collapsed.clear();
      this.flatTasks = [];
      this._flattenTasks(this.tasks, 0);
      this.render();
    });

    document.getElementById('ganttCollapseAll')?.addEventListener('click', () => {
      this.tasks.forEach(t => this.collapsed.add(t.id));
      this.flatTasks = [];
      this._flattenTasks(this.tasks, 0);
      this.render();
    });

    // Sincronizzazione scroll verticale albero ↔ canvas
    this.chartArea.addEventListener('scroll', () => {
      this.treeBody.scrollTop = this.chartArea.scrollTop;
    });
    this.treeBody.addEventListener('scroll', () => {
      this.chartArea.scrollTop = this.treeBody.scrollTop;
    });

    // Tooltip mouse
    this.canvas.addEventListener('mousemove', (e) => this._handleMouseMove(e));
    this.canvas.addEventListener('mouseleave', () => {
      this.tooltip.classList.remove('show');
    });

    // Click canvas (apri task)
    this.canvas.addEventListener('click', (e) => this._handleCanvasClick(e));
  }

  _handleMouseMove(e) {
    const rect    = this.canvas.getBoundingClientRect();
    const mouseX  = e.clientX - rect.left;
    const mouseY  = e.clientY - rect.top;
    const hh      = this.opts.headerHeight;
    const rh      = this.opts.rowHeight;

    if (mouseY < hh) { this.tooltip.classList.remove('show'); return; }

    const idx  = Math.floor((mouseY - hh) / rh);
    const task = this.flatTasks[idx];
    if (!task) { this.tooltip.classList.remove('show'); return; }

    // Verifica se mouse è sulla barra
    if (task.data_inizio_prevista && task.data_fine_prevista) {
      const x1 = this._dateToX(task.data_inizio_prevista);
      const x2 = this._dateToX(task.data_fine_prevista);
      if (mouseX >= x1 - 8 && mouseX <= x2 + 8) {
        this._showTooltip(e, task);
        return;
      }
    }
    this.tooltip.classList.remove('show');
  }

  _showTooltip(e, task) {
    const perc = parseFloat(task.percentuale_completamento || 0).toFixed(1);
    const s    = task.data_inizio_prevista ? new Date(task.data_inizio_prevista).toLocaleDateString('it-IT') : 'N/D';
    const f    = task.data_fine_prevista   ? new Date(task.data_fine_prevista).toLocaleDateString('it-IT')   : 'N/D';

    this.tooltip.innerHTML = `
      <div class="tt-title">${escapeHtml(task.nome)}</div>
      <div class="tt-row"><span class="tt-label">WBS:</span> <span>${escapeHtml(task.codice_wbs || '')}</span></div>
      <div class="tt-row"><span class="tt-label">Stato:</span> <span>${escapeHtml(task.stato || '')}</span></div>
      <div class="tt-row"><span class="tt-label">Inizio:</span> <span>${s}</span></div>
      <div class="tt-row"><span class="tt-label">Fine:</span> <span>${f}</span></div>
      <div class="tt-row"><span class="tt-label">Avanzo:</span> <span>${perc}%</span></div>
      ${task.assegnato_nome ? `<div class="tt-row"><span class="tt-label">Resp.:</span> <span>${escapeHtml(task.assegnato_nome)}</span></div>` : ''}`;

    this.tooltip.style.left = (e.clientX + 12) + 'px';
    this.tooltip.style.top  = (e.clientY - 10) + 'px';
    this.tooltip.classList.add('show');

    // Evita che esca dallo schermo
    const box = this.tooltip.getBoundingClientRect();
    if (box.right > window.innerWidth) {
      this.tooltip.style.left = (e.clientX - box.width - 12) + 'px';
    }
  }

  _handleCanvasClick(e) {
    const rect   = this.canvas.getBoundingClientRect();
    const mouseY = e.clientY - rect.top;
    const hh     = this.opts.headerHeight;
    const rh     = this.opts.rowHeight;

    if (mouseY < hh) return;
    const idx  = Math.floor((mouseY - hh) / rh);
    const task = this.flatTasks[idx];
    if (task && this.opts.onTaskClick) {
      this.opts.onTaskClick(task);
    }
  }

  /* =====================================================================
     HELPER
  ===================================================================== */
  _getMonths() {
    const months  = [];
    const cur     = new Date(this.startDate);
    cur.setDate(1);
    const totalDays = (this.endDate - this.startDate) / 86400000;

    while (cur < this.endDate) {
      const monthStart = new Date(cur);
      const monthEnd   = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
      const end        = monthEnd < this.endDate ? monthEnd : this.endDate;
      const days       = (end - monthStart) / 86400000 + 1;
      const width      = Math.max((days / totalDays) * (this.container.clientWidth - 340) * 1.8, 60);

      months.push({
        label: cur.toLocaleDateString('it-IT', { month: 'short', year: '2-digit' }),
        start: monthStart,
        end:   end,
        width: width,
      });

      cur.setMonth(cur.getMonth() + 1);
    }
    return months;
  }

  _getWeekNumber(d) {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
  }

  _isSameWeek(d1, d2) {
    const diff = (d) => { const t = new Date(d); t.setHours(0,0,0,0); return t; };
    const a = diff(d1), b = diff(d2);
    const weekA = new Date(a.setDate(a.getDate() - a.getDay()));
    const weekB = new Date(b.setDate(b.getDate() - b.getDay()));
    return weekA.toDateString() === weekB.toDateString();
  }

  /* =====================================================================
     API PUBBLICA
  ===================================================================== */
  scrollToToday() {
    const x = this._dateToX(new Date());
    this.chartArea.scrollLeft = Math.max(0, x - this.chartArea.clientWidth / 2);
  }

  setZoom(zoom) {
    this.opts.zoom = zoom;
    this._computeDateRange();
    this.render();
  }

  destroy() {
    this.container.innerHTML = '';
  }
}
