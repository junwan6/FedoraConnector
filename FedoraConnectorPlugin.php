<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4; */

/**
 * Plugin runner.
 *
 * @package     omeka
 * @subpackage  fedoraconnector
 * @author      Scholars' Lab <>
 * @author      David McClure <david.mcclure@virginia.edu>
 * @copyright   2012 The Board and Visitors of the University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html Apache 2 License
 */


class FedoraConnectorPlugin
{

    // Hooks.
    private static $_hooks = array(
        'install',
        'uninstall',
        'before_delete_item',
        'after_save_form_item',
        'admin_theme_header',
        'define_routes',
        'admin_append_to_items_show_primary',
        'public_append_to_items_show'
    );

    // Filters.
    private static $_filters = array(
        'admin_items_form_tabs',
        'admin_navigation_main',
        'exhibit_builder_exhibit_display_item',
        'exhibit_builder_display_exhibit_thumbnail_gallery'
    );

    /**
     * Add hooks and filers, get tables.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_db = get_db();
        $this->_objects = $this->_db->getTable('FedoraConnectorObject');
        self::addHooksAndFilters();
    }

    /**
     * Connect hooks and filters with callbacks.
     *
     * @return void
     */
    public function addHooksAndFilters()
    {

        foreach (self::$_hooks as $hookName) {
            $functionName = Inflector::variablize($hookName);
            add_plugin_hook($hookName, array($this, $functionName));
        }

        foreach (self::$_filters as $filterName) {
            $functionName = Inflector::variablize($filterName);
            add_filter($filterName, array($this, $functionName));
        }

    }

    /**
     * Install.
     *
     * @return void
     */
    public function install()
    {

        // Servers.
        $this->_db->query(
            "CREATE TABLE IF NOT EXISTS `{$this->_db->prefix}fedora_connector_servers` (
                `id` int(10) unsigned NOT NULL auto_increment,
                `url` tinytext collate utf8_unicode_ci,
                `name` tinytext collate utf8_unicode_ci,
                PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"
        );

        // Objects.
        $this->_db->query(
            "CREATE TABLE IF NOT EXISTS `{$this->_db->prefix}fedora_connector_objects` (
                `id` int(10) unsigned NOT NULL auto_increment,
                `item_id` int(10) unsigned,
                `server_id` int(10) unsigned,
                `pid` tinytext collate utf8_unicode_ci,
                `dsids` tinytext collate utf8_unicode_ci,
                PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"
        );

    }

    /**
     * Uninstall.
     *
     * @return void
     */
    public function uninstall()
    {

        // Drop the servers table.
        $sql = "DROP TABLE IF EXISTS `{$this->_db->prefix}fedora_connector_servers`";
        $this->_db->query($sql);

        // Drop the objects table.
        $sql = "DROP TABLE IF EXISTS `{$this->_db->prefix}fedora_connector_objects`";
        $this->_db->query($sql);
    }

    /**
     * Add plugin static assets.
     *
     * @param Zend_Controller_Request_Http $request The request.
     *
     * @return void
     */
    public function adminThemeHeader($request)
    {

        if (in_array($request->getModuleName(), array(
          'fedora-connector', 'default'))) {

            // Admin css.
            queue_css('fedora_connector_main');

            // Datastreams dependencies.
            queue_js('vendor/load/load');
            queue_js('load-datastreams');

        }

    }

    /**
     * Register routes.
     *
     * @param object $router Front controller router.
     *
     * @return void
     */
    public function defineRoutes($router)
    {
        $router->addConfig(new Zend_Config_Ini(
            FEDORA_CONNECTOR_PLUGIN_DIR . '/routes.ini', 'routes'
        ));
    }

    /**
     * Add Fedora tab to the Items interface.
     *
     * @param array $tabs Array of tab names => markup.
     *
     * @return array Updated $tabs array.
     */
    public function adminItemsFormTabs($tabs)
    {

        // Construct the form, strip the <form> tag.
        $form = new FedoraConnector_Form_Object();
        $form->removeDecorator('form');

        // Get the item.
        $item = get_current_item();

        // If the item is saved.
        if (!is_null($item->id)) {

            // Try to get a datastream.
            $object = $this->_objects->findByItem($item);

            // Populate fields.
            if ($object) {
                $form->populate(array(
                    'server' => $object->server_id,
                    'pid' => $object->pid,
                    'saved-dsids' => $object->dsids
                ));
            }
        }

        // Add tab.
        $tabs['Fedora'] = $form;
        return $tabs;

    }

    /**
     * Save/update datastream, do import.
     *
     * @param Item  $item The item.
     * @param array $post The complete $_POST.
     *
     * @return void.
     */
    public function afterSaveFormItem($item, $post)
    {

        // Create or update the datastream.
        $object = $this->_objects->createOrUpdate(
            $item, (int) $post['server'], $post['pid'], $post['dsids']
        );

        // Import.
        if ((bool) $post['import']) {
            $importer = new FedoraConnector_Import();
            $importer->import($object);
        }

    }

    /**
     * Add Fedora tab to admin menu bar.
     *
     * @param array $tabs Array of label => URI.
     *
     * @return array The modified tabs array.
     */
    public function adminNavigationMain($tabs)
    {
        $tabs['Fedora Connector'] = uri('fedora-connector');
        return $tabs;
    }

    /**
     * Render the datastream on admin show page.
     *
     * @return void.
     */
    public function adminAppendToItemsShowPrimary()
    {
        echo fedora_connector_display_object(get_current_item());
    }

    /**
     * Render the datastream on public show page.
     *
     * @return void.
     */
    public function publicAppendToItemsShow()
    {
        echo fedora_connector_display_object(get_current_item());
    }

    public function exhibitBuilderExhibitDisplayItem($html, $displayFileOptions, $linkProperties, $item)
    {
      $fedoraObject = fedora_connector_display_object($item, array('scale' => settings('fullsize_constraint')));
      $html = $fedoraObject ? exhibit_builder_link_to_exhibit_item($fedoraObject, $linkProperties, $item) : $html;
      return $html;
    }

    public function exhibitBuilderDisplayExhibitThumbnailGallery($html, $start, $end, $props, $thumbnailType) {

      $params = array();

      switch($thumbnailType) {
        case 'thumbnail':
          $params['scale'] = settings('thumbnail_constraint');
          break;
        case 'square_thumbnail':
          $params['region'] = '0.5,0.5,'.settings('square_thumbnail_constraint').','.settings('square_thumbnail_constraint');
          $params['level'] = 1;
          break;
      }

      $html = '';

      for ($i=(int)$start; $i <= (int)$end; $i++) {
        if (exhibit_builder_use_exhibit_page_item($i)) {
          $thumbnail = fedora_connector_display_object($item, $params) ? fedora_connector_display_object($item, $params) : item_image($thumbnailType, $props);
          $html .= "\n" . '<div class="exhibit-item">';
          $html .= exhibit_builder_link_to_exhibit_item($thumbnail);
          $html .= exhibit_builder_exhibit_display_caption($i);
          $html .= '</div>' . "\n";

        }
      }

      return $html;
    }

}
