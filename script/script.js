// script.js
// Gestion du zoom sur l'image de circuit dans la page détail GP

document.addEventListener('DOMContentLoaded', function () {
  // On cherche l'image de circuit uniquement sur la page de détail
  const wrapper = document.querySelector('.circuit-detail-image-wrapper');
  if (!wrapper) return; // pas sur la page GP -> on ne fait rien

  const img = wrapper.querySelector('img');
  if (!img) return;

  // Création de la modale dynamiquement (HTML injecté par JS)
  const modal = document.createElement('div');
  modal.className = 'image-modal';
  modal.setAttribute('aria-hidden', 'true');

  modal.innerHTML = `
    <div class="image-modal-backdrop"></div>
    <div class="image-modal-content">
      <button class="image-modal-close" type="button" aria-label="Fermer l’image">&times;</button>
      <img src="" alt="Tracé du circuit en grand">
    </div>
  `;

  document.body.appendChild(modal);

  const modalImg = modal.querySelector('img');
  const closeBtn = modal.querySelector('.image-modal-close');
  const backdrop = modal.querySelector('.image-modal-backdrop');

  function openModal() {
    modalImg.src = img.src;
    modalImg.alt = img.alt || 'Tracé du circuit en grand';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  // Ouverture en cliquant sur l'image
  wrapper.addEventListener('click', openModal);

  // Fermeture : croix, clic sur le fond sombre, touche Échap
  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
  }
  if (backdrop) {
    backdrop.addEventListener('click', closeModal);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeModal();
    }
  });
});
