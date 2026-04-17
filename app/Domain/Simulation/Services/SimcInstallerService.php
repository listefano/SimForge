<?php

namespace App\Domain\Simulation\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SimcInstallerService
{
    private const VERSION_FILE = 'version.json';

    private const BINARY_NAME = 'simc';

    public function install(string $channel): void
    {
        $release = $this->fetchRelease($channel);
        $asset = $this->findAssetForPlatform($release['assets']);

        $installDir = $this->channelDirectory($channel);

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        $this->downloadAndExtract($asset['browser_download_url'], $installDir);
        $this->persistVersionInfo($installDir, $release, $channel);
    }

    /**
     * Returns the installed version string, or null when not installed.
     */
    public function installedVersion(string $channel): ?string
    {
        $versionFile = $this->channelDirectory($channel).'/'.self::VERSION_FILE;

        if (! file_exists($versionFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($versionFile), true);

        return $data['tag_name'] ?? null;
    }

    public function isInstalled(string $channel): bool
    {
        $binary = $this->channelDirectory($channel).'/'.self::BINARY_NAME;

        return is_executable($binary);
    }

    /**
     * Installs only when a newer release is available. Returns true when updated.
     */
    public function update(string $channel): bool
    {
        $release = $this->fetchRelease($channel);
        $currentVersion = $this->installedVersion($channel);

        if ($currentVersion === $release['tag_name']) {
            return false;
        }

        $asset = $this->findAssetForPlatform($release['assets']);
        $installDir = $this->channelDirectory($channel);

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        $this->downloadAndExtract($asset['browser_download_url'], $installDir);
        $this->persistVersionInfo($installDir, $release, $channel);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchRelease(string $channel): array
    {
        $response = $this->githubClient()
            ->get($this->releasesEndpoint($channel));

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to fetch SimulationCraft releases from GitHub: {$response->status()}"
            );
        }

        $payload = $response->json();

        // The /releases/latest endpoint returns an object; the /releases endpoint
        // returns an array. For nightly we fetch all releases and pick the first
        // pre-release.
        if ($channel === 'nightly') {
            $release = collect($payload)
                ->first(fn (array $r) => $r['prerelease'] === true);

            if (! $release) {
                throw new RuntimeException(
                    'No nightly (pre-release) found in the SimulationCraft GitHub repository.'
                );
            }

            return $release;
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $assets
     * @return array<string, mixed>
     */
    private function findAssetForPlatform(array $assets): array
    {
        $pattern = $this->platformPattern();

        $asset = collect($assets)
            ->first(fn (array $a) => preg_match($pattern, $a['name']) === 1);

        if (! $asset) {
            $names = collect($assets)->pluck('name')->implode(', ');
            throw new RuntimeException(
                "No SimulationCraft asset matched platform pattern '{$pattern}'. Available: {$names}"
            );
        }

        return $asset;
    }

    private function downloadAndExtract(string $url, string $targetDir): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'simc_dl_');

        try {
            $this->downloadFile($url, $tmpFile);
            $this->extract($tmpFile, $targetDir);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function downloadFile(string $url, string $destination): void
    {
        $resource = fopen($destination, 'wb');

        if (! $resource) {
            throw new RuntimeException("Cannot open destination file: {$destination}");
        }

        $response = $this->githubClient()->sink($resource)->get($url);

        fclose($resource);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to download SimulationCraft binary from {$url}: {$response->status()}"
            );
        }
    }

    private function extract(string $archive, string $targetDir): void
    {
        $mimeType = mime_content_type($archive);

        // All Linux/macOS releases are gzip-compressed tarballs.
        if (str_contains($mimeType, 'gzip') || str_ends_with($archive, '.tar.gz')) {
            $this->extractTarGz($archive, $targetDir);

            return;
        }

        throw new RuntimeException(
            "Unsupported archive type '{$mimeType}'. Only .tar.gz releases are supported by the managed installer."
        );
    }

    private function extractTarGz(string $archive, string $targetDir): void
    {
        // Extract into a temp staging directory so we can find the simc binary
        // regardless of the top-level folder name inside the tarball.
        $stagingDir = $targetDir.'/_staging';

        if (! is_dir($stagingDir)) {
            mkdir($stagingDir, 0755, true);
        }

        $exitCode = null;
        system(
            sprintf('tar -xzf %s -C %s --strip-components=1 2>&1', escapeshellarg($archive), escapeshellarg($stagingDir)),
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new RuntimeException("Failed to extract SimulationCraft archive (exit code {$exitCode}).");
        }

        // Move the simc binary to the channel directory root.
        $binary = $stagingDir.'/'.self::BINARY_NAME;

        if (! file_exists($binary)) {
            throw new RuntimeException(
                "SimulationCraft binary not found in extracted archive at '{$binary}'."
            );
        }

        $destination = $targetDir.'/'.self::BINARY_NAME;

        rename($binary, $destination);
        chmod($destination, 0755);

        // Clean up staging directory.
        array_map('unlink', glob($stagingDir.'/*') ?: []);
        rmdir($stagingDir);
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function persistVersionInfo(string $installDir, array $release, string $channel): void
    {
        file_put_contents(
            $installDir.'/'.self::VERSION_FILE,
            json_encode([
                'tag_name' => $release['tag_name'],
                'channel' => $channel,
                'published_at' => $release['published_at'] ?? null,
                'installed_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT),
        );
    }

    private function githubClient(): PendingRequest
    {
        $client = Http::baseUrl('https://api.github.com')
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->timeout(30);

        $token = config('simc.github.token');

        if ($token) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function releasesEndpoint(string $channel): string
    {
        $owner = config('simc.github.owner');
        $repo = config('simc.github.repo');

        return $channel === 'nightly'
            ? "/repos/{$owner}/{$repo}/releases"
            : "/repos/{$owner}/{$repo}/releases/latest";
    }

    private function platformPattern(): string
    {
        $os = PHP_OS_FAMILY;

        return match (true) {
            $os === 'Darwin' => config('simc.asset_patterns.darwin'),
            str_starts_with($os, 'Win') => config('simc.asset_patterns.windows'),
            default => config('simc.asset_patterns.linux'),
        };
    }

    private function channelDirectory(string $channel): string
    {
        return rtrim(config('simc.install_path'), '/').'/'.ltrim($channel, '/');
    }
}
