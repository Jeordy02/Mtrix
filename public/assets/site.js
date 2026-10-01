/* M'trix — entrée décodée, choix du coloris, portée en tranches, apparitions */
(function () {
  var reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ==========================================================
     Entrée : les lettres se décodent, puis l'écran s'ouvre
     ========================================================== */
  var intro  = document.getElementById('intro');
  var motEl  = document.getElementById('introMot');
  var skip   = document.getElementById('introSkip');
  var trait  = intro ? intro.querySelector('.intro__trait') : null;
  var MOT    = ['M', "’", 'T', 'R', 'I', 'X'];
  var GLYPHES = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#%&@*/<>+=';
  var minuteurs = [];

  function fermerIntro() {
    if (!intro || intro.classList.contains('is-gone')) return;
    minuteurs.forEach(clearTimeout);
    minuteurs.length = 0;
    intro.classList.add('is-gone');
    document.body.classList.remove('intro-on');
    setTimeout(function () { if (intro.parentNode) intro.parentNode.removeChild(intro); }, 950);
  }

  if (intro && motEl) {
    if (reduit) {
      MOT.forEach(function (l) {
        var s = document.createElement('span');
        if (l === "’") s.className = 'apos';
        s.textContent = l;
        motEl.appendChild(s);
      });
      fermerIntro();
    } else {
      var spans = [];
      MOT.forEach(function (lettre) {
        var s = document.createElement('span');
        if (lettre === "’") { s.className = 'apos'; s.textContent = lettre; }
        else { s.textContent = GLYPHES[Math.floor(Math.random() * GLYPHES.length)]; }
        motEl.appendChild(s);
        spans.push({ el: s, vrai: lettre, fige: lettre === "’" });
      });

      var duree = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--intro')) || 2600;

      var brouillage = setInterval(function () {
        spans.forEach(function (s) {
          if (s.fige) return;
          s.el.textContent = GLYPHES[Math.floor(Math.random() * GLYPHES.length)];
        });
      }, 55);
      minuteurs.push(brouillage);

      spans.forEach(function (s, i) {
        if (s.fige) return;
        minuteurs.push(setTimeout(function () {
          s.fige = true;
          s.el.textContent = s.vrai;
        }, 420 + i * 135));
      });

      minuteurs.push(setTimeout(function () {
        clearInterval(brouillage);
        spans.forEach(function (s) { s.el.textContent = s.vrai; });
        motEl.classList.add('is-glitch');
        if (trait) trait.classList.add('is-on');
      }, 420 + MOT.length * 135));

      minuteurs.push(setTimeout(fermerIntro, duree));
      if (skip) skip.addEventListener('click', fermerIntro);
      intro.addEventListener('click', function (e) { if (e.target !== skip) fermerIntro(); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') fermerIntro();
      });
    }
  } else {
    document.body.classList.remove('intro-on');
  }

  /* Filet de sécurité : la page ne reste jamais bloquée */
  setTimeout(function () { document.body.classList.remove('intro-on'); }, 9000);

  /* ==========================================================
     Choix du coloris
     ========================================================== */
  var picker = document.getElementById('picker');
  var nom    = document.getElementById('pickerNom');
  var imgs   = document.querySelectorAll('.stage__img');
  var dots   = picker ? picker.querySelectorAll('.picker__dot') : [];

  if (picker) {
    picker.addEventListener('click', function (e) {
      var b = e.target.closest('.picker__dot');
      if (!b) return;
      var cible = b.getAttribute('data-cible');
      imgs.forEach(function (im) { im.classList.toggle('is-on', im.getAttribute('data-couleur') === cible); });
      dots.forEach(function (d) {
        var on = d === b;
        d.classList.toggle('is-on', on);
        d.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      if (nom) nom.textContent = b.getAttribute('data-nom') || '';
    });
  }

  /* ==========================================================
     Le bandeau défilant : on doit pouvoir l'arrêter
     ---------------------------------------------------------
     Le survol seul ne servait à rien au clavier ni au doigt.
     ========================================================== */
  (function () {
    var ticker = document.getElementById('ticker');
    var bouton = document.getElementById('tickerPause');
    if (!ticker || !bouton) return;

    var etiquette = bouton.querySelector('[data-pause-txt]');

    function appliquer(fige) {
      ticker.classList.toggle('est-fige', fige);
      bouton.setAttribute('aria-pressed', fige ? 'true' : 'false');
      if (etiquette) etiquette.textContent = fige ? 'Relancer' : 'Figer';
    }

    /* Si la personne a demandé moins d'animations, il part déjà arrêté. */
    appliquer(reduit);

    bouton.addEventListener('click', function () {
      appliquer(!ticker.classList.contains('est-fige'));
    });
  })();

  /* ==========================================================
     Portée : l'image se recompose en tranches, très vite
     ========================================================== */
  (function () {
    var carte = document.getElementById('defil');
    if (!carte) return;

    var tranches = carte.querySelectorAll('.defil__tranche');
    var num = document.getElementById('defilNum');
    var liste;
    try { liste = JSON.parse(carte.getAttribute('data-photos') || '[]'); }
    catch (e) { return; }
    if (!liste.length || !tranches.length) return;

    var PAUSE  = 900;   // une photo toutes les 0,9 s
    var DECALE = 55;    // écart d'une tranche à la suivante
    var i = -1, minuteur = null, attentes = [];

    /* Préchargement : la bascule ne doit jamais montrer de vide */
    liste.forEach(function (u) { var im = new Image(); im.src = u; });

    function poser(n) {
      i = (n + liste.length) % liste.length;
      var url = liste[i];
      attentes.forEach(clearTimeout);
      attentes.length = 0;
      tranches.forEach(function (t, k) {
        attentes.push(setTimeout(function () {
          t.classList.remove('bascule');
          void t.offsetWidth;
          t.style.backgroundImage = 'url("' + url + '")';
          t.classList.add('bascule');
        }, k * DECALE));
      });
      if (num) num.textContent = ('0' + (i + 1)).slice(-2);
    }

    function arreter() { if (minuteur) { clearInterval(minuteur); minuteur = null; } }
    function lancer()  { arreter(); minuteur = setInterval(function () { poser(i + 1); }, PAUSE); }

    poser(0);
    if (reduit) return;
    lancer();

    carte.addEventListener('mouseenter', arreter);
    carte.addEventListener('mouseleave', lancer);
    carte.addEventListener('click', function () { poser(i + 1); lancer(); });

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) { lancer(); } else { arreter(); } });
      }, { threshold: 0.2 }).observe(carte);
    }
  })();

  /* ==========================================================
     Apparitions au défilement
     ========================================================== */
  var cibles = document.querySelectorAll('.strip__item, .teinte, .cinq__titre, .cta h2, .cta p, .cta__btn');
  if (reduit || !('IntersectionObserver' in window)) {
    cibles.forEach(function (el) { el.classList.add('is-in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      en.target.classList.add('is-in');
      io.unobserve(en.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
  cibles.forEach(function (el, i) {
    el.classList.add('reveal');
    el.style.transitionDelay = ((i % 4) * 70) + 'ms';
    io.observe(el);
  });
})();
