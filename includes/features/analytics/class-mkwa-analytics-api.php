<?php
// includes/features/analytics/class-mkwa-analytics-api.php

defined('ABSPATH') || exit;

class MKWA_Analytics_API {
    private static $instance = null;
    private $namespace = 'mkwa/v1';
    private $base = 'analytics';
    private $report_generator;
    private $analytics_manager;
    private $cache_expiration = 300; // 5 minutes
    private $rate_limit = 100; // requests per hour

    private function __construct() {
        $this->report_generator = MKWA_Report_Generator::get_instance();
        $this->analytics_manager = MKWA_Analytics_Manager::get_instance();
        $this->init();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Get analytics reports
        register_rest_route($this->namespace, "/{$this->base}/reports/(?P<member_id>\d+)", array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_member_report'),
                'permission_callback' => array($this, 'check_report_access'),
                'args' => $this->get_report_args()
            )
        ));

        // Get specific metrics
        register_rest_route($this->namespace, "/{$this->base}/metrics/(?P<member_id>\d+)", array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_member_metrics'),
                'permission_callback' => array($this, 'check_metrics_access'),
                'args' => $this->get_metrics_args()
            )
        ));

        // Export analytics data
        register_rest_route($this->namespace, "/{$this->base}/export/(?P<member_id>\d+)", array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'export_analytics_data'),
                'permission_callback' => array($this, 'check_export_access'),
                'args' => $this->get_export_args()
            )
        ));

        // Get analytics insights
        register_rest_route($this->namespace, "/{$this->base}/insights/(?P<member_id>\d+)", array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_member_insights'),
                'permission_callback' => array($this, 'check_insights_access'),
                'args' => $this->get_insights_args()
            )
        ));

        // Batch analytics requests
        register_rest_route($this->namespace, "/{$this->base}/batch", array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'process_batch_request'),
                'permission_callback' => array($this, 'check_batch_access'),
                'args' => $this->get_batch_args()
            )
        ));
    }

    // Permission Callbacks
    public function check_report_access($request) {
        $member_id = $request->get_param('member_id');
        return $this->verify_member_access($member_id);
    }

    public function check_metrics_access($request) {
        $member_id = $request->get_param('member_id');
        return $this->verify_member_access($member_id);
    }

    public function check_export_access($request) {
        $member_id = $request->get_param('member_id');
        return $this->verify_member_access($member_id) && current_user_can('export_analytics_data');
    }

    public function check_insights_access($request) {
        $member_id = $request->get_param('member_id');
        return $this->verify_member_access($member_id);
    }

    public function check_batch_access($request) {
        return current_user_can('access_analytics_api');
    }

    // Main Route Callbacks with Improved Error Handling and Caching
    public function get_member_report($request) {
        $member_id = $request->get_param('member_id');
        $report_type = $request->get_param('type');
        $date_range = $request->get_param('date_range');
        $options = $request->get_param('options');

        try {
            $this->check_rate_limit($member_id);
            $cache_key = "report_{$member_id}_{$report_type}_{$date_range}";
            
            $cached_data = $this->get_cached_response($cache_key);
            if ($cached_data !== false) {
                return new WP_REST_Response($cached_data, 200);
            }

            $report = $this->report_generator->generate_member_report(
                $member_id,
                $report_type,
                $date_range,
                $options
            );

            $this->set_cached_response($cache_key, $report);
            $this->log_api_request('get_member_report', compact('member_id', 'report_type'), 200);

            return new WP_REST_Response($report, 200);
        } catch (Exception $e) {
            $this->log_api_request('get_member_report', compact('member_id', 'report_type'), 500);
            return new WP_Error('report_generation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    public function get_member_metrics($request) {
        $member_id = $request->get_param('member_id');
        $metrics = $request->get_param('metrics');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

        try {
            $this->check_rate_limit($member_id);
            $this->validate_date_range($start_date, $end_date);
            
            $cache_key = "metrics_{$member_id}_" . md5(json_encode($metrics) . $start_date . $end_date);
            
            $cached_data = $this->get_cached_response($cache_key);
            if ($cached_data !== false) {
                return new WP_REST_Response($cached_data, 200);
            }

            $metrics_data = $this->analytics_manager->get_member_metrics(
                $member_id,
                $metrics,
                $start_date,
                $end_date
            );

            $this->set_cached_response($cache_key, $metrics_data);
            $this->log_api_request('get_member_metrics', compact('member_id', 'metrics'), 200);

            return new WP_REST_Response($metrics_data, 200);
        } catch (Exception $e) {
            $this->log_api_request('get_member_metrics', compact('member_id', 'metrics'), 500);
            return new WP_Error('metrics_fetch_failed', $e->getMessage(), array('status' => 500));
        }
    }

    public function export_analytics_data($request) {
        $member_id = $request->get_param('member_id');
        $format = $request->get_param('format');
        $date_range = $request->get_param('date_range');
        $data_types = $request->get_param('data_types');

        try {
            $this->check_rate_limit($member_id);

            $export_data = $this->analytics_manager->export_data(
                $member_id,
                $data_types,
                $date_range,
                $format
            );

            $filename = "analytics_export_{$member_id}_" . date('Y-m-d') . "." . $format;
            
            $this->log_api_request('export_analytics_data', compact('member_id', 'format'), 200);

            header('Content-Type: ' . $this->get_content_type($format));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo $export_data;
            exit;
        } catch (Exception $e) {
            $this->log_api_request('export_analytics_data', compact('member_id', 'format'), 500);
            return new WP_Error('export_failed', $e->getMessage(), array('status' => 500));
        }
    }

    public function get_member_insights($request) {
        $member_id = $request->get_param('member_id');
        $insight_types = $request->get_param('types');
        $timeframe = $request->get_param('timeframe');

        try {
            $this->check_rate_limit($member_id);
            
            $cache_key = "insights_{$member_id}_" . md5(json_encode($insight_types) . $timeframe);
            
            $cached_data = $this->get_cached_response($cache_key);
            if ($cached_data !== false) {
                return new WP_REST_Response($cached_data, 200);
            }

            $insights = $this->analytics_manager->generate_insights(
                $member_id,
                $insight_types,
                $timeframe
            );

            $this->set_cached_response($cache_key, $insights);
            $this->log_api_request('get_member_insights', compact('member_id', 'insight_types'), 200);

            return new WP_REST_Response($insights, 200);
        } catch (Exception $e) {
            $this->log_api_request('get_member_insights', compact('member_id', 'insight_types'), 500);
            return new WP_Error('insights_generation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    public function process_batch_request($request) {
        $batch_requests = $request->get_param('requests');
        $responses = array();

        try {
            foreach ($batch_requests as $req) {
                $internal_request = new WP_REST_Request(
                    $req['method'],
                    $this->namespace . '/' . $this->base . '/' . $req['endpoint']
                );

                if (!empty($req['params'])) {
                    foreach ($req['params'] as $key => $value) {
                        $internal_request->set_param($key, $value);
                    }
                }

                $response = rest_do_request($internal_request);
                $responses[] = array(
                    'id' => $req['id'] ?? null,
                    'response' => $response->get_data(),
                    'status' => $response->get_status()
                );
            }

            $this->log_api_request('process_batch_request', array('count' => count($batch_requests)), 200);
            return new WP_REST_Response($responses, 200);
        } catch (Exception $e) {
            $this->log_api_request('process_batch_request', array('error' => $e->getMessage()), 500);
            return new WP_Error('batch_processing_failed', $e->getMessage(), array('status' => 500));
        }
    }

    // Argument Definitions
    private function get_report_args() {
        return array(
            'type' => array(
                'required' => true,
                'type' => 'string',
                'enum' => array(
                    'workout_summary',
                    'progress_tracking',
                    'achievement_overview',
                    'nutrition_analysis',
                    'program_progress',
                    'comprehensive'
                )
            ),
            'date_range' => array(
                'required' => false,
                'type' => 'string',
                'default' => 'last_30_days'
            ),
            'options' => array(
                'required' => false,
                'type' => 'object',
                'default' => array()
            )
        );
    }

    private function get_metrics_args() {
        return array(
            'metrics' => array(
                'required' => true,
                'type' => 'array',
                'items' => array(
                    'type' => 'string'
                )
            ),
            'start_date' => array(
                'required' => false,
                'type' => 'string',
                'format' => 'date-time'
            ),
            'end_date' => array(
                'required' => false,
                'type' => 'string',
                'format' => 'date-time'
            )
        );
    }

    private function get_export_args() {
        return array(
            'format' => array(
                'required' => false,
                'type' => 'string',
                'enum' => array('csv', 'json', 'xml', 'pdf'),
                'default' => 'csv'
            ),
            'date_range' => array(
                'required' => false,
                'type' => 'string',
                'default' => 'last_30_days'
            ),
            'data_types' => array(
                'required' => false,
                'type' => 'array',
                'items' => array(
                    'type' => 'string'
                ),
                'default' => array('all')
            )
        );
    }

    private function get_insights_args() {
        return array(
            'types' => array(
                'required' => false,
                'type' => 'array',
                'items' => array(
                    'type' => 'string'
                ),
                'default' => array('all')
            ),
            'timeframe' => array(
                'required' => false,
                'type' => 'string',
                'default' => 'last_30_days'
            )
        );
    }

    private function get_batch_args() {
        return array(
            'requests' => array(
                'required' => true,
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'required' => array('method', 'endpoint'),
                    'properties' => array(
                        'id' => array(
                            'type' => 'string'
                        ),
                        'method' => array(
                            'type' => 'string',
                            'enum' => array('GET', 'POST', 'PUT', 'DELETE')
                        ),
                        'endpoint' => array(
                            'type' => 'string'
                        ),
                        'params' => array(
                            'type' => 'object'
                        )
                    )
                )
            )
        );
    }

    // Utility Methods
    private function verify_member_access($member_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        // Verify nonce except for trusted API consumers
        if (!$this->is_trusted_api_consumer() && !wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'mkwa_analytics_nonce')) {
            return false;
        }

        $current_user_id = get_current_user_id();
        
        return ($current_user_id == $member_id) || 
               current_user_can('manage_analytics') || 
               current_user_can('administrator');
    }

    private function is_trusted_api_consumer() {
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        return in_array($api_key, $this->get_trusted_api_keys());
    }

    private function get_trusted_api_keys() {
        return apply_filters('mkwa_trusted_api_keys', array());
    }

    private function check_rate_limit($member_id) {
        $rate_key = "mkwa_analytics_rate_{$member_id}";
        $rate_limit = get_transient($rate_key);
        
        if ($rate_limit !== false && $rate_limit >= $this->rate_limit) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        set_transient($rate_key, ($rate_limit ? $rate_limit + 1 : 1), HOUR_IN_SECONDS);
        return true;
    }

    private function get_cached_response($cache_key) {
        return wp_cache_get($cache_key, 'mkwa_analytics');
    }

    private function set_cached_response($cache_key, $data, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->cache_expiration;
        }
        wp_cache_set($cache_key, $data, 'mkwa_analytics', $expiration);
    }

    private function validate_date_range($start_date, $end_date) {
        if (!$start_date || !$end_date) {
            return;
        }

        if (strtotime($end_date) < strtotime($start_date)) {
            throw new Exception('End date cannot be before start date');
        }
        
        if (strtotime($start_date) < strtotime('-1 year')) {
            throw new Exception('Date range cannot exceed 1 year');
        }
    }

    private function get_content_type($format) {
        $content_types = array(
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf'
        );

        return $content_types[$format] ?? 'application/octet-stream';
    }

    private function log_api_request($endpoint, $params, $status) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[%s] MKWA Analytics API Request: %s, Params: %s, Status: %d',
                current_time('mysql'),
                $endpoint,
                json_encode($params),
                $status
            ));
        }
    }
}