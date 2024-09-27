<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * ILP Integration
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://opensource.org/licenses/gpl-3.0.html.
 *
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @package block_intelligent_learning
 * @author Sam Chaffee
 */

/**
 * Admin settings
 *
 * @author Sam Chaffee
 * @version $Id$
 * @package block_intelligent_learning
 **/
if ($ADMIN->fulltree) {
    global $CFG;

    require($CFG->dirroot.'/local/mr/bootstrap.php');
    require_once($CFG->dirroot.'/blocks/intelligent_learning/extrasettings.php');

    $helper = new mr_helper('blocks/intelligent_learning');

    $yesnooptions = array(
        0 => new lang_string('no'),
        1 => new lang_string('yes')
    );

    $configs = array();

    $options = array(
        'moodle' => new lang_string('moodle', 'block_intelligent_learning'),
        'ilp'    => new lang_string('ilpst', 'block_intelligent_learning')
    );

    $configs[] = new admin_setting_configselect(
        'gradebookapp',
        new lang_string('gradebookapp', 'block_intelligent_learning'),
        new lang_string('gradebookappdesc', 'block_intelligent_learning'),
        'moodle',
        $options
    );

    $configs[] = new admin_setting_configselect(
        'gradelock',
        new lang_string('gradelock', 'block_intelligent_learning'),
        new lang_string('gradelockdesc', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

    $configs[] = new admin_setting_configselect(
        'modifysectionvisibility',
        new lang_string('modifysectionvisibility', 'block_intelligent_learning'),
        new lang_string('modifysectionvisibilitydesc', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

    $configs[] = new admin_setting_configselect(
        'modifycrosslistvisibility',
        new lang_string('modifycrosslistvisibility', 'block_intelligent_learning'),
        new lang_string('modifycrosslistvisibilitydesc', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

    $configs[] = new admin_setting_configselect(
        'showlastattendance',
        new lang_string('showlastattendance', 'block_intelligent_learning'),
        new lang_string('showlastattendancedesc', 'block_intelligent_learning'),
        1,
        $yesnooptions
    );

     $configs[] = new admin_setting_configselect(
        'showneverattended',
        new lang_string('showneverattended', 'block_intelligent_learning'),
        new lang_string('showneverattendeddesc', 'block_intelligent_learning'),
        1,
        $yesnooptions
    );

    $options   = array(
        'expirelabel_expiredate' => new lang_string('expirelabel_expiredate', 'block_intelligent_learning'),
        'expirelabel_extensiondate' => new lang_string('expirelabel_extensiondate', 'block_intelligent_learning')
    );
    $configs[] = new admin_setting_configselect(
        'expirelabel',
        new lang_string('expirelabel', 'block_intelligent_learning'),
        new lang_string('expirelabeldesc', 'block_intelligent_learning'),
        'expirelabel_expiredate',
        $options
    );

    $configs[] = new admin_setting_configselect(
        'dateformat',
        new lang_string('dateformat', 'block_intelligent_learning'),
        new lang_string('dateformatdesc', 'block_intelligent_learning'),
        'm/d/Y',
        $helper->date->get_formats()
    );
    /*
     * Category grade cutoff*/
     
    $configs[] = new admin_setting_intelligent_learning_catdate(
        'categorycutoff',
        new lang_string('categorycutoff', 'block_intelligent_learning'),
        new lang_string('categorycutoffdesc', 'block_intelligent_learning'),
        ''
    );


    $options   = array(
        0 => '0',
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
        5 => '5',
        6 => '6'
    );


    $configs[] = new admin_setting_configselect(
        'midtermgradecolumns',
        new lang_string('midtermgradecolumns', 'block_intelligent_learning'),
        new lang_string('midtermgradecolumnsdesc', 'block_intelligent_learning'),
        1,
        $options
    );

     $configs[] = new admin_setting_configselect(
        'gradevalidatelocalgradescheme',
        new lang_string('gradevalidatelocalgradescheme', 'block_intelligent_learning'),
        new lang_string('gradevalidatelocalgradeschemedesc', 'block_intelligent_learning'),
        1,
        $yesnooptions
    );

    $configs[] = new admin_setting_intelligent_learning_extraletters();

    $configs[] = new admin_setting_configtext(
        'ilpurl',
        new lang_string('ilpurl', 'block_intelligent_learning'),
        new lang_string('ilpurldesc', 'block_intelligent_learning'),
        '',
        PARAM_URL
    );


    $configs[] = new admin_setting_configpasswordunmask(
        'webservices_token',
        new lang_string('webservices_token', 'block_intelligent_learning'),
        new lang_string('webservices_tokendesc', 'block_intelligent_learning'),
        ''
    );

    $configs[] = new admin_setting_configtext(
        'webservices_ipaddresses',
        new lang_string('webservices_ipaddresses', 'block_intelligent_learning'),
        new lang_string('webservices_ipaddressesdesc', 'block_intelligent_learning'),
        '',
        PARAM_RAW,
        40
    );

    // Colleague-only settings.
    /*
    * 
    $configs[] = new admin_setting_heading('colleaguesection', new lang_string('colleaguesection', 'block_intelligent_learning'), null);

    $configs[] = new admin_setting_configselect(
        'retentionalertlink',
        new lang_string('retentionalertlink', 'block_intelligent_learning'),
        new lang_string('retentionalertlinkdesc', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

    $configs[] = new admin_setting_configselect(
        'dailyattendancelink',
        new lang_string('dailyattendancelink', 'block_intelligent_learning'),
        new lang_string('dailyattendancelinkdesc', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

     $configs[] = new admin_setting_configtext(
        'retentionalertpid',
        new lang_string('retentionalertpid', 'block_intelligent_learning'),
        new lang_string('retentionalertpiddesc', 'block_intelligent_learning'),
        'CORE-WBCOS067'
    );

    $configs[] = new admin_setting_configtext(
        'attendancepid',
        new lang_string('attendancepid', 'block_intelligent_learning'),
        new lang_string('attendancepiddesc', 'block_intelligent_learning'),
        'ST-WESTS041'
    );

    $configs[] = new admin_setting_configtext(
        'stgradebookpid',
        new lang_string('stgradebookpid', 'block_intelligent_learning'),
        new lang_string('stgradebookpiddesc', 'block_intelligent_learning'),
        'ST-GBS005'
    );
    */

    // Banner-only settings.
    $configs[] = new admin_setting_heading('bannersection', new lang_string('bannersection', 'block_intelligent_learning'), null);

    $configs[] = new admin_setting_configselect(
        'showdefaultincomplete',
        new lang_string('showdefaultincomplete', 'block_intelligent_learning'),
        new lang_string('showdefaultincompletedesc', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

    // ILP API connection.
    $configs[] = new admin_setting_heading('livegrades', new lang_string('livegrades', 'block_intelligent_learning'), null);

    $configs[] = new admin_setting_configtext(
        'ilpapi_url',
        new lang_string('ilpapi_url', 'block_intelligent_learning'),
        new lang_string('ilpapi_urldesc', 'block_intelligent_learning'),
        '',
        PARAM_URL
    );

    $configs[] = new admin_setting_configtext(
        'ilpapi_connectionid',
        new lang_string('ilpapi_connectionid', 'block_intelligent_learning'),
        new lang_string('ilpapi_connectioniddesc', 'block_intelligent_learning'),
        ''
    );

    $configs[] = new admin_setting_configpasswordunmask(
        'ilpapi_connectionpassword',
        new lang_string('ilpapi_connectionpassword', 'block_intelligent_learning'),
        new lang_string('ilpapi_connectionpassworddesc', 'block_intelligent_learning'),
        ''
    );

    $configs[] = new admin_setting_configselect(
        'ilpapi_issslcaauthority',
        new lang_string('ilpapi_issslcaauthority', 'block_intelligent_learning'),
        new lang_string('ilpapi_sslcawarning', 'block_intelligent_learning'),
        0,
        $yesnooptions
    );

    $configs[] = new admin_setting_configtext(
        'ilpapi_certpath',
        new lang_string('ilpapi_certpath', 'block_intelligent_learning'),
        new lang_string('ilpapi_certexplanation', 'block_intelligent_learning'),
        ''
    );

    // Web Services.
    $services = array('course', 'enroll', 'user', 'groups', 'groups_members');

    $endpoints = array();
    foreach ($services as $service) {
        $endpoints[] = "$CFG->wwwroot/blocks/intelligent_learning/webservices/$service.php";
    }
    $endpoints = '<h5>'.new lang_string('datatelwebserviceendpoints', 'block_intelligent_learning').'</h5><ul><li>'.implode('</li><li>', $endpoints).'</li></ul>';

    $configs[] = new admin_setting_heading('webserviceendpoints', new lang_string('webserviceendpoints', 'block_intelligent_learning'), $endpoints);

    // Define the config plugin so it is saved to the config_plugin table then add to the settings page.
    foreach ($configs as $config) {
        $config->plugin = 'blocks/intelligent_learning';
        $settings->add($config);
    }
}