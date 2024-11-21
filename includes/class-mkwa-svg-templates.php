// includes/class-mkwa-svg-templates.php

class MKWA_SVG_Templates {
    private static $colors = [
        'bronze' => ['#CD7F32', '#8B4513'],
        'silver' => ['#C0C0C0', '#808080'],
        'gold'   => ['#FFD700', '#DAA520'],
        'basic'  => ['#4CAF50', '#388E3C'],
        'rare'   => ['#2196F3', '#1976D2'],
        'epic'   => ['#9C27B0', '#7B1FA2']
    ];

    public static function generate_badge($type, $tier, $text) {
        $colors = self::$colors[$tier] ?? self::$colors['basic'];
        
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
            <!-- Gradient Definition -->
            <defs>
                <linearGradient id="badge-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:{$colors[0]};stop-opacity:1" />
                    <stop offset="100%" style="stop-color:{$colors[1]};stop-opacity:1" />
                </linearGradient>
                <!-- Add shine effect -->
                <filter id="shine">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="3" result="blur" />
                    <feSpecularLighting in="blur" surfaceScale="5" specularConstant=".75" 
                                      specularExponent="20" lighting-color="#white" result="shine">
                        <fePointLight x="150" y="60" z="20" />
                    </feSpecularLighting>
                    <feComposite in="shine" in2="SourceAlpha" operator="in" result="shineFinal" />
                </filter>
            </defs>
            
            <!-- Badge Background -->
            <circle cx="100" cy="100" r="90" fill="url(#badge-gradient)" />
            <circle cx="100" cy="100" r="90" fill="white" filter="url(#shine)" opacity="0.3" />
            
            <!-- Badge Icon -->
            <g transform="translate(50, 50)">
                <!-- Dynamic icon based on type -->
                {self::get_icon_path($type)}
            </g>
            
            <!-- Badge Text -->
            <text x="100" y="160" font-family="Arial, sans-serif" font-size="16" 
                  font-weight="bold" text-anchor="middle" fill="white">{$text}</text>
        </svg>
        SVG;
    }

    private static function get_icon_path($type) {
        $icons = [
            'workout' => 'M10 20 L90 20 M50 10 L50 30 M30 40 L70 40',
            'streak' => 'M50 10 L90 90 L10 90 Z',
            'community' => 'M25 50 A25 25 0 1 1 75 50 A25 25 0 1 1 25 50',
            // Add more icon paths as needed
        ];
        
        return $icons[$type] ?? $icons['workout'];
    }
}