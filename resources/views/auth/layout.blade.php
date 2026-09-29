<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title') · {{ $firmName }}</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
  :root{--ink:#1C2B2B;--parch:#F2EDE4;--vd:#3F5E46;--vu:#5A7D61;--slate:#8A9BA8;--ember:#B85C2A;--ember-p:#FBF0EB;--vp:#EDF1EC;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Inter',system-ui,sans-serif;background:var(--parch);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;}
  .card{background:#fff;border-radius:14px;box-shadow:0 16px 48px rgba(28,43,43,.12);width:100%;max-width:400px;overflow:hidden;}
  .head{background:var(--ink);padding:22px 24px;}
  .firm{font-family:'Cormorant Garamond',Georgia,serif;font-size:20px;font-weight:600;color:#fff;}
  .sub{font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--slate);margin-top:4px;}
  .body{padding:22px 24px;}
  h1{font-family:'Cormorant Garamond',Georgia,serif;font-size:22px;font-weight:600;margin-bottom:4px;}
  p.lead{font-size:12px;color:var(--slate);margin-bottom:16px;line-height:1.5;}
  label{display:block;font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:var(--slate);margin:12px 0 4px;}
  input[type=email],input[type=password],input[type=text],input[type=tel],select{width:100%;font-size:14px;padding:10px 12px;border:1.5px solid rgba(28,43,43,.14);border-radius:6px;background:var(--parch);outline:none;font-family:inherit;}
  input:focus{border-color:var(--vd);}
  .row{display:flex;align-items:center;justify-content:space-between;margin-top:12px;font-size:12px;}
  .row label{margin:0;text-transform:none;letter-spacing:0;font-family:inherit;font-size:12px;color:var(--ink);display:flex;align-items:center;gap:6px;}
  a{color:var(--vd);}
  button{width:100%;margin-top:18px;padding:11px;border:none;border-radius:6px;background:var(--vd);color:#fff;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;}
  button:hover{background:var(--vu);}
  .error{background:var(--ember-p);color:var(--ember);font-size:12px;padding:9px 11px;border-radius:6px;margin-bottom:10px;}
  .hint{font-size:11px;color:var(--slate);margin-top:4px;}
  .switch{font-size:12px;color:var(--slate);text-align:center;margin-top:16px;padding-top:14px;border-top:1px solid rgba(28,43,43,.08);}
  .switch a{font-weight:600;}
  .status{background:var(--vp);color:var(--vd);font-size:12px;padding:9px 11px;border-radius:6px;margin-bottom:10px;}
</style>
</head>
<body>
  <main class="card">
    <div class="head"><div class="firm">{{ $firmName }}</div><div class="sub">Virtual Law Firm</div></div>
    <div class="body">
      @if (session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
      @if ($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
      @yield('content')
    </div>
  </main>
</body>
</html>
