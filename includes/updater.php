<?php
/**
 * AuctionKai Update Checker
 * Checks GitHub Releases API for newer versions.
 * Results cached in DB settings table for 1 hour.
 */

function checkForUpdates(PDO $db): array {
    $default = [
        'has_update' => false,
        'current_version' => APP_VERSION,
        'latest_version' => APP_VERSION,
        'release_name' => '',
        'release_notes' => '',
        'release_url' => '',
        'published_at' => '',
        'checked_at' => date('Y-m-d H:i:s'),
        'error' => null,
    ];

    try {
        // Check cache first
        $cached = $db->query("SELECT value FROM settings WHERE `key` = 'update_check_cache'")->fetchColumn();

        if ($cached) {
            $data = json_decode($cached, true);
            if ($data && isset($data['checked_at'])) {
                $age = time() - strtotime($data['checked_at']);
                if ($age < UPDATE_CHECK_INTERVAL) {
                    return $data;
                }
            }
        }

        // Fetch latest release from GitHub API
        $url = 'https://api.github.com/repos/' . APP_GITHUB_REPO . '/releases/latest';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => implode("\r\n", [
                    'User-Agent: AuctionKai/' . APP_VERSION,
                    'Accept: application/vnd.github.v3+json',
                ]),
                'ignore_errors' => true,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $default['error'] = 'Could not reach GitHub API';
            return $default;
        }

        $release = json_decode($response, true);

        if (empty($release['tag_name'])) {
            $default['error'] = 'No release found on GitHub. Create a release tag first.';
            return $default;
        }

        $latestVersion = $release['tag_name'];
        $releaseNotes  = $release['body'] ?? '';
        $releaseName   = $release['name'] ?? $latestVersion;
        $releaseUrl    = $release['html_url'] ?? '';
        $publishedAt   = $release['published_at'] ?? '';

        // Compare versions (strip 'v' prefix)
        $currentClean = ltrim(APP_VERSION, 'v');
        $latestClean  = ltrim($latestVersion, 'v');
        $hasUpdate    = version_compare($latestClean, $currentClean, '>');

        $result = [
            'has_update'       => $hasUpdate,
            'current_version'  => APP_VERSION,
            'latest_version'   => $latestVersion,
            'release_name'     => $releaseName,
            'release_notes'    => $releaseNotes,
            'release_url'      => $releaseUrl,
            'published_at'     => $publishedAt,
            'checked_at'       => date('Y-m-d H:i:s'),
            'error'            => null,
        ];

        // Cache result in settings table
        $db->prepare("
            INSERT INTO settings (`key`, `value`)
            VALUES ('update_check_cache', ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ")->execute([json_encode($result)]);

        return $result;

    } catch (Exception $e) {
        $default['error'] = 'Update check failed: ' . $e->getMessage();
        return $default;
    }
}

/**
 * Render the update notification banner HTML
 */
function renderUpdateBanner(array $updateInfo): string {
    if (empty($updateInfo['has_update'])) return '';

    $v = h($updateInfo['latest_version']);
    $name = h($updateInfo['release_name']);
    $url = h($updateInfo['release_url']);
    $notes = $updateInfo['release_notes'] ?? '';
    $published = $updateInfo['published_at'] ?? '';
    $dateStr = $published ? date('M j, Y', strtotime($published)) : '';

    // Convert markdown-style notes to HTML (basic)
    $notesHtml = nl2br(h($notes));
    $notesHtml = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $notesHtml);
    $notesHtml = preg_replace('/^- (.+)$/m', '• $1', $notesHtml);

    return '<div class="bg-ak-gold/10 border border-ak-gold/30 rounded-xl p-4 mb-4 animate-fade-in">
      <div class="flex items-start gap-3">
        <span class="text-xl">🚀</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-ak-gold font-bold text-sm">Update Available!</span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-ak-gold/20 text-ak-gold">' . $v . '</span>
            ' . ($name && $name !== $v ? '<span class="text-ak-muted text-xs">— ' . $name . '</span>' : '') . '
            ' . ($dateStr ? '<span class="text-ak-muted text-[10px]">(' . $dateStr . ')</span>' : '') . '
          </div>
          <div class="text-ak-muted text-xs mt-1">Current: ' . h($updateInfo['current_version']) . ' → Latest: ' . $v . '</div>
          ' . ($notesHtml ? '<div class="mt-3 p-3 bg-ak-bg rounded-lg text-ak-text2 text-xs leading-relaxed max-h-40 overflow-y-auto">' . $notesHtml . '</div>' : '') . '
          <div class="flex gap-2 mt-3">
            <a href="' . $url . '" target="_blank" class="btn btn-gold btn-sm text-[11px]">↓ View Release</a>
            <button class="btn btn-dark btn-sm text-[11px]" onclick="this.closest(\'.bg-ak-gold\\/10\').style.display=\'none\'">Dismiss</button>
          </div>
        </div>
      </div>
    </div>';
}
