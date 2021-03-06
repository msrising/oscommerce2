<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2016 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\Registry;

  class nb_home {
    var $code = 'nb_home';
    var $group = 'navbar_modules_home';
    var $title;
    var $description;
    var $sort_order;
    var $enabled = false;

    function __construct() {
      $this->title = MODULE_NAVBAR_HOME_TITLE;
      $this->description = MODULE_NAVBAR_HOME_DESCRIPTION;

      if ( defined('MODULE_NAVBAR_HOME_STATUS') ) {
        $this->sort_order = MODULE_NAVBAR_HOME_SORT_ORDER;
        $this->enabled = (MODULE_NAVBAR_HOME_STATUS == 'True');

        switch (MODULE_NAVBAR_HOME_CONTENT_PLACEMENT) {
          case 'Home':
          $this->group = 'navbar_modules_home';
          break;
          case 'Left':
          $this->group = 'navbar_modules_left';
          break;
          case 'Right':
          $this->group = 'navbar_modules_right';
          break;
        }
      }
    }

    function getOutput() {
      global $oscTemplate;

      ob_start();
      require('includes/modules/navbar_modules/templates/home.php');
      $data = ob_get_clean();

      $oscTemplate->addBlock($data, $this->group);
    }

    function isEnabled() {
      return $this->enabled;
    }

    function check() {
      return defined('MODULE_NAVBAR_HOME_STATUS');
    }

    function install() {
      $OSCOM_Db = Registry::get('Db');

      $OSCOM_Db->save('configuration', [
        'configuration_title' => 'Enable Home Module',
        'configuration_key' => 'MODULE_NAVBAR_HOME_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to add the module to your Navbar?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), ',
        'date_added' => 'now()'
      ]);

      $OSCOM_Db->save('configuration', [
        'configuration_title' => 'Content Placement',
        'configuration_key' => 'MODULE_NAVBAR_HOME_CONTENT_PLACEMENT',
        'configuration_value' => 'Left',
        'configuration_description' => 'Should the module be loaded in the Left or Right or the Home area of the Navbar?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'tep_cfg_select_option(array(\'Left\', \'Right\', \'Home\'), ',
        'date_added' => 'now()'
      ]);

      $OSCOM_Db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_NAVBAR_HOME_SORT_ORDER',
        'configuration_value' => '520',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]);
    }

    function remove() {
      return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
    }

    function keys() {
      return array('MODULE_NAVBAR_HOME_STATUS', 'MODULE_NAVBAR_HOME_CONTENT_PLACEMENT', 'MODULE_NAVBAR_HOME_SORT_ORDER');
    }
  }
