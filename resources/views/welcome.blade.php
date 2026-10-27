<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $firmName }} · Virtual Law Firm</title>
<meta name="description" content="{{ $firmName }} runs its legal work online: matters, court dates, deadlines, documents, time and billing, with a private portal for every client.">
<link rel="icon" href="{{ asset('favicon.ico') }}">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1C2B2B;--ink-m:#2A3E3E;--parch:#F2EDE4;--parch-d:#E8E1D6;--white:#fff;
    --vd:#3F5E46;--vu:#5A7D61;--vp:#EDF1EC;--slate:#8A9BA8;--muted:#5B6B6B;
    --ember:#B85C2A;--ember-p:#FBF0EB;--gold:#8B7335;--gold-p:#F7F2E5;--sky:#2563A8;--sky-p:#EBF2FC;
    --serif:'Cormorant Garamond',Georgia,serif;--sans:'Inter',system-ui,sans-serif;--mono:'JetBrains Mono',monospace;
    --line:rgba(28,43,43,.09);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{font-family:var(--sans);background:var(--parch);color:var(--ink);line-height:1.6;-webkit-font-smoothing:antialiased;}
  a{color:inherit;}
  .wrap{max-width:1120px;margin:0 auto;padding:0 16px;}
  .skip{position:absolute;left:-9999px;top:8px;background:var(--ink);color:#fff;padding:8px 12px;border-radius:6px;z-index:100;}
  .skip:focus{left:8px;}

  /* Buttons */
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;padding:12px 20px;border-radius:6px;border:1.5px solid transparent;transition:background .15s,border-color .15s,color .15s;white-space:nowrap;}
  .btn-v{background:var(--vd);color:#fff;}
  .btn-v:hover{background:var(--vu);}
  .btn-line{border-color:rgba(242,237,228,.35);color:var(--parch);}
  .btn-line:hover{border-color:var(--parch);}
  .btn-ink-line{border-color:rgba(28,43,43,.2);color:var(--ink);}
  .btn-ink-line:hover{border-color:var(--ink);}
  .btn:focus-visible,.nav a:focus-visible{outline:2px solid var(--vu);outline-offset:2px;}

  /* Header */
  header.top{position:sticky;top:0;z-index:50;background:var(--ink);border-bottom:1px solid rgba(255,255,255,.06);}
  .top .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;height:64px;}
  .brand{text-decoration:none;color:#fff;min-width:0;}
  .brand .firm{font-family:var(--serif);font-size:20px;font-weight:600;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;}
  .brand .sub{font-family:var(--mono);font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--slate);}
  .nav{display:flex;align-items:center;gap:22px;}
  .nav a.link{color:rgba(242,237,228,.75);text-decoration:none;font-size:13px;}
  .nav a.link:hover{color:#fff;}
  .nav .btn{padding:9px 16px;font-size:11px;}
  @media (max-width:820px){ .nav a.link{display:none;} }

  /* Hero */
  .hero{background:var(--ink);color:var(--parch);padding:72px 0 88px;overflow:hidden;}
  .hero .wrap{display:grid;grid-template-columns:1.1fr .9fr;gap:48px;align-items:center;}
  .eyebrow{font-family:var(--mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--vu);margin-bottom:16px;}
  .hero h1{font-family:var(--serif);font-weight:600;font-size:clamp(34px,5vw,54px);line-height:1.08;color:#fff;margin-bottom:18px;}
  .hero p.lead{font-size:16px;color:rgba(242,237,228,.78);max-width:520px;margin-bottom:28px;}
  .cta{display:flex;flex-wrap:wrap;gap:12px;}
  .hero .note{font-size:12px;color:var(--slate);margin-top:16px;}
  .hero .note a{color:var(--parch);}

  /* Hero preview: a small picture of the workspace */
  .preview{background:var(--parch);border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.35);overflow:hidden;color:var(--ink);}
  .preview .bar{background:var(--ink-m);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;}
  .preview .bar span{font-family:var(--mono);font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:var(--slate);}
  .preview .dots{display:flex;gap:5px;}
  .preview .dots i{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.18);}
  .preview .body{padding:16px;display:grid;gap:10px;}
  .preview .ttl{font-family:var(--serif);font-size:18px;font-weight:600;}
  .pv-row{background:#fff;border:1px solid var(--line);border-radius:8px;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .pv-row b{font-size:12.5px;font-weight:500;display:block;}
  .pv-row small{font-family:var(--mono);font-size:9.5px;color:var(--slate);}
  .pill{font-family:var(--mono);font-size:9px;letter-spacing:.08em;text-transform:uppercase;padding:3px 8px;border-radius:10px;white-space:nowrap;}
  .pill.urg{background:var(--ember-p);color:var(--ember);}
  .pill.warn{background:var(--gold-p);color:var(--gold);}
  .pill.ok{background:var(--vp);color:var(--vd);}
  .pill.sky{background:var(--sky-p);color:var(--sky);}
  @media (max-width:900px){ .hero .wrap{grid-template-columns:1fr;} .hero{padding:48px 0 64px;} }

  /* Sections */
  section.band{padding:80px 0;}
  section.band.alt{background:var(--white);}
  .sec-head{max-width:640px;margin-bottom:40px;}
  .sec-head h2{font-family:var(--serif);font-weight:600;font-size:clamp(28px,3.6vw,38px);line-height:1.15;margin-bottom:10px;}
  .sec-head p{color:var(--muted);font-size:15px;}
  @media (max-width:640px){ section.band{padding:56px 0;} }

  /* Feature grid */
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
  @media (max-width:900px){ .grid{grid-template-columns:repeat(2,1fr);} }
  @media (max-width:600px){ .grid{grid-template-columns:1fr;} }
  .feat{background:var(--white);border:1px solid var(--line);border-radius:12px;padding:22px;}
  .alt .feat{background:var(--parch);}
  .feat .ic{width:38px;height:38px;border-radius:9px;background:var(--vp);color:var(--vd);display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
  .feat h3{font-size:15px;font-weight:600;margin-bottom:6px;}
  .feat p{font-size:13.5px;color:var(--muted);}

  /* Authority classes */
  .classes{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;}
  @media (max-width:760px){ .classes{grid-template-columns:1fr;} }
  .cls{background:var(--white);border-radius:12px;border:1px solid var(--line);padding:22px;border-top:4px solid var(--vd);}
  .cls.a{border-top-color:var(--ember);}
  .cls.b{border-top-color:var(--gold);}
  .cls .k{font-family:var(--serif);font-size:30px;font-weight:600;line-height:1;}
  .cls .who{font-family:var(--mono);font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--slate);margin:6px 0 10px;}
  .cls p{font-size:13.5px;color:var(--muted);}
  .rules{display:grid;grid-template-columns:repeat(2,1fr);gap:10px 28px;list-style:none;}
  @media (max-width:760px){ .rules{grid-template-columns:1fr;} }
  .rules li{font-size:14px;padding-left:26px;position:relative;}
  .rules li::before{content:"";position:absolute;left:0;top:4px;width:16px;height:16px;border-radius:50%;background:var(--vp) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233F5E46' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12l5 5 9-10'/%3E%3C/svg%3E") center/10px no-repeat;}

  /* Steps (document review / invoice flow) */
  .flows{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
  @media (max-width:860px){ .flows{grid-template-columns:1fr;} }
  .flow{background:var(--parch);border-radius:12px;padding:22px;}
  .flow h3{font-size:15px;font-weight:600;margin-bottom:14px;}
  .steps{display:flex;flex-wrap:wrap;align-items:center;gap:6px;}
  .step{background:var(--white);border:1px solid var(--line);border-radius:6px;padding:7px 11px;font-size:12.5px;font-weight:500;}
  .step.key{border-color:var(--ember);color:var(--ember);}
  .arr{color:var(--slate);font-size:13px;}
  .flow p{font-size:13px;color:var(--muted);margin-top:12px;}

  /* Portal split */
  .split{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center;}
  @media (max-width:860px){ .split{grid-template-columns:1fr;gap:28px;} }
  .ticks{list-style:none;display:grid;gap:12px;margin:20px 0 26px;}
  .ticks li{font-size:14.5px;padding-left:26px;position:relative;}
  .ticks li::before{content:"";position:absolute;left:0;top:5px;width:16px;height:16px;border-radius:50%;background:var(--vp) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233F5E46' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12l5 5 9-10'/%3E%3C/svg%3E") center/10px no-repeat;}
  .portal-card{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 12px 36px rgba(28,43,43,.08);}
  .portal-card .ttl{font-family:var(--serif);font-size:20px;font-weight:600;margin-bottom:12px;}
  .portal-card .pv-row{background:var(--parch);}

  /* Roles table */
  .roles{width:100%;border-collapse:collapse;background:var(--white);border-radius:12px;overflow:hidden;border:1px solid var(--line);}
  .roles th,.roles td{text-align:left;padding:14px 18px;font-size:14px;border-bottom:1px solid var(--line);vertical-align:top;}
  .roles th{font-family:var(--mono);font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--slate);font-weight:400;background:var(--parch);}
  .roles tr:last-child td{border-bottom:none;}
  .roles td:first-child{font-weight:600;white-space:nowrap;}
  .roles td{color:var(--muted);}
  .roles td:first-child{color:var(--ink);}
  @media (max-width:640px){
    .roles thead{display:none;}
    .roles tr{display:block;padding:12px 16px;border-bottom:1px solid var(--line);}
    .roles td{display:block;border:none;padding:2px 0;}
  }

  /* Closing call to action */
  .closing{background:var(--ink);color:var(--parch);padding:72px 0;text-align:center;}
  .closing h2{font-family:var(--serif);font-weight:600;font-size:clamp(28px,4vw,40px);color:#fff;margin-bottom:12px;}
  .closing p{color:rgba(242,237,228,.75);max-width:560px;margin:0 auto 28px;}
  .closing .cta{justify-content:center;}

  footer{background:var(--ink);border-top:1px solid rgba(255,255,255,.07);color:var(--slate);font-size:12.5px;padding:24px 0;}
  footer .wrap{display:flex;flex-wrap:wrap;gap:12px 24px;justify-content:space-between;}
  footer a{color:rgba(242,237,228,.8);text-decoration:none;}
  footer a:hover{color:#fff;}
  footer nav{display:flex;flex-wrap:wrap;gap:18px;}
</style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

@php
  // Signed-in people go straight to the system; everyone else signs in first.
  $appUrl = route('app');
@endphp

<header class="top">
  <div class="wrap">
    <a class="brand" href="{{ url('/') }}">
      <span class="firm">{{ $firmName }}</span>
      <span class="sub">Virtual Law Firm</span>
    </a>
    <nav class="nav" aria-label="Main">
      <a class="link" href="#features">What it does</a>
      <a class="link" href="#authority">Authority</a>
      <a class="link" href="#portal">Client portal</a>
      <a class="link" href="#roles">Who uses it</a>
      @auth
        <a class="btn btn-v" href="{{ $appUrl }}">Open the app</a>
      @else
        <a class="btn btn-v" href="{{ route('login') }}">Sign in</a>
      @endauth
    </nav>
  </div>
</header>

<main id="main">

  <!-- Hero -->
  <section class="hero">
    <div class="wrap">
      <div>
        <div class="eyebrow">Law firm management system</div>
        <h1>Every matter, deadline and decision in one place.</h1>
        <p class="lead">{{ $firmName }} runs its legal work online: matters, court dates, deadlines, documents, time and billing. Every important step is checked against who is allowed to take it, and every client follows their own matters in a private portal.</p>
        <div class="cta">
          @auth
            <a class="btn btn-v" href="{{ $appUrl }}">Open the app</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button class="btn btn-line" type="submit" style="background:none;cursor:pointer;font-family:inherit">Sign out</button></form>
          @else
            <a class="btn btn-v" href="{{ route('login') }}">Sign in</a>
            <a class="btn btn-line" href="#portal">I'm a client</a>
          @endauth
        </div>
        @guest
          <p class="note">New to the firm? <a href="{{ route('register') }}">Create an account</a>. The firm approves it before you can sign in.</p>
        @endguest
      </div>

      <div class="preview" aria-hidden="true">
        <div class="bar"><div class="dots"><i></i><i></i><i></i></div><span>Command Centre</span></div>
        <div class="body">
          <div class="ttl">What needs your attention</div>
          <div class="pv-row"><div><b>Filing deadline: written submissions</b><small>Due today · 4:00 PM</small></div><span class="pill urg">Critical</span></div>
          <div class="pv-row"><div><b>Witness statement awaiting partner sign-off</b><small>Class A · Document review</small></div><span class="pill warn">Approval</span></div>
          <div class="pv-row"><div><b>Hearing: mention before the registrar</b><small>Tomorrow · 9:30 AM · Preparation 3 of 4</small></div><span class="pill sky">Court</span></div>
          <div class="pv-row"><div><b>Invoice approved and issued</b><small>Client notified in the portal</small></div><span class="pill ok">Done</span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section class="band" id="features">
    <div class="wrap">
      <div class="sec-head">
        <div class="eyebrow">What it does</div>
        <h2>The firm's day-to-day work, from intake to invoice.</h2>
        <p>One system for advocates, clerks, the firm administrator and clients. Each person signs in with their own account and sees only what their role allows.</p>
      </div>
      <div class="grid">
        <div class="feat">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h18v13H3z"/><path d="M8 7V4h8v3"/></svg></div>
          <h3>Matters and intake</h3>
          <p>A five-step intake opens each matter, with a required conflict check against existing clients and opposing parties. A conflict stops the matter from opening.</p>
        </div>
        <div class="feat">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></div>
          <h3>Court diary and deadlines</h3>
          <p>Hearings with court, judge, appearing advocate and a preparation checklist. Deadlines are graded normal, important or critical, and the responsible person is notified.</p>
        </div>
        <div class="feat">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/></svg></div>
          <h3>Tasks with authority classes</h3>
          <p>Assign work to colleagues with a class (A, B or C), priority and deadline. Tasks move through pending, in progress, blocked and done.</p>
        </div>
        <div class="feat">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg></div>
          <h3>Time and invoicing</h3>
          <p>Time is logged at each advocate's own rate. Invoices are built from unbilled time, approved by a partner, issued to the client and tracked until paid.</p>
        </div>
        <div class="feat">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M9 14l2 2 4-4"/></svg></div>
          <h3>Documents and review</h3>
          <p>Draft in the editor or upload files. Each upload is fingerprinted, reviewed by someone other than the author, and filed only by a partner.</p>
        </div>
        <div class="feat">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <h3>Messages and notifications</h3>
          <p>Each matter has an internal conversation and a client conversation. People are notified, in the app and by email, when something affects them.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Authority -->
  <section class="band alt" id="authority">
    <div class="wrap">
      <div class="sec-head">
        <div class="eyebrow">Authority you can rely on</div>
        <h2>Nothing important happens without the right person's sign-off.</h2>
        <p>Work is sorted into three authority classes, and the server enforces them on every request. The acting person always comes from their own signed-in account.</p>
      </div>
      <div class="classes">
        <div class="cls a"><div class="k">Class A</div><div class="who">Needs a partner</div><p>Filing a document with the court and approving an invoice for issue.</p></div>
        <div class="cls b"><div class="k">Class B</div><div class="who">Needs an associate</div><p>Drafting, reviewing, internal coordination and client communication.</p></div>
        <div class="cls"><div class="k">Class C</div><div class="who">Junior work</div><p>Research and first drafts, always reviewed before they go further.</p></div>
      </div>

      <div class="flows">
        <div class="flow">
          <h3>A document's path</h3>
          <div class="steps">
            <span class="step">Draft</span><span class="arr">→</span>
            <span class="step">Under review</span><span class="arr">→</span>
            <span class="step">Approved</span><span class="arr">→</span>
            <span class="step key">Filed by a partner</span>
          </div>
          <p>Nobody reviews their own document. Once submitted, its content is locked; the reviewer approves it or returns it with a reason.</p>
        </div>
        <div class="flow">
          <h3>An invoice's path</h3>
          <div class="steps">
            <span class="step">Draft</span><span class="arr">→</span>
            <span class="step key">Approved by a partner</span><span class="arr">→</span>
            <span class="step">Issued</span><span class="arr">→</span>
            <span class="step">Paid</span>
          </div>
          <p>Billed time is never invoiced twice. Issued invoices can't be edited, and clients only ever see issued and paid invoices.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Client portal -->
  <section class="band" id="portal">
    <div class="wrap split">
      <div>
        <div class="eyebrow">For clients</div>
        <div class="sec-head" style="margin-bottom:0">
          <h2>Follow your matter without chasing your lawyer.</h2>
          <p>When you instruct {{ $firmName }}, the firm invites you to a private portal. It shows your own organisation's work and nothing else.</p>
        </div>
        <ul class="ticks">
          <li>Each matter's stage, court, legal team and next court date</li>
          <li>Documents the firm has approved for you, ready to download</li>
          <li>A conversation with your legal team for every matter</li>
          <li>Issued invoices and what is still outstanding</li>
        </ul>
        <div class="cta">
          @auth
            <a class="btn btn-v" href="{{ $appUrl }}">Open your portal</a>
          @else
            <a class="btn btn-v" href="{{ route('login') }}">Sign in to the portal</a>
            <a class="btn btn-ink-line" href="{{ route('password.request') }}">Set or reset your password</a>
          @endauth
        </div>
      </div>
      <div class="portal-card" aria-hidden="true">
        <div class="ttl">My legal matters</div>
        <div style="display:grid;gap:10px">
          <div class="pv-row"><div><b>Commercial dispute: breach of contract</b><small>High Court · Next date: hearing in 6 days</small></div><span class="pill sky">Hearing</span></div>
          <div class="pv-row"><div><b>Land title transfer</b><small>Documents ready to download</small></div><span class="pill ok">Approved</span></div>
          <div class="pv-row"><div><b>Invoice issued</b><small>Due in 14 days</small></div><span class="pill warn">Outstanding</span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Roles -->
  <section class="band alt" id="roles">
    <div class="wrap">
      <div class="sec-head">
        <div class="eyebrow">Who uses it</div>
        <h2>One sign-in, the right view for each person.</h2>
        <p>Accounts are created or approved by the firm. Each role opens its own workspace.</p>
      </div>
      <table class="roles">
        <thead><tr><th>Role</th><th>Opens</th><th>Can do</th></tr></thead>
        <tbody>
          <tr><td>Partner</td><td>Advocate workspace and Firm Admin</td><td>Everything, including Class A acts: approving invoices and filing documents</td></tr>
          <tr><td>Associate</td><td>Advocate workspace</td><td>Open matters, run intake, assign tasks, log time, draft and review documents, draft invoices</td></tr>
          <tr><td>Junior associate and clerk</td><td>Advocate workspace</td><td>The same screens, with their work reviewed before approval</td></tr>
          <tr><td>Firm administrator</td><td>Firm Admin</td><td>Manage staff, settings and firm-wide billing</td></tr>
          <tr><td>Client</td><td>Client portal</td><td>See their own matters, approved documents and issued invoices, and message their legal team</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Closing -->
  <section class="closing">
    <div class="wrap">
      @auth
        <h2>Welcome back.</h2>
        <p>Pick up where you left off.</p>
        <div class="cta"><a class="btn btn-v" href="{{ $appUrl }}">Open the app</a></div>
      @else
        <h2>Already working with the firm?</h2>
        <p>Sign in with the email address the firm registered for you. If you're new, create an account and the firm will approve it.</p>
        <div class="cta">
          <a class="btn btn-v" href="{{ route('login') }}">Sign in</a>
          <a class="btn btn-line" href="{{ route('register') }}">Create an account</a>
        </div>
      @endauth
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div>&copy; {{ date('Y') }} {{ $firmName }}@if ($firmAddress) · {{ $firmAddress }}@endif</div>
    <nav aria-label="Footer">
      <a href="#features">What it does</a>
      <a href="#portal">Client portal</a>
      @auth
        <a href="{{ $appUrl }}">Open the app</a>
      @else
        <a href="{{ route('login') }}">Sign in</a>
      @endauth
    </nav>
  </div>
</footer>
</body>
</html>
