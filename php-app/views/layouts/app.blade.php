<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'Smart Home', ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { --bg:#f4f7fb; --card:#fff; --line:#d9e1ef; --text:#152033; --muted:#5f6b82; --accent:#0f766e; }
    body{ margin:0; font-family:Segoe UI,Arial,sans-serif; color:var(--text); background:linear-gradient(180deg,#edf4ff,#f7fbff); }
    .wrap{ max-width:1200px; margin:0 auto; padding:20px; }
    .nav{ display:flex; gap:10px; margin-bottom:14px; }
    .nav a{ text-decoration:none; }
    .head h1{ margin:0; font-size:1.6rem; }
    .head p{ margin:.3rem 0 0; color:var(--muted); }
    .layout{ display:grid; grid-template-columns:320px 1fr; gap:16px; margin-top:16px; }
    .panel{ background:var(--card); border:1px solid var(--line); border-radius:14px; padding:14px; }
    .panel--narrow{ max-width:760px; }
    .panel--wide{ max-width:920px; }
    .muted{ color:var(--muted); }
    .controllers{ display:flex; flex-direction:column; gap:8px; }
    .controller{ width:100%; text-align:left; border:1px solid var(--line); background:#f9fbff; border-radius:10px; padding:10px; cursor:pointer; }
    .controller.active{ border-color:#7dd3fc; background:#ecfeff; }
    .controller-select-btn{ width:100%; text-align:left; border:none; background:transparent; padding:0; cursor:pointer; }
    .controller-actions{ margin-top:8px; }
    .cards{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; }
    .card{ border:1px solid var(--line); border-radius:12px; padding:10px; }
    .card h4{ margin:0 0 8px; font-size:14px; color:var(--muted); }
    .charts-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; }
    .chart-card{ border:1px solid var(--line); border-radius:12px; padding:10px; background:#fff; }
    .chart-svg{ width:100%; height:160px; display:block; }
    .section-spacer{ margin-top:14px; }
    .section-title{ margin:0; }
    .history-range-controls{ display:flex; gap:6px; }
    .modal-dialog{ width:95%; border:1px solid var(--line); border-radius:12px; padding:16px; }
    .modal-dialog--sm{ max-width:560px; }
    .modal-dialog--md{ max-width:760px; }
    .modal-form{ display:grid; gap:10px; }
    .modal-form--single{ grid-template-columns:1fr; }
    .modal-form--double{ grid-template-columns:repeat(2,minmax(220px,1fr)); }
    .modal-title{ margin:0; }
    .modal-title--full{ grid-column:1/-1; }
    .form-full{ width:100%; }
    .field-full{ grid-column:1/-1; }
    .checkbox-row{ display:flex; align-items:center; gap:8px; }
    .modal-error{ margin:0; }
    .modal-error--full{ grid-column:1/-1; }
    .modal-actions{ display:flex; justify-content:flex-end; gap:8px; }
    .modal-actions--full{ grid-column:1/-1; }
    .form-grid-two{ display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:10px; }
    .input-control{ width:100%; padding:8px; border:1px solid var(--line); border-radius:8px; }
    .table-wrap{ overflow:auto; }
    .table-wrap.hidden{ display:none; }
    .data-table{ width:100%; border-collapse:collapse; min-width:680px; }
    .data-table th{ text-align:left; border-bottom:1px solid var(--line); padding:8px; }
    .data-table td{ border-bottom:1px solid var(--line); padding:8px; }
    .toolbar-actions{ display:flex; gap:8px; align-items:center; }
    .toolbar-actions-end{ display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
    .select-min-260{ min-width:260px; }
    .hidden{ display:none !important; }
    .mt-8{ margin-top:8px; }
    .scenes-group-body{ margin-top:8px; display:grid; gap:8px; }
    .scenes-group-row{ cursor:default; }
    .scenes-group-header{ cursor:pointer; }
    .scenario-row{ border:1px solid var(--line); border-radius:8px; padding:8px; }
    .mt-4{ margin-top:4px; }
    .mt-2{ margin-top:2px; }
    .value{ font-size:24px; font-weight:700; }
    .row{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .switch{ border:1px solid var(--line); border-radius:8px; padding:6px 10px; background:#fff; cursor:pointer; }
    .switch.active{ border-color:#7dd3fc; background:#ecfeff; color:#0b4f77; }
    .on{ color:#047857; }
    .off{ color:#b91c1c; }
    .error{ color:#b91c1c; }
    @media (max-width: 960px){
      .layout{ grid-template-columns:1fr; }
      .nav{ flex-wrap:wrap; }
    }
    @media (max-width: 768px){
      .wrap{ padding:14px; }
      .cards{ grid-template-columns:1fr; }
      .charts-grid{ grid-template-columns:1fr; }
      .history-range-controls{ flex-wrap:wrap; }
      .history-range-controls .switch{ min-width:56px; }
      .toolbar-actions{ width:100%; justify-content:space-between; }
      .toolbar-actions .switch{ width:auto; }
      .modal-dialog{ width:100%; max-width:none; margin:0; padding:12px; }
      .modal-form--double{ grid-template-columns:1fr; }
      .form-grid-two{ grid-template-columns:1fr; }
      .modal-actions{ flex-wrap:wrap; }
      .modal-actions .switch{ flex:1 1 160px; }
      .section-title{ font-size:1.05rem; }
    }
    @media (max-width: 520px){
      .head h1{ font-size:1.35rem; }
      .row{ flex-wrap:wrap; }
      .switch{ width:100%; }
      .history-range-controls .switch{ width:auto; }
      .toolbar-actions .switch{ width:auto; }
      .nav a{ padding:4px 0; }
    }
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="panel nav">
      <a href="/">Дашборд</a>
      <a href="/scenes">Сценарии</a>
      <a href="/parameters">Параметры</a>
      <a href="/schedule">Расписание</a>
      <a href="/settings">Настройки</a>
    </nav>
    <?= $content ?>
  </main>
  <?php if (!empty($script)): ?>
    <script src="<?= htmlspecialchars((string)$script, ENT_QUOTES, 'UTF-8') ?>"></script>
  <?php endif; ?>
</body>
</html>
