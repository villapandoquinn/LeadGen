<?php

/*
|--------------------------------------------------------------------------
| PRIMOVATIVE CO.
| LEAD GENERATION SYSTEM
|--------------------------------------------------------------------------
| Campaign Management
|
| Path:
| /campaigns/index.php
|
| Workflow:
| Campaigns
|     â†“
| Lead Discovery
|     â†“
| Leads
|     â†“
| Outreach
|     â†“
| Conversations
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Workspace';
$pageTitle = 'Campaigns';
$activePage = 'campaigns';


/*
|--------------------------------------------------------------------------
| CAMPAIGN DATA
|--------------------------------------------------------------------------
*/

$campaigns = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            campaigns.id,
            campaigns.name,
            campaigns.description,
            campaigns.industry,
            campaigns.keywords,
            campaigns.location AS target_location,
            campaigns.lead_limit,
            campaigns.status,
            campaigns.total_leads,
            campaigns.contacted_leads AS contacted,
            campaigns.replied_leads AS replies,
            campaigns.created_at,
            u.name AS creator_name
        FROM campaigns
        LEFT JOIN users u ON campaigns.created_by = u.id
        WHERE campaigns.workspace_id = ?
        ORDER BY campaigns.created_at DESC
    ");
    $stmt->execute([$currentWorkspaceId]);

    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $campaigns = [];
}


/*
|--------------------------------------------------------------------------
| KPI CALCULATIONS
|--------------------------------------------------------------------------
*/

$totalCampaigns = count($campaigns);

$activeCampaigns = 0;
$totalLeads = 0;
$totalReplies = 0;
$totalContacted = 0;

foreach ($campaigns as $campaign) {

    if (($campaign['status'] ?? '') === 'active') {
        $activeCampaigns++;
    }

    $totalLeads += (int) ($campaign['total_leads'] ?? 0);
    $totalContacted += (int) ($campaign['contacted'] ?? 0);
    $totalReplies += (int) ($campaign['replies'] ?? 0);
}

$replyRate = $totalContacted > 0
    ? round(($totalReplies / $totalContacted) * 100, 1)
    : 0;


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function campaignStatusClass($status): string
{
    return match (strtolower((string) $status)) {

        'active' => 'status-active',

        'paused' => 'status-paused',

        'completed' => 'status-completed',

        'draft' => 'status-draft',

        'archived' => 'status-archived',

        default => 'status-draft'
    };
}


function campaignIcon($industry): string
{
    $industry = strtolower((string) $industry);

    return match (true) {

        str_contains($industry, 'roof')
            => 'bi-house',

        str_contains($industry, 'solar')
            => 'bi-sun',

        str_contains($industry, 'dent')
            => 'bi-heart-pulse',

        str_contains($industry, 'landscap')
            => 'bi-tree',

        str_contains($industry, 'restaurant')
            => 'bi-shop',

        str_contains($industry, 'real estate')
            => 'bi-buildings',

        str_contains($industry, 'construction')
            => 'bi-building',

        default
            => 'bi-megaphone'
    };
}


function campaignAvatarClass($industry): string
{
    $industry = strtolower((string) $industry);

    return match (true) {

        str_contains($industry, 'roof')
            => 'campaign-house',

        str_contains($industry, 'solar')
            => 'campaign-solar',

        str_contains($industry, 'dent')
            => 'campaign-dental',

        str_contains($industry, 'landscap')
            => 'campaign-landscape',

        default
            => 'campaign-default'
    };
}


function replyRate($contacted, $replies): float
{
    $contacted = (int) $contacted;
    $replies = (int) $replies;

    if ($contacted <= 0) {
        return 0;
    }

    return round(($replies / $contacted) * 100, 1);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Primovative Co. Lead Generation System â€” Campaign Management"
    >

    <title>
        Campaigns â€” Primovative Co.
    </title>


    <!-- ==========================================================
         BOOTSTRAP
    =========================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- ==========================================================
         BOOTSTRAP ICONS
    =========================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- ==========================================================
         PRIMOVATIVE DESIGN SYSTEM
    =========================================================== -->

    <link
        rel="stylesheet"
        href="../css/styles.css"
    >


    <!-- ==========================================================
         CAMPAIGN PAGE STYLES
    =========================================================== -->

    <style>

        /* =========================================================
           CAMPAIGN PAGE
        ========================================================= */

        .campaign-page-header {
            margin-bottom: 22px;
        }

        .campaign-page-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .campaign-page-title h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 750;
            letter-spacing: -.02em;
        }

        .campaign-page-title p {
            margin: 5px 0 0;
            color: var(--pm-text-secondary);
            font-size: 12px;
        }

        .btn-primary-custom {
            min-height: 42px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            border: 0;
            border-radius: var(--pm-radius-sm);

            background:
                linear-gradient(
                    135deg,
                    #4f46e5,
                    #2563eb
                );

            color: #fff;
            font-size: 12px;
            font-weight: 700;

            box-shadow:
                0 7px 18px rgba(37, 99, 235, .18);

            transition:
                transform var(--pm-transition),
                box-shadow var(--pm-transition);
        }

        .btn-primary-custom:hover {
            color: #fff;

            transform: translateY(-1px);

            box-shadow:
                0 10px 24px rgba(37, 99, 235, .25);
        }

        .btn-primary-custom:active {
            transform: scale(.98);
        }


        /* =========================================================
           KPI
        ========================================================= */

        .campaign-kpi-card {
            min-height: 112px;
            padding: 18px;

            display: flex;
            align-items: center;
            gap: 14px;

            background: #fff;

            border: 1px solid var(--pm-border);

            border-radius: var(--pm-radius-md);

            box-shadow: var(--pm-shadow-xs);

            transition:
                transform var(--pm-transition),
                box-shadow var(--pm-transition);
        }

        .campaign-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--pm-shadow-sm);
        }

        .campaign-kpi-icon {
            width: 48px;
            height: 48px;

            flex: 0 0 48px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            color: #fff;
            font-size: 19px;
        }

        .campaign-kpi-icon.blue {
            background:
                linear-gradient(
                    135deg,
                    #3b82f6,
                    #2563eb
                );
        }

        .campaign-kpi-icon.green {
            background:
                linear-gradient(
                    135deg,
                    #14b87a,
                    #0f9f6e
                );
        }

        .campaign-kpi-icon.violet {
            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #4f46e5
                );
        }

        .campaign-kpi-icon.amber {
            background:
                linear-gradient(
                    135deg,
                    #fbbf24,
                    #f59e0b
                );
        }

        .campaign-kpi-content {
            min-width: 0;
        }

        .campaign-kpi-label {
            display: block;

            color: var(--pm-text-secondary);

            font-size: 11px;
            font-weight: 500;
        }

        .campaign-kpi-value {
            display: block;

            margin-top: 3px;

            color: var(--pm-text);

            font-size: 22px;
            font-weight: 750;

            line-height: 1;
        }

        .campaign-kpi-description {
            display: block;

            margin-top: 6px;

            color: var(--pm-text-muted);

            font-size: 9px;
        }


        /* =========================================================
           TOOLBAR
        ========================================================= */

        .campaign-toolbar {
            margin-top: 20px;
            margin-bottom: 14px;

            padding: 14px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;

            background: #fff;

            border: 1px solid var(--pm-border);

            border-radius: var(--pm-radius-md);

            box-shadow: var(--pm-shadow-xs);
        }

        .campaign-toolbar-left {
            min-width: 0;

            flex: 1;

            display: flex;
            align-items: center;

            gap: 10px;
        }

        .campaign-search {
            position: relative;

            width: 100%;
            max-width: 350px;
        }

        .campaign-search i {
            position: absolute;

            top: 50%;
            left: 13px;

            transform: translateY(-50%);

            color: var(--pm-text-muted);

            font-size: 14px;
        }

        .campaign-search input {
            width: 100%;
            height: 40px;

            padding:
                0 13px 0 38px;

            border: 1px solid var(--pm-border);

            border-radius: var(--pm-radius-sm);

            background: #fff;

            color: var(--pm-text);

            font-size: 11px;

            outline: none;

            transition:
                border-color var(--pm-transition),
                box-shadow var(--pm-transition);
        }

        .campaign-search input:focus {
            border-color: #93c5fd;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .08);
        }

        .campaign-filter {
            height: 40px;

            min-width: 125px;

            padding: 0 10px;

            border: 1px solid var(--pm-border);

            border-radius: var(--pm-radius-sm);

            background: #fff;

            color: var(--pm-text-secondary);

            font-size: 11px;

            outline: none;
        }

        .campaign-toolbar-count {
            color: var(--pm-text-muted);
            font-size: 10px;
            white-space: nowrap;
        }


        /* =========================================================
           CAMPAIGN TABLE CARD
        ========================================================= */

        .campaign-table-card {
            width: 100%;

            background: #fff;

            border: 1px solid var(--pm-border);

            border-radius: var(--pm-radius-md);

            box-shadow: var(--pm-shadow-xs);

            overflow: hidden;
        }

        .campaign-table-header {
            padding: 17px 18px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;

            border-bottom: 1px solid var(--pm-border);
        }

        .campaign-table-header h2 {
            margin: 0;

            font-size: 14px;
            font-weight: 700;
        }

        .campaign-table-header span {
            color: var(--pm-text-muted);
            font-size: 10px;
        }

        .campaign-table-wrapper {
            width: 100%;

            overflow-x: auto;

            -webkit-overflow-scrolling: touch;
        }

        .campaign-table {
            width: 100%;

            min-width: 1050px;

            margin: 0;

            border-collapse: collapse;

            font-size: 11px;
        }

        .campaign-table th {
            padding: 11px 16px;

            color: var(--pm-text-muted);

            background: #fbfcfe;

            border-bottom: 1px solid var(--pm-border);

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .05em;

            white-space: nowrap;
        }

        .campaign-table td {
            padding: 14px 16px;

            border-bottom: 1px solid var(--pm-border-light);

            vertical-align: middle;
        }

        .campaign-table tbody tr {
            transition:
                background var(--pm-transition);
        }

        .campaign-table tbody tr:hover {
            background: #fafcff;
        }

        .campaign-table tbody tr:last-child td {
            border-bottom: 0;
        }


        /* =========================================================
           CAMPAIGN NAME
        ========================================================= */

        .campaign-name-cell {
            min-width: 260px;

            display: flex;
            align-items: center;

            gap: 11px;
        }

        .campaign-table-avatar {
            width: 38px;
            height: 38px;

            flex: 0 0 38px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;

            color: #fff;

            font-size: 16px;
        }

        .campaign-default {
            background:
                linear-gradient(
                    135deg,
                    #6366f1,
                    #4f46e5
                );
        }

        .campaign-name-copy {
            min-width: 0;

            display: flex;
            flex-direction: column;
        }

        .campaign-name-copy strong {
            overflow: hidden;

            color: var(--pm-text);

            font-size: 11px;
            font-weight: 700;

            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .campaign-name-copy small {
            margin-top: 2px;

            color: var(--pm-text-muted);

            font-size: 9px;

            white-space: nowrap;
        }


        /* =========================================================
           TARGET
        ========================================================= */

        .campaign-target {
            display: flex;
            flex-direction: column;
        }

        .campaign-target strong {
            color: var(--pm-text);

            font-size: 10px;
            font-weight: 600;
        }

        .campaign-target small {
            margin-top: 2px;

            color: var(--pm-text-muted);

            font-size: 9px;
        }


        /* =========================================================
           LOCATION
        ========================================================= */

        .campaign-location {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            color: var(--pm-text-secondary);

            font-size: 10px;

            white-space: nowrap;
        }

        .campaign-location i {
            color: var(--pm-primary);
        }


        /* =========================================================
           METRICS
        ========================================================= */

        .campaign-metric {
            display: flex;
            flex-direction: column;
        }

        .campaign-metric strong {
            color: var(--pm-text);

            font-size: 11px;
            font-weight: 700;
        }

        .campaign-metric small {
            margin-top: 2px;

            color: var(--pm-text-muted);

            font-size: 8px;
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status-completed {
            background: #eef2ff;
            color: #4f46e5;
        }

        .status-draft {
            background: #f1f5f9;
            color: #64748b;
        }

        .status-archived {
            background: #f8fafc;
            color: #94a3b8;
        }


        /* =========================================================
           ACTION BUTTONS
        ========================================================= */

        .campaign-actions {
            display: flex;
            align-items: center;

            gap: 4px;
        }

        .campaign-action {
            width: 32px;
            height: 32px;

            padding: 0;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border: 0;

            border-radius: 8px;

            background: transparent;

            color: var(--pm-text-secondary);

            transition:
                background var(--pm-transition),
                color var(--pm-transition);
        }

        .campaign-action:hover {
            background: #eff6ff;
            color: var(--pm-primary);
        }

        .campaign-action.delete:hover {
            background: #fef2f2;
            color: #dc2626;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .campaign-empty {
            padding: 70px 20px;

            text-align: center;
        }

        .campaign-empty-icon {
            width: 64px;
            height: 64px;

            margin: 0 auto 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 18px;

            background: var(--pm-primary-soft);

            color: var(--pm-primary);

            font-size: 25px;
        }

        .campaign-empty h3 {
            margin: 0;

            font-size: 16px;
            font-weight: 700;
        }

        .campaign-empty p {
            max-width: 440px;

            margin: 6px auto 18px;

            color: var(--pm-text-secondary);

            font-size: 11px;
        }


        /* =========================================================
           MODAL
        ========================================================= */

        .campaign-modal .modal-content {
            border: 0;

            border-radius: 18px;

            box-shadow:
                0 25px 70px rgba(15, 23, 42, .18);
        }

        .campaign-modal .modal-header {
            padding: 20px 22px;

            border-bottom: 1px solid var(--pm-border);
        }

        .campaign-modal .modal-title {
            font-size: 16px;
            font-weight: 700;
        }

        .campaign-modal .modal-subtitle {
            margin-top: 3px;

            color: var(--pm-text-secondary);

            font-size: 10px;
        }

        .campaign-modal .modal-body {
            padding: 22px;
        }

        .campaign-modal .form-label {
            margin-bottom: 6px;

            color: var(--pm-text);

            font-size: 10px;
            font-weight: 700;
        }

        .campaign-modal .form-control,
        .campaign-modal .form-select {
            min-height: 42px;

            border-color: var(--pm-border);

            border-radius: var(--pm-radius-sm);

            font-size: 11px;
        }

        .campaign-modal textarea.form-control {
            min-height: 90px;

            resize: vertical;
        }

        .campaign-modal .form-control:focus,
        .campaign-modal .form-select:focus {
            border-color: #93c5fd;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .08);
        }

        .campaign-modal .modal-footer {
            padding: 14px 22px;

            border-top: 1px solid var(--pm-border);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 991.98px) {

            .campaign-page-title-row {
                flex-direction: column;
            }

            .campaign-page-title-row .btn-primary-custom {
                width: 100%;
            }

            .campaign-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .campaign-toolbar-left {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .campaign-search {
                max-width: none;
            }

            .campaign-filter {
                width: 100%;
            }

            .campaign-toolbar-count {
                align-self: flex-start;
            }

        }


        @media (max-width: 767.98px) {

            .campaign-page-title h1 {
                font-size: 20px;
            }

            .campaign-kpi-card {
                min-height: 96px;
                padding: 15px;
            }

            .campaign-kpi-icon {
                width: 44px;
                height: 44px;
                flex-basis: 44px;
            }

            .campaign-kpi-value {
                font-size: 20px;
            }

            .campaign-table-header {
                padding: 15px;
            }

        }


    </style>

</head>


<body>


<a
    href="#main-content"
    class="skip-link"
>
    Skip to content
</a>


<!-- ==============================================================
     APPLICATION SHELL
=============================================================== -->

<div class="app-shell" id="appShell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- ==========================================================
         MAIN AREA
    =========================================================== -->

    <div class="main-area">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>



        <!-- ======================================================
             MAIN CONTENT
        ======================================================= -->

        <main
            class="content"
            id="main-content"
        >


            <!-- ==================================================
                 PAGE HEADER
            =================================================== -->

            <section class="campaign-page-header">


                <div class="campaign-page-title-row">


                    <div class="campaign-page-title">

                        <span class="welcome-kicker">
                            Lead Generation
                        </span>

                        <h1>
                            Campaigns
                        </h1>

                        <p>
                            Create, manage and track your lead generation campaigns.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="btn-primary-custom"
                        data-bs-toggle="modal"
                        data-bs-target="#newCampaignModal"
                    >

                        <i class="bi bi-plus-lg"></i>

                        New Campaign

                    </button>


                </div>


            </section>


            <!-- ==================================================
                 KPI CARDS
            =================================================== -->

            <section class="row kpi-grid">


                <!-- TOTAL CAMPAIGNS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="campaign-kpi-card">

                        <div class="campaign-kpi-icon blue">

                            <i class="bi bi-megaphone"></i>

                        </div>

                        <div class="campaign-kpi-content">

                            <span class="campaign-kpi-label">
                                Total Campaigns
                            </span>

                            <strong class="campaign-kpi-value">
                                <?= number_format($totalCampaigns) ?>
                            </strong>

                            <small class="campaign-kpi-description">
                                All campaigns
                            </small>

                        </div>

                    </div>

                </div>


                <!-- ACTIVE CAMPAIGNS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="campaign-kpi-card">

                        <div class="campaign-kpi-icon green">

                            <i class="bi bi-play-circle"></i>

                        </div>

                        <div class="campaign-kpi-content">

                            <span class="campaign-kpi-label">
                                Active Campaigns
                            </span>

                            <strong class="campaign-kpi-value">
                                <?= number_format($activeCampaigns) ?>
                            </strong>

                            <small class="campaign-kpi-description">
                                Currently running
                            </small>

                        </div>

                    </div>

                </div>


                <!-- TOTAL LEADS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="campaign-kpi-card">

                        <div class="campaign-kpi-icon violet">

                            <i class="bi bi-people"></i>

                        </div>

                        <div class="campaign-kpi-content">

                            <span class="campaign-kpi-label">
                                Total Leads
                            </span>

                            <strong class="campaign-kpi-value">
                                <?= number_format($totalLeads) ?>
                            </strong>

                            <small class="campaign-kpi-description">
                                Discovered leads
                            </small>

                        </div>

                    </div>

                </div>


                <!-- REPLY RATE -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="campaign-kpi-card">

                        <div class="campaign-kpi-icon amber">

                            <i class="bi bi-chat-left-text"></i>

                        </div>

                        <div class="campaign-kpi-content">

                            <span class="campaign-kpi-label">
                                Reply Rate
                            </span>

                            <strong class="campaign-kpi-value">
                                <?= number_format($replyRate, 1) ?>%
                            </strong>

                            <small class="campaign-kpi-description">
                                <?= number_format($totalReplies) ?> replies
                            </small>

                        </div>

                    </div>

                </div>


            </section>


            <!-- ==================================================
                 TOOLBAR
            =================================================== -->

            <section class="campaign-toolbar">


                <div class="campaign-toolbar-left">


                    <div class="campaign-search">

                        <i class="bi bi-search"></i>

                        <input
                            type="search"
                            id="campaignSearch"
                            placeholder="Search campaigns..."
                            autocomplete="off"
                        >

                    </div>


                    <select
                        class="campaign-filter"
                        id="campaignStatusFilter"
                    >

                        <option value="all">
                            All Status
                        </option>

                        <option value="active">
                            Active
                        </option>

                        <option value="draft">
                            Draft
                        </option>

                        <option value="paused">
                            Paused
                        </option>

                        <option value="completed">
                            Completed
                        </option>

                        <option value="archived">
                            Archived
                        </option>

                    </select>


                </div>


                <span
                    class="campaign-toolbar-count"
                    id="campaignCount"
                >
                    <?= number_format($totalCampaigns) ?> campaigns
                </span>


            </section>


            <!-- ==================================================
                 CAMPAIGN TABLE
            =================================================== -->

            <section class="campaign-table-card">


                <div class="campaign-table-header">

                    <div>

                        <h2>
                            Campaign List
                        </h2>

                        <span>
                            Manage your lead generation campaigns
                        </span>

                    </div>

                </div>


                <?php if (!empty($campaigns)): ?>


                    <div class="campaign-table-wrapper">

                        <table class="campaign-table">

                            <thead>

                                <tr>

                                    <th>
                                        Campaign
                                    </th>

                                    <th>
                                        Target
                                    </th>

                                    <th>
                                        Location
                                    </th>

                                    <th>
                                        Created By
                                    </th>

                                    <th>
                                        Leads
                                    </th>

                                    <th>
                                        Contacted
                                    </th>

                                    <th>
                                        Replies
                                    </th>

                                    <th>
                                        Reply Rate
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody id="campaignTableBody">


                                <?php foreach ($campaigns as $campaign): ?>


                                    <?php

                                    $status = strtolower(
                                        $campaign['status'] ?? 'draft'
                                    );

                                    $rate = replyRate(
                                        $campaign['contacted'] ?? 0,
                                        $campaign['replies'] ?? 0
                                    );

                                    ?>


                                    <tr
                                        class="campaign-table-row"
                                        data-name="<?= e(strtolower($campaign['name'] ?? '')) ?>"
                                        data-industry="<?= e(strtolower($campaign['industry'] ?? '')) ?>"
                                        data-location="<?= e(strtolower($campaign['target_location'] ?? '')) ?>"
                                        data-status="<?= e($status) ?>"
                                    >


                                        <!-- CAMPAIGN -->

                                        <td>

                                            <div class="campaign-name-cell">


                                                <div
                                                    class="campaign-table-avatar <?= e(campaignAvatarClass($campaign['industry'] ?? '')) ?>"
                                                >

                                                    <i
                                                        class="bi <?= e(campaignIcon($campaign['industry'] ?? '')) ?>"
                                                    ></i>

                                                </div>


                                                <div class="campaign-name-copy">

                                                    <strong>
                                                        <?= e($campaign['name'] ?? 'Unnamed Campaign') ?>
                                                    </strong>

                                                    <small>
                                                        <?= e($campaign['industry'] ?? 'General') ?>
                                                    </small>

                                                </div>


                                            </div>

                                        </td>


                                        <!-- TARGET -->

                                        <td>

                                            <div class="campaign-target">

                                                <strong>
                                                    <?= e($campaign['keywords'] ?? 'Business Leads') ?>
                                                </strong>

                                                <small>
                                                    Lead discovery criteria
                                                </small>

                                            </div>

                                        </td>


                                        <!-- LOCATION -->

                                        <td>

                                            <span class="campaign-location">

                                                <i class="bi bi-geo-alt-fill"></i>

                                                <?= e($campaign['target_location'] ?? 'Any Location') ?>

                                            </span>

                                        </td>


                                        <!-- CREATED BY -->

                                        <td>

                                            <span class="badge bg-light text-dark border">

                                                <i class="bi bi-person me-1"></i><?= e($campaign['creator_name'] ?? 'Unknown') ?>

                                            </span>

                                        </td>


                                        <!-- LEADS -->

                                        <td>

                                            <div class="campaign-metric">

                                                <strong>
                                                    <?= number_format((int)($campaign['total_leads'] ?? 0)) ?>
                                                </strong>

                                                <small>
                                                    leads
                                                </small>

                                            </div>

                                        </td>


                                        <!-- CONTACTED -->

                                        <td>

                                            <div class="campaign-metric">

                                                <strong>
                                                    <?= number_format((int)($campaign['contacted'] ?? 0)) ?>
                                                </strong>

                                                <small>
                                                    contacted
                                                </small>

                                            </div>

                                        </td>


                                        <!-- REPLIES -->

                                        <td>

                                            <div class="campaign-metric">

                                                <strong>
                                                    <?= number_format((int)($campaign['replies'] ?? 0)) ?>
                                                </strong>

                                                <small>
                                                    replies
                                                </small>

                                            </div>

                                        </td>


                                        <!-- REPLY RATE -->

                                        <td>

                                            <strong>
                                                <?= number_format($rate, 1) ?>%
                                            </strong>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status-pill <?= e(campaignStatusClass($status)) ?>"
                                            >

                                                <?= e(ucfirst($status)) ?>

                                            </span>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>

                                            <div class="campaign-actions">


                                                <!-- VIEW -->

                                                <a
                                                    href="view.php?id=<?= (int)$campaign['id'] ?>"
                                                    class="campaign-action"
                                                    title="View campaign"
                                                    aria-label="View campaign"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>


                                                <!-- DISCOVER LEADS -->

                                                <a
                                                    href="../lead-discovery/index.php?campaign_id=<?= (int)$campaign['id'] ?>"
                                                    class="campaign-action"
                                                    title="Discover leads"
                                                    aria-label="Discover leads"
                                                >

                                                    <i class="bi bi-search"></i>

                                                </a>


                                                <!-- EDIT -->

                                                <a
                                                    href="edit.php?id=<?= (int)$campaign['id'] ?>"
                                                    class="campaign-action"
                                                    title="Edit campaign"
                                                    aria-label="Edit campaign"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </a>


                                                <!-- DELETE -->

                                                <button
                                                    type="button"
                                                    class="campaign-action delete"
                                                    title="Delete campaign"
                                                    aria-label="Delete campaign"
                                                    onclick="deleteCampaign(<?= (int)$campaign['id'] ?>)"
                                                >

                                                    <i class="bi bi-trash3"></i>

                                                </button>


                                            </div>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                    <!-- NO SEARCH RESULTS -->

                    <div
                        id="noSearchResults"
                        class="campaign-empty d-none"
                    >

                        <div class="campaign-empty-icon">

                            <i class="bi bi-search"></i>

                        </div>

                        <h3>
                            No campaigns found
                        </h3>

                        <p>
                            Try changing your search or status filter.
                        </p>

                    </div>


                <?php else: ?>


                    <!-- ==================================================
                         EMPTY STATE
                    =================================================== -->

                    <div class="campaign-empty">


                        <div class="campaign-empty-icon">

                            <i class="bi bi-megaphone"></i>

                        </div>


                        <h3>
                            No campaigns yet
                        </h3>


                        <p>
                            Create your first campaign to start discovering
                            businesses and generating qualified leads.
                        </p>


                        <button
                            type="button"
                            class="btn-primary-custom"
                            data-bs-toggle="modal"
                            data-bs-target="#newCampaignModal"
                        >

                            <i class="bi bi-plus-lg"></i>

                            Create Your First Campaign

                        </button>


                    </div>


                <?php endif; ?>


            </section>


        </main>


        <!-- ======================================================
             FOOTER
        ======================================================= -->

        <footer class="app-footer">

            <span>
                Â© <?= date('Y') ?> Primovative Co. All rights reserved.
            </span>

            <span>
                Lead Generation System
            </span>

        </footer>


    </div>

</div>



<!-- ==============================================================
     NEW CAMPAIGN MODAL
================================================================ -->

<div
    class="modal fade campaign-modal"
    id="newCampaignModal"
    tabindex="-1"
    aria-labelledby="newCampaignModalLabel"
    aria-hidden="true"
>


    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">


            <!-- HEADER -->

            <div class="modal-header">

                <div>

                    <h2
                        class="modal-title"
                        id="newCampaignModalLabel"
                    >
                        Create New Campaign
                    </h2>

                    <div class="modal-subtitle">
                        Define your target market before discovering leads.
                    </div>

                </div>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <!-- BODY -->

            <form
                action="store.php"
                method="POST"
                id="newCampaignForm"
            >

                <?= csrf_field() ?>

                <div class="modal-body">


                    <div class="row g-3">


                        <!-- CAMPAIGN NAME -->

                        <div class="col-12">

                            <label
                                for="campaignName"
                                class="form-label"
                            >
                                Campaign Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="campaignName"
                                name="name"
                                placeholder="e.g. Roofing Services â€” Texas"
                                required
                                maxlength="150"
                            >

                        </div>


                        <!-- INDUSTRY -->

                        <div class="col-md-6">

                            <label
                                for="campaignIndustry"
                                class="form-label"
                            >
                                Industry
                            </label>

                            <select
                                class="form-select"
                                id="campaignIndustry"
                                name="industry"
                                required
                            >

                                <option value="">
                                    Select industry
                                </option>

                                <option value="Roofing">
                                    Roofing
                                </option>

                                <option value="Solar Installation">
                                    Solar Installation
                                </option>

                                <option value="Dentist & Orthodontist">
                                    Dentist & Orthodontist
                                </option>

                                <option value="Landscaping">
                                    Landscaping
                                </option>

                                <option value="Construction">
                                    Construction
                                </option>

                                <option value="Real Estate">
                                    Real Estate
                                </option>

                                <option value="Restaurants">
                                    Restaurants
                                </option>

                                <option value="Other">
                                    Other
                                </option>

                            </select>

                        </div>


                        <!-- TARGET LOCATION -->

                        <div class="col-md-6">

                            <label
                                for="campaignLocation"
                                class="form-label"
                            >
                                Target Location
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="campaignLocation"
                                name="target_location"
                                placeholder="e.g. Texas, USA"
                                required
                            >

                        </div>


                        <!-- KEYWORDS -->

                        <div class="col-12">

                            <label
                                for="campaignKeywords"
                                class="form-label"
                            >
                                Lead Keywords
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="campaignKeywords"
                                name="keywords"
                                placeholder="e.g. roofing contractor, roof repair, roofing company"
                            >

                        </div>


                        <!-- LEAD LIMIT -->

                        <div class="col-md-6">

                            <label
                                for="campaignLeadLimit"
                                class="form-label"
                            >
                                Lead Limit
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="campaignLeadLimit"
                                name="lead_limit"
                                value="100"
                                min="1"
                                max="10000"
                            >

                        </div>


                        <!-- STATUS -->

                        <div class="col-md-6">

                            <label
                                for="campaignStatus"
                                class="form-label"
                            >
                                Initial Status
                            </label>

                            <select
                                class="form-select"
                                id="campaignStatus"
                                name="status"
                            >

                                <option value="draft">
                                    Draft
                                </option>

                                <option value="active">
                                    Active
                                </option>

                                <option value="paused">
                                    Paused
                                </option>

                            </select>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="col-12">

                            <label
                                for="campaignDescription"
                                class="form-label"
                            >
                                Description
                            </label>

                            <textarea
                                class="form-control"
                                id="campaignDescription"
                                name="description"
                                placeholder="Describe the campaign objective and target audience..."
                            ></textarea>

                        </div>


                    </div>


                </div>


                <!-- FOOTER -->

                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn-primary-custom"
                    >

                        <i class="bi bi-plus-lg"></i>

                        Create Campaign

                    </button>


                </div>


            </form>


        </div>

    </div>

</div>



<!-- ==============================================================
     BOOTSTRAP JS
================================================================ -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>



<script>

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {


    const appShell =
        document.getElementById('appShell');


    const desktopSidebarToggle =
        document.getElementById('desktopSidebarToggle');


    if (desktopSidebarToggle) {

        desktopSidebarToggle.addEventListener(
            'click',
            function () {

                appShell.classList.toggle(
                    'sidebar-collapsed'
                );

            }
        );

    }


});


/*
|--------------------------------------------------------------------------
| ACCOUNT DROPDOWN
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {


    const accountWrapper =
        document.getElementById('accountWrapper');


    const accountToggle =
        document.getElementById('accountToggle');


    if (!accountWrapper || !accountToggle) {
        return;
    }


    accountToggle.addEventListener(
        'click',
        function (event) {

            event.stopPropagation();

            const isOpen =
                accountWrapper.classList.toggle('open');

            accountToggle.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );

        }
    );


    document.addEventListener(
        'click',
        function (event) {

            if (
                !accountWrapper.contains(event.target)
            ) {

                accountWrapper.classList.remove('open');

                accountToggle.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }
    );


});


/*
|--------------------------------------------------------------------------
| CAMPAIGN SEARCH
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {


    const searchInput =
        document.getElementById('campaignSearch');


    const statusFilter =
        document.getElementById('campaignStatusFilter');


    const rows =
        Array.from(
            document.querySelectorAll(
                '.campaign-table-row'
            )
        );


    const campaignCount =
        document.getElementById('campaignCount');


    const noResults =
        document.getElementById('noSearchResults');


    if (
        !searchInput ||
        !statusFilter ||
        !campaignCount
    ) {
        return;
    }


    function filterCampaigns() {


        const search =
            searchInput.value
                .trim()
                .toLowerCase();


        const status =
            statusFilter.value;


        let visibleCount = 0;


        rows.forEach(function (row) {


            const name =
                row.dataset.name || '';


            const industry =
                row.dataset.industry || '';


            const location =
                row.dataset.location || '';


            const rowStatus =
                row.dataset.status || '';


            const matchesSearch =
                !search ||
                name.includes(search) ||
                industry.includes(search) ||
                location.includes(search);


            const matchesStatus =
                status === 'all' ||
                rowStatus === status;


            const visible =
                matchesSearch &&
                matchesStatus;


            row.style.display =
                visible ? '' : 'none';


            if (visible) {
                visibleCount++;
            }

        });


        campaignCount.textContent =
            visibleCount +
            (visibleCount === 1
                ? ' campaign'
                : ' campaigns');


        if (noResults) {

            noResults.classList.toggle(
                'd-none',
                visibleCount !== 0
            );

        }

    }


    searchInput.addEventListener(
        'input',
        filterCampaigns
    );


    statusFilter.addEventListener(
        'change',
        filterCampaigns
    );


});


/*
|--------------------------------------------------------------------------
| DELETE CAMPAIGN
|--------------------------------------------------------------------------
*/

function deleteCampaign(id)
{

    if (!id) {
        return;
    }


    const confirmed =
        window.confirm(
            'Are you sure you want to delete this campaign? This action cannot be undone.'
        );


    if (!confirmed) {
        return;
    }


    /*
     * POST request to delete.php
     */

    const form =
        document.createElement('form');


    form.method = 'POST';

    form.action = 'delete.php';


    const input =
        document.createElement('input');


    input.type = 'hidden';

    input.name = 'id';

    input.value = id;


    form.appendChild(input);

    document.body.appendChild(form);

    form.submit();

}


</script>


</body>

</html>
```