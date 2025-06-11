<?php
/**
 * {eac}Doojigger Object Cache - SQLite powered WP_Object_Cache Drop-in.
 *
 * Plugin Name:			{eac}ObjectCache
 * Description:			{eac}Doojigger Object Cache - SQLite powered WP_Object_Cache Drop-in
 * Version:				1.3.2
 * Requires at least:	5.8
 * Tested up to:		6.8
 * Requires PHP:		7.4
 * Plugin URI:			https://eacdoojigger.earthasylum.com/eacobjectcache/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 */

defined( 'ABSPATH' ) || exit;

define('EAC_OBJECT_CACHE_VERSION','1.3.3');

/**
 * Derived from WordPress core WP_Object_Cache (wp-includes/class-wp-object-cache.php)
 *
 *
 * {eac}ObjectCache is an extension plugin to and is fully functional with installation and registration of
 * {eac}Doojigger (see: https://eacDoojigger.earthasylum.com/).
 *
 * However, the core 'object-cache.php' file may be installed without {eac}Doojigger - referred to as 'detached' mode.
 *
 * In detached mode, the plugin will attempt to copy this file to the `/wp-content` folder on activation,
 * or you may manually copy the file from the plugin '/src' folder to the '/wp-content' folder to activate.
 * Options can then be set using the documented PHP constants in the 'wp-config.php' file.
 *
 * -
 *
 * To prevent outside actors from flushing caches.
 *
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_FULL_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_GROUP_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_BLOG_FLUSH', true );
 * 		define( 'EAC_OBJECT_CACHE_DISABLE_RUNTIME_FLUSH', true );
 *
 */


class WP_Object_Cache
{
	/**
	 * this plugin id
	 *
	 * @var string
	 */
	const PLUGIN_NAME			= '{eac}ObjectCache';

	/**
	 * internal group id
	 *
	 * @var string
	 */
	const GROUP_ID				= '@object-cache';
	const GROUP_ID_GLOBAL		= '@object-cache-global';

	/**
	 * path name to cache folder
	 *
	 * Set with: EAC_OBJECT_CACHE_DIR
	 *
	 * @var string
	 */
	private $cache_dir			= WP_CONTENT_DIR.'/cache';

	/**
	 * name of cache file
	 *
	 * Set with: EAC_OBJECT_CACHE_FILE
	 *
	 * @var string
	 */
	private $cache_file			= '.eac_object_cache.sqlite';

	/**
	 * SQLite journal mode
	 *
	 * Set with: EAC_OBJECT_CACHE_JOURNAL_MODE
	 *
	 * @var string
	 */
	private $journal_mode		= 'WAL';

	/**
	 * SQLite timeout in seconds
	 *
	 * Set with: EAC_OBJECT_CACHE_TIMEOUT
	 *
	 * @var int
	 */
	private $timeout			= 3;

	/**
	 * SQLite mapped memory I/O
	 *
	 * Set with: EAC_OBJECT_CACHE_MMAP_SIZE
	 *
	 * @var int
	 */
	private $mmap_size			= 0;

	/**
	 * SQLite page size
	 *
	 * Set with: EAC_OBJECT_CACHE_PAGE_SIZE
	 *
	 * @var int
	 */
	private $page_size			= 4096;

	/**
	 * SQLite cache size
	 *
	 * Set with: EAC_OBJECT_CACHE_CACHE_SIZE
	 *
	 * @var int
	 */
	private $cache_size			= -2000;

	/**
	 * open/write/delete retries.
	 *
	 * Set with: EAC_OBJECT_CACHE_MAX_RETRIES, $wp_object_cache->max_retries = n
	 *
	 * @var int
	 */
	public $max_retries			= 3;

	/**
	 * sleep between retries (micro-seconds) 1/10-second.
	 *
	 * Set with: $wp_object_cache->sleep_time = n
	 *
	 * @var int
	 */
	public $sleep_time			= 100000;

	/**
	 * use delayed writes until shutdown or n records
	 *
	 * Set with: EAC_OBJECT_CACHE_DELAYED_WRITES, $wp_object_cache->delayed_writes = n
	 *
	 * @var bool|int
	 */
	public $delayed_writes		= 32;

	/**
	 * if expiration is not set, use this time integer
	 * -1 = don't cache (non-persistent), 0 = no expiration, int = seconds to expiration
	 *
	 * Set with: EAC_OBJECT_CACHE_DEFAULT_EXPIRE, $wp_object_cache->default_expire = n
	 *
	 * @var int
	 */
	public $default_expire		= 0;

	/**
	 * pre-fetch cache misses
	 *
	 * Set with: EAC_OBJECT_CACHE_PREFETCH_MISSES
	 *
	 * @var bool
	 */
	private $prefetch_misses 	= true;

	/**
	 * the probablity of running maintenance functions
	 *
	 * Set with: EAC_OBJECT_CACHE_PROBABILITY, $wp_object_cache->probability = n
	 *
	 * @var int
	 */
	public $probability			= 1000;

	/**
	 * List of actions or filters that trigger an immediate cache write (overrides $delayed_writes)
	 *
	 * Set with: EAC_OBJECT_CACHE_WRITE_HOOKS, $wp_object_cache->write_hooks = [...]
	 *
	 * @var string[]
	 */
	public $write_hooks 		= array(
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
	public $display_stats		= 0;

	/**
	 * display errors in an admin notice.
	 *
	 * Set with: $wp_object_cache->display_errors = false|true
	 *
	 * @var bool
	 */
	public $display_errors		= false;

	/**
	 * log errors in eacDoojigger log.
	 *
	 * Set with: $wp_object_cache->log_errors = false|true
	 *
	 * @var bool
	 */
	public $log_errors			= false;

	/**
	 * List of global/site-wide cache groups.
	 *
	 * Set with: EAC_OBJECT_CACHE_GLOBAL_GROUPS, wp_cache_add_global_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private $global_groups		= array(
		self::GROUP_ID_GLOBAL	=> true,
	);

	/**
	 * List of non-persistent cache groups.
	 *
	 * Set with: EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS, wp_cache_add_non_persistent_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private $nonp_groups		= array();

	/**
	 * List of permanent cache groups, no expiration required.
	 *
	 * Set with: EAC_OBJECT_CACHE_PERMANENT_GROUPS, wp_cache_add_permanent_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private $perm_groups		= array(
		self::GROUP_ID			=> true,
		self::GROUP_ID_GLOBAL	=> true,
		'transient'				=> true,
		'site-transient'		=> true,
	);

	/**
	 * List of pre-loaded cache groups.
	 *
	 * Set with: EAC_OBJECT_CACHE_PREFETCH_GROUPS, wp_cache_add_prefetch_groups( [...] )
	 *
	 * @var [string => bool]
	 */
	private $prefetch_groups 	= array(
		self::GROUP_ID			=> true,
		self::GROUP_ID_GLOBAL	=> true,
	);

	/**
	 * Holds the cached objects (group => [key => [value=>,expire=>] | false]).
	 *	false = tried but not in persistent cache, don't try again.
	 *
	 * @var array
	 */
	private $L1_cache			= array();

	/**
	 * Holds db writes objects (group => [key => expire | false]).
	 *	false = to be deleted from persistent cache.
	 *
	 * @var array
	 */
	private $L2_cache			= array();

	/**
	 * Memory/persistent cache stats
	 *
	 * @var int[]
	 */
	private $cache_stats		= array(
		'cache hits'			=> 0,	// total cache hits (memory & db)
		'cache misses'			=> 0,	// total cache misses (memory & db)
		'L1 cache hits'			=> 0,	// cache hits in memory
		'L1 cache (+)'			=> 0,	// in memory with data
		'L1 cache (-)'			=> 0,	// in memory, no data
		'L1 cache misses'		=> 0,	// cache misses in memory
		'L2 cache hits'			=> 0,	// cache hits from sqlite
		'L2 cache misses'		=> 0,	// cache misses from sqlite
		'L2 pre-fetched (+)'	=> 0,	// records pre-fetched by group
		'L2 pre-fetched (-)'	=> 0,	// misses pre-fetched by group
		'L2 selects'			=> 0,	// number of sql selects
		'L2 updates'			=> 0,	// number of records updated
		'L2 deletes'			=> 0,	// number of records deleted
		'L2 commits'			=> 0,	// number of sql transaction commits
	);

	/**
	 * Cache hits by group ([group => count])
	 *
	 * @var int[]
	 */
	private $group_stats		= array();

	/**
	 * Count L2 pre-fetched misses by blog id ([id => count])
	 * used for $cache_stats['L2 pre-fetched (-)']
	 *
	 * @var int[]
	 */
	private $L2_missed_stats 	= array();

	/**
	 * Recommended style for stats html table.
	 *
	 * @var string
	 */
	public $statsCSS			=
		"table.wp-object-cache th {text-align: left; font-weight: normal;}".
		"table.wp-object-cache th p {font-weight: bold;}".
		"table.wp-object-cache td {text-align: right; padding-left: 1em;}";

	/**
	 * Set time now.
	 *
	 * @var int
	 */
	private $time_now;

	/**
	 * Holds the value of is_multisite().
	 *
	 * @var bool
	 */
	private $is_multisite		= false;

	/**
	 * The blog prefix to prepend to keys in non-global groups.
	 *
	 * @var int
	 */
	private $blog_id			= 0;

	/**
	 * The SQLite database object.
	 *
	 * @var object
	 */
	private $db;


	/*
	 * constructor methods
	 */


	/**
	 * Constructor, sets up object properties, SQLite database.
	 * Called from wp_cache_init();
	 *
	 */
	public function __construct()
	{
		$this->time_now 	= time();
		$this->is_multisite = is_multisite();

		$this->get_defined_options();

		// we can still function as a memory-only cache on failure
		if (! $this->connect_sqlite() ) {
			$this->delete_cache_file();
			$this->connect_sqlite();
		}

		// because these are non-standard methods, nothing outside calls them
		$this->add_permanent_groups();
		$this->add_prefetch_groups();
	}


	/**
	 * Initialize, sets current blog id (imports transients).
	 * Called from wp_cache_init();
	 *
	 */
	public function init()
	{
		if (! $this->is_multisite) {
			$this->switch_to_blog( get_current_blog_id() );
		}

		if (is_admin())
		{
			add_action( 'admin_footer', function()
				{
					// this gets moved to the top of an admin page
					if ($this->display_stats) {
						echo "\n<style>".esc_html($this->statsCSS)."</style>\n";
						echo "<div class='object-cache-notice notice notice-info'>";
						echo "<details><summary>Object Cache...</summary>";
						$this->htmlStats( (bool)$this->display_stats );
						echo "</details></div>\n";
					}
				},PHP_INT_MAX - 100
			);
		}

		add_action( 'shutdown', function()
			{
				// for Query Monitor/eacDoojigger logging
				$this->getStatsCache(true);
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
		//	option suffix		validation
			['cache_dir', 		'is_string'],	// cache directory (/wp-content/cache)
			['cache_file', 		'is_string'],	// cache file name (.eac_object_cache.sqlite)
			['journal_mode', 	'is_string'],	// SQLite journal mode	(WAL)
			['mmap_size', 		'is_int'],		// SQLite mapped memory I/O
			['page_size', 		'is_int'],		// SQLite page size
			['cache_size', 		'is_int'],		// SQLite cache size
			['timeout', 		'is_int'],		// PDO timeout (int)
			['max_retries', 	'is_int'],		// database retries (int)
			['delayed_writes',	 null],			// delayed writes (true|false|int)
			['default_expire', 	'is_int'],		// default expiration (-1|0|int)
			['prefetch_misses', 'is_bool'],		// pre-fetch cache misses (bool)
			['probability', 	'is_int'],		// maintenance/sampling probability (int)
			['write_hooks', 	'is_array'],	// hooks that trigger an immediate cache write
		) as $option) {
			$this->get_defined_option(...$option);
		}

		// actions or filters that trigger an immediate cache write.
		foreach ($this->write_hooks as $hook)
		{
			add_filter( $hook, function($return)
			{
				$this->set_delayed_writes( false );
				return $return;
			});
		}

		// when about to process Action Scheduler queue, don't allow runtime flush
		/*
		add_filter( 'action_scheduler_before_process_queue', function($return)
		{
			if (! defined('EAC_OBJECT_CACHE_DISABLE_RUNTIME_FLUSH')) {
				define( 'EAC_OBJECT_CACHE_DISABLE_RUNTIME_FLUSH', true );
			}
		});
		*/
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
		$constant = sprintf( 'EAC_OBJECT_CACHE_%s', strtoupper( $var ) );
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
	 * open the SQLite database connection
	 *
	 */
	private function connect_sqlite(): bool
	{
		$cacheName	= trailingslashit($this->cache_dir) . $this->cache_file;
		$install	= !file_exists($cacheName);

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->db = new \PDO("sqlite:{$cacheName}",null,null,[
					PDO::ATTR_TIMEOUT				=> $this->timeout,
					PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
				]);
				$this->db->exec("
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
				$this->error_log(__METHOD__,$ex);
				$this->db = null;
				usleep($this->sleep_time);
			}
		}

		if ( ! $this->db ) return false;

		return ($install) ? $this->install($cacheName) : true;
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
	 * Serves as a utility function to determine whether a key exists in the cache.
	 *
	 * @since 3.4.0
	 *
	 * @param int|string $key   Cache key to check for existence.
	 * @param string     $group Cache group for the key existence check.
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	protected function _exists( $key, $group )
	{
		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;
		return $this->key_exists( $blogkey, $group );
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
		if ( is_int( $key ) ) {
			return true;
		}

		if ( is_string( $key ) && trim( $key ) !== '' ) {
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


	/*
	 * SQLite select (one / all)
	 */


	/**
	 * select a single record from the database.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool 		$force overwrite existing L1 cache
	 * @return array|bool	selected row or false;
	 */
	private function select_one( string $blogkey, string $group, bool $force = true )
	{
		static $stmt = null;

		if ( ! $this->db ) return false;

		$this->L1_cache[ $group ][ $blogkey ] = false;

		if ( isset( $this->nonp_groups[ $group ] ) ) return false;

		if (is_null($stmt)) {
			$stmt = $this->db->prepare(
				"SELECT * FROM wp_cache".
				" WHERE key = :key AND (expire = 0 OR expire >= {$this->time_now}) LIMIT 1;"
			);
		}

		try {
			$stmt->execute( ['key' => $group.'|'.$blogkey] );
			if ($row = $stmt->fetch()) {
				$row = $this->select_parse_row( $row, $force );
			}
			$this->addStats('L2 selects',1);
			$stmt->closeCursor();
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
		}

		return $row;
	}


	/**
	 * select multiple records from the database.
	 *
	 * @param array		$blogkeys keys to select (group => [blogkey,...]).
	 * @param bool		$like use 'key like' not 'key in'
	 * @param bool 		$force overwrite existing L1 cache
	 * @return array	selected rows.
	 */
	private function select_all( array $blogkeys, bool $like = false, bool $force = true ): array
	{
		if ( ! $this->db ) return [];

		// get the blogkeys that we need to select from L2
		$selectkeys = [];
		foreach ($blogkeys as $group => $keys) {
			foreach ($keys as $blogkey) {
				$selectkeys[] = $group.'|'.$blogkey;
				if (!$like) {
					$this->L1_cache[ $group ][ $blogkey ] = false;
				}
			}
		}

		if (empty($selectkeys)) return [];

		$selectkeys = array_unique($selectkeys);

		$where = ($like)
			? substr(str_repeat( 'KEY LIKE ? OR ', count($selectkeys)),0,-4)
			: 'KEY IN (' . substr(str_repeat('?,', count($selectkeys)),0,-1) . ')';

		$stmt = $this->db->prepare(
			"SELECT * FROM wp_cache".
			" WHERE {$where} AND (expire = 0 OR expire >= {$this->time_now});"
		);

		try {
			$stmt->execute($selectkeys);
			if ($rows = $stmt->fetchAll()) {
				foreach ($rows as &$row) {
					$row = $this->select_parse_row( $row, $force );
				}
			}
			$this->addStats('L2 selects',1);
			$stmt->closeCursor();
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
		}

		return ($rows) ? $rows : [];
	}


	/**
	 * Utilty to parse key from database row, unserialize value, and add to L1 cache.
	 * sets expire to seconds (not time) and adds to L1 cache.
	 *
	 * @param array 	$row database row (key, value, expire)
	 * @param bool 		$force overwrite existing L1 cache (default)
	 * @return array row (key, value, expire, group, blog)
	 */
	private function select_parse_row( array $row, bool $force = true ): array
	{
		if (preg_match("/^(?<group>.*)\|(?<blog>\d{5})\|(?<key>.*)$/", $row['key'], $parts)) {
			$row = array_merge($row,array_filter($parts,'is_string',ARRAY_FILTER_USE_KEY));
			$row['value']	= maybe_unserialize($row['value']);
			$row['expire']	= (!empty($row['expire'])) ? $row['expire'] - $this->time_now : 0;
			// add to the L1 (memory) cache
			$blogkey		= $row['blog'].'|'.$row['key'];
			if ($force || empty($this->L1_cache[ $row['group'] ][ $blogkey ])) {
				$this->L1_cache[ $row['group'] ][ $blogkey ] = [ 'value'=>$row['value'], 'expire'=>$row['expire'] ];
				unset($this->L2_cache[ $row['group'] ][ $blogkey ]);
			}
		} else { // this shouldn't ever happen
			$this->error_log(__METHOD__, 'invalid cache key format ['.$key.']');
		}
		return $row;
	}


	/*
	 * API utility methods
	 */


	/**
	 * Serves as a utility function to determine whether a key is valid.
	 *
	 * @param int|string $key Cache key to check for validity.
	 * @param string	 $group Where to group the cache contents. Default 'default'.
	 * @return string|bool combined site|key if the key is valid.
	 */
	private function get_valid_key( $key, $group, $global = false )
	{
		if ($this->is_valid_key($key)) {
			$blog_id = ( $this->is_multisite && !isset( $this->global_groups[ $group ] ) )
				? $this->blog_id : 0;
			return sprintf( "%05d|%s", $blog_id, $key );
		}

		return false;
	}


	/**
	 * Serves as a utility function to determine whether a key exists in the cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment hits/misses
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	private function key_exists( string $blogkey, string $group, $count = false ): bool
	{
		return ($this->key_exists_memory( $blogkey, $group, $count ))
			? (bool) $this->L1_cache[ $group ][ $blogkey ]
			: ( !isset($this->prefetch_groups[$group])
				? $this->key_exists_database( $blogkey, $group, $count )
				: false );
	}


	/**
	 * Serves as a utility function to determine whether a key exists in the memory cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment L1 hits/misses
	 * @return int	+1 = found w/data, -1 = found no data, 0 not found
	 */
	private function key_exists_memory( string $blogkey, string $group, $count = false ): int
	{
		if ( isset( $this->L1_cache[ $group ], $this->L1_cache[ $group ][ $blogkey ] ) ) {
			if ($count) {
				$this->addStats('cache hits',1,$group);
				$this->addStats('L1 cache hits',1);
			}
			if ($this->L1_cache[ $group ][ $blogkey ] !== false) {
				if ($count) $this->addStats('L1 cache (+)',1);
				return +1;
			} else {
				if ($count) $this->addStats('L1 cache (-)',1);
				return -1;
			}
		}

		if ($count) {
			$this->addStats('cache misses',1);
			$this->addStats('L1 cache misses',1);
		}
		return 0;
	}


	/**
	 * Serves as a utility function to determine whether a key exists in the database cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment L2 hits/misses
	 * @return bool Whether the key exists in the db
	 */
	private function key_exists_database( string $blogkey, string $group, $count = false ): bool
	{
		if ( ! $this->db ) return false;

		if ( $row = $this->select_one( $blogkey, $group ) ) {
			if ($count) {
				$this->addStats('cache hits',1,$group);
				$this->addStats('L2 cache hits',1);
			}
			return true;
		} else {
			if ($count) {
				$this->addStats('cache misses',1);
				$this->addStats('L2 cache misses',1);
			}
			return false;
		}
	}


	/*
	 * API methods  - outside actors manage cache objects (add/set/get/delete)
	 */


	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @uses WP_Object_Cache::key_exists() Checks to see if the cache already has data.
	 * @uses WP_Object_Cache::set()		Sets the data after the checking the cache
	 *									contents existence.
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
		if (empty( $group )) $group = 'default';

		if ( wp_suspend_cache_addition() ) return false;
		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;
		if ( $this->key_exists( $blogkey, $group ) ) return false;

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
	 * @see WP_Object_Cache::set()
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
		if (empty( $group )) $group = 'default';

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;
		if ( ! $this->key_exists( $blogkey, $group ) ) return false;

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
		if (empty( $group )) $group = 'default';

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		// set default expiration time - transients (perm_groups) don't expire unless explicitly set
		$expire = (!empty($expire))
			? (int)$expire
			: (isset( $this->perm_groups[ $group ] ) ? 0 : $this->default_expire);

		// value has not changed
		if ( ($this->key_exists_memory( $blogkey, $group ) > 0)
			&& ($data	=== $this->L1_cache[ $group ][ $blogkey ][ 'value' ])
			&& ($expire === $this->L1_cache[ $group ][ $blogkey ][ 'expire' ])
		) {
			return false;
		}

		$this->L1_cache[ $group ][ $blogkey ] = [ 'value' => $data, 'expire' => $expire ];

		// when not to write to db
		if ( ! $this->db || $expire < 0 || isset( $this->nonp_groups[ $group ] ) ) {
			return true;
		}

		// add the record
		$this->L2_cache[ $group ][ $blogkey ] = (int)$expire;
		$this->maybe_write_cache();

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
	 * Retrieves the cache contents, if it exists. - wp_cache_get()
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
		if (empty( $group )) $group = 'default';

		if ($result = $this->get_cache($key, $group, true, $force)) {
			$found = true;
			return $result;
		}
		$found = false;
		return false;
	}


	/**
	 * Internal get_cache - get from L1 or L2 cache, with (get) or without (internal) counting hit/miss
	 *
	 * @param int|string $key	The key under which the cache contents are stored.
	 * @param string	 $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool		 $count Optional. Whether to add to the cache counts.
	 * @param bool	 	 $force Optional. Whether to force an update of the local cache
	 *						from the persistent cache. Default false.
	 */
	protected function get_cache( $key, $group = 'default', $count = false, $force = false )
	{
		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;

		// $force is intended to read from L2 cache.
		// 		Used by 'alloptions' when adding/removing a single option
		//		and _get_cron_lock() in wp-cron process.
		// Any L1 key exists only for the current process
		// whereas the L2 key could be updated by another process.
		$force = false;		// this doesn't work with 'alloptions'

		$return = $save = false;

		if ($force) {
			$save = $this->L1_cache[ $group ][ $blogkey ] ?? false;
			$return = $this->key_exists_database( $blogkey, $group, $count );
		}

		if (!$return) {
			$return = $this->key_exists( $blogkey, $group, $count );
		}

		if ($return && $this->L1_cache[ $group ][ $blogkey ]) {
			$return = ( is_object( $this->L1_cache[ $group ][ $blogkey ][ 'value' ] ) )
				? clone $this->L1_cache[ $group ][ $blogkey ][ 'value' ]
				: $this->L1_cache[ $group ][ $blogkey ][ 'value' ];
		}

		if ($save) {
			$this->L1_cache[ $group ][ $blogkey ] = $save;
		}

		return $return;
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

		$blogkeys	= [];
		$selects	= 0;
		foreach ( $values as $key => &$value ) {
			if ( $blogkey = $this->get_valid_key( $key, $group ) ) {
				if ($force || ! $this->key_exists_memory($blogkey, $group, true)) {
					$blogkeys[ $group ][] = $blogkey;
					$selects++;
				} else {
					$value = $this->L1_cache[ $group ][ $blogkey ][ 'value' ] ?? false;
				}
			}
		}

		$hits = 0;
		if ( $this->db && ! isset( $this->nonp_groups[ $group ] ) ) {
			if (!empty($blogkeys)) {
				foreach ($this->select_all( $blogkeys ) as $row) {
					$values[ $row['key'] ] = $row['value'];
					$hits++;
				}
				$this->addStats('L2 cache hits',$hits);
				$this->addStats('L2 cache misses',$selects - $hits);
			}
		}

		$this->addStats('cache hits',$hits,$group);
		$this->addStats('cache misses',count($values) - $hits);
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
		if ( $this->db && ! isset( $this->nonp_groups[ $group ] ) ) {
			// get L2 group into L1 (use 'like', don't overwrite L1)
			$hits = count(
				$this->select_all( [ $group => [$this->get_valid_key('%',$group)] ], true, false )
			);
			$this->addStats('L2 cache hits',$hits);
		}

		$return = [];
		foreach ($this->L1_cache[ $group ] as $key => $value) {
			if (!empty($value)) {
				$return[ substr($key,6) ] = $value['value'];
			}
		}
		$this->addStats('L1 cache hits',count($return) - $hits);
		$this->addStats('cache hits',count($return),$group);
		return $return;
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
		if (empty( $group )) $group = 'default';

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;
		if ( ! $this->key_exists( $blogkey, $group ) ) return false;

		$this->L1_cache[ $group ][ $blogkey ] = false;	// not in persistent cache

		// when not to write to db
		if ( ! $this->db || isset( $this->nonp_groups[ $group ] ) ) {
			return true;
		}

		$this->L2_cache[ $group ][ $blogkey ] = false;	// to be deleted from table
		$this->maybe_write_cache();

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
	 * @param string $group Where the cache contents are grouped. Default 'default'.
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
		if (empty( $group )) $group = 'default';

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;

		$value = max( 0, ( (int) $this->get( $key, $group ) + (int) $offset ) );

		$expire = ($this->key_exists_memory( $blogkey, $group ) > 0)
			? $this->L1_cache[ $group ][ $blogkey ][ 'expire' ]
			: 0;

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
	 * SQLite triggers truncate optomizer
	 *
	 * @param bool $force override disabling constant.
	 * @return bool sql result
	 */
	public function flush(bool $force = false): bool
	{
		if (! $force && ! $this->is_flush_enabled('full')) return false;

		$this->L1_cache = $this->L2_cache = array();

		if ( ! $this->db ) return false;

		do_action( 'qm/start', __METHOD__ );
		$this->delete_cache_file();
		do_action( 'qm/stop', __METHOD__ );

		return $this->connect_sqlite();
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
		if (! $force && ! $this->is_flush_enabled('group')) return false;

		static $stmt = null;

		$this->write_cache();
		unset( $this->L1_cache[ $group ] );

		if ( ! $this->db ) return false;

		do_action( 'qm/start', __METHOD__ );

		if (is_null($stmt)) {
			$stmt = $this->db->prepare("DELETE FROM wp_cache WHERE key LIKE :group;");
		}

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$blogkey = $this->get_valid_key('%',$group);
				$this->db->beginTransaction();
				$stmt->execute( ['group' => $group.'|'.$blogkey] );
				$this->db->commit();
				if ($stmt->rowCount()) {
					$this->error_log(__CLASS__,"cache flushed for group '{$group}', ".
						$stmt->rowCount()." records deleted");
				}
				$this->addStats("flushed {$group}",$stmt->rowCount());
				break;
			} catch ( Exception $ex ) {
				$this->db->rollBack();
				$this->error_log(__METHOD__,$ex);
				usleep($this->sleep_time);
			}
		}

		do_action( 'qm/stop', __METHOD__ );

		return (bool)$stmt;
	}


	/**
	 * Removes all cache items tagged with a blog number.
	 *
	 * @param string $blog current blog
	 * @param bool $force override disabling constant.
	 * @return bool sql result
	 */
	public function flush_blog( $blog_id = null, bool $force = false ): bool
	{
		if (! $force && ! $this->is_flush_enabled('blog')) return false;

		if (! $this->is_multisite) {
			return $this->flush();
		}

		static $stmt = null;

		$this->write_cache();
		$this->L1_cache = array();

		if ( ! $this->db ) return false;

		do_action( 'qm/start', __METHOD__ );

		if (!is_int($blog_id)) $blog_id = $this->blog_id;

		if (is_null($stmt)) {
			$stmt = $this->db->prepare("DELETE FROM wp_cache WHERE key LIKE :blog;");
		}

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->db->beginTransaction();
				$stmt->execute( ['blog' => '%|'.sprintf("%05d", $blog_id).'|%'] );
				$this->db->commit();
				if ($stmt->rowCount()) {
					$this->error_log(__CLASS__,"cache flushed for blog id {$blog_id}, ".
						$stmt->rowCount()." records deleted");
				}
				$this->addStats("flushed blog id {$blog_id}",$stmt->rowCount());
				break;
			} catch ( Exception $ex ) {
				$this->db->rollBack();
				$this->error_log(__METHOD__,$ex);
				usleep($this->sleep_time);
			}
		}

		do_action( 'qm/stop', __METHOD__ );

		return (bool)$stmt;
	}


	/**
	 * Clears the L1 cache of all data.
	 * Intended for use by long-running tasks to invalidate the memory cache
	 * without invalidating the persistent cache.
	 *
	 * @param bool $force (internal) override disabling constant and write to persistend cache before clearing.
	 * @return bool Always returns true.
	 */
	public function flush_runtime(bool $force = false): bool
	{
		if (! $force && ! $this->is_flush_enabled('runtime')) return false;

		do_action( 'qm/start', __METHOD__ );

		if ($force) { // internal, writes misses & cache
			$this->write_cache();
		}

		$this->L1_cache = $this->L2_cache = array();

		do_action( 'qm/stop', __METHOD__ );

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
	public function add_global_groups( $groups ): array
	{
		// EAC_OBJECT_CACHE_GLOBAL_GROUPS
		$defined_groups = $this->get_defined_groups('global');

		$groups = array_fill_keys( (array) $groups, true );
		$this->global_groups = array_merge( $this->global_groups, $defined_groups, $groups );

		return $this->global_groups;
	}


	/**
	 * Sets the list of non-persistent cache groups.
	 * from wp-includes/load.php wp_start_object_cache()
	 *
	 * @param string|string[] $groups List of groups that are non-persistent.
	 * @return array [group=>true,...]
	 */
	public function add_non_persistent_groups( $groups ): array
	{
		// EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS
		$defined_groups = $this->get_defined_groups('non_persistent');

		$groups = array_fill_keys( (array) $groups, true );
		$this->nonp_groups = array_merge( $this->nonp_groups, $defined_groups, $groups );

		return $this->nonp_groups;
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
		$defined_groups = $this->get_defined_groups('permanent');

		$groups = array_fill_keys( $groups, true );
		$this->perm_groups = array_merge( $this->perm_groups, $defined_groups, $groups );

		return $this->perm_groups;
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
		$defined_groups = $this->get_defined_groups('prefetch');

		$groups = array_fill_keys( (array) $groups, true );
		$this->prefetch_groups = array_merge( $this->prefetch_groups, $defined_groups, $groups );

		$this->set('prefetch_groups', $this->prefetch_groups, self::GROUP_ID, 0);
		return $this->prefetch_groups;
	}


	/**
	 * Get group keys as array from constant
	 *
	 * @param string $constant unique part of group constant name
	 * @return array [group=>true,...]
	 */
	private function get_defined_groups( string $constant ): array
	{
		$groups = [];
		if ($constant = $this->get_defined_option("{$constant}_groups", 'is_array')) {
			if ( !empty( $constant ) ) {
				$groups = array_fill_keys( $constant, true );
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
		if ( ! $this->db ) return;

		$blogkeys = [];
		$this->prefetch_groups = $this->get_cache('prefetch_groups',self::GROUP_ID);
		foreach (array_keys($this->prefetch_groups) as $group) {
			if (! isset( $this->nonp_groups[ $group ] ) ) {
				$blogkeys[ $group ] = [ $this->get_valid_key('%',$group) ];
			}
		}

		if (!empty($blogkeys)) {
			$this->addStats("L2 pre-fetched (+)", count($this->select_all( $blogkeys, true )));
		}
	}


	/**
	 * Load cached misses.
	 *
	 */
	private function load_cache_misses(): void
	{
		if (! $this->prefetch_misses) return;

		// load (or re-load) prior cache misses into L1 cache
		if ($misses = $this->get_cache('cache-misses',self::GROUP_ID)) {
			$this->L2_missed_stats[$this->blog_id] = 0;
			foreach ($misses as $group => $keys) {
				if (!isset( $this->L1_cache[ $group ] )) $this->L1_cache[ $group ] = [];
				$keys = array_diff_key($keys, $this->L1_cache[ $group ]);
				$this->L1_cache[ $group ] = array_merge($keys, $this->L1_cache[ $group ]);
			//	$this->addStats("L2 pre-fetched (-)", count($keys));
				$this->L2_missed_stats[$this->blog_id] += count($keys);
			}
		}
		// remove from L1 cache
		$blogkey = $this->get_valid_key('cache-misses',self::GROUP_ID);
		unset( $this->L1_cache[ self::GROUP_ID ][ $blogkey ] );
	}


	/**
	 * Save cached misses.
	 *
	 */
	private function save_cache_misses(): void
	{
		if (! $this->prefetch_misses || empty($this->L1_cache)) return;

		$misses = array();
		foreach ($this->L1_cache as $group => $keys) {
			if ($keys = array_filter( $keys, function($v) {return $v === false;} )) {
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
			$this->addStats("blog switches",1);
		}
		$this->blog_id = (int)$blog_id;
		$this->load_prefetch_groups();
		$this->load_cache_misses();
		$this->wp_transients( 'import', $this->blog_id );

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
	 * close the persistent cache (wp_cache_close)
	 *
	 */
	public function close(): void
	{
		if (! $this->db ) return;

		do_action( 'qm/start', __METHOD__ );

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
		    $start_time = microtime( true );
			$this->probability = $this->probability + ($this->probability % 2); // even number
			$probability = ($network_requests % $this->probability);

			if ($probability == 0)												//	checkpoint/optimize
			{
				$limit = ((int)$this->delayed_writes >= 10) ? $this->delayed_writes : 32;
				$stmt = $this->db->prepare(
					"DELETE FROM wp_cache WHERE expire > 0 AND expire < {$this->time_now} LIMIT {$limit};"
				);
				try {
					$this->db->beginTransaction();
					$stmt->execute();
					$this->db->commit();
					if ($stmt->rowCount() > 0) {
						$this->db->exec("
							PRAGMA incremental_vacuum;
							PRAGMA optimize;
						");
					}
					$message = sprintf("garbage collection deleted %d rows (%01.4f)", $stmt->rowCount(), microtime( true ) - $start_time);
					$this->error_log(__CLASS__,$message);
				} catch ( Exception $ex ) {
					$this->db->rollBack();
					$this->error_log(__METHOD__,$ex);
				}
			}
		}

		if ($this->is_actionable() && $this->display_stats)
		{
			$probability = ($site_requests % $this->display_stats);

			if ($probability == 0)												// sampling
			{
				$this->set('sample', $this->getStats(), self::GROUP_ID, 0);
			//	$message = sprintf("request (%d/%d) sampling (%01.4f)",
			//		$site_requests,$this->display_stats, microtime( true ) - $start_time
			//	);
			//	$this->error_log(__CLASS__,$message);
			}
		}

		// write the cached records
		$this->write_cache();

		$this->db = null;

		do_action( 'qm/stop', __METHOD__ );
	}


	/**
	 * is a PHP request (not ajax, not image, etc.)
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
	 * temporarily set or reset delayed_writes.
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
	 * write the persistent cache to disk when db cache is full
	 *
	 */
	private function maybe_write_cache(): bool
	{
		switch ( (int)$this->delayed_writes ) {
			case 0:		// not delayed
				return $this->write_cache();
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
	 * get the number of records waiting to be updated or deleted
	 *
	 */
	private function pending_writes(): int
	{
		return count($this->L2_cache,COUNT_RECURSIVE) - count($this->L2_cache);
	}


	/**
	 * write the persistent cache to disk
	 *
	 */
	private function write_cache(): bool
	{
		// find and cache all cache misses - not in sqlite db
		$this->save_cache_misses();

		if (! $this->db || empty($this->L2_cache)) {
			return false;
		}

		$write = $delete = array();

		// find all writes and deletes
		foreach ($this->L2_cache as $group => $updates) {
			foreach ($updates as $blogkey => $expire) {
				if ($expire !== false) {
					$write[] = array(
							$group.'|'.$blogkey,
							maybe_serialize($this->L1_cache[ $group ][ $blogkey ][ 'value' ] ?? null),
							( ($expire) ? $this->time_now+$expire : 0 )
					);
				} else {
					$delete[] = $group.'|'.$blogkey;
				}
			}
		}

		$this->L2_cache = array();

		// replace records
		if (!empty($write)) {
			$stmt = $this->db->prepare(
				"INSERT INTO wp_cache (key, value, expire)" .
				" VALUES ".rtrim(str_repeat("(?,?,?),", count($write)),',') .
				" ON CONFLICT (key) DO UPDATE".
				" SET value = excluded.value, expire = excluded.expire;"
			);
			$retries = 0;
			while ( ++$retries <= $this->max_retries ) {
				try {
					$this->db->beginTransaction();
					$stmt->execute(array_merge(...$write));
					$this->addStats('L2 updates',$stmt->rowCount());
					$this->db->commit();
					$this->addStats('L2 commits',1);
					break;
				} catch ( Exception $ex ) {
					$this->db->rollBack();
					$this->error_log(__METHOD__,$ex);
					usleep($this->sleep_time);
				}
			}
		}

		// delete records
		if (!empty($delete)) {
			$stmt = $this->db->prepare(
				"DELETE FROM wp_cache".
				" WHERE key in (".rtrim(str_repeat("?,", count($delete)),',').")"
			);
			$retries = 0;
			while ( ++$retries <= $this->max_retries ) {
				try {
					$this->db->beginTransaction();
					$stmt->execute($delete);
					$this->addStats('L2 deletes',$stmt->rowCount());
					$this->db->commit();
					$this->addStats('L2 commits',1);
					break;
				} catch ( Exception $ex ) {
					$this->db->rollBack();
					$this->error_log(__METHOD__,$ex);
					usleep($this->sleep_time);
				}
			}
		}

		return true;
	}


	/**
	 * Delete the L2 cache file(s).
	 * Since we load early, WP_Filesystem is probably not available
	 *
	 */
	public function delete_cache_file(): void
	{
		$this->db = null;

		$cacheName = trailingslashit($this->cache_dir) . $this->cache_file;
		foreach ( [ '', '-journal', '-shm', '-wal' ] as $ext ) {
			if (file_exists($cacheName.$ext)) {
				unlink($cacheName.$ext);
			}
		}
		$this->error_log(__CLASS__,"L2 cache deleted");
	}


	/**
	 * Callable function to vacuum/optimize database.
	 * scedule with delete_expired_transients
	 *
	 */
	public function optimize(): void
	{
		if (! $this->db ) return;

		$start_time = microtime( true );
		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->db->beginTransaction();
				$count = $this->db->exec("
					DELETE FROM wp_cache WHERE expire > 0 AND expire < {$this->time_now};
				");
				$this->db->commit();
				// cannot VACUUM from within a transaction
				$this->db->exec("
					PRAGMA auto_vacuum = INCREMENTAL;
					VACUUM;
					PRAGMA optimize;
				");
				$message = sprintf("cache optimization deleted %d rows before vacuum/optimize (%01.4f)", $count, microtime( true ) - $start_time);
				$this->error_log(__CLASS__,$message);
				break;
			} catch ( Exception $ex ) {
				$this->db->rollback();
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
			do_action( "qm/{$class}", $source.': '.$message );
			do_action( "eacDoojigger_log_{$class}",$message,$source );
		}

		$class = "E_USER_".strtoupper($class);
		if (defined($class) && (constant($class) & error_reporting())) {
			\error_log($source.': '.$message);
		}
	}


	/*
	 * API methods  - counts/stats
	 */


	/**
	 * Update counters
	 *
	 * @param string $countId the counter to update
	 * @param int|bool $n the count to add (+1)
	 * @param string $groupId also update the group counter
	 */
	private function addStats(string $countId, $n, string $groupId=null)
	{
		if ($countId) {
			if (!isset($this->cache_stats[$countId])) {
				$this->cache_stats[$countId] = 0;
			}
			$this->cache_stats[$countId] += (int)$n;
		}
		if ($groupId) {
			if (!isset($this->group_stats[$groupId])) {
				$this->group_stats[$groupId] = 0;
			}
			$this->group_stats[$groupId] += (int)$n;
		}
	}


	/**
	 * Echoes the stats of the caching (similar to WP default cache).
	 * Called by outside actors (e.g. debug bar)
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 */
	public function stats(): void
	{
		$stats = $this->getStats();

		echo "<p>";
		echo "<strong>Cache Hits:</strong> ".number_format($this->cache_stats['cache hits'],0)."<br />";
		echo "<strong>Cache Misses:</strong> ".number_format($this->cache_stats['cache misses'])."<br />";
		echo "<strong>Cache Ratio:</strong> ".
			esc_attr( $this->cache_hit_ratio($this->cache_stats['cache hits'],$this->cache_stats['cache misses']) );
		echo "</p>\n";

		echo "<p><strong>Cache Counts:</strong></p><ul>";
		foreach ($stats['cache'] as $group => $cache) {
			if ($cache && $cache[0]) {
				echo '<li>' . esc_attr( $group ) .
					 ' - ' . number_format( $cache[0], 0 );
			}
		}

		echo "</ul>\n";
		echo "<p><strong>Cache Groups:</strong></p><ul>";
		foreach ($stats['cache-groups'] as $group => $cache) {
			if ($cache && $cache[0]) {
				echo '<li>' . esc_attr( $group ) .
					 ' - ' . number_format( $cache[0], 0 ).
					 ' ( ' . number_format( $cache[1] / KB_IN_BYTES, 2 ) . 'k )</li>';
			}
		}
		echo "</ul>\n";

		echo "<p>* ".esc_attr(self::PLUGIN_NAME)." v".esc_attr(EAC_OBJECT_CACHE_VERSION)."</p>\n";
	}


	/**
	 * Echos the stats of the caching, formatted table.
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 *
	 * @param bool $useSample use last sampling
	 */
	public function htmlStats($useSample = false): void
	{
		$stats = ($useSample) ? $this->getLastSample() : $this->getStats();

		echo "\n<div class='wp-object-cache'>";

		if (isset($stats['id'])) {
			echo "<p>";
			foreach ($stats['id'] as $name => $value) {
				echo esc_attr($name).": ".esc_attr($value)."<br />";
			}
			echo "</p>";
		}

		echo "\n<table class='wp-object-cache'>";

		if (isset($stats['cache'])) {
			echo "\n<tr><th colspan='4'><p>Cache Counts:</p></th></tr>";
			foreach ($stats['cache'] as $name => $value) {
				if ($value && $value[0]) {
					echo '<tr><th>'.esc_attr( $name ).'</th>'.
							 '<td>'.number_format($value[0], 0).'</td>'.
							 '<td>'.esc_attr($value[1]).'</td>'.
							 '<td></td></tr>';
				}
			}
		}

		if (isset($stats['cache-groups'])) {
			echo "\n<tr><th colspan='4'><p>L1 (In-Memory) Cache Groups:</p></th></tr>";
			foreach ($stats['cache-groups'] as $name => $value) {
				if ($value && $value[0]) {
					echo '<tr><th>'.esc_attr( $name ).'</th>'.
						 '<td>'	 .number_format( $value[0], 0) . '</td>'.
						 '<td> ~'.number_format( $value[1] / KB_IN_BYTES, 2 ) . 'k</td>'.
						 '<td> +'.number_format( $value[2], 0 ) . '</td></tr>';
				}
			}
		}

		if (isset($stats['database-groups'])) {
			echo "\n<tr><th colspan='4'><p>L2 (Persistent) Cache Groups:</p></th></tr>";
			foreach ($stats['database-groups'] as $name => $value) {
				if ($value && $value[0]) {
					echo '<tr><th>'.esc_attr( $name ).'</th>'.
						 '<td>'	 .number_format( $value[0], 0) . '</td>'.
						 '<td> ~'.number_format( $value[1] / KB_IN_BYTES, 2 ) . 'k</td>'.
						 '<td></td></tr>';
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
	 * @return array
	 */
	public function getStats(): array
	{
		static $stats = [];
		if (!empty($stats)) return $stats;

		do_action( 'qm/start', __METHOD__ );

		$this->write_cache();

		$stats = array();
		$stats['id'] = array(
			self::PLUGIN_NAME	=> EAC_OBJECT_CACHE_VERSION,
			'cache file'		=> ($this->db)
					? '~/'.trailingslashit(basename($this->cache_dir)) . $this->cache_file
					: 'memory-only',
			'peak memory used'	=> round((memory_get_peak_usage(false) / MB_IN_BYTES)).'M of '.ini_get('memory_limit'),
			'sample time'		=> wp_date('c'),
		);
		if (isset($_SERVER['REQUEST_URI'])) {
			$stats['id']['sample uri']	= sanitize_url( $_SERVER['REQUEST_URI'] );
		}

		// addStats() counters
		$stats['cache'] 				= $this->getStatsCache();

		// current cache contents by group
		$stats['cache-groups'] 			= $this->getStatsGroups();

		// database contents - all groups
		if ($this->db) {
			$stats['database-groups'] 	= $this->getStatsDB();
		}

		do_action( 'qm/stop', __METHOD__ );
		return $stats;
	}


	/**
	 * Returns the current cache stats..
	 *
	 * @param bool $log add Query Monitor/eacDoojigger logging
	 * @return array
	 */
	public function getStatsCache($log=false): array
	{
		$stats = array();

		$this->cache_stats["L2 pre-fetched (-)"] = array_sum($this->L2_missed_stats);

		foreach ($this->cache_stats as $name => $count) {
			$stats[$name] = [$count,''];
		}
		// add cache hit ratios
		$stats['cache hits'][1] =
			$this->cache_hit_ratio($this->cache_stats['cache hits'],$this->cache_stats['cache misses']);
		$stats['L1 cache hits'][1] =
			$this->cache_hit_ratio($this->cache_stats['L1 cache hits'],$this->cache_stats['L1 cache misses']);
		$stats['L2 cache hits'][1] =
			$this->cache_hit_ratio($this->cache_stats['L2 cache hits'],$this->cache_stats['L2 cache misses']);

		if ($log) {
			$qmlog =  __CLASS__." on ".current_action()."\n";
			foreach ($stats as $group => $cache) {
				if ($cache && $cache[0]) {
					$qmlog .= "\t" . esc_attr( $group ) . ': ' . number_format( $cache[0], 0 ) . "\n";
				}
			}
			do_action( 'qm/info', $qmlog );
			$qmlog = 'cache hits: ' . number_format( $stats['cache hits'][0], 0).', ' .
					 '(L1: ' 		. number_format( $stats['L1 cache hits'][0], 0).', ' .
					 'L2: ' 		. number_format( $stats['L2 cache hits'][0], 0).'), ' .
					 'misses: ' 	. number_format( $stats['cache misses'][0], 0).', ' .
					 'ratio: ' 		. $stats['cache hits'][1];
			do_action("eacDoojigger_log_notice",$qmlog,__CLASS__);
		}

		return $stats;
	}


	/**
	 * Returns the current cache stats..
	 *
	 * @return array
	 */
	public function getStatsGroups(): array
	{
		$stats = array();

		foreach ( $this->L1_cache as $group => $cache ) {
			$cache = array_filter($cache, function($v){return $v !== false;});
			$stat = $this->group_stats[$group] ?? 0;
			if (!empty($cache) && $stat > 0) {
				$count = count($cache);
				$size = 0;
				foreach ($cache as $k => $v) {
					$size += (strlen($k)+strlen(maybe_serialize($v)));
				}
				$stats[$group] = [$count, $size, $stat];
			}
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
	 * Returns the stats of the L2 database.
	 *
	 * @return array
	 */
	public function getStatsDB(): array
	{
		if (!$this->db) return ['Total'=>[0,0,0]];

		$stats = array();

		$blog_id = sprintf("%05d", $this->blog_id);
		if ($result = $this->db->query("
			SELECT SUBSTR(key,0,INSTR(key,'|')) as name,
				   SUBSTR(key,INSTR(key,'|')+1,5) as blog,
				   COUNT(*) as count,
				   SUM(LENGTH(key)*2 + LENGTH(value) + LENGTH(expire)*2) as size
			FROM wp_cache
			 WHERE (expire = 0 OR expire >= {$this->time_now})
			  AND (key LIKE '%|00000|%' or key LIKE '%|{$blog_id}|%')
			 GROUP BY name;"))
		{
			while ($row = $result->fetch()) {
				$stats[ $row['name'] ] = [$row['count'], $row['size']];
			}
			$result->closeCursor();
			ksort($stats);
			$cacheName	= trailingslashit($this->cache_dir) . $this->cache_file;
			$stats['Total'] = [
				array_sum(array_column($stats,0)),
				array_sum(array_column($stats,1)),
				filesize($cacheName),
			];
		}

		return $stats;
	}


	/**
	 * Returns the cache hit ratio (formatted)
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
		if ( $row = $this->get_cache( "sample", self::GROUP_ID ) ) {
			return $row;
		}
		return $this->getStats();
	}


	/*
	 * Install/Uninstall
	 */


	/**
	 * install (from connect for new db file)
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
				$this->db->exec(
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
		$message = __CLASS__.': L2 cache initialized';
		\error_log($message);
		return true;
	}


	/**
	 * uninstall (from external extension)
	 *
	 * @param bool $complete delete the cache file
	 */
	public function uninstall(bool $complete = true): bool
	{
		$this->wp_transients( 'export', $this->blog_id );
		wp_using_ext_object_cache( false );
		if ($complete) {
			$this->delete_cache_file();
		}
		return true;
	}


	/**
	 * alias to uninstall (from external extension / scheduled event)
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
	 * import/export WP MySQL transients
	 *
	 */
	private function wp_transients(string $action, int $blog_id): int
	{
		switch ($action) {
			case 'import':
				if ($count = $this->import_wp_transients()) {
					$this->cache_stats['transients imported'] = 0;
					$this->error_log(__CLASS__,sprintf("%d transients imported",$count));
				}
				return $count;
			case 'export':
				if ($count = $this->export_wp_transients()) {
					$this->cache_stats['transients exported'] = 0;
					$this->error_log(__CLASS__,sprintf("%d transients exported",$count));
				}
				return $count;
		}
		return 0;
	}


	/**
	 * import existing MySQL transients
	 *
	 */
	private function import_wp_transients(): int
	{
		global $wpdb;

		// do we have db connections
		if (! $this->db || ! $wpdb) return 0;

		// see if we've done this already
		if ( $this->get_cache( 'imported-transients', self::GROUP_ID ) ) {
			return 0;
		}

		// write all in one transaction
		$this->set_delayed_writes( true );

		// import transients from options table

		// so we don't try to use this cache
		wp_using_ext_object_cache( false );
		$installing = wp_installing( true );

		$optionSQL =
			"SELECT option_name as name, option_value as value".
			" FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s";

		$transients = $wpdb->get_results(
			$wpdb->prepare($optionSQL,'_transient_%','_transient_timeout_%')
		);

		if ($transients && !is_wp_error($transients)) {
			foreach ($transients as $row) {
				$this->pull_wp_transient($row,'transient');
			}
		}

		// import site transients from options or sitemeta table

		$siteSQL = ($this->is_multisite)
			? "SELECT meta_key as name, meta_value as value".
			  " FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_key NOT LIKE %s"
			: $optionSQL;

		$transients = $wpdb->get_results(
			$wpdb->prepare($siteSQL,'_site_transient_%','_site_transient_timeout_%')
		);

		if ($transients && !is_wp_error($transients)) {
			foreach ($transients as $row) {
				$this->pull_wp_transient($row,'site-transient');
			}
		}

		wp_using_ext_object_cache( true );
		wp_installing( $installing );

		$this->set('imported-transients', [ wp_date('c'),$this->cache_stats['transients imported'] ], self::GROUP_ID, 0);
		$this->set_delayed_writes();

		return $this->cache_stats['transients imported'];
	}


	/**
	 * pull a single transient from WP
	 *
	 * @param object $row transient record
	 * @param string $group 'transient' or 'site-transient'
	 */
	private function pull_wp_transient(object $row, string $group): void
	{
		$key = str_replace(['_site_transient_','_transient_'],'',$row->name);

		if ($group == 'transient') {
			$expire = get_option('_transient_timeout_'.$key,0);
		//	delete_transient($key);
		} else {
			$expire = get_site_option('_site_transient_timeout_'.$key,0);
		//	delete_site_transient($key);
		}

		if ($expire) {
			if ($expire <= $this->time_now) return;
			$expire = $expire - $this->time_now;
		}

		$this->set($key, maybe_unserialize($row->value), $group, $expire);
		$this->addStats('transients imported',1);
	}


	/**
	 * export existing transients back to MySQL
	 *
	 */
	private function export_wp_transients(): int
	{
		if (! $this->db) return 0;
		$this->write_cache();

		// so we don't try to use this cache
		wp_using_ext_object_cache( false );

		$blogkeys = [
			'transient' 		=> [$this->get_valid_key('%','transient')],
			'site-transient' 	=> [$this->get_valid_key('%','site-transient')]
		];
		$transients = $this->select_all( $blogkeys, true );
		foreach ($transients as $row) {
			$this->push_wp_transient($row,);
		}

		wp_using_ext_object_cache( true );

		return $this->cache_stats['transients exported'] ?? 0;
	}


	/**
	 * push a single transient to WP
	 *
	 * @param object $row transient record
	 */
	private function push_wp_transient(array $row): void
	{
		if ($row['expire'] >= 0) {
			if ($row['group'] == 'transient') {
				set_transient($row['key'],$row['value'],$row['expire']);
			} else {
				set_site_transient($row['key'],$row['value'],$row['expire']);
			}
			$this->addStats('transients exported',1);
		}
	}
}


/*
 *
 * global wp functions (wp-include/cache.php)
 * WP_PLUGIN_DIR not yet set
 */
require __DIR__.'/plugins/eacobjectcache/src/wp-cache.php';
