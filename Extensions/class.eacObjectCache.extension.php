<?php
namespace EarthAsylumConsulting\Extensions;

if (! class_exists(__NAMESPACE__.'\object_cache_extension', false) )
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

	class object_cache_extension extends \EarthAsylumConsulting\abstract_extension
	{
		/**
		 * @var string extension version
		 */
		const VERSION	= '25.0330.1';

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
			parent::__construct($plugin, self::ALLOW_ALL);
			return $this->isEnabled() && $this->isEnabled(defined('EAC_OBJECT_CACHE_VERSION'));
		}


		/**
		 * initialize method - called from main plugin
		 *
		 * @return 	void
		 */
		public function initialize()
		{
			if ( ! parent::initialize() ) return; // disabled

			if (defined('EAC_OBJECT_CACHE_VERSION'))
			{
				global $wp_object_cache;
				$wp_object_cache->display_stats 	= (int)$this->get_option('object_cache_stats',$wp_object_cache->display_stats);
				$wp_object_cache->delayed_writes 	= (int)$this->get_option('object_cache_delayed_writes',$wp_object_cache->delayed_writes);
				$wp_object_cache->display_errors 	= $this->is_admin();
				$wp_object_cache->log_errors 		= true;
			} else {
				return $this->isEnabled(false);
			}
		}


		/**
		 * Add filters and actions - called from main plugin
		 *
		 * @return	void
		 */
		public function addActionsAndFilters()
		{
			parent::addActionsAndFilters();

			if (defined('EAC_OBJECT_CACHE_VERSION'))
			{
				if (get_current_blog_id() == get_main_site_id()) {
					global $wp_object_cache;
				//	$this->add_action( 'daily_event', array($wp_object_cache, 'optimize') );
					$this->do_action( 'add_event_task', 'daily', array($wp_object_cache, 'optimize'));
				}
			}
		}
	}
}
/**
 * return a new instance of this class
 */
if (isset($this)) return new object_cache_extension($this);
?>
