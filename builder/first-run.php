<?php
$baseDir = __DIR__;
$configFile = $baseDir . '/config/config.json';
$templateSource = $baseDir . '/templates/fjolsenbanden';
$defaultProjectDir = $baseDir . '/projects/default';
$projectTemplateDir = $defaultProjectDir . '/template';
$projectJsonPath = $defaultProjectDir . '/project.json';
$streamerMediaDir = $defaultProjectDir . '/.streamer/media';
$streamerPreviewDir = $defaultProjectDir . '/.streamer/previews';

$currentConfig = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

if (!empty($currentConfig['installed']) && ($currentConfig['firstRun'] ?? false) === false) {
    header('Location: index.php');
    exit;
}

$errors = [];
$installationComplete = false;

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Kunne ikke opprette mappe: ' . $path);
    }
}

function recursiveCopy(string $src, string $dst): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('Finner ikke kilde-mappen: ' . $src);
    }

    ensureDirectory($dst);
    $dir = opendir($src);

    if ($dir === false) {
        throw new RuntimeException('Kunne ikke åpne mappe: ' . $src);
    }

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourcePath = $src . '/' . $file;
        $destinationPath = $dst . '/' . $file;

        if (is_dir($sourcePath)) {
            recursiveCopy($sourcePath, $destinationPath);
        } else {
            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Kunne ikke kopiere ' . $sourcePath . ' til ' . $destinationPath);
            }
        }
    }

    closedir($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirmPassword === '') {
        $errors[] = 'Admin-passord må fylles ut.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passordene må være like.';
    }

    if (empty($errors)) {
        try {
            ensureDirectory(dirname($configFile));
            ensureDirectory($defaultProjectDir);

            recursiveCopy($templateSource, $projectTemplateDir);
            ensureDirectory($streamerMediaDir);
            ensureDirectory($streamerPreviewDir);

            $projectData = [
                'name' => 'Fjolsenbanden',
                'homePage' => 'index.html',
                'templatePath' => 'projects/default/template',
                'pages' => [
                    [
                        'id' => 'index',
                        'title' => 'Home',
                        'file' => 'index.html',
                        'isHome' => true,
                        'preview' => '.streamer/previews/index.png',
                    ],
                ],
            ];

            file_put_contents(
                $projectJsonPath,
                json_encode($projectData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $newConfig = [
                'projectName' => 'Fjolsenbanden',
                'defaultProject' => 'projects/default/template',
                'projectMeta' => 'projects/default/project.json',
                'language' => $currentConfig['language'] ?? 'en',
                'publishPath' => $currentConfig['publishPath'] ?? 'public',
                'assetPaths' => $currentConfig['assetPaths'] ?? [
                    'css' => 'css',
                    'js' => 'js',
                    'images' => 'images',
                ],
                'googleFonts' => $currentConfig['googleFonts'] ?? [],
                'pageContainer' => $currentConfig['pageContainer'] ?? 'body',
                'systemSettings' => $currentConfig['systemSettings'] ?? [
                    'language' => 'en',
                    'tips' => true,
                    'updater' => false,
                ],
                'adminPassword' => password_hash($password, PASSWORD_BCRYPT),
                'installed' => true,
                'firstRun' => false,
            ];

            file_put_contents(
                $configFile,
                json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $installationComplete = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Streamer Site Builder – Førstegangsinstallasjon</title>
  <style>
    :root {
      --bg: #0b1224;
      --panel: #0f172a;
      --panel-strong: #111827;
      --accent: #f97316;
      --text: #e5e7eb;
      --muted: #9ca3af;
      --border: #1f2937;
      --radius: 18px;
      --glow: 0 20px 60px rgba(249, 115, 22, 0.22);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: radial-gradient(circle at 20% 20%, rgba(249, 115, 22, 0.12), transparent 25%),
        radial-gradient(circle at 80% 0%, rgba(59, 130, 246, 0.08), transparent 20%),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px;
    }

    .wizard-shell {
      width: 100%;
      max-width: 760px;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 22px;
      padding: 36px;
      box-shadow: var(--glow);
    }

    .wizard-header h1 {
      margin: 0 0 8px;
      font-size: 28px;
    }

    .wizard-header p {
      margin: 0;
      color: var(--muted);
    }

    .steps-indicator {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 26px 0;
    }

    .step-indicator {
      background: var(--panel-strong);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 10px 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--muted);
      font-weight: 600;
    }

    .step-indicator.active {
      color: var(--text);
      border-color: var(--accent);
      box-shadow: 0 0 0 1px rgba(249, 115, 22, 0.4);
    }

    .step-number {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(249, 115, 22, 0.16);
      color: var(--accent);
      display: grid;
      place-items: center;
      font-weight: 700;
      border: 1px solid rgba(249, 115, 22, 0.32);
    }

    .wizard-panel {
      background: var(--panel-strong);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 24px;
    }

    .wizard-panel h2 {
      margin: 0 0 12px;
    }

    .wizard-panel p {
      margin: 0 0 18px;
      color: var(--muted);
    }

    .form-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 16px;
    }

    label {
      font-weight: 600;
      color: var(--text);
    }

    input[type="password"] {
      background: #0b1224;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 14px;
      color: var(--text);
      font-size: 16px;
    }

    .wizard-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 18px;
    }

    .btn {
      appearance: none;
      border: none;
      border-radius: 12px;
      padding: 12px 18px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.08s ease, box-shadow 0.12s ease, background 0.12s ease;
    }

    .btn-primary {
      background: linear-gradient(135deg, #f97316, #fb923c);
      color: #0b0f1a;
      box-shadow: var(--glow);
    }

    .btn-secondary {
      background: #0b1224;
      color: var(--text);
      border: 1px solid var(--border);
    }

    .btn:active {
      transform: translateY(1px);
    }

    .wizard-step {
      display: none;
    }

    .wizard-step.active {
      display: block;
    }

    .checklist {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 10px;
    }

    .checklist li {
      display: flex;
      gap: 10px;
      align-items: center;
      background: #0b1224;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 14px;
      color: var(--text);
      font-weight: 600;
    }

    .check-icon {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: rgba(52, 211, 153, 0.18);
      color: #34d399;
      display: grid;
      place-items: center;
      font-weight: 800;
      border: 1px solid rgba(52, 211, 153, 0.3);
    }

    .errors {
      background: rgba(248, 113, 113, 0.12);
      border: 1px solid rgba(248, 113, 113, 0.6);
      color: #fecdd3;
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 16px;
    }

    .success {
      text-align: center;
    }

    .success h2 {
      margin-top: 0;
    }

    .success p {
      color: var(--muted);
      margin-bottom: 22px;
    }
  </style>
</head>
<body>
  <div class="wizard-shell">
    <div class="wizard-header">
      <h1>Velkommen til Streamer Site Builder</h1>
      <p>La oss sette opp systemet ditt. Dette tar under 30 sekunder.</p>
    </div>

    <?php if (!empty($errors)) : ?>
      <div class="errors">
        <?php foreach ($errors as $error) : ?>
          <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($installationComplete) : ?>
      <div class="wizard-panel success">
        <h2>Streamer Site Builder er klart!</h2>
        <p>Standardprosjektet er på plass. Start editoren for å bygge videre.</p>
        <form method="get" action="index.php">
          <input type="hidden" name="page" value="/projects/default/template/index.html" />
          <button class="btn btn-primary" type="submit">Start visual editor</button>
        </form>
      </div>
    <?php else : ?>
      <div class="steps-indicator">
        <div class="step-indicator active" data-step="1">
          <div class="step-number">1</div>
          <div>Opprett admin-passord</div>
        </div>
        <div class="step-indicator" data-step="2">
          <div class="step-number">2</div>
          <div>Sett opp standardprosjekt</div>
        </div>
        <div class="step-indicator" data-step="3">
          <div class="step-number">3</div>
          <div>Ferdig</div>
        </div>
      </div>

      <form id="install-form" method="post">
        <div class="wizard-step active" data-step="1">
          <div class="wizard-panel">
            <h2>Opprett admin-passord</h2>
            <p>Dette passordet sikrer editoren din.</p>
            <div class="form-field">
              <label for="password">Admin passord</label>
              <input required type="password" id="password" name="password" autocomplete="new-password" />
            </div>
            <div class="form-field">
              <label for="confirm_password">Gjenta passord</label>
              <input required type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" />
            </div>
            <div class="wizard-actions">
              <button class="btn btn-primary" data-next="2" type="button">Fortsett →</button>
            </div>
          </div>
        </div>

        <div class="wizard-step" data-step="2">
          <div class="wizard-panel">
            <h2>Oppretter standardprosjekt</h2>
            <p>Installerer Fjolsenbanden-demoen og oppretter filstrukturen.</p>
            <ul class="checklist">
              <li><span class="check-icon">✔</span>Standard prosjekt installert</li>
              <li><span class="check-icon">✔</span>Fjolsenbanden-demo importert</li>
              <li><span class="check-icon">✔</span>Filstruktur opprettet</li>
              <li><span class="check-icon">✔</span>Media-bibliotek initialisert</li>
            </ul>
            <div class="wizard-actions">
              <button class="btn btn-secondary" data-prev="1" type="button">Tilbake</button>
              <button class="btn btn-primary" data-next="3" type="button">Fortsett →</button>
            </div>
          </div>
        </div>

        <div class="wizard-step" data-step="3">
          <div class="wizard-panel">
            <h2>Streamer Site Builder er klart!</h2>
            <p>Trykk på knappen under for å starte editoren.</p>
            <div class="wizard-actions">
              <button class="btn btn-secondary" data-prev="2" type="button">Tilbake</button>
              <button class="btn btn-primary" type="submit">Start visual editor</button>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <script>
    const steps = Array.from(document.querySelectorAll('[data-step]'));
    const indicators = Array.from(document.querySelectorAll('.step-indicator'));

    function setStep(step) {
      steps.forEach((panel) => {
        const isActive = panel.getAttribute('data-step') === String(step);
        panel.classList.toggle('active', isActive);
      });

      indicators.forEach((indicator) => {
        const isActive = indicator.getAttribute('data-step') === String(step);
        indicator.classList.toggle('active', isActive);
      });
    }

    function handleNext(targetStep) {
      const password = document.getElementById('password');
      const confirm = document.getElementById('confirm_password');

      if (Number(targetStep) === 2) {
        if (!password.value || !confirm.value) {
          alert('Fyll inn begge passordfeltene.');
          return;
        }

        if (password.value !== confirm.value) {
          alert('Passordene må være like.');
          return;
        }
      }

      setStep(targetStep);
    }

    document.querySelectorAll('[data-next]').forEach((btn) => {
      btn.addEventListener('click', () => handleNext(btn.getAttribute('data-next')));
    });

    document.querySelectorAll('[data-prev]').forEach((btn) => {
      btn.addEventListener('click', () => setStep(btn.getAttribute('data-prev')));
    });
  </script>
</body>
</html>
