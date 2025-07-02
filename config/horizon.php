<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        // Default supervisor for general jobs - can be removed if all queues are explicitly defined below
        // 'supervisor-default' => [
        //     'connection' => 'redis',
        //     'queue' => ['default'],
        //     'balance' => 'auto',
        //     'autoScalingStrategy' => 'time', // Consider 'workload' for more dynamic scaling
        //     'maxProcesses' => 1, // Base, overridden by environment
        //     'maxTime' => 0, // No max time for supervisor process itself
        //     'maxJobs' => 0, // No max jobs for supervisor process itself
        //     'memory' => 128, // Worker memory limit
        //     'tries' => 1,    // How many times a worker should attempt a job
        //     'timeout' => 60, // Job timeout in seconds
        //     'nice' => 0,
        // ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'workload', // More responsive to load
                'minProcesses' => 1,
                'maxProcesses' => 5, // Adjust based on server capacity
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 60, // Standard job timeout
                'nice' => 0,
            ],
            'supervisor-price-updates' => [
                'connection' => 'redis',
                'queue' => ['price-updates'],
                'balance' => 'auto', // Or 'simple' if you want a fixed number of workers
                'autoScalingStrategy' => 'workload',
                'minProcesses' => 2,  // Start with a couple for price updates
                'maxProcesses' => 10, // Can scale up significantly
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'memory' => 256,      // Higher memory for batch jobs
                'tries' => 1,         // Worker tries job once
                'timeout' => 1300,    // Job timeout (slightly > job's own timeout)
                // ProcessBlueprintPriceBatchJob has timeout = 1200
                'nice' => 5,          // Lower priority if needed, adjust
            ],
            'supervisor-emails' => [
                'connection' => 'redis',
                'queue' => ['emails'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'workload',
                'minProcesses' => 1,
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 120,     // Emails usually faster
                'nice' => 0,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple', // Keep it simple for local
                'maxProcesses' => 5,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-price-updates' => [
                'connection' => 'redis',
                'queue' => ['price-updates'],
                'balance' => 'simple',
                'maxProcesses' => 5,    // Can run a couple locally for testing batches
                'memory' => 256,
                'tries' => 1,
                'timeout' => 1300,
                'nice' => 0,
            ],
            'supervisor-emails' => [
                'connection' => 'redis',
                'queue' => ['emails'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 120,
                'nice' => 0,
            ],
        ],
    ],
];
