<?php
namespace ConverseLab;

if(!defined('ABSPATH')){
    exit;
}

class Plugin{
    public static function init(){
        add_action('admin_menu',[__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts',[__CLASS__,'enqueue_admin_assests']);
        add_action('init', [__CLASS__, 'register_gutenberg_block']);
    }
    /**
     * register menu
     */

    public static function register_menu(){
        add_menu_page(
            'Converselab',
            'Converselab',
            'manage_options',
            'converselab',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic'
        );

        add_submenu_page(
            'converselab',
            'Note App',
            'Notes App',
            'manage_options',
            'converselab-react-app',
            [__CLASS__,'render_react_page']
        );
    }

    /**
     * Set default options on plugin activation
     */
    public static function set_defaults() {
        if (get_option('converselab_notes_enabled') === false) {
            update_option('converselab_notes_enabled', 0);
        }

        if (get_option('converselab_notes_default_count') === false) {
            update_option('converselab_notes_default_count', 5);
        }

        if (get_option('converselab_notes_allowed_roles') === false) {
            update_option('converselab_notes_allowed_roles', []);
        }
    }

    public static function deactivate() {
        //
    }
    
    /**
     * render setting page
     */

    public static function render_settings_page(){
        if(!current_user_can('manage_options')){
            return;
        }

        $error_messages=[];

    // Handle form submission
        if(isset($_POST['converselab_settings_submit'])){

            if(!isset($_POST['converselab_nonce']) ||
                !wp_verify_nonce($_POST['converselab_nonce'],'converselab_save_settings')){
                $error_messages[] = __('Invalid security token.', 'converselab');
    ;
            }

            // Checkbox
            $notes_enabled = isset($_POST['converselab_notes_enabled']) ? 1 : 0;

            // Integer
            if (isset($_POST['converselab_notes_default_count'])) {
                    $default_count = intval($_POST['converselab_notes_default_count']);
                    if ($default_count < 1) {
                        $error_messages[] = __('Default Notes Count must be at least 1.', 'converselab');
                        $default_count = get_option('converselab_notes_default_count', 5);
                    }
                } else {
                    $default_count = get_option('converselab_notes_default_count', 5);
                }

            // Roles
            if(isset($_POST['converselab_notes_allowed_roles'])){
                $allowed_roles = $_POST['converselab_notes_allowed_roles'];

                if(!is_array($allowed_roles)){
                    $allowed_roles = [];
                }

                foreach($allowed_roles as $key => $role){
                    $allowed_roles[$key] = sanitize_text_field($role);
                }
            } else{
                $allowed_roles = [];
            }

            if (empty($error_messages)) {
                    update_option('converselab_notes_enabled', $notes_enabled);
                    update_option('converselab_notes_default_count', $default_count);
                    update_option('converselab_notes_allowed_roles', $allowed_roles);

                    echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'converselab') . '</p></div>';
                } else{
                    foreach ($error_messages as $error){
                        echo '<div class="error notice"><p>' . esc_html($error) . '</p></div>';
                    }
                }
        }

    $notes_enabled = get_option( 'converselab_notes_enabled', 0 );
    $default_count = get_option( 'converselab_notes_default_count', 5 );
    $allowed_roles = get_option( 'converselab_notes_allowed_roles', [] );

    
    require CONVERSELAB_PATH . 'includes/views/settings-page.php';
    }

    public static function render_react_page(){
        echo '<div class="wrap"><div id="converselab-admin-app">Loading App....</div></div>';
    }

    public static function enqueue_admin_assests($hook){
        if($hook !=='converselab_page_converselab-react-app'){
            return;
        }

        $asset_path = CONVERSELAB_PATH . 'build/index.asset.php';

        if(!file_exists($asset_path)){
            return;
        }
        $asser_file = include($asset_path);
        $plugin_url = defined('CONVERSELAB_URL')?CONVERSELAB_URL : plugin_dir_url(dirname(__FILE__)); 

        wp_enqueue_script(
            'converselab-admin-js',
            $plugin_url.'build/index.js',
            $asser_file['dependencies'],
            $asser_file['version'],
            true
        );
        wp_localize_script('converselab-admin-js','converselabSettings',[
            'restUrl'=> esc_url_raw(rest_url('converselab/v1/notes')),
            'nonce'=> wp_create_nonce('wp_rest')
        ]);

    }
    /**
     * Register Gutenberg Block & Assets
     */
    public static function register_gutenberg_block() {
        $asset_path = CONVERSELAB_PATH . 'build/index.asset.php';

        if(!file_exists($asset_path)){
            return;
        }
        $asset_file = include($asset_path);
        $plugin_url = defined('CONVERSELAB_URL') ? CONVERSELAB_URL : plugin_dir_url(dirname(__FILE__)); 

        
        wp_register_script(
            'converselab-block-js',
            $plugin_url . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        // Pass our security nonce and API URL to the block
        wp_localize_script('converselab-block-js', 'converselabSettings', [
            'restUrl' => esc_url_raw(rest_url('converselab/v1/notes')),
            'nonce'   => wp_create_nonce('wp_rest')
        ]);

    
        register_block_type('converselab/notes-block', [
            'editor_script' => 'converselab-block-js',
            'render_callback' => [__CLASS__, 'render_notes_frontend'] 
            // We will add 'render_callback' here on Day 2!
        ]);
    }

    /**
     * Render the block on the frontend (PHP)
     */
    public static function render_notes_frontend($attributes) {
        $count = isset($attributes['count']) ? intval($attributes['count']) : 5;
        $priority = isset($attributes['priority']) ? sanitize_text_field($attributes['priority']) : 'all';
        $showSource = isset($attributes['showSource']) ? (bool) $attributes['showSource'] : true;

    
        $args = [
            'post_type'      => 'converselab_note',
            'posts_per_page' => $count,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC'
        ];


        if ($priority !== 'all') {
            $args['meta_key']   = 'converselab_note_priority';
            $args['meta_value'] = $priority;
        }

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return '<p>No notes found.</p>';
        }

        $output = '<div class="converselab-notes-wrapper" style="padding: 20px; border: 1px solid #ddd; background: #fff;">';
        $output .= '<h3>' . esc_html__('Latest Notes', 'converselab') . '</h3>';
        $output .= '<ul style="list-style: none; padding: 0;">';

        while ($query->have_posts()) {
            $query->the_post();
            
            $note_id = get_the_ID();
            $note_priority = get_post_meta($note_id, 'converselab_note_priority', true);
            $note_source = get_post_meta($note_id, 'converselab_note_source_url', true);
            
            $badge_color = '#ccc';
            if($note_priority === 'high') $badge_color = '#d63638';
            if($note_priority === 'medium') $badge_color = '#dba617';
            if($note_priority === 'low') $badge_color = '#00a32a';

            $output .= '<li style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">';
            
            $output .= '<strong>' . esc_html(get_the_title()) . '</strong>';
            if($note_priority) {
                $output .= ' <span style="background: ' . $badge_color . '; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; vertical-align: middle;">' 
                        . esc_html(strtoupper($note_priority)) . '</span>';
            }
            
            $output .= '<div style="margin-top: 5px; color: #555;">' . esc_html(get_the_content()) . '</div>';

            if ($showSource && !empty($note_source)) {
                $output .= '<div style="font-size: 12px; margin-top: 5px;">';
                $output .= '🔗 <a href="' . esc_url($note_source) . '" target="_blank" rel="noopener noreferrer">' . esc_html($note_source) . '</a>';
                $output .= '</div>';
            }

            $output .= '</li>';
        }
        
        $output .= '</ul>';
        $output .= '</div>';

        wp_reset_postdata();

        return $output;
    }

}


    // public static function load_textdomain(): void{
    //     load_plugin_textdomain(
    //         CONVERSELAB_TEXT_DOMAIN,
    //         false,
    //         dirname(plugin_basename(CONVERSELAB_PATH . 'converselab.php')) . '/languages'
    //     );
    // }
