(function () {
  'use strict';
  let charts = [];
  function clearCharts() { charts.forEach(c => { try { c.destroy(); } catch (_) {} }); charts = []; }
  function init() {
    const root = document.getElementById('im-v2-dashboard');
    if (!root) { clearCharts(); return; }
    const theme = localStorage.getItem('imnehnet-dashboard-theme') || 'dark';
    root.dataset.imTheme = theme;
    if (root.dataset.imV3Initialized !== '1') {
      root.dataset.imV3Initialized = '1';
      root.querySelector('#im-v2-theme')?.addEventListener('click', () => {
        root.dataset.imTheme = root.dataset.imTheme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('imnehnet-dashboard-theme', root.dataset.imTheme);
        renderCharts();
      });
      root.querySelector('#im-v2-menu')?.addEventListener('click', () => root.classList.toggle('im-v2-menu-open'));
      root.querySelectorAll('.im-v2-nav a').forEach(a => a.addEventListener('click', () => root.classList.remove('im-v2-menu-open')));
    }
    renderCharts();
  }
  function renderCharts(attempt = 0) {
    const root = document.getElementById('im-v2-dashboard');
    const dataElement = document.getElementById('im-v3-chart-data');
    if (!root || !dataElement) return;
    if (!window.ApexCharts) {
      if (attempt < 20) return setTimeout(() => renderCharts(attempt + 1), 250);
      console.error('[IMNEHNET Dashboard] ApexCharts not loaded by resources/js/app.js');
      return;
    }
    let data;
    try { data = JSON.parse(dataElement.textContent); } catch (e) { console.error('Invalid chart data', e); return; }
    clearCharts();
    const dark = root.dataset.imTheme !== 'light';
    const ink = dark ? '#c5d6f1' : '#4b6494';
    const grid = dark ? '#293b59' : '#d5e1f4';
    const common = { chart: { height: 255, toolbar: { show: false }, background: 'transparent', animations: { enabled: true, speed: 300 }, fontFamily: 'inherit' }, theme: { mode: dark ? 'dark' : 'light' }, grid: { borderColor: grid, strokeDashArray: 3 }, dataLabels: { enabled: false }, tooltip: { theme: dark ? 'dark' : 'light' }, xaxis: { categories: data.days, labels: { style: { colors: ink } }, axisBorder: { show: false }, axisTicks: { show: false } }, yaxis: { labels: { style: { colors: ink } }, min: 0 }, legend: { show: false } };
    const specs = [
      ['#im-v3-activity', { ...common, chart: { ...common.chart, type: 'area' }, series: [{ name: 'Sessions', data: data.activity }], colors: ['#239bff'], stroke: { curve: 'smooth', width: 3 }, fill: { type: 'gradient', gradient: { shadeIntensity: .2, opacityFrom: .23, opacityTo: 0 } }, markers: { size: 3 } }],
      ['#im-v3-completion', { chart: { type: 'donut', height: 255, background: 'transparent', fontFamily: 'inherit' }, series: [Math.max(0, Math.min(100, data.percent)), 100 - Math.max(0, Math.min(100, data.percent))], labels: ['Completed', 'Remaining'], colors: ['#00b7ed', dark ? '#243b5c' : '#d0eaff'], stroke: { width: 0 }, dataLabels: { enabled: false }, legend: { show: false }, tooltip: { enabled: false }, plotOptions: { pie: { donut: { size: '74%', labels: { show: true, name: { show: false }, value: { show: false }, total: { show: true, showAlways: true, label: '', formatter: () => `${data.percent}%`, fontSize: '30px', fontWeight: 800, color: dark ? '#e9efff' : '#102452' } } } } } }],
      ['#im-v3-usage', { ...common, chart: { ...common.chart, type: 'bar' }, series: [{ name: 'Hours', data: data.usage }], colors: ['#219bff'], plotOptions: { bar: { borderRadius: 2, columnWidth: '60%' } } }]
    ];
    for (const [selector, opts] of specs) {
      const el = root.querySelector(selector);
      if (!el) continue;
      try { const chart = new window.ApexCharts(el, opts); chart.render(); charts.push(chart); }
      catch (error) { console.error('[IMNEHNET Dashboard] Chart failed', selector, error); }
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
  document.addEventListener('livewire:navigated', init);
})();
