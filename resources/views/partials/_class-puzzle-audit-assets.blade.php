@once
@include('partials._chess-engine')
<script>
(() => {
  const parseJson = (value) => {
    try {
      return JSON.parse(value || '[]');
    } catch (error) {
      return [];
    }
  };

  const wireTabs = (root = document) => {
    root.querySelectorAll('[data-detail-tab]').forEach((button) => {
      if (button.dataset.detailTabReady === '1') return;
      button.dataset.detailTabReady = '1';

      button.addEventListener('click', () => {
        const details = button.closest('.classroom-details') || root;
        const target = button.dataset.detailTab;

        details.querySelectorAll('[data-detail-tab]').forEach((tab) => {
          const active = tab === button;
          tab.classList.toggle('is-active', active);
          tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        details.querySelectorAll('[data-detail-panel]').forEach((panel) => {
          panel.classList.toggle('is-active', panel.dataset.detailPanel === target);
        });
      });
    });
  };

  const init = (root = document) => {
    wireTabs(root);

    if (!window.MiniChess?.Board) return;

    root.querySelectorAll('[data-class-puzzle-board]').forEach((host) => {
      if (host.dataset.boardReady === '1') return;

      try {
        new window.MiniChess.Board(host, {
          size: 240,
          draggable: false,
          coords: true,
          position: host.dataset.fen,
          arrows: parseJson(host.dataset.arrows),
          highlights: parseJson(host.dataset.highlights),
        });
        host.dataset.boardReady = '1';
      } catch (error) {
        host.textContent = 'Position preview unavailable.';
        host.className = 'ui-meta-text';
      }
    });
  };

  window.ArcherClassPuzzleAudit = { init, wireTabs };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init(document), { once: true });
  } else {
    init(document);
  }
})();
</script>
@endonce
