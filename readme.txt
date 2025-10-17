=== {eac}ObjectCache - a persistent object cache using SQLite & APCu to cache WordPress objects. ===
Plugin URI:         https://eacdoojigger.earthasylum.com/eacobjectcache/
Author:             [EarthAsylum Consulting](https://www.earthasylum.com)
Stable tag:         2.1.1
Last Updated:       17-Oct-2025
Requires at least:  5.8
Tested up to:       6.8
Requires PHP:       8.1
Contributors:       kevinburkholder
Donate link:        https://github.com/sponsors/EarthAsylum
License:            GPLv3 or later
License URI:        https://www.gnu.org/licenses/gpl.html
Tags:               object cache, wp cache, APCu, sqlite, persistent object cache, performance, {eac}Doojigger,
WordPress URI:      https://wordpress.org/plugins/eacobjectcache
GitHub URI:         https://github.com/EarthAsylum/eacObjectCache

{eac}ObjectCache is a persistent object cache using APCu & SQLite to cache WordPress objects; A drop-in replacement to the WP Object Cache used by WordPress.

== Description ==

The _{eac}ObjectCache_ is a light-weight and very efficient drop-in persistent object cache that uses a fast SQLite database and even faster APCu shared memory to cache WordPress objects.

See [The WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/)

>   The WordPress Object Cache is used to save on trips to the database. The Object Cache stores all of the cache data to memory and makes the cache contents available by using a key, which is used to name and later retrieve the cache contents.

>   By default, the object cache is non-persistent. This means that data stored in the cache resides in memory only and only for the duration of the request. Cached data will not be stored persistently across page loads unless you install a persistent caching plugin.

Here, an object is any piece of data - a number, text, a set of database records, an API response, etc. - that can be referenced by a name or key. Objects are categorized by a group name. Groups help identify what an object is and how it is used.

{eac}ObjectCache replaces the default WordPress object cache to not only store data in process memory but to also store data persistently, across requests, in APCu shared memory and/or in a SQLite database, increasing the likelihood of cache hits and decreasing the need for costly computations, complex MySQL database queries, and remote API requests.

SQLite is a fast, small, single-file relational database engine. By using SQLite to store objects, {eac}ObjectCache is able to manage a relatively large amount of data (groups, keys, and values) in a very efficient and fast data-store.

[APCu](https://www.php.net/manual/en/book.apcu.php) is a shared, in-memory, persistent cache available only when the [APCu PECL Extension](https://www.php.net/manual/en/apcu.setup.php) is installed. {eac}ObjectCache uses APCu as an intermediate cache between the L1 memory cache and the L2 SQLite database cache providing extremely fast object retrieval,

{eac}ObjectCache always uses per-request, in-memory caching and may operate with either APCu memory caching or SQLite database caching - or both. APCu memory caching uses a single block of memory shared by all PHP requests and is persistent until and unless the cache is cleared or the server is rebooted (or PHP restarted). SQLite database caching is persistent until and unless the cache is deliberately cleared.

= Features =

+   Lightweight, efficient, and fast!
+   L1 (in-process memory) _and_ L2 (APCu & SQLite) caching.
+   Supports Write-Back (delayed transactions) or Write-Through SQL caching.
+   Caching by object group name.
    +   Preserves uniqueness of keys.
    +   Manage keys by group name.
    +   Supports group name attributes (:sitewide, :nocaching, :permanent, :prefetch)
+   Pre-fetch object groups from L2 to L1 cache.
+   Caches and pre-fetches L2 misses (known to not exist in L2 cache).
    +   Prevents repeated, unnecessary L2 cache reads across requests.
+   Multisite / Network support:
    +   Cache/flush/switch by blog id.
+   Caching statistics:
    +   Cache hits (typically above 90%).
    +   Overall and L1/L2 hits, misses, & ratio.
    +   Cache hits by object groups.
    +   Number of APCu and SQLite keys stored.
    +   SQLite select/update/delete/commit counts.
+   Supports an enhanced superset of WP Object Cache functions.
+   Easily enabled or disabled from {eac}Doojigger administrator page.
    +   Imports existing MySQL transients.
    +   Exports cached transients to MySQL when disabled.
+   Automatically cleans and optimizes SQLite database.
+   Optionally schedule periodic cache invalidation and rebuild.
+   Uses the PHP Data Objects (PDO) extension included with PHP.

_While {eac}ObjectCache does support multiple WordPress installations on a single server it does not support multiple servers per installation. SQLite and APCu work only on a single server, not in a clustered or load-balanced environment._

= Configuration Alternatives =

Assuming you have SQLite and APCu installed, what are your best options?

1.	Fastest Caching - _Uses in-process memory and APCu shared memory._

	+	Disable SQLite. \*
		+	`define( 'EAC_OBJECT_CACHE_USE_DB', false );`
	+ 	Advantage
		+	Fast memory-only access.
		+	Handles concurrent updates through APCu cache.
	+ 	Disadvantage
		+ 	APCu may invalidate data under memory constraint.
		+	APCu cache is not shared with CLI.
		+	APCu cache is lost on system or PHP restart.

2.	Less memory (almost as fast) - _Uses in-process memory and APCu shared memory._

	+	Disable SQLite. \*
		+	`define( 'EAC_OBJECT_CACHE_USE_DB', false );`
	+	Optimize memory use. \*
		+	`define( 'EAC_OBJECT_CACHE_OPTIMIZE_MEMORY', true );`
	+ 	Advantage
		+	Fast memory-only access.
		+	Handles concurrent updates through APCu cache.
		+	Conserves per-request memory by not pushing APCu hits to in-process memory.
	+ 	Disadvantage
		+	Slightly slower to access APCu memory over in-process memory.
		+ 	APCu may invalidate data under memory constraint.
		+	APCu cache is not shared with CLI.
		+	APCu cache is lost on system or PHP restart.

3.	Most resilient (and still fast) - _Uses in-process memory, APCu shared memory, and SQLite database._

	+	Do nothing, this is the default.
	+ 	Advantage
		+	Most cache hits will come from in-process and APCu memory.
		+	SQLite retains cache data after restart.
	+ 	Disadvantage
		+ 	Must keep SQLite database (on disk) updated.
		+	Potential concurrency issues on high-volume site.

4.	Resilient, efficient, and fast (recommended) - _Uses in-process memory, APCu shared memory, and SQLite database._

	+	Optimize memory use. \*
		+	`define( 'EAC_OBJECT_CACHE_OPTIMIZE_MEMORY', true );`
	+ 	Advantage
		+	Most cache hits will come from in-process and APCu memory.
		+	Handles concurrent updates better through APCu cache.
		+	Conserves per-request memory by not pushing APCu hits to in-process memory.
		+	SQLite retains cache data after restart.
	+ 	Disadvantage
		+	Slightly slower to access APCu memory over in-process memory.
		+ 	Must keep SQLite database (on disk) updated.

5.	Least efficient (default when APCu is not installed) - _Uses in-process memory and SQLite database._

	+	Disable APCu. \*
		+	`define( 'EAC_OBJECT_CACHE_USE_APCU', false );`
	+ 	Advantage
		+	Saves resources by not taking up APCu reserves.
		+	More secure by not using shared memory.
		+	SQLite retains cache data after restart.
	+ 	Disadvantage
		+	All cached data initially read from disk.
		+ 	Must keep SQLite database (on disk) updated.
		+	Potential concurrency issues on high-volume site.

6.	For high-volume sites - _reduces or eliminates potential race conditions_
	+	Optimize memory use. \*
		+	`define( 'EAC_OBJECT_CACHE_OPTIMIZE_MEMORY', true );`
	+	Disable delayed writes. \*
		+	`define( 'EAC_OBJECT_CACHE_DELAYED_WRITES', false );`
	+	Disable use of `alloptions` array.
		+	`define( 'EAC_OBJECT_CACHE_DISABLE_ALLOPTIONS', true );`
	+ 	Advantage
		+	Most cache hits will come from in-process and APCu memory.
		+	Conserves per-request memory by not pushing APCu hits to in-process memory.
		+	Updates SQLite data immediately.
		+	Conserves per-request memory by elimination large `alloptions` array(s).
	+ 	Disadvantage
		+	Slightly slower to access APCu memory over in-process memory.
		+	Multiple single-row SQLite update transactions.
		+	Slightly slower to access individual options from cache rather than `alloptions` array.

\* These options may be set from the {eac}Doojigger administration screen.

_When using SQLite, `delayed writes` (see below) dramatically improves efficiency by only writing updates at the end of the script process._

_When using APCu shared memory, data is accessable by other PHP processes that may run on the server._

= Inside The Numbers =

<img alt="Cache Counts" width="325" src="https://ps.w.org/eacobjectcache/assets/wpoc_example.png" />

| Label             | Value               |
| :---------------- | :------------------ |
| cache hits        | The total number of requests that returned a cached value. |
| cache misses      | The total number of requests that did not return a cached value. This number includes *L1 cache (-)*, *L2 non-persistent*, *L2 APCu (-)*, and *L2 SQL misses*. |
| L1 cache hits     | The number of requests that were found in the L1 memory cache. |
| L1 cache (+)      | Request found in the L1 memory cache with data (positive hits). |
| L1 cache (-)      | Request found in the L1 memory cache with no data (negative hits). |
| L1 cache misses   | The number of requests not found in the L1 memory cache. |
| L2 non-persistent | L1 cache misses in a non-persistent group (not in L2 cache). |
| L2 APCu hits      | The number of L1 cache misses (minus L2 non-persistent) that were found in the L2 APCu cache. |
| L2 APCu (+)       | Request found in the L2 APCu cache with data (positive hits). |
| L2 APCu (-)       | Request found in the L2 APCu cache with no data (negative hits). |
| L2 APCu misses    | The number of requests not found in the L2 APCu cache. |
| L2 SQL hits       | The number of L2 APCu misses (or L1 cache misses) that were found in the L2 SQLite cache. |
| L2 SQL misses     | The number of requests not found in the L2 SQLite cache. |
| L2 APCu updates	| The number of APCu keys updated. |
| L2 APCu deletes	| The number of APCu keys deleted. |
| L2 SQL selects	| The number of SQLite select statements executed. |
| L2 SQL updates	| The number of SQLite records updated. |
| L2 SQL deletes	| The number of SQLite records deleted. |
| L2 SQL commits    | The number of SQLite transactions executed to update and delete records. |

* When a request results in a *L2 SQL miss*, the key is added to the L1 memory or L2 APCu cache as a miss so that additional requests for the same key do not result in additional SQL selects. This is known as a *negative hit* and still counted as a *cache miss* making the _cache hit ratio_ (93.10%) understated.

Object cache statistics may be found:

+	In the *WP Object Cache* dashboard panel.
	+	Uses `$wp_object_cache->showDashboardStats()`
+ 	In the *Debug Bar > Object Cache* panel.
	+	Uses `$wp_object_cache->stats()`
+	In the *Query Monitor > Logs > Info* panel.
	+	Uses `$wp_object_cache->getCurrentStats()`
+	In a *wp_admin_notice* block when *display_stats* is set for sampling.
	+	Uses `$wp_object_cache->htmlStats()`

== Settings ==

Several cache settings can be modified by adding defined constants to the `wp-config.php` file. The default settings are recommended and optimal in most cases but individual settings may need to be adjusted based on traffic volume, specific requirements, or unique circumstances. *Most of these settings can be adjusted in the {eac}Doojigger administrator screen.*

* * *

+   To disable use of the SQLite Database                       (default: true):

```
    define( 'EAC_OBJECT_CACHE_USE_DB', false );
```

{eac}ObjectCache will still operate as an in-memory cache without the persistent database. If using APCu memory caching, persistence is maintained as long as the cache is not flushed, manually or by restarting PHP.

* * *

+   To disable use of the APCu memory cache                     (default: true if APCu is enabled):

```
    define( 'EAC_OBJECT_CACHE_USE_APCU', false );
```

APCu memory caching is used, by default, only if the [APCu PECL extension](https://www.php.net/manual/en/apcu.installation.php) is installed.

* * *

+   To optimize memory use when using APCu                      (default: false):

```
    define( 'EAC_OBJECT_CACHE_OPTIMIZE_MEMORY', true );
```

When using APCu memory caching, optimize internal memory by not storing APCu data in the L1 memory cache. This may slightly (negligibly) increase processing time as cache hits will come through APCu but will reduce the per-process memory usage. This may also be advantageous on high-volume sites where a single object may be updated by simultaneous processes.

* * *

+   To disable use of the `alloptions` array in WordPress       (default: false):

```
    define( 'EAC_OBJECT_CACHE_DISABLE_ALLOPTIONS', true );
```

By default, WordPress pre-fetches many of the option values from the wp-options table on startup. This facilitates faster access to oft-used options. However, this also creates 1) a potential race condition on high-volume sites, and 2) a sometimes very large array of data in memory that may also be duplicated in the L1 and L2 caches. Disabling this forces WordPress to get individual options from the cache rather than from the array, eliminates the race condition, eliminates the large array(s), and reduces much of the logic used to maintain the array(s). This may be particularly advantageous when using APCu since the option values should already be in shared memory.

\* _Once enabled, the caches should be cleared to eliminate previously cached `alloptions` arrays._
\* _Uses filters `pre_wp_load_alloptions` (introduced in WP 6.2.0) and `wp_autoload_values_to_autoload` (introduced in WP 6.6.0)._

* * *

+   To set the location of the SQLite database                  (default: ../wp-content/cache):

```
    define( 'EAC_OBJECT_CACHE_DIR', '/full/path/to/folder' );
```

This folder can be outside of the web-accessable folders of your site - i.e. above the document root (htdocs, www, etc.) - provided that PHP can access (read/write) the folder (see the PHP *open_basedir* directive).

This folder should not be on a network share or other remote media. We're caching data for quick access, the cache folder should be on fast, local media.

* * *

+   To set the name of the SQLite database                      (default: '.eac_object_cache.sqlite'):

```
    define( 'EAC_OBJECT_CACHE_FILE', 'filename.sqlite' );
```

In addition to the database file, SQLite may also create temporary files using the same file name with a '-shm' and '-wal' suffix.

* * *

+   To set SQLite journal mode                                  (default: 'WAL'):

```
    define( 'EAC_OBJECT_CACHE_JOURNAL_MODE', journal_mode )
```

*journal_mode* can be one of 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', or 'OFF'.
See [SQLite journal mode](https://www.sqlite.org/pragma.html#pragma_journal_mode)

* * *

+   To set SQLite Mapped Memory I/O                             (default: 0):

```
    define( 'EAC_OBJECT_CACHE_MMAP_SIZE', int );
```

Sets the maximum number of bytes that are set aside for memory-mapped I/O.
See [SQLite memory-mapped I/O](https://www.sqlite.org/pragma.html#pragma_mmap_size)

* * *

+   To set SQLite Page Size                                     (default: 4096):

```
    define( 'EAC_OBJECT_CACHE_PAGE_SIZE', int );
```

Sets the SQLite page size for the database.
See [SQLite page size](https://www.sqlite.org/pragma.html#pragma_page_size)

* * *

+   To set SQLite Cache Size                                    (default: -2000 [2,048,000]):

```
    define( 'EAC_OBJECT_CACHE_CACHE_SIZE', int );
```

Sets the maximum number of database disk pages that SQLite will hold in memory or the maximum amount of memory to use for page caching.
See [SQLite cache size](https://www.sqlite.org/pragma.html#pragma_cache_size)

* * *

+   To set SQLite timeout                                       (default: 3):

```
    define( 'EAC_OBJECT_CACHE_TIMEOUT', int );
```

Sets the number of seconds before a SQLite transaction may timeout in error.

* * *

+   To set SQLite retries                                       (default: 3):

```
    define( 'EAC_OBJECT_CACHE_MAX_RETRIES', int );
```

Sets the maximum number of retries to attempt on critical actions.

* * *

+   To set delayed writes                                       (default: 32):

```
    define( 'EAC_OBJECT_CACHE_DELAYED_WRITES', true|false|int );
```

{eac}ObjectCache caches all objects in memory and writes new, updated, or deleted objects to the L2 (SQLite) cache. *delayed writes* simply holds objects in memory until the number of objects reaches a specified threshold, then writes them, in a single transaction, to the L2 cache (a.k.a. write-back caching). Setting *delayed writes* to false turns this functionality off (a.k.a. write-through caching). Setting to true writes all records only at the end of the script process/page request. Setting this to a number sets the object pending threshold to that number of objects.

* * *

+   To set the default expiration time (in seconds)             (default: 0 [never]):

```
    define( 'EAC_OBJECT_CACHE_DEFAULT_EXPIRE', -1|0|int );
```

When using the default WordPress object cache, object expiration isn't very important because the entire cache expires at the end of the script process/page request. With a persistent cache, this isn't the case. When an object is cached, the developer has the option of specifying an expiration time for that object. Since we don't know the intent of the developer when not specifying an expiration time, cache persistence *may* sometimes cause issues. Setting *default expiration* may alleviate problems and/or possibly improve performance by limiting cache data. When set to -1, objects with no expiration are not saved in the L2 cache.

_\*  Transients with no expiration overide this setting and are allowed (as that is the normal WordPress functionality)._

_\*  Usually, but not always, unexpired objects are updated when the source data has changed and do not present any issues._

* * *

+   To set the default expiration time (in seconds) by group name:

```
    define( 'EAC_OBJECT_CACHE_GROUP_EXPIRE', array( 'group' => -1|0|int, ... ) );
```

This option allows for setting the expiration time for specific object groups.

See also `wp_cache_add_group_expire( $groups )`.

* * *

+   To enable or disable pre-fetching of cache misses           (default: true [enabled]):

```
    define( 'EAC_OBJECT_CACHE_PREFETCH_MISSES', true | false );
```

Pre-fetching cache misses (keys that are not in the L2 persistent cache) prevents repeated, unnecessary reads of the L2 cache. Pre-fetching is disabled if APCu caching is being used.

* * *

+   To set maintenance probability                              (default: 1000):

```
    define( 'EAC_OBJECT_CACHE_PROBABILITY', int );
```

Sets the probability of running maintenance (garbage collection/optimization) tasks - approximately 1 in n requests, n >= 10.

* * *

+   Object groups that are global (not site-specific) in a multi-site/network environment:

```
    define( 'EAC_OBJECT_CACHE_GLOBAL_GROUPS', [ 'groupA', 'groupB', ... ] );
```

Global object groups are not tagged with or separated by the site/blog id.

_\* WordPress already defines several global groups that do not need to be duplicated here, rather the groups entered here are added to those defined by WordPress._


* * *

+   Object groups that should not be stored in the persistent cache:

```
    define( 'EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS', [ 'groupA', 'groupB', ... ] );
```

Non-persistent groups are object groups that do not persist across page loads. This may be another method to alleviate issues caused by cache persistence or to improve performance by limiting cache data.

_\* WordPress already defines several non-persistent groups that do not need to be duplicated here, rather the groups entered here are added to those defined by WordPress._

* * *

+   Object groups that are allowed permanence:

```
    define( 'EAC_OBJECT_CACHE_PERMANENT_GROUPS', [ 'groupA', 'groupB', ... ] );
```

When setting a default expiration (`EAC_OBJECT_CACHE_DEFAULT_EXPIRE`) for objects without an expiration, these groups are excluded from using the default, allowing them to be permanent (with no expiration). Transients and site-transients are automatically included.

* * *

+   To pre-fetch specific object groups from the L2 cache at startup:

```
    define( 'EAC_OBJECT_CACHE_PREFETCH_GROUPS', [ 'groupA', 'groupB', ... ] );
    define( 'EAC_OBJECT_CACHE_PREFETCH_GROUPS', [ 'groupA'=>['keyA','keyB'], 'groupB'=>'key%', ... ] );
```

Pre-fetching a group of records may be much faster than loading each key individually, but may load keys that are not needed, using memory unnecessarily. Pre-fetching is disabled if APCu caching is being used.

* * *

+   To prevent outside actors (scripts, plugins, etc.), including WordPress, from flushing caches.

```
    define( 'EAC_OBJECT_CACHE_DISABLE_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_FULL_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_GROUP_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_BLOG_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_RUNTIME_FLUSH', true );
```

* * *

+   To disable the importing of transients:

```
	define( 'EAC_OBJECT_CACHE_DISABLE_TRANSIENT_IMPORT', true );
```

* * *

+   To disable the exporting of transients when uninstalled:

```
	define( 'EAC_OBJECT_CACHE_DISABLE_TRANSIENT_EXPORT', true );
```

= Utility methods =

+	Returns true if using the SQLite database cache.

```
    $wp_object_cache->usingSQLite();
```

+	Returns true if using the APCu memory cache.

```
    $wp_object_cache->usingAPCu();
```

+   Outputs an html table of current stats. Use `$wp_object_cache->statsCSS` to style.

```
    $wp_object_cache->htmlStats();
```

+   Outputs an html table of current stats as seen on the admin dashboard. Use `$wp_object_cache->statsCSS` to style.

```
    $wp_object_cache->showDashboardStats();
```

+   Outputs an html list of current stats.

```
    $wp_object_cache->stats( $full=false );
```

+   Returns an array of current stats.

```
    $cacheStats = $wp_object_cache->getCurrentStats( $full=false );
```

+   Returns an array of stats from the last sample saved (or current).

```
    $cacheStats = $wp_object_cache->getLastSample();
```

=  Optional runtime settings =

+	Optimize internal memory use when using APCu cache.

```
    $wp_object_cache->optimize_memory = true;
```

+   Delay writing to database until shutdown or n pending records (see *delayed writes*).

```
    $wp_object_cache->delayed_writes = true | false | n;
```

+    Change the default expiration time for objects with no expiration.

```
    $wp_object_cache->default_expire = n;
```

+    Samples (every n requests) & outputs an admin notice with htmlStats().

```
    $wp_object_cache->display_stats = n;
```

+    Outputs cache stats to Query Monitor and/or {eac}Doojigger logs.

```
    $wp_object_cache->log_stats = true;
```

+   Outputs an administrator notice on error.

```
    $wp_object_cache->display_errors = true;
```

+   Log errors to Query Monitor and/or {eac}Doojigger logs.

```
    $wp_object_cache->log_errors = true;
```

= Group Name Attributes =

Specifying group attributes can be done in two ways:

1.   Using the `wp_cache_add_global_groups()`, `wp_cache_add_non_persistent_groups()`, `wp_cache_add_permanent_groups()` and `wp_cache_add_prefetch_groups()` functions.

2.   By adding group names to the `EAC_OBJECT_CACHE_GLOBAL_GROUPS`, `EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS`, `EAC_OBJECT_CACHE_PERMANENT_GROUPS` and `EAC_OBJECT_CACHE_PREFETCH_GROUPS` constants in wp-config.php.

Now, in addition, a developer can set the attribute by adding a suffix to the group name when storing and accessing the object.

+   Global group - `{group}:sitewide`
+   Non-Persistent group - `{group}:nocaching`
+   Permanent group - `{group}:permanent`
+   Prefetch group - `{group}:prefetch`

Adding a group suffix makes the group distinct (i.e. `{group}` <> `{group}:sitewide`). Multiple attributes are not supported.


== WP Cache Functions ==

= Implemented Standard and Non-Standard WP-Cache API Functions: =

[wp_cache_init](https://developer.wordpress.org/reference/functions/wp_cache_init/)()

[wp_cache_add](https://developer.wordpress.org/reference/functions/wp_cache_add/)( $key, $data, $group = '', $expire = 0 )

[wp_cache_add_multiple](https://developer.wordpress.org/reference/functions/wp_cache_add_multiple/)( array $data, $group = '', $expire = 0 )

[wp_cache_replace](https://developer.wordpress.org/reference/functions/wp_cache_replace/)( $key, $data, $group = '', $expire = 0 )

wp_cache_replace_multiple( array $data, $group = '', $expire = 0 )

[wp_cache_set](https://developer.wordpress.org/reference/functions/wp_cache_set/)( $key, $data, $group = '', $expire = 0 )

[wp_cache_set_multiple](https://developer.wordpress.org/reference/functions/wp_cache_set_multiple/)( array $data, $group = '', $expire = 0 )

[wp_cache_get](https://developer.wordpress.org/reference/functions/wp_cache_get/)( $key, $group = '', $force = false, &$found = null )

[wp_cache_get_multiple](https://developer.wordpress.org/reference/functions/wp_cache_get_multiple/)( $keys, $group = '', $force = false )

wp_cache_get_group( $group )

[wp_cache_delete](https://developer.wordpress.org/reference/functions/wp_cache_delete/)( $key, $group = '' )

[wp_cache_delete_multiple](https://developer.wordpress.org/reference/functions/wp_cache_delete_multiple/)( array $keys, $group = '' )

wp_cache_delete_group( $group )

[wp_cache_incr](https://developer.wordpress.org/reference/functions/wp_cache_incr/)( $key, $offset = 1, $group = '' )

[wp_cache_decr](https://developer.wordpress.org/reference/functions/wp_cache_decr/)( $key, $offset = 1, $group = '' )

[wp_cache_flush](https://developer.wordpress.org/reference/functions/wp_cache_flush/)()

[wp_cache_flush_runtime](https://developer.wordpress.org/reference/functions/wp_cache_flush_runtime/)()

[wp_cache_flush_group](https://developer.wordpress.org/reference/functions/wp_cache_flush_group/)( $group )

wp_cache_flush_blog( $blog_id = null )

[wp_cache_supports](https://developer.wordpress.org/reference/functions/wp_cache_supports/)( $feature )

[wp_cache_close](https://developer.wordpress.org/reference/functions/wp_cache_close/)()

[wp_cache_add_global_groups](https://developer.wordpress.org/reference/functions/wp_cache_add_global_groups/)( $groups )

[wp_cache_add_non_persistent_groups](https://developer.wordpress.org/reference/functions/)( $groups )

wp_cache_add_permanent_groups( $groups )

wp_cache_add_prefetch_groups( $groups )

wp_cache_add_group_expire( $groups )

[wp_cache_switch_to_blog](https://developer.wordpress.org/reference/functions/wp_cache_switch_to_blog/)( $blog_id )


= Examples =

```php
	/*
	 * set runtime options
	 */
	if (defined('EAC_OBJECT_CACHE_VERSION'))
	{
		global $wp_object_cache;
		$wp_object_cache->display_stats = 1;
		$wp_object_cache->log_stats = true;
		$wp_object_cache->log_errors = true;
	}

    /*
     * add custom groups to global (not blog-specific)
     */
    wp_cache_add_global_groups( [ 'ridiculous', 'absurd' ] );

    /*
     * calculate the sum of all digits in Pi multiplied by each known prime number...
     *  only do this once a year (or when cache is cleared) 'cause it may take a while.
     */
    if ( ! $result = wp_cache_get('calculation_result','ridiculous') ) {
        $result = do_calculation();
        wp_cache_set( 'calculation_result', $result, 'ridiculous', YEAR_IN_SECONDS );
    }

    /*
     * get all objects in the 'ridiculous' group.
     */
    if (wp_cache_supports( 'get_group' )) {
        $ridiculous = wp_cache_get_group( 'ridiculous' );
    }

    /*
     * erase the 'ridiculous' group
     */
    wp_cache_flush_group( 'ridiculous' );

    /*
     * erase the cache for this blog only (multisite)
     */
    if (wp_cache_supports( 'flush_blog' )) {
        wp_cache_flush_blog();
    }

    /*
     * using a group suffix to make the group global (site-wide).
     */
    if ( ! $result = wp_cache_get('my_query_result','my_query_group:sitewide') ) {
        $result = $wpdb->query( $wpdb->prepare( 'SELECT...' ) );
        wp_cache_set( 'my_query_result', $result, 'my_query_group:sitewide', DAY_IN_SECONDS );
    }

    /*
     * set a default expiration time by group
     */
    if (wp_cache_supports( 'group_expire' )) {
        wp_cache_add_group_expire( [
            'comment-queries'		=> DAY_IN_SECONDS,
            'site-queries'			=> DAY_IN_SECONDS,
            'network-queries'		=> DAY_IN_SECONDS,
            'post-queries'			=> DAY_IN_SECONDS,
            'term-queries'			=> DAY_IN_SECONDS,
            'user-queries'			=> DAY_IN_SECONDS,
        ] );
    }
```


== Installation ==

*{eac}ObjectCache* is an extension plugin to and is fully functional with installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

However, the core *object-cache.php* file may be installed without {eac}Doojigger (referred to as 'detached' mode).

In detached mode, the plugin will attempt to copy the *object-cache.php* file to the `/wp-content` folder on activation, or you may manually copy the *object-cache.php* file from the plugin `/src` folder to the `/wp-content` folder to activate. Options can then be set using the documented PHP constants in the `wp-config.php` file.

= Automatic Plugin Installation =

This plugin is available from the [WordPress Plugin Repository](https://wordpress.org/plugins/search/earthasylum/) and can be installed from the WordPress Dashboard » *Plugins* » *Add New* page. Search for 'EarthAsylum', click the plugin's [Install] button and, once installed, click [Activate].

See [Managing Plugins -> Automatic Plugin Installation](https://wordpress.org/support/article/managing-plugins/#automatic-plugin-installation-1)

= Upload via WordPress Dashboard =

Installation of this plugin can be managed from the WordPress Dashboard » *Plugins* » *Add New* page. Click the [Upload Plugin] button, then select the eacobjectcache.zip file from your computer.

See [Managing Plugins -> Upload via WordPress Admin](https://wordpress.org/support/article/managing-plugins/#upload-via-wordpress-admin)

= Manual Plugin Installation =

You can install the plugin manually by extracting the eacobjectcache.zip file and uploading the 'eacobjectcache' folder to the 'wp-content/plugins' folder on your WordPress server.

See [Managing Plugins -> Manual Plugin Installation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

= Settings =

Once installed and activated, options for this extension will show in the 'Object Cache' tab of {eac}Doojigger settings.


== Screenshots ==

1. Object Cache
![{eac}ObjectCache](https://ps.w.org/eacobjectcache/assets/screenshot-1.png)

2. Object Cache (Advanced Options)
![{eac}ObjectCache Advanced](https://ps.w.org/eacobjectcache/assets/screenshot-2.png)

3. Object Cache (Cache Stats)
![{eac}ObjectCache Stats](https://ps.w.org/eacobjectcache/assets/screenshot-3.png)


== Other Notes ==

= Additional Information =

*{eac}ObjectCache* is an extension plugin to and is fully functional with installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

However, the core *object-cache.php* file may be installed without {eac}Doojigger - referred to as 'detached' mode.

In detached mode, the plugin will attempt to copy the *object-cache.php* file to the `/wp-content` folder on activation, or you may manually copy the *object-cache.php* file from the plugin `/src` folder to the `/wp-content` folder to activate. Options can then be set using the documented PHP constants in the `wp-config.php` file.

= See Also =

[{eac}KeyValue](https://github.com/EarthAsylum/eacKeyValue) - An easy to use, efficient, key-value pair storage mechanism for WordPress that takes advatage of the WP Object Cache.


== Copyright ==

= Copyright © 2025, EarthAsylum Consulting, distributed under the terms of the GNU GPL. =

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


== Changelog ==

= Version 2.1.1 – October 17, 2025 =

+	FIX: proper return of empty/falsy APCu value.
+	Filter out unused APCu/SQL stats on dashboard widget.

= Version 2.1.0 – October 14, 2025 =

+	Added `EAC_OBJECT_CACHE_DISABLE_ALLOPTIONS` constant to disable use of WP `alloptions` array.
	+	More efficient with APCu, reduces memory and storage use.
	+	Requires WP 6.6.0+.
+	Prefetch `alloptions` and `notoptions` when not using APCu.
	+	Requests for `alloptions` and `notoptions` may be more than 70% of cache hits.
+	Support keys in pre-fetch groups (group=>key) with SQL wildcard;
+	Proper implementation of the `$force` option in `wp_cache_get()`.
+	Fixed pre-fetch loader.
+	Added dashboard widget - `showDashboardStats()`.
+	Use full cache pathname (not just directory) to get APCu prefix id.
+	Expire last sample after 1 day.
+	Rework stats array/output, including htmlStats labels.
+	Disabled `write_hooks` when using APCu (unnecessary).

= Version 2.0.0 – September 30, 2025 =

+	Added support for APCu caching as an intermediate layer between L1 and L2 caching.
+	Add `EAC_OBJECT_CACHE_USE_DB` constant to disable DB use.
+	Add `EAC_OBJECT_CACHE_USE_APCU` constant to disable APCu use.
+	Add `EAC_OBJECT_CACHE_OPTIMIZE_MEMORY` constant when using APCu.
+	Removed bulk import of transients on install/rebuild.
+	Added single import of transient on demand.
+	Use default `flush_group()` for `wp_cache_set_last_changed` hook.
+	Removed `select_all()`, use `select_each()`.
+	Add `log_stats` flag and check for external logging plugins.
+	Verify Query Manager active before triggering actions.
+	Type hint class variables.
+	Updated administrator page.
+	Rework stats arrays - 0=>hits, 1=>count, 2=>size (db total adds 3=>file size).
+	Many other internal changes and optimizations.

= Version 1.4.1 – July 12, 2025 =

+   Implemented `cache_clear_query_groups()` on `wp_cache_set_last_changed` hook to invalidate group cache for query groups.
    +   See https://make.wordpress.org/core/2023/07/17/improvements-to-the-cache-api-in-wordpress-6-3/
+   New `wp_cache_add_group_expire( $groups )` to set default ttl by group.
+   New `EAC_OBJECT_CACHE_GROUP_EXPIRE` to set default ttl by group.
+   Use `instanceof` instead of `is_a()` when checking `WP_Object_Cache`.

= Version 1.4.0 – June 27, 2025 =

+   Issue: Although setting `wp_using_ext_object_cache( false )` would seem to disable use of the object cache, this setting is rarely used.
    +   Fix: (A) check `wp_using_ext_object_cache()` on each external function call (i.e. wp_cache_get()). This has NOT been implemented as it is likely to cause many issues throughout WordPress by disabling all object caching.
    +   Fix: (B) selectively check `wp_using_ext_object_cache()` within the object-cache code. This has been implemented to suppress L2 (database) access only, retaining access to the current L1 (memory) cache.
    +   `wp_using_ext_object_cache( false )` may temporarily disable L2 caching while allowing L1 access.
+   New (internal) `select_cursor()`, `select_each()`, and updated `select_all()` methods for better cursor/array management when retrieving multiple records from L2.
+   New (non-standard) sub-group or group name suffix to indicate specific attributes of a group:
    +   Global group - `{group}:sitewide` (applies to all sites in a multi-site network).
    +   Non-Persistent group - `{group}:nocaching` (not saved in the L2 cache).
    +   Permanent group - `{group}:permanent` (no expiration time required or set).
    +   Prefetch group - `{group}:prefetch` (load to L1 cache on startup).
+   Enforce `wp_suspend_cache_addition()` on `wp_cache_set()` if the key doesn't already exist.
+   Circumvent $wpdb->query by using $wpdb->dbh with MySQLi to retrieve individual rows when importing transients.
+   Use SQL (not get_option) to get expiration when importing transients.
+   Set `wp_suspend_cache_addition(true)` and `wp_using_ext_object_cache(true)` when exporting transients to MySQL.
+   Don't set `wp_installing(true)` when importing transients.
+   New EAC_OBJECT_CACHE_DISABLE_TRANSIENT_IMPORT / EXPORT constants.
+   Fixed potential critical error on cache create caused by file permissions.

= Version 1.3.4 – June 17, 2025 =

+   Fixed bug (potential crash) introduced with persisting prefetch groups.

= Version 1.3.3 – June 10, 2025 =

+   Save (persist) added prefetch groups since pre-fetching happens before add.
+   Don't check database for prefetch group keys after pre-fetching.

= Version 1.3.2 – June 3, 2025 =

+   Fix error in object-cache.php & ObjectCache.admin when FS_CHMOD_FILE/DIR is not defined.

= Version 1.3.1 – April 19, 2025 =

+   Compatible with WordPress 6.8.
+   Prevent `_load_textdomain_just_in_time was called incorrectly` notice from WordPress.
    +   All extensions - via eacDoojigger 3.1.
    +   Modified extension registration in constructor.

= Version 1.3.0 - April 11, 2025 =

+   Support for 'detached' mode (installed without {eac}Doojigger)...
    +   Installs/uninstalls `object-cache.php` on activation/deactivation hooks.
    +   Or manually install `src/object-cache.php` to the `wp-content` folder.
+   Added option to purge/rebuild cache on a scheduled (hourly, twicedaily, daily, weekly, monthly) basis.
    +   Manually: `add_action( 'some_weekly_event', [ $wp_object_cache, 'rebuild_object_cache' ] );`
        +   assuming `some_weekly_event` has been scheduled with `wp_schedule_event(...)`.
+   Added `get_group()`, returns all L1 & L2 keys for a group.
+   Added `delete_group()` deletes all L1 & L2 keys for a group.
    +   flush_group() is immediate, delete_group() fetches rows and caches deletes.
+   Added `set_transient_doing_cron` to `write_hooks` array.
    +   Forces cache write when setting `doing_cron` transient.
+   Removed `action_scheduler_before_process_queue` action that prevented runtime cache flush.

= Version 1.2.2 - April 4, 2025 =

+   New `write_hooks` sets hook names that trigger an immediate cache write.
    +   Default is `[ 'cron_request', 'updated_option_cron' ]`
    to fix wp-cron jobs not running or rescheduling correctly.
    +   Can be set/overridden with `EAC_OBJECT_CACHE_WRITE_HOOKS` constant.
+   Added action on `action_scheduler_before_process_queue` hook to prevent cache flush triggered by Action Scheduler.

= Version 1.2.1 - March 30, 2025 =

+   Use `add_event_task` filter to add optimize scheduled task.
+   Fix probability error when set to 25.

= Version 1.2.0 - March 20, 2025 =

+   Optimize `insert... on conflict` statement.
+   Use `exec()` instead of `query()` where applicable.
+   Optimize stats sample select SQL.
+   Rework count/size in cache stats.
+   Don't wait for mu_plugins to load prefetch groups.
+   Added `optimize()` method with scheduled event (daily @1am).
    +   Purge expired records before vacuum.
+   Added `incremental_vacuum` on maintenance probability.
+   Allow defined constants to prevent outside actors from flushing caches.
+   Reworked option constants load.
+   Support Query Monitor logging and timings.
+   Prevent maintenance on ajax/cron/not-php requests.
+   New `init()` to allow instantiation before triggering use.
+   New `getStatsCache()`, `getStatsGroups()`, `getStatsDB()` used by `getStats()`.
+   Sampling/maintenance probability per blog/global.
+   `display_stats` is numeric indicating every n requests to sample.
+   Isolated admin extension for backend only.

= Version 1.1.0 - March 13, 2025 =

+   Change SQLite `synchronous` from OFF to NORMAL.
+   Use `insert... on conflict` rather than `replace`.
+   Add SQLite `mmap_size` to enable memory mapped I/O.
+   Add SQLite `page_size` to adjust page size.
+   Add SQLite `cache_size` to adjust cache size.
+   Add `WITHOUT ROWID` when creating table.
+   Ensure correct blog id when building key in multisite environment.
+   Optimize pre-fetch.
+   Export cached transients back to MySql when disabled.
+   Requires Sqlite3 version 3.24.0.

= Version 1.0.4 - May 24, 2024 =

+   Fix for updated {eac}Doojigger Advanced Mode, remove from 'Tools' tab.

= Version 1.0.3 - April 3, 2024 =

+   Added notice if eacDoojigger is not active.
+   Delete sqlite file(s) rather than records on flush.
+   Set wp_installing and fixed expiration when importing transients,
+   switch_to_blog() now flushes L1 and reloads prefetch groups.
+   flush_blog() use `$this->blog_id` instead of `get_current_blog_id()`.
+   Suppress flush message when 0 records flushed.
+   Use `options_form.requestSubmit()` instead of `options_form.submit()`.

= Version 1.0.2 - February 17, 2024 =

+   Minor updates as per WordPress review team.
+   Changed EAC_OBJECT_CACHE to EAC_OBJECT_CACHE_VERSION.
+   Added ABSPATH check in wp-cache.php.
+   Escape output of calculated cache ratio.

= Version 1.0.1 - January 24, 2024 =

+   Improved sanitization and output escaping.
+   Changed constant EACDOOJIGGER_OBJECT_CACHE to EAC_OBJECT_CACHE.

= Version 1.0.0 - December 9, 2023 =

+   First public release, submitted to WordPress review team.

= Version 0.5 =

+   Testing in live, multisite environment.
+   Ignore 'force_cache' flag (force L2 read).
    +   if we've updated a key, but not written yet, then force a persistent load, we lose that value.
+   Added wp_flush_blog() function.
+   Cache L2 misses saving sqlite selects on records known to not exist.
+   Don't attempt read or delete on non-persistent groups.
+   Added cache hit ratio to stats.
+   Remove function call counts (for testing).

= Version 0.4 =

+   Enhanced admin screen with advanced options.
+   Group constants used:
    +   EAC_OBJECT_CACHE_GLOBAL_GROUPS
    +   EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS
    +   EAC_OBJECT_CACHE_PERMANENT_GROUPS
    +   EAC_OBJECT_CACHE_PREFETCH_GROUPS
+   Added non-standard wp_cache_add_permanent_groups(), wp_cache_add_prefetch_groups()

= Version 0.3 =

+   Parameterize timeout, retries.
+   Import transients from MySQL.
+   Rework select/replace/delete SQL.
    +    New select_one(), select_all() methods.
+   key_exists(), key_exists_memory(), key_exists_database() replace _exists().
+   Add permanent groups (allow no expiration, overriding default expire).
+   Add function call counts (for testing).

= Version 0.2 =

+   Support add/get/set/delete _multiple methods (non-standard replace_multiple).
+   Add pre-fetch groups.
+   Add delayed writes.
+   Add settings via defined constants.
+   Add more detailed counts/stats.
+   Manage install/uninstall, activate/deactivate actions.

= Version 0.1 =

+   Simple memory caching with get/set persistent cache supporting wp-cache functions.
+   Testing SQLite methods.
