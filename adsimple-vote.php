<?php
/*
 * Plugin Name: AdSimple Vote
 * Plugin URI: https://www.adsimple.at/adsimple-vote
 * Description: Let the users to vote with just one simple click. Create a question and get deep insights in the process. Listen your audience.
 * Version: 1.0.1
 * Author: AdSimple
 * Author URI: https://www.adsimple.at/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define('ASV_FILE', __FILE__);
define('ASV_PATH', dirname(ASV_FILE));
define('ASV_PATH_CLASSES', ASV_PATH . DIRECTORY_SEPARATOR . 'classes');

define('ASV_POST_TYPE', 'adsimplevote');

require_once ASV_PATH_CLASSES . DIRECTORY_SEPARATOR . 'AdSimpleVoteHelper.php';

if (!class_exists('AdSimpleVote')) {

	/**
	 * Core class used to implement a AdSimple-Vote plugin.
	 *
	 * This is used to define internationalization, admin-specific hooks, and
	 * public-facing site hooks.
	 *
	 * @since 1.0.0
	 */
	class AdSimpleVote {

        const VERSION = '1.0.1';

		/**
		 * Sets up a new AdSimple-Vote instance.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {

			/** Load translations. */
			$this->load_textdomain();

			/** Add plugin links. */
			add_filter("plugin_action_links_" . plugin_basename(__FILE__), array($this, 'add_links'));

			/** Registers AdSimpleVote post type. */
			add_action('init', array($this, 'register_adsimplevote_post_type'));

			/** Add REST API support to adsimplevote post type. */
			add_action('init', array($this, 'adsimplevote_rest_support'), 25);

			/**
			 * Since WP 4.7, filter has been removed from WP-API. I have no idea why.
			 * Add the necessary filter to each post type.
			 */
			add_action('rest_api_init', array($this, 'rest_api_filter_add_filters'));

			/** Create editor button for shortcode. */
			add_action('init', array($this, 'add_button'));

			/** Add ShorCode column to votes list. */
			add_filter('manage_adsimplevote_posts_columns', array($this, 'add_head_shortcode_column'), 10);
			add_action('manage_adsimplevote_posts_custom_column', array($this, 'add_content_shortcode_column'), 10, 2);

			/** Add plugin setting page. */
			add_action('admin_menu', array($this, 'add_admin_menu') );
			add_action('admin_init', array($this, 'settings_init') );

			/** Fire meta box setup on the post editor screen. */
			add_action('load-post.php', array($this, 'meta_boxes_setup') );
			add_action('load-post-new.php', array($this, 'meta_boxes_setup') );

			/** Add admin css and js. */
			add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') ); // Add CSS

			/** Add plugin css and js if post has shortcode. */
			add_action('the_posts', array($this, 'enqueue_styles'));

			/** Create [adsimplevote id="ID"] shorcode. */
			add_shortcode('adsimplevote', array($this, 'adsimplevote_shortcode_func'));

			/** Remove unnecessary metaboxes. */
			add_action('edit_form_after_title', array($this, 'remove_unnecessary_metaboxes'), 100);

			/** Creating adsimplevote table. */
			add_action('init', array($this, 'register_adsimplevote_table'), 1);
			add_action('switch_blog', array($this, 'register_adsimplevote_table'));
			register_activation_hook(__FILE__, array($this, 'create_adsimplevote_table'));

			/** Add action to AJAX process vote. */
			add_action('wp_ajax_process_vote', array($this, 'process_vote'));
			add_action('wp_ajax_nopriv_process_vote', array($this, 'process_vote'));

			/** Clear votes on remove vote post. */
			add_action('before_delete_post', array($this, 'before_delete_vote_post'));

			/** Print Open Graph tags */
			add_action('wp_head', array($this, 'open_graph_print'));

			/** Handle Open Graph canonical URL */
			add_filter('get_canonical_url', array($this, 'open_graph_canonical_url'), 50, 1);
		}

		/**
		 * Clear votes after remove vote post.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function before_delete_vote_post($post_id){
			global $wpdb;

			if( get_post_type($post_id) != ASV_POST_TYPE){ // Work only with adsimplevote post type
				return;
			}

			$res = $wpdb->delete(
				$wpdb->adsimplevote,
				array('vote_id' => $post_id)
			);

			return $res;
		}

		/**
		 * AJAX Add vote.
		 * Users can vote 10 times from 1 IP in 24 hours.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function process_vote() {
			$is_new_vote = boolval($_POST['new_vote']);
			$vote_id = intval( $_POST['vote_id'] );
			$vote_val = intval( $_POST['vote_val'] );
			$user_ip = sanitize_text_field(AdSimpleVoteHelper::getIP());
			$guid = sanitize_text_field($_POST['guid']);
			$created = gmdate('Y-m-d H:i:s');
			$modified = gmdate('Y-m-d H:i:s');

			// TODO: Organize logic more gracefully.
			if ($is_new_vote) { // New Vote.
				// Check limits to vote from this IP
				if (AdSimpleVoteHelper::checkVotesLimits($vote_id, $user_ip)) {
					// Insert vote in table
					AdSimpleVoteHelper::insertVote($vote_id, $vote_val, $user_ip, $guid, $created, $modified);
				} else {
					// Exceeded the limit of votes from one IP
					echo json_encode(array( "status" => 0, "message" => __("Exceeded the limit of votes from one IP.", 'adsimple-vote') ));
					wp_die(); // Required to terminate immediately and return a proper response.
				}
			} else { // User alredy voted.
				// Check limits to vote from this IP
				if (AdSimpleVoteHelper::checkVotesLimits($vote_id, $user_ip)) {
					// Update previous vote
					AdSimpleVoteHelper::updateVote($vote_id, $vote_val, $user_ip, $guid, $modified);
				} else {
					// Exceeded the limit of votes from one IP
					echo json_encode(array( "status" => 0, "message" => __("Exceeded the limit of votes from one IP.", 'adsimple-vote') ));
					wp_die(); // Required to terminate immediately and return a proper response.
				}
			}

			// All OK
			echo json_encode(array('status' => 1, 'is_new_vote' => $is_new_vote));
			wp_die(); // Required to terminate immediately and return a proper response.
		}

		/**
		 * Creating a adsimplevote table.
		 * id | vote_id (Post ID) | ip | value | created | modified
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function create_adsimplevote_table() {
			global $wpdb;
			global $charset_collate;

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			/** Call this manually as we may have missed the init hook. */
			$this->register_adsimplevote_table();

			$sql_create_table = "CREATE TABLE {$wpdb->adsimplevote} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				vote_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
				value INT(3) UNSIGNED NOT NULL DEFAULT '0',
				ip varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '',
				guid varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '',
				created DATETIME DEFAULT NULL,
				modified DATETIME DEFAULT NULL,
				PRIMARY KEY (id)
		   ) $charset_collate; ";

			dbDelta( $sql_create_table );
		}

		/**
		 * Store our table name in $wpdb with correct prefix.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function register_adsimplevote_table(){
			global $wpdb;

			$wpdb->adsimplevote = "{$wpdb->prefix}adsimplevote";
		}

		/**
		 * Remove all unnecessary meta boxes.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function remove_unnecessary_metaboxes($post) {
			global $wp_meta_boxes;

			if( $post->post_type != ASV_POST_TYPE){ return; } // Only for Votes

			if( ! isset( $wp_meta_boxes[ASV_POST_TYPE] ) ) { return; } // Does the post have metaboxes?

			/** All metaboxes will be removed except this. */
			$filter_metaboxes = array(
				'submitdiv',
				'postimagediv',
				'postexcerpt',
				'asv-values-meta-box',
                'asv-chart-meta-box',
                'asv-actions-meta-box'
			);

			foreach( (array) $wp_meta_boxes['adsimplevote'] as $context_key => $context_item ) {
				foreach( $context_item as $priority_key => $priority_item ) {
					foreach ($priority_item as $metabox_key => $metabox_item) {
						if( ! in_array($metabox_key, $filter_metaboxes) ) {
							unset($wp_meta_boxes['adsimplevote'][$context_key][$priority_key][$metabox_key]); // Remove meta boxes.
						}
					}
				}
			}
		}

		/**
		 * Add REST API support to adsimplevote post type.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function adsimplevote_rest_support()	{
			global $wp_post_types;

			$post_type_name = ASV_POST_TYPE;
			if( isset( $wp_post_types[ $post_type_name ] ) ) {
				$wp_post_types[$post_type_name]->show_in_rest = true;
			}
		}

		/**
		 * Create editor button for shorcode.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function add_button() {
			if (is_admin() && current_user_can('edit_posts') && current_user_can('edit_pages')) {
				add_filter('mce_external_plugins', array($this, 'add_TinyMCE_plugin') );
				add_filter('mce_buttons', array($this, 'register_button') );
				add_filter('mce_css', array($this, 'plugin_mce_css') );

                $suffix = AdSimpleVoteHelper::getResourceSuffix();

				wp_enqueue_script('asv-button-js', plugin_dir_url(__FILE__) . 'js/button' . $suffix . '.js', array('jquery'), self::VERSION, true);

				$adsimple_data = array(
					'rest_url' => get_rest_url()
				);
				wp_localize_script('asv-button-js', 'adsimple_data', $adsimple_data );
			}
		}

		/**
		 * Adds shortcode to the array of buttons.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function register_button($buttons) {
			/** Register button with their id. */
			array_push($buttons, "adsimplevote");

			return $buttons;
		}

		/**
		 * Register TinyMCE Plugin.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		function add_TinyMCE_plugin($plugin_array) {
			$suffix = AdSimpleVoteHelper::getResourceSuffix();
			$plugin_array['adsimplevote_plugin'] = plugin_dir_url(__FILE__) . 'js/adsimplevote' . $suffix . '.js';

			return $plugin_array;
		}

		/**
		 * Add stylesheet to the TinyMCE.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function plugin_mce_css( $mce_css ) {
            $suffix = AdSimpleVoteHelper::getResourceSuffix();
			wp_enqueue_style('adsimplevote-editor', plugin_dir_url(__FILE__) . 'css/editor' . $suffix . '.css');

			return $mce_css;
		}

		/**
		 * Core logic of [adsimplevote id="VOTE_ID"] shortcode
		 *
		 * @return string
		 * @since 1.0.0
		 * @access public
		 */
		public function adsimplevote_shortcode_func($atts, $content = null, $tag) {
			global $post;

			$atts = shortcode_atts( array('id' => NULL), $atts);

			$vote = AdSimpleVoteHelper::getVote($atts['id']);
			if (!$vote) {
				return '';
			}

			$left_value = get_post_meta($vote->ID, 'left_value', true);
			$right_value = get_post_meta($vote->ID, 'right_value', true);

            $votes_count = AdSimpleVoteHelper::countVotes($vote->ID);

			// Get plugin settings.
			$options = AdSimpleVoteHelper::getOptions();

            $before_vote_msg_header = $options['before_vote_msg_header'];
            $before_vote_msg_description = $options['before_vote_msg_description'];
            $after_vote_msg_header = $options['after_vote_msg_header'];
            $after_vote_msg_description = $options['after_vote_msg_description'];

			$share_url = add_query_arg('adv', $vote->ID, get_permalink($post->ID));

			ob_start();
			?>

			<div id="adsimplevote-<?php echo $vote->ID; ?>" class="adsimplevote-box">
				<div class="asv-container">

					<header>
						<div><h4><?php echo $vote->post_title; ?></h4></div>
					</header>

					<?php if (strlen(trim($vote->post_excerpt)) > 0): ?>
						<div class="asv-post-excerpt"><?php echo $vote->post_excerpt; ?></div>
					<?php endif; ?>

					<div class="asv-before-vote-msg">
                        <?php if ($before_vote_msg_header) { ?><div class="asv-header"><?php echo $before_vote_msg_header; ?></div><?php } ?>
                        <?php if ($before_vote_msg_description) { ?><div class="asv-description"><?php echo $before_vote_msg_description; ?></div><?php } ?>
					</div>

					<div class="asv-after-vote-msg" style="display: none;">
                        <?php if ($after_vote_msg_description) { ?><div class="asv-header"><?php echo $after_vote_msg_header; ?></div><?php } ?>
                        <?php if ($after_vote_msg_description) { ?><div class="asv-description"><?php echo $after_vote_msg_description; ?></div><?php } ?>
					</div>

					<div class="asv-slider">
                        <div class="adsimplevote-chart">
						    <div class="ct-chart ct-golden-section"></div>
                        </div>
						<script type='text/javascript'>
							<?php $this->adsimplevote_data_e($vote->ID); ?>
						</script>
						<div class="vote-slider">
							<input class="range" type="range" value="50" min="0" max="100">
							<span class="value">0</span>
						</div>
						<div class="left_value"><?php echo $left_value; ?></div>
						<div class="right_value"><?php echo $right_value; ?></div>
					</div>

					<div class="asv-footer">
						<div class="asv-social">
							<?php foreach ($options['social_share_buttons'] as $value) : ?>

								<?php if($value == "fb") : ?>
									<span class="facebook"><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($share_url); ?>" target="_blank">Facebook</a></span>
								<?php endif; ?>

								<?php if($value == "tw") : ?>
									<span class="twitter"><a href="https://twitter.com/intent/tweet?source=webclient&amp;original_referer=<?php echo urlencode($share_url); ?>&amp;text=<?php echo urlencode(get_the_title($vote)); ?>&amp;url=<?php echo urlencode(get_permalink($post)); ?>" target="_blank">Twitter</a></span>
								<?php endif; ?>

								<?php if($value == "gp") : ?>
									<span class="googleplus"><a href="https://plus.google.com/share?url=<?php echo urlencode($share_url); ?>" target="_blank">Google Plus</a></span>
								<?php endif; ?>

							<?php endforeach; ?>
						</div>
						<div class="asv-couter"><span><b><?php echo $votes_count; ?></b> <?php echo _n( 'Vote', 'Votes', $votes_count, 'adsimple-vote' ); ?></span></div>
					</div>

					

				</div>
			</div>

			<?php
			return ob_get_clean();
		}

        /**
         * Vote form template
         *
         * @return string
         * @since 1.0.0
         * @access public
         */
        public function adsimplevote_template() {
            // Get plugin settings.
            $options = AdSimpleVoteHelper::getOptions();

            $before_vote_msg_header = $options['before_vote_msg_header'];
            $before_vote_msg_description = $options['before_vote_msg_description'];
            ?>
            <script type="text/html" id="tmpl-adsimplevote">
                <div id="adsimplevote-sample" class="adsimplevote-box">
                    <div class="asv-container">

                        <header>
                            <div><h4>{{ data.title }}</h4></div>
                        </header>
                        <# if (data.description) { #>
                        <div class="asv-post-excerpt">{{ data.description }}</div>
                        <# } #>

                        <div class="asv-before-vote-msg">
                            <?php if ($before_vote_msg_header) { ?><div class="asv-header"><?php echo $before_vote_msg_header; ?></div><?php } ?>
                            <?php if ($before_vote_msg_description) { ?><div class="asv-description"><?php echo $before_vote_msg_description; ?></div><?php } ?>
                        </div>

                        <div class="asv-slider">
                            <div class="adsimplevote-chart">
                                <div class="ct-chart ct-golden-section"></div>
                            </div>
                            <div class="vote-slider">
                                <input class="range" type="range" value="50" min="0" max="100">
                                <span class="value">0</span>
                            </div>
                            <div class="left_value">{{ data.left_value }}</div>
                            <div class="right_value">{{ data.right_value }}</div>
                        </div>

                        <div class="asv-footer">
                            <div class="asv-social">
                                <?php foreach ($options['social_share_buttons'] as $value) { ?>
                                    <?php if($value === 'fb') { ?>
                                        <span class="facebook"><a href="#">Facebook</a></span>
                                    <?php } ?>
                                    <?php if($value === 'tw') { ?>
                                        <span class="twitter"><a href="#">Twitter</a></span>
                                    <?php } ?>
                                    <?php if($value === 'gp') { ?>
                                        <span class="googleplus"><a href="#">Google Plus</a></span>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                            <div class="asv-couter"><span><b>{{ data.votes_count }}</b>
                            <# if (data.votes_count === 1) { #>
                                <?php echo __('Vote', 'adsimple-vote'); ?>
                            <# } else { #>
                                <?php echo __('Votes', 'adsimple-vote'); ?>
                            <# } #>
                            </span></div>
                        </div>
                        <div class="asv-copyrights"><a href="https://www.adsimple.at" target="_blank"><?php _e('Powered by AdSimple', 'adsimple-vote'); ?></a></div>
                    </div>
                </div>
            </script>
            <?php
        }

		/**
		 * Return chart data set for javascript.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		private function adsimplevote_data_e($vote_id) {
			$arr = AdSimpleVoteHelper::getVotesData($vote_id);
			$result = array('series' => array($arr));
			echo 'var adsimplevote_data_' . $vote_id . ' = ' . json_encode($result) . ';';
		}

		/**
		 * Add plugin css and js if post has shortcode
		 *
		 * @param array $posts
		 * @return array
		 * @since 1.0.0
		 * @access public
		 */
		public function enqueue_styles($posts) {
			if (empty($posts)) {
                return $posts;
            }

			/** False because we have to search through the posts first. */
			$found = false;

			/** Search through each post. */
			foreach ($posts as $post) {
				/** Check the post content for the short code. */
				if (has_shortcode($post->post_content, 'adsimplevote')) {
					$found = true; // We have found a post with the short code.
					break; // Stop the search.
				}
			}

			// TODO: Move charts script to footer
			if ($found) {
                $this->enqueue_common();

                $suffix = AdSimpleVoteHelper::getResourceSuffix();

				// JS
				wp_enqueue_script('adsimplevote-script-js', plugin_dir_url(__FILE__) . 'js/script' . $suffix . '.js', array('adsimplevote-common-js'), '', true);

				// in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
				wp_localize_script('adsimplevote-script-js', 'adsimplevote_ajax', array('url' => admin_url('admin-ajax.php')));

                $this->enqueue_chart();
			}

			return $posts;
		}

		/**
		 * Register JS & CSS for admin area.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function admin_enqueue_scripts() {
			/* Add admin styles only on plugin pages */
			$screen = get_current_screen();
			if ($screen->post_type != ASV_POST_TYPE) {
                return;
            }

            add_thickbox();

            $this->enqueue_common();

            $suffix = AdSimpleVoteHelper::getResourceSuffix();

			wp_enqueue_style('adsimplevote-admin', plugin_dir_url(__FILE__) . 'css/admin' . $suffix . '.css', array(), self::VERSION);
            wp_enqueue_script('adsimplevote-admin-js', plugin_dir_url(__FILE__) . 'js/admin' . $suffix . '.js', array('jquery'), self::VERSION);

            wp_localize_script('adsimplevote-admin-js', 'adsimplevote_dict', array(
                'preview' => __('Preview', 'adsimple-vote')
            ));

            $this->enqueue_chart();
        }

        protected function enqueue_common()
        {
            $suffix = AdSimpleVoteHelper::getResourceSuffix();

            wp_enqueue_style('adsimplevote-common', plugin_dir_url(__FILE__) . 'css/common' . $suffix . '.css', array(), self::VERSION);
            wp_enqueue_script('adsimplevote-common-js', plugin_dir_url(__FILE__) . 'js/common' . $suffix . '.js', array('jquery'), self::VERSION);
        }

        protected function enqueue_chart()
        {
            $suffix = AdSimpleVoteHelper::getResourceSuffix();

            wp_enqueue_style('adsimplevote-chartist', plugin_dir_url(__FILE__) . 'css/chartist' . $suffix . '.css');

            /** Add inline CSS */
            $options = AdSimpleVoteHelper::getOptions(); // Get plugin options
            $css = ".adsimplevote-box .asv-footer .asv-couter span {color:" . $options['key_color'] . ";}";
            $css .= ".adsimplevote-chart .ct-series-a .ct-line {stroke:" . $options['key_color'] . ";}";
            $css .= ".adsimplevote-chart .ct-series-a .ct-area {fill:" . $options['key_color'] . ";}";
            $css .= ".adsimplevote-box .range::-webkit-slider-thumb:hover {background: " . $options['key_color'] . ";}";
            $css .= ".adsimplevote-box .range:active::-webkit-slider-thumb {background: " . $options['key_color'] . ";}";
            $css .= ".adsimplevote-box .range::-moz-range-thumb:hover {background: " . $options['key_color'] . ";}";
            $css .= ".adsimplevote-box .range:active::-moz-range-thumb {background: " . $options['key_color'] . ";}";

            wp_add_inline_style('adsimplevote-chartist', $css);

            // JS
            wp_enqueue_script('adsimplevote-chartist-js', plugin_dir_url(__FILE__) . 'js/chartist' . $suffix . '.js', array(), '', true);
        }

		/**
		 * Add admin menu for plugin settings.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function add_admin_menu() {
			add_submenu_page(
				'edit.php?post_type=' . ASV_POST_TYPE,
				__('AdSimple-Vote — Let the users to vote with just one simple click.', 'adsimple-vote'),
				__('Settings', 'adsimple-vote'),
				'manage_options',
				'adsimplevote_settings',
				array($this, 'options_page')
			);
		}

		/**
		 * Generate Settings Page
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function settings_init() {

			register_setting('AdSimpleVoteOptionsGroup', 'adsimplevote_settings');
			add_settings_section('adsimplevote_pluginPage_section', __('Plugin settings', 'adsimple-vote'), array($this, 'settings_section_callback'), 'AdSimpleVoteOptionsGroup');

			/** KeyColor */
			add_settings_field('key_color', __('Color', 'adsimple-vote'), array($this, 'key_color_render'), 'AdSimpleVoteOptionsGroup', 'adsimplevote_pluginPage_section');

			/** Social Buttons */
			add_settings_field('social_share_buttons', esc_html__('Social Share Buttons', 'adsimple-vote'), array($this, 'social_share_buttons_render'), 'AdSimpleVoteOptionsGroup', 'adsimplevote_pluginPage_section');

			/** Before Vote Message */
			add_settings_field('before_vote_msg', esc_html__('Before Vote Message', 'adsimple-vote'), array($this, 'before_vote_msg_render'), 'AdSimpleVoteOptionsGroup', 'adsimplevote_pluginPage_section');

			/** After Vote Message */
			add_settings_field('after_vote_msg', esc_html__('After Vote Message', 'adsimple-vote'), array($this, 'after_vote_msg_render'), 'AdSimpleVoteOptionsGroup', 'adsimplevote_pluginPage_section');

		}

		/**
		 * Render After Vote Message field.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function after_vote_msg_render() {
			$options = AdSimpleVoteHelper::getOptions();
			?>
            <p><input type="text" class="regular-text" name="adsimplevote_settings[after_vote_msg_header]" placeholder="<?php _e('Message header', 'adsimple-vote'); ?>" value="<?php esc_attr_e($options['after_vote_msg_header']); ?>" /></p>
            <p><input type="text" class="regular-text" name="adsimplevote_settings[after_vote_msg_description]" placeholder="<?php _e('Message description', 'adsimple-vote'); ?>" value="<?php esc_attr_e($options['after_vote_msg_description']); ?>" /></p>
            <p class="description"><?php esc_html_e('You can add your custom "After Vote Message".', 'adsimple-vote'); ?></p>
			<?php
		}

		/**
		 * Render Before Vote Message field.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function before_vote_msg_render() {
			$options = AdSimpleVoteHelper::getOptions();
			?>
            <p><input type="text" class="regular-text" name="adsimplevote_settings[before_vote_msg_header]" placeholder="<?php _e('Message header', 'adsimple-vote'); ?>" value="<?php esc_attr_e($options['before_vote_msg_header']); ?>" /></p>
            <p><input type="text" class="regular-text" name="adsimplevote_settings[before_vote_msg_description]" placeholder="<?php _e('Message description', 'adsimple-vote'); ?>" value="<?php esc_attr_e($options['before_vote_msg_description']); ?>" /></p>
			<p class="description"><?php esc_html_e('You can add your custom "Before Vote Message".', 'adsimple-vote'); ?></p>
			<?php
		}

		/**
		 * Render Social Share Buttons field.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function social_share_buttons_render() {

			$options = AdSimpleVoteHelper::getOptions();

			?>
			<p class="description"><?php echo __('Choose social networks to use.', 'adsimple-vote'); ?></p>
			<label>
				<input type='checkbox' name='adsimplevote_settings[social_share_buttons][]' value="fb" <?php echo in_array('fb', $options['social_share_buttons']) ? 'checked' : ''; ?>>
				<span><?php esc_html_e('Facebook', 'adsimple-vote'); ?></span>
			</label>
			<br />
			<label>
				<input type='checkbox' name='adsimplevote_settings[social_share_buttons][]' value="tw" <?php echo in_array('tw', $options['social_share_buttons']) ? 'checked' : ''; ?>>
				<span><?php esc_html_e('Twitter', 'adsimple-vote'); ?></span>
			</label>
			<br />
			<label>
				<input type='checkbox' name='adsimplevote_settings[social_share_buttons][]' value="gp" <?php echo in_array('gp', $options['social_share_buttons']) ? 'checked' : ''; ?>>
				<span><?php esc_html_e('Google Plus', 'adsimple-vote'); ?></span>
			</label>
			<?php
		}

		/**
		 * Render Key Color field.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function key_color_render() {

			$options = AdSimpleVoteHelper::getOptions();

			?>
			<input id="t42-rpb-color" type='color' name='adsimplevote_settings[key_color]' value='<?php echo $options['key_color']; ?>'>
			<p class="description"><?php _e('Select key color to modyfy design.', 'adsimple-vote'); ?></p>
			<?php

		}

		/**
		 * Render setting section.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function settings_section_callback() {

			echo __('Let the users to vote with just one simple click. Create a question and get deep insights in the process. Listen your audience.', 'adsimple-vote');

		}

		/**
		 * Plugin Settings Page
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function options_page() {

			if ( ! current_user_can('manage_options')) { return; }

			?>
			<h1><?php _e('AdSimple Vote Settings', 'adsimple-vote'); ?></h1>
			<form action='options.php' method='post'>
				<?php settings_fields('AdSimpleVoteOptionsGroup'); ?>
				<?php do_settings_sections('AdSimpleVoteOptionsGroup'); ?>
				<?php submit_button(); ?>
			</form>
			<?php
		}

		/**
		 * Registers AdSimpleVote post type.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function register_adsimplevote_post_type() {
			register_post_type(ASV_POST_TYPE,
				array (
					'public' => true,
					'labels' => array(
						'name'					=> __('Votes', 'adsimple-vote'),
						'singular_name'			=> __('Vote', 'adsimple-vote'),
						'add_new'				=> __('Add New Vote', 'adsimple-vote'),
						'add_new_item'			=> __('Add New Vote', 'adsimple-vote'),
						'edit_item'				=> __('Edit Vote', 'adsimple-vote'),
						'new_item'				=> __('New Vote', 'adsimple-vote'),
						'view_item'				=> __('View Vote', 'adsimple-vote'),
						'view_items'			=> __('View Votes', 'adsimple-vote'),
						'search_items'			=> __('Search Votes', 'adsimple-vote'),
						'not_found'				=> __('No votes found', 'adsimple-vote'),
						'not_found_in_trash'	=> __('No posts found in Trash', 'adsimple-vote'),
						'all_items'				=> __('All votes', 'adsimple-vote'),
						'archives'				=> __('Vote Archives', 'adsimple-vote'),
						'attributes'			=> __('Vote Attributes', 'adsimple-vote'),
						'insert_into_item'		=> __('Insert into vote', 'adsimple-vote'),
						'uploaded_to_this_item'	=> __('Uploaded to this vote', 'adsimple-vote'),
						'featured_image'		=> __('Vote Image', 'adsimple-vote'),
						'set_featured_image'	=> __('Set vote image', 'adsimple-vote'),
						'remove_featured_image'	=> __('Remove vote image', 'adsimple-vote'),
						'use_featured_image'	=> __('Use as vote image', 'adsimple-vote'),
						'menu_name'				=> __('AdSimple Vote', 'adsimple-vote')
					),
					'menu_icon'				=> $this->get_svg_icon(),
					'exclude_from_search'	=> TRUE,
					'publicly_queryable'	=> FALSE,
					'menu_position'			=> FALSE,
					'show_in_rest'			=> TRUE,
					'rest_base'				=> 'adsimplevote',
					'supports' => array( 'title', 'excerpt', 'thumbnail' )
				)
			);
		}

		/**
		 * Meta box setup function.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function meta_boxes_setup( ) {
			/** Add meta boxes on the 'add_meta_boxes' hook. */
			add_action( 'add_meta_boxes', array($this, 'asv_add_meta_boxes') );

			/** Save Left and Right values on the 'save_post' hook. */
			add_action( 'save_post', array($this, 'save_vote_meta'), 1, 2 );
		}

		/**
		 * Create Left and Right values meta boxes to be displayed on vote editor screen.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function asv_add_meta_boxes() {
            $screen = get_current_screen();

			/** Left and Right values metabox */
			add_meta_box(
				'asv-values-meta-box',
				__('Values', 'adsimple-vote'),
				array($this, 'values_meta_box'),
				'adsimplevote',
				'normal',
				'default'
			);

            if ('add' != $screen->action) {
                add_meta_box(
                    'asv-chart-meta-box',
                    __('Chart', 'adsimple-vote'),
                    array($this, 'chart_meta_box'),
                    'adsimplevote',
                    'normal',
                    'default'
                );
            }

            add_meta_box(
                'asv-actions-meta-box',
                __('Actions', 'adsimple-vote'),
                array($this, 'actions_meta_box'),
                'adsimplevote',
                'side',
                'default'
            );
		}

		/**
		 * Save Left and Right values.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function save_vote_meta($post_id, $post) {

			/** Verify the nonce before proceeding. */
			if ( ! isset($_POST['metabox_fields_nonce']) || ! wp_verify_nonce($_POST['metabox_fields_nonce'], basename(__FILE__))) {
				return $post_id;
			}

			/** Get the post type object. */
			$post_type = get_post_type_object($post->post_type);

			/** Check if the current user has permission to edit the post. */
			if (!current_user_can($post_type->cap->edit_post, $post_id)) {
				return $post_id;
			}

			/** Get Left value and sanitize it for use. */
			$left_value = ( isset($_POST['left_value']) ? sanitize_text_field($_POST['left_value']) : '' );

			/** Update meta value. */
			$this->update_meta_val($left_value, 'left_value', $post_id);

			/** Get Right value and sanitize it for use. */
			$right_value = ( isset($_POST['right_value']) ? sanitize_text_field($_POST['right_value']) : '' );

			/** Update meta value. */
			$this->update_meta_val($right_value, 'right_value', $post_id);

		}

		/**
		 * Add, update or remove meta value.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function update_meta_val($new_value, $meta_key, $post_id){
			/** Get the meta value of the custom field key. */
			$meta_value = get_post_meta($post_id, $meta_key, true);

			/* If a new meta value was added and there was no previous value, add it. */
			if ($new_value && '' == $meta_value) {
				add_post_meta($post_id, $meta_key, $new_value, true);
			}
			/* If the new meta value does not match the old value, update it. */
			elseif ($new_value && $new_value != $meta_value) {
				update_post_meta($post_id, $meta_key, $new_value);
			}
			/* If there is no new meta value but an old value exists, delete it. */
			elseif ('' == $new_value && $meta_value) {
				delete_post_meta($post_id, $meta_key, $meta_value);
			}
		}

		/**
		 * Display Left and Right values meta box.
		 *
		 * @since 1.0.0
		 * @access public
         * @param WP_Post $vote
		 */
		public function values_meta_box($vote) {
			/** Nonce field to validate form request came from current site. */
			wp_nonce_field(basename(__FILE__), 'metabox_fields_nonce');

			/** Get the left and right values if it's already been entered. */
			$left_value = get_post_meta($vote->ID, 'left_value', true);
			$right_value = get_post_meta($vote->ID, 'right_value', true);

			?>
			<div class="asv-left-right-box">
				<p class="asv-left-value-fld">
					<label for="left-value-field"><?php _e("Left value:", 'adsimple-vote'); ?></label>
					<input type="text" id="left-value-field" name="left_value" value="<?php echo esc_textarea( $left_value ); ?>" class="widefat">
				</p>

				<p class="asv-right-value-fld">
					<label for="right-value-field"><?php _e("Right value:", 'adsimple-vote'); ?></label>
					<input type="text" id="right-value-field" name="right_value" value="<?php echo esc_textarea( $right_value ); ?>" class="widefat">
				</p>
			</div>
			<?php
		}

		/**
		 * Display chart meta box.
		 *
		 * @since 1.0.0
		 * @access public
         * @param WP_Post $vote
		 */
		public function chart_meta_box($vote) {
			?>
			<div class="adsimplevote-chart" data-id="<?php echo $vote->ID; ?>" data-debug="true" id="adsimplevote-<?php echo $vote->ID; ?>">
				<div class="ct-chart ct-golden-section"></div>
			</div>
			<div id="adsimplevote-chart-values" class="asv-chart-box-values"></div>
			<script type="text/html" id="tmpl-adsimplevote-chart-values">
				<p class="asv-left-value-fld">{{ data.left_value }}</p>
				<p class="asv-right-value-fld">{{ data.right_value }}</p>
			</script>
            <script type='text/javascript'>
                <?php $this->adsimplevote_data_e($vote->ID); ?>
            </script>
			<?php
		}

        /**
         * Display actions meta box.
         *
         * @param WP_Post $vote
         */
        public function actions_meta_box($vote) {
            ?>
            <?php $this->adsimplevote_template(); ?>
            <div id="adsimplevote-preview" style="display: none;"></div>
            <input type="button" id="adsimplevote-preview-button" value="<?php _e('Preview', 'adsimple-vote'); ?>" class="button" />
            <?php
        }

		/**
		 * Add HEAD for custom column with vote shorcode.
		 *
		 * @param array $columns
		 * @return array
		 * @since 1.0.0
		 * @access public
		 */
		public function add_head_shortcode_column ( $columns ) {
			// Add new column key to the existing columns.
			$columns['vote_shorcode'] = __('Shorcode', 'adsimple-vote');

			// Define a new order.
			$newOrder = array('cb', 'title', 'vote_shorcode', 'date');

			// Order columns like set in $newOrder.
            $new = array();
			foreach ($newOrder as $colname) {
				$new[$colname] = $columns[$colname];
			}

			// Return a new column array to WordPress.
			return $new;
		}

		/**
		 * Add CONTENT for custom column with vote shorcode.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function add_content_shortcode_column($column_name, $post_ID) {
			if ($column_name == 'vote_shorcode') {
				echo "<code>[adsimplevote id=\"{$post_ID}\"]";
			}
		}

		/**
		 * Return base64 encoded SVG icon.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function get_svg_icon() {
			$svg = <<<CONTENT
				<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="adsimple-vote-svg-ico" x="0px" y="0px" viewBox="0 0 511.999 511.999" xml:space="preserve">
					<path fill="black" d="M74.114,390.943c-1.859-1.86-4.439-2.93-7.069-2.93c-2.641,0-5.211,1.069-7.07,2.93c-1.86,1.859-2.93,4.439-2.93,7.07    c0,2.63,1.069,5.21,2.93,7.069c1.859,1.861,4.43,2.931,7.07,2.931c2.63,0,5.21-1.07,7.069-2.931    c1.86-1.859,2.931-4.439,2.931-7.069C77.045,395.382,75.975,392.802,74.114,390.943z"></path>
					<path fill="black" d="M386.938,216.842c-1.859-1.86-4.439-2.92-7.069-2.92c-2.63,0-5.2,1.061-7.07,2.92c-1.86,1.87-2.93,4.44-2.93,7.08    c0,2.63,1.069,5.21,2.93,7.07c1.86,1.859,4.44,2.92,7.07,2.92s5.21-1.061,7.069-2.92c1.87-1.87,2.931-4.44,2.931-7.07    C389.869,221.292,388.808,218.712,386.938,216.842z"></path>
					<path fill="black" d="M289.004,53.001c-93.733,0-169.991,76.258-169.991,169.991s76.258,169.991,169.991,169.991    s169.991-76.258,169.991-169.991S382.737,53.001,289.004,53.001z M289.004,73c46.159,0,87.508,20.965,115.045,53.865    l-10.997,10.997c-3.917-1.878-8.3-2.932-12.925-2.932c-16.541,0-29.998,13.457-29.998,29.998c0,3.873,0.746,7.572,2.088,10.973    l-58.051,52.033c-3.21-1.176-6.675-1.818-10.286-1.818c-7.099,0-13.625,2.484-18.769,6.622l-47.452-31.207    c0.142-1.183,0.224-2.383,0.224-3.604c0-16.541-13.457-29.998-29.998-29.998s-29.998,13.457-29.998,29.998    c0,2.722,0.371,5.357,1.053,7.865l-19.882,13.754C140.893,138.426,207.449,73,289.004,73z M390.126,164.929    c0,5.514-4.486,9.999-10,9.999c-5.514,0-9.999-4.486-9.999-9.999c0-5.514,4.486-10,9.999-10    C385.64,154.929,390.126,159.415,390.126,164.929z M177.883,323.631c-19.916-21.969-33.417-49.849-37.537-80.659l29.981-20.741    c2.297,1.664,4.836,3.011,7.556,3.975V323.631z M187.882,207.927c-5.514,0-10-4.486-10-9.999c0-5.514,4.486-10,10-10    s9.999,4.486,9.999,10C197.882,203.442,193.396,207.927,187.882,207.927z M273.878,372.224    c-28.403-2.857-54.487-13.677-75.996-30.179V226.207c4.062-1.44,7.721-3.733,10.767-6.662l45.918,30.198    c-0.447,2.055-0.688,4.186-0.688,6.373c0,13.035,8.361,24.151,19.999,28.279V372.224z M283.877,266.116    c-5.514,0-9.999-4.486-9.999-10c0-5.514,4.486-9.999,9.999-9.999s9.999,4.486,9.999,9.999    C293.877,261.631,289.391,266.116,283.877,266.116z M389.872,333.908V264.63c0-5.522-4.478-9.999-9.999-9.999    s-9.999,4.478-9.999,9.999v84.635c-22.07,14.183-48.082,22.735-75.996,23.631v-88.501c11.638-4.128,19.999-15.242,19.999-28.279    c0-5.564-1.528-10.775-4.179-15.247l55.63-49.862c4.371,2.49,9.419,3.921,14.799,3.921c16.541,0,29.998-13.457,29.998-29.998    c0-4.625-1.054-9.008-2.932-12.925l8.778-8.778c14.577,23.12,23.026,50.472,23.026,79.765    C438.996,266.895,420.035,306.452,389.872,333.908z"></path>
					<path fill="black" d="M509.07,50.127c3.905-3.905,3.905-10.237,0-14.142c-3.906-3.904-10.235-3.904-14.142,0l-39.077,39.077    c-2.953-3.322-6.001-6.579-9.169-9.747C404.566,23.197,348.568,0.003,289.004,0.003S173.443,23.197,131.327,65.314    c-42.118,42.116-65.312,98.113-65.312,157.678c0,14.976,1.469,29.726,4.334,44.088L4.33,312.751    c-4.542,3.142-5.677,9.37-2.534,13.912c1.941,2.806,5.061,4.312,8.232,4.312c1.962,0,3.944-0.576,5.681-1.777l59.827-41.388    c6.362,21.051,15.841,41.029,28.195,59.376l-16.936,16.936c-3.905,3.905-3.905,10.236,0,14.142    c1.953,1.952,4.512,2.929,7.071,2.929s5.118-0.977,7.071-2.929l14.838-14.838c4.857,5.969,10.039,11.727,15.555,17.243    c5.516,5.516,11.274,10.697,17.243,15.555l-89.16,89.159c-8.918,8.919-23.529,8.819-32.572-0.225    c-9.043-9.043-9.145-23.655-0.226-32.573l19.005-19.004c3.905-3.905,3.905-10.236,0-14.142c-3.905-3.903-10.234-3.904-14.142,0    l-19.005,19.005C-4.245,455.16-4.144,482.461,12.696,499.3c8.462,8.461,19.562,12.696,30.635,12.695    c10.965,0,21.903-4.154,30.221-12.471l91.258-91.257c36.427,24.528,79.296,37.713,124.193,37.713    c59.564,0,115.562-23.194,157.679-65.311c42.116-42.116,65.31-98.113,65.31-157.678c0-48.287-15.246-94.228-43.456-132.331    L509.07,50.127z M491.993,222.992c0,111.929-91.06,202.989-202.989,202.989S86.015,334.921,86.015,222.992    s91.06-202.989,202.989-202.989S491.993,111.063,491.993,222.992z"></path>
				</svg>
CONTENT;

			return 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}

		/**
		 * Loads the AdSimple-Vote translated strings.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function load_textdomain() {
			load_plugin_textdomain('adsimple-vote', false, dirname(plugin_basename(__FILE__)) . '/languages');
		}

		/**
		 * Add "AdSimple" and "Settings" links to plugin page.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param array $links Current links: Deactivate | Edit
		 */
		public function add_links($links) {
			array_unshift($links, '<a title="Settings" href="'. admin_url( 'edit.php?post_type=' . ASV_POST_TYPE . '&page=adsimplevote_settings' ) .'">Settings</a>');
			array_unshift($links, '<a title="' . __('Content Marketing Agentur AdSimple® aus Österreich', 'adsimple-vote') . '" href="https://www.adsimple.at/" target="_blank"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA3FpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMTQyIDc5LjE2MDkyNCwgMjAxNy8wNy8xMy0wMTowNjozOSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDoxY2E1ODI4Mi0xMDU0LTFmNGItYmUxZC0wMTNmOGE1MzZiN2IiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6QjQyRTlEQ0EyMDZFMTFFOEE2MEFERUFGRUU1NkZGNDEiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6QjQyRTlEQzkyMDZFMTFFOEE2MEFERUFGRUU1NkZGNDEiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIChXaW5kb3dzKSI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjFjYTU4MjgyLTEwNTQtMWY0Yi1iZTFkLTAxM2Y4YTUzNmI3YiIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDoxY2E1ODI4Mi0xMDU0LTFmNGItYmUxZC0wMTNmOGE1MzZiN2IiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz7UkGJKAAABIElEQVR42mIMP9X6lIGBgZeBPPCZBUhIMZAPeJlAplBgwGcmBgoBxQawEFKgwy3L8Onvd4ZPf74xfABikgyQ4xBhqNGKBbMvvb/D0HZnFWleCJK0hrP1BFUYBFi4SDNAH6gJBL7//QmmvcVMiTfARViPgZOZHax5z4szYDFrEV3iDXAQNQDTx19fYdj66jSYLcTOx2DBr0bYAFDgqfDKgNlf//5gMOFXYXj67TWY7wQ1GG8seIgaw9m+0tYocqp8suDAhEUpVgMsRXXANMjWtz8/omgGhQsoMJc+Owg3gBdb4IFA882lKIknTdadwUnCmMFIUA1mADgzPYNmKDB2FDX4C5K58uHuH6Dmz8hyx99fB5smzSXKoMsjB2I/AwgwAG3oWlSMwXMxAAAAAElFTkSuQmCC" alt="" style="width: 16px; height: 16px; vertical-align: middle; position: relative; top: -2px; float: none; margin-right: 0; padding-right: 2px;"> www.adsimple.at</a>');

			return $links;
		}

		/**
		 * Since WP 4.7, filter has been removed from WP-API. I have no idea why.
		 * Add the necessary filter to each post type.
		 *
		 * @see https://github.com/WP-API/rest-filter
		 * @since 1.0.0
		 * @access public
		 */
		public function rest_api_filter_add_filters() {
			foreach (get_post_types(array('show_in_rest' => true), 'objects') as $post_type) {
				add_filter('rest_' . $post_type->name . '_query', array($this, 'rest_api_filter_add_filter_param'), 10, 2);
			}
		}

		/**
		 * Add the filter parameter
		 *
		 * @param  array           $args    The query arguments.
		 * @param  WP_REST_Request $request Full details about the request.
		 * @return array $args.
		 *
		 * @since 1.0.0
		 * @access public
		 * */
		public function rest_api_filter_add_filter_param($args, $request) {
			// Bail out if no filter parameter is set.
			if (empty($request['filter']) || !is_array($request['filter'])) {
				return $args;
			}

			$filter = $request['filter'];

			if (isset($filter['posts_per_page']) && ( (int) $filter['posts_per_page'] >= 1 && (int) $filter['posts_per_page'] <= 100 )) {
				$args['posts_per_page'] = $filter['posts_per_page'];
			}

			global $wp;
			$vars = apply_filters('query_vars', $wp->public_query_vars);

			foreach ($vars as $var) {
				if (isset($filter[$var])) {
					$args[$var] = $filter[$var];
				}
			}
			return $args;
		}

		public function open_graph_print() {
			$vote = AdSimpleVoteHelper::getVote(isset($_GET['adv']) ? (int)$_GET['adv'] : 0);
			if (!$vote) {
				return;
			}

			$thumbnail_url = get_the_post_thumbnail_url($vote);
			$title = trim($vote->post_title);
			$description = trim($vote->post_excerpt);
			?>
			<?php if ($title) { ?><meta property="og:title" content="<?php esc_attr_e($title); ?>" /><?php } ?>
			<?php if ($description) { ?><meta property="og:description" content="<?php esc_attr_e($description); ?>" /><?php } ?>
			<?php if ($thumbnail_url) { ?><meta property="og:image" content="<?php esc_attr_e($thumbnail_url); ?>" /><?php } ?>
			<?php
		}

		public function open_graph_canonical_url($canonical_url) {
			$vote = AdSimpleVoteHelper::getVote(isset($_GET['adv']) ? (int)$_GET['adv'] : 0);
			if (!$vote) {
				return $canonical_url;
			}
			return add_query_arg('adv', $vote->ID, $canonical_url);
		}

	} // End of class AdSimple-Vote.

}

/** Execution of the plugin. */
new AdSimpleVote();
