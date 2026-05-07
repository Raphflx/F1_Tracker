// Zoom sur l’image de circuit (page détail GP)
document.addEventListener(‘DOMContentLoaded’, function () {
  const img = document.querySelector(‘.circuit-img’);
  if (!img) return;

  const modal = document.createElement(‘div’);
  modal.className = ‘image-modal’;
  modal.setAttribute(‘aria-hidden’, ‘true’);
  modal.innerHTML = `
    <div class="image-modal-backdrop"></div>
    <div class="image-modal-content">
      <button class="image-modal-close" type="button" aria-label="Fermer l’image">&times;</button>
      <img src="" alt="">
    </div>
  `;
  document.body.appendChild(modal);

  const modalImg = modal.querySelector(‘img’);
  const closeBtn = modal.querySelector(‘.image-modal-close’);
  const backdrop = modal.querySelector(‘.image-modal-backdrop’);

  function openModal() {
    modalImg.src = img.src;
    modalImg.alt = img.alt;
    modal.classList.add(‘open’);
    modal.setAttribute(‘aria-hidden’, ‘false’);
  }

  function closeModal() {
    modal.classList.remove(‘open’);
    modal.setAttribute(‘aria-hidden’, ‘true’);
  }

  img.addEventListener(‘click’, openModal);
  closeBtn.addEventListener(‘click’, closeModal);
  backdrop.addEventListener(‘click’, closeModal);
  document.addEventListener(‘keydown’, (e) => { if (e.key === ‘Escape’) closeModal(); });
});
