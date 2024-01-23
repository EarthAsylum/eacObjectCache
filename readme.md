## {eac}ObjectCache - SQLite powered WP_Object_Cache Drop-in.
- Plugin URI:         https://eacdoojigger.earthasylum.com/eacobjectcache/
- Author:             [EarthAsylum Consulting](https://www.earthasylum.com)
- Stable tag:         1.0.1
- Last Updated:       23-Jan-2023
- Requires at least:  5.5.0
- Tested up to:       6.4
- Requires PHP:       7.4
- Requires EAC:       2.4
- Contributors:       kevinburkholder
- License:            GPLv3 or later
- License URI:        https://www.gnu.org/licenses/gpl.html
- Tags:               cache, object cache, wp cache, sqlite, performance, {eac}Doojigger,
- WordPress URI:		https://wordpress.org/plugins/eacobjectcache

{eac}ObjectCache is a drop-in persistent object cache using a SQLite database to cache WordPress objects.

### Description

The _{eac}Doojigger Object Cache_ ({eac}ObjectCache) is a light-weight and very efficient drop-in persistent object cache that uses a fast SQLite database to cache WordPress objects.

See [The WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/)

>	The WordPress Object Cache is used to save on trips to the database. The Object Cache stores all of the cache data to memory and makes the cache contents available by using a key, which is used to name and later retrieve the cache contents.

>	By default, the object cache is non-persistent. This means that data stored in the cache resides in memory only and only for the duration of the request. Cached data will not be stored persistently across page loads unless you install a persistent caching plugin.

Here, an object is any piece of data - a number, text, a set of database records, an API response, etc. - that can be referenced by a name or key. Objects are categorized by a group name. Groups help identify what an object is and how it is used.

{eac}ObjectCache replaces the default WordPress object cache to not only store data in memory but to also store data persistently, across requests, in a SQLite database, increasing the likelihood of cache hits and decreasing the need for costly computations, complex MySQL database queries, and remote API requests.

SQLite is a fast, small, single-file relational database engine. By using SQLite to store objects, {eac}ObjectCache is able to manage a relatively large amount of data (groups, keys, and values) in a very efficient and fast data-store.

#### Features

+	Lightweight, efficient, and fast!
+	L1 (memory) _and_ L2 (SQLite) caching.
+	Supports Write-Back (delayed transactions) or Write-Through caching.
+	Cache by object group name.
	+	Preserves uniqueness of keys.
	+	Manage keys by group name.
+	Pre-fetch object groups from L2 to L1 cache.
+	Caches and pre-fetches L2 misses (known to not be in L2 cache).
	+	Prevents repeated, unnecessary L2 cache reads across requests.
+	Multisite / Network support:
	+	Cache by blog id.
	+	Flush by blog id.
+	Caching statistics:
	+	Overall and L1/L2 hits, misses, & ratio.
	+	L1 hits by object groups.
	+	L2 group keys stored.
	+	L2 select/update/delete/commit counts.
+	Supports a superset of WP_Object_Cache functions.
+	Imports existing transients when enabled.
+	Easily enabled or disabled from administrator page.
+	Uses the PHP Data Objects (PDO) extension included with PHP.


### Settings

Several cache settings can be modified by adding defined constants to the `wp-config.php` file. The default settings are recommended and optimal in most cases but individual settings may need to be adjusted based on traffic volume, specific requirements, or unique circumstances.

* * *

+	To set the location of the SQLite database 					(default: ../wp-content/cache):

```
	define( 'EAC_OBJECT_CACHE_DIR', '/full/path/to/folder' );
```

This folder can be outside of the web-accessable folders of your site - i.e. above the document root (htdocs, www, etc.) - provided that PHP can access (read/write) the folder (see the PHP *open_basedir* directive).

This folder should not be on a network share or other remote media. We're caching data for quick access, the cache folder should be on fast, local media.

* * *

+	To set the name of the SQLite database 						(default: '.eac_object_cache.sqlite'):

```
	define( 'EAC_OBJECT_CACHE_FILE', 'filename.sqlite' );
```

In addition to the database file, SQLite may also create temporary files using the same file name with a '-shm' and '-wal' suffix.

* * *

+	To set SQLite journal mode									(default: 'WAL'):

```
	define( 'EAC_OBJECT_CACHE_JOURNAL_MODE', journal_mode )
```

*journal_mode* can be one of 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', or 'OFF'.
See [SQLite journal mode](https://www.sqlite.org/pragma.html#pragma_journal_mode)

* * *

+	To set SQLite timeout 										(default: 3):

```
	define( 'EAC_OBJECT_CACHE_TIMEOUT', int );
```

Sets the number of seconds before a SQLite transaction may timeout in error:

* * *

+	To set SQLite retries 										(default: 3):

```
	define( 'EAC_OBJECT_CACHE_RETRIES', int );
```

Sets the number of retries to attempt on critical actions.

* * *

+	To set delayed writes 										(default: 32):

```
	define( 'EAC_OBJECT_CACHE_DELAYED_WRITES', true|false|int );
```

{eac}ObjectCache caches all objects in memory and writes new or updated objects to the L2 (SQLite) cache. *delayed writes* simply holds objects in memory until the number of objects reaches a specified threshold, then writes them, in a single transaction, to the L2 cache (a.k.a. write-back caching). Setting *delayed writes* to false turns this functionality off (a.k.a. write-through caching). Setting to true writes all records only at the end of the script process/page load. Setting this to a number sets the object pending threshold to that number of objects.

* * *

+	To set the default expiration time (in seconds)				(default: 0 [never]):

```
	define( 'EAC_OBJECT_CACHE_DEFAULT_EXPIRE', -1|0|int );
```

When using the default WordPress object cache, object expiration isn't very important because the entire cache expires at the end of the script process/page load. With a persistent cache, this isn't the case. When an object is cached, the developer has the option of specifying an expiration time for that object. Since we don't know the intent of the developer when not specifying an expiration time, cache persistence *may* sometimes cause issues. Setting *default expiration* may alleviate problems and/or possibly improve performance by limiting cache data. When set to -1, objects with no expiration are not saved in the L2 cache.

_\*  Transients with no expiration overide this setting and are allowed (as that is the normal WordPress functionality)._

_\*  More often than not, unexpired objects are updated when the source data has changed and do not present any issues._

* * *

+	To enable or disable pre-fetching of cache misses 			(default: true [enabled]):

```
	define( 'EAC_OBJECT_CACHE_PREFETCH_MISSES', true | false );
```

Pre-fetching cache misses (keys that are not in the L2 persistent cache) prevents repeated, unnecessary reads of the L2 cache.

* * *

+	To set maintenance/sampling probability						(default: 100):

```
	define( 'EAC_OBJECT_CACHE_PROBABILITY', int );
```

Sets the probability of running maintenance & sampling tasks (approximately 1 in n requests).

* * *

+	Object groups that are global (not site-specific) in a multi-site/network environment:

```
	define( 'EAC_OBJECT_CACHE_GLOBAL_GROUPS', [ 'groupA', 'groupB', ... ] );
```

Global Object groups are not tagged with or separated by the site/blog id.

_\* WordPress already defines several global groups that do not need to be duplicated here, rather the groups entered here are added to those defined by WordPress._


* * *

+	Object groups that should not be stored in the persistent cache:

```
	define( 'EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS', [ 'groupA', 'groupB', ... ] );
```

Non-persistent groups are object groups that do not persist across page loads. This may be another method to alleviate issues caused by cache persistence or to improve performance by limiting cache data.

_\* WordPress already defines several non-persistent groups that do not need to be duplicated here, rather the groups entered here are added to those defined by WordPress._

* * *

+	Object groups that are allowed permanence:

```
	define( 'EAC_OBJECT_CACHE_PERMANENT_GROUPS', [ 'groupA', 'groupB', ... ] );
```

When setting a default expiration (`EAC_OBJECT_CACHE_DEFAULT_EXPIRE`) for objects without an expiration, these groups are excluded from using the default, allowing them to be permanent (with no expiration). Transients and site-transients are automatically included.

* * *

+	To pre-fetch specific object groups from the L2 cache at startup:

```
	define( 'EAC_OBJECT_CACHE_PREFETCH_GROUPS', [ 'groupA', 'groupB', ... ] );
```

Pre-fetching a group of records may be much faster than loading each key individually, but may load keys that are not needed, using memory unnecessarily.

#### Utility methods

+	Outputs an html table of current stats. Use `$wp_object_cache->statsCSS` to style.

```
	$wp_object_cache->htmlStats();
```

+	Outputs an html table of current stats similar to that generated by the default WordPress object cache.

```
	$wp_object_cache->stats();
```

+	Returns an array of current stats.

```
	$wp_object_cache->getStats();
```

+	Returns an array of stats from the last sample saved (or current).

```
	$wp_object_cache->getLastSample();
```

####  Optional runtime settings

+	Delay writing to database until shutdown or n pending records (see *delayed writes*).

```
	$wp_object_cache->delayed_writes = true | false | n;
```

+	Outputs an administrator notice using htmlStats().

```
	$wp_object_cache->display_stats = true | 'current' | 'sample';
```

+ 	Outputs an administrator notice on error.

```
	$wp_object_cache->display_errors = true;
```

+ 	Log errors to {eac}Doojigger log.

```
	$wp_object_cache->log_errors = true;
```


### WP-Cache

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

[wp_cache_delete](https://developer.wordpress.org/reference/functions/wp_cache_delete/)( $key, $group = '' )

[wp_cache_delete_multiple](https://developer.wordpress.org/reference/functions/wp_cache_delete_multiple/)( array $keys, $group = '' )

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


#### Examples

```php
	/*
	 * add custom groups to pre-fetch
	 */
	if (wp_cache_supports( 'prefetch_groups' )) {
		wp_cache_add_prefetch_groups( [ 'ridiculous', 'absurd' ] );
	}

	/*
	 * calculate the sum of all digits in Pi multiplied by each known prime number...
	 *  only do this once a year (or when cache is cleared) 'cause it may take a while.
	 */
	if ( ! $result = wp_cache_get('calculation_result','ridiculous') ) {
		$result = do_calculation();
		wp_cache_set( 'calculation_result', $result, 'ridiculous', YEAR_IN_SECONDS );
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


### Installation

**{eac}ObjectCache** is an extension plugin to and requires installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

_\* Currently pending approval from the WordPress Plugin Repository._

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

Once installed and activated options for this extension will show in the 'Tools' or 'Object Cache' tab of {eac}Doojigger settings.


### Screenshots

1. Object Cache (Tools)
![{eac}ObjectCache](https://d2xk802d4616wu.cloudfront.net/eacobjectcache/assets/screenshot-1.png)

2. Object Cache (Advanced Options)
![{eac}ObjectCache Advanced](https://d2xk802d4616wu.cloudfront.net/eacobjectcache/assets/screenshot-2.png)

3. Object Cache (Cache Stats)
![{eac}ObjectCache Stats](https://d2xk802d4616wu.cloudfront.net/eacobjectcache/assets/screenshot-3.png)



### Other Notes

#### Additional Information

+   {eac}ObjectCache is an extension plugin to and requires installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).


### Copyright

#### Copyright © 2023, EarthAsylum Consulting, distributed under the terms of the GNU GPL.

- This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


### Changelog

#### Version 1.0.1 - January 23, 2023

+	Improved sanitization and output escaping.

#### Version 1.0.0 - December 9, 2023

+	First public release.

#### Version 0.5

+	Testing in live, multisite environment.
+	Ignore 'force_cache' flag (force L2 read).
	+	if we've updated a key, but not written yet, then force a persistent load, we lose that value.
+	Added wp_flush_blog() function.
+	Cache L2 misses saving sqlite selects on records known to not exist.
+	Don't attempt read or delete on non-persistent groups.
+	Added cache hit ratio to stats.
+	Remove function call counts (for testing).

#### Version 0.4

+	Enhanced admin screen with advanced options.
+	Group constants used:
	+	EAC_OBJECT_CACHE_GLOBAL_GROUPS
	+	EAC_OBJECT_CACHE_NON_PERSISTENT_GROUPS
	+	EAC_OBJECT_CACHE_PERMANENT_GROUPS
	+	EAC_OBJECT_CACHE_PREFETCH_GROUPS
+	Added non-standard wp_cache_add_permanent_groups(), wp_cache_add_prefetch_groups()

#### Version 0.3

+	Parameterize timeout, retries.
+	Import transients from MySQL.
+	Rework select/replace/delete SQL.
	+	 New select_one(), select_all() methods.
+	key_exists(), key_exists_memory(), key_exists_database() replace _exists().
+	Add permanent groups (allow no expiration, overriding default expire).
+	Add function call counts (for testing).

#### Version 0.2

+	Support add/get/set/delete _multiple methods (non-standard replace_multiple).
+	Add pre-fetch groups.
+	Add delayed writes.
+	Add settings via defined constants.
+	Add more detailed counts/stats.
+	Manage install/uninstall, activate/deactivate actions.

#### Version 0.1

+	Simple memory caching with get/set persistent cache supporting wp-cache functions.
+	Testing SQLite methods.
