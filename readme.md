## {eac}ObjectCache - a persistent object cache using SQLite & APCu to cache WordPress objects.
[![EarthAsylum Consulting](https://img.shields.io/badge/EarthAsylum-Consulting-0?&labelColor=6e9882&color=707070)](https://earthasylum.com/)
[![WordPress](https://img.shields.io/badge/WordPress-Plugins-grey?logo=wordpress&labelColor=blue)](https://wordpress.org/plugins/search/EarthAsylum/)
[![eacDoojigger](https://img.shields.io/badge/Requires-%7Beac%7DDoojigger-da821d)](https://eacDoojigger.earthasylum.com/)
[![Sponsorship](https://img.shields.io/static/v1?label=Sponsorship&message=%E2%9D%A4&logo=GitHub&color=bf3889)](https://github.com/sponsors/EarthAsylum)

<details><summary>Plugin Header</summary>

Plugin URI:         https://eacdoojigger.earthasylum.com/eacobjectcache/  
Author:             [EarthAsylum Consulting](https://www.earthasylum.com)  
Stable tag:         2.0.0  
Last Updated:       30-Sep-2025  
Requires at least:  5.8  
Tested up to:       6.8  
Requires PHP:       8.1  
Contributors:       [kevinburkholder](https://profiles.wordpress.org/kevinburkholder)  
Donate link:        https://github.com/sponsors/EarthAsylum  
License:            GPLv3 or later  
License URI:        https://www.gnu.org/licenses/gpl.html  
Tags:               object cache, wp cache, APCu, sqlite, persistent object cache, performance, {eac}Doojigger,  
WordPress URI:      https://wordpress.org/plugins/eacobjectcache  
GitHub URI:         https://github.com/EarthAsylum/eacObjectCache  

</details>

> {eac}ObjectCache is a persistent object cache using APCu & SQLite to cache WordPress objects; A drop-in replacement to the WP_Object_Cache used by WordPress.

### Description

The _{eac}ObjectCache_ is a light-weight and very efficient drop-in persistent object cache that uses a fast SQLite database and even faster APCu shared memory to cache WordPress objects.

See [The WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/)

>   The WordPress Object Cache is used to save on trips to the database. The Object Cache stores all of the cache data to memory and makes the cache contents available by using a key, which is used to name and later retrieve the cache contents.

>   By default, the object cache is non-persistent. This means that data stored in the cache resides in memory only and only for the duration of the request. Cached data will not be stored persistently across page loads unless you install a persistent caching plugin.

Here, an object is any piece of data - a number, text, a set of database records, an API response, etc. - that can be referenced by a name or key. Objects are categorized by a group name. Groups help identify what an object is and how it is used.

{eac}ObjectCache replaces the default WordPress object cache to not only store data in process memory but to also store data persistently, across requests, in APCu shared memory and/or in a SQLite database, increasing the likelihood of cache hits and decreasing the need for costly computations, complex MySQL database queries, and remote API requests.

SQLite is a fast, small, single-file relational database engine. By using SQLite to store objects, {eac}ObjectCache is able to manage a relatively large amount of data (groups, keys, and values) in a very efficient and fast data-store.

[APCu](https://www.php.net/manual/en/book.apcu.php) is a shared, in-memory, persistent cache available only when the [APCu PECL Extension](https://www.php.net/manual/en/apcu.setup.php) is installed. {eac}ObjectCache uses APCu as an intermediate cache between the L1 memory cache and the L2 SQLite database cache providing extremely fast object retrieval,

{eac}ObjectCache always uses per-request, in-memory caching and may operate with either APCu memory caching or SQLite database caching - or both. APCu memory caching uses a single block of memory shared by all PHP requests and is persistent until and unless the cache is cleared or the server is rebooted (or PHP restarted). SQLite database caching is persistent until and unless the cache is deliberately cleared.

#### Features

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
    +   Overall and L1/APCu/L2 hits, misses, & ratio.
    +   L1 hits by object groups.
    +   Number of APCu and SQLite keys stored.
    +   L2 select/update/delete/commit counts.
+   Supports an enhanced superset of WP Object Cache functions.
+   Easily enabled or disabled from {eac}Doojigger administrator page.
    +   Imports existing MySQL transients.
    +   Exports cached transients to MySQL when disabled.
+   Automatically cleans and optimizes SQLite database.
+   Optionally schedule periodic cache invalidation and rebuild.
+   Uses the PHP Data Objects (PDO) extension included with PHP.

_While {eac}ObjectCache does support multiple installations on a single server it does not support multiple servers per installation. SQLite and APCu work only on a single server, not in a clustered or load-balanced environment._

#### Configuration Alternatives

Assuming you have SQLite and APCu installed, what are your best options?

1.	Fastest Caching

	+	Disable SQLite. [^1]
		+	`define( 'EAC_OBJECT_CACHE_USE_DB', false );`
	+	_Uses in-process memory and APCu shared memory._
	+ 	Advantage
		+	Fast memory-only access.
	+ 	Disadvantage
		+ 	APCu may invalidate data under memory constraint.
		+	APCu cache is lost on system or PHP restart.

2.	Less memory (almost as fast)

	+	Disable SQLite. [^1]
		+	`define( 'EAC_OBJECT_CACHE_USE_DB', false );`
	+	Optimize memory use. [^1]
		+	`define( 'EAC_OBJECT_CACHE_OPTIMIZE_MEMORY', true );`
	+	_Uses in-process memory and APCu shared memory._
	+ 	Advantage
		+	Fast memory-only access.
		+	Conserves per-request memory by not pushing APCu hits to in-process memory.
	+ 	Disadvantage
		+	Slightly slower to access APCu memory over in-process memory.
		+ 	APCu may invalidate data under memory constraint.
		+	APCu cache is lost on system or PHP restart.

3.	Most resilient (and still fast)

	+	Do nothing, this is the default.
	+	_Uses in-process memory, APCu shared memory, and SQLite database._
	+ 	Advantage
		+	Most cache hits will come from in-process and APCu memory.
		+	SQLite retains cache data after restart.
	+ 	Disadvantage
		+ 	Must keep SQLite database (on disk) updated.

4.	Resilient, efficient, and fast (recommended)

	+	Optimize memory use. [^1]
		+	`define( 'EAC_OBJECT_CACHE_OPTIMIZE_MEMORY', true );`
	+	_Uses in-process memory, APCu shared memory, and SQLite database._
	+ 	Advantage
		+	Most cache hits will come from in-process and APCu memory.
		+	Conserves per-request memory by not pushing APCu hits to in-process memory.
		+	SQLite retains cache data after restart.
	+ 	Disadvantage
		+	Slightly slower to access APCu memory over in-process memory.
		+ 	Must keep SQLite database (on disk) updated.

5.	Least efficient (default when APCu is not installed)

	+	Disable APCu. [^1]
		+	`define( 'EAC_OBJECT_CACHE_USE_APCU', false );`
	+	_Uses in-process memory and SQLite database._
	+ 	Advantage
		+	Saves resources by not taking up APCu reserves.
		+	SQLite retains cache data after restart.
	+ 	Disadvantage
		+	All cached data read from disk.
		+ 	Must keep SQLite database (on disk) updated.

[^1]: These options may be set from the {eac}Doojigger administration screen.

_When using SQLite, `delayed writes` (see below) dramatically improves efficiency by only writing updates at the end of the script process._

#### Inside The Numbers

<img alt="Cache Counts" width="325" src="https://ps.w.org/eacobjectcache/assets/wpoc_example.png" />

- - -

| Label             | Value               |
| :--------------   | :---------------    |
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

* When a request results in a *L2 SQL miss*, the key is added to the L1 memory or L2 APCu cache as a miss so that additional requests for the same key do not result in additional SQL selects. This is known as a *negative hit* and still counted as a *cache miss*.

### Settings

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

When using APCu memory caching, optimize internal memory by not storing APCu data in the L1 memory cache. This may slightly increase processing time as cache hits will come through APCu but will reduce the per-process memory usage. This may also be advantageous on high volume systems where a single object may be updated by simultaneous processes.

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

#### Utility methods

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
    $wp_object_cache->htmlStats( $full=false );
```

+   Outputs an html table of current stats similar to that generated by the default WordPress object cache.

```
    $wp_object_cache->stats( $full=false );
```

+   Returns an array of current stats.

```
    $cacheStats = $wp_object_cache->getStats( $full=false );
```

+   Returns an array of stats from the last sample saved (or current).

```
    $cacheStats = $wp_object_cache->getLastSample();
```

####  Optional runtime settings

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

#### Group Name Attributes

Specifying group attributes can be done in two ways:

1.   Using the `wp_cache_add_global_groups()`, `wp_cache_add_non_persistent_groups()`, `wp_cache_add_permanent_groups()` and `wp_cache_add_prefetch_groups()` functions.

2.   By adding group names to the `EAC_OBJECT_CACHE_GLOBAL_GROUPS`, `EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS`, `EAC_OBJECT_CACHE_PERMANENT_GROUPS` and `EAC_OBJECT_CACHE_PREFETCH_GROUPS` constants in wp-config.php.

Now, in addition, a developer can set the attribute by adding a suffix to the group name when storing and accessing the object.

+   Global group - `{group}:sitewide`
+   Non-Persistent group - `{group}:nocaching`
+   Permanent group - `{group}:permanent`
+   Prefetch group - `{group}:prefetch`

Adding a group suffix makes the group distinct (i.e. `{group}` <> `{group}:sitewide`). Multiple attributes are not supported.


### WP Cache Functions

#### Implemented Standard and Non-Standard WP-Cache API Functions:

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


#### Examples

```php
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
            'comment-queries'		=> WEEK_IN_SECONDS,
            'site-queries'			=> WEEK_IN_SECONDS,
            'network-queries'		=> WEEK_IN_SECONDS,
            'post-queries'			=> WEEK_IN_SECONDS,
            'term-queries'			=> WEEK_IN_SECONDS,
            'user-queries'			=> WEEK_IN_SECONDS,
        ] );
    }
```


### Installation

*{eac}ObjectCache* is an extension plugin to and is fully functional with installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

However, the core *object-cache.php* file may be installed without {eac}Doojigger (referred to as 'detached' mode).

In detached mode, the plugin will attempt to copy the *object-cache.php* file to the `/wp-content` folder on activation, or you may manually copy the *object-cache.php* file from the plugin `/src` folder to the `/wp-content` folder to activate. Options can then be set using the documented PHP constants in the `wp-config.php` file.

#### Automatic Plugin Installation

This plugin is available from the [WordPress Plugin Repository](https://wordpress.org/plugins/search/earthasylum/) and can be installed from the WordPress Dashboard » *Plugins* » *Add New* page. Search for 'EarthAsylum', click the plugin's [Install] button and, once installed, click [Activate].

See [Managing Plugins -> Automatic Plugin Installation](https://wordpress.org/support/article/managing-plugins/#automatic-plugin-installation-1)

#### Upload via WordPress Dashboard

Installation of this plugin can be managed from the WordPress Dashboard » *Plugins* » *Add New* page. Click the [Upload Plugin] button, then select the eacobjectcache.zip file from your computer.

See [Managing Plugins -> Upload via WordPress Admin](https://wordpress.org/support/article/managing-plugins/#upload-via-wordpress-admin)

#### Manual Plugin Installation

You can install the plugin manually by extracting the eacobjectcache.zip file and uploading the 'eacobjectcache' folder to the 'wp-content/plugins' folder on your WordPress server.

See [Managing Plugins -> Manual Plugin Installation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

#### Settings

Once installed and activated, options for this extension will show in the 'Object Cache' tab of {eac}Doojigger settings.


### Screenshots

1. Object Cache
![{eac}ObjectCache](https://ps.w.org/eacobjectcache/assets/screenshot-1.png)

2. Object Cache (Advanced Options)
![{eac}ObjectCache Advanced](https://ps.w.org/eacobjectcache/assets/screenshot-2.png)

3. Object Cache (Cache Stats)
![{eac}ObjectCache Stats](https://ps.w.org/eacobjectcache/assets/screenshot-3.png)


### Other Notes

#### Additional Information

*{eac}ObjectCache* is an extension plugin to and is fully functional with installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

However, the core *object-cache.php* file may be installed without {eac}Doojigger - referred to as 'detached' mode.

In detached mode, the plugin will attempt to copy the *object-cache.php* file to the `/wp-content` folder on activation, or you may manually copy the *object-cache.php* file from the plugin `/src` folder to the `/wp-content` folder to activate. Options can then be set using the documented PHP constants in the `wp-config.php` file.

#### See Also

[{eac}KeyValue](https://github.com/EarthAsylum/eacKeyValue) - An easy to use, efficient, key-value pair storage mechanism for WordPress that takes advatage of the WP Object Cache.


### Copyright

#### Copyright © 2025, EarthAsylum Consulting, distributed under the terms of the GNU GPL.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.  

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


