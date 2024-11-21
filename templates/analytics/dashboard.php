<?php
// templates/analytics/dashboard.php

defined('ABSPATH') || exit;

$analytics_manager = MKWA_Analytics_Manager::get_instance();
$chart_generator = MKWA_Chart_Generator::get_instance();
$user_id = get_current_user_id();

// Get analytics data
$analytics_data = $analytics_manager->get_analytics_summary($user_id, 'last_30_days');
?>

<div class="mkwa-analytics-dashboard">
    <div class="mkwa-dashboard-header">
        <h2><?php esc_html_e('Analytics Dashboard', 'mkwa'); ?></h2>
        <div class="mkwa-date-range-picker">
            <!-- Date range picker implementation will go here -->
        </div>
    </div>

    <div class="mkwa-dashboard-grid">
        <!-- Page Views Line Chart -->
        <div class="mkwa-dashboard-item">
            <?php
            $page_views_config = $chart_generator->generate_line_chart(
                array(
                    'Page Views' => array_map(function($event) {
                        return [
                            'date' => $event['timestamp'],
                            'value' => 1
                        ];
                    }, array_filter($analytics_data['events'], function($event) {
                        return $event['event_type'] === 'page_view';
                    }))
                ),
                array(
                    'title' => 'Page Views Over Time',
                    'x_label' => 'Date',
                    'y_label' => 'Views',
                    'fill' => true
                )
            );
            echo $chart_generator->render_chart('page-views', $page_views_config);
            ?>
        </div>

        <!-- Event Distribution Bar Chart -->
        <div class="mkwa-dashboard-item">
            <?php
            $events_config = $chart_generator->generate_bar_chart(
                array(
                    'Events' => array_column($analytics_data['events'], 'count', 'event_type')
                ),
                array(
                    'title' => 'Event Distribution',
                    'x_label' => 'Event Type',
                    'y_label' => 'Count',
                    'stacked' => false
                )
            );
            echo $chart_generator->render_chart('event-distribution', $events_config);
            ?>
        </div>

        <!-- Metrics Overview -->
        <div class="mkwa-dashboard-item">
            <?php
            $metrics_data = array();
            foreach ($analytics_data['metrics'] as $metric) {
                $metrics_data[$metric['metric_name']][] = array(
                    'date' => $metric['timestamp'],
                    'value' => $metric['value']
                );
            }
            
            $metrics_config = $chart_generator->generate_line_chart(
                $metrics_data,
                array(
                    'title' => 'Metrics Overview',
                    'x_label' => 'Date',
                    'y_label' => 'Value',
                    'fill' => false
                )
            );
            echo $chart_generator->render_chart('metrics-overview', $metrics_config);
            ?>
        </div>

        <!-- Recent Activity Timeline -->
        <div class="mkwa-dashboard-item">
            <h3><?php esc_html_e('Recent Activity', 'mkwa'); ?></h3>
            <div class="mkwa-timeline">
                <?php
                $recent_events = array_slice($analytics_data['events'], 0, 5);
                foreach ($recent_events as $event) {
                    $event_data = json_decode($event['data'], true);
                    ?>
                    <div class="mkwa-timeline-item">
                        <span class="mkwa-timeline-date">
                            <?php echo esc_html(date('M j, Y H:i', strtotime($event['timestamp']))); ?>
                        </span>
                        <span class="mkwa-timeline-event">
                            <?php echo esc_html($event['event_type']); ?>
                        </span>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Export Controls -->
    <div class="mkwa-chart-export-controls">
        <button class="mkwa-chart-export" data-chart-id="page-views" data-format="png">
            <?php esc_html_e('Export Page Views', 'mkwa'); ?>
        </button>
        <button class="mkwa-chart-export" data-chart-id="event-distribution" data-format="png">
            <?php esc_html_e('Export Event Distribution', 'mkwa'); ?>
        </button>
        <button class="mkwa-chart-export" data-chart-id="metrics-overview" data-format="png">
            <?php esc_html_e('Export Metrics Overview', 'mkwa'); ?>
        </button>
    </div>
</div>