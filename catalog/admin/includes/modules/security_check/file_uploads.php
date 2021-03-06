<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\OSCOM;
  use OSC\OM\Registry;

  class securityCheck_file_uploads {
    var $type = 'warning';

    protected $lang;

    function __construct() {
      $this->lang = Registry::get('Language');

      $this->lang->loadDefinitions('modules/security_check/file_uploads');
    }

    function pass() {
      return (bool)ini_get('file_uploads');
    }

    function getMessage() {
      return WARNING_FILE_UPLOADS_DISABLED;
    }
  }
?>
