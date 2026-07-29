<?php /* Shared page styles for all supervisor pages */ ?>
<style>
body { background: #f4f6f9; }
.content { padding: 0 !important; min-width: 0; overflow-x: hidden; }
.dash-topbar { background:#fff; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e8ecf0; position:sticky; top:0; z-index:50; }
.dash-topbar-title { font-size:1.1rem; font-weight:800; color:#1a1a1a; }
.dash-topbar-sub   { font-size:.78rem; color:#888; margin-top:1px; }
.dash-topbar-badge { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border-radius:20px; padding:4px 12px; font-size:.74rem; font-weight:700; }
.dash-body { padding:18px 24px; }
.kpi-card { background:#fff; border-radius:14px; padding:14px 16px; box-shadow:0 2px 10px rgba(0,0,0,.05); display:flex; align-items:center; gap:12px; transition:transform .25s,box-shadow .25s; border:none; margin-bottom:0; }
.kpi-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.08); }
.kpi-icon { width:44px; height:44px; border-radius:11px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.15rem; }
.kpi-icon.green  { background:#e8f5e9; color:#1B7D3A; }
.kpi-icon.blue   { background:#e3f2fd; color:#1565c0; }
.kpi-icon.orange { background:#fff3e0; color:#e65100; }
.kpi-icon.yellow { background:#fffde7; color:#f9a825; }
.kpi-icon.red    { background:#fdecea; color:#c62828; }
.kpi-icon.teal   { background:#e0f2f1; color:#00695c; }
.kpi-icon.purple { background:#f3e5f5; color:#6a1b9a; }
.kpi-num { font-size:1.45rem; font-weight:900; color:#1a1a1a; line-height:1; }
.kpi-lbl { font-size:.75rem; color:#888; margin-top:2px; }
.section-hdr { margin-bottom:12px; }
.section-hdr h5 { font-weight:800; color:#1a1a1a; margin:0; font-size:.95rem; }
.section-hdr p  { color:#888; font-size:.8rem; margin:2px 0 0; }
.chart-card { background:#fff; border-radius:14px; padding:16px 18px; box-shadow:0 2px 10px rgba(0,0,0,.05); height:100%; }
.chart-card h6 { font-weight:700; color:#1a1a1a; margin-bottom:10px; font-size:.88rem; }
.chart-wrap { position:relative; height:220px; width:100%; }
.table-card { background:#fff; border-radius:14px; padding:16px 18px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
.table-card .table thead th { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; border:none; padding:7px 10px; white-space:nowrap; }
.table-card .table tbody td { padding:6px 10px; font-size:.8rem; vertical-align:middle; border-color:#f5f5f5; white-space:nowrap; }
.table-card .table tbody tr:hover { background:#f8fffe; }
.pill { display:inline-block; padding:3px 9px; border-radius:20px; font-size:.72rem; font-weight:700; }
.pill-green  { background:#e8f5e9; color:#1B7D3A; }
.pill-yellow { background:#fffde7; color:#f9a825; }
.pill-red    { background:#fdecea; color:#c62828; }
.pill-blue   { background:#e3f2fd; color:#1565c0; }
.pill-orange { background:#fff3e0; color:#e65100; }
.pill-grey   { background:#f5f5f5; color:#888; }
.btn-add { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; border-radius:9px; padding:7px 16px; font-size:.82rem; font-weight:700; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
.btn-add:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(27,125,58,.3); color:#fff; }
.btn-edit { background:#fff8e1; color:#e65100; border:1.5px solid #ffe082; border-radius:7px; padding:4px 10px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-edit:hover { background:#fff3e0; }
.alert-banner { background:linear-gradient(135deg,#fdecea,#fff5f5); border:1.5px solid #f5c6cb; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.alert-banner i { font-size:1.15rem; color:#c62828; }
.modal-content { border:none; border-radius:16px; overflow:hidden; }
.modal-header { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; padding:14px 20px; }
.modal-header .btn-close { filter:brightness(0) invert(1); }
.modal-title { font-weight:700; font-size:.95rem; }
.modal-body { padding:18px 20px; }
.modal-footer { border-top:1px solid #f0f0f0; padding:12px 20px; }
.modal-body .form-label { font-weight:600; font-size:.82rem; color:#333; margin-bottom:4px; }
.modal-body .form-control, .modal-body .form-select { border:1.5px solid #e0e0e0; border-radius:9px; padding:8px 12px; font-size:.84rem; }
.modal-body .form-control:focus, .modal-body .form-select:focus { border-color:#1B7D3A; box-shadow:0 0 0 3px rgba(27,125,58,.1); }
.fac-card { background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.05); transition:transform .25s,box-shadow .25s; border:none; }
.fac-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.08); }
.fac-card-img { width:100%; height:140px; object-fit:cover; }
.fac-card-body { padding:14px; }
.fac-card-name { font-weight:700; font-size:.9rem; color:#1a1a1a; margin-bottom:8px; }
.fac-info-row { display:flex; justify-content:space-between; font-size:.78rem; color:#888; margin-bottom:4px; }
.fac-info-row strong { color:#333; }
.btn-update { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; border-radius:7px; padding:5px 12px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .2s; }
.btn-update:hover { transform:translateY(-1px); box-shadow:0 4px 10px rgba(27,125,58,.25); }
/* Settings form */
.settings-card { background:#fff; border-radius:14px; padding:20px 22px; box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:20px; }
.settings-card h5 { font-weight:800; color:#1a1a1a; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid #f0f0f0; font-size:.95rem; }
.settings-card .form-label { font-size:.78rem; font-weight:700; color:#222; }
.settings-card .form-control, .settings-card .form-select { border:1.5px solid #e0e0e0; border-radius:9px; font-size:.86rem; padding:8px 12px; }
.settings-card .form-control:focus, .settings-card .form-select:focus { border-color:#27A457; box-shadow:0 0 0 3px rgba(39,164,87,.13); }
.btn-save { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; border-radius:9px; padding:8px 20px; font-weight:700; font-size:.85rem; cursor:pointer; transition:all .25s; display:inline-flex; align-items:center; gap:6px; box-shadow:0 3px 10px rgba(27,125,58,.25); }
.btn-save:hover { background:linear-gradient(135deg,#14602c,#1f8a48); transform:translateY(-1px); box-shadow:0 4px 14px rgba(27,125,58,.35); color:#fff; }
.btn-save:active { transform:translateY(0); }
</style>
