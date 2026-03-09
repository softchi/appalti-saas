/**
 * APPALTI PUBBLICI SAAS - Charts.js
 *
 * Configurazioni e factory per tutti i grafici della piattaforma.
 * Usa Chart.js 4.x
 *
 * @version 1.0.0
 */

'use strict';

// Registro globale istanze Chart.js (per destroy prima di ricreare)
const ChartRegistry = new Map();

const CHART_COLORS = {
  primary:   '#0d6efd',
  success:   '#198754',
  warning:   '#ffc107',
  danger:    '#dc3545',
  info:      '#0dcaf0',
  secondary: '#6c757d',
  purple:    '#6f42c1',
  orange:    '#fd7e14',
  teal:      '#20c997',
  pink:      '#d63384',
};

const PALETTE_6 = [
  CHART_COLORS.primary, CHART_COLORS.success, CHART_COLORS.warning,
  CHART_COLORS.danger,  CHART_COLORS.purple,  CHART_COLORS.info,
];

/* Defaults globali Chart.js */
Chart.defaults.font.family   = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.font.size     = 12;
Chart.defaults.color         = '#6c757d';
Chart.defaults.responsive    = true;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.legend.position = 'bottom';
Chart.defaults.plugins.legend.labels.boxWidth = 12;
Chart.defaults.plugins.legend.labels.padding  = 16;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(33,37,41,0.92)';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;

/* Helper: distruggi e ricrea grafico */
function getOrCreateChart(canvasId, config) {
  if (ChartRegistry.has(canvasId)) {
    ChartRegistry.get(canvasId).destroy();
    ChartRegistry.delete(canvasId);
  }
  const canvas = document.getElementById(canvasId);
  if (!canvas) return null;

  const chart = new Chart(canvas.getContext('2d'), config);
  ChartRegistry.set(canvasId, chart);
  return chart;
}

/* =============================================================================
   GRAFICO: Stato commesse (Doughnut)
============================================================================= */
function chartStatoCommesse(canvasId, data) {
  const labels  = data.map(d => d.stato_label || d.stato);
  const values  = data.map(d => d.n);
  const colors  = data.map((d, i) => PALETTE_6[i % PALETTE_6.length]);

  return getOrCreateChart(canvasId, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data:            values,
        backgroundColor: colors,
        borderWidth:     2,
        borderColor:     '#fff',
        hoverOffset:     6,
      }],
    },
    options: {
      cutout: '68%',
      plugins: {
        legend: { position: 'right' },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.raw} commess${ctx.raw === 1 ? 'a' : 'e'}`,
          },
        },
      },
    },
  });
}

/* =============================================================================
   GRAFICO: Valore per stato (Bar orizzontale)
============================================================================= */
function chartValoreStato(canvasId, data) {
  const labels = data.map(d => d.stato);
  const values = data.map(d => parseFloat(d.valore) || 0);
  const colors = data.map((d, i) => PALETTE_6[i % PALETTE_6.length]);

  return getOrCreateChart(canvasId, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label:           'Valore (€)',
        data:            values,
        backgroundColor: colors,
        borderRadius:    6,
        borderSkipped:   false,
      }],
    },
    options: {
      indexAxis: 'y',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' € ' + ctx.raw.toLocaleString('it-IT', { maximumFractionDigits: 0 }),
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            callback: v => '€ ' + (v / 1000).toFixed(0) + 'k',
          },
          grid: { color: 'rgba(0,0,0,0.05)' },
        },
        y: { grid: { display: false } },
      },
    },
  });
}

/* =============================================================================
   GRAFICO: Avanzamento mensile (Line chart)
============================================================================= */
function chartAvanzamentoMensile(canvasId, data) {
  const labels     = data.map(d => d.mese_label);
  const completate = data.map(d => parseInt(d.completate) || 0);
  const inCorso    = data.map(d => parseInt(d.in_corso) || 0);

  return getOrCreateChart(canvasId, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label:           'Completate',
          data:            completate,
          borderColor:     CHART_COLORS.success,
          backgroundColor: 'rgba(25,135,84,0.1)',
          fill:            true,
          tension:         0.4,
          pointRadius:     5,
          pointHoverRadius: 7,
        },
        {
          label:           'In corso',
          data:            inCorso,
          borderColor:     CHART_COLORS.primary,
          backgroundColor: 'rgba(13,110,253,0.1)',
          fill:            true,
          tension:         0.4,
          pointRadius:     5,
          pointHoverRadius: 7,
        },
      ],
    },
    options: {
      plugins: { legend: { position: 'top' } },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1 },
          grid: { color: 'rgba(0,0,0,0.05)' },
        },
        x: { grid: { display: false } },
      },
    },
  });
}

/* =============================================================================
   GRAFICO: Avanzamento commessa (Gauge-like progress)
============================================================================= */
function chartAvanzamentoCommessa(canvasId, perc) {
  const value   = Math.min(100, Math.max(0, parseFloat(perc) || 0));
  const color   = value >= 80 ? CHART_COLORS.success
                : value >= 50 ? CHART_COLORS.primary
                : value >= 25 ? CHART_COLORS.warning
                : CHART_COLORS.danger;

  return getOrCreateChart(canvasId, {
    type: 'doughnut',
    data: {
      datasets: [{
        data:            [value, 100 - value],
        backgroundColor: [color, '#e9ecef'],
        borderWidth:     0,
        hoverOffset:     0,
      }],
    },
    options: {
      cutout:      '78%',
      rotation:    -90,
      circumference: 180,
      plugins: {
        legend:  { display: false },
        tooltip: { enabled: false },
      },
    },
    plugins: [{
      id: 'centerText',
      afterDraw(chart) {
        const { ctx, chartArea: { left, right, top, bottom } } = chart;
        const cx = (left + right) / 2;
        const cy = bottom - 10;
        ctx.save();
        ctx.font = 'bold 1.4rem Segoe UI, sans-serif';
        ctx.fillStyle = color;
        ctx.textAlign = 'center';
        ctx.fillText(value.toFixed(1) + '%', cx, cy);
        ctx.restore();
      },
    }],
  });
}

/* =============================================================================
   GRAFICO: SAL - Andamento liquidazione (Bar + Line combo)
============================================================================= */
function chartSalAndamento(canvasId, salData) {
  const labels   = salData.map(s => `SAL ${s.numero_sal}`);
  const periodi  = salData.map(s => parseFloat(s.importo_totale)  || 0);
  const cumulati = salData.map(s => parseFloat(s.importo_cumulato) || 0);

  return getOrCreateChart(canvasId, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          type:            'bar',
          label:           'Importo periodo',
          data:            periodi,
          backgroundColor: 'rgba(13,110,253,0.7)',
          borderRadius:    6,
          order:           2,
        },
        {
          type:        'line',
          label:       'Importo cumulato',
          data:        cumulati,
          borderColor: CHART_COLORS.success,
          backgroundColor: 'rgba(25,135,84,0.1)',
          fill:        true,
          tension:     0.3,
          yAxisID:     'y2',
          order:       1,
          pointRadius: 5,
        },
      ],
    },
    options: {
      plugins: { legend: { position: 'top' } },
      scales: {
        y: {
          beginAtZero: true,
          position:    'left',
          ticks: { callback: v => '€ ' + (v/1000).toFixed(0) + 'k' },
          grid: { color: 'rgba(0,0,0,0.05)' },
        },
        y2: {
          beginAtZero: true,
          position:    'right',
          ticks: { callback: v => '€ ' + (v/1000).toFixed(0) + 'k' },
          grid: { drawOnChartArea: false },
        },
        x: { grid: { display: false } },
      },
      interaction: { mode: 'index', intersect: false },
      tooltip: {
        callbacks: {
          label: ctx => ` ${ctx.dataset.label}: € ${(ctx.raw/1000).toFixed(1)}k`,
        },
      },
    },
  });
}

/* =============================================================================
   GRAFICO: Costi per categoria (Bar orizzontale)
============================================================================= */
function chartCostiCategorie(canvasId, categorie) {
  const labels       = categorie.map(c => c.codice + ' - ' + c.descrizione.substring(0, 30));
  const contrattuale = categorie.map(c => parseFloat(c.importo_contrattuale) || 0);
  const eseguito     = categorie.map(c => parseFloat(c.importo_eseguito)     || 0);

  return getOrCreateChart(canvasId, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label:           'Contrattuale',
          data:            contrattuale,
          backgroundColor: 'rgba(13,110,253,0.5)',
          borderRadius:    4,
        },
        {
          label:           'Eseguito',
          data:            eseguito,
          backgroundColor: 'rgba(25,135,84,0.8)',
          borderRadius:    4,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: € ${ctx.raw.toLocaleString('it-IT', { maximumFractionDigits: 0 })}`,
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: { callback: v => '€ ' + (v/1000).toFixed(0) + 'k' },
          grid: { color: 'rgba(0,0,0,0.05)' },
        },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
      },
    },
  });
}

/* =============================================================================
   GRAFICO: Distribuzione tasks per stato (Pie)
============================================================================= */
function chartTasksStato(canvasId, stats) {
  const data = [
    { label: 'Completati',   value: stats.completati   || 0, color: CHART_COLORS.success   },
    { label: 'In corso',     value: stats.in_corso     || 0, color: CHART_COLORS.primary   },
    { label: 'In ritardo',   value: stats.in_ritardo   || 0, color: CHART_COLORS.danger    },
    { label: 'Non iniziati', value: stats.non_iniziati || 0, color: CHART_COLORS.secondary },
  ].filter(d => d.value > 0);

  return getOrCreateChart(canvasId, {
    type: 'doughnut',
    data: {
      labels:   data.map(d => d.label),
      datasets: [{
        data:            data.map(d => d.value),
        backgroundColor: data.map(d => d.color),
        borderWidth:     2,
        borderColor:     '#fff',
        hoverOffset:     6,
      }],
    },
    options: {
      cutout: '60%',
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.raw} task${ctx.raw !== 1 ? 's' : ''}`,
          },
        },
      },
    },
  });
}

/* =============================================================================
   GRAFICO: KPI SPI/CPI (Radar)
============================================================================= */
function chartKpiRadar(canvasId, metriche) {
  return getOrCreateChart(canvasId, {
    type: 'radar',
    data: {
      labels: ['Avanzamento', 'SPI', 'CPI', 'Qualità Doc.', 'Rispetto Scadenze'],
      datasets: [{
        label:           'Commessa',
        data: [
          Math.min(100, parseFloat(metriche.percentuale_avanzamento) || 0),
          Math.min(100, (parseFloat(metriche.spi) || 1) * 100),
          Math.min(100, (parseFloat(metriche.cpi) || 1) * 100),
          75, // placeholder
          Math.max(0, 100 - (parseInt(metriche.scadenze_critiche) || 0) * 10),
        ],
        fill:            true,
        backgroundColor: 'rgba(13,110,253,0.15)',
        borderColor:     CHART_COLORS.primary,
        pointBackgroundColor: CHART_COLORS.primary,
        pointRadius:     5,
      }],
    },
    options: {
      scales: {
        r: {
          beginAtZero: true,
          max:         100,
          ticks: { display: false },
          grid: { color: 'rgba(0,0,0,0.08)' },
          pointLabels: { font: { size: 11 } },
        },
      },
      plugins: { legend: { display: false } },
    },
  });
}
