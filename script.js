// ============================
// Sötét/világos mód
// ============================
(function () {
  const root = document.documentElement;
  const stored = localStorage.getItem('theme');
  if (stored === 'light') root.setAttribute('data-theme', 'light');

  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const isLight = root.getAttribute('data-theme') === 'light';
      if (isLight) {
        root.removeAttribute('data-theme');
        localStorage.setItem('theme', 'dark');
      } else {
        root.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
      }
    });
  }
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

// Ha egy belső (#) linkre kattintanak, zárja be a mobil menüt görgetés előtt
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
const revealEls = document.querySelectorAll('.reveal');

const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, {
  threshold: 0.15,
  rootMargin: '0px 0px -40px 0px'
});

revealEls.forEach(el => revealObserver.observe(el));

// ============================
// Számláló animáció a "Rólunk" statisztikáknál
// ============================
const counters = document.querySelectorAll('.stat-number[data-target]');

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.4 });

function animateCounter(el) {
  const target = parseInt(el.getAttribute('data-target'), 10);
  const suffix = el.getAttribute('data-suffix') || '';
  const duration = 1400;
  const startTime = performance.now();

  function step(now) {
    const progress = Math.min((now - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.floor(eased * target) + suffix;
    if (progress < 1) {
      requestAnimationFrame(step);
    } else {
      el.textContent = target + suffix;
    }
  }
  requestAnimationFrame(step);
}

counters.forEach(el => counterObserver.observe(el));

// ============================
// Ajánlatkérő űrlap küldése (AJAX -> PHP -> SMTP)
// ============================
const cateringForm = document.getElementById('cateringForm');

if (cateringForm) {
  const submitBtn = document.getElementById('formSubmitBtn');
  const formMessage = document.getElementById('formMessage');

  cateringForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Egyszerű kliensoldali validáció
    if (!cateringForm.checkValidity()) {
      cateringForm.reportValidity();
      return;
    }

    const formData = new FormData(cateringForm);
    const originalBtnText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Küldés...';
    formMessage.textContent = '';
    formMessage.className = 'form-message';

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
        result = { success: false, message: 'Váratlan válasz érkezett a szervertől.' };
      }

      if (response.ok && result.success) {
        formMessage.textContent = result.message || 'Köszönjük! Az ajánlatkérést elküldtük, hamarosan jelentkezünk.';
        formMessage.classList.add('form-message--success');
        cateringForm.reset();
      } else {
        formMessage.textContent = result.message || 'Hiba történt a küldés során. Kérjük, próbálja újra, vagy hívjon minket telefonon.';
        formMessage.classList.add('form-message--error');
      }
    } catch (err) {
      formMessage.textContent = 'Hálózati hiba történt. Kérjük, ellenőrizze az internetkapcsolatot, majd próbálja újra.';
      formMessage.classList.add('form-message--error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalBtnText;
    }
  });
}