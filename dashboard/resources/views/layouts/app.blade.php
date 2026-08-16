<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $isAdmin = request()->getHost() === 'admin.azkia.cloud';
        $pageTitle = match (true) {
            $isAdmin && request()->routeIs('admin.providers') => 'Providers',
            $isAdmin && request()->routeIs('admin.models') => 'Models',
            $isAdmin && request()->routeIs('admin.pricing') => 'Pricing',
            $isAdmin && request()->routeIs('admin.status') => 'Status',
            $isAdmin && request()->routeIs('admin.keys') => 'API Keys',
            $isAdmin && request()->routeIs('admin.redeem-codes.*') => 'Kode Redeem',
            $isAdmin && request()->routeIs('admin.deposits.*') => 'Kelola Deposit',
            $isAdmin && request()->routeIs('admin.billing-monitoring.*') => 'Billing Monitoring',
            $isAdmin && request()->routeIs('admin.request-logs.*') => 'Request Logs',
            $isAdmin && request()->routeIs('admin.rejections') => 'Request Ditolak',
            $isAdmin && request()->routeIs('admin.support.*') => 'Support Center',
            $isAdmin && request()->routeIs('admin.payment-settings.*') => 'Payment Gateway',
            $isAdmin && request()->routeIs('admin.event') => 'Event',
            $isAdmin && request()->routeIs('admin.dashboard-popups.*') => 'Popup Dashboard',
            $isAdmin && request()->routeIs('admin.users.show') => 'User Detail',
            $isAdmin && request()->routeIs('admin.users.edit') => 'Edit User',
            $isAdmin && request()->routeIs('admin.users') => 'Users',
            $isAdmin && request()->routeIs('admin.index') => 'Admin Dashboard',
            $isAdmin => 'Admin Dashboard',
            request()->routeIs('dashboard') => __('dashboard.titles.dashboard'),
            request()->routeIs('keys') => __('dashboard.titles.keys'),
            request()->routeIs('usage') => __('dashboard.titles.usage'),
            request()->routeIs('billing') => __('dashboard.titles.billing'),
            request()->routeIs('plans') => __('dashboard.titles.plans'),
            request()->routeIs('models') => __('dashboard.titles.models'),
            request()->routeIs('status') => __('dashboard.titles.status'),
            request()->routeIs('leaderboard') => __('dashboard.titles.leaderboard'),
            request()->routeIs('docs') => __('dashboard.titles.docs'),
            request()->routeIs('inbox*') => __('dashboard.titles.inbox'),
            request()->routeIs('support.*') => 'Support Center',
            request()->routeIs('settings') => __('dashboard.titles.settings'),
            request()->routeIs('redeem-codes.*') => 'Redeem Code',
            request()->routeIs('payments.create') => __('dashboard.titles.topup'),
            request()->routeIs('payments.show') => __('dashboard.titles.payment'),
            request()->routeIs('api-health') => __('dashboard.titles.api_health'),
            default => __('dashboard.titles.default'),
        };
        $user = auth()->user();
        $name = $user->name ?? 'User';
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
        $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
    @endphp
    <title>{{ $pageTitle }} · {{ config('app.name') }}</title>
    <script>
        (function () {
            var saved = localStorage.getItem('azkia-theme');
            var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== Design tokens ===== */
        :root{
            color-scheme:light;
            --bg:#f8fafc;--panel:#ffffff;--ink:#0f172a;--body:#334155;--muted:#64748b;
            --line:#e2e8f0;--line-strong:#cbd5e1;--soft:#f1f5f9;--topbar:rgba(255,255,255,.86);--table-head:#f8fafc;--input:#ffffff;--hero-start:#f0f6ff;--hero-mid:#fafbff;--hero-end:#eef8ff;
            --brand:#2563eb;--brand-hover:#1d4ed8;--brand-soft:#eff6ff;--brand-line:#bfdbfe;
            --green:#16a34a;--green-soft:#f0fdf4;--green-line:#bbf7d0;--green-ink:#15803d;
            --red:#dc2626;--red-soft:#fef2f2;--red-line:#fecaca;--red-ink:#b91c1c;
            --amber:#d97706;--amber-soft:#fffbeb;--amber-line:#fde68a;--amber-ink:#b45309;
            --r-card:14px;--r-btn:9px;--r-input:9px;
            --shadow-card:0 1px 2px rgba(15,23,42,.05);
            --shadow-hover:0 6px 16px rgba(15,23,42,.08);
        }
        :root[data-theme="dark"]{
            color-scheme:dark;
            --bg:#080d18;--panel:#111827;--ink:#f1f5f9;--body:#cbd5e1;--muted:#94a3b8;
            --line:#263244;--line-strong:#3b4a61;--soft:#1b2535;--topbar:rgba(17,24,39,.88);--table-head:#172033;--input:#111827;--hero-start:#111c31;--hero-mid:#111827;--hero-end:#102235;
            --brand:#60a5fa;--brand-hover:#93c5fd;--brand-soft:#172554;--brand-line:#1e40af;
            --green:#22c55e;--green-soft:#052e1b;--green-line:#166534;--green-ink:#86efac;
            --red:#ef4444;--red-soft:#3b1014;--red-line:#7f1d1d;--red-ink:#fca5a5;
            --amber:#f59e0b;--amber-soft:#35220a;--amber-line:#854d0e;--amber-ink:#fcd34d;
            --shadow-card:0 1px 3px rgba(0,0,0,.28);--shadow-hover:0 8px 22px rgba(0,0,0,.35);
        }
        *{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}
        body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased;font-size:14px;line-height:1.55}
        a{text-decoration:none;color:inherit}
        button{font-family:inherit}

        /* ===== Auth ===== */
        .auth-bg{min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:24px;position:relative;overflow:hidden;background:var(--bg)}
        .auth-bg::before,.auth-bg::after{content:"";position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none}
        .auth-bg::before{width:480px;height:480px;top:-180px;right:-120px;background:rgba(37,99,235,.14)}
        .auth-bg::after{width:420px;height:420px;bottom:-160px;left:-140px;background:rgba(14,165,233,.12)}
        .auth-card{width:100%;max-width:400px;background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:32px;box-shadow:var(--shadow-hover);position:relative}
        .auth-top{text-align:center;margin-bottom:24px}
        .auth-logo{width:44px;height:44px;border-radius:13px;margin:0 auto 14px;font-size:15px}
        .auth-top h2{margin:0;font-size:21px;font-weight:800;letter-spacing:-.02em}
        .auth-top p{margin:6px 0 0;color:var(--muted);font-size:13.5px}
        .auth-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:18px;color:var(--muted);font-size:13px;font-weight:600}
        .auth-back:hover{color:var(--brand)}
        .auth-card label{display:block;margin:14px 0 5px}
        .auth-card .btn{width:100%;margin-top:18px;padding:11px}
        .auth-switch{text-align:center;margin:18px 0 0;font-size:13.5px}
        .auth-switch a{color:var(--brand);font-weight:700}
        .auth-switch a:hover{text-decoration:underline}

        .dashboard-popup[hidden]{display:none}
        .dashboard-popup{position:fixed;inset:0;z-index:200;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.58);backdrop-filter:blur(4px)}
        .dashboard-popup-card{width:min(500px,100%);max-height:calc(100dvh - 40px);overflow-y:auto;background:var(--panel);border:1px solid var(--line);border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.3);padding:24px;position:relative}
        .dashboard-popup-close{position:absolute;top:12px;right:12px;width:34px;height:34px;padding:0;background:var(--soft);border-color:var(--line);color:var(--muted);box-shadow:none}
        .dashboard-popup-close:hover{background:var(--line);border-color:var(--line-strong);color:var(--ink);box-shadow:none}
        .dashboard-popup-card h2{margin:0 44px 8px 0;font-size:21px;line-height:1.3}
        .dashboard-popup-body{color:var(--body);white-space:pre-line;overflow-wrap:anywhere}
        .dashboard-popup-body .popup-shine,.dashboard-popup-body.popup-shine-all,.dashboard-popup-body.popup-shine-all *{background:linear-gradient(90deg,#2563eb,#0ea5e9,#ffffff,#0ea5e9,#2563eb);background-size:240% 100%;-webkit-background-clip:text;background-clip:text;color:transparent!important;animation:dashboardPopupShine 2.8s linear infinite;font-weight:800}
        @keyframes dashboardPopupShine{from{background-position:200% 0}to{background-position:-200% 0}}
        .dashboard-popup-preference{display:flex;align-items:center;gap:8px;margin-top:18px;color:var(--muted);font-size:13px;cursor:pointer}
        .dashboard-popup-preference input{width:16px;height:16px;margin:0;flex:0 0 auto}
        .dashboard-popup-actions{display:flex;justify-content:flex-end;gap:9px;flex-wrap:wrap;margin-top:18px}
        @media(max-width:639.98px){.dashboard-popup{padding:14px}.dashboard-popup-card{padding:20px;border-radius:15px}.dashboard-popup-actions{flex-direction:column-reverse}.dashboard-popup-actions .btn,.dashboard-popup-actions button{width:100%}}

        /* ===== Logo ===== */
        .logo{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,var(--brand),#0ea5e9);display:grid;place-items:center;color:#fff;font-weight:800;font-size:13px;letter-spacing:.02em;flex:0 0 auto;box-shadow:0 4px 10px rgba(37,99,235,.28)}
        .logo.platform-logo{background:transparent;object-fit:contain;padding:0;border:0;box-shadow:none}
        .logo.sm{width:30px;height:30px;border-radius:9px;font-size:11px;box-shadow:none}

        /* ===== Sidebar (off-canvas drawer) ===== */
        .app{min-height:100vh;min-height:100dvh}
        .sidebar{position:fixed;inset:0 auto 0 0;width:264px;background:var(--panel);border-right:1px solid var(--line);padding:18px 14px;display:flex;flex-direction:column;z-index:40;transform:translateX(-106%);transition:transform .3s cubic-bezier(.4,0,.2,1);box-shadow:0 0 30px rgba(15,23,42,.08)}
        body.nav-open .sidebar{transform:translateX(0)}
        .brand{display:flex;align-items:center;gap:11px;padding:2px 8px 16px;border-bottom:1px solid var(--line);margin-bottom:14px;position:relative}
        .brand h1{font-size:15px;margin:0;font-weight:750;letter-spacing:-.01em}
        .brand p{font-size:11.5px;color:var(--muted);margin:1px 0 0}
        .sidebar-close{display:none;position:absolute;right:0;top:-4px;width:32px;height:32px;border:0;border-radius:9px;background:var(--soft);color:var(--muted);font-size:14px;cursor:pointer;align-items:center;justify-content:center;transition:background .15s,color .15s}
        .sidebar-close:hover{background:var(--line);color:var(--ink)}
        .nav{display:grid;gap:2px;flex:1;overflow-y:auto;overflow-x:hidden;padding-right:2px;scrollbar-width:thin;scrollbar-color:var(--line) transparent}
        .nav::-webkit-scrollbar{width:5px}
        .nav::-webkit-scrollbar-thumb{background:var(--line);border-radius:999px}
        .nav-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:10px 11px 5px}
        /* Spesifisitas > .nav a agar gaya kartu tidak tertimpa (background/border/display flex). */
        .nav .sidebar-plans{display:grid;gap:4px;padding:2px 2px 8px}
        .nav .plan-mini{display:block;width:100%;background:var(--soft);border:1px solid var(--line);border-radius:9px;padding:7px 10px;gap:0;white-space:normal;text-align:left;color:var(--body);font-weight:550;transition:border-color .13s,background .13s}
        .nav .plan-mini:hover{background:var(--line);border-color:var(--line-strong)}
        .plan-mini-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
        .plan-mini-head strong{font-size:12px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .plan-mini-head span{font-size:11px;color:var(--muted);flex:0 0 auto;font-variant-numeric:tabular-nums}
        .plan-mini-bar{height:5px;border-radius:999px;background:var(--line);overflow:hidden;margin-top:5px}
        .plan-mini-bar > div{height:100%;border-radius:999px;background:var(--brand);transition:width .3s}
        .nav a,.nav button{width:100%;display:flex;align-items:center;gap:10px;text-align:left;background:transparent;border:0;color:var(--body);padding:8.5px 11px;border-radius:9px;font-size:13.5px;font-weight:550;cursor:pointer;white-space:nowrap;transition:background .13s,color .13s}
        .nav a svg,.nav button svg{width:17px;height:17px;flex:0 0 auto;color:var(--muted);transition:color .13s}
        .nav a:hover,.nav button:hover{background:var(--soft);color:var(--ink)}
        .nav a:hover svg,.nav button:hover svg{color:var(--ink)}
        .nav a:active,.nav button:active{transform:scale(.985)}
        .nav .active{background:var(--brand-soft);color:var(--brand);font-weight:700}
        .nav .active svg{color:var(--brand)}
        .nav .danger-link{color:var(--red-ink)}
        .nav .danger-link svg{color:var(--red-ink)}
        .nav .danger-link:hover{background:var(--red-soft);color:var(--red-ink)}
        .sidebar-logout{margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
        .sidebar-logout button{width:100%;display:flex;align-items:center;justify-content:flex-start;gap:10px;background:transparent;border:0;color:var(--red-ink);padding:8.5px 11px;border-radius:9px;font-size:13.5px;font-weight:600;box-shadow:none}
        .sidebar-logout button:hover{background:var(--red-soft);border:0;box-shadow:none}
        .sidebar-logout svg{width:17px;height:17px;flex:0 0 auto}
        .footer-user{margin-top:8px;padding-top:12px;border-top:1px solid var(--line);display:flex;align-items:center;gap:10px}
        .footer-user .logo{width:34px;height:34px;border-radius:10px;font-size:12px}
        .fu-text{min-width:0}
        .fu-text strong{display:block;font-size:13px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .fu-text span{display:block;font-size:11.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        /* ===== Overlay ===== */
        .overlay{position:fixed;inset:0;z-index:30;background:rgba(15,23,42,.4);backdrop-filter:blur(2px);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
        body.nav-open .overlay{opacity:1;visibility:visible}
        body.nav-open{overflow:hidden}

        /* ===== Main / Topbar ===== */
        .main{margin-left:264px;min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;transition:margin-left .3s cubic-bezier(.4,0,.2,1)}
        body.nav-collapsed .main{margin-left:0}
        .topbar{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:12px;height:60px;padding:0 24px;background:var(--topbar);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
        .hamburger{width:36px;height:36px;border:0;border-radius:9px;background:transparent;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;cursor:pointer;color:var(--ink);transition:background .13s}
        .hamburger:hover{background:var(--soft)}
        .hamburger span{display:block;width:17px;height:2px;border-radius:2px;background:currentColor;transition:transform .22s cubic-bezier(.4,0,.2,1),opacity .18s}
        body.menu-visible .hamburger span:nth-child(1){transform:translateY(7px) rotate(45deg)}
        body.menu-visible .hamburger span:nth-child(2){opacity:0}
        body.menu-visible .hamburger span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
        .tb-brand{display:none;align-items:center;gap:9px;font-weight:750;font-size:14.5px;letter-spacing:-.01em}
        .tb-title{font-size:14.5px;font-weight:700;letter-spacing:-.01em}
        .tb-right{margin-left:auto;display:flex;align-items:center;gap:12px}
        .locale-switch{display:inline-flex;align-items:center;background:var(--soft);border:1px solid var(--line);border-radius:9px;padding:2px;gap:2px}
        .locale-switch button{background:transparent;border:0;color:var(--muted);padding:4px 7px;border-radius:6px;font-size:10.5px;box-shadow:none}
        .locale-switch button.active{background:var(--panel);color:var(--ink);box-shadow:0 1px 3px rgba(15,23,42,.12)}
        .theme-toggle{width:36px;height:36px;padding:0;border-radius:10px;background:var(--soft);border:1px solid var(--line);color:var(--body);box-shadow:none}
        .theme-toggle:hover{background:var(--panel);border-color:var(--line-strong);color:var(--ink);box-shadow:none}
        .theme-toggle svg{width:17px;height:17px}
        .theme-toggle .icon-sun{display:none}
        :root[data-theme="dark"] .theme-toggle .icon-sun{display:block}
        :root[data-theme="dark"] .theme-toggle .icon-moon{display:none}
        .inbox-menu{position:relative}
        .inbox-btn{width:36px;height:36px;padding:0;border-radius:10px;background:transparent;border:1px solid transparent;color:var(--body);box-shadow:none;position:relative}
        .inbox-btn:hover{background:var(--soft);border-color:var(--line);color:var(--ink);box-shadow:none}
        .inbox-btn svg{width:18px;height:18px}
        .inbox-count{position:absolute;top:-4px;right:-4px;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:var(--red);color:#fff;border:2px solid var(--panel);font-size:9px;font-weight:800;display:grid;place-items:center}
        .inbox-dropdown{position:fixed;right:16px;top:68px;width:min(360px,calc(100vw - 28px));max-height:calc(100dvh - 84px);overflow-y:auto;overscroll-behavior:contain;background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 16px 40px rgba(15,23,42,.2);padding:7px;z-index:100;scrollbar-width:thin;scrollbar-color:var(--line-strong) transparent}
        .inbox-dropdown[hidden]{display:none}
        .inbox-head{display:flex;align-items:center;justify-content:space-between;padding:8px 9px 10px;border-bottom:1px solid var(--line)}
        .inbox-head strong{font-size:13.5px}
        .inbox-head a{font-size:11.5px;color:var(--brand);font-weight:700}
        .inbox-item{display:block;padding:10px;border-radius:9px;border-bottom:1px solid var(--line)}
        .inbox-item:last-of-type{border-bottom:0}
        .inbox-item:hover{background:var(--soft)}
        .inbox-item.unread{background:var(--brand-soft)}
        .inbox-item strong{display:block;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .inbox-item span{display:block;color:var(--muted);font-size:11.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
        .inbox-item time{display:block;color:var(--muted);font-size:10.5px;margin-top:5px}
        .inbox-empty{padding:24px 12px;text-align:center;color:var(--muted);font-size:12.5px}
        .inbox-foot{display:block;text-align:center;padding:9px;color:var(--brand);font-size:12px;font-weight:700;border-top:1px solid var(--line)}
        .tb-balance{display:inline-flex;align-items:center;gap:7px;background:var(--soft);border:1px solid var(--line);border-radius:999px;padding:4px 11px}
        .tb-balance-usd{font-size:11.5px;font-weight:650;color:var(--muted);font-variant-numeric:tabular-nums}
        .tb-balance-label{font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700}
        .tb-balance strong{font-size:13px;font-weight:750;color:var(--green-ink);font-variant-numeric:tabular-nums}
        .tb-balance strong::before{content:"●";font-size:7px;margin-right:4px;vertical-align:2px}
        .avatar{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--brand),#0ea5e9);color:#fff;display:grid;place-items:center;font-weight:750;font-size:12.5px;letter-spacing:.02em;flex:0 0 auto;box-shadow:0 4px 10px rgba(37,99,235,.25)}
        .tb-user{display:flex;flex-direction:column;line-height:1.3}
        .tb-user strong{font-size:13px;font-weight:700}
        .tb-user span{font-size:12px;color:var(--muted)}
        .tb-user-menu{position:relative}
        .tb-user-btn{display:flex;align-items:center;gap:10px;background:transparent;border:0;border-radius:10px;padding:4px 8px;cursor:pointer;color:var(--ink);transition:background .13s}
        .tb-user-btn:hover{background:var(--soft)}
        .tb-caret{width:14px;height:14px;color:var(--muted);flex:0 0 auto;transition:transform .15s}
        .tb-user-menu.open .tb-caret{transform:rotate(180deg)}
        .tb-menu{position:absolute;right:0;top:calc(100% + 8px);min-width:230px;background:var(--panel);border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 32px rgba(15,23,42,.16);padding:6px;z-index:50;animation:menuIn .15s ease}
        .tb-menu[hidden]{display:none}
        @keyframes menuIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
        .tb-menu-head{padding:8px 10px 10px;border-bottom:1px solid var(--line);margin-bottom:6px}
        .tb-menu-head strong{display:block;font-size:13.5px;font-weight:750}
        .tb-menu-head span{display:block;font-size:12px;color:var(--muted);word-break:break-all}
        .tb-menu-item{display:flex;align-items:center;gap:9px;width:100%;text-align:left;background:transparent;border:0;color:var(--body);border-radius:8px;padding:8px 10px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:background .13s,color .13s}
        .tb-menu-item:hover{background:var(--soft);color:var(--ink)}
        .tb-menu-item svg{width:15px;height:15px;flex:0 0 auto;color:var(--muted)}
        .tb-menu-item.tb-menu-danger{color:var(--red-ink)}
        .tb-menu-item.tb-menu-danger:hover{background:var(--red-soft)}
        .tb-menu-item.tb-menu-danger svg{color:var(--red-ink)}
        .tb-menu-sep{height:1px;background:var(--line);margin:6px 4px}

        /* ===== Content ===== */
        .content{flex:1;padding:30px 32px 48px;max-width:1400px;width:100%}
        .legal-footer{border-top:1px solid var(--line);background:var(--panel);color:var(--muted);font-size:12.5px}
        .legal-footer-inner{display:flex;align-items:center;justify-content:space-between;gap:14px;max-width:1400px;margin:0 auto;padding:18px 32px;flex-wrap:wrap}
        .legal-footer nav{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .legal-footer a{color:var(--muted);font-weight:600;text-decoration:none}
        .legal-footer a:hover{color:var(--ink)}
        .top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:22px;flex-wrap:wrap}
        .top h2{font-size:22px;margin:0;font-weight:800;letter-spacing:-.02em}
        .top p{color:var(--muted);margin:4px 0 0;font-size:14px}
        .pill{display:inline-flex;align-items:center;gap:6px;background:var(--brand-soft);color:var(--brand);border:1px solid var(--brand-line);border-radius:999px;padding:4px 11px;font-weight:650;font-size:12px;white-space:nowrap}
        .hero{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--hero-start) 0%,var(--hero-mid) 55%,var(--hero-end) 100%);border:1px solid var(--brand-line);border-radius:16px;padding:26px 28px;margin-bottom:20px;color:var(--ink)}
        .hero::before{content:"";position:absolute;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.12),transparent 65%);top:-160px;right:-80px;pointer-events:none}
        .hero::after{content:"";position:absolute;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(14,165,233,.1),transparent 65%);bottom:-150px;left:-60px;pointer-events:none}
        .hero h2{position:relative;margin:0 0 6px;font-size:23px;font-weight:800;letter-spacing:-.02em}
        .hero p{position:relative;margin:0;color:var(--body);font-size:14px;max-width:620px}
        .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .grid > *,.grid2 > *,.grid3 > *{min-width:0}
        .card{background:var(--panel);border:1px solid var(--line);border-radius:var(--r-card);padding:18px;box-shadow:var(--shadow-card);transition:box-shadow .18s,border-color .18s}
        .card:hover{box-shadow:var(--shadow-hover);border-color:var(--line-strong)}
        .card h3{margin:0 0 12px;font-size:14px;font-weight:750;letter-spacing:-.01em}
        .metric{font-size:26px;font-weight:800;margin-top:6px;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .metric.small{font-size:20px}
        .muted{color:var(--muted)}
        .badge{display:inline-flex;align-items:center;border-radius:999px;padding:3px 9px;font-size:11.5px;font-weight:650;background:var(--brand-soft);color:var(--brand);border:1px solid var(--brand-line);white-space:nowrap}
        .badge.green{background:var(--green-soft);color:var(--green-ink);border-color:var(--green-line)}
        .badge.red{background:var(--red-soft);color:var(--red-ink);border-color:var(--red-line)}
        .badge.amber{background:var(--amber-soft);color:var(--amber-ink);border-color:var(--amber-line)}
        .section{margin-top:16px}
        .table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--line);border-radius:12px;background:var(--panel)}
        table{width:100%;border-collapse:collapse;min-width:760px}
        th,td{padding:11px 14px;border-bottom:1px solid var(--line);font-size:13.5px;text-align:left;vertical-align:top}
        th{background:var(--table-head);color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
        tbody tr:last-child td{border-bottom:0}
        tbody tr{transition:background .12s}
        tbody tr:hover{background:#fafbfc}
        input,select{width:100%;border:1px solid var(--line);background:var(--input);color:var(--ink);border-radius:var(--r-input);padding:8px 12px;margin:4px 0 10px;font-size:13.5px;font-family:inherit;transition:border-color .15s,box-shadow .15s}
        input::placeholder{color:#94a3b8}
        input:focus,select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.14)}
        .btn,button{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#0f172a;border:1px solid #0f172a;border-radius:var(--r-btn);color:#fff;padding:8px 14px;font-weight:650;font-size:13.5px;cursor:pointer;transition:background .15s,border-color .15s,box-shadow .15s,transform .1s}
        .btn:hover,button:hover{background:#020617;border-color:#020617;box-shadow:0 4px 12px rgba(15,23,42,.18)}
        .btn:active,button:active{transform:scale(.98)}
        .btn.secondary,button.secondary{background:var(--panel);color:var(--ink);border-color:var(--line-strong)}
        .btn.secondary:hover,button.secondary:hover{background:var(--soft);border-color:var(--line-strong);box-shadow:none}
        .btn.danger,button.danger{background:var(--red-soft);color:var(--red-ink);border-color:var(--red-line)}
        .btn.danger:hover,button.danger:hover{background:#fde3e3;border-color:var(--red-line);box-shadow:none}
        .key{background:#0f172a;color:#cbd5e1;border:1px solid #1e293b;border-radius:12px;padding:14px 16px;word-break:break-word;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.65;overflow-x:auto}
        .key code{font-family:inherit}
        .error{background:var(--red-soft);color:var(--red-ink);border:1px solid var(--red-line);padding:11px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .compact input,.compact select{margin-bottom:6px}

        /* ===== Pagination ===== */
        .pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:13px}
        .pg-info{color:var(--muted)}
        .pg-links{display:flex;align-items:center;gap:5px}
        .pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;border:1px solid var(--line);border-radius:8px;background:var(--panel);color:var(--body);font-weight:600;font-size:13px;transition:border-color .13s,color .13s,background .13s}
        a.pg-btn:hover{background:var(--soft);color:var(--ink);border-color:var(--line-strong)}
        .pg-btn.current{background:#0f172a;border-color:#0f172a;color:#fff}
        .pg-btn.disabled{opacity:.45;pointer-events:none}
        .pg-dots{color:var(--muted);padding:0 3px}

        /* ===== Responsive: desktop ≥1000px · tablet 640–999px · phone <640px ===== */
        @media (min-width:1000px){
            .sidebar{transform:translateX(0)}
            body.nav-collapsed .sidebar{transform:translateX(-106%)}
            .overlay{display:none}
        }
        @media (max-width:999.98px){ /* tablet + phone: drawer menu, konten full-width */
            .main{margin-left:0}
            .content{padding:20px 18px 40px}
            .top{display:block}
            .top .pill{margin-top:10px}
            .tb-brand{display:flex}
            .tb-title{display:none}
            .tb-user span{display:none}
            .tb-balance{display:none}
            .sidebar-close{display:flex}
            .topbar{padding:0 14px}
            .inbox-dropdown{right:14px;top:66px;max-height:calc(100dvh - 80px)}
            .pagination{justify-content:center}
        }
        @media (min-width:640px) and (max-width:1099.98px){ /* tablet: grid 2 kolom */
            .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .grid3{grid-template-columns:repeat(2,minmax(0,1fr))}
            .grid3 > :last-child:nth-child(odd){grid-column:1/-1}
        }
        @media (max-width:639.98px){ /* phone: semua 1 kolom, kontrol lebih besar */
            .grid,.grid2,.grid3{grid-template-columns:minmax(0,1fr)}
            table{min-width:640px}
            .form-row{grid-template-columns:1fr}
            .tb-user{display:none}
            .top h2{font-size:20px}
            .top p{font-size:13.5px}
            .metric{font-size:24px}
            .hero{padding:20px}
            .hero h2{font-size:20px}
            .card{padding:16px}
            .hamburger{width:40px;height:40px}
            .topbar{padding:0 12px}
            .inbox-dropdown{left:14px;right:14px;top:66px;width:auto;max-height:calc(100dvh - 80px)}
            .content{padding:18px 14px 36px}
            input,select{padding:10px 12px}
            .key{padding:12px 13px;font-size:12px}
            .pagination{flex-direction:column;align-items:center;gap:10px}
            .pg-info{text-align:center}
            .auth-card{padding:24px}
            .auth-bg{padding:16px}
            .table-wrap{border-radius:10px}
        }
        @media (prefers-reduced-motion:reduce){
            *,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto}
        }
    </style>
    <noscript><style>
        @media (max-width:999.98px){
            .sidebar{transform:none;position:static;width:auto;border-right:0;border-bottom:1px solid var(--line)}
            .main{margin-left:0}
            .sidebar-close{display:none}
            .topbar .hamburger{display:none}
        }
    </style></noscript>
</head>
<body>
@auth
<div class="app">
    <aside class="sidebar" id="sidebar" role="dialog" aria-modal="true" aria-label="{{ $isAdmin ? 'Menu navigasi' : __('dashboard.common.navigation_menu') }}">
        <div class="brand">
            <img class="logo platform-logo" src="{{ asset('azkia-logo.png') }}" alt="Logo Azkia Router">
            <div><h1>{{ $isAdmin ? 'Azkia Admin' : 'Azkia Router' }}</h1>@if($isAdmin)<p>Control Panel</p>@endif</div>
            <button class="sidebar-close" id="sidebar-close" aria-label="{{ $isAdmin ? 'Tutup menu' : __('dashboard.common.close_menu') }}">✕</button>
        </div>
        <nav class="nav">
            @if($isAdmin)
                <div class="nav-label">Management</div>
                <a class="{{ request()->routeIs('admin.index') ? 'active' : '' }}" href="{{ route('admin.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Dashboard</a>
                <a class="{{ request()->routeIs('admin.providers') ? 'active' : '' }}" href="{{ route('admin.providers') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Providers</a>
                <a class="{{ request()->routeIs('admin.models') ? 'active' : '' }}" href="{{ route('admin.models') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4"/><path d="M15 2v4"/><path d="M9 18v4"/><path d="M15 18v4"/><path d="M2 9h4"/><path d="M2 15h4"/><path d="M18 9h4"/><path d="M18 15h4"/><circle cx="12" cy="12" r="2.5"/></svg>{{ $isAdmin ? 'Models' : __('dashboard.nav.models') }}</a>
                <a class="{{ request()->routeIs('admin.pricing') ? 'active' : '' }}" href="{{ route('admin.pricing') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.5-6.2 4.5 2.4-7.4L2 9.4h7.6z"/></svg>Pricing</a>
                <a class="{{ request()->routeIs('admin.status') ? 'active' : '' }}" href="{{ route('admin.status') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>{{ $isAdmin ? 'Status' : __('dashboard.nav.status') }}</a>
                <a class="{{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Plans</a>
                <a class="{{ request()->routeIs('admin.keys') ? 'active' : '' }}" href="{{ route('admin.keys') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><path d="M10.5 12.5 20 3"/><path d="M15.5 7.5 18.5 10.5"/><path d="M18 4.5 20 6.5"/></svg>{{ $isAdmin ? 'API Keys' : __('dashboard.nav.keys') }}</a>
                <a class="{{ request()->routeIs('admin.payment-settings.*') ? 'active' : '' }}" href="{{ route('admin.payment-settings.edit') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>Payment Gateway</a>
                <a class="{{ request()->routeIs('admin.dashboard-popups.*') ? 'active' : '' }}" href="{{ route('admin.dashboard-popups.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h7M7 16h4"/></svg>Popup Dashboard</a>
                <a class="{{ request()->routeIs('admin.event') ? 'active' : '' }}" href="{{ route('admin.event') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>Event</a>
                <a class="{{ request()->routeIs('admin.redeem-codes.*') ? 'active' : '' }}" href="{{ route('admin.redeem-codes.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 9h8M8 13h5"/></svg>Kode Redeem</a>
                <a class="{{ request()->routeIs('admin.deposits.*') ? 'active' : '' }}" href="{{ route('admin.deposits.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Kelola Deposit</a>
                <a class="{{ request()->routeIs('admin.billing-monitoring.*') ? 'active' : '' }}" href="{{ route('admin.billing-monitoring.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg>Billing Monitoring</a>
                <a class="{{ request()->routeIs('admin.request-logs.*') ? 'active' : '' }}" href="{{ route('admin.request-logs.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>Request Logs</a>
                <a class="{{ request()->routeIs('admin.rejections') ? 'active' : '' }}" href="{{ route('admin.rejections') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg>Request Ditolak</a>
                <a class="{{ request()->routeIs('admin.support.*') ? 'active' : '' }}" href="{{ route('admin.support.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>Support Center</a>
                <a class="{{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Users</a>
                <div class="nav-label">Lainnya</div>
                <a class="{{ request()->routeIs('api-health') ? 'active' : '' }}" href="{{ route('api-health') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>API Health</a>
                <a href="https://azkia.cloud"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>User Site</a>
            @else
                <div class="nav-label">{{ __('dashboard.nav.section_main') }}</div>
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/></svg>{{ $isAdmin ? 'Overview' : __('dashboard.nav.overview') }}</a>
                <a class="{{ request()->routeIs('keys') ? 'active' : '' }}" href="{{ route('keys') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><path d="M10.5 12.5 20 3"/><path d="M15.5 7.5 18.5 10.5"/><path d="M18 4.5 20 6.5"/></svg>{{ $isAdmin ? 'API Keys' : __('dashboard.nav.keys') }}</a>
                <a class="{{ request()->routeIs('usage') ? 'active' : '' }}" href="{{ route('usage') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20v-6"/><path d="M12 20V8"/><path d="M18 20v-10"/><path d="M3 20h18"/></svg>{{ __('dashboard.nav.usage') }}</a>
                <div class="nav-label">{{ __('dashboard.nav.section_services') }}</div>
                <a class="{{ request()->routeIs('models') ? 'active' : '' }}" href="{{ route('models') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4"/><path d="M15 2v4"/><path d="M9 18v4"/><path d="M15 18v4"/><path d="M2 9h4"/><path d="M2 15h4"/><path d="M18 9h4"/><path d="M18 15h4"/><circle cx="12" cy="12" r="2.5"/></svg>{{ $isAdmin ? 'Models' : __('dashboard.nav.models') }}</a>
                <a class="{{ request()->routeIs('status') ? 'active' : '' }}" href="{{ route('status') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>{{ $isAdmin ? 'Status' : __('dashboard.nav.status') }}</a>
                <a class="{{ request()->routeIs('leaderboard') ? 'active' : '' }}" href="{{ route('leaderboard') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>{{ __('dashboard.nav.leaderboard') }}</a>
                <div class="nav-label">{{ __('dashboard.nav.section_billing') }}</div>
                <a class="{{ request()->routeIs('billing') ? 'active' : '' }}" href="{{ route('billing') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>{{ __('dashboard.nav.billing') }}</a>
                <a class="{{ request()->routeIs('plans') ? 'active' : '' }}" href="{{ route('plans') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>{{ __('dashboard.nav.plans') }}</a>
                <a class="{{ request()->routeIs('redeem-codes.*') ? 'active' : '' }}" href="{{ route('redeem-codes.create') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 9h8M8 13h5"/></svg>Redeem Code</a>
                <div class="nav-label">{{ __('dashboard.nav.section_help') }}</div>
                <a class="{{ request()->routeIs('support.*') ? 'active' : '' }}" href="{{ route('support.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>Support Center</a>
                <a class="{{ request()->routeIs('docs') ? 'active' : '' }}" href="{{ route('docs') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>{{ __('dashboard.nav.docs') }}</a>
                <a href="https://t.me/+CKiwOHJUP2ZmOWM9" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>{{ __('dashboard.nav.community') }}</a>
                <div class="nav-label">{{ __('dashboard.nav.section_account') }}</div>
                <a class="{{ request()->routeIs('referral') ? 'active' : '' }}" href="{{ route('referral') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>{{ __('dashboard.nav.referral') }}</a>
                <a class="{{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>{{ __('dashboard.nav.settings') }}</a>
                @php
                    $sidebarPlans = $user->activePlans()->with('plan')->get();
                @endphp
                @if($sidebarPlans->isNotEmpty())
                    <div class="nav-label">{{ __('dashboard.nav.plans_active') }}</div>
                    <div class="sidebar-plans">
                        @foreach($sidebarPlans as $sidebarPlan)
                        <a class="plan-mini" href="{{ route('plans') }}" title="{{ $sidebarPlan->plan?->name ?? '' }}">
                            <div class="plan-mini-head">
                                <strong>{{ $sidebarPlan->plan?->name ?? '—' }}</strong>
                                <span>{{ format_compact_number($sidebarPlan->remaining_tokens) }}</span>
                            </div>
                            @if($sidebarPlan->quota_tokens > 0)
                            <div class="plan-mini-bar"><div style="width:{{ $sidebarPlan->remaining_percent }}%"></div></div>
                            @endif
                        </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </nav>
        <form class="sidebar-logout" method="post" action="{{ route($isAdmin ? 'admin.logout' : 'logout') }}">@csrf<button class="danger-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>{{ $isAdmin ? 'Logout' : __('dashboard.nav.logout') }}</button></form>
        <div class="footer-user">
            <div class="logo">{{ $initials }}</div>
            <div class="fu-text"><strong>{{ $name }}</strong><span>{{ $user->email }}</span></div>
        </div>
    </aside>
    <div class="overlay" id="overlay" aria-hidden="true"></div>
    <div class="main">
        <header class="topbar">
            <button class="hamburger" id="nav-toggle" aria-label="{{ $isAdmin ? 'Buka menu' : __('dashboard.common.open_menu') }}" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
            <div class="tb-brand"><img class="logo sm platform-logo" src="{{ asset('azkia-logo.png') }}" alt="Logo Azkia Router"><span>{{ $isAdmin ? 'Azkia Admin' : 'Azkia Router' }}</span></div>
            <div class="tb-title">{{ $pageTitle }}</div>
            <div class="tb-right">
                @if(!$isAdmin)
                <form class="locale-switch" method="post" action="{{ route('locale.update') }}" aria-label="{{ __('dashboard.common.language') }}">
                    @csrf
                    <button type="submit" name="locale" value="id" class="{{ app()->getLocale() === 'id' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'id' ? 'true' : 'false' }}">ID</button>
                    <button type="submit" name="locale" value="en" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">EN</button>
                </form>
                @endif
                <button class="theme-toggle" id="theme-toggle" type="button" aria-label="{{ $isAdmin ? 'Ganti ke mode gelap' : __('dashboard.common.dark_mode') }}" title="{{ $isAdmin ? 'Ganti tema' : __('dashboard.common.theme') }}">
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/></svg>
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                </button>
                @if(!$isAdmin)
                <span class="tb-balance" title="{{ __('dashboard.topbar.active_balance') }}"><span class="tb-balance-label">{{ __('dashboard.topbar.balance') }}</span><strong>{{ format_idr_from_usd($user->balance) }}</strong><span class="tb-balance-usd">{{ format_usd($user->balance) }}</span></span>
                <div class="inbox-menu" id="inbox-menu">
                    <button class="inbox-btn" id="inbox-btn" type="button" aria-label="{{ __('dashboard.topbar.open_inbox') }}" aria-haspopup="true" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                        @if($unreadInboxCount > 0)<span class="inbox-count">{{ $unreadInboxCount > 99 ? '99+' : $unreadInboxCount }}</span>@endif
                    </button>
                    <div class="inbox-dropdown" id="inbox-dropdown" hidden>
                        <div class="inbox-head"><strong>{{ __('dashboard.pages.inbox.heading') }}</strong><a href="{{ route('inbox') }}">{{ __('dashboard.topbar.view_all') }}</a></div>
                        @forelse($inboxMessages as $message)
                        <a class="inbox-item {{ $message->read_at ? '' : 'unread' }}" href="{{ route('inbox') }}#message-{{ $message->id }}">
                            <strong>{{ $message->subject }}</strong>
                            <span>{{ $message->body }}</span>
                            <time>{{ $message->created_at?->locale(app()->getLocale())->diffForHumans() }}</time>
                        </a>
                        @empty
                        <div class="inbox-empty">{{ __('dashboard.topbar.empty_inbox') }}</div>
                        @endforelse
                        <a class="inbox-foot" href="{{ route('inbox') }}">{{ __('dashboard.topbar.open_inbox_link') }}</a>
                    </div>
                </div>
                @endif
                <div class="tb-user-menu" id="tb-user-menu">
                    <button class="tb-user-btn" id="tb-user-btn" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar">{{ $initials }}</div>
                        <div class="tb-user"><strong>{{ $name }}</strong><span>{{ $user->email }}</span></div>
                        <svg class="tb-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="tb-menu" id="tb-menu" role="menu" aria-label="{{ $isAdmin ? 'Menu akun' : __('dashboard.common.account_menu') }}" hidden>
                        <div class="tb-menu-head"><strong>{{ $name }}</strong><span>{{ $user->email }}</span></div>
                        <a class="tb-menu-item" href="{{ route($isAdmin ? 'admin.index' : 'dashboard') }}" role="menuitem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/></svg>{{ $isAdmin ? 'Overview' : __('dashboard.nav.overview') }}</a>
                        @if($isAdmin)
                        <a class="tb-menu-item" href="{{ route('admin.status') }}" role="menuitem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>{{ $isAdmin ? 'Status' : __('dashboard.nav.status') }}</a>
                        @endif
                        <div class="tb-menu-sep"></div>
                        <form method="post" action="{{ route($isAdmin ? 'admin.logout' : 'logout') }}">@csrf<button class="tb-menu-item tb-menu-danger" type="submit" role="menuitem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>{{ $isAdmin ? 'Logout' : __('dashboard.nav.logout') }}</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content">@yield('content')</main>
        @include('partials.legal-footer')
    </div>
</div>
@if(!$isAdmin && request()->routeIs('dashboard') && $dashboardPopup)
<div class="dashboard-popup {{ $dashboardPopup->type }}" id="dashboard-popup" data-popup-id="{{ $dashboardPopup->id }}" role="dialog" aria-modal="true" aria-labelledby="dashboard-popup-title" hidden>
    <div class="dashboard-popup-card">
        <button class="dashboard-popup-close" type="button" data-popup-close aria-label="Tutup popup">✕</button>
        <h2 id="dashboard-popup-title">{{ $dashboardPopup->title }}</h2>
        <div class="dashboard-popup-body">{!! $dashboardPopup->body !!}</div>
        <label class="dashboard-popup-preference"><input id="dashboard-popup-today" type="checkbox"> Jangan tampilkan lagi hari ini</label>
        <div class="dashboard-popup-actions">
            <button class="secondary" type="button" data-popup-close>Nanti</button>
            @if($dashboardPopup->button_text && $dashboardPopup->button_url)
            <a class="btn" href="{{ $dashboardPopup->button_url }}" data-popup-action>{{ $dashboardPopup->button_text }}</a>
            @endif
        </div>
    </div>
</div>
@endif
@else
@yield('content')
@include('partials.legal-footer')
@endauth
<script>
(function () {
    var popup = document.getElementById('dashboard-popup');
    if (!popup) return;
    var checkbox = document.getElementById('dashboard-popup-today');
    var storageKey = 'azkia-dashboard-popup-' + popup.getAttribute('data-popup-id');
    var today = new Date().toLocaleDateString('en-CA');
    try {
        if (localStorage.getItem(storageKey) === today) return;
        if (sessionStorage.getItem(storageKey) === 'action') {
            sessionStorage.removeItem(storageKey);
            return;
        }
    } catch (e) {}
    popup.hidden = false;
    document.body.style.overflow = 'hidden';

    function closePopup() {
        popup.hidden = true;
        document.body.style.overflow = '';
        if (checkbox && checkbox.checked) {
            try { localStorage.setItem(storageKey, today); } catch (e) {}
        }
    }

    popup.querySelectorAll('[data-popup-close]').forEach(function (button) { button.addEventListener('click', closePopup); });
    var action = popup.querySelector('[data-popup-action]');
    if (action) action.addEventListener('click', function () {
        if (checkbox && checkbox.checked) {
            try { localStorage.setItem(storageKey, today); } catch (e) {}
        } else {
            try { sessionStorage.setItem(storageKey, 'action'); } catch (e) {}
        }
        popup.hidden = true;
        document.body.style.overflow = '';
    });
    popup.addEventListener('click', function (event) { if (event.target === popup) closePopup(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !popup.hidden) closePopup(); });
})();

(function () {
    var toggle = document.getElementById('theme-toggle');
    if (!toggle) return;
    var lightLabel = @json($isAdmin ? 'Ganti ke mode terang' : __('dashboard.common.light_mode'));
    var darkLabel = @json($isAdmin ? 'Ganti ke mode gelap' : __('dashboard.common.dark_mode'));

    function sync(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        toggle.setAttribute('aria-label', theme === 'dark' ? lightLabel : darkLabel);
    }

    toggle.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('azkia-theme', next);
        sync(next);
    });
    sync(document.documentElement.getAttribute('data-theme') || 'light');
})();

(function () {
    var sidebar = document.getElementById('sidebar');
    var toggle = document.getElementById('nav-toggle');
    var overlay = document.getElementById('overlay');
    var closeBtn = document.getElementById('sidebar-close');
    var mq = window.matchMedia('(min-width:1000px)');

    function setDesktop(visible) {
        document.body.classList.toggle('nav-collapsed', !visible);
        syncIcon();
    }
    function setMobile(open) {
        document.body.classList.toggle('nav-open', open);
        syncIcon();
    }
    function syncIcon() {
        var visible = mq.matches
            ? !document.body.classList.contains('nav-collapsed')
            : document.body.classList.contains('nav-open');
        document.body.classList.toggle('menu-visible', visible);
        if (toggle) toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
    }
    function close() {
        if (mq.matches) { setDesktop(true); } else { setMobile(false); }
    }
    if (toggle) toggle.addEventListener('click', function () {
        if (mq.matches) {
            setDesktop(document.body.classList.contains('nav-collapsed'));
        } else {
            setMobile(!document.body.classList.contains('nav-open'));
        }
    });
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (overlay) overlay.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.querySelectorAll('.nav a, .nav button').forEach(function (el) {
        el.addEventListener('click', function () { if (!mq.matches) setMobile(false); });
    });
    if (mq.addEventListener) {
        mq.addEventListener('change', function (e) { e.matches ? setDesktop(true) : setMobile(false); });
    } else if (mq.addListener) {
        mq.addListener(function (e) { e.matches ? setDesktop(true) : setMobile(false); });
    }
    syncIcon();
})();

(function () {
    var wrap = document.getElementById('inbox-menu');
    var btn = document.getElementById('inbox-btn');
    var menu = document.getElementById('inbox-dropdown');
    if (!wrap || !btn || !menu) return;

    function setOpen(open) {
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(menu.hidden);
    });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();

(function () {
    // Dropdown menu akun di header (avatar): buka/tutup + klik di luar untuk menutup.
    var wrap = document.getElementById('tb-user-menu');
    var btn = document.getElementById('tb-user-btn');
    var menu = document.getElementById('tb-menu');
    if (!btn || !menu || !wrap) return;

    function setOpen(open) {
        menu.hidden = !open;
        wrap.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(menu.hidden);
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>
</body>
</html>
