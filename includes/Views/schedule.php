<?php

/**
 * Public schedule page.
 *
 * Included from SchedulePage::render(), so it runs in that class's scope and
 * can call self::typeLabel() and friends.
 *
 * @var object $event     Event row.
 * @var array  $rows      Agenda rows, already sorted.
 * @var bool   $isPreview True while sharing is switched off (organiser only).
 * @var int    $talks     Session count, breaks excluded.
 */

if (!defined('ABSPATH')) {
    exit;
}

$speakerCount = 0;
foreach ($rows as $row) {
    $speakerCount += count($row['speakers']);
}

$summary = array();
$summary[] = sprintf(_n('%s session', '%s sessions', $talks, 'textdomain'), number_format_i18n($talks));
$summary[] = sprintf(_n('%s speaker', '%s speakers', $speakerCount, 'textdomain'), number_format_i18n($speakerCount));

if ($rows && $rows[0]['from']) {
    $summary[] = sprintf(__('starts %s', 'textdomain'), $rows[0]['from']);
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($event->title); ?> — <?php esc_html_e('Schedule', 'textdomain'); ?></title>
    <style>
        :root {
            --ink: #14161a;
            --ink-2: #545a63;
            --ink-3: #8b919b;
            --line: #e8eaee;
            --bg: #f5f6f8;
            --card: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
        }

        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }

        .bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border: 1px solid var(--ink);
            border-radius: 7px;
            background: var(--ink);
            color: #fff;
            font: inherit;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover { opacity: 0.88; }

        .notice {
            margin-bottom: 10px;
            padding: 7px 12px;
            border: 1px solid #f3dfa6;
            background: #fffaed;
            border-radius: 8px;
            font-size: 12.5px;
            color: #8a6116;
        }

        .sheet {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
        }

        .head {
            padding: 20px 22px 16px;
            border-bottom: 1px solid var(--line);
        }

        .eyebrow {
            margin: 0 0 4px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--ink-3);
        }

        h1 {
            margin: 0;
            font-size: 21px;
            line-height: 1.25;
            letter-spacing: -0.015em;
        }

        .meta {
            margin: 5px 0 0;
            font-size: 13px;
            color: var(--ink-2);
        }

        .summary {
            margin: 4px 0 0;
            font-size: 12.5px;
            color: var(--ink-3);
        }

        .agenda { padding: 4px 22px 18px; }

        .row {
            display: grid;
            grid-template-columns: 104px 1fr;
            gap: 14px;
            padding: 11px 0;
            border-bottom: 1px solid var(--line);
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .row:last-child { border-bottom: 0; }

        .time {
            font-size: 13px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            color: var(--ink-3);
            padding-top: 1px;
        }

        .time b {
            color: var(--ink);
            font-weight: 600;
        }

        .title-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .title {
            font-size: 14.5px;
            font-weight: 600;
            letter-spacing: -0.005em;
        }

        .row--break .title {
            font-weight: 500;
            color: var(--ink-2);
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.7;
            color: #fff;
        }

        .speakers {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 12px;
            margin: 5px 0 0;
            padding: 0;
            list-style: none;
        }

        .speaker {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ink-2);
        }

        .speaker img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--bg);
        }

        .empty {
            margin: 20px 0;
            text-align: center;
            font-size: 13px;
            color: var(--ink-3);
        }

        .foot {
            margin: 12px 0 0;
            text-align: center;
            font-size: 11.5px;
            color: var(--ink-3);
        }

        @media (max-width: 560px) {
            .wrap { padding: 14px 10px 28px; }
            .head { padding: 16px 16px 12px; }
            .agenda { padding: 4px 16px 14px; }
            .row { grid-template-columns: 1fr; gap: 3px; padding: 10px 0; }
        }

        @media print {
            @page { margin: 12mm; }

            body { background: #fff; font-size: 11pt; }
            .wrap { max-width: none; padding: 0; }
            .bar, .notice, .foot { display: none !important; }

            .sheet {
                border: 0;
                border-radius: 0;
            }

            .head { padding: 0 0 10px; }
            .agenda { padding: 0; }
            .row { padding: 8px 0; }

            /* Keep the type colours in the PDF. */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <button class="btn" type="button" onclick="window.print()">
            <?php esc_html_e('Print / Save as PDF', 'textdomain'); ?>
        </button>
    </div>

    <?php if ($isPreview) : ?>
        <p class="notice">
            <?php esc_html_e('Preview — public sharing is off, so only logged-in organisers can open this page.', 'textdomain'); ?>
        </p>
    <?php endif; ?>

    <div class="sheet">
        <header class="head">
            <p class="eyebrow"><?php esc_html_e('Schedule', 'textdomain'); ?></p>
            <h1><?php echo esc_html($event->title); ?></h1>

            <?php if ($event->event_date || $event->location) : ?>
                <p class="meta">
                    <?php echo esc_html($event->event_date); ?>
                    <?php if ($event->event_date && $event->location) : ?> · <?php endif; ?>
                    <?php echo esc_html($event->location); ?>
                </p>
            <?php endif; ?>

            <?php if ($rows) : ?>
                <p class="summary"><?php echo esc_html(implode(' · ', $summary)); ?></p>
            <?php endif; ?>
        </header>

        <div class="agenda">
            <?php if (!$rows) : ?>
                <p class="empty"><?php esc_html_e('No slots have been scheduled yet.', 'textdomain'); ?></p>
            <?php endif; ?>

            <?php foreach ($rows as $row) : ?>
                <div class="row<?php echo $row['type'] === 'break' ? ' row--break' : ''; ?>">
                    <div class="time">
                        <b><?php echo esc_html($row['from'] ? $row['from'] : '—'); ?></b><?php
                        if ($row['to']) {
                            echo ' – ' . esc_html($row['to']);
                        }
                        ?>
                    </div>

                    <div>
                        <div class="title-row">
                            <span class="title">
                                <?php echo esc_html($row['name'] ? $row['name'] : __('Untitled slot', 'textdomain')); ?>
                            </span>

                            <?php if ($row['type']) : ?>
                                <span class="tag" style="background: <?php echo esc_attr(self::typeColor($row['type'])); ?>">
                                    <?php echo esc_html(self::typeLabel($row['type'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($row['speakers']) : ?>
                            <ul class="speakers">
                                <?php foreach ($row['speakers'] as $speaker) : ?>
                                    <li class="speaker">
                                        <?php if ($speaker['email']) : ?>
                                            <img
                                                src="<?php echo esc_url(self::avatar($speaker['email'], 40)); ?>"
                                                alt=""
                                                loading="lazy"
                                            >
                                        <?php endif; ?>
                                        <?php echo esc_html($speaker['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="foot"><?php esc_html_e('Schedule published by WPMiners Event Scheduler', 'textdomain'); ?></p>
</div>
</body>
</html>
