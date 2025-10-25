<?php
/**
 * {eac}Doojigger Object Cache - SQLite and APCu powered WP_Object_Cache Drop-in
 *
 * Plugin Name:			{eac}ObjectCache
 * Description:			{eac}Doojigger Object Cache - SQLite and APCu powered WP_Object_Cache Drop-in
 * Version:				2.1.2
 * Requires at least:	5.8
 * Tested up to:		6.8
 * Requires PHP:		7.4
 * Plugin URI:			https://eacdoojigger.earthasylum.com/eacobjectcache/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 */

defined( 'ABSPATH' ) || exit;

define('EAC_OBJECT_CACHE_VERSION','2.1.2');

/**
 * Derived from WordPress core WP_Object_Cache (wp-includes/class-wp-object-cache.php)
 *
 * {eac}ObjectCache is an extension plugin to and is fully functional with installation and registration of
 * {eac}Doojigger (see: https://eacDoojigger.earthasylum.com/).
 *
 * However, the core 'object-cache.php' file may be installed without {eac}Doojigger - referred to as 'detached' mode.
 *
 * - - -
 *
 * To disable use of SQLite database
 *
 * 		define( 'EAC_OBJECT_CACHE_USE_DB', false );
 *
 * To disable use of APCu memory
 *
 * 		define( 'EAC_OBJECT_CACHE_USE_APCU', false );
 *
 * To prevent outside actors from flushing caches.
 *
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_FULL_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_GROUP_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_BLOG_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_RUNTIME_FLUSH', true );
 */


class WP_Object_Cache
{
	/**
	 * This plugin id.
	 *
	 * @var string
	 */
	public const PLUGIN_NAME			= '{eac}ObjectCache';

	/**
	 * Internal group id.
	 *
	 * @var string
	 */
	private const GROUP_ID				= '@object-cache';
	private const GROUP_ID_GLOBAL		= '@object-cache-global';

	/**
	 * Path name to cache folder.
	 *
	 * Set with: EAC_OBJECT_CACHE_DIR
	 *
	 * @var string
	 */
	private string $cache_dir			= WP_CONTENT_DIR.'/cache';

	/**
	 * Name of cache file.
	 *
	 * Set with: EAC_OBJECT_CACHE_FILE
	 *
	 * @var string
	 */
	private string $cache_file			= '.eac_object_cache.sqlite';

	/**
	 * SQLite journal mode.
	 *
	 * Set with: EAC_OBJECT_CACHE_JOURNAL_MODE
	 *
	 * @var string
	 */
	private string $journal_mode		= 'WAL';

	/**
	 * SQLite timeout in seconds.
	 *
	 * Set with: EAC_OBJECT_CACHE_TIMEOUT
	 *
	 * @var int
	 */
	private int $timeout				= 3;

	/**
	 * SQLite mapped memory I/O.
	 *
	 * Set with: EAC_OBJECT_CACHE_MMAP_SIZE
	 *
	 * @var int
	 */
	private int $mmap_size				= 0;

	/**
	 * SQLite page size.
	 *
	 * Set with: EAC_OBJECT_CACHE_PAGE_SIZE
	 *
	 * @var int
	 */
	private int $page_size				= 4096;

	/**
	 * SQLite cache size.
	 *
	 * Set with: EAC_OBJECT_CACHE_CACHE_SIZE
	 *
	 * @var int
	 */
	private int $cache_size				= -2000;

	/**
	 * SQL (open/write/delete) retries.
	 *
	 * Set with: EAC_OBJECT_CACHE_MAX_RETRIES, $wp_object_cache->max_retries = n
	 *
	 * @var int
	 */
	public int $max_retries				= 3;

	/**
	 * Sleep between retries (micro-seconds) 1/10-second.
	 *
	 * Set with: $wp_object_cache->sleep_time = n
	 *
	 * @var int
	 */
	public int $sleep_time				= 100000;

	/**
	 * Use delayed writes until shutdown or n records.
	 *
	 * Set with: EAC_OBJECT_CACHE_DELAYED_WRITES, $wp_object_cache->delayed_writes = n
	 *
	 * @var bool|int
	 */
	public $delayed_writes				= 32;

	/**
	 * If expiration is not set, use this time integer.
	 * -1 = don't cache (non-persistent), 0 = no expiration, int = seconds to expiration
	 *
	 * Set with: EAC_OBJECT_CACHE_DEFAULT_EXPIRE, $wp_object_cache->default_expire = n
	 * See $group_expire (expire by group)
	 *
	 * @var int
	 */
	public int $default_expire			= 0;

	/**
	 * Pre-fetch cache misses.
	 *
	 * Set with: EAC_OBJECT_CACHE_PREFETCH_MISSES
	 *
	 * @var bool
	 */
	private bool $prefetch_misses 		= true;

	/**
	 * The probablity of running maintenance functions.
	 *
	 * Set with: EAC_OBJECT_CACHE_PROBABILITY, $wp_object_cache->probability = n
	 *
	 * @var int
	 */
	public int $probability				= 1000;

	/**
	 * List of actions or filters that disable $delayed_writes.
	 *
	 * Set with: EAC_OBJECT_CACHE_WRITE_HOOKS, $wp_object_cache->write_hooks = [...]
	 *
	 * @var string[]
	 */
	public array $write_hooks 			= array(
		'cron_request',								// when preparing to spawn a cron request
		'updated_option_cron',						// after updating cron schedule
		'set_transient_doing_cron',					// after setting the doing_cron transient
	//	'updated_option_active_plugins',			// after activating plugins
	//	'updated_option_active_sitewide_plugins',	// after activating plugins
	);

	/**
	 * Samples (every n requests) & outputs an admin notice with htmlStats().
	 *
	 * Set with: $wp_object_cache->display_stats = n
	 *
	 * @var int
	 */
	public int $display_stats			= 0;

	/**
	 * Log stats in eacDoojigger log & Query Monitor action.
	 *
	 * Set with: $wp_object_cache->log_stats = false|true
	 *
	 * @var bool
	 */
	public bool $log_stats				= false;

	/**
	 * Display errors in an admin notice.
	 *
	 * Set with: $wp_object_cache->display_errors = false|true
	 *
	 * @var bool
	 */
	public bool $display_errors			= false;

	/**
	 * Log errors in eacDoojigger log & Query Monitor action.
	 *
	 * Set with: $wp_object_cache->log_errors = false|true
	 *
	 * @var bool
	 */
	public bool $log_errors				= false;

	/**
	 * List of global/site-wide cache groups (or any group with ':sitewide' suffix).
	 * Although there is no standard way to set ':prefetch' or ':nocaching' on a transient, we do allow for it.
	 * Whereas ':permanent',':sitewide' don't make sense.
	 *
	 * WordPress core adds default global groups.
	 *
	 * Set with: EAC_OBJECT_CACHE_GLOBAL_GROUPS, wp_cache_add_global_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private array $global_groups		= array(
		self::GROUP_ID_GLOBAL		=> true,
		'site-transient:prefetch' 	=> true,
		'site-transient:nocaching' 	=> true,
	);

	/**
	 * List of non-persistent cache groups (or any group with ':nocaching' suffix).
	 *
	 * WordPress core adds default non-persistent groups.
	 *
	 * Set with: EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS, wp_cache_add_non_persistent_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private array $nonp_groups			= array();

	/**
	 * List of permanent cache groups, no expiration required (or any group with ':permanent' suffix).
	 * Although there is no standard way to set ':prefetch' on a transient, we do allow for it.
	 * Whereas ':nocaching',':permanent',':sitewide' don't make sense.
	 *
	 * Set with: EAC_OBJECT_CACHE_PERMANENT_GROUPS, wp_cache_add_permanent_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private array $perm_groups			= array(
		self::GROUP_ID				=> true,
		self::GROUP_ID_GLOBAL		=> true,
		'transient'					=> true,
		'transient:prefetch'		=> true,
		'site-transient'			=> true,
		'site-transient:prefetch' 	=> true,
	);

	/**
	 * List of pre-loaded cache groups (or any group with ':prefetch' suffix).
	 *
	 * Set with: EAC_OBJECT_CACHE_PREFETCH_GROUPS, wp_cache_add_prefetch_groups( [...] )
	 *
	 * @var [string => bool|string|array]
	 */
	private array $prefetch_groups 		= array(
		self::GROUP_ID				=> true,
		self::GROUP_ID_GLOBAL		=> true,
		'%:prefetch'				=> true,
  		'options' 					=> ['%alloptions','%notoptions'],
  		'site-options' 				=> ['%alloptions','%notoptions'],
  	);

	/**
	 * If expiration is not set, use this time integer for the group.
	 * -1 = don't cache (non-persistent), 0 = no expiration, int = seconds to expiration
	 *
	 * Set with: EAC_OBJECT_CACHE_GROUP_EXPIRE, wp_cache_add_group_expire( [...] )
	 * See $default_expire and cache_clear_query_groups()
	 *
	 * @var [string => int]
	 */
	public array $group_expire			= array(
	// these query groups have no expiration
	//	'comment-queries'			=> DAY_IN_SECONDS,
	//	'site-queries'				=> DAY_IN_SECONDS,
	//	'network-queries'			=> DAY_IN_SECONDS,
	//	'post-queries'				=> DAY_IN_SECONDS,
	//	'term-queries'				=> DAY_IN_SECONDS,
	//	'user-queries'				=> DAY_IN_SECONDS,
	);

	/**
	 * Holds the cached objects (group => [key => [value=>,expire=>] | false]).
	 *	false = tried but not in persistent cache, don't try again.
	 *
	 * @var array
	 */
	private array $L1_cache				= array();

	/**
	 * Holds db writes objects (group => [key => expire | false]).
	 *	false = to be deleted from persistent cache.
	 *
	 * @var array
	 */
	private array $L2_cache				= array();

	/**
	 * Holds the currently found/selected object ([value=>,expire=>] | false).
	 *
	 * @var mixed
	 */
	private $current					= null;

	/**
	 * The row count of the current/last select
	 *
	 * @var int
	 */
	private int $select_count			= 0;

	/**
	 * When using APCu, save memory by not storing APCu data in L1_cache.
	 * Slight increase in processing as most cache hits will come through APCu.
	 *
	 * Set with: EAC_OBJECT_CACHE_OPTIMIZE_MEMORY, $wp_object_cache->optimize_memory = false|true
	 *
	 * @var bool
	 */
	public bool $optimize_memory		= false;

	/**
	 * Disable use of 'alloptions' array.
	 * 70% + of cache hits are for 'alloptions'.
	 * Maybe better to just get the single option(s) from the cache.
	 *
	 * Set with: EAC_OBJECT_CACHE_DISABLE_ALLOPTIONS
	 *
	 * @var bool
	 */
	private bool $disable_alloptions	= false;

	/**
	 * Memory/persistent cache stats.
	 *
	 * @var [string => int]
	 */
	private array $stats_runtime		= array(
		'cache hits'				=> 0,	// total cache hits (memory & db)
		'cache misses'				=> 0,	// total cache misses (memory & db)
		'L1 cache hits'				=> 0,	// cache hits in memory
		'L1 cache (+)'				=> 0,	// in memory with data
		'L1 cache (-)'				=> 0,	// in memory, no data (L2 miss)
		'L1 cache misses'			=> 0,	// cache misses in memory
		'L2 non-persistent'			=> 0,	// not read from L2
		'L2 APCu hits'				=> 0, 	// APCu cache hits
		'L2 APCu (+)'				=> 0,	// in memory with data
		'L2 APCu (-)'				=> 0,	// in memory, no data (L2 miss)
		'L2 APCu misses'			=> 0, 	// APCu cache misses
		'L2 SQL hits'				=> 0,	// cache hits from sqlite
		'L2 SQL misses'				=> 0,	// cache misses from sqlite
		'L2 APCu updates'			=> 0, 	// APCu cache adds
		'L2 APCu deletes'			=> 0, 	// APCu cache deletes
		'L2 SQL selects'			=> 0,	// number of sql selects
		'L2 SQL updates'			=> 0,	// number of records updated
		'L2 SQL deletes'			=> 0,	// number of records deleted
		'L2 SQL commits'			=> 0,	// number of sql transaction commits
		'pre-fetched (+)'			=> 0,	// records pre-fetched by group
		'pre-fetched (-)'			=> 0,	// misses pre-fetched by group
	);

	/**
	 * Cache hits by group ([group => count]).
	 *
	 * @var [string => int]
	 */
	private array $stats_groups			= array();

	/**
	 * Pre-fetch misses by blog ([blog_id => count]).
	 *
	 * @var [int => int]
	 */
	private array $stats_premissed		= array();

	/**
	 * Recommended style for stats html table.
	 *
	 * @var string
	 */
	public string $statsCSS				=
		"table.wp-object-cache th {text-align: left; font-weight: normal;}".
		"table.wp-object-cache th p {font-weight: bold;}".
		"table.wp-object-cache td {text-align: right; padding-left: 1em;}";

	/**
	 * Set time now.
	 *
	 * @var int
	 */
	private int $time_now				= 0;

	/**
	 * Holds the value of is_multisite().
	 *
	 * @var bool
	 */
	private bool $is_multisite			= false;

	/**
	 * The blog prefix to prepend to keys in non-global groups.
	 *
	 * @var int
	 */
	private int $blog_id				= 0;

	/**
	 * The SQLite database object (or false).
	 *
	 * @var bool|object
	 */
	private $use_db 					= true;

	/**
	 * Use APCu memory key prefix (or false).
	 *
	 * @var bool|string
	 */
	private $use_apcu					= true;


	/*
	 * constructor methods
	 */


	/**
	 * Constructor, sets up object properties, APCu, SQLite database.
	 * Called from wp_cache_init();
	 *
	 */
	public function __construct()
	{
		$this->time_now 		= time();
		$this->is_multisite 	= is_multisite();

		$this->get_defined_options();

		$this->use_apcu 		= $this->connect_apcu();
		$this->optimize_memory 	= ($this->optimize_memory && $this->use_apcu);

		$this->use_db 			= $this->connect_sqlite();

		// because these are non-standard methods, nothing outside calls them
		$this->add_permanent_groups();
		$this->add_prefetch_groups();
		$this->add_group_expire();
	}

	/**
	 * Initialize, sets current blog id and WP actions.
	 * Called from wp_cache_init();
	 *
	 */
	public function init()
	{
		if (! $this->is_multisite)
		{
			$this->switch_to_blog( get_current_blog_id() );
		}

		add_action( 'wp_cache_set_last_changed', [$this,'cache_clear_query_groups'] );

		if (is_admin())
		{
			add_action( (is_network_admin() ? 'wp_network_dashboard_setup' :'wp_dashboard_setup'),function()
				{
					wp_add_dashboard_widget( 'object_cache_widget', 'WP Object Cache', [$this,'showDashboardStats'] );
				}
			);

			add_action( 'admin_footer', function()
				{
					if ($this->display_stats && function_exists('is_admin_bar_showing') && is_admin_bar_showing()) {
						$this->showAdminPageStats();
					}
				},PHP_INT_MAX - 100
			);
		}

		add_action( 'shutdown', function()
			{
				// for Query Monitor/eacDoojigger logging
				if ($this->log_stats) {
					$this->logRuntimeStats();
				}
			},8 // before qm (9)
		);
	}


	/**
	 * get defined constants
	 *
	 */
	private function get_defined_options(): void
	{
		foreach(array(
		//	option suffix			validation
			['cache_dir', 			'is_string'],	// cache directory (/wp-content/cache)
			['cache_file', 			'is_string'],	// cache file name (.eac_object_cache.sqlite)
			['journal_mode', 		'is_string'],	// SQLite journal mode	(WAL)
			['mmap_size', 			'is_int'],		// SQLite mapped memory I/O
			['page_size', 			'is_int'],		// SQLite page size
			['cache_size', 			'is_int'],		// SQLite cache size
			['timeout', 			'is_int'],		// PDO timeout (int)
			['max_retries', 		'is_int'],		// database retries (int)
			['delayed_writes',		null],			// delayed writes (true|false|int)
			['default_expire', 		'is_int'],		// default expiration (-1|0|int)
			['prefetch_misses', 	'is_bool'],		// pre-fetch cache misses (bool)
			['probability', 		'is_int'],		// maintenance/sampling probability (int)
			['write_hooks', 		'is_array'],	// hooks that trigger an immediate cache write
			['optimize_memory', 	'is_bool'],		// optimize memory
			['disable_alloptions', 	'is_bool'],		// disable use of 'alloptions' array
			['use_apcu', 			'is_bool'],		// disable APCu
			['use_db', 				'is_bool'],		// disable SQLite db
		) as $option) {
			$this->get_defined_option(...$option);
		}

		$this->cache_dir = trailingslashit($this->cache_dir);

		// actions or filters that trigger an immediate cache write.
		//if (!$this->use_apcu) 	// unnecessary when using APCu
		//{
			$noDelay = function($return=null) {
				$this->set_delayed_writes( false );
				return $return;
			};
			foreach ($this->write_hooks as $hook) {
				add_filter( $hook, fn()=>$noDelay() );
			}
		//}

		if ($this->disable_alloptions)
		{
			// a non-empty array circumvents alloptions - no option is ever found here.
			// @since WP 6.2.0
			add_filter( 'pre_wp_load_alloptions', 			fn()=>[__CLASS__ => true] );
			// empty array means that nothing is ever autoloaded.
			// @since WP 6.6.0
			add_filter( 'wp_autoload_values_to_autoload', 	fn()=>[] );
		}
	}


	/**
	 * get a defined constant
	 *
	 * @param string $var - the defined constant suffix & variable name
	 * @param string $valid - validation callback
	 * @return mixed constant value or null
	 */
	private function get_defined_option(string $var, $is_valid = null)
	{
		$value = null;
		$constant = 'EAC_OBJECT_CACHE_' . strtoupper( $var );
		if (defined( $constant )) {
			$constant = constant($constant);
			$value = (is_callable($is_valid))
				? ( ($is_valid($constant)) ? $constant : null )
				: $constant;
			if (!is_null($value) && isset($this->{$var})) { 	// don't overwrite with null
				$this->{$var} = (is_array($value))
					? array_merge($this->{$var},$value)			// merge with existing array
					: $value;
			}
		}
		return $value;
	}


	/**
	 * Initialize APCu key based on SQLite cache location.
	 *
	 * @reurn string|bool APCu key prefix or false
	 */
	private function connect_apcu()
	{
		$this->use_apcu = ($this->use_apcu && function_exists('apcu_enabled') && apcu_enabled());
		if ( ! $this->use_apcu ) return false;

		$apcu_cache_ids  = apcu_fetch('WPOC_CACHE_IDS') ?: [];

		foreach([
			$this->cache_dir, // prior version used only directory
			$this->cache_dir . $this->cache_file
		] as $cacheName) {
			$apcu_key = array_search($cacheName, $apcu_cache_ids);
			if ($apcu_key !== false) break;
		}

		if ($apcu_key === false) {
			$apcu_cache_ids[] = $cacheName;
			$apcu_key = key($apcu_cache_ids);
			apcu_store('WPOC_CACHE_IDS',$apcu_cache_ids);
		}

		return 'WPOC'.(string)$apcu_key;
	}


	/**
	 * open the SQLite database connection
	 *
	 */
	private function connect_sqlite()
	{
		$this->use_db = ($this->use_db && extension_loaded('pdo_sqlite'));
		if ( ! $this->use_db ) return false;

		$cacheName	= $this->cache_dir . $this->cache_file;
		$install	= !file_exists($cacheName);

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->use_db = new \PDO("sqlite:{$cacheName}",null,null,[
					PDO::ATTR_TIMEOUT				=> $this->timeout,
					PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
				]);
				$this->use_db->exec("
					PRAGMA encoding = 'UTF-8';
					PRAGMA synchronous = NORMAL;
					PRAGMA auto_vacuum = INCREMENTAL;
					PRAGMA journal_mode = {$this->journal_mode};
					PRAGMA mmap_size = {$this->mmap_size};
					PRAGMA page_size = {$this->page_size};
					PRAGMA cache_size = {$this->cache_size};
				");
				break;
			} catch ( Exception $ex ) {
				\error_log(__METHOD__.': '.$ex->getCode().' '.$ex->getMessage());
				$this->use_db = false;
				usleep($this->sleep_time);
			}
		}

		if ($this->use_db && $install) {
			$this->install($cacheName);
		}

		return $this->use_db;
	}


	/**
	 * Clear expired query groups on wp_cache_set_last_changed
	 *
	 * @see https://make.wordpress.org/core/2023/07/17/improvements-to-the-cache-api-in-wordpress-6-3/
	 * @param string $group
	 */
	public function cache_clear_query_groups( $group )
	{
		$cache_group = rtrim($group,'s').'-queries';
		$this->flush_group($cache_group,true);
	}


	/*
	 * compatibility methods
	 */


	/**
	 * Makes private properties readable for backward compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get( $name )
	{
		return $this->$name;
	}


	/**
	 * Makes private properties checkable for backward compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $name )
	{
		return isset( $this->$name );
	}


	/**
	 * Serves as a utility function to determine whether a key is valid.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string $key Cache key to check for validity.
	 * @return bool Whether the key is valid.
	 */
	protected function is_valid_key( $key )
	{
		if ( is_int( $key ) || ( is_string( $key ) && trim( $key ) !== '' ) ) {
			return true;
		}

		$type = gettype( $key );

		if ( ! function_exists( '__' ) ) {
			wp_load_translations_early();
		}

		$message = is_string( $key )
			? __( 'Cache key must not be an empty string.' )
			/* translators: %s: The type of the given cache key. */
			: sprintf( __( 'Cache key must be an integer or a non-empty string, %s given.' ), $type );

		_doing_it_wrong(
			sprintf( '%s::%s', __CLASS__, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ),
			$message,
			'6.1.0'
		);

		return false;
	}


	/**
	 * Are we using the SQLite database
	 *
	 * @return bool
	 */
	public function usingSQLite(): bool
	{
		return (bool) $this->use_db;
	}


	/**
	 * Are we using the APCu cache
	 *
	 * @return bool
	 */
	public function usingAPCu(): bool
	{
		return (bool) $this->use_apcu;
	}


	/*
	 * SQLite select (one / each)
	 */


	/**
	 * Select a single record from the database.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @return array|bool	selected row or false
	 */
	private function select_one( string $blogkey, string $group )
	{
		static $stmt = null;
		$this->select_count = 0;

		if (is_null($stmt)) {
			$stmt = $this->use_db->prepare(
				"SELECT * FROM wp_cache".
				" WHERE key = :key AND (expire = 0 OR expire >= {$this->time_now}) LIMIT 1;"
			);
		}

		// cache not-found, prevents repeated selects
		$this->L1_cache[ $group ][ $blogkey ] = false;

		try {
			$stmt->execute( ['key' => $group.'|'.$blogkey] );
			$this->addRuntimeStats('L2 SQL selects',1);
			if ($row = $stmt->fetch()) {
				$row = $this->select_parse( $row, true );
			}
			$stmt->closeCursor();
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
		}

		return $row;
	}


	/**
	 * Select each record from the database.
	 *
	 * @param array		$blogkeys keys to select (group => [blogkey,...]).
	 * @param bool		$like use 'key like' not 'key in'
	 * @param bool 		$push update memory/APCu cache (default)
	 * @return array|bool	each selected row or false
	 */
	private function select_each( array $blogkeys, bool $like = false, bool $push = true )
	{
		static $cursor = null;

		if (is_null($cursor)) {
			$cursor = $this->select_cursor($blogkeys,$like);
		}

		$row = false;

		if ($cursor) {
			if ($row = $cursor->fetch()) {
				$row = $this->select_parse( $row, $push );
			} else {
				$cursor->closeCursor();
				$cursor = null;
			}
		}

		return $row;
	}


	/**
	 * Execute select for multiple rows, returning cursor for fetch() or fetchAll().
	 *
	 * @param array		$blogkeys keys to select (group => [blogkey,...]).
	 * @param bool		$like use 'key like' not 'key in'
	 * @return object|bool	statement cursor or false
	 */
	private function select_cursor( array $blogkeys, bool $like = false )
	{
		$this->select_count = 0;

		// get the blogkeys that we need to select from L2
		$selectkeys = [];
		foreach ($blogkeys as $group => $keys) {
			foreach ($keys as $blogkey) {
				$selectkeys[] = $group.'|'.$blogkey;
				// cache not-found, prevents repeated selects
				$this->L1_cache[ $group ][ $blogkey ] = false;
			}
		}

		if (empty($selectkeys)) return false;

		$selectkeys = array_unique($selectkeys);

		$where = ($like)
			? substr(str_repeat( 'KEY LIKE ? OR ', count($selectkeys)),0,-4)
			: 'KEY IN (' . substr(str_repeat('?,', count($selectkeys)),0,-1) . ')';

		$stmt = $this->use_db->prepare(
			"SELECT * FROM wp_cache".
			" WHERE {$where} AND (expire = 0 OR expire >= {$this->time_now});"
		);

		try {
			$stmt->execute($selectkeys);
			$this->addRuntimeStats('L2 SQL selects',1);
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
			return false;
		}

		return $stmt;
	}


	/**
	 * Parse key from database row, unserialize value, and add to L1 cache.
	 * sets expire to seconds (not time) and adds to L1 cache.
	 *
	 * @param array 	$data database row (key, value, expire)
	 * @param bool 		$push update memory/APCu cache (default)
	 * @return array row (key, value, expire, group, blog)
	 */
	private function select_parse( array $data, bool $push = true ): array
	{
		if ($row = $this->parse_key("sqlite|".$data['key']))
		{
			$this->select_count++;
			$row['value']	= maybe_unserialize($data['value']);
			$row['expire']	= (!empty($data['expire'])) ? $data['expire'] - $this->time_now : 0;

			if ($push || empty($this->L1_cache[ $row['group'] ][ $row['blogkey'] ]))
			{
				unset($this->L1_cache[ $row['group'] ][ $row['blogkey'] ],
					  $this->L2_cache[ $row['group'] ][ $row['blogkey'] ]);
				// add to the L1 (memory) cache
				if (! $this->optimize_memory) {
					$this->L1_cache[ $row['group'] ][ $row['blogkey'] ] =
						[ 'value'=>$row['value'], 'expire'=>$row['expire'] ];
				}
				// add to the APCu cache - if reading from db must not be in apcu
				if ($apcu_key = $this->get_apcu_key( $row['group'], $row['blogkey'] )) {
					if (apcu_store($apcu_key, [ 'value'=>$row['value'], 'expire'=>$row['expire'] ], $row['expire'])) {
						$this->addRuntimeStats('L2 APCu updates',1);
					} else {
						$this->error_log(__METHOD__,"APCu store failed for key ".$apcu_key,'error');
					}
				}
			}
		}
		return $row;
	}


	/*
	 * API utility methods
	 */


	/**
	 * Parse key from SQL row or APCu iterator.
	 *
	 * @param string 	$key key "prefix|group|blog|key"
	 * @return array  	[prefix, group, blog, key, blogkey]
	 */
	private function parse_key( string $key )
	{
		static $parse_key 	= "/^(?<prefix>.*)\|(?<group>.*)\|(?<blog>\d{5})\|(?<key>.*)$/";

		if (preg_match($parse_key,$key,$parts)) {
			$parts 				= array_filter($parts,'is_string',ARRAY_FILTER_USE_KEY);
			$parts['blogkey']	= $parts['blog'].'|'.$parts['key'];
			return $parts;
		} else {
			$this->error_log(__METHOD__, 'invalid cache key format ['.$key.']','error');
			return false;
		}
	}


	/**
	 * Are we currently using the L2 caches
	 *
	 * @param string	 $group Where to group the cache contents. Default 'default'.
	 * @return bool
	 */
	private function usingL2cache( $group )
	{
		global $_wp_using_ext_object_cache;

		return ($_wp_using_ext_object_cache && ! $this->is_non_persistent_group($group));
	}


	/**
	 * Get the blog id for a group
	 *
	 * @param string	 $group Where to group the cache contents. Default 'default'.
	 * @return int blog id.
	 */
	private function get_blog_id( $group )
	{
		return ( $this->is_multisite && !$this->is_global_group($group) )
			? $this->blog_id
			: 0;
	}


	/**
	 * Get key with blog prefix - blog | key - and verify/set group
	 *
	 * @param int|string $key Cache key to check for validity.
	 * @param string	 $group Where to group the cache contents. Default 'default'.
	 * @return string|bool combined site|key if the key is valid.
	 */
	private function get_blog_key( $key, &$group )
	{
		if (empty( $group )) $group = 'default';

		return ($this->is_valid_key($key))
			? sprintf( "%05d|%s", $this->get_blog_id( $group ), $key )
			: false;
	}


	/**
	 * Get the APCu cache key - prefix | group | blog | key.
	 *
	 * @param string	$group Where to group the cache contents. Default 'default'.
	 * @param string	$blogkey blog id or blog|key (see get_blog_key)
	 * @return string|bool key used for APCu cache
	 */
	private function get_apcu_key( $group=null, $blogkey=null )
	{
		global $_wp_using_ext_object_cache;

		if ($this->use_apcu && $_wp_using_ext_object_cache)
		{
			$apcu_key = $this->use_apcu.'|';
			if ($group) {
				$apcu_key .= $group.'|';
				if (!is_null($blogkey)) {
					$apcu_key .= (is_int($blogkey)) ? sprintf("%05d|", $blogkey) : $blogkey;
				}
			}
			return $apcu_key;
		}
		return false;
	}


	/**
	 * Get from APCu cache
	 *
	 * @param string	$group Where to group the cache contents. Default 'default'.
	 * @param string	$blogkey blog id or blog|key (see get_blog_key)
	 * @return mixed|bool fetch result or false
	 */
	private function apcu_fetch( $group, $blogkey )
	{
		$success = false;
		if ($apcu_key = $this->get_apcu_key($group, $blogkey)) {
			$data = \apcu_fetch($apcu_key,$success);
		}
		return ($success) ? $data : false;
	}


	/**
	 * Determine whether a key exists in the cache, memory or database.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment hits/misses
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	private function key_exists( string $blogkey, string $group, bool $count = false ): bool
	{
		global $_wp_using_ext_object_cache;

		// Does the key exist in the L1 cache?
		$found = $this->key_exists_memory( $blogkey, $group, $count );
		if (is_bool($found)) return $found;

		$this->current = null;

		// non-persistent keys aren't in L2 cache
		if ( $this->is_non_persistent_group($group) ) {
			$this->L1_cache[ $group ][ $blogkey ] = false;
			$this->addRuntimeStats('L2 non-persistent',(int)$count);
			return false;
		}

		// external cache disabled, consider this non-persistent
		if (! $_wp_using_ext_object_cache) {
			$this->addRuntimeStats('L2 non-persistent',(int)$count);
			return false;
		}

		// Does the key exist in the APCu cache?
		$found = $this->key_exists_apcu( $blogkey, $group, $count );
		if (is_bool($found)) return $found;

		// Does the key exist in the SQL cache?
		return $this->key_exists_database( $blogkey, $group, $count );
	}


	/**
	 * Determine whether a key exists in the memory cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment L1 hits/misses
	 * @return bool|null	true = found w/data, false = found no data, null not found
	 */
	private function key_exists_memory( string $blogkey, string $group, bool $count = false ): ?bool
	{
		if (isset( $this->L1_cache[ $group ], $this->L1_cache[ $group ][ $blogkey ] ))
		{
			if (is_object($this->L1_cache[ $group ][ $blogkey ])) {
				$this->current = clone $this->L1_cache[ $group ][ $blogkey ];
			} else {
				$this->current = $this->L1_cache[ $group ][ $blogkey ];
			}
			//if ($group=='options') $group .= ':M:'.$blogkey;
			$this->addRuntimeStats('cache hits',(int)$count,$group);
			$this->addRuntimeStats('L1 cache hits',(int)$count);

			if ($this->current !== false) {
				$this->addRuntimeStats('L1 cache (+)',(int)$count);
				return true;
			} else {
				$this->addRuntimeStats('L1 cache (-)',(int)$count);
				return false;
			}
		}

		$this->addRuntimeStats('L1 cache misses',(int)$count);
		return null;
	}


	/**
	 * Determine whether a key exists in the APCu cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment L1 hits/misses
	 * @return bool|null	true = found w/data, false = found no data, null not found
	 */
	private function key_exists_apcu( string $blogkey, string $group, bool $count = false ): ?bool
	{
		if ( ! $this->use_apcu ) return false;

		if ($data = $this->apcu_fetch($group, $blogkey))
		{
			$this->current = (is_array($data)) ? $data : false;
			if (! $this->optimize_memory) {
				$this->L1_cache[ $group ][ $blogkey ] = $this->current;
			} else {
				unset($this->L1_cache[ $group ][ $blogkey ]);
			}
			//if ($group=='options') $group .= ':A:'.$blogkey;
			$this->addRuntimeStats('cache hits',(int)$count,$group);
			$this->addRuntimeStats('L2 APCu hits',(int)$count);

			if ($this->current !== false) {
				$this->addRuntimeStats('L2 APCu (+)',(int)$count);
				return true;
			} else {
				$this->addRuntimeStats('L2 APCu (-)',(int)$count);
				return false;
			}
		}

		$this->addRuntimeStats('L2 APCu misses',(int)$count);
		return null;
	}


	/**
	 * Determine whether a key exists in the database cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment L2 hits/misses
	 * @return bool Whether the key exists in the db
	 */
	private function key_exists_database( string $blogkey, string $group, bool $count = false ): bool
	{
		if ( ! $this->use_db ) return false;

		if ($row = $this->select_one( $blogkey, $group ))
		{
			$this->current = [ 'value'=>$row['value'], 'expire'=>$row['expire'] ];
			$this->addRuntimeStats('cache hits',(int)$count,$group);
			$this->addRuntimeStats('L2 SQL hits',(int)$count);
			return true;
		}

		// cache SQL misses in APCu
		if ($apcu_key = $this->get_apcu_key( $group, $blogkey ))
		{
			apcu_store($apcu_key, -1, DAY_IN_SECONDS/3);
		}

		$this->addRuntimeStats('L2 SQL misses',(int)$count);
		return false;
	}


	/*
	 * API methods  - outside actors manage cache objects (add/set/get/delete)
	 */


	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @param int|string $key	 What to call the contents in the cache.
	 * @param mixed		 $data	 The contents to store in the cache.
	 * @param string	 $group	 Optional. Where to group the cache contents. Default 'default'.
	 * @param int		 $expire Optional. When to expire the cache contents, in seconds.
	 *							 Default 0 (no expiration).
	 * @return bool True on success, false if cache key and group already exist.
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 )
	{
		if ( ! $blogkey = $this->get_blog_key( $key, $group ) ) return false;
		if ( $this->key_exists( $blogkey, $group, false ) ) return false;

		return $this->set( $key, $data, $group, (int) $expire );
	}


	/**
	 * Adds multiple values to the cache in one call.
	 *
	 * @param array	 $data	 Array of keys and values to be added.
	 * @param string $group	 Optional. Where the cache contents are grouped. Default empty.
	 * @param int	 $expire Optional. When to expire the cache contents, in seconds.
	 *						 Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *				  true on success, or false if cache key and group already exist.
	 */
	public function add_multiple( array $data, $group = '', $expire = 0 )
	{
		$values = array();

		// write all in one transaction
		$this->set_delayed_writes( true );

		foreach ( $data as $key => $value ) {
			$values[ $key ] = $this->add( $key, $value, $group, $expire );
		}

		$this->set_delayed_writes();

		return $values;
	}


	/**
	 * Replaces the contents in the cache, if contents already exist.
	 *
	 * @param int|string $key	 What to call the contents in the cache.
	 * @param mixed		 $data	 The contents to store in the cache.
	 * @param string	 $group	 Optional. Where to group the cache contents. Default 'default'.
	 * @param int		 $expire Optional. When to expire the cache contents, in seconds.
	 *							 Default 0 (no expiration).
	 * @return bool True if contents were replaced, false if original value does not exist.
	 */
	public function replace( $key, $data, $group = 'default', $expire = 0 )
	{
		if ( ! $blogkey = $this->get_blog_key( $key, $group ) ) return false;
		if ( ! $this->key_exists( $blogkey, $group, false ) ) return false;

		return $this->set( $key, $data, $group, (int) $expire );
	}


	/**
	 * Replace multiple values to the cache in one call.
	 *
	 * @param array	 $data	 Array of keys and values to be added.
	 * @param string $group	 Optional. Where the cache contents are grouped. Default empty.
	 * @param int	 $expire Optional. When to expire the cache contents, in seconds.
	 *						 Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *				  true on success, or false if cache key and group already exist.
	 */
	public function replace_multiple( array $data, $group = 'default', $expire = 0 )
	{
		$values = array();

		// write all in one transaction
		$this->set_delayed_writes( true );

		foreach ( $data as $key => $value ) {
			$values[ $key ] = $this->replace( $key, $value, $group, $expire );
		}

		$this->set_delayed_writes();

		return $values;
	}


	/**
	 * Sets the data contents into the cache.
	 *
	 * The cache contents are grouped by the $group parameter followed by the
	 * $key. This allows for duplicate IDs in unique groups. Therefore, naming of
	 * the group should be used with care and should follow normal function
	 * naming guidelines outside of core WordPress usage.
	 *
	 * @param int|string $key	 What to call the contents in the cache.
	 * @param mixed		 $data	 The contents to store in the cache.
	 * @param string	 $group	 Optional. Where to group the cache contents. Default 'default'.
	 * @param int		 $expire Optional.
	 * @return bool True if contents were set, false if key is invalid.
	 */
	public function set( $key, $data, $group = 'default', $expire = 0 )
	{
		if ( is_null($data) ) return false;
		if ( ! $blogkey = $this->get_blog_key( $key, $group ) ) return false;

		if ( wp_suspend_cache_addition() ) {
			if ( ! $this->key_exists( $blogkey, $group, false ) ) return false;
		}

		// set default expiration time - transients (perm_groups) don't expire unless explicitly set
		$expire = (!empty($expire))
			? (int)$expire
			: (int)($this->is_permanent_group($group) ? 0 : ($this->group_expire[$group] ?? $this->default_expire));

		// value has not changed
		if ($this->key_exists_memory( $blogkey, $group, false )
			&& ($data	=== $this->current[ 'value' ])
			&& ($expire === $this->current[ 'expire' ])
		) {
			return false;
		}

		if ( is_object( $data ) ) $data = clone $data;
		$this->L1_cache[ $group ][ $blogkey ] = $this->current = [ 'value' => $data, 'expire' => $expire ];

		// when not to write to db
		if ( $expire < 0 || ! $this->usingL2cache($group) ) {
			return true;
		}

		// add the value to APCu
		if ($apcu_key = $this->get_apcu_key( $group, $blogkey ))
		{
			if (apcu_store($apcu_key, $this->current, $expire)) {
				if ($key != 'alloptions' && $key != 'notoptions') { // repeatedly updated
					$this->addRuntimeStats('L2 APCu updates',1);
				}
			} else {
				$this->error_log(__METHOD__,"APCu store failed for key ".$apcu_key,'error');
			}
		}

		// add the value to SQLite
		if ($this->use_db)
		{
			$this->L2_cache[ $group ][ $blogkey ] = $expire;
			$this->maybe_write_cache();
		}

		$this->current = null;
		return true;
	}


	/**
	 * Sets multiple values to the cache in one call.
	 *
	 * @param array	 $data	 Array of key and value to be set.
	 * @param string $group	 Optional. Where the cache contents are grouped. Default empty.
	 * @param int	 $expire Optional. When to expire the cache contents, in seconds.
	 *						 Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key.
	 */
	public function set_multiple( array $data, $group = '', $expire = 0 )
	{
		$values = array();

		// write all in one transaction
		$this->set_delayed_writes( true );

		foreach ( $data as $key => $value ) {
			$values[ $key ] = $this->set( $key, $value, $group, $expire );
		}

		$this->set_delayed_writes();

		return $values;
	}


	/**
	 * Retrieves the cache contents, if it exists. - wp_cache_get().
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * key in the cache group. If the cache is hit (success) then the contents
	 * are returned.
	 *
	 * On failure, the number of cache misses will be incremented.
	 *
	 * @param int|string $key	The key under which the cache contents are stored.
	 * @param string	 $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool		 $force Optional. Whether to force an update of the local cache
	 *							from the persistent cache. Default false.
	 * @param bool		 $found Optional. Whether the key was found in the cache (passed by reference).
	 *							Disambiguates a return of false, a storable value. Default null.
	 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null )
	{
		if ($force && $this->usingL2cache($group)) {
		// $force read from L2 cache, assuming L2 is the most recent, maybe updated by another process.
		// Used by 'alloptions' when adding/removing a single option and _get_cron_lock() in wp-cron process.
			if ($blogkey = $this->get_blog_key( $key, $group )) {
				if (isset( $this->L1_cache[ $group ], $this->L1_cache[ $group ][ $blogkey ] )) {
					unset($this->L1_cache[ $group ][ $blogkey ]);
				}
			}
		}

		$result = $this->get_cache($key, $group, true);
		if ($result !== false) {
			$found = true;
			return $result;
		}

		// maybe import transient from MySQL
		if (!defined('EAC_OBJECT_CACHE_DISABLE_TRANSIENT_IMPORT')) {
			if ($group == 'transient') {
				if ($result = $this->get_transient($key)) {
					$found = true;
					return $result;
				}
			} else
			if ($group == 'site-transient') {
				if ($result = $this->get_site_transient($key)) {
					$found = true;
					return $result;
				}
			}
		}

		$found = false;
		return false;
	}


	/**
	 * Internal get_cache - get from L1 or L2 cache, with (get) or without (internal) counting hit/miss.
	 *
	 * @param int|string $key	The key under which the cache contents are stored.
	 * @param string	 $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool		 $count Optional. Whether to add to the cache counts.
	 */
	protected function get_cache( $key, $group = 'default', $count = false)
	{
		if ( ! $blogkey = $this->get_blog_key( $key, $group ) ) return false;

		if ($this->key_exists( $blogkey, $group, $count )) {
			return $this->current[ 'value' ] ?? false;
		} else {
			$this->addRuntimeStats('cache misses',(int)$count);
			return false;
		}
	}


	/**
	 * Retrieves multiple values from the cache in one call.
	 *
	 * @param array	 $keys	Array of keys under which the cache contents are stored.
	 * @param string $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool	 $force Optional. Whether to force an update of the local cache
	 *						from the persistent cache. Default false.
	 * @return array Array of return values, grouped by key. Each value is either
	 *				 the cache contents on success, or false on failure.
	 */
	public function get_multiple( array $keys, $group = 'default', $force = false )
	{
		// fill array [key => false]
		$values = array_fill_keys( (array)$keys, false );

		if (empty( $group )) $group = 'default';

		$blogkeys		= [];	// keys to select from db
		$selects		= 0; 	// count selects
		$use_l2_cache 	= $this->usingL2cache($group);


		// get from L1 memory or L2 APCu cache first
		foreach ( $values as $key => &$value ) {
			if ( $blogkey = $this->get_blog_key( $key, $group ) ) {
				$blogkeys[ $group ][$key] = $blogkey; // to be selected
				if (! $force && is_bool($this->key_exists_memory($blogkey, $group, true))) {
					$value = $this->current[ 'value' ] ?? false;
					unset($blogkeys[ $group ][$key]);
				} else if ( $use_l2_cache ) {
					if (is_bool($this->key_exists_apcu($blogkey, $group, true))) {
						$value = $this->current[ 'value' ] ?? false;
						unset($blogkeys[ $group ][$key]);
					}
				}
			}
		}

		// get from L2 SQLite cache
		$selects = count($blogkeys,COUNT_RECURSIVE) - count($blogkeys);
		if ( $use_l2_cache ) {
			if ( $this->use_db && $selects > 0 ) {
				if (!empty($blogkeys)) {
					while ($row = $this->select_each($blogkeys,true,false)) {
						$values[ $row['key'] ] = $row['value'];
					}
					$this->addRuntimeStats('cache hits',$this->select_count,$group);
					$this->addRuntimeStats('L2 SQL hits',$this->select_count);
					$this->addRuntimeStats('L2 SQL misses',$selects - $this->select_count);
				}
			}
		} else {
			$this->addRuntimeStats('L2 non-persistent',$selects);
		}

		$this->addRuntimeStats('cache misses',count( array_filter($values, fn($v) => $v === false) ));
		$this->current = null;
		return $values;
	}


	/**
	 * Retrieves all values for a group from the cache in one call.
	 *
	 * @param string $group Where the cache contents are grouped. Default 'default'.
	 * @return array Array of return values, grouped by key.
	 */
	public function get_group( string $group )
	{
		$found 		= [];

		if (isset($this->L1_cache[ $group ])) {
			// get from L1 memory cache first
			$blog_id 	= sprintf('%05d|',$this->get_blog_id($group));
			foreach($this->L1_cache[ $group ] as $blogkey => $data) {
				if (str_starts_with($blogkey,$blog_id) && is_array($data)) {
					$blogkey = substr($blogkey,6);
					$found[ $blogkey ] = $data['value'];
					$this->addRuntimeStats('L1 cache hits',1);
					$this->addRuntimeStats('L1 cache (+)',1);
				}
			}
		}

		if ( $this->usingL2cache($group) ) {
			// get from L2 APCu cache
			if ($apcu_key = $this->get_apcu_key( $group, $this->get_blog_id($group) )) {
				$keys = new APCUIterator('/^'.preg_quote($apcu_key,'/').'/');
				foreach( $keys as $data ) { // key,value[value,expire]
					$row = $this->parse_key($data['key']);
					if (!isset($found[ $row['key'] ]) && is_array($data['value'])) {
						$found[ $row['key'] ] = $data['value']['value'];
						$this->addRuntimeStats('L2 APCu hits',1);
						if (! $this->optimize_memory) {
							$this->L1_cache[ $group ][ $row['blogkey'] ] = $data['value'];
						}
					}
				}
			}

			// get from L2 SQLite cache
			if ( $this->use_db ) {
				$blogkeys = [ $group => [$this->get_blog_key('%',$group)] ];
				while ($row = $this->select_each($blogkeys,true,false)) {
					if (!isset($found[ $row['key'] ])) {
						$found[ $row['key'] ] = $row['value'];
						$this->addRuntimeStats('L2 SQL hits',1);
					}
				}
			}
		}

		if (empty($found)) {
			$this->addRuntimeStats('cache misses',1);
		} else {
			$this->addRuntimeStats('cache hits',count($found),$group);
		}
		return $found;
	}


	/**
	 * Removes the contents of the cache key in the group.
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @param int|string $key		 What the contents in the cache are called.
	 * @param string	 $group		 Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool		 $deprecated Optional. Unused. Default false.
	 * @return bool True on success, false if the contents were not deleted.
	 */
	public function delete( $key, $group = 'default', $deprecated = false )
	{
		if ( ! $blogkey = $this->get_blog_key( $key, $group ) ) return false;
		//if ( ! $this->key_exists( $blogkey, $group, false ) ) return false;

		// set, not in persistent cache
		$this->L1_cache[ $group ][ $blogkey ] = false;

		// when not to write to db
		if ( !$this->usingL2cache($group) ) {
			return true;
		}

		if ($apcu_key = $this->get_apcu_key( $group, $blogkey )) {
			if (apcu_delete($apcu_key)) {
				$this->addRuntimeStats('L2 APCu deletes',1);
			}
		}

		if ($this->use_db) {
			$this->L2_cache[ $group ][ $blogkey ] = false;	// to be deleted from table
			$this->maybe_write_cache();
		}

		return true;
	}


	/**
	 * Deletes multiple values from the cache in one call.
	 *
	 * @param array	 $keys	Array of keys to be deleted.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *				  true on success, or false if the contents were not deleted.
	 */
	public function delete_multiple( array $keys, $group = '' )
	{
		$values = array();

		// delete all in one transaction
		$this->set_delayed_writes( true );

		foreach ( $keys as $key ) {
			$values[ $key ] = $this->delete( $key, $group );
		}

		$this->set_delayed_writes();

		return $values;
	}


	/**
	 * Deletes all keys for a group in one call.
	 * flush_group() is immediate, delete_group() fetches rows and caches deletes.
	 *
	 * @param string $group Where the cache contents are grouped.
	 * @return array Array of return values, grouped by key.
	 */
	public function delete_group( string $group )
	{
		$rows = $this->get_group($group);
		return $this->delete_multiple(array_keys($rows), $group);
	}


	/**
	 * Increments numeric cache item's value.
	 *
	 * @param int|string $key	 The cache key to increment.
	 * @param int		 $offset Optional. The amount by which to increment the item's value.
	 *							 Default 1.
	 * @param string	 $group	 Optional. The group the key is in. Default 'default'.
	 * @return int|false The item's new value on success, false on failure.
	 */
	public function incr( $key, $offset = 1, $group = 'default' )
	{
		if ( ! $blogkey = $this->get_blog_key( $key, $group ) ) return false;

		$value = max( 0, ( (int) $this->get( $key, $group ) + (int) $offset ) );

		$expire = $this->current[ 'expire' ] ?? 0;

		$this->set( $key, $value, $group, $expire );
		return $value;
	}


	/**
	 * Decrements numeric cache item's value.
	 *
	 * @param int|string $key	 The cache key to decrement.
	 * @param int		 $offset Optional. The amount by which to decrement the item's value.
	 *							 Default 1.
	 * @param string	 $group	 Optional. The group the key is in. Default 'default'.
	 * @return int|false The item's new value on success, false on failure.
	 */
	public function decr( $key, $offset = 1, $group = 'default' )
	{
		return $this->incr( $key, - $offset, $group );
	}


	/*
	 * API methods - flush cache
	 */


	/**
	 * Is flushing enabled (allowed)
	 *
	 * @param string $type null | runtime | group | blog | full
	 * @return bool sql result
	 */
	protected function is_flush_enabled( $type=null ): bool
	{
		if ($this->get_defined_option("disable_flush", 'is_bool')) {
			return false;
		}
		return (!empty($type))
			? ! (bool)$this->get_defined_option("disable_{$type}_flush", 'is_bool')
			: true;
	}


	/**
	 * Clears the object cache of all data.
	 *
	 * @param bool $force override disabling constant.
	 * @return bool sql result
	 */
	public function flush(bool $force = false): bool
	{
		if (! $force && ! $this->is_flush_enabled('full')) return false;

		// clear L1 memory cache
		$this->L1_cache = $this->L2_cache = array();

		if ( ! wp_using_ext_object_cache() ) return true;

		// clear L2 APCu cache
		$this->delete_cache_APCu();
		// clear L2 SQLite cache
		$this->delete_cache_file();
		// reconnect SQLite
		$this->connect_sqlite();
		return true;
	}


	/**
	 * Removes all cache items in a group.
	 *
	 * @param string $group Name of group to remove from cache.
	 * @param bool $force override disabling constant.
	 * @return bool sql result
	 */
	public function flush_group( string $group, bool $force = false ): bool
	{
		static $stmt = null;

		if (! $force && ! $this->is_flush_enabled('group')) return false;

		// clear L1 memory cache
		unset( $this->L1_cache[ $group ], $this->L2_cache[ $group ] );

		if ( ! wp_using_ext_object_cache() ) return true;

		// clear L2 APCu cache
		if ($apcu_key = $this->get_apcu_key( $group, $this->get_blog_id($group) )) {
			$keys = new APCUIterator('/^'.preg_quote($apcu_key,'/').'/');
			if ($count = $keys->getTotalCount()) {
				apcu_delete( $keys );
				$this->error_log(__CLASS__,"APCu cache flushed for group: {$group}, {$count} records deleted");
				$this->addRuntimeStats('L2 APCu deletes',$count);
			}
		}

		if ( ! $this->use_db ) return true;

		if (is_null($stmt)) {
			$stmt = $this->use_db->prepare("DELETE FROM wp_cache WHERE key LIKE :group;");
		}

		// clear L2 SQLite cache
		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$blogkey = $this->get_blog_key('%',$group);
				$this->use_db->beginTransaction();
				$stmt->execute( ['group' => $group.'|'.$blogkey] );
				if ($count = $stmt->rowCount()) {
					$this->error_log(__CLASS__,"SQL cache flushed for group: {$group}, {$count} records deleted");
					$this->addRuntimeStats('L2 SQL deletes',$count);
					$this->addRuntimeStats('L2 SQL commits',1);
				}
				$this->use_db->commit();
				break;
			} catch ( Exception $ex ) {
				$this->use_db->rollBack();
				$this->error_log(__METHOD__,$ex);
				usleep($this->sleep_time);
			}
		}

		return (bool)$stmt;
	}


	/**
	 * Removes all cache items tagged with a blog number.
	 *
	 * @param int $blog current blog
	 * @param bool $force override disabling constant.
	 * @return bool sql result
	 */
	public function flush_blog( $blog_id = null, bool $force = false ): bool
	{
		static $stmt = null;

		if (! $force && ! $this->is_flush_enabled('blog')) return false;
		if (! $this->is_multisite) return $this->flush();
		if (! empty($this->L2_cache)) $this->write_cache();
		if (!is_int($blog_id)) $blog_id = $this->blog_id;

		// clear L1 memory cache
		foreach ($this->L1_cache as $group => $keys) {
			$this->L1_cache[$group] = array_filter($keys,
				fn($v,$k) => $v === false || !str_starts_with($k,sprintf("%05d|",$blog_id)),ARRAY_FILTER_USE_BOTH);
		}

		if ( ! wp_using_ext_object_cache() ) return true;

		// clear L2 APCu cache
		if ($apcu_key = $this->get_apcu_key()) {
			$apcu_key = preg_quote($apcu_key) . '.+' . preg_quote(sprintf("|%05d|", $blog_id));
			$keys = new APCUIterator('/^'.$apcu_key.'/');
			if ($count = $keys->getTotalCount()) {
				apcu_delete( $keys );
				$this->error_log(__CLASS__,"APCu cache flushed for site: {$blog_id}, {$count} records deleted");
				$this->addRuntimeStats('L2 APCu deletes',$count);
			}
		}

		if ( ! $this->use_db ) return true;

		if (is_null($stmt)) {
			$stmt = $this->use_db->prepare("DELETE FROM wp_cache WHERE key LIKE :blog;");
		}

		// clear L2 SQLite cache
		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->use_db->beginTransaction();
				$stmt->execute( ['blog' => '%|'.sprintf("%05d", $blog_id).'|%'] );
				if ($count = $stmt->rowCount()) {
					$this->error_log(__CLASS__,"SQL cache flushed for site: {$blog_id}, {$count} records deleted");
					$this->addRuntimeStats('L2 SQL deletes',$count);
					$this->addRuntimeStats('L2 SQL commits',1);
				}
				$this->use_db->commit();
				break;
			} catch ( Exception $ex ) {
				$this->use_db->rollBack();
				$this->error_log(__METHOD__,$ex);
				usleep($this->sleep_time);
			}
		}

		return (bool)$stmt;
	}


	/**
	 * Clears the L1 cache of all data.
	 * Intended for use by long-running tasks to invalidate the memory cache
	 * without invalidating the persistent cache.
	 *
	 * @param bool $force (internal) override disabling constant and write to persistent cache before clearing.
	 * @return bool Always returns true.
	 */
	public function flush_runtime(bool $force = false): bool
	{
		if (! $force && ! $this->is_flush_enabled('runtime')) return false;

		if ($force) { // internal, writes misses & cache
			if (! empty($this->L2_cache)) $this->write_cache();
		} else if ($this->use_apcu) {
			// clear APCu cache of current keys
			$apcuKeys = [];
			foreach($this->L1_cache as $group => $keys) {
				foreach($keys as $blogkey => $data) {
					if ($data && ($apcu_key = $this->get_apcu_key($group,$blogkey))) {
						$apcuKeys[] = $apcu_key;
					}
				}
			}
			if (!empty($apcuKeys)) {
				apcu_delete($apcuKeys);
				$this->addRuntimeStats('L2 APCu deletes',count($apcuKeys));
			}
		}

		// clear L1 memory cache
		$this->L1_cache = $this->L2_cache = array();

		return true;
	}


	/*
	 * API methods - manage groups
	 */


	/**
	 * Sets the list of global/site-wide cache groups.
	 * from wp-includes/load.php wp_start_object_cache()
	 *
	 * @param string|string[] $groups List of groups that are global.
	 * @return array [group=>true,...]
	 */
	public function add_global_groups( $groups = [] ): array
	{
		// EAC_OBJECT_CACHE_GLOBAL_GROUPS
		$defined_groups = $this->get_defined_groups('global',(array)$groups);
		$this->global_groups = array_merge( $this->global_groups, $defined_groups );

		return $this->global_groups;
	}


	/**
	 * Is group a global (site-wide) group.
	 *
	 * @param string $group group name
	 * @return bool
	 */
	public function is_global_group( $group ): bool
	{
		return isset($this->global_groups[ $group ]) || str_ends_with($group,':sitewide');
	}


	/**
	 * Sets the list of non-persistent cache groups.
	 * from wp-includes/load.php wp_start_object_cache()
	 *
	 * @param string|string[] $groups List of groups that are non-persistent.
	 * @return array [group=>true,...]
	 */
	public function add_non_persistent_groups( $groups = [] ): array
	{
		// EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS
		$defined_groups = $this->get_defined_groups('non_persistent',(array)$groups);
		$this->nonp_groups = array_merge( $this->nonp_groups, $defined_groups );

		return $this->nonp_groups;
	}


	/**
	 * Is group a non-persistent group.
	 *
	 * @param string $group group name
	 * @return bool
	 */
	public function is_non_persistent_group( $group ): bool
	{
		return isset($this->nonp_groups[ $group ]) || str_ends_with($group,':nocaching');
	}


	/**
	 * Sets the list of permanent cache groups.
	 *
	 * @param string|string[] $groups List of groups that can be permanent.
	 * @return array [group=>true,...]
	 */
	public function add_permanent_groups( $groups = [] ): array
	{
		// EAC_OBJECT_CACHE_PERMANENT_GROUPS
		$defined_groups = $this->get_defined_groups('permanent',(array)$groups);
		$this->perm_groups = array_merge( $this->perm_groups, $defined_groups );

		return $this->perm_groups;
	}


	/**
	 * Is group a permanent group.
	 *
	 * @param string $group group name
	 * @return bool
	 */
	public function is_permanent_group( $group ): bool
	{
		return isset($this->perm_groups[ $group ]) || str_ends_with($group,':permanent');
	}


	/**
	 * Sets the list of pre-loaded groups.
	 *
	 * @param string|string[] $groups List of groups that are pre-loaded.
	 * @return array [group=>true,...]
	 */
	public function add_prefetch_groups( $groups = [] ): array
	{
		// EAC_OBJECT_CACHE_PREFETCH_GROUPS
		$defined_groups = $this->get_defined_groups('prefetch',(array)$groups);
		$this->prefetch_groups = array_merge( $this->prefetch_groups, $defined_groups );

		$this->set('prefetch_groups', $this->prefetch_groups, self::GROUP_ID, 0);
		return $this->prefetch_groups;
	}


	/**
	 * Is group a prefetch group
	 *
	 * @param string $group group name
	 * @return bool
	 */
	public function is_prefetch_group( $group ): bool
	{
		return !$this->use_apcu && (isset($this->prefetch_groups[ $group ]) || str_ends_with($group,':prefetch'));
	}


	/**
	 * Sets the list of group expirations.
	 *
	 * @param array $groups [group => expires]
	 * @return array [group=>expires,...]
	 */
	public function add_group_expire( $groups = [] ): array
	{
		// EAC_OBJECT_CACHE_GROUP_EXPIRE
		$defined_groups = $this->get_defined_option("group_expire", 'is_array') ?? [];

		$this->group_expire = array_merge( $this->group_expire, $defined_groups, (array)$groups );

		return $this->group_expire;
	}


	/**
	 * Get group keys as array from constant.
	 *
	 * @param string $constant unique part of group constant name
	 * @return array [group=>true,...]
	 */
	private function get_defined_groups( string $constant, array $addGroups = [] ): array
	{
		$groups = [];
		if ($constant = $this->get_defined_option("{$constant}_groups", 'is_array')) {
		//	if ( !empty( $constant ) ) {
		//		$groups = array_fill_keys( $constant, true );
		//	}
			$addGroups = array_merge($addGroups,$constant);
		}
		foreach ($addGroups as $key => $value) {
			if (is_int($key)) {
				$groups[$value] = true;
			} else {
				$groups[$key] = $value;
			}
		}
		return $groups;
	}


	/**
	 * pre-fetch existing group.
	 * called after add_global_groups(),add_non_persistent_groups() with EAC_OBJECT_CACHE_PREFETCH_GROUPS
	 *
	 */
	private function load_prefetch_groups(): void
	{
		if ( $this->use_db && ! $this->use_apcu )
		{
			$blogkeys = [];
			$this->prefetch_groups = $this->get_cache('prefetch_groups',self::GROUP_ID) ?: [];
			foreach ($this->prefetch_groups as $group => $key) {
				if (! $this->is_non_persistent_group($group) ) {
					if (is_array($key)) {
						foreach ($key as $k) {
							$blogkeys[ $group ][] = $this->get_blog_key($k, $group);
						}
					} else {
						$blogkeys[ $group ][] = $this->get_blog_key((is_bool($key)) ? '%' : $key, $group);
					}
				}
			}

			if (!empty($blogkeys)) {
				while ($this->select_each( $blogkeys, true )) {}
				$this->addRuntimeStats("pre-fetched (+)",$this->select_count);
			}
		}
		/* - results in potentially stale data and is antithetical to optimize_memory.
		else if ($this->optimize_memory)
		{
			// 'notoptions' option is requested thousands of times, preload to L1 cache
			foreach (['options','site-options'] as $group) {
				$value 		= $this->get_cache('notoptions',$group) ?: [];
				$blogkey 	= $this->get_blog_key('notoptions',$group);
				$this->L1_cache[ $group ][ $blogkey ] = [ 'value' => $value, 'expire' => 0 ];
			}
		}
		*/
	}


	/**
	 * Load cached misses.
	 * When we set/switch blog.
	 *
	 */
	private function load_cache_misses(): void
	{
		if (! $this->prefetch_misses || ! $this->use_db || $this->use_apcu) return;

		$group 		= self::GROUP_ID;
		$blog_id 	= $this->get_blog_id('*');

		// load (or re-load) prior cache misses into L1 cache
		if ($misses = $this->get_cache('cache-misses',$group)) {
			$this->stats_premissed[$blog_id] = 0;
			foreach ($misses as $group => $keys) {
				if (!isset( $this->L1_cache[ $group ] )) $this->L1_cache[ $group ] = [];
				$keys = array_diff_key($keys, $this->L1_cache[ $group ]);
				$this->L1_cache[ $group ] = array_merge($keys, $this->L1_cache[ $group ]);
				$this->stats_premissed[$blog_id] += count($keys);
			}
		}
		// remove from L1 cache
		$blogkey = $this->get_blog_key('cache-misses',$group);
		unset( $this->L1_cache[ $group ][ $blogkey ] );
	}


	/**
	 * Save cached misses.
	 * When we write the cache (switch blog).
	 *
	 */
	private function save_cache_misses(): void
	{
		if (! $this->prefetch_misses  || ! $this->use_db || $this->use_apcu || empty($this->L1_cache)) return;

		$misses = array();
		foreach ($this->L1_cache as $group => $keys) {
			if ($keys = array_filter( $keys, fn($v) => $v === false )) {
				$misses[$group] = $keys;
			}
		}
		$this->set('cache-misses', $misses, self::GROUP_ID, HOUR_IN_SECONDS);
	}


	/*
	 * API methods - multisite
	 */


	/**
	 * Switches the internal blog ID.
	 *
	 * This changes the blog ID used to create keys in blog specific groups.
	 *
	 * @param int $blog_id Blog ID.
	 */
	public function switch_to_blog( $blog_id )
	{
		if ($this->is_multisite) {
			$this->flush_runtime(true);
			$this->addRuntimeStats("blog switches",1);
		}
		$this->blog_id = (int)$blog_id;
		$this->load_prefetch_groups();
		$this->load_cache_misses();
	}


	/**
	 * Resets cache keys.
	 *
	 * @deprecated 3.5.0 Use WP_Object_Cache::switch_to_blog()
	 * @see switch_to_blog()
	 */
	public function reset()
	{
		$this->switch_to_blog( get_current_blog_id() );
	}


	/*
	 * API methods - close
	 */


	/**
	 * Close the persistent cache (wp_cache_close)
	 *
	 */
	public function close(): void
	{
		// count requests (since last close)
		if ($this->is_actionable()) {
			$site_requests 		= $this->incr('site-requests',1,self::GROUP_ID);
			$network_requests 	= ($this->is_multisite)
				? $this->incr('network-requests',1,self::GROUP_ID_GLOBAL)
				: $site_requests;
		}

		// maintenance functions (every n requests)
		if ($this->is_actionable() && $this->probability >= 10)
		{
			$this->probability = $this->probability + ($this->probability % 2); // even number
			$probability = ($network_requests % $this->probability);

			if ($this->use_db  && $probability == 0)							//	checkpoint/optimize
			{
				$limit = ((int)$this->delayed_writes >= 10) ? $this->delayed_writes : 32;
				$this->optimize(false, $limit);
			}
		}

		if ($this->is_actionable() && $this->display_stats)
		{
			$probability = ($site_requests % $this->display_stats);

			if ($probability == 0)												// sampling
			{
				$this->set('sample', $this->getCurrentStats(true), self::GROUP_ID, DAY_IN_SECONDS);
			}
		}

		// write the cached records
		$this->write_cache();

		$this->use_db = false;
	}


	/**
	 * Is a PHP request (not ajax, not image, etc.)
	 *
	 */
	private function is_actionable()
	{
		static $is_actionable = null;

		if (is_null($is_actionable))
		{
			$is_actionable = true;

			if (wp_doing_ajax() || wp_doing_cron() ||
				(isset($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
				 $_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest"))
			{
				$is_actionable = false;
			}
			else if (isset($_SERVER["REQUEST_URI"]))
			{
				$ext = explode('?',$_SERVER['REQUEST_URI']);
				$ext = pathinfo(trim($ext[0],'/'),PATHINFO_EXTENSION);
				if (!empty($ext) && $ext != 'php') $is_actionable = false;
			}
		}
		return $is_actionable;
	}


	/*
	 * internal methods
	 */


	/**
	 * Temporarily set or reset delayed_writes.
	 *
	 * @param bool|int $set new value
	 */
	private function set_delayed_writes($set=null)
	{
		static $delayed_writes = false;

		if (is_null($set)) {		// reset to prior value
			$this->delayed_writes	= $delayed_writes;
		} else {					// set to number of adds/deletes or true
			$delayed_writes			= $this->delayed_writes;
			$this->delayed_writes	= $set;
		}
		$this->maybe_write_cache();
	}


	/**
	 * Write the persistent cache to disk when db cache is full.
	 *
	 */
	private function maybe_write_cache(): bool
	{
		switch ( (int)$this->delayed_writes ) {
			case 0:		// not delayed
				return (bool)$this->write_cache();
			case 1:		// unlimited
				return false;
			default:
				$pending = $this->pending_writes();
				return ($pending >= (int)$this->delayed_writes)
					? (bool)$this->write_cache()
					: false;
		}
	}


	/**
	 * Get the number of records waiting to be updated or deleted.
	 *
	 */
	private function pending_writes(): int
	{
		return count($this->L2_cache,COUNT_RECURSIVE) - count($this->L2_cache);
	}


	/**
	 * Write the persistent cache to disk.
	 *
	 */
	private function write_cache(): bool
	{
		// find and cache all cache misses - not in sqlite db
		$this->save_cache_misses();

		if (! $this->use_db || empty($this->L2_cache)) {
			return false;
		}

		$write = $delete = array();

		// find all writes and deletes
		foreach ($this->L2_cache as $group => $updates) {
			foreach ($updates as $blogkey => $expire) {
				if ($expire !== false) {
					$data = $this->L1_cache[ $group ][ $blogkey ][ 'value' ] ?? null;
					if (is_null($data)) {
						if ($data = $this->apcu_fetch($group, $blogkey)) $data = $data[ 'value' ] ?? null;
					}
					if (is_null($data)) {
						$this->error_log(__METHOD__,"SQL write no data for key ".$group.'|'.$blogkey);
						continue;
					}
					$write[] = array(
							$group.'|'.$blogkey,
							maybe_serialize($data),
							( ($expire) ? $this->time_now+$expire : 0 )
					);
				} else {
					$delete[] = $group.'|'.$blogkey;
				}
			}
		}

		$this->L2_cache = array();

		// insert/update records
		if (!empty($write)) {
			$stmt = $this->use_db->prepare(
				"INSERT INTO wp_cache (key, value, expire)" .
				" VALUES ".rtrim(str_repeat("(?,?,?),", count($write)),',') .
				" ON CONFLICT (key) DO UPDATE".
				" SET value = excluded.value, expire = excluded.expire;"
			);
			$retries = 0;
			while ( ++$retries <= $this->max_retries ) {
				try {
					$this->use_db->beginTransaction();
					$stmt->execute(array_merge(...$write));
					$this->addRuntimeStats('L2 SQL updates',$stmt->rowCount());
					$this->use_db->commit();
					$this->addRuntimeStats('L2 SQL commits',1);
					break;
				} catch ( Exception $ex ) {
					$this->use_db->rollBack();
					$this->error_log(__METHOD__,$ex);
					usleep($this->sleep_time);
				}
			}
		}

		// delete records
		if (!empty($delete)) {
			$stmt = $this->use_db->prepare(
				"DELETE FROM wp_cache".
				" WHERE key in (".rtrim(str_repeat("?,", count($delete)),',').")"
			);
			$retries = 0;
			while ( ++$retries <= $this->max_retries ) {
				try {
					$this->use_db->beginTransaction();
					$stmt->execute($delete);
					$this->addRuntimeStats('L2 SQL deletes',$stmt->rowCount());
					$this->use_db->commit();
					$this->addRuntimeStats('L2 SQL commits',1);
					break;
				} catch ( Exception $ex ) {
					$this->use_db->rollBack();
					$this->error_log(__METHOD__,$ex);
					usleep($this->sleep_time);
				}
			}
		}

		return true;
	}


	/**
	 * Delete the L2 APCu cache.
	 *
	 */
	public function delete_cache_APCu(): void
	{
		if ($apcu_key = $this->get_apcu_key()) {
			$keys = new APCUIterator('/^'.preg_quote($apcu_key,'/').'/');
			apcu_delete( $keys );
			$this->error_log(__CLASS__,"APCu cache deleted");
		}
	}


	/**
	 * Delete the L2 cache file(s).
	 * Since we load early, WP_Filesystem is probably not available
	 *
	 */
	public function delete_cache_file(): void
	{
		if ($this->use_db) {
			$cacheName = $this->cache_dir . $this->cache_file;
			foreach ( [ '', '-journal', '-shm', '-wal' ] as $ext ) {
				if (is_file($cacheName.$ext)) {
					unlink($cacheName.$ext);
				}
			}
			$this->error_log(__CLASS__,"SQLite cache deleted");
			$this->use_db = false;
		}
	}


	/**
	 * Callable function to vacuum/optimize database.
	 * schedule with delete_expired_transients or daily task
	 *
	 */
	public function optimize(bool $full=true, int $limit=10000): void
	{
		if (! $this->use_db ) return;

		$start_time = microtime( true );

		$optimize = ($full) ? "VACUUM;" : "PRAGMA incremental_vacuum;";
		$optimize = "PRAGMA wal_checkpoint(RESTART);" .
					$optimize .
					"PRAGMA optimize;";

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->use_db->beginTransaction();
				$count = $this->use_db->exec("
					DELETE FROM wp_cache WHERE expire > 0 AND expire < {$this->time_now} LIMIT {$limit};
				");
				$this->use_db->commit();
				// cannot VACUUM from within a transaction
				$this->use_db->exec($optimize);
				$message = sprintf("SQLite optimization deleted %d rows (%01.4f)", $count, microtime( true ) - $start_time);
				$this->error_log(__CLASS__,$message);
				break;
			} catch ( Exception $ex ) {
				$this->use_db->rollback();
				$this->error_log(__METHOD__,$ex);
				usleep($this->sleep_time);
			}
		}
	}


	/**
	 * Error logging.
	 *
	 * Writes and/or displays error message.
	 *
	 * @param string $source (function)
	 * @param string|object $message message string or exception object
	 * @param string $class error level (notice|error)
	 */
	protected function error_log($source,$message,$class='notice'): void
	{
		$trace 		= '';

		if (is_object($message)) {
			$message = (is_wp_error($message))
				? $message->get_error_code().' '.$message->get_error_message()
				: $message->getCode().' '.$message->getMessage();
			$class 	= 'error';
			ob_start();
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			$trace 	= PHP_EOL . ob_get_clean();
		}

		$class 		= esc_attr($class);
		$message 	= esc_attr($message);
		$siteId 	= ($this->is_multisite) ? " [site {$this->blog_id}]" : "";
		$source		= esc_attr($source.$siteId);

		if ($this->display_errors && is_admin() && function_exists('is_admin_bar_showing') && is_admin_bar_showing()) {
			echo "<div class='object-cache-{$class} notice notice-{$class}'>".
				 "WP Object Cache{$siteId}: {$message}</div>\n";
		}

		$message = $message.$trace;

		if ($this->log_errors) {
			if (defined('QM_VERSION') && ( !defined('QM_DISABLED') || !QM_DISABLED )) {
				do_action( "qm/{$class}", $source.': '.$message );
			}
			if (defined('EACDOOJIGGER_VERSION')) {
				do_action( "eacDoojigger_log_{$class}",$message,$source );
			}
		}

		if ($this->log_errors || $class == 'error') {
			$level = "E_USER_".strtoupper($class);
			if (defined($level) && (constant($level) & error_reporting())) {
				\error_log($source.' '.$class.': '.$message);
			}
		}
	}


	/*
	 * API methods  - counts/stats
	 */


	/**
	 * Update runtime counters.
	 *
	 * @param string $countId the counter to update
	 * @param int|bool $n the count to add (+1)
	 * @param string $groupId also update the group counter
	 */
	private function addRuntimeStats(string $countId, $n, string $groupId=null)
	{
		if ((int)$n)
		{
			if ($countId) {
				if (!isset($this->stats_runtime[$countId])) {
					$this->stats_runtime[$countId] = 0;
				}
				$this->stats_runtime[$countId] += (int)$n;
			}
			if ($groupId) {
				if (!isset($this->stats_groups[$groupId])) {
					$this->stats_groups[$groupId] = 0;
				}
				$this->stats_groups[$groupId] += (int)$n;
			}
		}
	}


	/**
	 * Adds a dashboard widget
	 *
	 * @return void
	 */
	public function showDashboardStats(): void
	{
		echo "\n<style>".esc_html($this->statsCSS)."</style>\n";
		$this->htmlStats(false);
	}


	/**
	 * Adds an admin notice
	 *
	 * @return void
	 */
	public function showAdminPageStats(): void
	{
		// this gets moved to the top of an admin page
		echo "\n<style>".esc_html($this->statsCSS)."</style>\n";
		echo "<div class='object-cache-notice notice notice-info'>";
		echo "<details><summary>Object Cache Stats...</summary>";
		$this->htmlStats();
		echo "</details></div>\n";
	}


	/**
	 * log runtime stats
	 *
	 * @return void
	 */
	public function logRuntimeStats(): void
	{
		$stats = $this->getStatsCache();

		if (defined('QM_VERSION') && ( !defined('QM_DISABLED') || !QM_DISABLED ))
		{
			$log =  __CLASS__." on ".current_action()."\n";
			foreach ($stats as $group => $cache) {
				if ($cache && $cache[0]) {
					$log .= "\t" . esc_attr( $group ) . ': ' . number_format( $cache[0], 0 );
					if ($cache[1]) $log .=  ' ( '.esc_attr($cache[1]) . ' )';
					$log .= "\n";
				}
			}
			do_action( 'qm/info', $log );
		}

		if (defined('EACDOOJIGGER_VERSION'))
		{
			$log = 'cache hits: ' . number_format( $this->stats_runtime['cache hits'], 0).', ' .
					'(L1: ' 		. number_format( $this->stats_runtime['L1 cache hits'], 0).', ';
			if ($this->use_apcu) {
				$log .=
					'APCu: ' 		. number_format( $this->stats_runtime['L2 APCu hits'], 0).', ';
			}
			if ($this->use_db) {
				$log .=
					'L2: ' 		. number_format( $this->stats_runtime['L2 SQL hits'], 0);
			}
			$log = ltrim($log,', ').'), '.
					 'misses: ' 	. number_format( $this->stats_runtime['cache misses'], 0).'}, ' .
					 'ratio: ' 		. $stats['cache hits'][1];
			do_action("eacDoojigger_log_notice",$log,__CLASS__);
		}
	}


	/**
	 * Echoes the stats of the caching (similar to WP default cache).
	 * Called by outside actors (e.g. debug bar)
	 *
	 * Gives the cache hits, and cache misses.
	 *
	 * @param bool $full include groups, APCu & db
	 */
	public function stats(bool $full=false): void
	{
		$stats = $this->getCurrentStats($full);

		if ($full && isset($stats['id'])) {
			echo "<p>";
			foreach ($stats['id'] as $name => $value) {
				echo $value."<br />";
			}
			echo "</p>";
		}

		echo "<p><strong>Object Cache Counts:</strong></p><ul>";
		foreach ($stats['cache'] as $group => $cache) {
			if ($cache && $cache[0]) {
				echo '<li>' . ucwords(esc_attr( $group )) . ': ' . number_format( $cache[0], 0 );
				if ($cache[1]) echo ' ( '.esc_attr($cache[1]) . ' )';

			}
		}
		echo "</ul>\n";

		if ($full && isset($stats['cache-groups']))
		{
			echo "<p><strong>Cache Groups:</strong></p><ul>";
			foreach ($stats['cache-groups'] as $group => $cache) {
				if ($cache && $cache[1]) {
					echo '<li>' . esc_attr( $group ) .
						 ' - ' . number_format( $cache[1], 0 ).
						 ' ( ' . number_format( $cache[2] / KB_IN_BYTES, 0 ) . 'k )</li>';
				}
			}
			echo "</ul>\n";
		}

		if ($full && isset($stats['apcu-groups']))
		{
			echo "<p><strong>APCu Lifetime:</strong></p><ul>";
			foreach ($stats['apcu-groups'] as $group => $cache) {
				if ($cache && $cache[1]) {
					echo '<li>' . esc_attr( $group ) .
						 ' - ' . number_format( $cache[1], 0 ).
						 ' ( ' . number_format( $cache[2] / KB_IN_BYTES, 0 ) . 'k )</li>';
				}
			}
		}

		if ($full && isset($stats['database-groups']))
		{
			echo "<p><strong>SQLite DB:</strong></p><ul>";
			foreach ($stats['database-groups'] as $group => $cache) {
				if ($cache && $cache[1]) {
					echo '<li>' . esc_attr( $group ) .
						 ' - ' . number_format( $cache[1], 0 ).
						 ' ( ' . number_format( $cache[2] / KB_IN_BYTES, 0 ) . 'k )</li>';
				}
			}
		}
	}


	/**
	 * Echos the stats of the caching, formatted table.
	 *
	 * Gives the cache hits, and cache misses.
	 *
	 * @param bool $full include groups, APCu & db
	 */
	public function htmlStats(bool $full=true): void
	{
		$stats = $this->getLastSample();

		echo "\n<div class='wp-object-cache'>";

		if (isset($stats['id'])) {
			echo "<p>";
			foreach ($stats['id'] as $name => $value) {
				echo $value."<br />";
			}
			echo "</p>";
		}

		echo "\n<table class='wp-object-cache'>";

		if (isset($stats['cache']))
		{
			if ($full) {
				echo "\n<tr><th colspan='4'><p>Cache Counts:</p></th></tr>";
				$stats['cache'] = array_filter($stats['cache'], function($value,$name) {
					return ($value && $value[0]);
				}, ARRAY_FILTER_USE_BOTH);
			} else {
				$stats['cache'] = array_filter($stats['cache'], function($value,$name) {
					return (str_ends_with($name,'hits') || str_ends_with($name,'misses'));
				}, ARRAY_FILTER_USE_BOTH);
				if (! $this->use_apcu) {
					$stats['cache'] = array_filter($stats['cache'], function($value,$name) {
						return (! str_starts_with($name,'L2 APCu'));
					}, ARRAY_FILTER_USE_BOTH);
				}
				if (! $this->use_db) {
					$stats['cache'] = array_filter($stats['cache'], function($value,$name) {
						return (! str_starts_with($name,'L2 SQL'));
					}, ARRAY_FILTER_USE_BOTH);
				}
			}
			foreach ($stats['cache'] as $name => $value) {
				echo '<tr><th>'.esc_attr( $name ).'</th>'.
						 '<td>'.number_format($value[0], 0).'</td>'.
						 '<td>'.esc_attr($value[1]).'</td>'.
						 '<td></td></tr>';
			}
		}

		if ($full && isset($stats['cache-groups']))
		{
			echo "\n<tr><th colspan='4'><p>Cache Groups:</p></th></tr>";
			foreach ($stats['cache-groups'] as $name => $value) {
				if ($value && $value[0]) {
					echo '<tr><th>'.esc_attr( $name ).'</th>'.
						 '<td> +' .number_format( $value[0], 0) . '</td>';
					echo ($value[1])
						? '<td>'  .number_format( $value[1], 0 ) . '</td>'
						: '<td></td>';
					echo ($value[2])
						? '<td> ~'.number_format( $value[2] / KB_IN_BYTES, 2 ) . 'k</td></tr>'
						: '<td></td></tr>';
				}
			}
		}

		if ($full && isset($stats['apcu-groups']))
		{
			echo "\n<tr><th colspan='4'><p>APCu Cache:</p></th></tr>";
			foreach ($stats['apcu-groups'] as $name => $value) {
				if ($value && $value[0]) {
					echo '<tr><th>'.esc_attr( $name ).'</th>'.
						 '<td> +'.number_format( $value[0], 0) . '</td>'.
						 '<td>'  .number_format( $value[1], 0) . '</td>'.
						 '<td> ~'.number_format( $value[2] / KB_IN_BYTES, 2  ) . 'k</td></tr>';
				}
			}
		}

		if ($full && isset($stats['database-groups']))
		{
			echo "\n<tr><th colspan='4'><p>SQLite Cache:</p></th></tr>";
			foreach ($stats['database-groups'] as $name => $value) {
				if ($value && $value[1]) {
					echo '<tr><th>'.esc_attr( $name ).'</th>'.
						 '<td> +'.number_format( $value[0], 0) . '</td>'.
						 '<td>'  .number_format( $value[1], 0) . '</td>';
					echo ($value[2])
						? '<td> ~'.number_format( $value[2] / KB_IN_BYTES, 2 ) . 'k</td></tr>'
						: '<td></td></tr>';
				}
			}
		}

		echo "\n</table></div>\n";
	}


	/**
	 * Returns the stats of the caching.
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 *
	 * @param bool $full include APCu & db
	 * @return array
	 */
	public function getCurrentStats(bool $full=false): array
	{
		$this->write_cache();

		$stats = array( 'id'=>[] );

		$stats['id']['plugin'] 			= "<a href='https://eacdoojigger.earthasylum.com/eacobjectcache/' target='_blank'>" .
							self::PLUGIN_NAME . "</a> v" . esc_attr(EAC_OBJECT_CACHE_VERSION);
		//	'memory' 	=> 'peak memory used: ' . round((memory_get_peak_usage(false) / MB_IN_BYTES),1).'M of '.ini_get('memory_limit'),

		if ($this->use_db) {
			$cacheName	= $this->cache_dir . $this->cache_file;
			$stats['id']['SQLite'] 		= "SQLite v" . \SQLite3::version()['versionString'] .
							" using " . number_format( filesize($cacheName) / MB_IN_BYTES, 1 ) . "MB";
		}

		if ($this->use_apcu) {
			$apcu = apcu_cache_info(true);
			$stats['id']['APCu'] 		= "APCu v" . phpversion('apcu') . " using " .
							round(($apcu['mem_size'] / MB_IN_BYTES),1).'MB of '.ini_get('apc.shm_size') . "B";
		}

		//$stats['id']['file'] 			= "cache file: " . ((bool)$this->use_db
		//					? '~/'.trailingslashit(basename($this->cache_dir)) . $this->cache_file
		//					: 'memory-only');

		if (isset($_SERVER['REQUEST_URI'])) {
			$stats['id']['sample_uri']	= 'sample uri: '.sanitize_url( $_SERVER['REQUEST_URI'] );
		}
		$stats['id']['sample_time']		= 'sample time: '.wp_date('c');

		// addRuntimeStats() counters
		$stats['cache'] 				= $this->getStatsCache();

		if ($full)
		{
			// current cache contents by group
			$stats['cache-groups'] 		= $this->getStatsGroups();

			// APCu contents
			if ($this->use_apcu) {
				$stats['apcu-groups'] 	= $this->getStatsAPCu();
			}

			// database contents
			if ($this->use_db) {
				$stats['database-groups'] = $this->getStatsDB();
			}
		}

		return $stats;
	}


	/**
	 * Returns the current cache stats.
	 *
	 * @return array
	 */
	public function getStatsCache(): array
	{
		$stats = array();

		$this->stats_runtime["pre-fetched (-)"] = $this->stats_premissed[$this->get_blog_id('*')] ?? 0;

		foreach ($this->stats_runtime as $name => $count) {
			$stats[$name] = [$count,''];
		}

		// add cache hit ratios
		$stats['cache hits'][1] =
			$this->cache_hit_ratio($this->stats_runtime['cache hits'],$this->stats_runtime['cache misses']);
		$stats['L1 cache hits'][1] =
			$this->cache_hit_ratio($this->stats_runtime['L1 cache hits'],$this->stats_runtime['L1 cache misses']);
		$stats['L2 APCu hits'][1] =
			$this->cache_hit_ratio($this->stats_runtime['L2 APCu hits'],$this->stats_runtime['L2 APCu misses']);
		$stats['L2 SQL hits'][1] =
			$this->cache_hit_ratio($this->stats_runtime['L2 SQL hits'],$this->stats_runtime['L2 SQL misses']);

		return $stats;
	}


	/**
	 * Returns the current cache stats by group.
	 *
	 * @return array
	 */
	public function getStatsGroups(): array
	{
		$stats = array();

		foreach($this->stats_groups as $group => $hits) {
			$count = $size = 0;
			if ($cache = $this->L1_cache[$group] ?? false) {
				foreach ($cache as $k => $v) {
					$count++;
					if (is_array($v)) {
						$size += (strlen($k)+strlen(maybe_serialize($v)));
					} else {
						$size += (strlen($k)+1);
					}
				}
			}
			$stats[$group] = [$hits, $count, $size];
		}
		ksort($stats);
		$stats['Total'] = [
			array_sum(array_column($stats,0)),
			array_sum(array_column($stats,1)),
			array_sum(array_column($stats,2))
		];

		return $stats;
	}


	/**
	 * Returns the stats of the L2 APCu cache.
	 *
	 * @return array
	 */
	public function getStatsAPCu(): array
	{
		if ($this->use_apcu)
		{
			$apcu_key = preg_quote($this->get_apcu_key());
			if ($this->is_multisite && !is_network_admin()) {
				$apcu_key .= sprintf(".+\|00000|%05d\|", $this->get_blog_id('*'));
			}
			$keys = new APCUIterator('/^'.$apcu_key.'/');
			return ['Total' =>
				[
					$this->stats_runtime['L2 APCu hits'],
					$keys->getTotalCount(),
					$keys->getTotalSize(),
				]
			];
		}
		return ['Total'=>[0,0,0]];
	}


	/**
	 * Returns the stats of the L2 database.
	 *
	 * @return array
	 */
	public function getStatsDB(): array
	{
		$stats = array( 'Total' => [$this->stats_runtime['L2 SQL hits'],0,0,0] );

		if (!$this->use_db) return $stats;

		$blog_id = sprintf("%05d", $this->blog_id);
		$key_like = ($this->is_multisite && !is_network_admin())
			? "AND (key LIKE '%|00000|%' or key LIKE '%|{$blog_id}|%')"
			: "";
		if ($result = $this->use_db->query("
			SELECT SUBSTR(key,0,INSTR(key,'|')) as name,
				   SUBSTR(key,INSTR(key,'|')+1,5) as blog,
				   COUNT(*) as count,
				   SUM(LENGTH(key)*2 + LENGTH(value) + LENGTH(expire) + 4) as size
			FROM wp_cache
			 WHERE (expire = 0 OR expire >= {$this->time_now}) {$key_like}
			 GROUP BY name;"))
		{
			while ($row = $result->fetch()) {
			//	$stats[ $row['name'] ] = [$row['count'], $row['size']];
				$stats['Total'][1] += $row['count'];
				$stats['Total'][2] += $row['size'];
			}
			$result->closeCursor();
		//	ksort($stats);
		//	$stats['Total'] = [
		//	/* hits */	0,
		//	/* count */ array_sum(array_column($stats,0)),
		//	/* size */ 	array_sum(array_column($stats,1)),
		//				filesize($cacheName),
		//	];
			$cacheName	= $this->cache_dir . $this->cache_file;
			$stats['Total'][3] = filesize($cacheName);
		}

		return $stats;
	}


	/**
	 * Returns the cache hit ratio (formatted).
	 *
	 * @param int $hits number of hits
	 * @param int $misses number of misses
	 * @return string formatted cache hit ratio
	 */
	private function cache_hit_ratio(int $hits, int $misses): string
	{
		return ($hits > 0)
			? number_format( ($hits / ($hits + $misses)) * 100, 2 ) . '%'
			: '0.00%';
	}


	/**
	 * Returns the stats from the last sample saved.
	 *
	 */
	public function getLastSample(): array
	{
		if ($this->display_stats) {
			if ( $row = $this->get_cache( "sample", self::GROUP_ID ) ) {
				return $row;
			}
		}
		return $this->getCurrentStats(true);
	}


	/*
	 * Install/Uninstall
	 */


	/**
	 * Install (from connect for new db file).
	 *
	 * @param string $cacheName path to sqlite file
	 */
	public function install(string $cacheName): bool
	{
		$FS_CHMOD_FILE = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : (0644 & ~ umask());
		chmod($cacheName,$FS_CHMOD_FILE|0640);

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->use_db->exec(
					"CREATE TABLE IF NOT EXISTS wp_cache (".
					"key TEXT NOT NULL COLLATE BINARY PRIMARY KEY, value BLOB, expire INT".
					") WITHOUT ROWID;".
					"CREATE INDEX IF NOT EXISTS expire ON wp_cache (expire);"
				);
				break;
			} catch ( Exception $ex ) {
				$this->error_log(__METHOD__,$ex);
				usleep($this->sleep_time);
			}
		}

		// don't use $this->error_log before instantiated
		$message = __CLASS__.': SQLite cache initialized';
		\error_log($message);
		return true;
	}


	/**
	 * Uninstall (from external extension).
	 * Called for each site in multisite environment.
	 *
	 * @param bool $complete delete the cache file
	 */
	public function uninstall(bool $complete = true): bool
	{
		$this->wp_transients( 'export', $this->blog_id );
		if ($complete) {
			$this->delete_cache_APCu();
			$this->delete_cache_file();
			wp_using_ext_object_cache( false );
		}
		return true;
	}


	/**
	 * Alias to uninstall (from external extension / scheduled event).
	 * Called for each site in multisite environment.
	 *
	 */
	public function rebuild_object_cache(bool $complete = true): bool
	{
		return $this->uninstall($complete);
	}


	/*
	 * MySQL  transients
	 */


	/**
	 * Get WP/MySQL transient.
	 * May come from cached 'alloptions' or 'options' or options table
	 *
	 */
	private function get_transient($transient)
	{
		wp_using_ext_object_cache( false );
		$value = \get_transient( $transient );
		wp_using_ext_object_cache( true );

		if ($value) {
			$transient_timeout = '_transient_timeout_' . $transient;
			if ($expire = get_option( $transient_timeout ) ?: 0) {
				$expire = $expire - $this->time_now;
			}
			$this->addRuntimeStats('transients imported',1);
			$this->set($transient,$value,'transient',$expire);
		}

		return $value;
	}


	/**
	 * Get WP/MySQL site transient.
	 * May come from cached 'alloptions' or 'options' or sitemeta table
	 *
	 */
	private function get_site_transient($transient)
	{
		wp_using_ext_object_cache( false );
		$value = \get_site_transient( $transient );
		wp_using_ext_object_cache( true );

		if ($value) {
			$transient_timeout = '_site_transient_timeout_' . $transient;
			if ($expire = get_site_option( $transient_timeout ) ?: 0) {
				$expire = $expire - $this->time_now;
			}
			$this->addRuntimeStats('site transients imported',1);
			$this->set($transient,$value,'site-transient',$expire);
		}

		return $value;
	}


	/**
	 * MySQL transient import/export.
	 *
	 */
	private function wp_transients(string $action, int $blog_id): int
	{
		switch ($action) {
		/*
			case 'import':
				if ($this->get_defined_option("disable_transient_import", 'is_bool')) return false;
				if ($count = $this->import_wp_transients()) {
					$this->stats_runtime['transients imported'] = 0;
					$this->error_log(__CLASS__,sprintf("%d transients imported",$count));
				}
				return $count;
		*/
			case 'export':
				if ($this->get_defined_option("disable_transient_export", 'is_bool')) return false;
				if ($count = $this->export_wp_transients()) {
					$this->stats_runtime['transients exported'] = 0;
					$this->error_log(__CLASS__,sprintf("%d transients exported",$count));
				}
				return $count;
		}
		return 0;
	}


	/**
	 * Import existing MySQL transients.
	 *
	 */
	private function import_wp_transients(): int
	{
/*
		global $wpdb;

		// do we have db connections
		if (! $wpdb) return 0;

		// see if we've done this already
		if ( $this->get_cache( 'imported-transients', self::GROUP_ID ) ) {
			return 0;
		}

		// maximize available memory
		wp_raise_memory_limit( 'admin' );

		// write all in one transaction
		$this->set_delayed_writes( true );

		// import transients from options table
		$optionSQL =
			"SELECT option_name as name, option_value as value".
			" FROM {$wpdb->options} WHERE option_name LIKE '%s' AND option_name NOT LIKE '%s'";

		// circumvent wpdb to retrieve individual rows
		$transients = mysqli_query( $wpdb->dbh,
			sprintf($optionSQL,'_transient_%','_transient_timeout_%')
		);
		if ( $transients instanceof mysqli_result ) {
			while ( $row = mysqli_fetch_object( $transients ) ) {
				$this->pull_wp_transient($row,'transient');
			}
			mysqli_free_result($transients);
		}

		// import site transients from options or sitemeta table
		$siteSQL = ($this->is_multisite)
			? "SELECT meta_key as name, meta_value as value".
			  " FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%s' AND meta_key NOT LIKE '%s'"
			: $optionSQL;

		// circumvent wpdb to retrieve individual rows
		$transients = mysqli_query( $wpdb->dbh,
			sprintf($siteSQL,'_site_transient_%','_site_transient_timeout_%')
		);
		if ( $transients instanceof mysqli_result ) {
			while ( $row = mysqli_fetch_object( $transients ) ) {
				$this->pull_wp_transient($row,'site-transient');
			}
			mysqli_free_result($transients);
		}

		$this->set('imported-transients', [ wp_date('c'),$this->stats_runtime['transients imported'] ], self::GROUP_ID, 0);
		$this->set_delayed_writes();

		return $this->stats_runtime['transients imported'] ?? 0;
*/
	}


	/**
	 * Pull a single transient from WP.
	 *
	 * @param object $row transient record
	 * @param string $group 'transient' or 'site-transient'
	 */
	private function pull_wp_transient(object $row, string $group): void
	{
/*
		global $wpdb;
		static $optionSQL = null;
		static $siteSQL = null;

		$key = str_replace(['_site_transient_','_transient_'],'',$row->name);

		if (is_null($optionSQL)) {
			$optionSQL =
				"SELECT option_value FROM $wpdb->options ".
				"WHERE option_name = '%s' LIMIT 1";
		}
		if (is_null($siteSQL)) {
			$siteSQL =
				"SELECT meta_value FROM $wpdb->sitemeta ".
				"WHERE meta_key = '%s' AND site_id = '%d' LIMIT 1";
		}

		if ($group == 'transient') {
		//	$expire = get_option('_transient_timeout_'.$key,0);
			$expire = $wpdb->get_var(					// too early to use prepare/wp_kses
				sprintf($optionSQL,
				"_transient_timeout_{$key}" )
			);
		//	delete_transient($key);
		} else {
		//	$expire = get_site_option('_site_transient_timeout_'.$key,0);
			$expire = $wpdb->get_var(					// too early to use prepare/wp_kses
				sprintf(($this->is_multisite ? $siteSQL : $optionSQL),
				"_site_transient_timeout_{$key}", get_current_network_id() )
			);
		//	delete_site_transient($key);
		}

		$expire = (int)$expire ?? 0;

		if ($expire > 0) {
			if ($expire <= $this->time_now) return;
			$expire = $expire - $this->time_now;
		}

		$this->set($key, maybe_unserialize($row->value), $group, $expire);
		$this->addRuntimeStats('transients imported',1);
*/
	}


	/**
	 * Export existing transients back to MySQL.
	 *
	 */
	private function export_wp_transients(): int
	{
		$rows = [];

		$blogkeys = [];
		foreach (['transient','transient:prefetch'] as $group) {
			$blogkeys[$group] = [$this->get_blog_key('%',$group)];
		}

		if (!$this->is_multisite || $this->blog_id == get_main_site_id()) {
			foreach (['site-transient','site-transient:prefetch'] as $group) {
				$blogkeys[$group] = [$this->get_blog_key('%',$group)];
			}
		}

		// get DB transients
		if ($this->use_db)
		{
			$this->write_cache();

			while ($row = $this->select_each($blogkeys,true,false)) {
				$rows[ "{$row['group']}|{$row['blog']}|{$row['key']}" ] = $row;
			}
		}

		// get APCu transients
		foreach($blogkeys as $group => $blogkey)
		{
			$blogid = $this->get_blog_id($group);
			if ($apcu_key = $this->get_apcu_key( $group, $blogid ))
			{
				$keys = new APCUIterator('/^'.preg_quote($apcu_key,'/').'/');
				foreach( $keys as $data ) { // key,value
					if ( ($row = $this->parse_key($data['key'])) && is_array($data['value']) ) {
						$rows[ "{$row['group']}|{$row['blog']}|{$row['key']}" ] = array_merge($row,$data['value']);
					}
				}
			}
		}

		// so we don't try to use this cache
		wp_using_ext_object_cache( false );
		$suspended  = wp_suspend_cache_addition(true);

		foreach($rows as $row) {
			$this->push_wp_transient($row);
		}

		wp_suspend_cache_addition($suspended);
		wp_using_ext_object_cache( true );

		return $this->stats_runtime['transients exported'] ?? 0;
	}


	/**
	 * Push a single transient to WP.
	 *
	 * @param object $row transient record
	 */
	private function push_wp_transient(array $row): void
	{
		if ($row['expire'] >= 0) {
			switch ($row['group']) {
				case 'transient':
				case 'transient:prefetch':
					set_transient($row['key'],$row['value'],$row['expire']);
					break;
				case 'site-transient':
				case 'site-transient:prefetch':
					set_site_transient($row['key'],$row['value'],$row['expire']);
					break;
			}
			$this->addRuntimeStats('transients exported',1);
		}
	}
}


/*
 * global wp functions (wp-include/cache.php)
 * WP_PLUGIN_DIR not yet set
 */
require __DIR__.'/plugins/eacobjectcache/src/wp-cache.php';

