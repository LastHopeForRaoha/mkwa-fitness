// includes/class-mkwa-image-optimizer.php

class MKWA_Image_Optimizer {
    private $cache_dir;
    private $cache_time = 86400; // 24 hours

    public function __construct() {
        $this->cache_dir = MKWA_PLUGIN_DIR . 'assets/cache/';
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    public function optimize_svg($svg_content) {
        // Remove comments
        $svg_content = preg_replace('/<!--.*?-->/s', '', $svg_content);
        
        // Remove white space
        $svg_content = preg_replace('/>\s+</', '><', $svg_content);
        
        return trim($svg_content);
    }

    public function cache_svg($badge_id, $svg_content) {
        $cache_file = $this->cache_dir . md5($badge_id) . '.svg';
        file_put_contents($cache_file, $this->optimize_svg($svg_content));
        return $cache_file;
    }

    public function get_cached_svg($badge_id) {
        $cache_file = $this->cache_dir . md5($badge_id) . '.svg';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < $this->cache_time)) {
            return file_get_contents($cache_file);
        }
        
        return false;
    }
}