// admin/templates/badge-manager.php
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="mkwa-badge-controls">
        <select id="mkwa-badge-type">
            <option value="workout">Workout Badge</option>
            <option value="streak">Streak Badge</option>
            <option value="community">Community Badge</option>
        </select>
        
        <select id="mkwa-badge-tier">
            <option value="bronze">Bronze</option>
            <option value="silver">Silver</option>
            <option value="gold">Gold</option>
        </select>
        
        <input type="text" id="mkwa-badge-text" placeholder="Badge Text">
        
        <button class="button button-primary" id="mkwa-generate-badge">
            Generate Badge
        </button>
    </div>
    
    <div class="mkwa-badge-preview">
        <h3>Preview</h3>
        <div id="badge-preview-container"></div>
    </div>
    
    <div class="mkwa-badge-list">
        <h3>Existing Badges</h3>
        <div id="badge-list-container"></div>
    </div>
</div>