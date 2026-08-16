<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('dashboard.landing.meta_title') }}</title>
    <meta name="description" content="{{ __('dashboard.landing.meta_description') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f8fafc;--panel:#ffffff;--ink:#0f172a;--body:#334155;--muted:#64748b;--line:#e2e8f0;--line-strong:#cbd5e1;--soft:#f1f5f9;--brand:#2563eb;--brand-hover:#1d4ed8;--brand-soft:#eff6ff;--brand-line:#bfdbfe;--r-btn:9px}
        *{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}
        body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased;font-size:15px;line-height:1.6}
        a{text-decoration:none;color:inherit}

        /* Nav */
        .site-nav{position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:24px;height:64px;padding:0 32px;max-width:1180px;margin:0 auto;background:rgba(248,250,252,.85);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
        .nav-logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:16px;letter-spacing:-.01em}
        .logo{width:32px;height:32px;object-fit:contain;background:transparent}
        .nav-links{display:flex;align-items:center;gap:22px;margin-left:auto}
        .nav-links a{color:var(--muted);font-size:14px;font-weight:600;transition:color .15s}
        .nav-links a:hover{color:var(--ink)}
        .nav-actions{display:flex;align-items:center;gap:10px}
        .locale-switch{display:inline-flex;align-items:center;background:var(--panel);border:1px solid var(--line-strong);border-radius:9px;padding:2px;gap:2px}
        .locale-switch button{background:transparent;border:0;color:var(--muted);padding:4px 7px;border-radius:6px;font-size:10.5px;font-weight:700;cursor:pointer;transition:background .15s,color .15s}
        .locale-switch button.active{background:var(--soft);color:var(--ink)}

        /* Buttons */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:var(--r-btn);padding:9px 16px;font-weight:650;font-size:14px;cursor:pointer;transition:background .15s,border-color .15s,box-shadow .15s,transform .1s;border:1px solid transparent}
        .btn:active{transform:scale(.98)}
        .btn.primary{background:#0f172a;color:#fff}
        .btn.primary:hover{background:#020617;box-shadow:0 6px 16px rgba(15,23,42,.22)}
        .btn.blue{background:var(--brand);color:#fff}
        .btn.blue:hover{background:var(--brand-hover);box-shadow:0 6px 16px rgba(37,99,235,.3)}
        .btn.ghost{background:var(--panel);color:var(--ink);border-color:var(--line-strong)}
        .btn.ghost:hover{background:var(--soft)}
        .btn.lg{padding:12px 22px;font-size:15px}

        /* Hero */
        .hero{position:relative;max-width:1180px;margin:0 auto;padding:88px 32px 60px;text-align:center;overflow:hidden}
        .hero::before{content:"";position:absolute;inset:0;background:radial-gradient(600px 320px at 50% -80px,rgba(37,99,235,.12),transparent 70%);pointer-events:none}
        .hero-badge{display:inline-flex;align-items:center;gap:7px;background:var(--brand-soft);border:1px solid var(--brand-line);color:var(--brand);border-radius:999px;padding:5px 13px;font-size:12.5px;font-weight:700;margin-bottom:22px;position:relative}
        .hero-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--brand)}
        .hero h1{margin:0;font-size:52px;line-height:1.08;font-weight:800;letter-spacing:-.035em;position:relative}
        .hero h1 .grad{background:linear-gradient(120deg,var(--brand),#0ea5e9);-webkit-background-clip:text;background-clip:text;color:transparent}
        .hero-sub{margin:20px auto 0;max-width:560px;color:var(--body);font-size:17px;position:relative}
        .hero-cta{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:32px;flex-wrap:wrap;position:relative}
        .hero-meta{margin-top:26px;color:var(--muted);font-size:13px;display:flex;align-items:center;justify-content:center;gap:18px;flex-wrap:wrap;position:relative}
        .hero-meta span{display:inline-flex;align-items:center;gap:6px}
        .hero-meta svg{width:15px;height:15px;color:var(--green)}

        /* Code card */
        .code-card{max-width:620px;margin:44px auto 0;text-align:left;background:#0f172a;border:1px solid #1e293b;border-radius:14px;overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,.25);position:relative}
        .code-head{display:flex;align-items:center;gap:7px;padding:12px 16px;background:#111c2e;border-bottom:1px solid #1e293b}
        .dot{width:11px;height:11px;border-radius:50%}
        .dot.r{background:#f87171}.dot.y{background:#fbbf24}.dot.g{background:#34d399}
        .code-name{margin-left:10px;color:#64748b;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
        .code-card pre{margin:0;padding:20px 22px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.7;color:#cbd5e1;overflow-x:auto}
        .code-card pre .c{color:#475569}
        .code-card pre .s{color:#7dd3fc}
        .code-card pre .k{color:#c4b5fd}
        .code-card pre .f{color:#93c5fd}
        .code-card pre .n{color:#a5b4fc}

        /* Sections */
        .section{max-width:1180px;margin:0 auto;padding:70px 32px}
        .sec-head{text-align:center;margin-bottom:44px}
        .sec-head h2{margin:0;font-size:32px;font-weight:800;letter-spacing:-.03em}
        .sec-head p{margin:10px auto 0;max-width:520px;color:var(--muted);font-size:15.5px}
        .features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .feature{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:0 1px 2px rgba(15,23,42,.05);transition:box-shadow .18s,border-color .18s,transform .18s}
        .feature:hover{box-shadow:0 12px 28px rgba(15,23,42,.09);border-color:var(--line-strong);transform:translateY(-2px)}
        .feature .ic{width:38px;height:38px;border-radius:10px;background:var(--brand-soft);border:1px solid var(--brand-line);color:var(--brand);display:grid;place-items:center;margin-bottom:14px}
        .feature .ic svg{width:19px;height:19px}
        .feature h3{margin:0 0 6px;font-size:15.5px;font-weight:750;letter-spacing:-.01em}
        .feature p{margin:0;color:var(--muted);font-size:13.5px}

        /* Steps */
        .steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;counter-reset:step}
        .step{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:24px;position:relative}
        .step::before{counter-increment:step;content:"0" counter(step);position:absolute;top:18px;right:20px;font-size:28px;font-weight:800;letter-spacing:-.03em;color:#e2e8f0}
        .step h3{margin:0 0 6px;font-size:15.5px;font-weight:750}
        .step p{margin:0;color:var(--muted);font-size:13.5px}
        .step code{background:var(--soft);border:1px solid var(--line);border-radius:6px;padding:1px 6px;font-size:12.5px;color:var(--ink);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}

        /* API section */
        .api-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}
        .api-col,.code-card{min-width:0}
        .api-col p{color:var(--muted);font-size:14px;margin:10px 0 18px}
        .endpoints{background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden}
        .endpoint{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--line);font-size:13.5px}
        .endpoint:last-child{border-bottom:0}
        .method{font-family:ui-monospace,monospace;font-size:11px;font-weight:800;letter-spacing:.04em;padding:3px 8px;border-radius:6px}
        .method.get{background:var(--green-soft,#f0fdf4);color:#15803d;border:1px solid #bbf7d0}
        .method.post{background:var(--brand-soft);color:var(--brand);border:1px solid var(--brand-line)}
        .path{font-family:ui-monospace,monospace;font-weight:600;color:var(--ink)}
        .desc{margin-left:auto;color:var(--muted);font-size:12.5px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        /* CTA */
        .cta{max-width:1180px;margin:0 auto;padding:0 32px 80px}
        .cta-inner{background:#0f172a;border-radius:20px;padding:56px 40px;text-align:center;color:#fff;position:relative;overflow:hidden}
        .cta-inner::before{content:"";position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.45),transparent 65%);top:-180px;right:-80px;pointer-events:none}
        .cta-inner h2{margin:0 0 10px;font-size:30px;font-weight:800;letter-spacing:-.03em;position:relative}
        .cta-inner p{margin:0 auto 28px;max-width:440px;color:#94a3b8;font-size:15px;position:relative}
        .btn.white{background:#fff;color:#0f172a}
        .btn.white:hover{background:#e2e8f0;box-shadow:0 8px 20px rgba(255,255,255,.2)}

        /* Footer */
        .footer{border-top:1px solid var(--line);background:var(--panel)}
        .foot-inner{max-width:1180px;margin:0 auto;padding:26px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--muted)}
        .foot-links{display:flex;gap:18px}
        .foot-links a{font-weight:600;transition:color .15s}
        .foot-links a:hover{color:var(--ink)}
        .legal-footer{border-top:1px solid var(--line);background:var(--panel);color:var(--muted);font-size:13px}
        .legal-footer-inner{max-width:1180px;margin:0 auto;padding:26px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .legal-footer nav{display:flex;gap:18px;flex-wrap:wrap}
        .legal-footer a{font-weight:600;text-decoration:none;color:var(--muted)}
        .legal-footer a:hover{color:var(--ink)}

        @media (max-width:900px){ /* tablet + phone */
            .nav-links{display:none}
            .hero h1{font-size:38px}
            .api-grid{grid-template-columns:minmax(0,1fr)}
            .section{padding:50px 20px}
            .hero{padding:64px 20px 44px}
        }
        /* Models grid (landing) */
        .models-wrap{background:var(--panel);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
        .models-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .model-card{position:relative;display:flex;flex-direction:column;background:var(--bg);border:1px solid var(--line);border-radius:14px;padding:16px;transition:box-shadow .18s,border-color .18s,transform .18s;min-width:0}
        .model-card:hover{box-shadow:0 12px 28px rgba(15,23,42,.09);border-color:var(--line-strong);transform:translateY(-2px)}
        .model-head{display:flex;align-items:center;gap:10px;margin-bottom:8px;min-width:0}
        .model-ic{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto;overflow:hidden;border:1px solid var(--brand-line);background:var(--brand-soft);color:var(--brand)}
        .model-ic.has-img{background:var(--panel);border-color:var(--line);padding:4px}
        .model-ic.has-img img{width:100%;height:100%;object-fit:contain;display:block;border-radius:7px}
        .model-ic svg{width:17px;height:17px}
        .model-ic.t-embedding{background:var(--green-soft,#f0fdf4);color:#15803d;border-color:#bbf7d0}
        .model-ic.t-completion{background:#fffbeb;color:#b45309;border-color:#fde68a}
        .model-ic.t-other{background:var(--soft);color:var(--muted);border-color:var(--line)}
        .model-name{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13.5px;font-weight:750;letter-spacing:-.01em;color:var(--ink);word-break:break-all;flex:1;min-width:0}
        .model-caps{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
        .model-cap{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:700;background:var(--brand-soft);color:var(--brand);border:1px solid var(--brand-line)}
        .model-cap svg{width:11px;height:11px;display:block}
        .model-cap.c-completion{background:#fffbeb;color:#b45309;border-color:#fde68a}
        .model-cap.c-embedding{background:var(--green-soft,#f0fdf4);color:#15803d;border-color:#bbf7d0}
        .model-cap.c-tool{background:#f5f3ff;color:#7c3aed;border-color:#ddd6fe}
        .model-cap.c-other{background:var(--soft);color:var(--muted);border-color:var(--line)}
        .model-context{display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid var(--line)}
        .model-context strong{font-variant-numeric:tabular-nums;color:var(--body)}
        .model-price{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:auto}
        .model-price .pb{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:6px 9px}
        .model-price .pb .l{display:block;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:2px}
        .model-price .pb .v{font-size:12px;font-weight:750;font-variant-numeric:tabular-nums}
        .model-price .pb .original{display:block;color:var(--muted);font-size:10px;text-decoration:line-through;font-weight:600}
        .models-cta{margin-top:30px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap}
        .model-card-hidden{display:none}
        /* Plans section (landing) */
        .plans-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:stretch}
        .plan-card{position:relative;display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(15,23,42,.05);transition:box-shadow .18s,border-color .18s,transform .18s;min-width:0}
        .plan-card:hover{box-shadow:0 12px 28px rgba(15,23,42,.09);border-color:var(--line-strong);transform:translateY(-2px)}
        .plan-card h3{margin:0 0 4px;font-size:17px;font-weight:800;letter-spacing:-.02em;padding-right:56px}
        .plan-card .plan-desc{margin:0 0 14px;color:var(--muted);font-size:13px;min-height:38px}
        .plan-card .plan-models{margin:0 0 12px;font-size:11.5px;color:var(--muted);line-height:1.5}
        .plan-card .plan-models strong{color:var(--body)}
        .plan-metric{display:flex;justify-content:space-between;align-items:baseline;gap:10px;padding:7px 0;border-top:1px dashed var(--line);font-size:13px}
        .plan-metric span{color:var(--muted);font-size:12px}
        .plan-metric strong{font-size:13px;font-variant-numeric:tabular-nums}
        .plan-price{margin:auto 0 0;padding-top:14px}
        .plan-price .usd{font-size:24px;font-weight:800;letter-spacing:-.02em}
        .plan-price .idr{color:var(--muted);font-size:12.5px;margin-top:1px}
        .plan-price .plan-btn{width:100%;margin-top:12px}
        .plan-badge{position:absolute;top:16px;right:16px;display:inline-flex;align-items:center;gap:5px;background:var(--brand-soft);border:1px solid var(--brand-line);color:var(--brand);border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;letter-spacing:.02em}
        .plan-card.free{background:linear-gradient(180deg,var(--brand-soft),var(--panel));border-color:var(--brand-line)}
        .plan-card.free .usd{color:var(--brand)}
        .plan-card.free .plan-btn{background:var(--brand);color:#fff}
        .plan-card.free .plan-btn:hover{background:var(--brand-hover);box-shadow:0 6px 16px rgba(37,99,235,.3)}
        .payg-note{margin-top:26px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;color:var(--muted);font-size:13px;text-align:center}
        .payg-note strong{color:var(--ink)}
        .payg-note svg{width:15px;height:15px;color:var(--brand);flex:0 0 auto}
        .plans-empty{grid-column:1/-1;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:22px;text-align:center;color:var(--muted)}
        /* Tools section (landing) */
        .tools-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .tool-card{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.05);transition:box-shadow .18s,border-color .18s,transform .18s;min-width:0}
        .tool-card:hover{box-shadow:0 12px 28px rgba(15,23,42,.09);border-color:var(--line-strong);transform:translateY(-2px)}
        .tool-name{font-weight:750;font-size:14.5px;letter-spacing:-.01em;color:var(--ink)}
        .tool-cat{font-size:11.5px;font-weight:600;color:var(--muted);background:var(--soft);border:1px solid var(--line);border-radius:999px;padding:3px 10px;white-space:nowrap;flex:0 0 auto}
        @media (min-width:641px) and (max-width:900px){ /* tablet: 2 kolom */
            .features{grid-template-columns:repeat(2,minmax(0,1fr))}
            .steps{grid-template-columns:repeat(2,minmax(0,1fr))}
            .models-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .plans-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .tools-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
        @media (max-width:640px){ /* phone: 1 kolom */
            .site-nav{padding:0 14px}
            .hero h1{font-size:31px}
            .hero-sub{font-size:15.5px}
            .hero-cta{flex-direction:column;width:100%}
            .hero-cta .btn{width:100%}
            .hero-meta{flex-direction:column;gap:8px}
            .features,.steps,.models-grid,.plans-grid,.tools-grid{grid-template-columns:minmax(0,1fr)}
            .sec-head h2{font-size:26px}
            .code-card pre{font-size:12px;padding:16px}
            .code-name{display:none}
            .cta-inner{padding:44px 22px}
            .nav-actions .btn{padding:8px 12px;font-size:13px}
            .foot-inner{flex-direction:column;align-items:center;text-align:center}
        }
    </style>
</head>
<body>
<header class="site-nav">
    <a class="nav-logo" href="/"><img class="logo" src="{{ asset('azkia-logo.png') }}" alt="{{ __('dashboard.landing.logo_alt') }}">Azkia Router</a>
    <nav class="nav-links">
        <a href="#models">{{ __('dashboard.landing.nav.models') }}</a>
        <a href="#features">{{ __('dashboard.landing.nav.features') }}</a>
        <a href="#how">{{ __('dashboard.landing.nav.how') }}</a>
        <a href="#plans">{{ __('dashboard.landing.nav.pricing') }}</a>
        <a href="#tools">{{ __('dashboard.landing.nav.tools') }}</a>
        <a href="#api">{{ __('dashboard.landing.nav.api') }}</a>
        <a href="#payment">{{ __('dashboard.landing.nav.payment') }}</a>
    </nav>
    <div class="nav-actions">
        <form class="locale-switch" method="post" action="{{ route('locale.update') }}" aria-label="{{ __('dashboard.landing.language') }}">
            @csrf
            <button type="submit" name="locale" value="id" class="{{ app()->getLocale() === 'id' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'id' ? 'true' : 'false' }}">ID</button>
            <button type="submit" name="locale" value="en" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">EN</button>
        </form>
        <a class="btn ghost" href="{{ route('login') }}">{{ __('dashboard.landing.login') }}</a>
        <a class="btn primary" href="{{ route('register') }}">{{ __('dashboard.landing.get_started') }}</a>
    </div>
</header>

<main>
    <section class="hero">
        <span class="hero-badge">{{ __('dashboard.landing.hero_badge') }}</span>
        <h1>{{ __('dashboard.landing.hero_title_1') }}<br><span class="grad">{{ __('dashboard.landing.hero_title_2') }}</span></h1>
        <p class="hero-sub">{{ __('dashboard.landing.hero_sub') }}</p>
        <div class="hero-cta">
            <a class="btn blue lg" href="{{ route('register') }}">{{ __('dashboard.landing.cta_start') }}</a>
            <a class="btn ghost lg" href="#api">{{ __('dashboard.landing.cta_docs') }}</a>
        </div>
        <div class="hero-meta">
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>{{ __('dashboard.landing.meta_payg') }}</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>{{ __('dashboard.landing.meta_topup') }}</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>{{ __('dashboard.landing.meta_sdk') }}</span>
        </div>
        <div class="code-card">
            <div class="code-head"><span class="dot r"></span><span class="dot y"></span><span class="dot g"></span><span class="code-name">python · quickstart.py</span></div>
            <pre><span class="c">{{ __('dashboard.landing.code_comment') }}</span>
<span class="k">from</span> openai <span class="k">import</span> OpenAI

client = OpenAI(
    base_url=<span class="s">"https://api.azkia.cloud/v1"</span>,
    api_key=<span class="s">"azkia_xxxxxxxx"</span>,
)

response = client.chat.completions.create(
    model=<span class="s">"azkia/fast"</span>,
    messages=[{<span class="s">"role"</span>: <span class="s">"user"</span>, <span class="s">"content"</span>: <span class="s">"Hello!"</span>}],
)
<span class="n">print</span>(response.choices[<span class="f">0</span>].message.content)</pre>
        </div>
    </section>

    <section class="models-wrap" id="models">
        <div class="section">
            <div class="sec-head">
                <h2>{{ __('dashboard.landing.models.heading') }}</h2>
                <p>{{ __('dashboard.landing.models.sub') }}</p>
            </div>
            <div class="models-grid">
                @forelse($models as $model)
                    @php
                        $type = strtolower($model->type ?? 'other');
                        $typeClass = match ($type) {
                            'chat' => '',
                            'embedding' => 't-embedding',
                            'completion' => 't-completion',
                            default => 't-other',
                        };
                        $rule = $model->latestPricingRule;
                        $inPrice = $rule ? format_idr_usd($rule->effective_input_price) : null;
                        $outPrice = $rule ? format_idr_usd($rule->effective_output_price) : null;
                        $originalInPrice = $rule?->original_input_per_million !== null ? format_idr_usd($rule->original_input_per_million) : null;
                        $originalOutPrice = $rule?->original_output_per_million !== null ? format_idr_usd($rule->original_output_per_million) : null;
                        $isPromo = (bool) ($rule?->promo_is_active ?? false);
                        $caps = collect($model->capabilities ?: [$type])->map(fn ($c) => strtolower((string) $c))->filter(fn ($c) => $c !== '')->unique()->values();
                    @endphp
                    <div class="model-card {{ $loop->index >= 5 ? 'model-card-hidden' : '' }}">
                        <div class="model-head">
                            <div class="model-ic {{ $typeClass }} {{ $model->icon_url ? 'has-img' : '' }}">
                                @if($model->icon_url)
                                    <img src="{{ $model->icon_url }}" alt="{{ __('dashboard.landing.models.icon_alt', ['model' => $model->public_name]) }}" loading="lazy">
                                @elseif($type === 'embedding')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                                @elseif($type === 'completion')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h10"/><path d="M4 18h7"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                @endif
                            </div>
                            <div class="model-name">{{ $model->public_name }}</div>
                        </div>
                        @if($caps->isNotEmpty())
                        <div class="model-caps">
                            @foreach($caps as $cap)
                                <span class="model-cap c-{{ in_array($cap, ['chat','completion','embedding','tool'], true) ? $cap : 'other' }}">{!! capability_icon($cap) !!}</span>
                            @endforeach
                        </div>
                        @endif
                        @if($model->context_window)
                        <div class="model-context"><span>{{ __('dashboard.landing.models.context') }}</span><strong>{{ __('dashboard.landing.models.tokens', ['count' => format_compact_number($model->context_window)]) }}</strong></div>
                        @endif
                        <div class="model-price">
                            <div class="pb"><span class="l">{{ __('dashboard.landing.models.input') }}</span>@if($isPromo && $originalInPrice)<span class="original">{{ $originalInPrice }}</span>@endif<span class="v">{{ $inPrice ?? '—' }}</span></div>
                            <div class="pb"><span class="l">{{ __('dashboard.landing.models.output') }}</span>@if($isPromo && $originalOutPrice)<span class="original">{{ $originalOutPrice }}</span>@endif<span class="v">{{ $outPrice ?? '—' }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <h3>{{ __('dashboard.landing.models.empty') }}</h3>
                        <p>{{ __('dashboard.landing.models.empty_hint') }}</p>
                    </div>
                @endforelse
            </div>
            <div class="models-cta">
                @if($models->count() > 5)
                <button class="btn ghost lg" id="models-toggle" type="button">{{ __('dashboard.landing.models.show_all', ['count' => $models->count()]) }}</button>
                @endif
                <a class="btn blue lg" href="{{ route('register') }}">{{ __('dashboard.landing.models.get_access') }}</a>
            </div>
        </div>
    </section>

    <section class="section" id="plans">
        <div class="sec-head">
            <h2>{{ __('dashboard.landing.plans.heading') }}</h2>
            <p>{{ __('dashboard.landing.plans.sub') }}</p>
        </div>
        <div class="plans-grid">
            @if($freePlan)
            <div class="plan-card free">
                <span class="plan-badge">{{ __('dashboard.landing.plans.badge_free') }}</span>
                <h3>{{ $freePlan->name }}</h3>
                <p class="plan-desc">{{ $freePlan->description }}</p>
                <div class="plan-metric"><span>{{ __('dashboard.landing.plans.daily_quota') }}</span><strong>{{ __('dashboard.landing.models.tokens', ['count' => format_compact_number($freePlan->daily_limit_tokens ?? $freePlan->total_tokens)]) }}</strong></div>
                <div class="plan-metric"><span>{{ __('dashboard.landing.plans.reset') }}</span><strong>{{ __('dashboard.landing.plans.reset_daily') }}</strong></div>
                <div class="plan-metric"><span>{{ __('dashboard.landing.plans.price') }}</span><strong>Rp 0</strong></div>
                <div class="plan-price">
                    <div class="usd">{{ __('dashboard.landing.plans.free') }}</div>
                    <div class="idr">{{ __('dashboard.landing.plans.free_hint') }}</div>
                    <a class="btn plan-btn" href="{{ route('register') }}">{{ __('dashboard.landing.plans.register_free') }}</a>
                </div>
            </div>
            @endif
            @forelse($plans as $plan)
            <div class="plan-card">
                <h3>{{ $plan->name }}</h3>
                <p class="plan-desc">{{ $plan->description }}</p>
                @if($plan->models->isNotEmpty())
                <p class="plan-models"><strong>{{ __('dashboard.landing.plans.models_label') }}</strong> {{ $plan->models->take(3)->pluck('public_name')->join(', ') }}@if($plan->models->count() > 3) +{{ $plan->models->count() - 3 }}@endif</p>
                @endif
                <div class="plan-metric"><span>{{ __('dashboard.landing.plans.total_quota') }}</span><strong>{{ __('dashboard.landing.models.tokens', ['count' => $plan->tokens_label]) }}</strong></div>
                @if($plan->daily_limit_label)<div class="plan-metric"><span>{{ __('dashboard.landing.plans.daily_limit') }}</span><strong>{{ $plan->daily_limit_label }}</strong></div>@endif
                @if($plan->rate_limit_per_minute)<div class="plan-metric"><span>{{ __('dashboard.landing.plans.rate_limit') }}</span><strong>{{ __('dashboard.landing.plans.rate_per_min', ['count' => $plan->rate_limit_per_minute]) }}</strong></div>@endif
                <div class="plan-metric"><span>{{ __('dashboard.landing.plans.valid') }}</span><strong>{{ $plan->duration_label }}</strong></div>
                @if($plan->stock !== null)
                <div class="plan-metric"><span>{{ __('dashboard.landing.plans.stock') }}</span><strong @if($plan->is_sold_out) style="color:#dc2626" @endif>{{ $plan->is_sold_out ? __('dashboard.landing.plans.sold_out') : __('dashboard.landing.plans.stock_left', ['count' => number_format($plan->stock, 0, ',', '.')]) }}</strong></div>
                @endif
                <div class="plan-price">
                    <div class="usd">{{ format_usd($plan->price_usd) }}</div>
                    @if($plan->price_idr)<div class="idr">≈ Rp {{ number_format($plan->price_idr, 0, ',', '.') }}</div>@endif
                    <a class="btn blue plan-btn" href="{{ route('register') }}">{{ __('dashboard.landing.plans.buy') }}</a>
                </div>
            </div>
            @empty
            <div class="plans-empty">{{ __('dashboard.landing.plans.empty') }}</div>
            @endforelse
        </div>
        <div class="payg-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l2.5 2.5"/></svg>
            <span>{!! __('dashboard.landing.plans.payg_note') !!}</span>
        </div>
    </section>

    <section class="section" id="features">
        <div class="sec-head">
            <h2>{{ __('dashboard.landing.features.heading') }}</h2>
            <p>{{ __('dashboard.landing.features.sub') }}</p>
        </div>
        <div class="features">
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg></div>
                <h3>{{ __('dashboard.landing.features.f1.t') }}</h3>
                <p>{{ __('dashboard.landing.features.f1.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div>
                <h3>{{ __('dashboard.landing.features.f2.t') }}</h3>
                <p>{{ __('dashboard.landing.features.f2.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l2.5 2.5"/><circle cx="12" cy="12" r="9"/></svg></div>
                <h3>{{ __('dashboard.landing.features.f3.t') }}</h3>
                <p>{{ __('dashboard.landing.features.f3.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
                <h3>{{ __('dashboard.landing.features.f4.t') }}</h3>
                <p>{{ __('dashboard.landing.features.f4.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20v-6"/><path d="M12 20V8"/><path d="M18 20v-10"/><path d="M3 20h18"/></svg></div>
                <h3>{{ __('dashboard.landing.features.f5.t') }}</h3>
                <p>{{ __('dashboard.landing.features.f5.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"/><path d="M7.5 7.5a6.36 6.36 0 0 0 0 9"/><path d="M16.5 7.5a6.36 6.36 0 0 1 0 9"/><path d="M4.9 4.9a10.9 10.9 0 0 0 0 14.2"/><path d="M19.1 4.9a10.9 10.9 0 0 1 0 14.2"/></svg></div>
                <h3>{{ __('dashboard.landing.features.f6.t') }}</h3>
                <p>{{ __('dashboard.landing.features.f6.d') }}</p>
            </div>
        </div>
    </section>

    <section class="section" id="how" style="background:var(--panel);border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
        <div class="sec-head">
            <h2>{{ __('dashboard.landing.steps.heading') }}</h2>
            <p>{{ __('dashboard.landing.steps.sub') }}</p>
        </div>
        <div class="steps">
            <div class="step"><h3>{{ __('dashboard.landing.steps.s1.t') }}</h3><p>{{ __('dashboard.landing.steps.s1.d') }}</p></div>
            <div class="step"><h3>{{ __('dashboard.landing.steps.s2.t') }}</h3><p>{{ __('dashboard.landing.steps.s2.d') }}</p></div>
            <div class="step"><h3>{{ __('dashboard.landing.steps.s3.t') }}</h3><p>{!! __('dashboard.landing.steps.s3.d') !!}</p></div>
        </div>
    </section>

    <section class="section" id="payment">
        <div class="sec-head">
            <h2>{{ __('dashboard.landing.payment.heading') }}</h2>
            <p>{{ __('dashboard.landing.payment.sub') }}</p>
        </div>
        <div class="features">
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div>
                <h3>{{ __('dashboard.landing.payment.f1.t') }}</h3>
                <p>{{ __('dashboard.landing.payment.f1.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/><circle cx="12" cy="12" r="9"/></svg></div>
                <h3>{{ __('dashboard.landing.payment.f2.t') }}</h3>
                <p>{{ __('dashboard.landing.payment.f2.d') }}</p>
            </div>
            <div class="feature">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
                <h3>{{ __('dashboard.landing.payment.f3.t') }}</h3>
                <p>{{ __('dashboard.landing.payment.f3.d') }}</p>
            </div>
        </div>
    </section>

    <section class="section" id="api">
        <div class="sec-head">
            <h2>{{ __('dashboard.landing.api.heading') }}</h2>
            <p>{{ __('dashboard.landing.api.sub') }}</p>
        </div>
        <div class="api-grid">
            <div class="api-col">
                <div class="code-card">
                    <div class="code-head"><span class="dot r"></span><span class="dot y"></span><span class="dot g"></span><span class="code-name">bash · request.sh</span></div>
                    <pre>curl https://api.azkia.cloud/v1/chat/completions \
  -H <span class="s">"Authorization: Bearer azkia_xxxxx"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{
    "model": "azkia/fast",
    "messages": [{"role": "user",
                  "content": "Hello!"}]
  }'</span></pre>
                </div>
            </div>
            <div class="api-col">
                <p>{{ __('dashboard.landing.api.intro') }}</p>
                <div class="endpoints">
                    <div class="endpoint"><span class="method get">GET</span><span class="path">/v1/models</span><span class="desc">{{ __('dashboard.landing.api.d_models') }}</span></div>
                    <div class="endpoint"><span class="method post">POST</span><span class="path">/v1/chat/completions</span><span class="desc">{{ __('dashboard.landing.api.d_chat') }}</span></div>
                    <div class="endpoint"><span class="method post">POST</span><span class="path">/v1/completions</span><span class="desc">{{ __('dashboard.landing.api.d_text') }}</span></div>
                    <div class="endpoint"><span class="method post">POST</span><span class="path">/v1/embeddings</span><span class="desc">{{ __('dashboard.landing.api.d_embedding') }}</span></div>
                </div>
                <p style="margin-top:16px">{!! __('dashboard.landing.api.docs_hint') !!}</p>
            </div>
        </div>
    </section>

    <section class="section" id="tools">
        <div class="sec-head">
            <h2>{{ __('dashboard.landing.tools.heading') }}</h2>
        </div>
        <div class="tools-grid">
            <div class="tool-card"><span class="tool-name">OpenCode</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_agent') }}</span></div>
            <div class="tool-card"><span class="tool-name">Aider</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_agent') }}</span></div>
            <div class="tool-card"><span class="tool-name">Cline</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_vscode') }}</span></div>
            <div class="tool-card"><span class="tool-name">Roo Code</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_vscode') }}</span></div>
            <div class="tool-card"><span class="tool-name">Continue</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_ide') }}</span></div>
            <div class="tool-card"><span class="tool-name">Cherry Studio</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_desktop') }}</span></div>
            <div class="tool-card"><span class="tool-name">Chatbox</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_chat') }}</span></div>
            <div class="tool-card"><span class="tool-name">Open WebUI</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_web') }}</span></div>
            <div class="tool-card"><span class="tool-name">LibreChat</span><span class="tool-cat">{{ __('dashboard.landing.tools.cat_web') }}</span></div>
        </div>
        <div class="models-cta">
            <a class="btn blue lg" href="{{ route('register') }}">{{ __('dashboard.landing.tools.cta') }}</a>
        </div>
    </section>

    <section class="cta">
        <div class="cta-inner">
            <h2>{{ __('dashboard.landing.cta.heading') }}</h2>
            <p>{{ __('dashboard.landing.cta.sub') }}</p>
            <a class="btn white lg" href="{{ route('register') }}">{{ __('dashboard.landing.cta.button') }}</a>
        </div>
    </section>
</main>

@include('partials.legal-footer')
<script>
(function () {
    var toggle = document.getElementById('models-toggle');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
        var hidden = document.querySelectorAll('.model-card-hidden');
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        hidden.forEach(function (card) { card.classList.toggle('model-card-hidden', expanded); });
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        toggle.textContent = expanded ? @json(__('dashboard.landing.models.show_all', ['count' => '__COUNT__'])).replace('__COUNT__', hidden.length) : @json(__('dashboard.landing.models.show_less'));
    });
})();
</script>
</body>
</html>
