@once
  @push('scripts')
  <script>
  (() => {
    if (window.ArcherPeopleSearchReady) return;
    window.ArcherPeopleSearchReady = true;

    document.addEventListener('input', (event) => {
      const input = event.target.closest('[data-people-search-input]');
      if (!input) return;

      const form = input.form;
      if (!form) return;
      form.requestSubmit ? form.requestSubmit() : form.submit();
    });
  })();

  (() => {
    if (window.ArcherDashboardHubReady) return;
    window.ArcherDashboardHubReady = true;

    const HUB_ID = 'dashboard-hub';

    const executeScripts = (root) => {
      root.querySelectorAll('script').forEach((oldScript) => {
        const script = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => script.setAttribute(attr.name, attr.value));
        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
      });
    };

    const refreshHub = async () => {
      const hub = document.getElementById(HUB_ID);
      if (!hub) return;

      const response = await fetch(window.location.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        window.location.reload();
        return;
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const freshHub = doc.getElementById(HUB_ID);
      if (!freshHub) {
        window.location.reload();
        return;
      }

      hub.innerHTML = freshHub.innerHTML;
      executeScripts(hub);
    };

    const renderErrors = (form, payload) => {
      const errors = payload?.errors || {};
      const messages = Object.values(errors).flat();
      let box = form.querySelector('[data-form-errors]');
      if (!box) {
        box = document.createElement('div');
        box.setAttribute('data-form-errors', '1');
        box.className = 'alert alert-danger mb-3';
        form.insertBefore(box, form.firstElementChild?.nextElementSibling || form.firstElementChild);
      }
      if (!messages.length) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
      }
      box.style.display = 'block';
      box.innerHTML = `<ul style="margin:0;padding-left:18px">${messages.map(message => `<li>${message}</li>`).join('')}</ul>`;
    };

    document.addEventListener('submit', async (event) => {
      const form = event.target.closest('form[data-dashboard-async]');
      if (!form || !document.getElementById(HUB_ID)) return;

      event.preventDefault();

      const submitter = event.submitter;
      if (submitter) submitter.disabled = true;

      renderErrors(form, { errors: {} });

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || window.CSRF,
            'Accept': 'application/json',
          },
          body: new FormData(form),
          credentials: 'same-origin',
        });

        if (response.status === 422) {
          renderErrors(form, await response.json());
          return;
        }

        if (!response.ok) {
          window.location.reload();
          return;
        }

        const payload = await response.json();
        form.closest('.modal-bg')?.style && (form.closest('.modal-bg').style.display = 'none');
        window.ArcherToast?.show(payload.message || 'Saved.', 'success');
        await refreshHub();
      } catch (error) {
        window.ArcherToast?.show('Something went wrong. Please try again.', 'error');
      } finally {
        if (submitter) submitter.disabled = false;
      }
    });
  })();
  </script>
  @endpush
@endonce
