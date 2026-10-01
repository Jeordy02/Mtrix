/* M'trix — réservation et redirection vers le paiement FedaPay. */

(function () {
  'use strict';

  var radios = document.querySelectorAll('.teintes__opt input');
  var photos = document.querySelectorAll('.res__photo');
  var legende = document.querySelector('[data-nom-couleur]');
  var bouton = document.getElementById('btnPayer');
  var zoneAttente = document.getElementById('zoneAttente');
  var sondage = null;
  var occupe = false;

  function couleurChoisie() {
    for (var i = 0; i < radios.length; i++) if (radios[i].checked) return radios[i];
    return radios[0] || null;
  }

  function afficher(radio) {
    if (!radio) return;
    for (var i = 0; i < photos.length; i++) {
      photos[i].classList.toggle('est-vue', photos[i].dataset.pour === radio.value);
    }
    if (legende) legende.textContent = radio.dataset.nom || '';
  }

  for (var i = 0; i < radios.length; i++) {
    radios[i].addEventListener('change', function () { afficher(this); });
  }
  afficher(couleurChoisie());

  /* Avant, ça écrasait .payer__sous — le texte « paiement sécurisé par
     FedaPay » disparaîssait définitivement, et rien n'était annoncé.
     Le message va maintenant dans sa propre zone, en role=alert. */
  function annoncer(texte) {
    var zone = document.getElementById('payerAnnonce');
    if (zone) zone.textContent = texte;
  }

  function rendreLaMain(message) {
    occupe = false;
    if (bouton) {
      bouton.classList.remove('est-occupe');
      bouton.disabled = false;
    }
    if (message) annoncer(message);
  }

  function rediriger(url) {
    try { sessionStorage.removeItem('mtx_jeton'); } catch (e) {}
    window.location.assign(url);
  }

  function arreterSondage() {
    if (sondage) { clearInterval(sondage); sondage = null; }
  }

  function afficherAttente(jeton, position, verrouille) {
    try { sessionStorage.setItem('mtx_jeton', jeton); } catch (e) {}
    if (!zoneAttente || !bouton) return;

    zoneAttente.hidden = false;
    bouton.hidden = true;
    var texte = zoneAttente.querySelector('[data-attente-texte]');
    if (texte) {
      texte.textContent = verrouille
        ? 'Ce palier n’est pas encore lancé. Tu es en position ' + position + ' dans la file.'
        : (position === 1
          ? 'Tu es le prochain. Dès qu’une place se libère, elle est à toi.'
          : 'Tu es en position ' + position + ' dans la file.');
    }
    arreterSondage();
    sonderFile(jeton);
    sondage = setInterval(function () { sonderFile(jeton); }, 4000);
  }

  function traiterReponse(reponse) {
    if (reponse && reponse.ok && reponse.url) {
      arreterSondage();
      rediriger(reponse.url);
      return;
    }
    if (reponse && reponse.patiente && reponse.jeton) {
      afficherAttente(reponse.jeton, reponse.position, !!reponse.verrouille);
      return;
    }

    arreterSondage();
    try { sessionStorage.removeItem('mtx_jeton'); } catch (e) {}
    if (zoneAttente) zoneAttente.hidden = true;
    if (bouton) bouton.hidden = false;
    rendreLaMain((reponse && reponse.erreur) || 'Impossible de lancer le paiement. Réessaie.');
  }

  function reserver(jeton) {
    var radio = couleurChoisie();
    if (!radio || !bouton) return;

    occupe = true;
    bouton.classList.add('est-occupe');
    bouton.disabled = true;

    var corps = new FormData();
    corps.append('couleur', radio.value);
    if (jeton) corps.append('jeton', jeton);

    fetch('commander.php?reserver=1', { method: 'POST', body: corps })
      .then(function (r) { return r.json(); })
      .then(traiterReponse)
      .catch(function () {
        rendreLaMain('La connexion a été coupée. Réessaie.');
      });
  }

  function sonderFile(jeton) {
    var corps = new FormData();
    corps.append('jeton', jeton);
    fetch('commander.php?attente=1', { method: 'POST', body: corps })
      .then(function (r) { return r.json(); })
      .then(function (reponse) {
        if (reponse && reponse.ok && reponse.url) {
          arreterSondage();
          rediriger(reponse.url);
          return;
        }
        if (reponse && reponse.patiente) {
          var texte = zoneAttente && zoneAttente.querySelector('[data-attente-texte]');
          if (texte) {
            texte.textContent = reponse.position === 1
              ? 'Tu es le prochain. Dès qu’une place se libère, elle est à toi.'
              : 'Tu es en position ' + reponse.position + ' dans la file.';
          }
          return;
        }
        traiterReponse(reponse);
      })
      .catch(function () {});
  }

  if (!bouton) return;

  bouton.addEventListener('click', function () {
    if (!occupe) reserver(null);
  });

  var jetonRepris = null;
  try { jetonRepris = sessionStorage.getItem('mtx_jeton'); } catch (e) {}
  if (jetonRepris) reserver(jetonRepris);
})();
