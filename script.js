/* ═══════════════════════════════════════
   Gondal Infra — Hero Industrial Park 3
   script.js v4
═══════════════════════════════════════ */

// ── Nav toggle ──
const menu   = document.getElementById('menu');
const nav    = document.getElementById('nav');

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

// ── Footer year ──
document.getElementById('year').textContent = new Date().getFullYear();

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

// ── FAQ toggle icon ──
document.querySelectorAll('.faq-item').forEach(item => {
  item.addEventListener('toggle', () => {
    const t = item.querySelector('.faq-toggle');
    if (t) t.textContent = item.open ? '−' : '+';
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
function triggerDownloads() {
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
if (dlBtn) dlBtn.addEventListener('click', e => { e.preventDefault(); triggerDownloads(); });

const footerDl = document.getElementById('footer-download-images');
if (footerDl) footerDl.addEventListener('click', e => { e.preventDefault(); triggerDownloads(); });

// ── Form feedback ──
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
