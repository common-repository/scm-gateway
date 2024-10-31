<?php
class SCM_gateway {

	var $api_actions = array(
		'get_auth',
		'get_posts',
		'get_posts_urls',
		'get_categories',
		'get_users',
		'insert_post',
	);

	public function __construct(){
		global $wp_json_basic_auth_error;

		add_action('admin_init', array(&$this,'admin_init'));
		add_action('admin_menu', array(&$this,'add_menu'));
		$plugin = plugin_basename(SCM_GATEWAY_PATH."scm-gateway.php");
		add_filter("plugin_action_links_".$plugin, array(&$this, 'plugin_settings_link'));


		add_filter( 'determine_current_user', array(&$this, 'json_basic_auth_handler'), 20 );
		add_filter( 'json_authentication_errors', array(&$this, 'json_basic_auth_error') );


		add_action('init', array(&$this, 'custom_rewrite_basic'));
	}

	public function admin_init(){
		register_setting('scm_gateway','setting_1');
		register_setting('scm_gateway','setting_2');
	}

	public function add_menu(){
	    add_options_page(__('SCM Gateway Settings', 'scm_gateway'), 'SCM Gateway', 'manage_options', 'scm_gateway', array(&$this, 'plugin_settings_page'));
	}

	public function plugin_settings_link($links){
        $settings_link = '<a href="options-general.php?scm_gateway=bootnet">'.__('Settings', 'scm_gateway').'</a>';
        array_unshift($links, $settings_link);
        return $links;
	}

	public function plugin_settings_page(){
		if(!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		include(SCM_GATEWAY_ADMIN_PAGES_PATH."settings.php");
	}

	/*
	Rewrite
	*/
	function custom_rewrite_basic() {
		global $wp_json_basic_auth_error;


		$scm_action = isset($_REQUEST['scm_action']) ? htmlspecialchars($_REQUEST['scm_action']) : false;
		if($scm_action && in_array($scm_action, $this->api_actions))
		{
			if($wp_json_basic_auth_error !== true)
			{
				wp_send_json(array('response'=>$wp_json_basic_auth_error), 401);
				die();
			}

			$response = array(
				'status' => 'ok',
				'action' => $scm_action,
			);

			if($scm_action == 'get_auth')
			{
				$response['user_id'] = $wp_json_basic_auth_error;
			}
			if($scm_action == 'get_categories')
			{
				if( current_user_can('manage_categories') ) {
					$args = array(
						'type'         => 'post',
						'orderby'      => 'name',
						'order'        => 'ASC',
						'hide_empty'        => 0,
					);
					$categories = get_categories( $args );
					$response['categories'] = $categories;
				}
			}

			if($scm_action == 'get_posts')
			{
				if( current_user_can('read_private_posts') && current_user_can('read')) {
					add_filter( 'posts_where' , 'posts_where' );

					function posts_where($where) {
						global $wpdb;
						if(!empty($_POST['filter']))
						{
							if(!empty($_POST['filter']['date']))
							{
								$date = (new wpdb())->_real_escape( $_POST['filter']['date'] );
								$where .= ' AND post_modified >= "'.$date.'"';
							}
						}
						return $where;
					}

					$limit = intval($_POST['filter']['limit']);
					$offset = isset($_POST['filter']['offset']) ? intval($_POST['filter']['offset']) : false;
					$posts_per_page = isset($_POST['filter']['posts_per_page']) ? intval($_POST['filter']['posts_per_page']) : false;
					$args = array(
						'type'         => 'post',
						'orderby'      => 'post_modified',
						'order'        => 'DESC',
						'numberposts'        => $limit ? : -1,
						'post_status'        => 'any',
						'suppress_filters' => false
					);
					if($offset !== false)
						$args['offset'] = $offset;
					if($posts_per_page !== false)
						$args['posts_per_page'] = $posts_per_page;
					$posts = get_posts( $args );

					foreach($posts as &$post)
					{
						$cats = wp_get_post_categories( $post->ID );
						$post->categories = $cats;

						$post->url = get_permalink( $post->ID );
					}
					unset($post);

					wp_reset_postdata();

					$response['posts'] = $posts;
				}
			}

			if($scm_action == 'get_posts_urls')
			{
				if( current_user_can('read_private_posts') && current_user_can('read')) {
					add_filter( 'posts_where' , 'posts_where' );

					function posts_where($where) {
						global $wpdb;
						if(!empty($_POST['filter']))
						{
							if(!empty($_POST['filter']['date']))
							{
								$date = (new wpdb())->_real_escape( $_POST['filter']['date'] );
								$where .= ' AND post_modified >= "'.$date.'"';
							}

						}
						return $where;
					}

					$limit = intval($_POST['filter']['limit']);
					$offset = isset($_POST['filter']['offset']) ? intval($_POST['filter']['offset']) : false;
					$posts_per_page = isset($_POST['filter']['posts_per_page']) ? intval($_POST['filter']['posts_per_page']) : false;
					$args = array(
						'type'         => 'post',
						'orderby'      => 'post_modified',
						'order'        => 'DESC',
						'numberposts'        => $limit ? : -1,
						'post_status'        => 'any',
						'suppress_filters' => false
					);
					if(!empty($_POST['filter']['id']))
					{
						$args['include'] = $_POST['filter']['id'];
					}
					if($offset !== false)
						$args['offset'] = $offset;
					if($posts_per_page !== false)
						$args['posts_per_page'] = $posts_per_page;
					$posts = get_posts( $args );

					$arResult = array();
					foreach($posts as &$post)
					{
						$arResult[$post->ID] = get_permalink( $post->ID );
					}
					unset($post);

					wp_reset_postdata();

					$response['urls'] = $arResult;
				}
			}

			if($scm_action == 'insert_post' && !empty($_POST))
			{
				if( current_user_can('edit_posts')) {

					$cat = intval($_POST['post_category']);
					if(!is_array($_POST['post_category']) && !empty($_POST['post_category'])) $cat = array(intval($_POST['post_category']));
					# Данные для публикации записи:
					$source = array(
					  'post_title' => sanitize_text_field(htmlspecialchars($_POST['post_title'])),
					  'post_content' => sanitize_text_field($_POST['post_content']),
					  'post_date' => sanitize_text_field($_POST['post_date']),
					  'post_status' => 'publish',
					  'post_author' => intval($_POST['post_author']),
					  'post_type' => 'post',
					  'post_category' => $cat,
					  'tags_input' => !is_array($_POST['tags_input']) ? sanitize_text_field($_POST['tags_input']) : '',
					  'comment_status' => 'open'
					);


					$r = wp_insert_post($source, true);

					if($r > 0)
					{
						//wp_set_object_terms( $r, $cat, 'category' );

						$args = array(
							'type'         => 'post',
							'post_status'        => 'any',
							'numberposts'        => 0,
							'include'      => $r,
						);
						$post = get_posts( $args );
						$response['post'] = $post;
					}
					else
					{
						wp_send_json(array('response'=>$r), 400);
						die();
					}

				}
			}

			if($scm_action == 'get_users')
			{
				//list_users
				if( current_user_can('list_users')) {

					$args = array(

					);
					$users = get_users( $args );
					$response['users'] = $users;
				}
			}

			wp_send_json($response , 200);

			die();
		}
	}


	/*
	Basic Auth
	*/
	function json_basic_auth_handler( $user ) {

		global $wp_json_basic_auth_error;
		$wp_json_basic_auth_error = null;
		// Don't authenticate twice
		if ( ! empty( $user ) ) {
			return $user;
		}

		if ( empty( $_SERVER['PHP_AUTH_USER'] )  && isset($_REQUEST['HTTP_AUTHORIZATION']) ) {
			base64_decode($_REQUEST['HTTP_AUTHORIZATION']);
			list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) =
  				explode(':', base64_decode($_REQUEST['HTTP_AUTHORIZATION']));
		}
		// Check that we're trying to authenticate
		if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $user;
		}
		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];
		/**
		 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
		 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
		 * recursion and a stack overflow unless the current function is removed from the determine_current_user
		 * filter during authentication.
		 */

		remove_filter( 'determine_current_user', 'json_basic_auth_handler', 20 );
		$user = wp_authenticate( $username, $password );
		add_filter( 'determine_current_user', array(&$this, 'json_basic_auth_handler'), 20 );
		if ( is_wp_error( $user ) ) {
			$wp_json_basic_auth_error = $user;
			return null;
		}

		$wp_json_basic_auth_error = true;
		return $user->ID;
	}



	function json_basic_auth_error( $error ) {
		// Passthrough other errors
		if ( ! empty( $error ) ) {
			return $error;
		}
		global $wp_json_basic_auth_error;
		return $wp_json_basic_auth_error;
	}


}
?>