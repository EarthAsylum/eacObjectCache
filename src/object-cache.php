<?php
/**
 * {eac}Doojigger Object Cache - SQLite powered WP_Object_Cache Drop-in.
 *
 * Plugin Name:			{eac}ObjectCache
 * Description:			{eac}Doojigger Object Cache - SQLite powered WP_Object_Cache Drop-in
 * Version:				1.0.3
 * Requires at least:	5.5.0
 * Tested up to:		6.4
 * Requires PHP:		7.4
 * Plugin URI:			https://eacdoojigger.earthasylum.com/eacobjectcache/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 */

defined( 'ABSPATH' ) || exit;

define('EAC_OBJECT_CACHE_VERSION','1.0.3');

/**
 *
 * Derived from WordPress core WP_Object_Cache (wp-includes/class-wp-object-cache.php)
 *
 *
 * To set the location of the SQLite database		(default: WP_CONTENT_DIR.'/cache'):
 *		define('EAC_OBJECT_CACHE_DIR','/full/path/to/folder');
 *
 * To set the name of the SQLite database			(default: '.eac_object_cache.sqlite'):
 *		define('EAC_OBJECT_CACHE_FILE','filename.sqlite');
 *
 * To set SQLite journal mode						(default: 'WAL')
 *		define('EAC_OBJECT_CACHE_JOURNAL_MODE','DELETE' | 'TRUNCATE' | 'PERSIST' | 'MEMORY' | 'WAL' | 'OFF')
 *
 * To set SQLite timeout (in seconds)				(default: 3)
 *		define('EAC_OBJECT_CACHE_TIMEOUT',int);
 *
 * To set SQLite RETRIES							(default: 3)
 *		define('EAC_OBJECT_CACHE_RETRIES',int);
 *
 * To set delayed writes							(default: 32)
 *		define('EAC_OBJECT_CACHE_DELAYED_WRITES', true | false | int);
 *
 * To set the default expiration time (in seconds)	(default: 0 [never])
 *		define('EAC_OBJECT_CACHE_DEFAULT_EXPIRE', -1 | 0 | int);
 *
 * To enable/disable pre-fetching of cache misses
 *		define('EAC_OBJECT_CACHE_PREFETCH_MISSES', true | false);
 *
 * To set maintenance/sampling probability			(default: 100)
 *		define('EAC_OBJECT_CACHE_PROBABILITY',int);
 *
 * To set groups as global (not site-specific in multisite)
 *		define('EAC_OBJECT_CACHE_GLOBAL_GROUPS', [ 'groupA', 'groupB', ... ]);
 *
 * To set groups as non-persistant (not stored in the SQLite table)
 *		define('EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS', [ 'groupA', 'groupB', ... ]);
 *
 * To set groups as permanent (can have no expiration)
 *		define('EAC_OBJECT_CACHE_PERMANENT_GROUPS', [ 'groupA', 'groupB', ... ]);
 *
 * To pre-fetch group(s)
 *		define('EAC_OBJECT_CACHE_PREFETCH_GROUPS', [ 'groupA', 'groupB', ... ]);
 *
 *
 * Get stats :
 *
 *		$wp_object_cache->htmlStats();
 *			outputs an html table of current stats
 *			get (or set) $wp_object_cache->statsCSS to style
 *		$wp_object_cache->getStats();
 *			returns an array of current stats
 *		$wp_object_cache->getLastSample();
 *			returns an array of stats from the last sample saved (or current)
 *
 */

/**
 * The WordPress Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 */

class WP_Object_Cache
{
	/**
	 * this plugin id
	 *
	 * @var string
	 */
	const PLUGIN_NAME		= '{eac}ObjectCache';

	/**
	 * internal group id
	 *
	 * @var string
	 */
	const GROUP_ID			= '@object-cache';

	/**
	 * path name to cache folder (EAC_OBJECT_CACHE_DIR)
	 *
	 * @var string
	 */
	private $cache_folder	= WP_CONTENT_DIR.'/cache';

	/**
	 * name of cache file (EAC_OBJECT_CACHE_FILE)
	 *
	 * @var string
	 */
	private $cache_file		= '.eac_object_cache.sqlite';

	/**
	 * SQLite journal mode (EAC_OBJECT_CACHE_JOURNAL_MODE).
	 *
	 * @var string
	 */
	private $journal_mode	= 'WAL';

	/**
	 * SQLite timeout in seconds (EAC_OBJECT_CACHE_TIMEOUT).
	 *
	 * @var bool|int
	 */
	private $pdo_timeout	= 3;

	/**
	 * open/write/delete retries (EAC_OBJECT_CACHE_RETRIES).
	 * can be changed with $wp_object_cache->max_retries
	 *
	 * @var int
	 */
	public $max_retries		= 3;

	/**
	 * sleep between retries (micro-seconds) 1/10-second.
	 *
	 * @var int
	 */
	private $sleep_time		= 100000;

	/**
	 * use delayed writes until shutdown or n records (EAC_OBJECT_CACHE_DELAYED_WRITES).
	 * can be changed with $wp_object_cache->delayed_writes
	 *
	 * @var bool|int
	 */
	public $delayed_writes	= 32;

	/**
	 * if expiration is not set, use this (EAC_OBJECT_CACHE_DEFAULT_EXPIRE).
	 * can be changed with $wp_object_cache->default_expire
	 * -1 = don't cache (persistent), 0 = no expiration, int = seconds to expiration
	 *
	 * @var int
	 */
	public $default_expire	= 0;

	/**
	 * pre-fetch cache misses (EAC_OBJECT_CACHE_PREFETCH_MISSES).
	 *
	 * @var bool
	 */
	private $prefetch_misses = true;

	/**
	 * the probablity of running maintenance functions (EAC_OBJECT_CACHE_PROBABILITY).
	 * can be changed with $wp_object_cache->gc_probability
	 *
	 * @var int
	 */
	public $gc_probability	= 100;

	/**
	 * When true, outputs an admin notice with htmlStats().
	 * can be changed with $wp_object_cache->display_stats
	 *
	 * @var bool
	 */
	public $display_stats	= false;

	/**
	 * display errors in an admin notice.
	 * can be changed with $wp_object_cache->display_errors
	 *
	 * @var string
	 */
	public $display_errors	= false;

	/**
	 * log errors in eacDoojigger log.
	 * can be changed with $wp_object_cache->log_errors
	 *
	 * @var string
	 */
	public $log_errors		= false;

	/**
	 * Holds the cached objects (group => [key => [value=>,expire=>] | false]).
	 *	false = tried but not in persistent cache, don't try again.
	 *
	 * @var array
	 */
	private $L1_cache		= array();

	/**
	 * Holds db writes objects (group => [key => expire | false]).
	 *	false = to be deleted from persistent cache.
	 *
	 * @var array
	 */
	private $L2_cache		= array();

	/**
	 * Memory/persistent cache stats
	 *
	 * @var int[]
	 */
	private $cache_stats	= array(
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
		'L2 commits'			=> 0,	// number of sql transaction commits
		'L2 updated'			=> 0,	// number of records updated
		'L2 deleted'			=> 0,	// number of records deleted
	);

	/**
	 * Cache hits by group (group => count)
	 *
	 * @var int[]
	 */
	private $group_stats	= array();

	/**
	 * List of global/site-wide cache groups.
	 * (EAC_OBJECT_CACHE_GLOBAL_GROUPS, wp_cache_add_global_groups)
	 *
	 * @var string[]
	 */
	private $global_groups	= array();

	/**
	 * List of non-persistent cache groups.
	 * (EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS, wp_cache_add_non_persistent_groups)
	 *
	 * @var string[]
	 */
	private $nonp_groups	= array();

	/**
	 * List of permanent cache groups, no expiration required.
	 * (EAC_OBJECT_CACHE_PERMANENT_GROUPS, wp_cache_add_permanent_groups)
	 *
	 * @var string[]
	 */
	private $perm_groups	= array(
		self::GROUP_ID		=> true,
		'transient'			=> true,
		'site-transient'	=> true,
	);

	/**
	 * List of pre-loaded cache groups.
	 * (EAC_OBJECT_CACHE_PREFETCH_GROUPS, wp_cache_add_prefetch_groups)
	 *
	 * @var string[]
	 */
	private $prefetch_groups = array(
		self::GROUP_ID		=> true,
	);

	/**
	 * Recommended style for stats html table.
	 *
	 * @var string
	 */
	public $statsCSS		=
		"table.wp-object-cache th {text-align: left; font-weight: normal;}".
		"table.wp-object-cache th p {font-weight: bold;}".
		"table.wp-object-cache td {text-align: right; padding-left: 1em;}";

	/**
	 * Set time now.
	 *
	 * @var string
	 */
	private $time_now;

	/**
	 * The blog prefix to prepend to keys in non-global groups.
	 *
	 * @var string
	 */
	private $blog_id;

	/**
	 * Holds the value of is_multisite().
	 *
	 * @var bool
	 */
	private $multisite;

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
	 * Constructor, sets up object properties, SQLite database (wp_cache_init).
	 *
	 */
	public function __construct()
	{
		$this->time_now 	= time();
		$this->multisite 	= is_multisite();
		$this->blog_id 		= get_current_blog_id();

		$this->get_defined_options();

		// we can still function as a memory-only cache on failure
		if (! $this->connect_sqlite() ) {
			$this->delete_cache_file();
			$this->connect_sqlite();
		}

		// as early as possible, safe on multisite
		add_action('muplugins_loaded',function()
			{
				// because these are non-standard methods, nothing outside calls them
				$this->add_permanent_groups();
				$this->add_prefetch_groups();
				$this->load_prefetch_groups();
				$this->import_wp_transients();
			}
		);

		if (is_admin())
		{
			$this->display_errors = true;
			add_action( 'admin_footer', function()
				{
					// this gets moved to the top of an admin page
					if ($this->display_stats && is_admin_bar_showing()) {
						echo "\n<style>".esc_html($this->statsCSS)."</style>\n";
						echo "<div class='object-cache-notice notice notice-info'>";
						echo "<details><summary>Object Cache...</summary>";
						$this->htmlStats( ($this->display_stats=='sample') );
						echo "</details></div>\n";
					}
				},1000
			);
		}
	}


	/**
	 * get defined constants
	 *
	 */
	private function get_defined_options(): void
	{
		// cache directory (/wp-content/cache)
		if (defined( 'EAC_OBJECT_CACHE_DIR' ) && is_string( EAC_OBJECT_CACHE_DIR )) {
			$this->cache_folder = EAC_OBJECT_CACHE_DIR;
		}

		// cache file name (.eac_object_cache.sqlite)
		if (defined( 'EAC_OBJECT_CACHE_FILE' ) && is_string( EAC_OBJECT_CACHE_FILE )) {
			$this->cache_file = EAC_OBJECT_CACHE_FILE;
		}

		// SQLite journal mode	(DELETE | TRUNCATE | PERSIST | MEMORY | WAL | OFF)
		if (defined( 'EAC_OBJECT_CACHE_JOURNAL_MODE' ) && is_string( EAC_OBJECT_CACHE_JOURNAL_MODE )) {
			$this->journal_mode = EAC_OBJECT_CACHE_JOURNAL_MODE;
		}

		// PDO timeout (int)
		if (defined( 'EAC_OBJECT_CACHE_TIMEOUT' ) && is_int( EAC_OBJECT_CACHE_TIMEOUT )) {
			$this->pdo_timeout = EAC_OBJECT_CACHE_TIMEOUT;
		}

		// database retries (int)
		if (defined( 'EAC_OBJECT_CACHE_RETRIES' ) && is_int( EAC_OBJECT_CACHE_RETRIES )) {
			$this->max_retries = EAC_OBJECT_CACHE_RETRIES;
		}

		// delayed writes (true|false|int)
		if (defined( 'EAC_OBJECT_CACHE_DELAYED_WRITES' )) {
			$this->delayed_writes = EAC_OBJECT_CACHE_DELAYED_WRITES;
		}

		// default expiration (-1|0|int)
		if (defined( 'EAC_OBJECT_CACHE_DEFAULT_EXPIRE' ) && is_int( EAC_OBJECT_CACHE_DEFAULT_EXPIRE )) {
			$this->default_expire = EAC_OBJECT_CACHE_DEFAULT_EXPIRE;
		}

		// pre-fetch cache misses (bool)
		if (defined( 'EAC_OBJECT_CACHE_PREFETCH_MISSES' ) && is_bool( EAC_OBJECT_CACHE_PREFETCH_MISSES )) {
			$this->prefetch_misses = EAC_OBJECT_CACHE_PREFETCH_MISSES;
		}

		// maintenance/sampling probability (int)
		if (defined( 'EAC_OBJECT_CACHE_PROBABILITY' ) && is_int( EAC_OBJECT_CACHE_PROBABILITY )) {
			$this->gc_probability = EAC_OBJECT_CACHE_PROBABILITY;
		}

		/* additional constants used...
			EAC_OBJECT_CACHE_GLOBAL_GROUPS (array)
			EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS (array)
			EAC_OBJECT_CACHE_PERMANENT_GROUPS (array)
			EAC_OBJECT_CACHE_PREFETCH_GROUPS (array)
		 */
	}


	/**
	 * open the SQLite database connection
	 *
	 */
	private function connect_sqlite(): bool
	{
		$cacheName	= trailingslashit($this->cache_folder) . $this->cache_file;
		$create		= !file_exists($cacheName);

		$retries = 0;
		while ( ++$retries <= $this->max_retries ) {
			try {
				$this->db = new \PDO("sqlite:{$cacheName}",null,null,[
					PDO::ATTR_TIMEOUT				=> $this->pdo_timeout,
					PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
				]);
				$this->db->exec("
					PRAGMA encoding = 'UTF-8';
					PRAGMA journal_mode = {$this->journal_mode};
					PRAGMA page_size = 4096;
					PRAGMA synchronous = OFF;
				");
				break;
			} catch ( Exception $ex ) {
				$this->error_log(__METHOD__,$ex);
				$this->db = null;
				usleep($this->sleep_time);
			}
		}

		if ( ! $this->db ) return false;

		// new file, create table
		if ($create) {
			try {
				$this->db->exec("
					CREATE TABLE IF NOT EXISTS wp_cache (
						key TEXT NOT NULL COLLATE BINARY PRIMARY KEY, value BLOB, expire INT
					);
					CREATE INDEX IF NOT EXISTS expire ON wp_cache (expire);
				");
				$this->error_log(__METHOD__,"L2 cache created");
			} catch ( Exception $ex ) {
				$this->error_log(__METHOD__,$ex);
				$this->db = null;
				return false;
			}
		}

		return true;
	}


	/*
	 * readability (no setability) of private properties
	 */


	/**
	 * Makes private properties readable for backward compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get( $name ) {
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
	public function __isset( $name ) {
		return isset( $this->$name );
	}


	/*
	 * SQLite select (one / all)
	 */


	/**
	 * select a single record from the database.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @return array|bool	selected row or false;
	 */
	private function select_one( string $blogkey, string $group )
	{
		static $stmt = null;

		if ( ! $this->db ) return false;

		$this->L1_cache[ $group ][ $blogkey ] = false;

		if ( isset( $this->nonp_groups[ $group ] ) ) return false;

		if (is_null($stmt)) {
			$stmt = $this->db->prepare(
				"SELECT * FROM wp_cache WHERE key = :key AND (expire = 0 OR expire >= {$this->time_now}) LIMIT 1;"
			);
		}

		try {
			$stmt->bindValue( ':key', $group.'|'.$blogkey, PDO::PARAM_STR );
			$stmt->execute();
			if ($row = $stmt->fetch()) {
				$row = $this->select_parse_row( $row );
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
	 * @return array	selected rows.
	 */
	private function select_all( array $blogkeys, bool $like=false ): array
	{
		if ( ! $this->db ) return [];

		// get the blogkeys that we need to select from L2
		$selectkeys = [];
		foreach ($blogkeys as $group => $keys) {
			foreach ($keys as $blogkey) {
				$this->L1_cache[ $group ][ $blogkey ] = false;
				$selectkeys[] = $group.'|'.$blogkey;
			}
		}

		if (empty($selectkeys)) return [];

		$selectkeys = array_unique($selectkeys);

		$where = ($like)
			? substr(str_repeat( 'KEY LIKE ? OR ', count($selectkeys)),0,-4)
			: 'KEY IN (' . substr(str_repeat('?,', count($selectkeys)),0,-1) . ')';

		$stmt = $this->db->prepare(
			"SELECT * FROM wp_cache WHERE {$where} AND (expire = 0 OR expire >= {$this->time_now});"
		);

		try {
			$stmt->execute($selectkeys);
			if ($rows = $stmt->fetchAll()) {
				foreach ($rows as &$row) {
					$row = $this->select_parse_row( $row );
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
	 * @param array $row database row (key, value, expire)
	 * @return array row (key, value, expire, group, blog)
	 */
	private function select_parse_row( array $row ): array
	{
		if (preg_match("/^(?<group>.*)\|(?<blog>\d{5})\|(?<key>.*)$/", $row['key'], $parts)) {
			$row = array_merge($row,array_filter($parts,'is_string',ARRAY_FILTER_USE_KEY));
			$row['value']	= maybe_unserialize($row['value']);
			$row['expire']	= (!empty($row['expire'])) ? $row['expire'] - $this->time_now : 0;
			// add to the L1 (memory) cache
			$blogkey		= $row['blog'].'|'.$row['key'];
			$this->L1_cache[ $row['group'] ][ $blogkey ] = [ 'value'=>$row['value'], 'expire'=>$row['expire'] ];
		} else { // this shouldn't ever happen
			$this->error_log(__METHOD__,'invalid key format ['.$key.']');
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
	private function get_valid_key( $key, $group )
	{
		if ( is_int( $key ) || ( is_string( $key ) && trim( $key ) !== '' ) ) {
			$blog_id = ( $this->multisite && !isset( $this->global_groups[ $group ] ) ) ? $this->blog_id : 0;
			return sprintf( "%05d|%s", $blog_id, $key );
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
	 * Serves as a utility function to determine whether a key exists in the cache.
	 *
	 * @param string	$blogkey Cache key to check for existence. (blog|key)
	 * @param string	$group Cache group for the key existence check.
	 * @param bool		$count increment hits/misses
	 * @param bool		$force Optional. Whether to force an update of the local cache
	 *							from the persistent cache. Default false.
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	private function key_exists( string $blogkey, string $group, $count = false, $force = false ): bool
	{
		// if we've updated a key, but not written yet, then we force a persistent load,
		// we'll lose that value - so we must ignore the $force flag.
		// ( wp_load_alloptions() uses $force )

		return (/*!$force &&*/ $this->key_exists_memory( $blogkey, $group, $count ))
			? (bool) $this->L1_cache[ $group ][ $blogkey ]
			: $this->key_exists_database( $blogkey, $group, $count );
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
			if ($count) $this->addStats('L1 cache hits',1);
			if ($this->L1_cache[ $group ][ $blogkey ] !== false) {
				if ($count) $this->addStats('L1 cache (+)',1);
				return +1;
			} else {
				if ($count) $this->addStats('L1 cache (-)',1);
				return -1;
			}
		}

		if ($count) $this->addStats('L1 cache misses',1);
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
			if ($count) $this->addStats('L2 cache hits',1);
			return true;
		} else {
			if ($count) $this->addStats('L2 cache misses',1);
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
		if ( wp_suspend_cache_addition() ) return false;

		if (empty( $group )) $group = 'default';

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;

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
		if (empty( $group )) $group = 'default';

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		// set default expiration time - transients (perm_groups) don't expire unless explicitly set
		$expire = (!empty($expire))
			? (int)$expire
			: (isset( $this->perm_groups[ $group ] ) ? 0 : $this->default_expire);

		// value has not changed (has WordPress already done this?)
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

		if ( ! $blogkey = $this->get_valid_key( $key, $group ) ) return false;

		if ( $this->key_exists( $blogkey, $group, true, $force ) ) {
			$found = true;
			$this->addStats('cache hits',1,$group);
			if ( is_object( $this->L1_cache[ $group ][ $blogkey ][ 'value' ] ) ) {
				return clone $this->L1_cache[ $group ][ $blogkey ][ 'value' ];
			} else {
				return $this->L1_cache[ $group ][ $blogkey ][ 'value' ];
			}
		}

		$this->addStats('cache misses',1);

		$found = false;
		return false;
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
	public function get_multiple( array	 $keys, $group = 'default', $force = false )
	{
		// fill array [key => false]
		$values = array_fill_keys( (array)$keys, false );

		if (empty( $group )) $group = 'default';

		$blogkeys	= [];
		$selects	= 0;
		foreach ( $values as $key => &$value ) {
			if ( $blogkey = $this->get_valid_key( $key, $group ) ) {
				if (/*$force ||*/ ! $this->key_exists_memory($blogkey, $group, true)) {
					$blogkeys[ $group ][] = $blogkey;
					$selects++;
				} else {
					$value = $this->L1_cache[ $group ][ $blogkey ][ 'value' ] ?? false;
				}
			}
		}

		if ( $this->db && ! isset( $this->nonp_groups[ $group ] ) ) {
			if (!empty($blogkeys)) {
				$hits = 0;
				foreach ($this->select_all( $blogkeys ) as $row) {
					$values[ $row['key'] ] = $row['value'];
					$hits++;
				}
				$this->addStats('L2 cache hits',$hits);
				$this->addStats('L2 cache misses',$selects - $hits);
			}
		}

		$hits = count(array_filter($values, function($v) {return $v !== false;}));
		$this->addStats('cache hits',$hits,$group);
		$this->addStats('cache misses',count($values) - $hits);
		return $values;
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

		if ( ! $this->key_exists( $blogkey, $group, false ) ) return false;

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
	 * Clears the object cache of all data.
	 * SQLite triggers truncate optomizer
	 *
	 * @return bool sql result
	 */
	public function flush(): bool
	{
		$this->L1_cache = $this->L2_cache = array();

		if ( ! $this->db ) return false;

		try {
			$this->db->beginTransaction();
			$result = $this->db->query("DELETE FROM wp_cache;");
			$this->db->commit();
			if ($result->rowCount()) {
				$this->error_log(__METHOD__,'cache flushed, '.
					$result->rowCount()." records deleted");
			}
			$this->addStats('flushed cache',$result->rowCount());
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
			$this->db->rollBack();
			return false;
		}

		return (bool)$result;
	}


	/**
	 * Removes all cache items in a group.
	 *
	 * @param string $group Name of group to remove from cache.
	 * @return bool sql result
	 */
	public function flush_group( string $group ): bool
	{
		static $stmt = null;

		$this->write_cache();
		unset( $this->L1_cache[ $group ] );

		if ( ! $this->db ) return false;

		if (is_null($stmt)) {
			$stmt = $this->db->prepare("DELETE FROM wp_cache WHERE key LIKE :group;");
		}

		try {
			$blogkey = $this->get_valid_key('%',$group);
			$stmt->bindValue( ':group', $group.'|'.$blogkey, PDO::PARAM_STR );
			$this->db->beginTransaction();
			$stmt->execute();
			$this->db->commit();
			if ($result->rowCount()) {
				$this->error_log(__METHOD__,"cache flushed for '{$group}', ".
					$stmt->rowCount()." records deleted");
			}
			$this->addStats("flushed {$group}",$stmt->rowCount());
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
			$this->db->rollBack();
			return false;
		}

		return (bool)$stmt;
	}


	/**
	 * Removes all cache items tagged with a blog number.
	 *
	 * @param string $blog current blog
	 * @return bool sql result
	 */
	public function flush_blog( $blog_id = null ): bool
	{
		if (! $this->multisite) {
			return $this->flush();
		}

		static $stmt = null;

		$this->write_cache();
		$this->L1_cache = array();

		if ( ! $this->db ) return false;

		if (!is_int($blog_id)) $blog_id = $this->blog_id;

		if (is_null($stmt)) {
			$stmt = $this->db->prepare("DELETE FROM wp_cache WHERE key LIKE :blog;");
		}

		try {
			$stmt->bindValue( ':blog', '%|'.sprintf("%05d", $blog_id).'|%', PDO::PARAM_STR );
			$this->db->beginTransaction();
			$stmt->execute();
			$this->db->commit();
			if ($result->rowCount()) {
				$this->error_log(__METHOD__,"cache flushed for blog id {$blog_id}, ".
					$stmt->rowCount()." records deleted");
			}
			$this->addStats("flushed blog id {$blog_id}",$stmt->rowCount());
		} catch ( Exception $ex ) {
			$this->error_log(__METHOD__,$ex);
			$this->db->rollBack();
			return false;
		}

		return (bool)$stmt;
	}


	/**
	 * Clears the object cache of all data.
	 *
	 * @return bool Always returns true.
	 */
	public function flush_runtime(): bool
	{
		$this->do_cache_misses(true);
		$this->write_cache();
		$this->L1_cache = array();
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
		$constant = 'EAC_OBJECT_CACHE_'.strtoupper($constant).'_GROUPS';
		if ( defined( $constant ) ) {
			$constant = (array) constant($constant);
			if ( is_array( $constant ) && ! empty ( $constant ) ) {
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
		foreach (array_keys($this->prefetch_groups) as $group) {
			if (! isset( $this->nonp_groups[ $group ] ) ) {
				$blogkeys[ $group ] = [ $this->get_valid_key('%',$group) ];
			}
		}

		$this->addStats("L2 pre-fetched (+)", count($this->select_all( $blogkeys, true )));

		// get cache misses from last request - known not to be in sqlite
		$this->do_cache_misses();
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
		$this->flush_runtime();
		$this->blog_id = $this->multisite ? (int) $blog_id : 0;
		$this->load_prefetch_groups();
	}


	/**
	 * Resets cache keys.
	 *
	 * @deprecated 3.5.0 Use WP_Object_Cache::switch_to_blog()
	 * @see switch_to_blog()
	 */
	public function reset()
	{
		_deprecated_function( __METHOD__, '3.5.0', 'WP_Object_Cache::switch_to_blog()' );
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

		// find and cache all cache misses - not in sqlite
		$this->do_cache_misses(true);

		// count requests (since last flush)
		$requests = $this->incr('requests',+1,self::GROUP_ID);

		// maintenance functions (every n requests)
		if ($this->gc_probability > 0)
		{
			$this->gc_probability = $this->gc_probability + ($this->gc_probability % 2); // even number
			$probability = ($requests % $this->gc_probability);

			if ($probability == 0)												//	checkpoint db
			{
				$this->write_cache();
				$result = $this->db->query('PRAGMA wal_checkpoint(TRUNCATE);');
			}

			if ($probability == (int)($this->gc_probability * .75))				// garbage collection
			{
				$limit = (is_int($this->delayed_writes) ? $this->delayed_writes : 32);
				try {
					$this->db->beginTransaction();
					$result = $this->db->query(
						"DELETE FROM wp_cache WHERE expire > 0 AND expire < {$this->time_now} LIMIT {$limit};"
					);
					$this->db->commit();
				} catch ( Exception $ex ) {
					$this->error_log(__METHOD__,$ex);
					$this->db->rollBack();
				}
			}

			if ($probability == (int)($this->gc_probability * .50))				// optimize db
			{
				$result = $this->db->query('PRAGMA optimize;');
			}


			if ($probability == (int)($this->gc_probability * .25))				// stats sample
			{
				$this->set('sample', $this->getStats(true), self::GROUP_ID, 0);
			}
		}

		$this->write_cache();

		$this->db = null;
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
	 * write the persistent cache to disk when db cache is full
	 *
	 */
	private function maybe_write_cache(): bool
	{
		if ( $this->delayed_writes !== true ) {
			$pending = count($this->L2_cache,COUNT_RECURSIVE) - count($this->L2_cache);
			if ($pending >= (int)$this->delayed_writes ) {
				return $this->write_cache();
			}
		}
		return false;
	}


	/**
	 * write the persistent cache to disk
	 *
	 */
	private function write_cache(): bool
	{
		if (! $this->db || empty($this->L2_cache)) {
			$this->L2_cache = array();
			return false;
		}

		$write = $delete = array();

		// find all writes and deletes
		foreach ($this->L2_cache as $group => $updates) {
			foreach ($updates as $blogkey => $expire) {
				if ($expire !== false) {
					$write[] = array(
							$group.'|'.$blogkey,
							maybe_serialize($this->L1_cache[ $group ][ $blogkey ][ 'value' ]),
							( ($expire) ? $this->time_now+$expire : 0 )
					);
				} else {
					$delete[] = array(
							$group.'|'.$blogkey,
					);
				}
			}
		}

		$this->L2_cache = array();

		$count = 0;
		// replace records
		if (!empty($write)) {
			$retries = 0;
			while ( ++$retries <= $this->max_retries ) {
				try {
					$stmt = $this->db->prepare(
						"REPLACE INTO wp_cache (key, value, expire) VALUES " .
						rtrim(str_repeat("(?,?,?),", count($write)),',')
					);
					$this->db->beginTransaction();
					$stmt->execute(array_merge(...$write));
					$count += $stmt->rowCount();
					$this->addStats('L2 updated',$stmt->rowCount());
					$this->db->commit();
					$this->addStats('L2 commits',1);
					break;
				} catch ( Exception $ex ) {
					$this->error_log(__METHOD__,$ex);
					$this->db->rollBack();
					usleep($this->sleep_time);
				}
			}
		}

		// delete records
		if (!empty($delete)) {
			$retries = 0;
			while ( ++$retries <= $this->max_retries ) {
				try {
					$stmt = $this->db->prepare(
						"DELETE FROM wp_cache WHERE key in (" .
						rtrim(str_repeat("?,", count($delete)),',').")"
					);
					$this->db->beginTransaction();
					$stmt->execute(array_merge(...$delete));
					$count += $stmt->rowCount();
					$this->addStats('L2 deleted',$stmt->rowCount());
					$this->db->commit();
					$this->addStats('L2 commits',1);
					break;
				} catch ( Exception $ex ) {
					$this->error_log(__METHOD__,$ex);
					$this->db->rollBack();
					usleep($this->sleep_time);
				}
			}
		}

		return (bool)$count;
	}


	/**
	 * manage cached misses.
	 * called to load on muplugins, called to save on close/shutdown.
	 *
	 * @param $save bool save cache misses
	 */
	private function do_cache_misses($save=false): void
	{
		if (! $this->prefetch_misses) return;

		// load (or re-load) prior cache misses into L1 cache
		if ($misses = $this->get('cache-misses',self::GROUP_ID)) {
			foreach ($misses as $group => $keys) {
				if (isset( $this->L1_cache[ $group ] )) {
					$keys = array_diff_key($keys, $this->L1_cache[ $group ]);
					$this->L1_cache[ $group ] = array_merge($keys, $this->L1_cache[ $group ]);
				} else {
					$this->L1_cache[ $group ] = $keys;
				}
				$this->addStats("L2 pre-fetched (-)", count($keys));
			}
		}
		// remove from cache
		$blogkey = $this->get_valid_key('cache-misses',self::GROUP_ID);
		unset( $this->L1_cache[ self::GROUP_ID ][ $blogkey ] );

		// save for next request
		if ($save) {
			$misses = array();
			foreach ($this->L1_cache as $group => $keys) {
				if ($keys = array_filter( $keys, function($v) {return $v === false;} )) {
					$misses[$group] = $keys;
				}
			}
			$this->set('cache-misses', $misses, self::GROUP_ID, HOUR_IN_SECONDS);
		}
	}


	/**
	 * Delete the L2 cache file(s).
	 * Since we load early, WP_Filesystem is probably not available
	 *
	 */
	public function delete_cache_file(): void
	{
		$this->db = null;

		$cacheName = trailingslashit($this->cache_folder) . $this->cache_file;
		foreach ( [ '', '-journal', '-shm', '-wal' ] as $ext ) {
			if (file_exists($cacheName.$ext)) {
				unlink($cacheName.$ext);
			}
		}
		$this->error_log(__METHOD__,"L2 cache deleted");
	}


	/**
	 * Error logging.
	 *
	 * Writes and/or displays error message.
	 *
	 * @param string $source (function)
	 * @param string|object $message message string or exception object
	 */
	private function error_log($source,$message): void
	{
		try {
			$class = 'warning';
			$trace = '';

			if (is_object($message)) {
				$class = 'error';
				$message = $message->getCode().' '.$message->getMessage();
				ob_start();
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$trace = PHP_EOL . ob_get_clean();
			}

			if ($this->display_errors && is_admin() && function_exists('is_admin_bar_showing') && is_admin_bar_showing()) {
				echo "<div class='object-cache-".esc_attr($class)." notice notice-".esc_attr($class)."'>".
					 "<p>WP Object Cache: ".esc_attr($message)."</p></div>";
			}

			$message = $source.' : '.$message.$trace;
			error_log($message);

			if ($this->log_errors && function_exists('eacDoojigger')) {
				eacDoojigger()->logError($message,self::PLUGIN_NAME);
			}
		} catch (Throwable $e) {}
	}


	/*
	 * API methods  - counts/stats
	 */


	/**
	 * Echoes the stats of the caching (similar to WP_Object_Cache::stats()).
	 * Called by outside actors (e.g. debug bar)
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 */
	public function stats(): void
	{
		$stats = $this->getStats(false);

		echo "</p>";
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
	 * @param bool $last use last sampling
	 * @param bool $full flush cache and get database stats
	 */
	public function htmlStats($last = false, $full = true): void
	{
		$stats = ($last) ? $this->getLastSample() : $this->getStats($full);

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
	 * @param bool $full flush cache and get database stats
	 * @return array
	 */
	public function getStats($full=true): array
	{
		// so outside actors don't force a cache write
		if ($full) $this->write_cache();

		$stats = array();
		$stats['id'] = array(
			self::PLUGIN_NAME	=> EAC_OBJECT_CACHE_VERSION,
			'cache file'		=> ($this->db)
					? '~/'.trailingslashit(basename($this->cache_folder)) . $this->cache_file
					: 'memory-only',
			'sample time'		=> wp_date('c'),
		);
		if (isset($_SERVER['HTTP_HOST'],$_SERVER['REQUEST_URI'])) {
			$stats['id']['url']	= sanitize_url( sprintf('%s://%s%s',
				(is_ssl()) ? 'https' : 'http',
				$_SERVER['HTTP_HOST'],
				$_SERVER['REQUEST_URI']) );
		}

		// addStats counters
		$stats['cache'] = array();
		foreach ($this->cache_stats as $name => $count) {
			$stats['cache'][$name] = [$count,''];
		}
		// add cache hit ratios
		$stats['cache']['cache hits'][1] =
			$this->cache_hit_ratio($this->cache_stats['cache hits'],$this->cache_stats['cache misses']);
		$stats['cache']['L1 cache hits'][1] =
			$this->cache_hit_ratio($this->cache_stats['L1 cache hits'],$this->cache_stats['L1 cache misses']);
		$stats['cache']['L2 cache hits'][1] =
			$this->cache_hit_ratio($this->cache_stats['L2 cache hits'],$this->cache_stats['L2 cache misses']);

		// current cache contents
		$stats['cache-groups'] = array();
		foreach ( $this->L1_cache as $group => $cache ) {
			$cache = array_filter($cache, function($v){return $v !== false;});
			if (!empty($cache)) {
				$count = count($cache);
				$size = strlen( serialize( $cache ) );
				$stats['cache-groups'][$group] = [$count, $size, $this->group_stats[$group] ?? 0];
			}
		}
		ksort($stats['cache-groups']);
		$stats['cache-groups']['Total'] = [
			array_sum(array_column($stats['cache-groups'],0)),
			array_sum(array_column($stats['cache-groups'],1)),
			array_sum(array_column($stats['cache-groups'],2))
		];

		// database contents - all groups
		if ($full && $this->db) {
			$blog_id = sprintf("%05d", $this->blog_id);
			$stats['database-groups'] = array();
			if ($result = $this->db->query("
				SELECT SUBSTR(key,0,INSTR(key,'|')) as name,
					   SUBSTR(key,INSTR(key,'|')+1,5) as blog,
					   COUNT(*) as count,
					   SUM(LENGTH(key)+2 + LENGTH(value)+4 + 8+2) as size
				FROM wp_cache WHERE blog IN ('00000','{$blog_id}')
				 AND (expire = 0 OR expire >= {$this->time_now}) GROUP BY name;"))
			{
				while ($row = $result->fetch()) {
					$stats['database-groups'][ $row['name'] ] = [$row['count'], $row['size']];
				}
				$result->closeCursor();
				ksort($stats['database-groups']);
				$stats['database-groups']['Total'] = [
					array_sum(array_column($stats['database-groups'],0)),
					array_sum(array_column($stats['database-groups'],1))
				];
			}
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
		if ( $row = $this->get( "sample", self::GROUP_ID ) ) {
			return $row;
		}
		return $this->getStats(true);
	}


	/*
	 * MySQL  transients
	 */


	/**
	 * Import existing MySQL transients
	 *
	 */
	private function import_wp_transients(): void
	{
		global $wpdb;

		// see if we've done this already
		if ( $row = $this->get( 'transients', self::GROUP_ID ) ) {
			return;
		}

		// write all in one transaction
		$this->set_delayed_writes( true );

		// import transients from options table

		// so we don't try to use this cache
		wp_using_ext_object_cache( false );
		$installing = wp_installing( true );

		$optionSQL =
			"SELECT option_name as name, option_value as value".
			"  FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s";

		$transients = $wpdb->get_results(
			$wpdb->prepare($optionSQL,'_transient_%','_transient_timeout_%')
		);

		if ($transients && !is_wp_error($transients)) {
			foreach ($transients as $row) {
				$this->add_wp_transient($row,'transient');
			}
		}

		// import site transients from options or sitemeta table

		$siteSQL = ($this->multisite)
			? "SELECT meta_key as name, meta_value as value".
			  " FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_key NOT LIKE %s"
			: $optionSQL;

		$transients = $wpdb->get_results(
			$wpdb->prepare($siteSQL,'_site_transient_%','_site_transient_timeout_%')
		);

		if ($transients && !is_wp_error($transients)) {
			foreach ($transients as $row) {
				$this->add_wp_transient($row,'site-transient');
			}
		}

		wp_using_ext_object_cache( true );
		wp_installing( $installing );

		$this->set('transients', [ wp_date('c'),$this->cache_stats['transients imported'] ], self::GROUP_ID, 0);
		$this->set_delayed_writes();
	}


	/**
	 * load a single transient from WP
	 *
	 * @param object $record transient record
	 * @param string $group 'transient' or 'site-transient'
	 */
	private function add_wp_transient(object $record, string $group): void
	{
		$key = str_replace(['_site_transient_','_transient_'],'',$record->name);

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

		$this->set($key, maybe_unserialize($record->value), $group, $expire);
		$this->addStats('transients imported',1);
	}
}


/*
 *
 * global wp functions (wp-include/cache.php)
 * WP_PLUGIN_DIR not yet set
 */

require __DIR__.'/plugins/eacobjectcache/src/wp-cache.php';
