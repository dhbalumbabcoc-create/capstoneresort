<?php /* Shared sidebar styles — Owner / Frontdesk / Supervisor */ ?>
<style>
/* ═══════════════════════════════════════════════════
   SHARED SIDEBAR — matches guest dashboard style
   ═══════════════════════════════════════════════════ */
:root { --sb-w: 240px; }
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body { background:#f4f6f9; }

/* ── Shell ── */
.sidebar-col { position:fixed; top:0; left:0; height:100vh; width:var(--sb-w); z-index:200; }
.sidebar {
    width:100%; height:100%;
    background:#1a3d2b;
    display:flex; flex-direction:column; overflow:hidden;
}

/* ── Brand ── */
.sb-brand {
    display:flex; align-items:center; gap:10px;
    padding:14px 16px 12px;
    border-bottom:1px solid rgba(255,255,255,.1);
    flex-shrink:0;
}
.sb-brand-logo {
    width:36px; height:36px; border-radius:10px; object-fit:cover;
    border:2px solid rgba(255,255,255,.3);
    box-shadow:0 2px 8px rgba(0,0,0,.25); flex-shrink:0;
}
.sb-brand-text strong { display:block; color:#fff; font-size:.86rem; font-weight:700; line-height:1.2; }
.sb-brand-text span   { color:rgba(255,255,255,.6); font-size:.7rem; font-weight:500; }

/* ── Profile block (below brand, above nav) ── */
.sb-profile-block {
    padding:12px 14px 10px;
    border-bottom:1px solid rgba(255,255,255,.1);
    text-align:center;
    flex-shrink:0;
}
.sb-profile-avatar {
    width:52px; height:52px; border-radius:50%;
    background:rgba(255,255,255,.2);
    border:2.5px solid rgba(255,255,255,.35);
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:1.35rem; color:#fff;
    margin:0 auto 6px;
    overflow:hidden;
}
.sb-profile-avatar img { width:100%; height:100%; object-fit:cover; }
.sb-profile-name  { color:#fff; font-weight:700; font-size:.88rem; margin-bottom:1px; }
.sb-profile-email { color:rgba(255,255,255,.6); font-size:.68rem; margin-bottom:6px; word-break:break-all; }
.sb-profile-badge {
    display:inline-flex; align-items:center; gap:5px;
    background:rgba(255,255,255,.15); color:#fff;
    border-radius:20px; padding:3px 10px; font-size:.68rem; font-weight:600;
}
.sb-profile-badge .dot {
    width:6px; height:6px; border-radius:50%;
    background:#4ade80; flex-shrink:0;
}

/* ── Nav ── */
.sb-nav { flex:1; overflow-y:auto; padding:10px 10px 0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.2) transparent; }
.sb-nav::-webkit-scrollbar { width:3px; }
.sb-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,.2); border-radius:3px; }

/* ── Nav link ── */
.sb-link {
    display:flex; align-items:center; gap:10px;
    padding:8px 12px; border-radius:9px; margin-bottom:2px;
    color:rgba(255,255,255,.78); font-size:.85rem; font-weight:500;
    text-decoration:none; cursor:pointer; border:none; background:none;
    width:100%; text-align:left; transition:all .2s ease; position:relative;
    white-space:nowrap;
}
.sb-link i.sb-nav-icon { font-size:.9rem; width:16px; text-align:center; flex-shrink:0; }
.sb-link .sb-label { flex:1; }
.sb-link .sb-arrow { font-size:.6rem; opacity:.6; transition:transform .25s; }
.sb-link:hover { background:rgba(255,255,255,.12); color:#fff; }
.sb-link.active {
    background:rgba(255,255,255,.2); color:#fff; font-weight:600;
}
.sb-link.active::before {
    content:''; position:absolute; left:0; top:50%; transform:translateY(-50%);
    width:3px; height:60%; background:#fff; border-radius:0 3px 3px 0;
}
.sb-link.sb-parent[aria-expanded="true"] .sb-arrow { transform:rotate(180deg); }

/* ── Sub-menu ── */
.sb-sub { padding-left:12px; }
.sb-sub .sb-link { padding:7px 10px; font-size:.82rem; color:rgba(255,255,255,.65); }
.sb-sub .sb-link:hover { color:#fff; background:rgba(255,255,255,.1); }
.sb-sub .sb-link.active { color:#fff; background:rgba(255,255,255,.15); }

/* ── Section label ── */
.sb-section-label {
    font-size:.62rem; font-weight:700; letter-spacing:1px; text-transform:uppercase;
    color:rgba(255,255,255,.4); padding:8px 8px 4px; margin-top:2px;
}

/* ── Sign Out ── */
.sb-signout {
    flex-shrink:0; padding:10px 12px;
    border-top:1px solid rgba(255,255,255,.1);
}
.sb-signout a {
    display:flex; align-items:center; gap:8px;
    color:rgba(255,255,255,.7); font-size:.84rem; font-weight:600;
    text-decoration:none; padding:8px 12px; border-radius:9px;
    transition:all .2s;
}
.sb-signout a:hover { background:rgba(220,53,69,.25); color:#fff; }
.sb-signout a i { font-size:.9rem; }

/* ── Content offset ── */
.content { margin-left:var(--sb-w); padding:0; width:calc(100% - var(--sb-w)); }

/* ── Shared page utilities ── */
.dashboard-card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,.1); margin-bottom:20px; transition:all .3s; }
.dashboard-card:hover { box-shadow:0 4px 8px rgba(0,0,0,.15); }
.stat-card { text-align:center; padding:20px; border-radius:12px !important; }
.stat-number { font-size:36px; font-weight:bold; color:#1B7D3A; }
.stat-label { color:#666; margin-top:10px; font-size:14px; }
.stat-icon { font-size:24px; color:#1B7D3A; margin-bottom:10px; }
.btn-gradient { background:linear-gradient(135deg,#1B7D3A 0%,#27A457 100%); border:none; color:#fff; border-radius:12px !important; }
.btn-gradient:hover { background:linear-gradient(135deg,#27A457 0%,#1B7D3A 100%); color:#fff; }
.table { width:100%; border-collapse:collapse; background:#fff; }
.table thead { background:linear-gradient(135deg,#1B7D3A 0%,#27A457 100%); color:#fff; }
.table tbody tr:hover { background:#f8f9fa; }
.badge { padding:6px 12px; font-weight:500; }
h2,h4,h5 { color:#000; font-weight:600; }
.modal-header { background:linear-gradient(135deg,#1B7D3A 0%,#27A457 100%); color:#fff; }
.modal-header .btn-close { filter:brightness(0) invert(1); }
.btn-primary,.bg-primary { background-color:#1B7D3A !important; border-color:#1B7D3A !important; color:#fff !important; }
.btn-primary:hover { background-color:#166431 !important; border-color:#166431 !important; }
.btn-success,.bg-success { background-color:#27A457 !important; border-color:#27A457 !important; color:#fff !important; }
.btn-warning,.bg-warning { background-color:#FFD166 !important; border-color:#FFD166 !important; color:#2f2f2f !important; }
.btn-danger,.bg-danger   { background-color:#D9534F !important; border-color:#D9534F !important; color:#fff !important; }
.btn-info,.bg-info       { background-color:#4AA3A2 !important; border-color:#4AA3A2 !important; color:#fff !important; }
.type-badge { padding:5px 10px; border-radius:5px; font-size:12px; font-weight:bold; }
.type-room { background:#4facfe; color:#fff; }
.type-cottage { background:#43e97b; color:#fff; }
.type-function_hall { background:#f093fb; color:#fff; }
.status-confirmed { background:#43e97b; color:#fff; }
.status-pending { background:#ffd89b; color:#333; }
.status-declined { background:#ff6b6b; color:#fff; }
</style>
