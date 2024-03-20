<?php
namespace EarthAsylumConsulting;

/**
 * {eac}Doojigger Object Cache - SQLite powered WP_Object_Cache Drop-in.
 *
 * @category	WordPress Plugin
 * @package		{eac}ObjectCache\{eac}Doojigger Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 * @link		https://eacDoojigger.earthasylum.com/
 *
 * @wordpress-plugin
 * Plugin Name:			{eac}ObjectCache
 * Description:			{eac}Doojigger Object Cache - SQLite powered WP_Object_Cache Drop-in
 * Version:				1.0.3
 * Requires at least:	5.5.0
 * Tested up to:		6.5
 * Requires PHP:		7.4
 * Plugin URI:			https://eacdoojigger.earthasylum.com/eacobjectcache/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 * License:				GPLv3 or later
 * License URI:			https://www.gnu.org/licenses/gpl.html
 */

if (!defined('EAC_DOOJIGGER_VERSION'))
{
	\add_action( 'all_admin_notices', function()
		{
			echo '<div class="notice notice-error is-dismissible"><p>{eac}ObjectCache requires installation & activation of '.
				 '<a href="https://eacdoojigger.earthasylum.com/eacdoojigger" target="_blank">{eac}Doojigger</a>.</p></div>';
		}
	);
	return;
}

class eacObjectCache
{
	/**
	 * constructor method
	 *
	 * @return	void
	 */
	public function __construct()
	{
		/**
		 * {pluginname}_load_extensions - get the extensions directory to load
		 *
		 * @param	array	$extensionDirectories - array of [plugin_slug => plugin_directory]
		 * @return	array	updated $extensionDirectories
		 */
		add_filter( 'eacDoojigger_load_extensions', function($extensionDirectories)
			{
				/*
    			 * Enable update notice (self hosted or wp hosted)
    			 */
				eacDoojigger::loadPluginUpdater(__FILE__,'wp');

				/*
    			 * Add links on plugins page
    			 */
				add_filter( (is_network_admin() ? 'network_admin_' : '').'plugin_action_links_' . plugin_basename( __FILE__ ),
					function($pluginLinks, $pluginFile, $pluginData) {
						return array_merge(
							[
								'settings'		=> eacDoojigger::getSettingsLink($pluginData,'tools'),
								'documentation'	=> eacDoojigger::getDocumentationLink($pluginData),
								'support'		=> eacDoojigger::getSupportLink($pluginData),
							],
							$pluginLinks
						);
					},20,3
				);

				/*
    			 * Add our extension to load
    			 */
				$extensionDirectories[ plugin_basename( __FILE__ ) ] = [plugin_dir_path( __FILE__ ).'/Extensions'];
				return $extensionDirectories;
			}
		);
	}
}
new \EarthAsylumConsulting\eacObjectCache();
?>
