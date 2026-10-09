<?php

return [
    /*
    | Master kill switch for automatic Inventory -> Accounting sync.
    */
    'disabled' => env('PROJECT_SYNC_DISABLED', false),

    /*
    | When false, only POs that were never synced are pushed automatically
    | (the historical behaviour). When true, every edit is pushed to Accounting
    | as a delta.
    */
    'on_update' => env('PROJECT_SYNC_ON_UPDATE', false),

    /*
    | Seconds a sync job waits before running, so a burst of edits to one PO
    | collapses into a single job.
    */
    'delay_seconds' => (int) env('PROJECT_SYNC_DELAY', 5),

    'queue' => env('PROJECT_SYNC_QUEUE', 'default'),

    'ppn_company_id' => (int) env('PPN_COMPANY_ID', 12),
    'non_ppn_company_id' => (int) env('NON_PPN_COMPANY_ID', 1),
];
