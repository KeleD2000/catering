// ============================
// Világos (alap) / sötét (inverz) mód
// Frissítéskor mindig a világos téma tölt be, a sötét csak a gombbal kapcsolható be.
// ============================
(function () {
  const root = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      if (root.getAttribute('data-theme') === 'dark') {
        root.removeAttribute('data-theme');
      } else {
        root.setAttribute('data-theme', 'dark');
      }
    });
  }
})();

// ============================
// Nyelvváltás (translations.js alapján)
// ============================
const SUPPORTED = ['hu', 'en', 'de'];
let currentLang = 'hu';
const t = (key) => (window.TRANSLATIONS[currentLang] || {})[key] ?? window.TRANSLATIONS.hu[key] ?? key;

function applyLang(lang) {
  if (!SUPPORTED.includes(lang)) lang = 'hu';
  currentLang = lang;
  document.documentElement.lang = lang;

  document.querySelectorAll('[data-i18n]').forEach(el => {
    el.innerHTML = t(el.dataset.i18n);
  });
  document.querySelectorAll('[data-i18n-attr]').forEach(el => {
    el.dataset.i18nAttr.split(';').forEach(pair => {
      const [attr, key] = pair.split(':');
      el.setAttribute(attr, t(key));
    });
  });

  document.title = t('title');
  const meta = document.querySelector('meta[name="description"]');
  if (meta) meta.setAttribute('content', t('desc'));

  document.querySelectorAll('.lang-switch button').forEach(b => {
    b.classList.toggle('is-current', b.dataset.lang === lang);
  });
  const hidden = document.getElementById('f-lang');
  if (hidden) hidden.value = lang;
}

(function initLang() {
  let stored = null;
  try { stored = localStorage.getItem('lang'); } catch (e) {}
  const browser = (navigator.language || 'hu').slice(0, 2).toLowerCase();
  const initial = SUPPORTED.includes(stored) ? stored : (SUPPORTED.includes(browser) ? browser : 'hu');
  if (initial !== 'hu') applyLang(initial);

  document.querySelectorAll('.lang-switch button').forEach(btn => {
    btn.addEventListener('click', () => {
      applyLang(btn.dataset.lang);
      try { localStorage.setItem('lang', btn.dataset.lang); } catch (e) {}
    });
  });
})();

// ============================
// Mobil navigáció (hamburger menü)
// ============================
const navToggle = document.getElementById('navToggle');
const navLinks = document.getElementById('navLinks');

navToggle.addEventListener('click', () => {
  const isOpen = navLinks.classList.toggle('open');
  navToggle.setAttribute('aria-expanded', isOpen);
  navToggle.textContent = isOpen ? '✕' : '☰';
});

navLinks.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', () => {
    navLinks.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.textContent = '☰';
  });
});

// ============================
// Scroll-reveal animáció
// ============================
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ============================
// Ajánlatkérő űrlap (AJAX -> PHP) + eredmény popup
// ============================
const cateringForm = document.getElementById('cateringForm');
const resultModal = document.getElementById('resultModal');

let focusAfterClose = null;

function showResult(type, message) {
  if (!resultModal) { alert(message); return; }
  resultModal.classList.remove('is-success', 'is-error', 'is-closing');
  resultModal.classList.add(type === 'success' ? 'is-success' : 'is-error');
  document.getElementById('resultIcon').textContent = type === 'success' ? '✓' : '!';
  document.getElementById('resultTitle').textContent = t(type === 'success' ? 'res_ok_title' : 'res_err_title');
  document.getElementById('resultText').textContent = message;
  if (!resultModal.open) resultModal.showModal();
  document.body.classList.add('modal-open');
}

if (resultModal) {
  let resultClosing = false;
  const closeResult = () => {
    if (!resultModal.open || resultClosing) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      resultModal.close();
      return;
    }
    resultClosing = true;
    resultModal.classList.add('is-closing');
    setTimeout(() => {
      resultClosing = false;
      resultModal.classList.remove('is-closing');
      resultModal.close();
    }, 300);
  };
  document.getElementById('resultCloseBtn').addEventListener('click', closeResult);
  resultModal.addEventListener('click', (e) => { if (e.target === resultModal) closeResult(); });
  resultModal.addEventListener('cancel', (e) => { e.preventDefault(); closeResult(); });
  resultModal.addEventListener('close', () => {
    document.body.classList.remove('modal-open');
    if (focusAfterClose) {
      focusAfterClose.focus();
      focusAfterClose = null;
    }
  });
}

if (cateringForm) {
  const submitBtn = document.getElementById('formSubmitBtn');

  cateringForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!cateringForm.checkValidity()) {
      cateringForm.classList.add('was-validated');
      focusAfterClose = cateringForm.querySelector(':invalid');
      showResult('error', t('msg.required'));
      return;
    }

    const formData = new FormData(cateringForm);
    const originalBtnText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = t('msg.sending');

    try {
      const response = await fetch('send-mail.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
      });

      let result;
      try {
        result = await response.json();
      } catch {
        result = { success: false, message: t('msg.unexpected') };
      }

      if (response.ok && result.success) {
        showResult('success', result.message || t('msg.success'));
        cateringForm.reset();
        cateringForm.classList.remove('was-validated');
        const hiddenLang = document.getElementById('f-lang');
        if (hiddenLang) hiddenLang.value = currentLang;
      } else {
        showResult('error', result.message || t('msg.error'));
      }
    } catch (err) {
      showResult('error', t('msg.network'));
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalBtnText;
    }
  });
}

// ============================
// "Rólunk" popup (dialog)
// ============================
const aboutModal = document.getElementById('aboutModal');
const aboutOpenBtn = document.getElementById('aboutOpenBtn');

if (aboutModal && aboutOpenBtn) {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let isClosing = false;

  function openAboutModal() {
    aboutModal.classList.remove('is-closing');
    aboutModal.showModal();
    document.body.classList.add('modal-open');
  }

  function closeAboutModal() {
    if (!aboutModal.open || isClosing) return;

    if (prefersReducedMotion.matches) {
      aboutModal.close();
      return;
    }

    isClosing = true;
    aboutModal.classList.add('is-closing');

    const finish = () => {
      if (!isClosing) return;
      isClosing = false;
      aboutModal.classList.remove('is-closing');
      aboutModal.close();
    };

    aboutModal.addEventListener('animationend', (e) => {
      if (e.target === aboutModal) finish();
    }, { once: true });

    setTimeout(finish, 400);
  }

  aboutOpenBtn.addEventListener('click', openAboutModal);
  aboutModal.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeAboutModal));
  aboutModal.addEventListener('click', (e) => { if (e.target === aboutModal) closeAboutModal(); });
  aboutModal.addEventListener('cancel', (e) => { e.preventDefault(); closeAboutModal(); });
  aboutModal.addEventListener('close', () => { document.body.classList.remove('modal-open'); });
}