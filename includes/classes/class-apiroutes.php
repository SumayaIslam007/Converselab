<?php
namespace ConverseLab;

class ApiRoutes{
    public static function init(){
        add_action('rest_api_init', [__CLASS__,'register_routes']);
    }

    public static function register_routes(){
        register_rest_route('converselab/v1', '/status', [
            'methods'               => 'GET',
            'callback'              => [__CLASS__,'get_status'],
            //'permission_callback'   =>[__CLASS__,'check_permission'],
            'permission_callback'   =>'__return_true'
        ]);

        register_rest_route('converselab/v1', '/notes', [
            [
                'methods'               => 'GET',
                'callback'              => [__CLASS__,'get_notes'],
                'permission_callback'   =>[__CLASS__,'check_permission'],
                //'permission_callback'   =>'__return_true',
                'args'=>[
                    'page'=>[
                        'validate_callback' => function($param){
                            return is_numeric($param);},
                        'default'=>1,
                    ],
                    'per_page'=>[
                        'validate_callback' => function($param){
                            return is_numeric($param);},
                        'default'=>10,
                    ],
                    'priority'=>[
                        'validate_callback' => function($param){
                            return in_array($param,['low','medium','high']);},
                    ],
                ],
            ],

            [
                'methods'               => 'POST',
                'callback'              => [__CLASS__,'create_note'],
                //'permission_callback'   =>[__CLASS__,'check_permission'],
                'permission_callback'   =>'__return_true',
                'args'                  =>[
                    'title'=>[
                        'required' => true,
                        'sanitize_callback'=>'sanitize_text_field',
                    ],
                    'content'=>[
                        'required' => true,
                        'sanitize_callback'=>'wp_kses_post',
                    ],
                    'priority'=>[
                        'required'=>false,
                        'validate_callback' => function($param){
                            return in_array($param,['low','medium','high']);},
                    ],
                    'source_url'=>[
                        'required'=>false,
                        'validate_callback' => function($param){
                            return filter_var($param, FILTER_VALIDATE_URL);
                        },
                        'sanitize_callback'=>'esc_url_raw',
                    ],
                ],
            ],
        ]);

        register_rest_route('converselab/v1', '/notes/(?P<id>\d+)', [
            [
                'methods'               => 'PUT',
                'callback'              => [__CLASS__,'update_note'],
                'permission_callback'   =>[__CLASS__,'check_update_permission'],
                //'permission_callback'   =>'__return_true',
                'args'                  =>[
                    'title'=>[
                        'sanitize_callback'=>'sanitize_text_field',
                    ],
                    'content'=>[
                        'sanitize_callback'=>'wp_kses_post',
                    ],
                    'priority'=>[
                        'validate_callback' => function($param){
                            return in_array($param,['low','medium','high']);},
                    ],
                    'source_url'=>[
                        'validate_callback' => function($param){
                            return filter_var($param, FILTER_VALIDATE_URL);
                        },
                        'sanitize_callback'=>'esc_url_raw',
                    ],
                ],
            ],

            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'delete_note'],
                'permission_callback' => [__CLASS__, 'check_delete_permission'],
                'args'                => [
                    'force' => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);
    }

    public static function check_permission($request){
        return current_user_can('manage_options');
    }

    public static function get_status($request){
        $user = wp_get_current_user();

        $response =[
            'plugin_version'    => '1.0.0',
            'wp-version'        => get_bloginfo('version'),
            'php_version'       => phpversion(),
            'user_check'        => [
                'id'            => $user->ID,
                'username'      => $user->user_login,
                'can_manage'    => user_can( $user, 'manage_options' ),
            ],
            'message'=>'Converselab system is operational',
        ];

        return rest_ensure_response($response);
    }

    public static function get_notes($request){
        $page=$request->get_param('page');
        $per_page=$request->get_param('per_page');
        $priority=$request->get_param('priority');

        $args=[
            'post_type'=>'converselab_note',
            'post_status'=>'any',
            'paged'=>$page,
            'posts_per_page'=>$per_page,
        ];

        if ( ! empty( $priority ) ) {
            $args['meta_key']   = 'converselab_note_priority';
            $args['meta_value'] = $priority;
        }

        $query = new \WP_Query( $args );

        
        $notes = [];
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                
                // Get our custom meta fields
                $prio_val = get_post_meta( get_the_ID(), 'converselab_note_priority', true );
                $src_val  = get_post_meta( get_the_ID(), 'converselab_note_source_url', true );

                $notes[] = [
                    'id'       => get_the_ID(),
                    'title'    => get_the_title(),
                    'content'  => get_the_content(), 
                    'priority' => $prio_val ? $prio_val : 'normal',
                    'source'   => $src_val ? $src_val : '',
                    'date'     => get_the_date( 'Y-m-d H:i:s' ),
                ];
            }
            wp_reset_postdata();
        }

        $response=rest_ensure_response($notes);
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );

        return $response;
    }

    public static function create_note($request){
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $priority = $request->get_param('priority');
        $source = $request->get_param('source_url');

        $post_id = wp_insert_post([
            'post_title'=>$title,
            'post_content'=>$content,
            'post_type'=>'converselab_note',
            'post_status'=>'publish',
        ]);

        if(is_wp_error($post_id)){
            return $post_id;
        }
        if(!empty($priority)){
            update_post_meta($post_id,'converselab_note_priority', $priority);
        }
        if(!empty($source)){
            update_post_meta( $post_id, 'converselab_note_source_url', $source );
        }

        return rest_ensure_response([
            'id'=>$post_id,
            'message'=>'Note created successfully',
            'link'=>get_permalink($post_id),
        ]);
    }

    public static function check_update_permission($request){
        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', 'Sorry, you are not allowed to do that.', ['status' => rest_authorization_required_code()]);
        }

        $post_id = $request->get_param('id');
        $post=get_post($post_id);

        if(!$post){
            return new \WP_Error('rest_invalid_id', 'Note not found', ['status'=>404]);
        }
        if($post->post_type !== 'converselab_note'){
            return new \WP_Error('rest_cannot_update', 'You can only update note', ['status'=>403]);
        }

        return true;
    }

    public static function update_note($request){
        $post_id = $request->get_param('id');
        $post_data=[
            'ID'=>$post_id,
        ];

        if($request->has_param('title')){
            $post_data['post_title']=$request->get_param('title');
        }
        if($request->has_param('content')){
            $post_data['post_content']=$request->get_param('content');
        }
        if(count($post_data)>1){
            $updated_id = wp_update_post($post_data, true);
            if(is_wp_error($updated_id)){
                return $updated_id;
            }
        }
        if($request->has_param('priority')){
            update_post_meta($post_id, 'converselab_note_priority',$request->get_param('priority'));
        }
        if($request->has_param('source_url')){
            update_post_meta($post_id, 'converselab_note_source_url',$request->get_param('source_url'));
        }
        return rest_ensure_response([
            'id'=>$post_id,
            'message'=>'Note update perfectly',
            'link'=>get_permalink($post_id)
        ]);
    }

    public static function check_delete_permission($request){
        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', 'Sorry, you are not allowed to do that.', ['status' => rest_authorization_required_code()]);
        }
        
        $post_id = $request->get_param('id');
        $post=get_post($post_id);

        if(!$post){
            return new \WP_Error('rest_invalid_id', 'Note not found', ['status'=>404]);
        }
        if($post->post_type !== 'converselab_note'){
            return new \WP_Error('rest_cannot_delete', 'You can only delete note', ['status'=>403]);
        }
        return true;
    }

    public static function delete_note($request){
        $post_id = $request->get_param('id');
        $force=$request->get_param('force');

        if($force){
            $result=wp_delete_post($post_id,true);
            $message='Note parmanently deleted';
        }
        else{
            $result=wp_trash_post($post_id);
            $message='Note moved to trash';
        }

        if(!$result){
            return new \WP_Error('rest_cannot_delete', 'The note could not be deleted',['status'=>500]);
        }

        return rest_ensure_response([
            'deleted'=>true,
            'id'=>$post_id,
            'message'=>$message,
        ]);
    }
}