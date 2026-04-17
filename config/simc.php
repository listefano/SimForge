<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SimulationCraft Binary
    |--------------------------------------------------------------------------
    |
    | Set SIMC_BINARY_PATH to use an existing simc installation (e.g. a
    | system-wide build). Leave empty to use the managed installation in
    | storage/simc/{channel}/simc, which is installed via `simc:install`.
    |
    */

    'binary_path' => env('SIMC_BINARY_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Release Channel
    |--------------------------------------------------------------------------
    |
    | Controls which build is used when no explicit binary path is set.
    | "stable" pulls the latest GitHub release; "nightly" pulls the latest
    | pre-release. Switch at any time and re-run `php artisan simc:install`.
    |
    */

    'channel' => env('SIMC_CHANNEL', 'stable'),

    /*
    |--------------------------------------------------------------------------
    | Simulation Defaults
    |--------------------------------------------------------------------------
    */

    'threads' => (int) env('SIMC_THREADS', 8),

    'iterations' => (int) env('SIMC_ITERATIONS', 10000),

    'timeout' => (int) env('SIMC_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Install Path
    |--------------------------------------------------------------------------
    |
    | Managed binaries are placed under this directory, in a sub-folder named
    | after the channel (stable / nightly). This directory must be writable
    | by the web process. It is intentionally outside the application code so
    | SimC can be updated without touching the app directory.
    |
    */

    'install_path' => env('SIMC_INSTALL_PATH', storage_path('simc')),

    /*
    |--------------------------------------------------------------------------
    | GitHub Release Source
    |--------------------------------------------------------------------------
    |
    | SimulationCraft publishes builds on GitHub. Stable builds are regular
    | releases; nightly builds are pre-releases. Provide a personal access
    | token via GITHUB_TOKEN to raise the API rate limit (optional).
    |
    */

    'github' => [
        'owner' => 'simulationcraft',
        'repo' => 'simc',
        'token' => env('GITHUB_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Asset Patterns
    |--------------------------------------------------------------------------
    |
    | These regex patterns are matched against GitHub release asset filenames
    | to select the correct binary for the current operating system. Override
    | them if SimC changes their naming convention.
    |
    */

    'asset_patterns' => [
        'linux' => '/SimulationCraft.*Linux.*x86_64.*\.tar\.gz$/i',
        'darwin' => '/SimulationCraft.*macOS.*\.tar\.gz$/i',
        'windows' => '/SimulationCraft.*win64.*\.exe$/i',
    ],

];
