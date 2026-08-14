/* ════════════════════════
   LOADER
════════════════════════ */
window.addEventListener('load', () => {
  const lw = document.getElementById('lw');
  const lbl = document.getElementById('lbl');
  const ltail = document.getElementById('ltail');
  const loader = document.getElementById('loader');

  setTimeout(() => { lw.classList.add('show'); }, 200);
  setTimeout(() => { lbl.classList.add('grow'); }, 500);
  setTimeout(() => { ltail.classList.add('grow'); }, 900);
  setTimeout(() => {
    loader.classList.add('done');
    initReveal();
    initScrollBridge();
  }, 1800);
});

/* ════════════════════════
   SMOOTH SCROLL (nav + in-page CTAs)
════════════════════════ */
function initSmoothScroll() {
  document.querySelectorAll('[data-scroll-to]').forEach(el => {
    el.addEventListener('click', () => {
      const target = document.getElementById(el.dataset.scrollTo);
      if (target) target.scrollIntoView({ behavior: 'smooth' });
      closeMobileNav();
    });
  });

  document.querySelectorAll('.nav-links a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const target = document.querySelector(a.getAttribute('href'));
      if (target) target.scrollIntoView({ behavior: 'smooth' });
      closeMobileNav();
    });
  });
}

/* ════════════════════════
   MOBILE NAV
════════════════════════ */
function closeMobileNav() {
  const links = document.getElementById('nav-links');
  const toggle = document.getElementById('nav-toggle');
  if (!links || !toggle) return;
  links.classList.remove('open');
  toggle.classList.remove('active');
  toggle.setAttribute('aria-expanded', 'false');
}

function initMobileNav() {
  const toggle = document.getElementById('nav-toggle');
  const links = document.getElementById('nav-links');
  if (!toggle || !links) return;

  toggle.addEventListener('click', () => {
    const isOpen = links.classList.toggle('open');
    toggle.classList.toggle('active', isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
  });
}

/* ════════════════════════
   ACTIVE NAV LINK ON SCROLL
════════════════════════ */
function initActiveNav() {
  const navLinks = Array.from(document.querySelectorAll('.nav-links a[href^="#"]'));
  if (!navLinks.length) return;

  const sections = navLinks
    .map(a => document.querySelector(a.getAttribute('href')))
    .filter(Boolean);

  const io = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const id = '#' + entry.target.id;
      navLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === id));
    });
  }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

  sections.forEach(s => io.observe(s));
}

/* ════════════════════════
   SCROLL BRIDGE LINE
════════════════════════ */
function initScrollBridge() {
  const fill = document.getElementById('sb-fill');
  const dot = document.getElementById('sb-dot');

  window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = Math.min(100, (scrollTop / docHeight) * 100);
    fill.style.height = pct + '%';
    dot.style.top = pct + '%';

    // Nav color switch
    const nav = document.getElementById('main-nav');
    if (scrollTop > 80) { nav.classList.add('light'); } else { nav.classList.remove('light'); }

    // Footer completion
    const footer = document.getElementById('footer');
    const footerTop = footer.getBoundingClientRect().top;
    if (footerTop < window.innerHeight * 0.8) {
      document.getElementById('footer-bridge').classList.add('done');
      document.getElementById('footer-vi').classList.add('glow');
    }

    // Gavel mockup animation
    const mockup = document.getElementById('gavel-mockup');
    if (mockup) {
      const rect = mockup.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.85) { triggerGavelMockup(); }
    }

    // Africa map animation
    const map = document.getElementById('africa-map');
    if (map) {
      const rect = map.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.8) { triggerAfricaMap(); }
    }
  }, { passive: true });
}

/* ════════════════════════
   REVEAL ON SCROLL
════════════════════════ */
function initReveal() {
  const els = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.12 });
  els.forEach(el => io.observe(el));
}

/* ════════════════════════
   PRINCIPLES
════════════════════════ */
function initPrinciples() {
  document.querySelectorAll('.pc').forEach(el => {
    el.addEventListener('click', () => {
      const isActive = el.classList.contains('active');
      document.querySelectorAll('.pc').forEach(p => p.classList.remove('active'));
      if (!isActive) el.classList.add('active');
    });
  });
}

/* ════════════════════════
   STAT COUNT-UP
════════════════════════ */
function initStatCounters() {
  const counters = document.querySelectorAll('.stat-count');
  if (!counters.length) return;

  const io = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      animateCountEl(el, 0, target, 1200);
      io.unobserve(el);
    });
  }, { threshold: 0.6 });

  counters.forEach(el => io.observe(el));
}

function animateCountEl(el, from, to, duration) {
  const start = performance.now();
  function step(now) {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(from + (to - from) * ease);
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

/* ════════════════════════
   FAQ ACCORDION
════════════════════════ */
function initFaq() {
  document.querySelectorAll('.faq-item').forEach(item => {
    const btn = item.querySelector('.faq-q');
    btn.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item.open').forEach(other => {
        if (other !== item) {
          other.classList.remove('open');
          other.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
        }
      });
      item.classList.toggle('open', !isOpen);
      btn.setAttribute('aria-expanded', String(!isOpen));
    });
  });
}

/* ════════════════════════
   BACK TO TOP
════════════════════════ */
function initBackToTop() {
  const btn = document.getElementById('back-to-top');
  if (!btn) return;

  window.addEventListener('scroll', () => {
    btn.classList.toggle('show', window.scrollY > window.innerHeight * 0.6);
  }, { passive: true });

  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

/* ════════════════════════
   HERO MESH PARALLAX
════════════════════════ */
function initHeroParallax() {
  const mesh = document.querySelector('.hero-mesh');
  const hero = document.querySelector('.hero');
  if (!mesh || !hero || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  hero.addEventListener('mousemove', e => {
    const x = (e.clientX / window.innerWidth - 0.5) * 24;
    const y = (e.clientY / window.innerHeight - 0.5) * 24;
    mesh.style.transform = `translate(${x}px, ${y}px)`;
  }, { passive: true });
}

/* ════════════════════════
   GAVEL MOCKUP ANIMATION
════════════════════════ */
let gavelDone = false;
function triggerGavelMockup() {
  if (gavelDone) return; gavelDone = true;

  // Animate stats
  animateCount('gm-stat-1', 0, 247, 1400);
  animateCount('gm-stat-2', 0, 94, 1200);
  animateCount('gm-stat-3', 0, 18, 1000);

  // Progress bar
  setTimeout(() => { document.getElementById('gm-progress').classList.add('go'); }, 300);

  // Documents appear
  const docs = ['gm-doc-1', 'gm-doc-2', 'gm-doc-3'];
  docs.forEach((id, i) => {
    setTimeout(() => { document.getElementById(id).classList.add('show'); }, 600 + i * 280);
  });
}

function animateCount(id, from, to, duration) {
  const el = document.getElementById(id);
  if (!el) return;
  const start = performance.now();
  function step(now) {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(from + (to - from) * ease);
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

/* ════════════════════════
   AFRICA MAP ANIMATION
════════════════════════ */
let mapDone = false;
function triggerAfricaMap() {
  if (mapDone) return; mapDone = true;

  // Kampala appears first
  setTimeout(() => {
    document.getElementById('mp-kampala').classList.add('show');
    document.getElementById('mr-kampala').classList.add('pulse');
  }, 200);

  // Lines draw
  const lines = ['mc-1', 'mc-2', 'mc-3', 'mc-4'];
  lines.forEach((id, i) => {
    setTimeout(() => {
      const el = document.getElementById(id);
      if (el) el.classList.add('draw');
    }, 500 + i * 350);
  });

  // Other hubs appear
  const hubs = [
    { id: 'mp-nairobi', delay: 900 },
    { id: 'mp-lagos', delay: 1200 },
    { id: 'mp-accra', delay: 1550 },
  ];
  hubs.forEach(h => {
    setTimeout(() => {
      const el = document.getElementById(h.id);
      if (el) { el.style.transition = 'opacity 0.4s'; el.style.opacity = '0.6'; }
    }, h.delay);
  });
}

/* ════════════════════════
   CONTACT PATHWAYS
════════════════════════ */
const ctxMap = {
  institution: 'You are looking to modernise an institution. Tell us about the organisation, the current system, and the outcome you are working toward.',
  legal: 'You are focused on legal services or legal technology. Tell us about the legal challenge, the users you serve, and how you see technology helping.',
  new: 'You are building something new. Tell us what you have in mind — even if it is early stage. We are interested in what you are trying to create.',
};

function initContactPathways() {
  document.querySelectorAll('.cp').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('.cp').forEach(p => p.classList.remove('selected'));
      el.classList.add('selected');

      const wrap = document.getElementById('contact-form-wrap');
      const ctx = document.getElementById('cf-context');
      ctx.textContent = ctxMap[el.dataset.path];
      wrap.classList.add('open');
      setTimeout(() => { wrap.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
    });
  });
}

/* ════════════════════════
   CONTACT FORM
════════════════════════ */
function initContactForm() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  form.addEventListener('submit', e => {
    e.preventDefault();
    const status = document.getElementById('cf-status');
    const submitBtn = form.querySelector('button[type="submit"]');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';

    // No backend is wired up yet — acknowledge receipt locally.
    setTimeout(() => {
      status.textContent = 'Thank you — your message has been noted. We will respond within two business days.';
      submitBtn.textContent = 'Message Sent';
      form.reset();
    }, 600);
  });
}

/* ════════════════════════
   INIT
════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initSmoothScroll();
  initMobileNav();
  initActiveNav();
  initPrinciples();
  initContactPathways();
  initContactForm();
  initStatCounters();
  initFaq();
  initBackToTop();
  initHeroParallax();
});
