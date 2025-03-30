<?php
namespace EarthAsylumConsulting\Extensions;

if (! class_exists(__NAMESPACE__.'\object_cache_admin', false) )
{
	/**
	 * Extension: eacObjectCache - SQLite powered WP_Object_Cache Drop-in.
	 *
	 * @category	WordPress Plugin
	 * @package		{eac}Doojigger Utilities\{eac}Doojigger Object Cache
	 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
	 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.EarthAsylum.com>
	 * @link		https://eacDoojigger.earthasylum.com/
	 */

	class object_cache_admin extends \EarthAsylumConsulting\abstract_extension
	{
		/**
		 * @var string extension version
		 */
		const VERSION	= '25.0320.1';

		/**
		 * @var string to set default tab name
		 */
		const TAB_NAME	= 'Object Cache';

		/**
		 * @var string|array|bool to set (or disable) default group display/switch
		 * 		false 		disable the 'Enabled'' option for this group
		 * 		string 		the label for the 'Enabled' option
		 * 		array 		override options for the 'Enabled' option (label,help,title,info, etc.)
		 */
		const ENABLE_OPTION	= false;


		/**
		 * constructor method
		 *
		 * @param 	object	$plugin main plugin object
		 * @return 	void
		 */
		public function __construct($plugin)
		{
			parent::__construct($plugin, self::ALLOW_ADMIN|self::ONLY_ADMIN|self::ALLOW_NETWORK);

			if ($this->is_admin())
			{
				$this->wpConfig = $this->wpconfig_handle();

				register_activation_hook(dirname(__DIR__).'/eacObjectCache.php',function()
					{
						$this->install_object_cache('update');
					}
				);
				register_deactivation_hook(dirname(__DIR__).'/eacObjectCache.php',function()
					{
						$this->install_object_cache('delete');
					}
				);

				$this->registerExtension( self::TAB_NAME );
				// Register plugin options when needed
				$this->add_action( "options_settings_page", array($this, 'admin_options_settings') );
				// Add contextual help
				$this->add_action( 'options_settings_help', array($this, 'admin_options_help') );
			}
		}


		/**
		 * register options on options_settings_page
		 *
		 * @return void
		 */
		public function admin_options_settings(): void
		{
			$check = $this->checkForInstall('version');

			if ( $check !== true )
			{
				$this->registerExtensionOptions( self::TAB_NAME,
					[
						'_sqlite_version' 	=> array(
								'type'		=> 	'display',
								'label'		=> 	'Object Cache',
								'default'	=> 	$check['message'],
						),
					]
				);
				return;
			}

			$check = $this->checkForInstall('pdo');

			if ( $check !== true )
			{
				$this->registerExtensionOptions( self::TAB_NAME,
					[
						'_pdo_missing' 		=> array(
								'type'		=> 	'display',
								'label'		=> 	'PHP Configuration',
								'default'	=> 	$check['message'],
						),
					]
				);
				return;
			}

			$check = $this->checkForInstall('existing');

			if ( $check !== true )
			{
				$this->registerExtensionOptions( self::TAB_NAME,
					[
						'_3rd_party' 		=> array(
								'type'		=> 	'display',
								'title'		=> 	$check['data']['Title'],
								'label'		=> 	'Object Cache',
								'default'	=> 	$check['message'],
						),
					]
				);
				return;
			}

			if ($this->checkForInstall('admin') === true && $this->checkForInstall('source') === true)
			{
				$default = $this->varPost('_btnCacheInstall') ?: ((defined('EAC_OBJECT_CACHE_VERSION')) ? 'Install' : 'Uninstall');
				$default = ($default=='Install') ? 'Uninstall' : 'Install';
				$this->registerExtensionOptions( self::TAB_NAME,
					[
						'_btnCacheInstall' 	=> array(
								'type'		=> 	'button',
								'label'		=> 	'Object Cache',
								'default'	=> 	$default,
								'info'		=>	$default.' the {eac}ObjectCache drop-in.'.
												'<br/><small>* Requires write access to wp-content folder.</small>',
								'validate'	=>	[$this, 'install_object_cache'],
						),
					]
				);
			}

			if (defined('EAC_OBJECT_CACHE_VERSION'))
			{
				global $wp_object_cache;
				$stats = $wp_object_cache->getStatsDB();
				$stats = $stats['Total'];
				$this->registerExtensionOptions( self::TAB_NAME,
					[
						'_btnCacheFlush' 	=> array(
								'type'		=> 	'button',
								'label'		=> 	'Flush Objects',
								'default'	=> 	'Erase Cache',
								'tooltip'	=>	'Erase the persistent object cache.',
								'info'		=>	'<small>* The cache has '.
												'<output style="color:blue;">'.number_format($stats[0],0).'</output>'.
												' records using over '.
												'<output style="color:blue;">'.number_format($stats[1] / MB_IN_BYTES, 1).'MB</output>'.
												' of storage in a '.
												'<output style="color:blue;">'.number_format($stats[2] / MB_IN_BYTES, 1).'MB</output>'.
												' database file.</small>',
								'validate'	=>	'wp_cache_flush_blog',
						),
					]
				);
				$this->registerExtensionOptions( self::TAB_NAME,
					[
						'object_cache_stats'	=> array(
								'type'		=> 	'select',
								'label'		=> 	'Sampling',
						//		'options'	=>	[ 'Disabled'=>'','Use Current Request'=>'current','Use Last Sample'=>'sample' ],
								'options'	=>  [
													"Disabled"					=> 0,
													"Each Request"				=> 1,
													"Every 10th Request"		=> 10,
													"Every 25th Request"		=> 25,
													"Every 50th Request"		=> 50,
													"Every 100th Request"		=> 100,
													"Every 250th Request"		=> 250,
													"Every 500th Request"		=> 500,
													"Every 1,000th Request"		=> 1000,
													"Every 2,500th Request"		=> 2500,
													"Every 5,000th Request"		=> 5000,
												],
								'default'	=>	'',
								'info'		=> 	'Sample and display object cache counts in a notification block on administrator pages.',
								// attributes are on <span> not <input>
								//'attributes'=>	['onchange'=>'options_form.requestSubmit()'],
						),
					]
				);


				if (method_exists($this->plugin,'isAdvancedMode') && $this->plugin->isAdvancedMode('settings'))
				{
					// updates to wp-config.php...
					if ($this->wpConfig) {
						$this->admin_options_settings_advanced();
					} else {
						$this->options_settings_extras(false);
					}
				}
			}
		}


		/**
		 * register advanced options on options_settings_page
		 *
		 * @return void
		 */
		public function admin_options_settings_advanced(): void
		{
			global $wp_object_cache;

			$this->registerExtensionOptions( self::TAB_NAME,
				[
					'_advanced'			=> array(
							'type'		=> 	'display',
							'label'		=> 	'<p><span class="dashicons dashicons-performance"></span></p>',
							'default'	=>	'<p><strong>Advanced options update settings in the wp-config.php file.</strong></p>',
					),
					'_mmap_size'			=> array(
							'type'		=> 	'select',
							'label'		=> 	'Memory Mapped I/O',
							'options'	=>  [
												"Disabled"				=>  0,
												"2MB"					=>  2 * MB_IN_BYTES,
												"4MB"					=>  4 * MB_IN_BYTES,
												"8MB"					=>  8 * MB_IN_BYTES,
												"12MB"					=> 12 * MB_IN_BYTES,
												"16MB"					=> 16 * MB_IN_BYTES,
												"24MB"					=> 24 * MB_IN_BYTES,
												"32MB"					=> 32 * MB_IN_BYTES,
												"48MB"					=> 48 * MB_IN_BYTES,
												"64MB"					=> 64 * MB_IN_BYTES,
												"128MB"					=> 128 * MB_IN_BYTES,
											],
							'default'	=>	(int)$wp_object_cache->mmap_size,
							'info'		=> 	'Sets the maximum number of bytes that are set aside for memory-mapped I/O.',
							'validate'	=>	[$this,'validate_config_option'],
					),

					'_timeout'			=> array(
							'type'		=> 	'number',
							'label'		=> 	'Cache Timeout',
							'default'	=>	(int)$wp_object_cache->pdo_timeout,
							'after'		=> ' seconds',
							'info'		=> 	'Set the SQLite database timeout.',
							'attributes'=>	['min'=>'1','max'=>'8','step'=>'1'],
							'validate'	=>	[$this,'validate_config_option'],
					),

					'_retries'			=> array(
							'type'		=> 	'number',
							'label'		=> 	'Cache Retries',
							'default'	=>	(int)$wp_object_cache->max_retries,
							'after'		=> ' attempts',
							'info'		=> 	'Set the number of retries to attempt on critical actions.',
							'attributes'=>	['min'=>'1','max'=>'8','step'=>'1'],
							'validate'	=>	[$this,'validate_config_option'],
					),
				]
			);

			$this->options_settings_extras(true);

			$this->registerExtensionOptions( self::TAB_NAME,
				[

					'_default_expire'	=> array(
							'type'		=> 	'select',
							'label'		=> 	'Default Expiration',
							'options'	=>  [
												"Cache in memory only"		=> -1,
												"Expire after 1 Minute"		=> MINUTE_IN_SECONDS,
												"Expire after 5 Minutes"	=> MINUTE_IN_SECONDS * 5,
												"Expire after 30 Minutes"	=> MINUTE_IN_SECONDS * 30,
												"Expire after 1 Hour"		=> HOUR_IN_SECONDS,
												"Expire after 12 Hours"		=> HOUR_IN_SECONDS * 12,
												"Expire after 1 Day"		=> DAY_IN_SECONDS,
												"Expire after 1 Week"		=> WEEK_IN_SECONDS,
												"Expire after 1 Month"		=> MONTH_IN_SECONDS,
												"No expiration"				=> 0,
											],
							'default'	=>	$wp_object_cache->default_expire,
							'info'		=> 	'Set the default when an object key does not specify an expiration time.',
							'help'		=> 	"[info] Cache persistence may sometimes causes issues. Here we can set a default expiration ".
											"to alleviate problems and/or improve performance by limiting cache data.",
							'validate'	=>	[$this,'validate_config_option'],
					),

					'_prefetch_misses'	=> array(
							'type'		=> 	'radio',
							'label'		=> 	'Pre-fetch Misses',
							'options'	=>	['Enabled'=>1,'Disabled'=>0],
							'default'	=>	(int)$wp_object_cache->prefetch_misses,
							'info'		=> 	'Pre-fetching cache misses prevents repeated, unnecessary reads of the L2 cache.',
							'validate'	=>	[$this,'validate_config_option'],
					),

					'_probability'		=> array(
							'type'		=> 	'select',
							'label'		=> 	'Probablity Factor',
							'options'	=>  [
												"1 in 10 Requests"			=> 10,
												"1 in 25 Requests"			=> 25,
												"1 in 50 Requests"			=> 50,
												"1 in 100 Requests"			=> 100,
												"1 in 250 Requests"			=> 250,
												"1 in 500 Requests"			=> 500,
												"1 in 1,000 Requests"		=> 1000,
												"1 in 2,500 Requests"		=> 2500,
												"1 in 5,000 Requests"		=> 5000,
												"1 in 10,000 Requests"		=> 10000,
											],
							'default'	=>	$wp_object_cache->gc_probability,
							'info'		=> 	'Determines how often expired objects are purged and the SQLite table is optimized.',
							'validate'	=>	[$this,'validate_config_option'],
					),

					'_nonp_groups' 		=> array(
							'type'		=>	'textarea',
							'label'		=>	"Non-Persistent Groups ",
							'default'	=>	 (defined('EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS') && is_array(EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS))
												? implode(', ',EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS) : '',
							'info'		=>	"Cache groups that should not be stored in the L2 cache table.",
							'help'		=>	"[info] Non-persistent groups are object groups that do not persist across page loads. ".
											"This may be another method to alleviate issues caused by cache persistence ".
											"or improve performance by limiting cache data.",
							'validate'	=>	[$this,'validate_config_option'],
							'height'	=>	'2',
					),

					'_prefetch_groups' 	=> array(
							'type'		=>	'textarea',
							'label'		=>	"Pre-fetch Groups ",
							'default'	=>	 (defined('EAC_OBJECT_CACHE_PREFETCH_GROUPS') && is_array(EAC_OBJECT_CACHE_PREFETCH_GROUPS))
												? implode(', ',EAC_OBJECT_CACHE_PREFETCH_GROUPS) : '',
							'info'		=>	"Pre-fetch specific object groups from L2 cache at startup.",
							'help'		=>	"[info] Pre-fretching a group of records may be much faster than loading each key individually, ".
											"but may load keys that are not neaded, using memory unnecessarily.",
							'validate'	=>	[$this,'validate_config_option'],
							'height'	=>	'2',
					),
				]
			);

			// reload page after submit so we show changes to wp-config constants
			$this->add_action('options_form_post', function($posted)
				{
					$this->page_reload(true);
				}
			);
		}


		/**
		 * register advanced options on options_settings_page
		 *
		 * @return void
		 */
		public function options_settings_extras(bool $isAdmin): void
		{
			global $wp_object_cache;

			$this->registerExtensionOptions( self::TAB_NAME,
				[
					'object_cache_delayed_writes'	=> array(
							'type'		=> 	'select',
							'label'		=> 	'Delayed Writes',
							'options'	=>  [
												"Disabled"				=> 0, 	// false
												"8 Records"				=> 8,
												"16 Records"			=> 16,
												"32 Records"			=> 32,
												"64 Records"			=> 64,
												"96 Records"			=> 96,
												"128 Records"			=> 128,
												"256 Records"			=> 256,
												"Unlimited"				=> 1,	// true
											],
							'default'	=>	(int)$wp_object_cache->delayed_writes,
							'info'		=> 	'Write-Back Caching - Set the number of records to hold in memory before writing to disk.',
							'help'		=> 	"[info] The lower the number, the more frequent physical disk writes, but greater integrity. ".
											"A Higher value means fewer disk writes and faster operation. ".
											"Records are always written at the end of the script process (page load).",
							'validate'	=>	($isAdmin) ? [$this,'validate_config_option'] : null,
					),
				]
			);
		}


		/**
		 * validate/set config options
		 *
		 * @return	void
		 */
 		public function validate_config_option($value, $fieldName, $metaData, $priorValue)
		{
			global $wp_object_cache;
			switch ($fieldName)
			{
				case '_mmap_size':
					if ($value == $wp_object_cache->mmap_size) return $value; 	// no change
					$value = (is_numeric($value)) ? (int)$value : 3;
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_MMAP_SIZE', "{$value}", ['raw'=>true] );
					break;
				case '_timeout':
					if ($value == $wp_object_cache->pdo_timeout) return $value; 	// no change
					$value = (is_numeric($value)) ? (int)$value : 3;
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_TIMEOUT', "{$value}", ['raw'=>true] );
					break;
				case '_retries':
					if ($value == $wp_object_cache->max_retries) return $value; 	// no change
					$value = (is_numeric($value)) ? (int)$value : 3;
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_RETRIES', "{$value}", ['raw'=>true] );
					break;
				case 'object_cache_delayed_writes':
					if ($value == (int)$wp_object_cache->delayed_writes) return $value; 	// no change
					$value = ($value == 0) ? 'FALSE' : ( ($value == 1) ? 'TRUE' : (int)$value );
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_DELAYED_WRITES', "{$value}", ['raw'=>true] );
					break;
				case '_default_expire':
					if ($value == $wp_object_cache->default_expire) return $value; 	// no change
					$value = (is_numeric($value)) ? (int)$value : -1;
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_DEFAULT_EXPIRE', "{$value}", ['raw'=>true] );
					break;
				case '_prefetch_misses':
					if ($value == (int)$wp_object_cache->prefetch_misses) return $value; 	// no change
					$value = ($value == 0) ? 'FALSE' : 'TRUE';
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_PREFETCH_MISSES', "{$value}", ['raw'=>true] );
					break;
				case '_probability':
					if ($value == $wp_object_cache->gc_probability) return $value; 	// no change
					$value = (is_numeric($value)) ? (int)($value + ($value % 2)) : 100;
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_PROBABILITY', "{$value}", ['raw'=>true] );
					break;
				case '_nonp_groups':
					$current = defined('EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS') ? EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS : [];
					$value = array_filter(array_map('trim', explode("\n", str_replace([',',' '],"\n",$value))));
					if ($value == $current) return $value;
					$value = (!empty($value)) ? "[ '".implode("', '",$value)."' ]" : '[]';
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS', "{$value}", ['raw'=>true] );
					break;
				case '_prefetch_groups':
					$current = defined('EAC_OBJECT_CACHE_PREFETCH_GROUPS') ? EAC_OBJECT_CACHE_PREFETCH_GROUPS : [];
					$value = array_filter(array_map('trim', explode("\n", str_replace([',',' '],"\n",$value))));
					if ($value == $current) return $value;
					$value = (!empty($value)) ? "[ '".implode("', '",$value)."' ]" : '[]';
					$this->wpConfig->update( 'constant', 'EAC_OBJECT_CACHE_PREFETCH_GROUPS', "{$value}", ['raw'=>true] );
					break;
			}
			return $value;
		}


		/**
		 * Add help tab on admin page
		 *
		 * @return	void
		 */
 		public function admin_options_help()
		{
			if (!$this->plugin->isSettingsPage(self::TAB_NAME)) return;

			ob_start();
			?>
			The {eac}Doojigger Object Cache is a light-weight and highly efficient drop-in persistent object cache
			that uses a SQLite database to cache WordPress objects.

			See: <a href='https://developer.wordpress.org/reference/classes/wp_object_cache/' target='_blank'>The WordPress Object Cache</a>

			<details><summary>Configuration Options</summary>
			{eac}ObjectCache configuration options may be set by adding defines in the wp-config.php file.
			<ul>
			<li>To set the location of the SQLite database (default: '../wp-content/cache'):<br>
				<code>define( 'EAC_OBJECT_CACHE_DIR', '/full/path/to/folder' );</code>

			<li>To set the name of the SQLite database (default: '.eac_object_cache.sqlite'):<br>
				<code>define( 'EAC_OBJECT_CACHE_FILE', 'filename.sqlite' );</code>

			<li>To set SQLite journal mode (default: 'WAL', Write-Ahead Log):<br>
				<code>define( 'EAC_OBJECT_CACHE_JOURNAL_MODE', journal_mode )</code>
				<br><small>journal_mode is one of 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', or 'OFF'</small>

			<li>To set SQLite mapped memory I/O (default: 0):<br>
				<code>define( 'EAC_OBJECT_CACHE_MMAP_SIZE', int )</code>

			<li>To set SQLite page size (default: 4096):<br>
				<code>define( 'EAC_OBJECT_CACHE_PAGE_SIZE', int )</code>

			<li>To set SQLite cache size (default: -2000 [2,048,000]):<br>
				<code>define( 'EAC_OBJECT_CACHE_CACHE_SIZE', int )</code>

			<li>To set SQLite timeout in seconds (default: 3)<br>
				<code>define( 'EAC_OBJECT_CACHE_TIMEOUT', int );</code>

			<li>To set SQLite retries (default: 3)<br>
				<code>define( 'EAC_OBJECT_CACHE_RETRIES', int );</code>

			<li>To set delayed writes (default: 32):<br>
				<code>define( 'EAC_OBJECT_CACHE_DELAYED_WRITES', true|false|int );</code>
				<br><small>false = no delayed writes, true = write all records at end,
				<br>int = the number of records in memory before writing to disk.</small>

			<li>To set the default expiration time (in seconds, default: 0)<br>
				<code>define( 'EAC_OBJECT_CACHE_DEFAULT_EXPIRE', -1|0|int );</code>
				<br><small>-1 = don't cache to persistent database, 0 = never expire, int = number of seconds until expired.</small>

			<li>To enable/disable pre-fetching of cache misses (default: true)<br>
				<code>define('EAC_OBJECT_CACHE_PREFETCH_MISSES', true | false);</code>

			<li>To set maintenance/sampling probability (default: 100)<br>
				<code>define( 'EAC_OBJECT_CACHE_PROBABILITY', int );</code>

			<li>To set groups as global (not site-specific in multisite)<br>
				<code>define( 'EAC_OBJECT_CACHE_GLOBAL_GROUPS', [ 'groupA', 'groupB', ... ] );</code>
				<br><small>WordPress automatically loads a list of global groups.</small>

			<li>To set groups as non-persistant (not stored in the SQLite table)<br>
				<code>define( 'EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS', [ 'groupA', 'groupB', ... ] );</code>
				<br><small>WordPress automatically loads a list of non-persistent groups.</small>

			<li>To pre-fetch group(s) into memory at startup<br>
				<code>define( 'EAC_OBJECT_CACHE_PREFETCH_GROUPS', [ 'groupA', 'groupB', ... ] );</code>
			</ul>
			</details>
			<?php
			$content = ob_get_clean();

			$this->addPluginHelpTab(self::TAB_NAME,$content,[self::TAB_NAME,'open']);

			$this->addPluginSidebarLink(
				"<span class='dashicons dashicons-performance'></span>{eac}ObjectCache",
				"https://eacdoojigger.earthasylum.com/eacobjectcache/",
				"{eac}ObjectCache Extension Plugin"
			);
		}


		/**
		 * check installation criteria
		 *
		 * @return bool|array
		 */
		public function checkForInstall($check='all')
		{
			// check SQLite version
			if ($check == 'all' || $check == 'version')
			{
				$version = (class_exists('\SQLite3')) ? \SQLite3::version()['versionString'] : '';
				if ( version_compare( $version, '3.24.0' ) < 0 )
				{
					return [
						'type'		=>	'version',
						'data'		=>	$version,
						'message'	=> 	'{eac}ObjectCache requires SQLite v3.24.0 or greater. '.
											($version ? "Version {$version} is currently installed." : '').'.'
					];
				}
			}

			// check PDO extensions
			if ($check == 'all' || $check == 'pdo')
			{
				if ( ! extension_loaded( 'pdo' ) )
				{
					return [
						'type'		=>	'pdo',
						'message'	=>	'The PHP PDO Extension is not loaded.'
					];
				}
				if ( ! extension_loaded( 'pdo_sqlite' ) )
				{
					return [
						'type'		=>	'pdo_sqlite',
						'message'	=>	'The PHP PDO Driver for SQLite is missing.'
					];
				}
			}

			// check 3rd-party object cache
			if ($check == 'all' || $check == 'existing')
			{
				if (file_exists(WP_CONTENT_DIR.'/object-cache.php') && !defined('EAC_OBJECT_CACHE_VERSION'))
				{
					$plugin_data = get_plugin_data( WP_CONTENT_DIR.'/object-cache.php', true );
					if ( ! $plugin_data['Title'] ) $plugin_data['Title'] = '3rd Party Object Cache';
					return [
						'type'		=>	'exists',
						'data'		=> 	$plugin_data,
						'message'	=> 	'a 3rd-party object cache drop-in is already installed '.
										'and must be removed before using {eac}ObjectCache.',

					];
				}
			}

			// check network admin
			if ($check == 'all' || $check == 'admin')
			{
				if (is_multisite() && !$this->plugin->is_network_admin())
				{
					return [
						'type'		=>	'admin',
						'message'	=>	'You must be a network administrator.'
					];
				}
			}

			// check source to install
			if ($check == 'all' || $check == 'source')
			{
				if ( !file_exists(dirname(__DIR__).'/src/object-cache.php') ||
					 !file_exists(dirname(__DIR__).'/src/wp-cache.php'))
				{
					return [
						'type'		=>	'source',
						'message'	=>	'The object-cache source file is missing.'
					];
				}
			}

			return true;
		}


		/**
		 * install/uninstall object cache
		 *
		 * @param $action button ('Install' | 'Uninstall') or ('update' | 'delete')
		 * @return string $action
		 */
		public function install_object_cache($action)
		{
			if ($this->checkForInstall() !== true)
			{
				return false;
			}

			$action = strtolower($action);

			if ($action == 'uninstall' || $action == 'delete')
			{
				global $wp_object_cache;
				if (method_exists($wp_object_cache,'uninstall')) {
					if (is_multisite()) {
						$this->forEachNetworkSite(function() use($wp_object_cache)
							{
								$wp_object_cache->uninstall(false);
							}
						);
					}
					$wp_object_cache->uninstall(true);
				}
			}
			else if ($fs = $this->fs->load_wp_filesystem())
			{
				$cache = (defined( 'EAC_OBJECT_CACHE_DIR' ) && is_string( EAC_OBJECT_CACHE_DIR ))
					? EAC_OBJECT_CACHE_DIR
					: $fs->wp_content_dir().'/cache';

				// since we write not using $fs, we need owner & group access
				if (!$fs->exists($cache)) {
					$fs->mkdir($cache,FS_CHMOD_DIR|0660);
				} else {
					$fs->chmod($cache,FS_CHMOD_DIR|0660);
				}
			}

			$this->installer->invoke($action,false,
				[
					'title'			=> 'The {eac}Doojigger Object Cache',
					'sourcePath'	=> dirname(__DIR__).'/src',
					'sourceFile'	=> 'object-cache.php',
					'targetPath'	=> WP_CONTENT_DIR,
					'targetFile'	=> 'object-cache.php',
					'return_url'	=> ($action != 'delete' && $action != 'update')
										? remove_query_arg('fs') : '',	// force reload after manual install
				]
			);
			return $action;
		}


		/**
		 * version updated
		 *
		 * @param	string	$curVersion currently installed version number
		 * @param	string	$newVersion version being installed/updated
		 * @return	bool
		 */
		public function adminVersionUpdate($curVersion,$newVersion)
		{
			if (defined('EAC_OBJECT_CACHE_VERSION'))
			{
				$this->install_object_cache('update');
			}
		}
	}
}
/**
 * return a new instance of this class
 */
if (isset($this)) return new object_cache_admin($this);
?>
