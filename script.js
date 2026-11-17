/* ═══════════════════════════════════════════
   Gondal Infra — Hero Industrial Park 3
   script.js v3
═══════════════════════════════════════════ */

// ── Nav toggle ──
const menu   = document.getElementById('menu');
const nav    = document.getElementById('nav');
const header = document.getElementById('site-header');

menu.addEventListener('click', () => {
  const isOpen = nav.classList.toggle('open');
  menu.setAttribute('aria-expanded', String(isOpen));
  menu.textContent = isOpen ? '✕' : '☰';
});
nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  nav.classList.remove('open');
  menu.setAttribute('aria-expanded', 'false');
  menu.textContent = '☰';
}));

// ── Header shadow on scroll ──
window.addEventListener('scroll', () => {
  header.classList.toggle('scrolled', window.scrollY > 40);
}, { passive: true });

// ── Active nav link on scroll ──
const sections = document.querySelectorAll('section[id]');
const navLinks = nav.querySelectorAll('a[href^="#"]');

const activateLink = () => {
  let current = '';
  sections.forEach(s => {
    if (window.scrollY >= s.offsetTop - 120) current = s.id;
  });
  navLinks.forEach(a => {
    a.classList.toggle('active', a.getAttribute('href') === '#' + current);
  });
};
window.addEventListener('scroll', activateLink, { passive: true });

// ── Footer year ──
document.getElementById('year').textContent = new Date().getFullYear();

// ── Scroll reveal ──
const revealEls = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      // Stagger siblings within the same parent
      const siblings = Array.from(entry.target.parentElement.querySelectorAll('.reveal:not(.visible)'));
      const delay = siblings.indexOf(entry.target) * 80;
      setTimeout(() => entry.target.classList.add('visible'), delay);
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
revealEls.forEach(el => revealObserver.observe(el));

// ── Lightbox ──
const lightbox    = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');

document.querySelectorAll('.gallery-item').forEach(item => {
  item.addEventListener('click', () => {
    lightboxImg.src = item.dataset.full;
    lightboxImg.alt = item.querySelector('img').alt;
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  });
});

function closeLightbox() {
  lightbox.classList.remove('open');
  lightbox.setAttribute('aria-hidden', 'true');
  lightboxImg.src = '';
  document.body.style.overflow = '';
}
document.getElementById('close-lightbox').addEventListener('click', closeLightbox);
lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

// ── FAQ toggle icon sync (details/summary) ──
document.querySelectorAll('.faq-item').forEach(item => {
  item.addEventListener('toggle', () => {
    const toggle = item.querySelector('.faq-toggle');
    if (toggle) toggle.textContent = item.open ? '−' : '+';
  });
});

// ── Download all site images ──
const siteImages = [
  'assets/site-work-01.jpg','assets/site-work-02.jpg','assets/site-work-03.jpg',
  'assets/site-work-04.jpg','assets/site-work-05.jpg','assets/site-work-06.jpg',
  'assets/site-work-07.jpg','assets/site-work-08.jpg','assets/site-work-09.jpg',
  'assets/site-work-10.jpg','assets/site-work-11.jpg','assets/site-work-12.jpg',
  'assets/site-work-13.jpg','assets/site-work-14.jpg','assets/site-work-15.jpg',
  'assets/site-work-16.jpg',
];

function triggerImageDownloads() {
  siteImages.forEach((src, i) => {
    setTimeout(() => {
      const a = document.createElement('a');
      a.href = src;
      a.download = src.split('/').pop();
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }, i * 320);
  });
}

const dlBtn = document.getElementById('download-all-images');
if (dlBtn) dlBtn.addEventListener('click', e => { e.preventDefault(); triggerImageDownloads(); });

const footerDlBtn = document.getElementById('footer-download-images');
if (footerDlBtn) footerDlBtn.addEventListener('click', e => { e.preventDefault(); triggerImageDownloads(); });

// ── Form feedback via query string ──
const params = new URLSearchParams(location.search);
const result = document.getElementById('form-result');
if (params.has('sent')) {
  result.textContent = 'Thank you — your enquiry has been submitted. Our team will be in touch shortly.';
  result.style.color = '#2d6b44';
  result.scrollIntoView({ behavior: 'smooth', block: 'center' });
  history.replaceState({}, '', location.pathname + '#enquire');
} else if (params.has('error')) {
  result.textContent = 'We could not submit your enquiry. Please try again or email us at sales@gonndalinfra.com';
  result.style.color = '#b03030';
  history.replaceState({}, '', location.pathname + '#enquire');
}
