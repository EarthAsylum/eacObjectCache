=== {eac}ObjectCache - a persistent object cache using a SQLite database to cache WordPress objects. ===
Plugin URI:         https://eacdoojigger.earthasylum.com/eacobjectcache/
Author:             [EarthAsylum Consulting](https://www.earthasylum.com)
Stable tag:         1.3.3
Last Updated:       10-Jun-2025
Requires at least:  5.8
Tested up to:       6.8
Requires PHP:       7.4
Requires EAC:       3.1
Contributors:       kevinburkholder
License:            GPLv3 or later
License URI:        https://www.gnu.org/licenses/gpl.html
Tags:               persistent object cache, object cache, wp cache, sqlite, performance, {eac}Doojigger,
WordPress URI:      https://wordpress.org/plugins/eacobjectcache
GitHub URI:         https://github.com/EarthAsylum/eacObjectCache

{eac}ObjectCache is a persistent object cache using a SQLite database to cache WordPress objects; A drop-in replacement to the WP_Object_Cache used by WordPress.

== Description ==

The _{eac}Doojigger Object Cache_ ({eac}ObjectCache) is a light-weight and very efficient drop-in persistent object cache that uses a fast SQLite database to cache WordPress objects.

See [The WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/)

>   The WordPress Object Cache is used to save on trips to the database. The Object Cache stores all of the cache data to memory and makes the cache contents available by using a key, which is used to name and later retrieve the cache contents.

>   By default, the object cache is non-persistent. This means that data stored in the cache resides in memory only and only for the duration of the request. Cached data will not be stored persistently across page loads unless you install a persistent caching plugin.

Here, an object is any piece of data - a number, text, a set of database records, an API response, etc. - that can be referenced by a name or key. Objects are categorized by a group name. Groups help identify what an object is and how it is used.

{eac}ObjectCache replaces the default WordPress object cache to not only store data in memory but to also store data persistently, across requests, in a SQLite database, increasing the likelihood of cache hits and decreasing the need for costly computations, complex MySQL database queries, and remote API requests.

SQLite is a fast, small, single-file relational database engine. By using SQLite to store objects, {eac}ObjectCache is able to manage a relatively large amount of data (groups, keys, and values) in a very efficient and fast data-store.

= Features =

+   Lightweight, efficient, and fast!
+   L1 (memory) _and_ L2 (SQLite) caching.
+   Supports Write-Back (delayed transactions) or Write-Through caching.
+   Cache by object group name.
    +   Preserves uniqueness of keys.
    +   Manage keys by group name.
+   Pre-fetch object groups from L2 to L1 cache.
+   Caches and pre-fetches L2 misses (known to not exist in L2 cache).
    +   Prevents repeated, unnecessary L2 cache reads across requests.
+   Multisite / Network support:
    +   Cache/flush/switch by blog id.
+   Caching statistics:
    +   Cache hits typically well above 90%.
    +   Overall and L1/L2 hits, misses, & ratio.
    +   L1 hits by object groups.
    +   L2 group keys stored.
    +   L2 select/update/delete/commit counts.
+   Supports an enhanced superset of WP_Object_Cache functions.
+   Easily enabled or disabled from {eac}Doojigger administrator page.
    +   Imports existing transients when enabled.
    +   Exports cached transients when disabled.
+   Automatically cleans and optimizes SQLite database.
+   Optionally schedule periodic L2 cache rebuild.
+   Uses the PHP Data Objects (PDO) extension included with PHP.


== Settings ==

Several cache settings can be modified by adding defined constants to the `wp-config.php` file. The default settings are recommended and optimal in most cases but individual settings may need to be adjusted based on traffic volume, specific requirements, or unique circumstances. Most of these settings can be adjusted in the {eac}Doojigger administrator screen.

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

{eac}ObjectCache caches all objects in memory and writes new or updated objects to the L2 (SQLite) cache. *delayed writes* simply holds objects in memory until the number of objects reaches a specified threshold, then writes them, in a single transaction, to the L2 cache (a.k.a. write-back caching). Setting *delayed writes* to false turns this functionality off (a.k.a. write-through caching). Setting to true writes all records only at the end of the script process/page request. Setting this to a number sets the object pending threshold to that number of objects.

* * *

+   To set the default expiration time (in seconds)             (default: 0 [never]):

```
    define( 'EAC_OBJECT_CACHE_DEFAULT_EXPIRE', -1|0|int );
```

When using the default WordPress object cache, object expiration isn't very important because the entire cache expires at the end of the script process/page request. With a persistent cache, this isn't the case. When an object is cached, the developer has the option of specifying an expiration time for that object. Since we don't know the intent of the developer when not specifying an expiration time, cache persistence *may* sometimes cause issues. Setting *default expiration* may alleviate problems and/or possibly improve performance by limiting cache data. When set to -1, objects with no expiration are not saved in the L2 cache.

_\*  Transients with no expiration overide this setting and are allowed (as that is the normal WordPress functionality)._

_\*  More often than not, unexpired objects are updated when the source data has changed and do not present any issues._

* * *

+   To enable or disable pre-fetching of cache misses           (default: true [enabled]):

```
    define( 'EAC_OBJECT_CACHE_PREFETCH_MISSES', true | false );
```

Pre-fetching cache misses (keys that are not in the L2 persistent cache) prevents repeated, unnecessary reads of the L2 cache.

* * *

+   To set maintenance probability                              (default: 1000):

```
    define( 'EAC_OBJECT_CACHE_PROBABILITY', int );
```

Sets the probability of running maintenance (garbage collection) tasks - approximately 1 in n requests, n>=10.

* * *

+   Object groups that are global (not site-specific) in a multi-site/network environment:

```
    define( 'EAC_OBJECT_CACHE_GLOBAL_GROUPS', [ 'groupA', 'groupB', ... ] );
```

Global Object groups are not tagged with or separated by the site/blog id.

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

Pre-fetching a group of records may be much faster than loading each key individually, but may load keys that are not needed, using memory unnecessarily.

* * *

+   To prevent outside actors (scripts, plugins, etc.), including WordPress, from flushing caches.

```
    define( 'EAC_OBJECT_CACHE_DISABLE_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_FULL_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_GROUP_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_BLOG_FLUSH', true );
    define( 'EAC_OBJECT_CACHE_DISABLE_RUNTIME_FLUSH', true );
```

= Utility methods =

+   Outputs an html table of current stats. Use `$wp_object_cache->statsCSS` to style.

```
    $wp_object_cache->htmlStats();
```

+   Outputs an html table of current stats similar to that generated by the default WordPress object cache.

```
    $wp_object_cache->stats();
```

+   Returns an array of current stats.

```
    $cacheStats = $wp_object_cache->getStats();
```

+   Returns an array of stats from the last sample saved (or current).

```
    $cacheStats = $wp_object_cache->getLastSample();
```

=  Optional runtime settings =

+   Delay writing to database until shutdown or n pending records (see *delayed writes*).

```
    $wp_object_cache->delayed_writes = true | false | n;
```

+    Samples (every n requests) & outputs an admin notice with htmlStats().

```
    $wp_object_cache->display_stats = n;
```

+    Change the default expiration time for objects with no expiration.

```
    $wp_object_cache->default_expire = n;
```

+   Outputs an administrator notice on error.

```
    $wp_object_cache->display_errors = true;
```

+   Log errors to {eac}Doojigger log.

```
    $wp_object_cache->log_errors = true;
```


== WP-Cache ==

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

[wp_cache_switch_to_blog](https://developer.wordpress.org/reference/functions/wp_cache_switch_to_blog/)( $blog_id )


= Examples =

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
```


== Installation ==

*{eac}ObjectCache* is an extension plugin to and is fully functional with installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

However, the core `object-cache.php` file may be installed without {eac}Doojigger (referred to as 'detached' mode).

In detached mode, the plugin will attempt to copy the `object-cache.php` file to the `/wp-content` folder on activation, or you may manually copy the `object-cache.php` file from the plugin `/src` folder to the `/wp-content` folder to activate. Options can then be set using the documented PHP constants in the `wp-config.php` file.

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

However, the core `object-cache.php` file may be installed without {eac}Doojigger - referred to as 'detached' mode.

In detached mode, the plugin will attempt to copy the `object-cache.php` file to the `/wp-content` folder on activation, or you may manually copy the `object-cache.php` file from the plugin `/src` folder to the `/wp-content` folder to activate. Options can then be set using the documented PHP constants in the `wp-config.php` file.


== Copyright ==

= Copyright © 2025, EarthAsylum Consulting, distributed under the terms of the GNU GPL. =

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


== Changelog ==

= Version 1.3.3 – June 10, 2025 =

+   Save (persist) added prefetch groups since pre-fetching happens before add.
+   Don't check databasse for prefetch group keys after pre-fetching.

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
