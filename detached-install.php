<?php
namespace EarthAsylumConsulting;

/**
 * {eac}Doojigger Object Cache - a persistent object cache using a SQLite database to cache WordPress objects
 *
 * Detached installer. Used only when {eac}Doojigger is not active.
 * Installs/Uninstalls object-cache.php to/from /wp-content.
 *
 * @category	WordPress Plugin
 * @package		{eac}ObjectCache\{eac}Doojigger Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 *
 * @version 25.0408.1
 */

defined( 'ABSPATH' ) || exit;

global $plugin;

register_activation_hook( $plugin, function()
	{
		global $wp_filesystem;
		$result = (is_a($wp_filesystem,'WP_Filesystem_Base'))
			? $wp_filesystem->copy(__DIR__."/src/object-cache.php", WP_CONTENT_DIR.'/object-cache.php', true)
			: copy(__DIR__."/src/object-cache.php", WP_CONTENT_DIR.'/object-cache.php');
		if ($result) {
			set_transient('eac_object_cache','activated',MINUTE_IN_SECONDS);
		}
	}
);

register_deactivation_hook( $plugin, function()
	{
		global $wp_filesystem,$wp_object_cache;
		if (method_exists($wp_object_cache, 'uninstall')) {
			$wp_object_cache->uninstall(true);
		}
		$result = (is_a($wp_filesystem,'WP_Filesystem_Base'))
			? $wp_filesystem->delete(WP_CONTENT_DIR.'/object-cache.php')
			: unlink(WP_CONTENT_DIR.'/object-cache.php');
	}
);

add_action( 'admin_footer', function()
	{
		$wp_content_dir = basename(WP_CONTENT_DIR);
		$message = '<p><strong>{eac}ObjectCache</strong> expects and is fully functional only when installed along with '.
				   '<a href="https://eacdoojigger.earthasylum.com/eacdoojigger" target="_blank">{eac}Doojigger</a>.</p>';
		if (get_transient('eac_object_cache') == 'activated') {
			$message .= '<p>The object cache has been installed in detached mode in the /'.$wp_content_dir.' folder.</p>';
			echo '<div class="notice notice-warning is-dismissible">'.$message.'</div>';
			delete_transient('eac_object_cache');
		} else if (!is_file(WP_CONTENT_DIR.'/object-cache.php')) {
			$message .= '<p>You may copy the <em>object-cache.php</em> file '.
						'from the plugin <em>/src</em> folder to the <em>/'.$wp_content_dir.'</em> folder '.
						'to activate the object cache in detached mode.</p>';
			echo '<div class="notice notice-warning is-dismissible">'.$message.'</div>';
		}
	}
);
