<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $firmName }} · Advocates</title>
<meta name="description" content="Work with {{ $firmName }} and always know where your matter stands: your next court date, your documents, your bills and a direct line to your legal team.">
<link rel="icon" href="{{ asset('favicon.ico') }}">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1F2A2A;--ink-soft:#2C3B3A;--text:#4A5654;--quiet:#7C8784;
    --cream:#FAF6EF;--sand:#F1E9DC;--sand-d:#E6DBC9;--white:#FFFFFF;
    --green:#3F5E46;--green-l:#5A7D61;--green-p:#E8EFE6;
    --clay:#B4643A;--clay-p:#F7E7DC;--sun:#D9A441;
    --serif:'Cormorant Garamond',Georgia,serif;--sans:'Inter',system-ui,sans-serif;
    --line:rgba(31,42,42,.1);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{font-family:var(--sans);background:var(--cream);color:var(--ink);line-height:1.65;font-size:16px;-webkit-font-smoothing:antialiased;}
  a{color:inherit;}
  img,svg{display:block;max-width:100%;}
  .wrap{max-width:1100px;margin:0 auto;padding:0 20px;}
  .skip{position:absolute;left:-9999px;top:8px;background:var(--ink);color:#fff;padding:8px 12px;border-radius:8px;z-index:100;}
  .skip:focus{left:8px;}
  em{font-style:italic;color:var(--clay);}

  /* Buttons */
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font:600 15px/1 var(--sans);text-decoration:none;padding:15px 26px;border-radius:999px;border:1.5px solid transparent;cursor:pointer;transition:background .2s,border-color .2s,transform .2s;white-space:nowrap;}
  .btn:hover{transform:translateY(-1px);}
  .btn-main{background:var(--green);color:#fff;}
  .btn-main:hover{background:var(--green-l);}
  .btn-soft{background:transparent;border-color:rgba(31,42,42,.22);color:var(--ink);}
  .btn-soft:hover{border-color:var(--ink);}
  .btn-light{background:var(--cream);color:var(--ink);}
  .btn-light:hover{background:#fff;}
  .btn-ghost-light{border-color:rgba(250,246,239,.4);color:var(--cream);background:none;}
  .btn-ghost-light:hover{border-color:var(--cream);}
  a:focus-visible,button:focus-visible{outline:3px solid var(--sun);outline-offset:3px;}

  /* Header */
  header.top{position:sticky;top:0;z-index:50;background:rgba(250,246,239,.92);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-bottom:1px solid var(--line);}
  .top .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;height:72px;}
  .brand{display:flex;align-items:center;gap:12px;text-decoration:none;min-width:0;}
  .mark{width:40px;height:40px;border-radius:50%;background:var(--green);color:var(--cream);display:flex;align-items:center;justify-content:center;font:600 18px var(--serif);flex-shrink:0;}
  .brand .firm{font:600 21px/1.1 var(--serif);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .nav{display:flex;align-items:center;gap:26px;}
  .nav a.link{text-decoration:none;font-size:14.5px;color:var(--text);}
  .nav a.link:hover{color:var(--ink);}
  .nav .btn{padding:11px 20px;font-size:14px;}
  @media (max-width:860px){ .nav a.link{display:none;} }
  .brand .firm{min-width:0;}
  @media (max-width:520px){
    .top .wrap{height:auto;min-height:68px;padding-top:10px;padding-bottom:10px;}
    .brand .firm{font-size:17px;white-space:normal;line-height:1.15;}
    .mark{width:34px;height:34px;font-size:16px;}
    .nav .btn{padding:10px 16px;font-size:13.5px;}
  }

  /* Hero */
  .hero{padding:72px 0 64px;position:relative;overflow:hidden;}
  .hero::before{content:"";position:absolute;right:-180px;top:-160px;width:560px;height:560px;border-radius:50%;background:radial-gradient(circle,var(--sand) 0%,rgba(241,233,220,0) 70%);z-index:-1;}
  .hero .wrap{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;}
  .hero .wrap > *{min-width:0;}
  @media (max-width:520px){ .hero .cta .btn{white-space:normal;text-align:center;} }
  .hello{display:inline-flex;align-items:center;gap:10px;font-size:14px;color:var(--green);background:var(--green-p);padding:7px 14px;border-radius:999px;margin-bottom:22px;}
  .hello i{width:8px;height:8px;border-radius:50%;background:var(--green-l);}
  .hero h1{font-family:var(--serif);font-weight:500;font-size:clamp(40px,6vw,66px);line-height:1.04;letter-spacing:-.01em;margin-bottom:22px;}
  .hero p.lead{font-size:18.5px;color:var(--text);max-width:520px;margin-bottom:32px;}
  .cta{display:flex;flex-wrap:wrap;gap:12px;}
  .hero .note{font-size:14px;color:var(--quiet);margin-top:20px;}
  .hero .note a{color:var(--green);font-weight:500;}
  @media (max-width:900px){ .hero .wrap{grid-template-columns:1fr;gap:40px;} .hero{padding:44px 0 48px;} }

  /* Hero illustration */
  .scene{position:relative;}
  .scene svg{width:100%;height:auto;}
  .bubble{position:absolute;background:var(--white);border-radius:18px;box-shadow:0 14px 40px rgba(31,42,42,.12);padding:14px 16px;font-size:14px;max-width:250px;line-height:1.45;}
  .bubble small{display:block;font-size:12px;color:var(--quiet);margin-bottom:3px;}
  .bubble.one{left:-8px;top:8%;border-bottom-left-radius:4px;}
  .bubble.two{right:-6px;bottom:10%;border-bottom-right-radius:4px;background:var(--green);color:var(--cream);}
  .bubble.two small{color:rgba(250,246,239,.7);}
  @media (max-width:520px){ .bubble{position:static;max-width:none;margin-top:10px;} }

  /* Sections */
  section.band{padding:96px 0;}
  section.band.sand{background:var(--sand);}
  .head{max-width:640px;margin-bottom:48px;}
  .head.center{margin-left:auto;margin-right:auto;text-align:center;}
  .kicker{font-size:14px;font-weight:500;color:var(--clay);margin-bottom:10px;}
  .head h2{font-family:var(--serif);font-weight:500;font-size:clamp(32px,4.4vw,46px);line-height:1.1;margin-bottom:14px;}
  .head p{color:var(--text);font-size:17px;}
  @media (max-width:640px){ section.band{padding:64px 0;} .head{margin-bottom:34px;} }

  /* Promise strip */
  .promise{padding:28px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);}
  .promise .wrap{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
  .promise div{display:flex;gap:14px;align-items:flex-start;font-size:15px;color:var(--text);}
  .promise b{display:block;color:var(--ink);font-weight:600;}
  .promise .dot{width:38px;height:38px;border-radius:50%;background:var(--clay-p);color:var(--clay);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  @media (max-width:760px){ .promise .wrap{grid-template-columns:1fr;} }

  /* Journey */
  .journey{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;counter-reset:step;position:relative;}
  .journey::before{content:"";position:absolute;top:30px;left:8%;right:8%;height:2px;background:repeating-linear-gradient(90deg,var(--sand-d) 0 8px,transparent 8px 16px);z-index:0;}
  .stop{position:relative;z-index:1;}
  .stop .num{width:60px;height:60px;border-radius:50%;background:var(--cream);border:2px solid var(--sand-d);display:flex;align-items:center;justify-content:center;font:500 26px var(--serif);color:var(--green);margin-bottom:18px;}
  .sand .stop .num{background:var(--sand);}
  .stop h3{font:600 23px/1.2 var(--serif);margin-bottom:8px;}
  .stop p{font-size:15px;color:var(--text);}
  @media (max-width:900px){ .journey{grid-template-columns:1fr 1fr;gap:34px 22px;} .journey::before{display:none;} }
  @media (max-width:520px){ .journey{grid-template-columns:1fr;} }

  /* Cards */
  .cards{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
  @media (max-width:760px){ .cards{grid-template-columns:1fr;} }
  .card{background:var(--white);border-radius:22px;padding:30px;border:1px solid var(--line);}
  .card .ic{width:48px;height:48px;border-radius:14px;background:var(--green-p);color:var(--green);display:flex;align-items:center;justify-content:center;margin-bottom:18px;}
  .card:nth-child(2) .ic{background:var(--clay-p);color:var(--clay);}
  .card:nth-child(3) .ic{background:#F6EED8;color:#94721F;}
  .card:nth-child(4) .ic{background:#E6EEF4;color:#3B6683;}
  .card h3{font:600 25px/1.2 var(--serif);margin-bottom:8px;}
  .card p{color:var(--text);font-size:15.5px;}

  /* Care / trust */
  .care{display:grid;grid-template-columns:.9fr 1.1fr;gap:56px;align-items:center;}
  @media (max-width:860px){ .care{grid-template-columns:1fr;gap:36px;} }
  .quote{font:italic 500 clamp(28px,3.6vw,38px)/1.25 var(--serif);color:var(--ink);border-left:3px solid var(--clay);padding-left:24px;}
  .quote span{display:block;font:400 15px var(--sans);color:var(--quiet);margin-top:16px;font-style:normal;}
  .checks{list-style:none;display:grid;gap:18px;}
  .checks li{display:flex;gap:14px;align-items:flex-start;font-size:16px;color:var(--text);}
  .checks li b{color:var(--ink);font-weight:600;}
  .tick{width:26px;height:26px;border-radius:50%;background:var(--green);color:var(--cream);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}

  /* Team strip */
  .team{display:grid;grid-template-columns:1.1fr .9fr;gap:48px;align-items:center;background:var(--ink);color:var(--cream);border-radius:28px;padding:52px;}
  .team h2{font:500 clamp(30px,3.8vw,40px)/1.12 var(--serif);margin-bottom:14px;color:#fff;}
  .team p{color:rgba(250,246,239,.78);font-size:16.5px;margin-bottom:26px;}
  .team ul{list-style:none;display:grid;gap:12px;}
  .team li{background:rgba(250,246,239,.07);border-radius:14px;padding:14px 18px;font-size:15px;color:rgba(250,246,239,.88);}
  .team li b{color:#fff;font-weight:600;}
  @media (max-width:860px){ .team{grid-template-columns:1fr;padding:34px 24px;gap:30px;} }

  /* Questions */
  .faq{max-width:760px;margin:0 auto;}
  .faq details{background:var(--white);border:1px solid var(--line);border-radius:18px;margin-bottom:12px;}
  .faq summary{list-style:none;cursor:pointer;padding:20px 24px;font:600 21px/1.3 var(--serif);display:flex;justify-content:space-between;align-items:center;gap:16px;}
  .faq summary::-webkit-details-marker{display:none;}
  .faq summary::after{content:"+";font:400 26px/1 var(--sans);color:var(--clay);transition:transform .2s;}
  .faq details[open] summary::after{transform:rotate(45deg);}
  .faq details p{padding:0 24px 22px;color:var(--text);font-size:15.5px;}

  /* Closing */
  .closing{text-align:center;padding:100px 0;}
  .closing h2{font:500 clamp(34px,5vw,54px)/1.08 var(--serif);margin-bottom:16px;}
  .closing p{color:var(--text);font-size:17.5px;max-width:540px;margin:0 auto 32px;}
  .closing .cta{justify-content:center;}

  footer{background:var(--sand);color:var(--text);font-size:14px;padding:30px 0;}
  footer .wrap{display:flex;flex-wrap:wrap;gap:14px 28px;justify-content:space-between;align-items:center;}
  footer .firm{font:600 19px var(--serif);color:var(--ink);}
  footer nav{display:flex;flex-wrap:wrap;gap:20px;}
  footer a{text-decoration:none;}
  footer a:hover{color:var(--ink);text-decoration:underline;}

  @media (prefers-reduced-motion:reduce){ html{scroll-behavior:auto;} .btn{transition:none;} .btn:hover{transform:none;} }
</style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

@php
  $appUrl = route('app');
  $initial = mb_strtoupper(mb_substr(trim($firmName), 0, 1));
@endphp

<header class="top">
  <div class="wrap">
    <a class="brand" href="{{ url('/') }}" aria-label="{{ $firmName }}, home">
      <span class="mark" aria-hidden="true">{{ $initial }}</span>
      <span class="firm">{{ $firmName }}</span>
    </a>
    <nav class="nav" aria-label="Main">
      <a class="link" href="#how">How we work</a>
      <a class="link" href="#portal">Your portal</a>
      <a class="link" href="#care">Our promise</a>
      <a class="link" href="#questions">Questions</a>
      @auth
        <a class="btn btn-main" href="{{ $appUrl }}">Open my account</a>
      @else
        <a class="btn btn-main" href="{{ route('login') }}">Sign in</a>
      @endauth
    </nav>
  </div>
</header>

<main id="main">

  <!-- Hero -->
  <section class="hero">
    <div class="wrap">
      <div>
        @auth
          <div class="hello"><i></i>Welcome back, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</div>
        @else
          <div class="hello"><i></i>Advocates, working with you</div>
        @endauth
        <h1>Legal matters are stressful. <em>Knowing where yours stands</em> shouldn't be.</h1>
        <p class="lead">At {{ $firmName }}, you're never left wondering. Your next court date, your documents, your bills and a direct line to the people handling your case are all in one private place, whenever you need them.</p>
        <div class="cta">
          @auth
            <a class="btn btn-main" href="{{ $appUrl }}">Open my account</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-soft" type="submit">Sign out</button></form>
          @else
            <a class="btn btn-main" href="{{ route('login') }}">Sign in to follow your matter</a>
            <a class="btn btn-soft" href="#how">See how we work</a>
          @endauth
        </div>
        @guest
          <p class="note">First time here? <a href="{{ route('register') }}">Create your account</a> and the firm will confirm it with you.</p>
        @endguest
      </div>

      <div class="scene" aria-hidden="true">
        <!-- Two people at a table: an advocate and a client going through papers together -->
        <svg viewBox="0 0 480 400" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="240" cy="200" r="182" fill="#F1E9DC"/>
          <circle cx="388" cy="78" r="26" fill="#D9A441" opacity=".35"/>
          <!-- window -->
          <rect x="176" y="62" width="128" height="92" rx="10" fill="#FAF6EF" stroke="#E6DBC9" stroke-width="3"/>
          <path d="M240 62v92M176 108h128" stroke="#E6DBC9" stroke-width="3"/>
          <path d="M190 140c14-18 28-26 42-22 12 3 18 14 30 12 10-2 18-10 28-8" stroke="#5A7D61" stroke-width="3" stroke-linecap="round" opacity=".55"/>
          <!-- plant -->
          <path d="M84 300c-4-40 4-70 20-92M104 300c2-34 14-58 30-72M94 300c-10-30-26-46-44-52" stroke="#3F5E46" stroke-width="5" stroke-linecap="round"/>
          <ellipse cx="104" cy="206" rx="12" ry="20" transform="rotate(25 104 206)" fill="#5A7D61"/>
          <ellipse cx="136" cy="226" rx="11" ry="18" transform="rotate(50 136 226)" fill="#3F5E46"/>
          <ellipse cx="50" cy="246" rx="11" ry="18" transform="rotate(-55 50 246)" fill="#5A7D61"/>
          <path d="M70 300h52l-6 42H76z" fill="#B4643A"/>
          <!-- left person: advocate -->
          <circle cx="168" cy="196" r="26" fill="#7A4A30"/>
          <path d="M142 190c2-20 16-30 30-30 16 0 26 12 26 26-10-8-26-10-36-4-8 4-14 6-20 8z" fill="#1F2A2A"/>
          <path d="M120 300c0-46 22-74 48-74s48 28 48 74z" fill="#1F2A2A"/>
          <path d="M160 228l8 18 8-18" stroke="#FAF6EF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          <!-- right person: client -->
          <circle cx="316" cy="192" r="26" fill="#A86B47"/>
          <path d="M288 196c-6-26 8-44 28-44 22 0 34 18 30 40-4 12-6 22-2 34-14-4-18-18-18-30-6 4-18 4-26-2-4 4-8 6-12 2z" fill="#2C3B3A"/>
          <path d="M268 300c0-46 22-76 48-76s48 30 48 76z" fill="#B4643A"/>
          <!-- table -->
          <rect x="96" y="292" width="292" height="16" rx="8" fill="#3F5E46"/>
          <path d="M128 308v58M356 308v58" stroke="#3F5E46" stroke-width="8" stroke-linecap="round"/>
          <!-- papers + cup -->
          <rect x="196" y="270" width="64" height="24" rx="3" fill="#FFFFFF" transform="rotate(-6 228 282)"/>
          <path d="M206 278h40M206 285h30" stroke="#E6DBC9" stroke-width="3" stroke-linecap="round" transform="rotate(-6 228 282)"/>
          <rect x="232" y="274" width="58" height="20" rx="3" fill="#FAF6EF" transform="rotate(7 261 284)"/>
          <path d="M300 276h18v16h-18z" fill="#FFFFFF"/>
          <path d="M318 280c8 0 8 9 0 9" stroke="#FFFFFF" stroke-width="3"/>
          <!-- hands -->
          <circle cx="204" cy="286" r="8" fill="#7A4A30"/>
          <circle cx="282" cy="288" r="8" fill="#A86B47"/>
        </svg>
        <div class="bubble one"><small>Your legal team</small>Your hearing is confirmed for Thursday. Everything you need to bring is listed in your portal.</div>
        <div class="bubble two"><small>You</small>Thank you, that's a weight off my mind.</div>
      </div>
    </div>
  </section>

  <!-- Promise strip -->
  <div class="promise">
    <div class="wrap">
      <div><span class="dot"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></span><span><b>Never miss a date</b>Court dates and deadlines in one place.</span></div>
      <div><span class="dot"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><span><b>Talk to your team</b>A conversation for every matter.</span></div>
      <div><span class="dot"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span><span><b>Private by design</b>Only you and your team see your matter.</span></div>
    </div>
  </div>

  <!-- How we work -->
  <section class="band" id="how">
    <div class="wrap">
      <div class="head">
        <div class="kicker">How we work with you</div>
        <h2>From the first conversation to the final outcome.</h2>
        <p>Every matter follows the same careful path, so you always know what's happening and what comes next.</p>
      </div>
      <div class="journey">
        <div class="stop">
          <div class="num">1</div>
          <h3>We listen</h3>
          <p>We hear your situation, take down your details and check that nothing stands in the way of us acting for you.</p>
        </div>
        <div class="stop">
          <div class="num">2</div>
          <h3>We take it on</h3>
          <p>Your matter is opened with a named advocate responsible for it and a partner overseeing their work.</p>
        </div>
        <div class="stop">
          <div class="num">3</div>
          <h3>You stay informed</h3>
          <p>You're invited to your own private portal to follow progress, read documents and message your team.</p>
        </div>
        <div class="stop">
          <div class="num">4</div>
          <h3>We see it through</h3>
          <p>From preparing for each hearing to the final bill, every step is recorded and nothing is left to chance.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Portal -->
  <section class="band sand" id="portal">
    <div class="wrap">
      <div class="head">
        <div class="kicker">Your private portal</div>
        <h2>Your matter, in plain sight. <em>Any time.</em></h2>
        <p>No more waiting on hold to ask what's happening. Sign in from your phone or computer and see it for yourself.</p>
      </div>
      <div class="cards">
        <div class="card">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></div>
          <h3>Where things stand</h3>
          <p>The stage your matter has reached, which court it's in, who's on your legal team and when you're next due in court.</p>
        </div>
        <div class="card">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <h3>A direct line to your team</h3>
          <p>Ask a question or share an update in the conversation for your matter. Your advocate is told as soon as you write.</p>
        </div>
        <div class="card">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg></div>
          <h3>Your documents</h3>
          <p>Documents your team has finished and approved for you, ready to read or download whenever you need them.</p>
        </div>
        <div class="card">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/></svg></div>
          <h3>Clear, honest bills</h3>
          <p>Every invoice you've been sent and what's still outstanding, with the work it covers. No surprises.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Our promise -->
  <section class="band" id="care">
    <div class="wrap care">
      <div>
        <div class="kicker">Our promise to you</div>
        <p class="quote">"Your case deserves more than one pair of eyes."<span>That's why careful checks are built into how we work, not left to memory.</span></p>
      </div>
      <ul class="checks">
        <li><span class="tick"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span><span><b>A partner signs off</b> on every document before it is filed in court, and on every invoice before it reaches you.</span></li>
        <li><span class="tick"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span><span><b>No one checks their own work.</b> Every document is reviewed by a second advocate before it goes further.</span></li>
        <li><span class="tick"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span><span><b>We check for conflicts first.</b> Before we take on your matter, we make sure we've never acted against you.</span></li>
        <li><span class="tick"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span><span><b>You're never billed twice</b> for the same piece of work, and a sent invoice is never quietly changed.</span></li>
        <li><span class="tick"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span><span><b>Your matter stays private.</b> Other clients can never see it, and our internal notes stay with us.</span></li>
      </ul>
    </div>
  </section>

  <!-- For the team -->
  <section class="band" style="padding-top:0">
    <div class="wrap">
      <div class="team">
        <div>
          <h2>Part of the team?</h2>
          <p>Advocates, clerks and administrators sign in to the same place to run the firm's work day to day.</p>
          @auth
            <a class="btn btn-light" href="{{ $appUrl }}">Open my workspace</a>
          @else
            <a class="btn btn-light" href="{{ route('login') }}">Staff sign in</a>
          @endauth
        </div>
        <ul>
          <li><b>Your day at a glance:</b> what needs you today, your hearings and your deadlines.</li>
          <li><b>Matters and clients:</b> open a new matter, assign the work and keep notes together.</li>
          <li><b>Time and billing:</b> record your time as you go and draft invoices from it.</li>
          <li><b>Running the firm:</b> partners and administrators see workload, billing and settings.</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Questions -->
  <section class="band sand" id="questions">
    <div class="wrap">
      <div class="head center">
        <div class="kicker">Questions people ask us</div>
        <h2>Good questions to ask before you start.</h2>
      </div>
      <div class="faq">
        <details>
          <summary>How do I get access to the portal?</summary>
          <p>When we take on your matter, we invite you by email. Open the link, choose your password and you're in. You can also create an account yourself, and the firm will confirm it with you before it opens.</p>
        </details>
        <details>
          <summary>Who can see my matter?</summary>
          <p>Only you, the people at your organisation you've asked us to include, and your legal team. Other clients can never see your matter, and the firm's internal notes and working papers aren't shown in the portal.</p>
        </details>
        <details>
          <summary>How will I know about my court dates?</summary>
          <p>Your next court date is shown on your matter as soon as it's set. Your legal team will also keep you updated in the conversation for your matter.</p>
        </details>
        <details>
          <summary>Can I see what I'm being billed for?</summary>
          <p>Yes. Each invoice in your portal lists the work it covers. A partner approves every invoice before it's sent, and once sent, it's never changed without a correction you can see.</p>
        </details>
        <details>
          <summary>I've forgotten my password. What now?</summary>
          <p>Use <a href="{{ route('password.request') }}">Forgot password</a> on the sign-in page and we'll email you a link to choose a new one.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- Closing -->
  <section class="closing">
    <div class="wrap">
      @auth
        <h2>Good to see you again.</h2>
        <p>Everything about your matter is where you left it.</p>
        <div class="cta"><a class="btn btn-main" href="{{ $appUrl }}">Open my account</a></div>
      @else
        <h2>Peace of mind is <em>one sign-in away.</em></h2>
        <p>Sign in with the email address you gave the firm. If you're new, create your account and we'll confirm it with you.</p>
        <div class="cta">
          <a class="btn btn-main" href="{{ route('login') }}">Sign in</a>
          <a class="btn btn-soft" href="{{ route('register') }}">Create your account</a>
        </div>
      @endauth
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div>
      <div class="firm">{{ $firmName }}</div>
      <div>&copy; {{ date('Y') }}@if ($firmAddress) · {{ $firmAddress }}@endif</div>
    </div>
    <nav aria-label="Footer">
      <a href="#how">How we work</a>
      <a href="#portal">Your portal</a>
      <a href="#questions">Questions</a>
      @auth
        <a href="{{ $appUrl }}">Open my account</a>
      @else
        <a href="{{ route('login') }}">Sign in</a>
      @endauth
    </nav>
  </div>
</footer>
</body>
</html>
