<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · Archer LMS</title>
  <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-auth-page">
  <main class="ui-auth-shell">
    <section class="ui-auth-brand" aria-label="Archer Chess Academy">
      <div class="ui-auth-brand__identity">
        <div class="ui-auth-brand__logo">
          <img src="{{ asset('logo.png') }}" alt="Archer Chess Academy">
        </div>
        <div>
          <div class="ui-auth-brand__name">Archer LMS</div>
          <div class="ui-auth-brand__meta">Chess Academy Workspace</div>
        </div>
      </div>

      <div class="ui-auth-brand__message">
        <h1>Welcome back</h1>
        <p>Access your classes, curriculum, homework, tournaments and performance reports from one workspace.</p>
      </div>

      <div class="ui-auth-brand__footer">© {{ date('Y') }} Archer Chess Academy</div>
    </section>

    <section class="ui-auth-card">
      <header class="ui-auth-card__header">
        <h2 class="ui-auth-card__title">Sign in</h2>
        <p class="ui-auth-card__subtitle">Enter your account details to continue.</p>
      </header>

      @if($errors->any())
        <div class="ui-auth-error" role="alert">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.5" />
            <path d="M10 6.2V10.5M10 13.7V13.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
          </svg>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('login.post') }}" autocomplete="on" class="ui-auth-form">
        @csrf

        <label class="ui-field" for="username">
          <span class="ui-label">Username</span>
          <input class="ui-input" id="username" name="username" type="text" value="{{ old('username') }}" required autofocus placeholder="Enter username" autocomplete="username">
        </label>

        <label class="ui-field" for="password">
          <span class="ui-label">Password</span>
          <span class="ui-auth-password">
            <input class="ui-input" id="password" name="password" type="password" required placeholder="Enter password" autocomplete="current-password">
            <button type="button" class="ui-auth-password__toggle" data-password-toggle aria-label="Show password">Show</button>
          </span>
        </label>

        <label class="ui-auth-remember">
          <input type="checkbox" name="remember" id="remember" @checked(old('remember'))>
          <span>Remember me</span>
        </label>

        <button class="ui-button ui-button--primary ui-button--md ui-button--block" type="submit">Sign in</button>
      </form>
    </section>
  </main>

  <script>
    document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
      const password = document.getElementById('password');
      const show = password.type === 'password';
      password.type = show ? 'text' : 'password';
      this.textContent = show ? 'Hide' : 'Show';
      this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  </script>
</body>
</html>
