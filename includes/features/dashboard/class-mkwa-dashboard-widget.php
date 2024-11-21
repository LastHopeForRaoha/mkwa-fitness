// includes/features/dashboard/class-mkwa-dashboard-widget.php

class MKWA_Dashboard_Widget {
    private $id;
    private $title;
    private $type;
    private $data_callback;
    private $settings;
    private $refresh_interval;
    private $size;

    public function __construct($id, $args = array()) {
        $defaults = array(
            'title' => '',
            'type' => 'chart',
            'data_callback' => null,
            'settings' => array(),
            'refresh_interval' => 0,
            'size' => 'normal'
        );

        $args = wp_parse_args($args, $defaults);

        $this->id = sanitize_key($id);
        $this->title = sanitize_text_field($args['title']);
        $this->type = $args['type'];
        $this->data_callback = $args['data_callback'];
        $this->settings = $args['settings'];
        $this->refresh_interval = absint($args['refresh_interval']);
        $this->size = $args['size'];
    }

    public function render() {
        $widget_data = $this->get_widget_data();
        $widget_class = 'mkwa-dashboard-widget';
        $widget_class .= ' widget-size-' . $this->size;
        $widget_class .= ' widget-type-' . $this->type;
        
        ?>
        <div id="<?php echo esc_attr($this->id); ?>" 
             class="<?php echo esc_attr($widget_class); ?>" 
             data-refresh="<?php echo esc_attr($this->refresh_interval); ?>">
            
            <div class="widget-header">
                <h3 class="widget-title"><?php echo esc_html($this->title); ?></h3>
                <div class="widget-actions">
                    <?php $this->render_actions(); ?>
                </div>
            </div>
            
            <div class="widget-content">
                <?php $this->render_content($widget_data); ?>
            </div>
        </div>
        <?php
    }

    private function get_widget_data() {
        if (is_callable($this->data_callback)) {
            return call_user_func($this->data_callback, $this->settings);
        }
        return array();
    }

    private function render_actions() {
        ?>
        <div class="widget-controls">
            <?php if ($this->refresh_interval > 0) : ?>
                <button class="refresh-widget" title="Refresh">
                    <span class="dashicons dashicons-update"></span>
                </button>
            <?php endif; ?>
            <button class="configure-widget" title="Configure">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
        </div>
        <?php
    }

    private function render_content($data) {
        switch ($this->type) {
            case 'chart':
                $this->render_chart($data);
                break;
            case 'stats':
                $this->render_stats($data);
                break;
            case 'list':
                $this->render_list($data);
                break;
            case 'progress':
                $this->render_progress($data);
                break;
            default:
                $this->render_custom($data);
                break;
        }
    }

    private function render_chart($data) {
        $chart_settings = wp_parse_args($this->settings['chart'] ?? array(), array(
            'type' => 'line',
            'options' => array()
        ));
        ?>
        <div class="chart-container">
            <canvas id="<?php echo esc_attr($this->id); ?>-chart"
                    data-chart='<?php echo esc_attr(wp_json_encode($data)); ?>'
                    data-settings='<?php echo esc_attr(wp_json_encode($chart_settings)); ?>'>
            </canvas>
        </div>
        <?php
    }

    private function render_stats($data) {
        if (empty($data) || !is_array($data)) {
            return;
        }
        ?>
        <div class="stats-grid">
            <?php foreach ($data as $stat) : ?>
                <div class="stat-item">
                    <div class="stat-label"><?php echo esc_html($stat['label']); ?></div>
                    <div class="stat-value"><?php echo esc_html($stat['value']); ?></div>
                    <?php if (!empty($stat['change'])) : ?>
                        <div class="stat-change <?php echo esc_attr($stat['change']['direction']); ?>">
                            <?php echo esc_html($stat['change']['value']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_list($data) {
        if (empty($data) || !is_array($data)) {
            return;
        }
        ?>
        <ul class="widget-list">
            <?php foreach ($data as $item) : ?>
                <li class="list-item">
                    <?php if (!empty($item['icon'])) : ?>
                        <span class="item-icon <?php echo esc_attr($item['icon']); ?>"></span>
                    <?php endif; ?>
                    
                    <span class="item-label"><?php echo esc_html($item['label']); ?></span>
                    
                    <?php if (!empty($item['value'])) : ?>
                        <span class="item-value"><?php echo esc_html($item['value']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private function render_progress($data) {
        if (empty($data) || !isset($data['current'], $data['total'])) {
            return;
        }
        
        $percentage = min(100, ($data['current'] / $data['total']) * 100);
        ?>
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo esc_attr($percentage); ?>%">
                <span class="progress-text">
                    <?php echo esc_html($data['current']); ?> / <?php echo esc_html($data['total']); ?>
                </span>
            </div>
        </div>
        <?php
    }

    private function render_custom($data) {
        if (isset($this->settings['custom_template']) && is_callable($this->settings['custom_template'])) {
            call_user_func($this->settings['custom_template'], $data, $this);
        }
    }

    public function get_id() {
        return $this->id;
    }

    public function get_settings() {
        return $this->settings;
    }

    public function update_settings($new_settings) {
        $this->settings = wp_parse_args($new_settings, $this->settings);
    }
}