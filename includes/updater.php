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
            ON DUPLICATE KEY UPDATE value = VALUES(`value`)
        ")->execute([json_encode($result)]);

        return $result;

    } catch (Exception $e) {
        $default['error'] = $e->getMessage();
        return $default;
    }
}

/**
 * Format GitHub markdown release notes to clean HTML for display
 */
function formatReleaseNotes(string $notes): string {
    if (empty($notes)) return '';

    $html = htmlspecialchars($notes, ENT_QUOTES);

    // Headers ### → bold section
    $html = preg_replace(
        '/^###\s+(.+)$/m',
        '<div class="font-bold text-ak-gold text-xs uppercase tracking-wider mt-3 mb-1">$1</div>',
        $html
    );

    // Headers ## → section title
    $html = preg_replace(
        '/^##\s+(.+)$/m',
        '<div class="font-bold text-ak-text text-sm mt-4 mb-2">$1</div>',
        $html
    );

    // Bullet points - → list items
    $html = preg_replace(
        '/^[-*]\s+(.+)$/m',
        '<div class="flex items-start gap-2 py-0.5"><span class="text-ak-gold shrink-0 mt-0.5">•</span><span class="text-ak-text2 text-xs">$1</span></div>',
        $html
    );

    // Bold text
    $html = preg_replace(
        '/\*\*(.+?)\*\*/',
        '<strong class="text-ak-text">$1</strong>',
        $html
    );

    // Inline code
    $html = preg_replace(
        '/`(.+?)`/',
        '<code class="bg-ak-infield px-1.5 py-0.5 rounded text-ak-gold font-mono text-[10px]">$1</code>',
        $html
    );

    // Clean up multiple blank lines
    $html = preg_replace('/\n{3,}/', "\n\n", $html);

    // Convert remaining newlines to br
    $html = nl2br($html);

    return $html;
}

/**
 * Dismiss update notification for current version
 */
function dismissUpdate(PDO $db, string $version): void {
    $db->prepare("
        INSERT INTO settings (`key`, `value`)
        VALUES ('update_dismissed_version', ?)
        ON DUPLICATE KEY UPDATE value = VALUES(`value`)
    ")->execute([$version]);
}

/**
 * Check if this version's update was dismissed
 */
function isUpdateDismissed(PDO $db, string $version): bool {
    try {
        $dismissed = $db->query("SELECT value FROM settings WHERE `key` = 'update_dismissed_version'")->fetchColumn();
        return $dismissed === $version;
    } catch (Exception $e) {
        return false;
    }
}
