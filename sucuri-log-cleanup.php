<?php
/*
Plugin Name: Sucuri log file cleanup
Description: Periodically cleanup Sucuri log files to avoid "Allowed memory size exhausted" error with big log files.
Version:     1.0.9
Author:      megamurmulis
Author URI:  https://codelab.tk/
Text Domain: SucuriLogCleanup
License:     GPL v3
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


if ( ! defined('SUCURI_LOG_CLEANUP_DOMAIN') ){
	define( 'SUCURI_LOG_CLEANUP_DOMAIN', 'SucuriLogCleanup' );
}


if ( ! class_exists( 'SucuriLogCleanup' ) ) :
	class SucuriLogCleanup {
		protected static $instance = null;
		const SCHEMA = 3; // Increment to force cleanup on update
		
		private $max_age;
		private $sucuri_log_path;
		private $log_files = array(
			'sucuri-auditqueue.php',
			'sucuri-oldfailedlogins.php',
			'sucuri-failedlogins.php',
		);
		
		const MAX_DAYS = 7;
		
		private function __construct(){
			add_action( 'init',       array( $this, 'load_plugin_textdomain' ) );
			add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings'  ) );
			
			$this->max_age         = $this::MAX_DAYS * 24;
			$this->sucuri_log_path = WP_CONTENT_DIR . '/uploads/sucuri/';
			
			if ( ! defined( 'SUCURISCAN' ) ){
				add_action( 'admin_notices', array( $this, 'sucuri_missing_notice' ) );
			}
			add_action( 'admin_init', array( $this, 'maybe_delete_old_log_files' ) );
			add_action( 'admin_init', array( $this, 'force_clean_on_update' ) );
		}
		
		
		public static function get_instance(){
			if ( null == self::$instance ){
				self::$instance = new self;
			}
			return self::$instance;
		}
		
		public function load_plugin_textdomain(){
			load_plugin_textdomain( SUCURI_LOG_CLEANUP_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
		
		public function sucuri_missing_notice(){
			echo '<div class="error"><p>' . __( 'SucuriLogCleanup: Sucuri plugin is inactive or not installed!<br>
				Log cleanup function is only necessary when Sucuri plugin is used..', SUCURI_LOG_CLEANUP_DOMAIN ) . '</p></div>';
		}
		
		
		public function register_menu_page(){
			add_options_page(
				__('Sucuri Log Cleanup', SUCURI_LOG_CLEANUP_DOMAIN),
				__('Sucuri Log Cleanup', SUCURI_LOG_CLEANUP_DOMAIN),
				'manage_options',
				SUCURI_LOG_CLEANUP_DOMAIN,
				array( $this, 'create_admin_page' )
			);
		}
		
		public static function register_settings(){
			//add_option( 'SucuriLogCleanup_last', time() );
			add_option( 'SucuriLogCleanup_last', 0 );
		}
		
		
		function create_admin_page(){
			if ( !is_admin() ) return;
			?>
			<style>
			.SucuriLogCleanup p.margin0{
				margin:0;
			}
			</style>
			<div class="wrap SucuriLogCleanup">
				<h2><?php _e('Sucuri - log file cleanup ', SUCURI_LOG_CLEANUP_DOMAIN); ?></h2>
				
				<?php
				if ( isset($_POST['clear_now']) ){
					echo '<p>Clearing Log files..</p>';
					$this->force_clean_logs(true);
				}
				?>
				
				<form method="post" action="options-general.php?page=<?php echo SUCURI_LOG_CLEANUP_DOMAIN; ?>">
					<?php echo settings_fields( 'SucuriLogCleanup' ); ?>
					<table class="form-table top-small">
						<tr valign="top">
							<th scope="row">
								<label for="">
									<?php _e('Hook:', SUCURI_LOG_CLEANUP_DOMAIN); ?>
								</label>
							</th>
							<td>
								<span><?php _e('admin_init', SUCURI_LOG_CLEANUP_DOMAIN); ?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="">
									<?php _e('Max age:', SUCURI_LOG_CLEANUP_DOMAIN); ?>
								</label>
							</th>
							<td>
								<span><?php
								printf( '%s', $this->seconds_to_human_time($this->max_age * 3600) );
								?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="">
									<?php _e('Last cron time:', SUCURI_LOG_CLEANUP_DOMAIN); ?>
								</label>
							</th>
							<td>
								<span><?php
								$last = (int)get_option('SucuriLogCleanup_last');
								echo $last;
								if ( $last ){
									printf( ' (%s ago)', $this->seconds_to_human_time(time() - $last) );
								}
								?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="">
									<?php _e('Log Files:', SUCURI_LOG_CLEANUP_DOMAIN); ?>
								</label>
							</th>
							<td>
								<?php
								clearstatcache();
								if ( file_exists($this->sucuri_log_path) && is_dir($this->sucuri_log_path) ){
									foreach($this->log_files as $filename){
										if ( file_exists($this->sucuri_log_path . $filename) ){
											printf('<p>%s (%d B)</p>', $filename, filesize($this->sucuri_log_path . $filename));
										}
										else{
											printf('<p>%s (deleted)</p>', $filename);
										}
									}
								}
								?>
							</td>
						</tr>
					</table>
					<p>
						<input type="submit" name="clear_now" class="button button-primary" value="<?php _e('Manually clear now..', SUCURI_LOG_CLEANUP_DOMAIN); ?>" />
					</p>
				</form>
			</div>
			<?php
		}
		
		function seconds_to_human_time($seconds)
		{
			$dt      = new DateTime('@'. floor($seconds), new DateTimeZone('UTC'));
			$days    = (int)$dt->format('z');
			$hours   = (int)$dt->format('G');
			$minutes = (int)$dt->format('i');
			$seconds = (int)$dt->format('s');
			
			$out = '';
			$ts  = false;
			if ($days){
				$ts   = true;
				$out .= $days .'d ';
			}
			if ($hours || $ts){
				$ts   = true;
				$out .= $hours .'h ';
			}
			if ($minutes || $ts){
				$ts   = true;
				$out .= $minutes .'m ';
			}
			$out .= $seconds .'s';
			return $out;
		}
		
		
		function maybe_delete_old_log_files(){
			$last = (int)get_option('SucuriLogCleanup_last');
			
			if ( !$last || ((time() - $last) >= (3600 * $this->max_age)) ){
				update_option('SucuriLogCleanup_last', time());
				$this->force_clean_logs();
			}
		}
		
		function force_clean_logs($verbose=false){
			update_option('SucuriLogCleanup_last', time());
			clearstatcache();
			if ( !file_exists($this->sucuri_log_path) || !is_dir($this->sucuri_log_path) ){
				return false;
			}
			foreach($this->log_files as $filename){
				@unlink( $this->sucuri_log_path . $filename);
				if ($verbose){
					printf('<p class="margin0">DEL: %s</p>', $this->sucuri_log_path . $filename);
				}
			}
		}
		
		function force_clean_on_update(){
			$old_schema = get_option('SucuriLogCleanup_schema');
			
			if ( !$old_schema || (version_compare($old_schema, $this::SCHEMA) < 0) ){
				update_option('SucuriLogCleanup_schema', $this::SCHEMA);
				$this->force_clean_logs(false);
			}
		}
	}
	
	add_action( 'plugins_loaded', array( 'SucuriLogCleanup', 'get_instance' ) );
endif;
