<?php /* Shared page styles for all admin pages — mirrors owner/frontdesk design */ ?>
<style>
body { background: #f4f6f9; }
.content { padding: 0 !important; flex: 1; min-width: 0; overflow-x: hidden; overflow-y: auto; }
.main-container { display: flex; min-height: 100vh; }

.dash-topbar {
    background: #fff; padding: 12px 24px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #e8ecf0; position: sticky; top: 0; z-index: 50;
}
.dash-topbar-title { font-size: 1.1rem; font-weight: 800; color: #1a1a1a; }
.dash-topbar-sub   { font-size: .78rem; color: #888; margin-top: 1px; }
.dash-topbar-badge {
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    color: #fff; border-radius: 20px; padding: 4px 12px; font-size: .74rem; font-weight: 700;
}
.dash-body { padding: 18px 24px; }

.kpi-card {
    background: #fff; border-radius: 14px; padding: 14px 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
    display: flex; align-items: center; gap: 12px;
    transition: transform .25s, box-shadow .25s; border: none; margin-bottom: 0;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.08); }
.kpi-icon {
    width: 44px; height: 44px; border-radius: 11px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
}
.kpi-icon.green  { background: #e8f5e9; color: #1B7D3A; }
.kpi-icon.blue   { background: #e3f2fd; color: #1565c0; }
.kpi-icon.orange { background: #fff3e0; color: #e65100; }
.kpi-icon.purple { background: #f3e5f5; color: #6a1b9a; }
.kpi-icon.teal   { background: #e0f2f1; color: #00695c; }
.kpi-icon.red    { background: #fdecea; color: #c62828; }
.kpi-icon.yellow { background: #fff8e1; color: #f57f17; }
.kpi-icon.pink   { background: #fce4ec; color: #c2185b; }
.kpi-num { font-size: 1.45rem; font-weight: 900; color: #1a1a1a; line-height: 1; }
.kpi-lbl { font-size: .75rem; color: #888; margin-top: 2px; }

.section-hdr { margin-bottom: 12px; }
.section-hdr h5 { font-weight: 800; color: #1a1a1a; margin: 0; font-size: .95rem; }
.section-hdr p  { color: #888; font-size: .8rem; margin: 2px 0 0; }

.table-card {
    background: #fff; border-radius: 14px; padding: 16px 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
}
.table-card .table thead th {
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    color: #fff; font-size: .7rem; text-transform: uppercase;
    letter-spacing: .5px; border: none; padding: 7px 10px; white-space: nowrap;
}
.table-card .table tbody td { padding: 6px 10px; font-size: .8rem; vertical-align: middle; border-color: #f5f5f5; white-space: nowrap; }
.table-card .table tbody tr:hover { background: #f8fffe; }

.chart-card {
    background: #fff; border-radius: 14px; padding: 16px 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05); height: 100%;
}
.chart-wrap { position: relative; height: 220px; width: 100%; }

.pill { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: .72rem; font-weight: 700; }
.pill-green  { background: #e8f5e9; color: #1B7D3A; }
.pill-yellow { background: #fff8e1; color: #e65100; }
.pill-red    { background: #fdecea; color: #c62828; }
.pill-blue   { background: #e3f2fd; color: #1565c0; }
.pill-grey   { background: #f5f5f5; color: #757575; }

.btn-add {
    background: linear-gradient(135deg, #1B7D3A, #27A457);
    color: #fff; border: none; border-radius: 9px; padding: 7px 16px;
    font-weight: 700; font-size: .82rem; cursor: pointer; transition: all .2s;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-add:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(27,125,58,.3); color: #fff; }
.btn-view { background: #e3f2fd; color: #1565c0; border: none; border-radius: 7px; padding: 5px 10px; font-size: .78rem; cursor: pointer; font-weight: 600; transition: all .2s; }
.btn-view:hover { background: #1565c0; color: #fff; }
.btn-approve { background: #e8f5e9; color: #1B7D3A; border: none; border-radius: 7px; padding: 5px 10px; font-size: .78rem; cursor: pointer; font-weight: 600; transition: all .2s; }
.btn-approve:hover { background: #1B7D3A; color: #fff; }
.btn-decline { background: #fdecea; color: #c62828; border: none; border-radius: 7px; padding: 5px 10px; font-size: .78rem; cursor: pointer; font-weight: 600; transition: all .2s; }
.btn-decline:hover { background: #c62828; color: #fff; }
.btn-del { background: #fdecea; color: #c62828; border: none; border-radius: 7px; padding: 5px 10px; font-size: .78rem; cursor: pointer; font-weight: 600; transition: all .2s; }
.btn-del:hover { background: #c62828; color: #fff; }

.search-bar { display: flex; align-items: center; gap: 6px; }
.search-bar .form-control { border-radius: 9px; border: 1.5px solid #e0e0e0; font-size: .84rem; padding: 7px 12px; }
.btn-search { background: linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; border-radius:9px; padding:7px 12px; font-size:.82rem; cursor:pointer; }
.btn-clear  { background: #fdecea; color:#c62828; border:none; border-radius:9px; padding:7px 10px; font-size:.82rem; cursor:pointer; text-decoration:none; }

.fac-card { background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.05); transition:transform .25s,box-shadow .25s; }
.fac-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.08); }
.fac-card-img { width:100%; height:140px; object-fit:cover; }
.fac-card-body { padding:14px; }
.fac-card-name { font-weight:800; font-size:.9rem; color:#1a1a1a; margin-bottom:8px; }
.fac-info-row { display:flex; justify-content:space-between; align-items:center; font-size:.78rem; color:#555; padding:4px 0; border-bottom:1px solid #f5f5f5; }
.fac-info-row:last-child { border-bottom:none; }
.btn-update { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; border-radius:7px; padding:5px 10px; font-size:.78rem; cursor:pointer; flex-shrink:0; }

/* Settings form */
.settings-card { background:#fff; border-radius:14px; padding:20px 22px; box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:20px; }
.settings-card h5 { font-weight:800; color:#1a1a1a; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid #f0f0f0; font-size:.95rem; }
.settings-card .form-label { font-size:.78rem; font-weight:700; color:#222; }
.settings-card .form-control, .settings-card .form-select { border:1.5px solid #e0e0e0; border-radius:9px; font-size:.86rem; padding:8px 12px; }
.settings-card .form-control:focus, .settings-card .form-select:focus { border-color:#27A457; box-shadow:0 0 0 3px rgba(39,164,87,.13); }
.btn-save { background:linear-gradient(135deg,#1B7D3A,#27A457); color:#fff; border:none; border-radius:9px; padding:8px 20px; font-weight:700; font-size:.85rem; cursor:pointer; transition:all .25s; }
.btn-save:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(27,125,58,.3); }

/* Alert pending */
.alert-pending { background:#fff8e1; border:1.5px solid #ffe082; border-radius:12px; padding:12px 18px; display:flex; align-items:center; gap:12px; font-size:.88rem; color:#e65100; font-weight:600; }
</style>
