<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\FileSystem;
  use OSC\OM\HTML;
  use OSC\OM\OSCOM;

  require('includes/application_top.php');

  $backup_directory = OSCOM::getConfig('dir_root') . 'includes/backups/';

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  if (tep_not_null($action)) {
    switch ($action) {
      case 'forget':
        $OSCOM_Db->delete('configuration', ['configuration_key' => 'DB_LAST_RESTORE']);

        $OSCOM_MessageStack->add(SUCCESS_LAST_RESTORE_CLEARED, 'success');

        OSCOM::redirect(FILENAME_BACKUP);
        break;
      case 'backupnow':
        tep_set_time_limit(0);
        $backup_file = 'db_' . OSCOM::getConfig('db_database') . '-' . date('YmdHis') . '.sql';
        $fp = fopen($backup_directory . $backup_file, 'w');

        $schema = '# osCommerce, https://www.oscommerce.com' . "\n" .
                  '#' . "\n" .
                  '# Database Backup For ' . STORE_NAME . "\n" .
                  '# Copyright (c) ' . date('Y') . ' ' . STORE_OWNER . "\n" .
                  '#' . "\n" .
                  '# Database: ' . OSCOM::getConfig('db_database') . "\n" .
                  '# Database Server: ' . OSCOM::getConfig('db_server') . "\n" .
                  '#' . "\n" .
                  '# Backup Date: ' . date(PHP_DATE_TIME_FORMAT) . "\n\n";
        fputs($fp, $schema);

        $Qtables = $OSCOM_Db->get([
          'INFORMATION_SCHEMA.TABLES t',
          'INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY ccsa'
        ], [
          't.TABLE_NAME',
          't.ENGINE',
          't.TABLE_COLLATION',
          'ccsa.CHARACTER_SET_NAME'
        ],
        [
          't.TABLE_SCHEMA' => OSCOM::getConfig('db_database'),
          't.TABLE_COLLATION' => [
            'rel' => 'ccsa.COLLATION_NAME'
          ]
        ], null, null, null, ['prefix_tables' => false]);

        while ($Qtables->fetch()) {
          $table = $Qtables->value('TABLE_NAME');

          $schema = 'drop table if exists ' . $table . ';' . "\n" .
                    'create table ' . $table . ' (' . "\n";

          $table_list = array();

          $Qfields = $OSCOM_Db->query('show fields from ' . $table);

          while ($Qfields->fetch()) {
            $table_list[] = $Qfields->value('Field');

            $schema .= '  ' . $Qfields->value('Field') . ' ' . $Qfields->value('Type');

            if (strlen($Qfields->value('Default')) > 0) $schema .= ' default \'' . $Qfields->value('Default') . '\'';

            if ($Qfields->value('Null') != 'YES') $schema .= ' not null';

            if (strlen($Qfields->value('Extra')) > 0) $schema .= ' ' . $Qfields->value('Extra');

            $schema .= ',' . "\n";
          }

          $schema = preg_replace("/,\n$/", '', $schema);

// add the keys
          $index = array();

          $Qkeys = $OSCOM_Db->query('show keys from ' . $table);

          while ($Qkeys->fetch()) {
            $kname = $Qkeys->value('Key_name');

            if (!isset($index[$kname])) {
              $index[$kname] = array('unique' => $Qkeys->valueInt('Non_unique') === 0,
                                     'fulltext' => ($Qkeys->value('Index_type') == 'FULLTEXT' ? '1' : '0'),
                                     'columns' => array());
            }

            $index[$kname]['columns'][] = $Qkeys->value('Column_name');
          }

          foreach ( $index as $kname => $info ) {
            $schema .= ',' . "\n";

            $columns = implode($info['columns'], ', ');

            if ($kname == 'PRIMARY') {
              $schema .= '  PRIMARY KEY (' . $columns . ')';
            } elseif ( $info['fulltext'] == '1' ) {
              $schema .= '  FULLTEXT ' . $kname . ' (' . $columns . ')';
            } elseif ($info['unique']) {
              $schema .= '  UNIQUE ' . $kname . ' (' . $columns . ')';
            } else {
              $schema .= '  KEY ' . $kname . ' (' . $columns . ')';
            }
          }

          $schema .= "\n" . ') ENGINE=' . $Qtables->value('ENGINE') . ' CHARACTER SET ' . $Qtables->value('CHARACTER_SET_NAME') . ' COLLATE ' . $Qtables->value('TABLE_COLLATION') . ';' . "\n\n";
          fputs($fp, $schema);

// dump the data
          if ( ($table != OSCOM::getConfig('db_table_prefix') . 'sessions' ) && ($table != OSCOM::getConfig('db_table_prefix') . 'whos_online') ) {
            $Qrows = $OSCOM_Db->get($table, $table_list);

            while ($Qrows->fetch()) {
              $schema = 'insert into ' . $table . ' (' . implode(', ', $table_list) . ') values (';

              foreach ( $table_list as $i ) {
                if (!$Qrows->hasValue($i)) {
                  $schema .= 'NULL, ';
                } elseif (tep_not_null($Qrows->value($i))) {
                  $row = addslashes($Qrows->value($i));
                  $row = preg_replace("/\n#/", "\n".'\#', $row);

                  $schema .= '\'' . $row . '\', ';
                } else {
                  $schema .= '\'\', ';
                }
              }

              $schema = preg_replace('/, $/', '', $schema) . ');' . "\n";
              fputs($fp, $schema);
            }
          }
        }

        fclose($fp);

        if (isset($_POST['download']) && ($_POST['download'] == 'yes')) {
          switch ($_POST['compress']) {
            case 'gzip':
              exec(LOCAL_EXE_GZIP . ' ' . $backup_directory . $backup_file);
              $backup_file .= '.gz';
              break;
            case 'zip':
              exec(LOCAL_EXE_ZIP . ' -j ' . $backup_directory . $backup_file . '.zip ' . $backup_directory . $backup_file);
              unlink($backup_directory . $backup_file);
              $backup_file .= '.zip';
          }
          header('Content-type: application/x-octet-stream');
          header('Content-disposition: attachment; filename=' . $backup_file);

          readfile($backup_directory . $backup_file);
          unlink($backup_directory . $backup_file);

          exit;
        } else {
          switch ($_POST['compress']) {
            case 'gzip':
              exec(LOCAL_EXE_GZIP . ' ' . $backup_directory . $backup_file);
              break;
            case 'zip':
              exec(LOCAL_EXE_ZIP . ' -j ' . $backup_directory . $backup_file . '.zip ' . $backup_directory . $backup_file);
              unlink($backup_directory . $backup_file);
          }

          $OSCOM_MessageStack->add(SUCCESS_DATABASE_SAVED, 'success');
        }

        OSCOM::redirect(FILENAME_BACKUP);
        break;
      case 'restorenow':
      case 'restorelocalnow':
        tep_set_time_limit(0);

        if ($action == 'restorenow') {
          $read_from = $_GET['file'];

          if (is_file($backup_directory . $_GET['file'])) {
            $restore_file = $backup_directory . $_GET['file'];
            $extension = substr($_GET['file'], -3);

            if ( ($extension == 'sql') || ($extension == '.gz') || ($extension == 'zip') ) {
              switch ($extension) {
                case 'sql':
                  $restore_from = $restore_file;
                  $remove_raw = false;
                  break;
                case '.gz':
                  $restore_from = substr($restore_file, 0, -3);
                  exec(LOCAL_EXE_GUNZIP . ' ' . $restore_file . ' -c > ' . $restore_from);
                  $remove_raw = true;
                  break;
                case 'zip':
                  $restore_from = substr($restore_file, 0, -4);
                  exec(LOCAL_EXE_UNZIP . ' ' . $restore_file . ' -d ' . $backup_directory);
                  $remove_raw = true;
              }

              if (isset($restore_from) && is_file($restore_from) && (filesize($restore_from) > 15000)) {
                $fd = fopen($restore_from, 'rb');
                $restore_query = fread($fd, filesize($restore_from));
                fclose($fd);
              }
            }
          }
        } elseif ($action == 'restorelocalnow') {
          $sql_file = new upload('sql_file');

          if ($sql_file->parse() == true) {
            $restore_query = fread(fopen($sql_file->tmp_filename, 'r'), filesize($sql_file->tmp_filename));
            $read_from = $sql_file->filename;
          }
        }

        if (isset($restore_query)) {
          $sql_array = array();
          $drop_table_names = array();
          $sql_length = strlen($restore_query);
          $pos = strpos($restore_query, ';');
          for ($i=$pos; $i<$sql_length; $i++) {
            if ($restore_query[0] == '#') {
              $restore_query = ltrim(substr($restore_query, strpos($restore_query, "\n")));
              $sql_length = strlen($restore_query);
              $i = strpos($restore_query, ';')-1;
              continue;
            }
            if ($restore_query[($i+1)] == "\n") {
              for ($j=($i+2); $j<$sql_length; $j++) {
                if (trim($restore_query[$j]) != '') {
                  $next = substr($restore_query, $j, 6);
                  if ($next[0] == '#') {
// find out where the break position is so we can remove this line (#comment line)
                    for ($k=$j; $k<$sql_length; $k++) {
                      if ($restore_query[$k] == "\n") break;
                    }
                    $query = substr($restore_query, 0, $i+1);
                    $restore_query = substr($restore_query, $k);
// join the query before the comment appeared, with the rest of the dump
                    $restore_query = $query . $restore_query;
                    $sql_length = strlen($restore_query);
                    $i = strpos($restore_query, ';')-1;
                    continue 2;
                  }
                  break;
                }
              }
              if ($next == '') { // get the last insert query
                $next = 'insert';
              }
              if ( (preg_match('/create/i', $next)) || (preg_match('/insert/i', $next)) || (preg_match('/drop t/i', $next)) ) {
                $query = substr($restore_query, 0, $i);

                $next = '';
                $sql_array[] = $query;
                $restore_query = ltrim(substr($restore_query, $i+1));
                $sql_length = strlen($restore_query);
                $i = strpos($restore_query, ';')-1;

                if (preg_match('/^create*/i', $query)) {
                  $table_name = trim(substr($query, stripos($query, 'table ')+6));
                  $table_name = substr($table_name, 0, strpos($table_name, ' '));

                  $drop_table_names[] = $table_name;
                }
              }
            }
          }

          $OSCOM_Db->exec('drop table if exists ' . implode(', ', $drop_table_names));

          for ($i=0, $n=sizeof($sql_array); $i<$n; $i++) {
            $OSCOM_Db->exec($sql_array[$i]);
          }

          session_write_close();

          $OSCOM_Db->delete('whos_online');
          $OSCOM_Db->delete('sessions');

          $OSCOM_Db->delete('configuration', ['configuration_key' => 'DB_LAST_RESTORE']);
          $OSCOM_Db->save('configuration', [
            'configuration_title' => 'Last Database Restore',
            'configuration_key' => 'DB_LAST_RESTORE',
            'configuration_value' => $read_from,
            'configuration_description' => 'Last database restore file',
            'configuration_group_id' => '6',
            'date_added' => 'now()'
          ]);

          if (isset($remove_raw) && ($remove_raw == true)) {
            unlink($restore_from);
          }

          $OSCOM_MessageStack->add(SUCCESS_DATABASE_RESTORED, 'success');
        }

        OSCOM::redirect(FILENAME_BACKUP);
        break;
      case 'download':
        $extension = substr($_GET['file'], -3);

        if ( ($extension == 'zip') || ($extension == '.gz') || ($extension == 'sql') ) {
          if ($fp = fopen($backup_directory . $_GET['file'], 'rb')) {
            $buffer = fread($fp, filesize($backup_directory . $_GET['file']));
            fclose($fp);

            header('Content-type: application/x-octet-stream');
            header('Content-disposition: attachment; filename=' . $_GET['file']);

            echo $buffer;

            exit;
          }
        } else {
          $OSCOM_MessageStack->add(ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE, 'error');
        }
        break;
      case 'deleteconfirm':
        if (strstr($_GET['file'], '..')) OSCOM::redirect(FILENAME_BACKUP);

        if (unlink($backup_directory . '/' . $_GET['file'])) {
          $OSCOM_MessageStack->add(SUCCESS_BACKUP_DELETED, 'success');

          OSCOM::redirect(FILENAME_BACKUP);
        }
        break;
    }
  }

// check if the backup directory exists
  $dir_ok = false;
  if (is_dir($backup_directory)) {
    if (FileSystem::isWritable($backup_directory)) {
      $dir_ok = true;
    } else {
      $OSCOM_MessageStack->add(ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE, 'error');
    }
  } else {
    $OSCOM_MessageStack->add(ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST, 'error');
  }

  $show_listing = true;

  require($oscTemplate->getFile('template_top.php'));

  if (empty($action)) {
?>

<div class="pull-right">
  <?= HTML::button(IMAGE_BACKUP, 'fa fa-clone', OSCOM::link('backup.php', 'action=backup'), null, 'btn-info') . HTML::button(IMAGE_RESTORE, 'fa fa-repeat', OSCOM::link('backup.php', 'action=restorelocal'), null, 'btn-info'); ?>
</div>

<?php
  }
?>

<h2><i class="fa fa-archive"></i> <a href="<?= OSCOM::link('backup.php'); ?>"><?= HEADING_TITLE; ?></a></h2>

<?php
  if (!empty($action)) {
    $heading = $contents = [];

    if (isset($_GET['file'])) {
      $file = basename($_GET['file']);

      if (is_file($backup_directory . $file)) {
        $info = [
          'file' => $file,
          'date' => date(PHP_DATE_TIME_FORMAT, filemtime($backup_directory . $file)),
          'size' => number_format(filesize($backup_directory . $file)) . ' bytes'
        ];

        switch (substr($file, -3)) {
          case 'zip': $info['compression'] = 'ZIP'; break;
          case '.gz': $info['compression'] = 'GZIP'; break;
          default: $info['compression'] = TEXT_NO_EXTENSION; break;
        }

        $buInfo = new objectInfo($info);

        switch ($action) {
          case 'restore':
            $heading[] = array('text' => $buInfo->date);

            $contents[] = array('text' => tep_break_string(sprintf(TEXT_INFO_RESTORE, $backup_directory . (($buInfo->compression != TEXT_NO_EXTENSION) ? substr($buInfo->file, 0, strrpos($buInfo->file, '.')) : $buInfo->file), ($buInfo->compression != TEXT_NO_EXTENSION) ? TEXT_INFO_UNPACK : ''), 35, ' '));
            $contents[] = array('text' => HTML::button(IMAGE_RESTORE, 'fa fa-repeat', OSCOM::link(FILENAME_BACKUP, 'file=' . $buInfo->file . '&action=restorenow'), null, 'btn-success') . HTML::button(IMAGE_CANCEL, null, OSCOM::link(FILENAME_BACKUP), null, 'btn-link'));
            break;

          case 'delete':
            $heading[] = array('text' => $buInfo->date);

            $contents = array('form' => HTML::form('delete', OSCOM::link(FILENAME_BACKUP, 'file=' . $buInfo->file . '&action=deleteconfirm')));
            $contents[] = array('text' => TEXT_DELETE_INTRO);
            $contents[] = array('text' => '<strong>' . $buInfo->file . '</strong>');
            $contents[] = array('text' => HTML::button(IMAGE_DELETE, 'fa fa-trash', null, null, 'btn-danger') . HTML::button(IMAGE_CANCEL, null, OSCOM::link(FILENAME_BACKUP), null, 'btn-link'));
            break;
        }
      }
    } else {
      switch ($action) {
        case 'backup':
          $heading[] = array('text' => TEXT_INFO_HEADING_NEW_BACKUP);

          $contents = array('form' => HTML::form('backup', OSCOM::link(FILENAME_BACKUP, 'action=backupnow')));
          $contents[] = array('text' => TEXT_INFO_NEW_BACKUP);

          $contents[] = array('text' => HTML::radioField('compress', 'no', true) . ' ' . TEXT_INFO_USE_NO_COMPRESSION);
          if (is_file(LOCAL_EXE_GZIP)) $contents[] = array('text' => HTML::radioField('compress', 'gzip') . ' ' . TEXT_INFO_USE_GZIP);
          if (is_file(LOCAL_EXE_ZIP)) $contents[] = array('text' => HTML::radioField('compress', 'zip') . ' ' . TEXT_INFO_USE_ZIP);

          if ($dir_ok == true) {
            $contents[] = array('text' => HTML::checkboxField('download', 'yes') . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '*<br /><br />*' . TEXT_INFO_BEST_THROUGH_HTTPS);
          } else {
            $contents[] = array('text' => HTML::radioField('download', 'yes', true) . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '*<br /><br />*' . TEXT_INFO_BEST_THROUGH_HTTPS);
          }

          $contents[] = array('text' => HTML::button(IMAGE_BACKUP, 'fa fa-copy', null, null, 'btn-success') . HTML::button(IMAGE_CANCEL, null, OSCOM::link(FILENAME_BACKUP), null, 'btn-link'));
          break;

        case 'restorelocal':
          $heading[] = array('text' => TEXT_INFO_HEADING_RESTORE_LOCAL);

          $contents = array('form' => HTML::form('restore', OSCOM::link(FILENAME_BACKUP, 'action=restorelocalnow'), 'post', 'enctype="multipart/form-data"'));
          $contents[] = array('text' => TEXT_INFO_RESTORE_LOCAL . '<br /><br />' . TEXT_INFO_BEST_THROUGH_HTTPS);
          $contents[] = array('text' => HTML::fileField('sql_file'));
          $contents[] = array('text' => TEXT_INFO_RESTORE_LOCAL_RAW_FILE);
          $contents[] = array('text' => HTML::button(IMAGE_RESTORE, 'fa fa-repeat', null, null, 'btn-success') . HTML::button(IMAGE_CANCEL, null, OSCOM::link(FILENAME_BACKUP), null, 'btn-link'));
          break;
      }
    }

    if (tep_not_null($heading) && tep_not_null($contents)) {
      $show_listing = false;

      echo HTML::panel($heading, $contents, ['type' => 'info']);
    }
  }

  if ($show_listing === true) {
?>

<table class="oscom-table table table-hover">
  <thead>
    <tr class="info">
      <th><?= TABLE_HEADING_TITLE; ?></th>
      <th class="text-right"><?= TABLE_HEADING_FILE_DATE; ?></th>
      <th class="text-right"><?= TABLE_HEADING_FILE_SIZE; ?></th>
      <th class="action"></th>
    </tr>
  </thead>
  <tbody>

<?php
    if ($dir_ok == true) {
      $dir = dir($backup_directory);
      $contents = array();
      while ($file = $dir->read()) {
        if (!is_dir($backup_directory . $file) && in_array(substr($file, -3), array('zip', 'sql', '.gz'))) {
          $contents[] = $file;
        }
      }
      sort($contents);

      for ($i=0, $n=sizeof($contents); $i<$n; $i++) {
        $entry = $contents[$i];
?>

    <tr>
      <td><?= $entry; ?></td>
      <td class="text-right"><?= date(PHP_DATE_TIME_FORMAT, filemtime($backup_directory . $entry)); ?></td>
      <td class="text-right"><?= number_format(filesize($backup_directory . $entry)); ?> bytes</td>
      <td class="action"><a href="<?= OSCOM::link('backup.php', 'file=' . $entry . '&action=download'); ?>"><i class="fa fa-download text-success" title="<?= ICON_FILE_DOWNLOAD; ?>"></i></a><a href="<?= OSCOM::link('backup.php', 'file=' . $entry . '&action=restore'); ?>"><i class="fa fa-repeat" title="<?= IMAGE_RESTORE; ?>"></i></a><a href="<?= OSCOM::link('backup.php', 'file=' . $entry . '&action=delete'); ?>"><i class="fa fa-trash" title="<?= IMAGE_DELETE; ?>"></i></a></td>
    </tr>

<?php
      }
      $dir->close();
    }
?>

  </tbody>
</table>

<p>
  <?= '<strong>' . TEXT_BACKUP_DIRECTORY . '</strong> ' . $backup_directory; ?>
</p>

<?php
    if (defined('DB_LAST_RESTORE')) {
?>

<p>
  <?= '<strong>' . TEXT_LAST_RESTORATION . '</strong> ' . DB_LAST_RESTORE . ' <a href="' . OSCOM::link(FILENAME_BACKUP, 'action=forget') . '">' . TEXT_FORGET . '</a>'; ?>
</p>

<?php
    }
  }

  require($oscTemplate->getFile('template_bottom.php'));
  require('includes/application_bottom.php');
?>
