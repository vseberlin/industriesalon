<?php
/**
 * Lightweight one-off upload endpoint for event media intake.
 */
declare(strict_types=1);

$eventSlug = $_ENV['EVENT_SLUG'] ?? 'event';
$storageRoot = '/event-drop-storage';
$incomingDir = $storageRoot . '/incoming';
$acceptedDir = $storageRoot . '/accepted';
$rejectedDir = $storageRoot . '/rejected';
$manifestFile = $storageRoot . '/manifests/upload-manifest.csv';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'm4v', 'mkv', 'webm', 'avi', 'zip'];
$allowedLicenses = [
    'all-rights-reserved' => 'All rights reserved',
    'cc-by-4.0' => 'CC BY 4.0',
    'cc-by-sa-4.0' => 'CC BY-SA 4.0',
    'cc-by-nc-4.0' => 'CC BY-NC 4.0',
    'cc0-1.0' => 'CC0 1.0',
];
$allowedLicenseValues = array_keys($allowedLicenses);
$maxUploadBytes = (int)($_ENV['EVENT_UPLOAD_MAX_BYTES'] ?? (4 * 1024 * 1024 * 1024));
$expectedToken = (string)($_ENV['EVENT_UPLOAD_TOKEN'] ?? '');
$adminToken = (string)($_ENV['EVENT_DROP_ADMIN_TOKEN'] ?? $expectedToken);
$publicToken = (string)($_ENV['EVENT_DROP_PUBLIC_TOKEN'] ?? $adminToken);
$uploadCode = (string)($_ENV['EVENT_DROP_UPLOAD_CODE'] ?? '');
$adminCode = (string)($_ENV['EVENT_DROP_ADMIN_CODE'] ?? '');
$publicCode = (string)($_ENV['EVENT_DROP_PUBLIC_CODE'] ?? '');
$allowInsecure = (string)($_ENV['EVENT_DROP_ALLOW_HTTP'] ?? '0') === '1';

foreach ([$incomingDir, $acceptedDir, $rejectedDir, $manifestFile] as $path) {
    $dir = strpos($path, '.csv') === false ? $path : dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
}

function reject(int $code, string $message, array $extra = []): never
{
    http_response_code($code);
    if ((($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['format'] ?? '') === 'json'))) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message] + $extra, JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo "<p style=\"color:#b00020;\">" . htmlspecialchars($message) . "</p>";
    if ($extra !== []) {
        echo '<pre>' . htmlspecialchars(print_r($extra, true)) . '</pre>';
    }
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function hasAuth(string $token, string $code, string $providedToken, string $providedCode): bool
{
    return (
        ($token !== '' && $providedToken !== '' && hash_equals($token, $providedToken)) ||
        ($code !== '' && $providedCode !== '' && hash_equals($code, $providedCode))
    );
}

function authQuery(string $token, string $code): string
{
    if ($code !== '') {
        return 'code=' . rawurlencode($code);
    }
    if ($token !== '') {
        return 'token=' . rawurlencode($token);
    }
    return '';
}

function isSafeFileName(string $name): bool
{
    return $name !== '' && preg_match('/^[A-Za-z0-9._-]{1,200}\.[A-Za-z0-9]{2,12}$/', $name) === 1;
}

function safePath(string $dir, string $file): string
{
    return rtrim($dir, '/') . '/' . basename($file);
}

function manifestLookup(string $manifestFile): array
{
    $rows = [];
    if (!is_file($manifestFile)) {
        return $rows;
    }

    $fp = fopen($manifestFile, 'r');
    if ($fp === false) {
        return $rows;
    }

    while (($row = fgetcsv($fp)) !== false) {
        if (count($row) >= 11) {
            $storedName = $row[3] ?? '';
            if ($storedName !== '') {
                $rows[$storedName] = [
                    'event_slug' => $row[0] ?? '',
                    'participant_id' => $row[1] ?? '',
                    'original_name' => $row[2] ?? '',
                    'extension' => $row[4] ?? '',
                    'size_bytes' => $row[5] ?? '',
                    'sha256' => $row[6] ?? '',
                    'remote_ip' => $row[7] ?? '',
                    'user_agent' => $row[8] ?? '',
                    'token_suffix' => $row[9] ?? '',
                    'uploaded_at' => $row[10] ?? '',
                    'attribution' => $row[11] ?? '',
                    'license' => $row[12] ?? '',
                    'consent' => $row[13] ?? '',
                    'uploader_email' => $row[14] ?? '',
                ];
            }
        }
    }
    fclose($fp);

    return $rows;
}

function listFiles(string $dir): array
{
    $items = [];
    if (!is_dir($dir)) {
        return $items;
    }
    foreach (glob($dir . '/*') as $path) {
        if (!is_file($path)) {
            continue;
        }
        $name = basename($path);
        $items[] = [
            'name' => $name,
            'size' => (string)filesize($path),
            'mtime' => filemtime($path),
            'mtime_h' => gmdate('c', (int)filemtime($path)),
        ];
    }
    usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $items;
}

function renderShared(string $acceptedDir, array $manifest, string $authToken, string $authCode): void
{
    $files = listFiles($acceptedDir);
    $query = authQuery($authToken, $authCode);
    $baseHref = '/event-drop/' . ($query === '' ? '' : '?' . $query);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Event media files</title>';
    echo '<h1>Available accepted files</h1>';

    if ($files === []) {
        echo '<p>No accepted files yet.</p>';
        echo '<p><a href="' . h($baseHref) . '">Back to upload</a></p>';
        exit;
    }

    echo '<table border="1" cellpadding="6" cellspacing="0">';
    echo '<tr><th>File</th><th>Event</th><th>Participant</th><th>Attribution</th><th>License</th><th>Uploaded</th><th>Size</th><th>Download</th></tr>';
    foreach ($files as $fileInfo) {
        $name = $fileInfo['name'];
        $meta = $manifest[$name] ?? [];
        $download = '/event-drop/?download=' . rawurlencode($name) . ($query === '' ? '' : '&' . $query);
        echo '<tr>';
        echo '<td>' . h($name) . '</td>';
        echo '<td>' . h((string)($meta['event_slug'] ?? '')) . '</td>';
        echo '<td>' . h((string)($meta['participant_id'] ?? '')) . '</td>';
        echo '<td>' . h((string)($meta['attribution'] ?? '')) . '</td>';
        echo '<td>' . h((string)($meta['license'] ?? '')) . '</td>';
        echo '<td>' . h((string)($meta['uploaded_at'] ?? $fileInfo['mtime_h'])) . '</td>';
        echo '<td>' . h($fileInfo['size']) . ' bytes</td>';
        echo '<td><a href="' . h($download) . '">Download</a></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<p><a href="' . h($baseHref) . '">Upload page</a></p>';
    exit;
}

function streamDownload(string $dir, string $file): never
{
    $source = safePath($dir, $file);
    if (!is_file($source) || !is_readable($source)) {
        reject(404, 'Requested file not found.');
    }

    $filename = basename($source);
    $safeName = str_replace(["\r", "\n", '"', '\\'], '', $filename);
    $mime = mime_content_type($source) ?: 'application/octet-stream';
    $size = (int)filesize($source);

    if (function_exists('ob_clean')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0');
    header('Pragma: public');

    $handle = fopen($source, 'rb');
    if ($handle === false) {
        reject(500, 'Unable to open file for download.');
    }

    while (!feof($handle)) {
        echo fread($handle, 1048576);
    }
    fclose($handle);
    exit;
}

function moveIncomingFile(string $incomingDir, string $targetDir, string $file, string $action): array
{
    $source = safePath($incomingDir, $file);
    if (!is_file($source)) {
        return ['ok' => false, 'message' => "Missing incoming file: {$file}"];
    }
    $target = safePath($targetDir, $file);
    if (file_exists($target)) {
        $target = $targetDir . '/' . substr(md5($file . microtime(true)), 0, 8) . '_' . $file;
    }
    if (!rename($source, $target)) {
        return ['ok' => false, 'message' => "Could not move {$file} to {$action}."];
    }
    return ['ok' => true, 'message' => "{$action}: {$file}"];
}

function syncAcceptedToWordPress(): array
{
    $wpLoad = dirname(__DIR__) . '/wp-load.php';
    if (!is_file($wpLoad)) {
        return ['ok' => false, 'message' => 'WordPress bootstrap not found.'];
    }

    if (!defined('ABSPATH')) {
        require_once $wpLoad;
    }

    if (!function_exists('event_drop_bridge_sync_from_intake')) {
        return ['ok' => false, 'message' => 'Event drop bridge is not loaded.'];
    }

    $result = event_drop_bridge_sync_from_intake();
    if (!is_array($result)) {
        return ['ok' => false, 'message' => 'Bridge sync returned no result.'];
    }

    return ['ok' => true, 'result' => $result];
}

function renderAdmin(string $incomingDir, string $acceptedDir, string $rejectedDir, string $manifestFile, string $authToken, string $authCode): void
{
    $manifest = manifestLookup($manifestFile);
    $incomingFiles = listFiles($incomingDir);
    $acceptedFiles = listFiles($acceptedDir);
    $rejectedFiles = listFiles($rejectedDir);
    $adminAuth = authQuery($authToken, $authCode);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Event media admin</title>';
    echo '<h1>Event media admin</h1>';
    echo '<p>Incoming: ' . count($incomingFiles) . ' files | ';
    echo 'Accepted: ' . count($acceptedFiles) . ' | ';
    echo 'Rejected: ' . count($rejectedFiles) . '</p>';

    echo '<h2>Incoming</h2>';
    if ($incomingFiles === []) {
        echo '<p>No incoming files.</p>';
    } else {
        echo '<table border="1" cellpadding="6" cellspacing="0">';
        echo '<tr><th>File</th><th>Event</th><th>Participant</th><th>Attribution</th><th>License</th><th>Uploaded</th><th>Size</th><th>SHA-256</th><th>Actions</th></tr>';
        foreach ($incomingFiles as $fileInfo) {
            $name = $fileInfo['name'];
            $meta = $manifest[$name] ?? [];
            $acceptLink = '/event-drop/?view=admin&action=accept&file=' . rawurlencode($name) . ($adminAuth === '' ? '' : '&' . $adminAuth);
            $rejectLink = '/event-drop/?view=admin&action=reject&file=' . rawurlencode($name) . ($adminAuth === '' ? '' : '&' . $adminAuth);
            echo '<tr>';
            echo '<td>' . h($name) . '</td>';
            echo '<td>' . h((string)($meta['event_slug'] ?? '')) . '</td>';
            echo '<td>' . h((string)($meta['participant_id'] ?? '')) . '</td>';
            echo '<td>' . h((string)($meta['attribution'] ?? '')) . '</td>';
            echo '<td>' . h((string)($meta['license'] ?? '')) . '</td>';
            echo '<td>' . h((string)($meta['uploaded_at'] ?? $fileInfo['mtime_h'])) . '</td>';
            echo '<td>' . h($fileInfo['size']) . '</td>';
            echo '<td>' . h((string)($meta['sha256'] ?? '')) . '</td>';
            echo '<td><a href="' . $acceptLink . '">accept</a> | <a href="' . $rejectLink . '">reject</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo '<h2>Accepted</h2>';
    if ($acceptedFiles === []) {
        echo '<p>None yet.</p>';
    } else {
        echo '<ul>';
        foreach ($acceptedFiles as $fileInfo) {
            echo '<li>' . h($fileInfo['name']) . ' (' . h($fileInfo['size']) . ' bytes)</li>';
        }
        echo '</ul>';
    }

    echo '<h2>Rejected</h2>';
    if ($rejectedFiles === []) {
        echo '<p>None yet.</p>';
    } else {
        echo '<ul>';
        foreach ($rejectedFiles as $fileInfo) {
            echo '<li>' . h($fileInfo['name']) . ' (' . h($fileInfo['size']) . ' bytes)</li>';
        }
        echo '</ul>';
    }
    echo '<p><a href="/event-drop/' . ($adminAuth === '' ? '' : '?' . $adminAuth) . '">Back to upload</a></p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $view = (string)($_GET['view'] ?? '');
    $token = (string)($_GET['token'] ?? '');
    $code = (string)($_GET['code'] ?? '');

    $downloadFile = (string)($_GET['download'] ?? '');
    if ($downloadFile !== '') {
        if (!hasAuth($publicToken, $publicCode, $token, $code)) {
            reject(403, 'Invalid or missing download token.');
        }
        if (!isSafeFileName($downloadFile)) {
            reject(400, 'Invalid file parameter.');
        }
        streamDownload($acceptedDir, $downloadFile);
    }

    if ($view === 'shared') {
        if (!hasAuth($publicToken, $publicCode, $token, $code)) {
            reject(403, 'Invalid or missing public token.');
        }
        $manifest = manifestLookup($manifestFile);
        renderShared($acceptedDir, $manifest, $publicToken, $publicCode);
    }

    if ($view === 'admin') {
        if (!hasAuth($adminToken, $adminCode, $token, $code)) {
            reject(403, 'Invalid or missing admin token.');
        }
        $adminQuery = authQuery($adminToken, $adminCode);

        $action = (string)($_GET['action'] ?? '');
        $result = '';
        if ($action !== '') {
            $file = (string)($_GET['file'] ?? '');
            if (!isSafeFileName($file)) {
                reject(400, 'Invalid file parameter.');
            }
            if ($action === 'accept') {
                $status = moveIncomingFile($incomingDir, $acceptedDir, $file, 'accepted');
                if (!$status['ok']) {
                    reject(500, $status['message']);
                }
                $sync = syncAcceptedToWordPress();
                if ($sync['ok']) {
                    $syncResult = $sync['result'];
                    $result = $status['message'] . ' | synced to WordPress: imported ' . (int)($syncResult['imported'] ?? 0) . ', skipped ' . (int)($syncResult['skipped'] ?? 0) . ', failed ' . (int)($syncResult['failed'] ?? 0);
                } else {
                    $result = $status['message'] . ' | WordPress sync not run: ' . (string)($sync['message'] ?? 'unknown error');
                }
            } elseif ($action === 'reject') {
                $status = moveIncomingFile($incomingDir, $rejectedDir, $file, 'rejected');
                if (!$status['ok']) {
                    reject(500, $status['message']);
                }
                $result = $status['message'];
            } else {
                reject(400, 'Unsupported action.');
            }
        }

        if ($result !== '') {
            echo '<!doctype html><meta charset="utf-8"><p>' . h($result) . '</p>';
            echo '<p><a href="/event-drop/?view=admin' . ($adminQuery === '' ? '' : '&' . $adminQuery) . '">Back to admin</a></p>';
            exit;
        }

        renderAdmin($incomingDir, $acceptedDir, $rejectedDir, $manifestFile, $adminToken, $adminCode);
    }

    $uploadCodeHint = hasAuth($expectedToken, $uploadCode, '', $code);
    $tokenHint = $uploadCodeHint ? 'Upload link accepted. No access token is needed.' : 'Access token required.';
    header('Content-Type: text/html; charset=utf-8');

    $licenseOptions = '';
    foreach ($allowedLicenses as $licenseValue => $licenseLabel) {
        $selected = $licenseValue === 'all-rights-reserved' ? ' selected' : '';
        $licenseOptions .= '<option value="' . h($licenseValue) . '"' . $selected . '>' . h($licenseLabel) . '</option>';
    }

    $eventInputValue = h((string)$eventSlug);
    $codeValue = h($uploadCodeHint ? $code : '');
    $allowedJson = json_encode(array_map(static fn($ext) => '.' . $ext, $allowedExtensions), JSON_UNESCAPED_SLASHES);
    $maxUploadJson = (string)$maxUploadBytes;

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Event media upload</title>';
    echo '<link href="https://releases.transloadit.com/uppy/v5.2.1/uppy.min.css" rel="stylesheet">';
    echo '<style>
        :root { color-scheme: light; --ink: #1e2528; --muted: #667176; --line: #d8dedc; --paper: #f7f5ef; --accent: #1f6f78; --accent-dark: #164d54; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: linear-gradient(135deg, #f7f5ef 0%, #e9f0ed 52%, #f4eadb 100%); }
        main { width: min(980px, calc(100% - 32px)); margin: 0 auto; padding: 32px 0 48px; }
        .drop-shell { display: grid; gap: 20px; }
        .drop-head { display: grid; gap: 8px; }
        h1 { margin: 0; font-size: clamp(2rem, 5vw, 4rem); line-height: 0.95; letter-spacing: 0; }
        .drop-note { margin: 0; color: var(--muted); font-size: 1rem; max-width: 62ch; }
        .drop-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 18px; background: rgba(255,255,255,0.74); border: 1px solid var(--line); border-radius: 8px; }
        .drop-field { display: grid; gap: 6px; font-size: 0.9rem; font-weight: 700; }
        .drop-field input, .drop-field select { min-height: 42px; border: 1px solid #b9c3c0; border-radius: 6px; padding: 0 10px; font: inherit; background: #fff; }
        .drop-field--wide { grid-column: 1 / -1; }
        .drop-check { grid-column: 1 / -1; display: flex; gap: 10px; align-items: flex-start; line-height: 1.35; color: var(--ink); font-weight: 600; }
        .drop-check input { margin-top: 3px; }
        #uppy { background: rgba(255,255,255,0.74); border: 1px solid var(--line); border-radius: 8px; padding: 10px; }
        .drop-links { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.92rem; color: var(--muted); }
        .drop-links a { color: var(--accent-dark); }
        .uppy-Dashboard-inner { border-radius: 6px !important; }
        @media (max-width: 680px) { main { width: min(100% - 20px, 980px); padding-top: 20px; } .drop-fields { grid-template-columns: 1fr; padding: 14px; } }
    </style></head><body><main><section class="drop-shell">';
    echo '<div class="drop-head"><h1>Event media upload</h1><p class="drop-note">' . h($tokenHint) . ' Add photos or videos, check the consent box, then start the upload. Files go to moderation before they appear in the event gallery.</p></div>';
    echo '<div class="drop-fields" id="event-drop-fields">';
    echo '<label class="drop-field">Participant name or initials<input id="participant_id" name="participant_id" autocomplete="name" required></label>';
    if (!$uploadCodeHint) {
        echo '<label class="drop-field">Access token<input id="token" name="token" required></label>';
        echo '<input type="hidden" id="code" value="">';
    } else {
        echo '<input type="hidden" id="token" value=""><input type="hidden" id="code" value="' . $codeValue . '">';
    }
    echo '<label class="drop-field">Event code<input id="event" name="event" value="' . $eventInputValue . '" required></label>';
    echo '<label class="drop-field">Credit / attribution<input id="attribution" name="attribution" required></label>';
    echo '<label class="drop-field">License<select id="license" name="license">' . $licenseOptions . '</select></label>';
    echo '<label class="drop-field">E-mail optional<input id="uploader_email" name="uploader_email" type="email"></label>';
    echo '<label class="drop-check"><input id="consent" name="consent" type="checkbox" value="1" required><span>I confirm that I have rights/consent for publishing these files and agree to the selected license.</span></label>';
    echo '</div><div id="uppy"></div>';
    echo '<div class="drop-links"><span>Allowed: ' . h(implode(', ', $allowedExtensions)) . '</span><a href="/event-drop/?view=shared&' . h(authQuery($publicToken, $publicCode)) . '">Accepted files</a>';
    if (authQuery($adminToken, $adminCode) !== '') {
        echo '<a href="/event-drop/?view=admin&' . h(authQuery($adminToken, $adminCode)) . '">Admin</a>';
    }
    echo '</div></section></main>';
    echo '<script type="module">
        import { Uppy, Dashboard, XHRUpload } from "https://releases.transloadit.com/uppy/v5.2.1/uppy.min.mjs";

        const allowedFileTypes = ' . $allowedJson . ';
        const maxFileSize = Number("' . $maxUploadJson . '");
        const fields = {
            participant_id: document.getElementById("participant_id"),
            token: document.getElementById("token"),
            code: document.getElementById("code"),
            event: document.getElementById("event"),
            attribution: document.getElementById("attribution"),
            license: document.getElementById("license"),
            uploader_email: document.getElementById("uploader_email"),
            consent: document.getElementById("consent"),
        };

        function collectMeta() {
            return {
                participant_id: fields.participant_id.value.trim(),
                token: fields.token.value.trim(),
                code: fields.code.value.trim(),
                event: fields.event.value.trim(),
                attribution: fields.attribution.value.trim(),
                license: fields.license.value,
                uploader_email: fields.uploader_email.value.trim(),
                consent: fields.consent.checked ? "1" : "",
                format: "json",
            };
        }

        function validateMeta(meta) {
            const missing = [];
            if (!meta.participant_id) missing.push("participant name");
            if (!meta.event) missing.push("event code");
            if (!meta.attribution) missing.push("credit / attribution");
            if (!meta.consent) missing.push("consent");
            if (!meta.code && !meta.token) missing.push("access token");
            return missing;
        }

        const uppy = new Uppy({
            id: "event-drop-uppy",
            autoProceed: false,
            allowMultipleUploadBatches: true,
            restrictions: {
                maxFileSize,
                allowedFileTypes,
            },
            onBeforeUpload(files) {
                const meta = collectMeta();
                const missing = validateMeta(meta);
                if (missing.length > 0) {
                    uppy.info("Please fill: " + missing.join(", "), "error", 6000);
                    return false;
                }
                for (const fileID of Object.keys(files)) {
                    uppy.setFileMeta(fileID, meta);
                }
                return true;
            },
        });

        uppy.use(Dashboard, {
            inline: true,
            target: "#uppy",
            proudlyDisplayPoweredByUppy: false,
            showProgressDetails: true,
            hideCancelButton: false,
            note: "Photos, videos, or ZIP archives. Each file is uploaded separately and reviewed before publishing.",
        });

        uppy.use(XHRUpload, {
            endpoint: "/event-drop/",
            method: "POST",
            fieldName: "media",
            formData: true,
            bundle: false,
            limit: 2,
            timeout: 0,
            responseType: "json",
            allowedMetaFields: ["participant_id", "token", "code", "event", "attribution", "license", "uploader_email", "consent", "format"],
        });

        uppy.on("upload-success", (file, response) => {
            const storedName = response?.body?.file || file.name;
            uppy.info("Uploaded: " + storedName + " - waiting for approval", "success", 5000);
        });
    </script></body></html>';
    exit;
}

if (!hasAuth(
    $expectedToken,
    $uploadCode,
    (string)($_POST['token'] ?? ''),
    (string)($_POST['code'] ?? (string)($_GET['code'] ?? ''))
)) {
    reject(403, 'Invalid or missing upload auth.');
}

if (!isset($_FILES['media'])) {
    reject(400, 'No file uploaded under field name \"media\".');
}

$token = (string)($_POST['token'] ?? ($_POST['code'] ?? ($_GET['code'] ?? '')));
$event = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string)($_POST['event'] ?? $eventSlug));
$participant = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string)($_POST['participant_id'] ?? '')); 
if ($event === '') {
    $event = $eventSlug;
}
if ($participant === '') {
    $participant = 'unknown';
}
$attribution = trim((string)($_POST['attribution'] ?? ''));
if ($attribution === '') {
    reject(400, 'Attribution is required.');
}
$license = (string)($_POST['license'] ?? '');
if (!in_array($license, $allowedLicenseValues, true)) {
    reject(400, 'Unsupported license.');
}
$consent = (string)($_POST['consent'] ?? '');
if ($consent !== '1') {
    reject(400, 'Consent is required before upload.');
}
$uploaderEmail = trim((string)($_POST['uploader_email'] ?? ''));
if ($participant === '') {
    $participant = 'unknown';
}

$upload = $_FILES['media'];
if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL => 'Upload was partial.',
        UPLOAD_ERR_NO_FILE => 'No file was sent.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory.',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write file.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension.',
    ];
    $msg = $codes[(int)$upload['error']] ?? 'Unknown upload error.';
    reject(400, $msg);
}

if ((int)$upload['size'] <= 0) {
    reject(400, 'Uploaded file has no content.');
}
if ((int)$upload['size'] > $maxUploadBytes) {
    reject(413, 'File exceeds server-side cap.');
}

$originalName = (string)($upload['name'] ?? '');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
    reject(400, 'File extension not allowed.', ['allowed' => $allowedExtensions]);
}

$rawMime = mime_content_type((string)$upload['tmp_name']) ?: 'application/octet-stream';
$allowedMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'video/x-m4v',
    'application/zip', 'application/x-zip-compressed'
];
if ($rawMime === false || (!in_array($rawMime, $allowedMimes, true) && !in_array($extension, ['webm', 'gif', 'zip'], true))) {
    reject(400, 'Uploaded media MIME type not accepted.', ['mime' => $rawMime]);
}

$timestamp = gmdate('Ymd_His');
$random = bin2hex(random_bytes(4));
$storedName = sprintf('%s_%s_%s_%s.%s', $event, $participant, $timestamp, $random, $extension);
$destination = $incomingDir . '/' . $storedName;

if (!move_uploaded_file((string)$upload['tmp_name'], $destination)) {
    reject(500, 'Unable to store uploaded file.');
}

$size = (string)filesize($destination);
$hash = hash_file('sha256', $destination);
$payload = [
    'event_slug',
    'participant_id',
    'original_name',
    'stored_name',
    'extension',
    'size_bytes',
    'sha256',
    'remote_ip',
    'user_agent',
    'token_suffix',
    'uploaded_at',
    'attribution',
    'license',
    'consent',
    'uploader_email',
];
if (!file_exists($manifestFile) || filesize($manifestFile) === 0) {
    file_put_contents($manifestFile, implode(',', $payload) . "\n", LOCK_EX);
}
    $line = [
        $event,
        $participant,
        $originalName,
        $storedName,
        $extension,
    $size,
    $hash,
        $_SERVER['REMOTE_ADDR'] ?? '',
        str_replace(["\r", "\n"], ' ', $_SERVER['HTTP_USER_AGENT'] ?? ''),
        substr($token, -8),
        gmdate('c'),
        $attribution,
        $license,
        $consent,
        $uploaderEmail,
    ];
$fp = fopen($manifestFile, 'a');
if ($fp !== false) {
    fputcsv($fp, $line);
    fclose($fp);
}

if (($mediaType = $_POST['format'] ?? '') === 'json' || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'file' => $storedName,
        'path' => $destination,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><h1>Upload received</h1>';
echo '<p>Stored as: ' . htmlspecialchars($storedName) . '</p>';
?>
