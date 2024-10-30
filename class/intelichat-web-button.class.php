<?php

if (!defined('INTELICHAT_W_PLUGIN_VERSION')) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if(!class_exists("InteliChatWebButton")) {

	class InteliChatWebButton {
		
		private static $initiated = false;
		private static $apiKey;
		private static $acceptTerms;
		private static $windowOptions;
		private static $sysOptions = null;
		
		public static function plugin_activation() {
		}
		
		public static function plugin_deactivation() {
		}
		
		public static function plugin_uninstall() {
			delete_option('icw_acceptTerms');
			delete_option('icw_apiKey');
			delete_option('icw_windowSettings');
		}
		
		public static function init()
		{
			if (self::$initiated) { return; }
			$default_window_w = 300;
			$default_window_h = 400;
			$default_button_w = 60;
			$default_button_h = 60;
			
			self::$initiated = true;
			self::$apiKey = get_option('icw_apiKey');
			self::$acceptTerms = get_option('icw_acceptTerms');
			
			self::$windowOptions = get_option('icw_windowSettings');
			if(!is_array(self::$windowOptions)) { self::$windowOptions = array(
				'color'=>'#ffffff',
				'backColor'=>'#55b277',
				'caption'=>'Intelichat Web Button',
				'window_side'=>'right',
				'bot'=>array('id'=>null,'url'=>null),
				'window_size'=>array('width'=>$default_window_w,'height'=>$default_window_h),
				'buttom_size'=>array('width'=>$default_button_w,'height'=>$default_button_h));
			}
			else {
				if(!isset(self::$windowOptions['bot']['id'], self::$windowOptions['bot']['url'])) {
					self::$windowOptions['bot'] = array('id'=>null,'url'=>null);
				}
				
				if(!isset(self::$windowOptions['window_size']['width'], self::$windowOptions['window_size']['height'])) {
					self::$windowOptions['window_size'] = array('width'=>$default_window_w,'height'=>$default_window_h);
				}
				
				if(!isset(self::$windowOptions['buttom_size']['width'], self::$windowOptions['buttom_size']['height'])) {
					self::$windowOptions['buttom_size'] = array('width'=>$default_button_w,'height'=>$default_button_h);
				}
			}
			
			self::$windowOptions['button'] = self::get_image_properties(self::$windowOptions['button'], INTELICHAT_W_PLUGIN_URL . 'public/images/button.png', array(120, 9999));
	 
			// Add the page to the admin menu
			add_action( 'admin_menu', array('InteliChatWebButton', 'add_menu_page'));
			 
			// Register page options
			add_action( 'admin_init', array('InteliChatWebButton', 'register_page_options'));
			 
			// Register javascript
			add_action('admin_enqueue_scripts', array('InteliChatWebButton', 'enqueue_admin_js'));
			
			if(!is_admin() && self::$acceptTerms && !empty(self::$windowOptions['bot']['url'])) {
				wp_register_style('icw-intelichat-style', INTELICHAT_W_PLUGIN_URL . 'public/css/style.css');
				
				wp_enqueue_style('icw-intelichat-style');
				
				add_action('wp_footer', array('InteliChatWebButton', 'print_footer'));
			}
		}
		
		public static function validate_apiKey($field) {
			return $field;
		}
		
		public static function validate_windowSettings($fields) {
			
			$valid_fields = array();
			
			$valid_fields['caption'] = strip_tags( stripslashes( trim( $fields['caption'] ) ) );
			$valid_fields['window_side'] = strtolower(strip_tags( stripslashes( trim( $fields['window_side'] ) ) ) );
			$valid_fields['color'] = strip_tags( stripslashes( trim( $fields['color'] ) ) );
			$valid_fields['backColor'] = strip_tags( stripslashes( trim( $fields['backColor'] ) ) );
			$valid_fields['button'] = (int)$fields['button'];
			$valid_fields['bot'] = $fields['bot'];
			$valid_fields['window_size'] = array();
			$valid_fields['window_size']['width'] = empty($fields['window_size']['width']) ? 0 : (int)$fields['window_size']['width'];
			$valid_fields['window_size']['height'] = empty($fields['window_size']['height']) ? 0 : (int)$fields['window_size']['height'];
			$valid_fields['buttom_size'] = array();
			$valid_fields['buttom_size']['width'] = empty($fields['buttom_size']['width']) ? "" : (int)$fields['buttom_size']['width'];
			$valid_fields['buttom_size']['height'] = empty($fields['buttom_size']['height']) ? "" : (int)$fields['buttom_size']['height'];
			
			if($valid_fields['window_size']['width'] <= 0 || $valid_fields['window_size']['height'] <= 0) {
				add_settings_error( 'icw_settings', 'window_size', 'Invalid value for <b>Window size</b>', 'error' );
				$valid_fields['window_size'] = self::$windowOptions['window_size'];
			}
			
			if((!empty($valid_fields['buttom_size']['width']) && $valid_fields['buttom_size']['width'] <= 0) || (!empty($valid_fields['buttom_size']['height']) && $valid_fields['buttom_size']['height'] <= 0)) {
				add_settings_error( 'icw_settings', 'buttom_size', 'Invalid value for <b>Web Button size</b>', 'error' );
				$valid_fields['buttom_size'] = self::$windowOptions['buttom_size'];
			}
			
			if(!is_array($valid_fields['bot'])) {
				$valid_fields['bot'] = empty($valid_fields['bot']) ? array('id'=>null,'url'=>null) : json_decode(str_replace("'",'"', $valid_fields['bot']), true);
			}
			
			if(!isset($valid_fields['bot']['id'], $valid_fields['bot']['url'])) {
				add_settings_error( 'icw_settings', 'icw_bot_error', 'Invalid value for <b>Bot</b>', 'error' );
				$valid_fields['bot'] = self::$windowOptions['bot'];
			}
			
			if(!self::is_color($valid_fields['color'])) {
				add_settings_error( 'icw_settings', 'icw_color_error', 'Invalid value for <b>Text color</b>', 'error' );
				$valid_fields['color'] = self::$windowOptions['color'];
			}
			
			if(!self::is_color($valid_fields['backColor'])) {
				add_settings_error( 'icw_settings', 'icw_backColor_error', 'Invalid value for <b>Background color</b>', 'error' );
				$valid_fields['backColor'] = self::$windowOptions['backColor'];
			}
			
			if($valid_fields['window_side'] != 'left' && $valid_fields['window_side'] != 'right') {
				add_settings_error( 'icw_settings', 'window_side_error', 'Invalid value for <b>Window position</b>', 'error' );
				$valid_fields['window_side'] = self::$windowOptions['window_side'];
			}
			
			return $valid_fields;
		}
		
		public static function print_footer() {
			
			$button_w = empty(self::$windowOptions['buttom_size']['width']) ? "" : self::$windowOptions['buttom_size']['width'] . "px";
			$button_h = empty(self::$windowOptions['buttom_size']['height']) ? "" : self::$windowOptions['buttom_size']['height'] . "px";
			$window_w = self::$windowOptions['window_size']['width'] . "px";
			$window_h = self::$windowOptions['window_size']['height'] . "px";
			
			$button = self::$windowOptions['button']['src'];
			$window_side = self::$windowOptions['window_side'];
			$color = self::$windowOptions['color'];
			$backcolor = self::$windowOptions['backColor'];
			$zIndex = "9999";
			$bot = self::$windowOptions['bot']['url'];
			$caption = self::$windowOptions['caption'];
			
			echo '<style>
				#inteliChatWindow { width:' . $window_w . '; z-index:' . $zIndex . '; ' . $window_side . ':20px; }
				.inteliChat.open-btn { width:' . $button_w . '; height:' . $button_h . '; z-index:' . $zIndex . '; ' . $window_side . ':30px; }
				.inteliChat.close-btn { background:' . $backcolor . '; border:solid 3px ' . $backcolor . '; }
				.inteliChat.content { height:' . $window_h . '; }
				.inteliChat.close-btn:hover { color:' . $backcolor . '; background:' . $color . '; }
				.inteliChat.hidden { display:none; }
			</style>
			
			
			<script>
				function showWindow() {
					jQuery("#webButton").addClass("hidden");
					jQuery("#inteliChatWindow").removeClass("hidden");
				}
				function hideWindow() {
					jQuery("#inteliChatWindow").addClass("hidden");
					jQuery("#webButton").removeClass("hidden");
				}
			</script>';
			
			echo '<div id="inteliChatWindow" class="inteliChat hidden">
				<h1 class="inteliChat title" style="color:' . $color . '; background:' . $backcolor . ';">' . $caption . '
					<span class="inteliChat close-btn" onclick="hideWindow();">X</span>
				</h1>
				<div class="inteliChat content">
					<iframe class="scroll" src="' . $bot . '" scrolling="yes" width="100%" height="100%" style="border-width:0px;"></iframe>
				</div>
			</div>
			<img class="inteliChat open-btn" id="webButton" src="' . $button . '" onclick="showWindow();"/>';
		}
		
		private static function get_image_properties($id, $defaultSrc, $size) {
			$sucess = false;
			$src = $defaultSrc;
			if (!empty($id)) {
				$image_attributes = wp_get_attachment_image_src($id, $size);
				$src = $image_attributes[0];
				$value = $options[$name];
				$sucess = true;
			}
			return array('id'=>$id, 'default'=>$defaultSrc, 'src'=>$src, 'sucess'=>$sucess);
		}
		
		/**
		 * Function that will add the options page under Setting Menu.
		 */
			
		public static function add_menu_page()
		{
			add_menu_page(
				'Intelichat Web Button Settings', // $page_title
				'Intelichat Web Button', // $menu_title
				'manage_options', // $capability
				'intelichat_icw_settings', // $menu_slug
				array('InteliChatWebButton', 'create_options_html'), // $function
				INTELICHAT_W_PLUGIN_URL . 'public/images/icon.png', // $icon_url
				20 // $position
			);
		}
		
		public static function display_section() {
		}
		
		private static function updateSysOptions() {
			
			if(self::$sysOptions != null) { return; }
			
			self::$sysOptions = get_option('icw_options');
			if(self::$sysOptions == null) { self::$sysOptions == array(); }
			
			if(!empty(self::$apiKey)) {
				
				$botList = InteliChatAPI::getBotList(self::$apiKey);
				if($botList != null)
				{
					if(isset($botList['error']))
					{
						// key inválida
						$botList = null;
					}
					self::$sysOptions['botList'] = array('time'=>date('d/m/Y H:i'), 'values'=>$botList);
				}
				else
				{
					// falha ao atualizar lista
					self::$sysOptions['botList']['update_error'] = true;
				}
			}
			else {
				self::$sysOptions['botList'] = array('time'=>date('d/m/Y H:i'));
			}
			update_option('icw_options', self::$sysOptions);
		}
		
		public static function create_options_html() {    ?>
		<div class="wrap intelichat_settings">
		
			<img height="70px" style="margin-bottom:10px;" src="<?=INTELICHAT_W_PLUGIN_URL . 'public/images/logo.jpg'?>"/>
			<form method="post" action="options.php">     
			<?php
				if(!self::$acceptTerms) {
					echo '<h2>Terms and conditions</h2>';
					if(file_exists(INTELICHAT_W_PLUGIN_DIR . 'admin/terms.txt')) {
						$file = fopen (INTELICHAT_W_PLUGIN_DIR . 'admin/terms.txt', 'r');
						while(!feof($file)) {
							echo fgets($file, 1024) . '<br />';
						}
						fclose($file);
					}
					settings_fields('icw_terms');
					echo '<br><input type="checkbox" id="icw_acceptTerms" name="icw_acceptTerms" required /><label for="icw_acceptTerms" style="color:#55A;font-weight:bold;">I accept the terms and conditions</label>';
					submit_button('Continue');
				}
				else {
					$page = $_GET['page'];
					if(isset($_GET['tab'])) { $active_tab = $_GET['tab']; }
					else { $active_tab = empty(self::$apiKey) ? 'icw_credentials' : 'icw_general'; }
					?>
					
					<h2 class="nav-tab-wrapper" style="margin-bottom:10px;">  
						<a href="?page=<?=$page?>&tab=icw_credentials" class="nav-tab <?php echo $active_tab == 'icw_credentials' ? 'nav-tab-active' : ''; ?>">Intelichat API Key</a>
						<a href="?page=<?=$page?>&tab=icw_general" class="nav-tab <?php echo $active_tab == 'icw_general' ? 'nav-tab-active' : ''; ?>">Configuration</a>
					</h2>
					
					<?php
					
					self::updateSysOptions();
					
					settings_errors();
					
					if( $active_tab == 'icw_general' ) {
			
						settings_fields('icw_settings');      
						do_settings_sections('icw_settings');
					}
					else {
						settings_fields('icw_credentials');      
						do_settings_sections('icw_credentials');
						echo "<i>Note: The Intelichat API Key is found in the ‘My profile’ menu of the administration section of Intelichat (upper right menu, with the avatar). <br></i>";
					}
					
					if(isset(self::$sysOptions['botList']['update_error'])) {
						if(!isset(self::$sysOptions['botList']['values'])) { echo '<div class="ic_error">* This API Key appears to be invalid</div>'; }
						echo '<div class="ic_error">* Sorry, could not update the Bot list...';
						if(isset(self::$sysOptions['botList']['time'])) { echo '<br>* Last update: ' . self::$sysOptions['botList']['time']; }
						echo '</div>';
					}
					else if(!isset(self::$sysOptions['botList']['values'])) { echo '<div class="ic_error">* This API Key is invalid</div>'; }
					
					submit_button();
				}
			?>
			</form>
		</div> <!-- /wrap -->
		<?php 
		}
		/**
		 * Function that will register admin page options.
		 */
		public static function register_page_options() { 
			register_setting('icw_terms', 'icw_acceptTerms', array('type'=>'boolean', 'default'=>false));
			
			add_settings_section('icw_credentials_section', 'Intelichat API key', array('InteliChatWebButton', 'display_section' ), 'icw_credentials'); // id, title, display cb, page
			add_settings_field( 'icw_apiKey_field', 'API Key', array('InteliChatWebButton', 'icw_apiKey_settings_field' ), 'icw_credentials', 'icw_credentials_section' ); // id, title, display cb, page, section
			register_setting('icw_credentials', 'icw_apiKey', array('sanitize_callback'=>array('InteliChatWebButton', 'validate_apiKey'))); // option group, option name, sanitize cb
			 
			// Add Section for option fields
			add_settings_section('icw_window_settings_section', 'Configuration', array('InteliChatWebButton', 'display_section' ), 'icw_settings'); // id, title, display cb, page
			 
			// Add Title Field
			add_settings_field( 'icw_bot_field', 'Bot', array('InteliChatWebButton', 'bot_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_caption_field', 'Window title', array('InteliChatWebButton', 'caption_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_color_field', 'Text color', array('InteliChatWebButton', 'color_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_backColor_field', 'Background color', array('InteliChatWebButton', 'backColor_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_position_field', 'Window position', array('InteliChatWebButton', 'position_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_window_size_field', 'Window size', array('InteliChatWebButton', 'window_size_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_buttom_size_field', 'Web Button size', array('InteliChatWebButton', 'buttom_size_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			add_settings_field( 'icw_buttom_field', 'Web Button', array('InteliChatWebButton', 'button_settings_field' ), 'icw_settings', 'icw_window_settings_section' ); // id, title, display cb, page, section
			
			register_setting('icw_settings', 'icw_windowSettings', array('sanitize_callback'=>array('InteliChatWebButton', 'validate_windowSettings'))); // option group, option name, sanitize cb 
		}
		
		/**
		 * Functions that display the fields.
		 */
		public static function bot_settings_field() { $find = false; ?>
			<select name="icw_windowSettings[bot]" >
				<option value=''>[Nenhum]</option>
				<?php if(isset(self::$sysOptions['botList']['values'])) { foreach(self::$sysOptions['botList']['values'] as $value) { ?>
				<option value="{'id':'<?=$value['id']?>','url':'<?=$value['url']?>'}" <?php if(self::$windowOptions['bot']['id'] == $value['id']) { $find = true; echo ' selected'; } ?>><?=$value['botname']?></option>
				<?php } } ?>
				<?php if(!$find && !empty(self::$windowOptions['bot']['url'])) { ?>
				<option class="ic_error" value="{'id':'<?=self::$windowOptions['bot']['id']?>','url':'<?=self::$windowOptions['bot']['url']?>'}" selected><?=self::$windowOptions['bot']['url']?></option>
				<?php } ?>
			</select>
			<?php
		}
		public static function position_settings_field() { ?>
			<select name="icw_windowSettings[window_side]">
				<option value="right" <?=self::$windowOptions['window_side'] == 'right' ? 'selected' : ''?>>Bottom right corner</option>
				<option value="left" <?=self::$windowOptions['window_side'] == 'left' ? 'selected' : ''?>>Bottom left corner</option>
			</select>
			<?php
		}
		
		public static function window_size_settings_field() {
			echo '<div class="ic_cols_values">Height: <input required class="number" min="1" type="number" name="icw_windowSettings[window_size][height]" value="' . self::$windowOptions['window_size']['height'] . '" />px';
			echo '<span class="separator"></span>';
			echo 'Width: <input required class="number" min="1" type="number" name="icw_windowSettings[window_size][width]" value="' . self::$windowOptions['window_size']['width'] . '" />px</div>';
		}
		
		public static function buttom_size_settings_field() {
			echo '<div class="ic_cols_values">Height: <input class="number" min="1" type="number" name="icw_windowSettings[buttom_size][height]" value="' . self::$windowOptions['buttom_size']['height'] . '" />px';
			echo '<span class="separator"></span>';
			echo 'Width: <input class="number" min="1" type="number" name="icw_windowSettings[buttom_size][width]" value="' . self::$windowOptions['buttom_size']['width'] . '" />px</div>';
		}
		
		public static function caption_settings_field() {
			echo '<input type="text" name="icw_windowSettings[caption]" value="' . self::$windowOptions['caption'] . '" />';
		}
		public static function color_settings_field() {
			echo '<input class="color-picker" type="text" name="icw_windowSettings[color]" value="' . self::$windowOptions['color'] . '" />';
		}
		public static function backColor_settings_field() {
			echo '<input class="color-picker" type="text" name="icw_windowSettings[backColor]" value="' . self::$windowOptions['backColor'] . '" />';
		}
		public static function icw_apiKey_settings_field() {
			echo '<input type="text" name="icw_apiKey" style="width: 360px !important;" value="' . self::$apiKey . '" />';
		}
		public static function button_settings_field() {
			echo '<div class="upload_display">
				<img data-src="' . self::$windowOptions['button']['default'] . '" src="' . self::$windowOptions['button']['src'] . '"/>
				<div>
					<input type="hidden" name="icw_windowSettings[button]" value="' . self::$windowOptions['button']['id'] . '"/>
					<button ' . (self::$windowOptions['button']['sucess'] ? '' : 'disabled') . ' class="remove_image_button button">Standard</button>
					<button class="upload_image_button button">Change</button>
				</div>
			</div>';
		}
		
		/**
		 * Function that will add javascript file for Color Piker.
		 */
		public static function enqueue_admin_js() {
			wp_register_style('icw-intelichat-style', INTELICHAT_W_PLUGIN_URL . 'admin/css/style.css', null, 2);
			
			wp_enqueue_style('wp-color-picker');
			wp_enqueue_style('icw-intelichat-style');
			
			wp_enqueue_media();
			wp_enqueue_script('icw-uploader-js', INTELICHAT_W_PLUGIN_URL . 'admin/js/uploader.js', array( 'jquery'), '', true  );
			wp_enqueue_script('icw-colorpicker-js', INTELICHAT_W_PLUGIN_URL . 'admin/js/colorpicker.js', array( 'jquery'), '', true  );
		}
		
		public static function is_color($value) {
			 
			if ( preg_match( '/^#[a-f0-9]{3}$/i', $value ) || preg_match( '/^#[a-f0-9]{6}$/i', $value ) ) {
				return true;
			}
			 
			return false;
		}
	}

}
