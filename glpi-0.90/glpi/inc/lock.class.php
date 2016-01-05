<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015 Teclib'.

 http://glpi-project.org

 based on GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.
 
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/**
 * This class manages locks
 * Lock management is available for objects and link between objects. It relies on the use of
 * a is_dynamic field, to incidate if item supports lock, and is_deleted field to incidate if the
 * item or link is locked
 * By setting is_deleted to 0 again, the item is unlock
 *
 * Note : GLPI's core supports locks for objects. It's up to the external inventory tool to manage
 * locks for fields
 *
 * @since version 0.84
 **/
class Lock {

   static function getTypeName($nb=0) {
      return _n('Lock', 'Locks', $nb);
   }


   /**
    * Display form to unlock fields and links
    *
    * @param CommonDBTM $item the source item
   **/
   static function showForItem(CommonDBTM $item) {
      global $DB;

      $ID       = $item->getID();
      $itemtype = $item->getType();
      $header   = false;

      //If user doesn't have write right on the item, lock form must not be displayed
      if (!$item->canCreate()) {
         return false;
      }

      echo "<div width='50%'>";
      echo "<form method='post' id='lock_form'
             name='lock_form' action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";
      echo "<input type='hidden' name='id' value='$ID'>\n";
      echo "<input type='hidden' name='itemtype' value='$itemtype'>\n";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2''>".__('Locked items')."</th></tr>";

      //Use a hook to allow external inventory tools to manage per field lock
      $results =  Plugin::doHookFunction('display_locked_fields', array('item'   => $item,
                                                                        'header' => $header));
      $header |= $results['header'];

      //Special locks for computers only
      if ($itemtype == 'Computer') {
         //Locks for items recorded in glpi_computers_items table
         $types = array('Monitor', 'Peripheral', 'Printer');
         foreach ($types as $type) {
            $params = array('is_dynamic'    => 1,
                            'is_deleted'    => 1,
                            'computers_id'  => $ID,
                            'itemtype'      => $type);
            $params['FIELDS'] = array('id', 'items_id');
            $first  = true;
            foreach ($DB->request('glpi_computers_items', $params) as $line) {
               $tmp    = new $type();
               $tmp->getFromDB($line['items_id']);
               $header = true;
               if ($first) {
                  echo "<tr><th colspan='2'>".$type::getTypeName(Session::getPluralNumber())."</th></tr>\n";
                  $first = false;
               }

               echo "<tr class='tab_bg_1'><td class='center' width='10'>";
               echo "<input type='checkbox' name='Computer_Item[" . $line['id'] . "]'></td>";
               echo "<td class='left' width='95%'>" . $tmp->getName() . "</td>";
               echo "</tr>\n";
            }

         }

         $types = array('ComputerDisk', 'ComputerVirtualMachine');
         foreach ($types as $type) {
            $params = array('is_dynamic'    => 1,
                            'is_deleted'    => 1,
                            'computers_id'  => $ID);
            $params['FIELDS'] = array('id', 'name');
            $first  = true;
            foreach ($DB->request(getTableForItemType($type), $params) as $line) {
               $header = true;
               if ($first) {
                  echo "<tr><th colspan='2'>".$type::getTypeName(Session::getPluralNumber())."</th></tr>\n";
                  $first = false;
               }

               echo "<tr class='tab_bg_1'><td class='center' width='10'>";
               echo "<input type='checkbox' name='".$type."[" . $line['id'] . "]'></td>";
               echo "<td class='left' width='95%'>" . $line['name'] . "</td>";
               echo "</tr>\n";
            }
         }

         //Software versions
         $params = array('is_dynamic'    => 1,
                         'is_deleted'    => 1,
                         'computers_id'  => $ID);
         $first  = true;
         $query  = "SELECT `csv`.`id` AS `id`,
                           `sv`.`name` AS `version`,
                           `s`.`name` AS `software`
                    FROM `glpi_computers_softwareversions` AS csv
                    LEFT JOIN `glpi_softwareversions` AS sv
                       ON (`csv`.`softwareversions_id` = `sv`.`id`)
                    LEFT JOIN `glpi_softwares` AS s
                       ON (`sv`.`softwares_id` = `s`.`id`)
                    WHERE `csv`.`is_deleted` = '1'
                          AND `csv`.`is_dynamic` = '1'
                          AND `csv`.`computers_id` = '$ID'";
         foreach ($DB->request($query) as $line) {
            $header = true;
            if ($first) {
               echo "<tr><th colspan='2'>".Software::getTypeName(Session::getPluralNumber())."</th></tr>\n";
               $first = false;
            }

            echo "<tr class='tab_bg_1'><td class='center' width='10'>";
            echo "<input type='checkbox' name='Computer_SoftwareVersion[" . $line['id'] . "]'></td>";
            echo "<td class='left' width='95%'>" . $line['software']." ".$line['version'] . "</td>";
            echo "</tr>\n";

         }

         //Software licenses
         $params = array('is_dynamic'    => 1,
                         'is_deleted'    => 1,
                         'computers_id'  => $ID);
         $first  = true;
         $query  = "SELECT `csv`.`id` AS `id`,
                           `sv`.`name` AS `version`,
                           `s`.`name` AS `software`
                    FROM `glpi_computers_softwarelicenses` AS csv
                    LEFT JOIN `glpi_softwarelicenses` AS sv
                       ON (`csv`.`softwarelicenses_id` = `sv`.`id`)
                    LEFT JOIN `glpi_softwares` AS s
                       ON (`sv`.`softwares_id` = `s`.`id`)
                    WHERE `csv`.`is_deleted` = '1'
                          AND `csv`.`is_dynamic` = '1'
                          AND `csv`.`computers_id` = '$ID'";
         foreach ($DB->request($query) as $line) {
            $header = true;
            if ($first) {
               echo "<tr><th colspan='2'>".SoftwareLicense::getTypeName(Session::getPluralNumber())."</th>".
                     "</tr>\n";
               $first = false;
            }

            echo "<tr class='tab_bg_1'><td class='center' width='10'>";
            echo "<input type='checkbox' name='Computer_SoftwareLicense[" . $line['id'] . "]'></td>";
            echo "<td class='left' width='95%'>" . $line['software']." ".$line['version'] . "</td>";
            echo "</tr>\n";
         }
      }


      $first  = true;
      $item   = new NetworkPort();
      $params = array('is_dynamic' => 1,
                      'is_deleted' => 1,
                      'items_id'   => $ID,
                      'itemtype'   => $itemtype);
      $params['FIELDS'] = array('id');
      foreach ($DB->request('glpi_networkports', $params) as $line) {
         $item->getFromDB($line['id']);
         $header = true;
         if ($first) {
            echo "<tr><th colspan='2'>".NetworkPort::getTypeName(Session::getPluralNumber())."</th></tr>\n";
            $first = false;
         }

         echo "<tr class='tab_bg_1'><td class='center' width='10'>";
         echo "<input type='checkbox' name='NetworkPort[" . $line['id'] . "]'></td>";
         echo "<td class='left' width='95%'>" . $item->getName() . "</td>";
         echo "</tr>\n";

      }

      $first = true;
      $item  = new NetworkName();
      $params = array('`glpi_networknames`.`is_dynamic`' => 1,
                      '`glpi_networknames`.`is_deleted`' => 1,
                      '`glpi_networknames`.`itemtype`'   => 'NetworkPort',
                      '`glpi_networknames`.`items_id`'   => '`glpi_networkports`.`id`',
                      '`glpi_networkports`.`items_id`'   => $ID,
                      '`glpi_networkports`.`itemtype`'   => $itemtype);
      $params['FIELDS'] = array('glpi_networknames' => 'id');
      foreach ($DB->request(array('glpi_networknames', 'glpi_networkports'), $params) as $line) {
         $item->getFromDB($line['id']);
         $header = true;
         if ($first) {
            echo "<tr><th colspan='2'>".NetworkName::getTypeName(Session::getPluralNumber())."</th></tr>\n";
            $first = false;
         }

         echo "<tr class='tab_bg_1'><td class='center' width='10'>";
         echo "<input type='checkbox' name='NetworkName[" . $line['id'] . "]'></td>";
         echo "<td class='left' width='95%'>" . $item->getName() . "</td>";
         echo "</tr>\n";

      }

      $first  = true;
      $item   = new IPAddress();
      $params = array('`glpi_ipaddresses`.`is_dynamic`' => 1,
                      '`glpi_ipaddresses`.`is_deleted`' => 1,
                      '`glpi_ipaddresses`.`itemtype`'   => 'Networkname',
                      '`glpi_ipaddresses`.`items_id`'   => '`glpi_networknames`.`id`',
                      '`glpi_networknames`.`itemtype`'  => 'NetworkPort',
                      '`glpi_networknames`.`items_id`'  => '`glpi_networkports`.`id`',
                      '`glpi_networkports`.`items_id`'  => $ID,
                      '`glpi_networkports`.`itemtype`'  => $itemtype);
      $params['FIELDS'] = array('glpi_ipaddresses' => 'id');
      foreach ($DB->request(array('glpi_ipaddresses',
                                  'glpi_networknames',
                                  'glpi_networkports'), $params) as $line) {
         $item->getFromDB($line['id']);
         $header = true;
         if ($first) {
            echo "<tr><th colspan='2'>".IPAddress::getTypeName(Session::getPluralNumber())."</th></tr>\n";
            $first = false;
         }

         echo "<tr class='tab_bg_1'><td class='center' width='10'>";
         echo "<input type='checkbox' name='IPAddress[" . $line['id'] . "]'></td>";
         echo "<td class='left' width='95%'>" . $item->getName() . "</td>";
         echo "</tr>\n";

      }

      $types = Item_Devices::getDeviceTypes();
      $nb    = 0;
      foreach ($types as $old => $type) {
         $nb += countElementsInTable(getTableForItemType($type),
                                     "`items_id`='$ID'
                                         AND `itemtype`='$itemtype'
                                         AND `is_dynamic`='1'
                                         AND `is_deleted`='1'");
      }
      if ($nb) {
         $header = true;
         echo "<tr><th colspan='2'>"._n('Component', 'Components', Session::getPluralNumber())."</th></tr>\n";
         foreach ($types as $old => $type) {
            $associated_type  = str_replace('Item_', '', $type);
            $associated_table = getTableForItemType($associated_type);
            $fk               = getForeignKeyFieldForTable($associated_table);

            $query = "SELECT `i`.`id`,
                             `t`.`designation` AS `name`
                      FROM `".getTableForItemType($type)."` AS i
                      LEFT JOIN `$associated_table` AS t
                         ON (`t`.`id` = `i`.`$fk`)
                      WHERE `itemtype` = '$itemtype'
                            AND `items_id` = '$ID'
                            AND `is_dynamic` = '1'
                            AND `is_deleted` = '1'";
            foreach ($DB->request($query) as $data) {
               echo "<tr class='tab_bg_1'><td class='center' width='10'>";
               echo "<input type='checkbox' name='".$type."[" . $data['id'] . "]'></td>";
               echo "<td class='left' width='95%'>";
               printf(__('%1$s: %2$s'), $associated_type::getTypeName(), $data['name']);
               echo "</td></tr>\n";
            }
         }
      }
      if ($header) {
         echo "<tr><th>";
         Html::checkAllAsCheckbox('lock_form');
         echo "</th><th>&nbsp</th></tr>\n";
         echo "</table>";
         Html::openArrowMassives('lock_form', true);
         Html::closeArrowMassives(array('unlock' => _sx('button', 'Unlock')));
      } else {
         echo "<tr class='tab_bg_2'>";
         echo "<td class='center' colspan='2'>". __('No locked item')."</td></tr>";
         echo "</table>";
      }

      Html::closeForm();
      echo "</div>\n";
   }


   /**
    * @see CommonGLPI::getTabNameForItem()
    *
    * @param $item               CommonGLPI object
    * @param $withtemplate       (default 0)
   **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->isDynamic() && $item->canCreate()) {
         return Lock::getTypeName(Session::getPluralNumber());
      }
      return '';
   }


   /**
    * @param $item            CommonGLPI object
    * @param $tabnum          (default 1)
    * @param $withtemplate    (default 0)
   **/
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->isDynamic()) {
         self::showForItem($item);
      }
      return true;
   }


   /**
    * Get infos to build an SQL query to get locks fields in a table
    *
    * @param $itemtype       itemtype of the item to look for locked fields
    * @param $baseitemtype   itemtype of the based item
    *
    * @return an array which contains necessary informations to build the SQL query
   **/
   static function getLocksQueryInfosByItemType($itemtype, $baseitemtype) {

      $condition = array();
      $table     = false;
      $field     = '';
      $type      = $itemtype;

      switch ($itemtype) {
         case 'Peripheral' :
         case 'Monitor' :
         case 'Printer' :
         case 'Phone' :
            $condition = array('itemtype'   => $itemtype,
                               'is_dynamic' => 1,
                               'is_deleted' => 1);
            $table     = 'glpi_computers_items';
            $field     = 'computers_id';
            $type      = 'Computer_Item';
            break;

         case 'NetworkPort' :
            $condition = array('itemtype'   => $baseitemtype,
                               'is_dynamic' => 1,
                               'is_deleted' => 1);
            $table     = 'glpi_networkports';
            $field     = 'items_id';
            break;

         case 'NetworkName' :
            $condition = array('`glpi_networknames`.`is_dynamic`' => 1,
                               '`glpi_networknames`.`is_deleted`' => 1,
                               '`glpi_networknames`.`itemtype`'   => 'NetworkPort',
                               '`glpi_networknames`.`items_id`'   => '`glpi_networkports`.`id`',
                               '`glpi_networkports`.`itemtype`'   => $baseitemtype);
            $condition['FIELDS']
                       = array('glpi_networknames' => 'id');
            $table     = array('glpi_networknames', 'glpi_networkports');
            $field     = '`glpi_networkports`.`items_id`';
            break;

         case 'IPAddress' :
            $condition = array('`glpi_ipaddresses`.`is_dynamic`' => 1,
                               '`glpi_ipaddresses`.`is_deleted`' => 1,
                               '`glpi_ipaddresses`.`itemtype`'   => 'NetworkName',
                               '`glpi_ipaddresses`.`items_id`'   => '`glpi_networknames`.`id`',
                               '`glpi_networknames`.`itemtype`'   => 'NetworkPort',
                               '`glpi_networknames`.`items_id`'   => '`glpi_networkports`.`id`',
                               '`glpi_networkports`.`itemtype`'   => $baseitemtype);
            $condition['FIELDS']
                       = array('glpi_ipaddresses' => 'id');
            $table     = array('glpi_ipaddresses', 'glpi_networknames', 'glpi_networkports');
            $field     = '`glpi_networkports`.`items_id`';
            break;

         case 'ComputerDisk' :
            $condition = array('is_dynamic' => 1,
                               'is_deleted' => 1);
            $table     = 'glpi_computerdisks';
            $field     = 'computers_id';
            break;

         case 'ComputerVirtualMachine' :
            $condition = array('is_dynamic' => 1,
                               'is_deleted' => 1);
            $table     = 'glpi_computervirtualmachines';
            $field     = 'computers_id';
            break;

         case 'SoftwareVersion' :
            $condition = array('is_dynamic' => 1,
                               'is_deleted' => 1);
            $table     = 'glpi_computers_softwareversions';
            $field     = 'computers_id';
            $type      = 'Computer_SoftwareVersion';
            break;

         default :
            // Devices
            if (preg_match('/^Item\_Device/',$itemtype)) {
               $condition = array('itemtype'   => $baseitemtype,
                                  'is_dynamic' => 1,
                                  'is_deleted' => 1);
               $table     = getTableForItemType($itemtype);
               $field     = 'items_id';
            }

      }

      return array('condition' => $condition,
                   'table'     => $table,
                   'field'     => $field,
                   'type'      => $type);
   }


   /**
    * @since version 0.85
    *
    * @see CommonDBTM::getMassiveActionsForItemtype()
   **/
   static function getMassiveActionsForItemtype(array &$actions, $itemtype, $is_deleted=0,
                                                CommonDBTM $checkitem=NULL) {

      $action_name = __CLASS__.MassiveAction::CLASS_ACTION_SEPARATOR.'unlock';

      if (Session::haveRight('computer', UPDATE)
          && ($itemtype == 'Computer')) {

         $actions[$action_name] = __('Unlock components');
      }
   }


   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
   **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'unlock' :
            $types = array('Monitor'                => _n('Monitor', 'Monitors', Session::getPluralNumber()),
                           'Peripheral'             => _n('Device', 'Devices', Session::getPluralNumber()),
                           'Printer'                => _n('Printer', 'Printers', Session::getPluralNumber()),
                           'SoftwareVersion'        => _n('Version', 'Versions', Session::getPluralNumber()),
                           'NetworkPort'            => _n('Network port', 'Network ports', Session::getPluralNumber()),
                           'NetworkName'            => _n('Network name', 'Network names', Session::getPluralNumber()),
                           'IPAddress'              => _n('IP address', 'IP addresses', Session::getPluralNumber()),
                           'ComputerDisk'           => _n('Volume', 'Volumes', Session::getPluralNumber()),
                           'Device'                 => _n('Component', 'Components', Session::getPluralNumber()),
                           'ComputerVirtualMachine' => _n('Virtual machine', 'Virtual machines', Session::getPluralNumber()));

            _e('Select the type of the item that must be unlock');
            echo "<br><br>\n";

            Dropdown::showFromArray('attached_item', $types,
                                    array('multiple' => true,
                                          'size'     => 5,
                                          'values'   => array_keys($types)));

            echo "<br><br>".Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
      }
      return false;
   }


   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $baseitem,
                                                       array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case 'unlock' :
            $input = $ma->getInput();
            if (isset($input['attached_item'])) {
               $attached_items = $input['attached_item'];
               if (($device_key = array_search('Device', $attached_items)) !== false) {
                  unset($attached_items[$device_key]);
                  $attached_items = array_merge($attached_items, Item_Devices::getDeviceTypes());
               }
               $links = array();
               foreach ($attached_items as $attached_item) {
                  $infos = self::getLocksQueryInfosByItemType($attached_item, $baseitem->getType());
                  if ($item = getItemForItemtype($infos['type'])) {
                     $infos['item'] = $item;
                     $links[$attached_item] = $infos;
                  }
               }
               foreach ($ids as $id) {
                  $action_valid = false;
                  foreach ($links as $infos) {
                     $infos['condition'][$infos['field']] = $id;
                     foreach ($DB->request($infos['table'], $infos['condition']) as $data) {
                        // Restore without history
                        $action_valid = $infos['item']->restore(array('id' => $data['id']));
                     }
                  }
                  if ($action_valid) {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                     $ma->addMessage($infos['item']->getErrorMessage(ERROR_ON_ACTION));
                  }
               }
            }
            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $baseitem, $ids);
   }

}
?>
