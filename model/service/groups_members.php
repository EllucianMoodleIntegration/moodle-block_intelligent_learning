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

require_once($CFG->dirroot.'/blocks/intelligent_learning/model/service/abstract.php');
/**
 * Groups Members Service Model
 *
 * @author Mark Nielsen
 * @author Sam Chaffee
 * @package block_intelligent_learning
 */
class blocks_intelligent_learning_model_service_groups_members extends blocks_intelligent_learning_model_service_abstract {

    protected $mappings = array(
        'course',
        'user',
        'groupname',
        'idnumber'
    );

    protected $mapoptions = array(
        'course'     => array('required' => 1, 'type' => PARAM_TEXT),
        'user'       => array('required' => 0, 'type' => PARAM_TEXT),
        'idnumber'   => array('required' => 0, 'type' => PARAM_TEXT),
        'groupname'  => array('type' => PARAM_TEXT),
    );

    protected $actions = array(
        'create',
        'add',
        'delete',
        'drop',
    );

    /**
     * Group Members Provisioning
     *
     * @param string $xml XML data
     * @return string
     */
    public function handle($xml) {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/group/lib.php');

        list($action, $data) = $this->helper->xmlreader->validate_xml($xml, $this);

        // TODO: Have data come back as an object?
        $data = (object) $data;

        if (!$course = $DB->get_record('course', array('idnumber' => $data->course))) {
            throw new Exception("Passed course doesn't exist: $data->course");
        }

        if (empty($data->user) && empty($data->idnumber)) {
            throw new Exception('Either username or idnumber is required');
        }
        if (!empty($data->idnumber)) {
            if (!$user = $DB->get_record('user', array('idnumber' => $data->idnumber))) {
                throw new Exception("Passed user idnumber doesn't exist: $data->idnumber");
            }
        } else {
            if (!$user = $DB->get_record('user', array('username' => $data->user))) {
                throw new Exception("Passed username doesn't exist: $data->user");
            }
        }
        if (!$group = $DB->get_record('groups', array('courseid' => $course->id))) {
            throw new Exception("Failed to add enrollment to group as Passed group doesn't exist: with course idnumber = $course->idnumber. But the enrollment is assigned to the section successfully.");
        }
        switch ($action) {
            case 'create':
            case 'add':
                if (!groups_add_member($group->id, $user->id)) {
                    throw new Exception("Failed to add new group member course idnumber = $course->idnumber user username = $user->username group name = $group->name");
                }
                break;
            case 'delete':
            case 'drop':
                if (!groups_remove_member($group->id, $user->id)) {
                    throw new Exception("Failed to remove group member course idnumber = $course->idnumber user username = $user->username group name = $group->name");
                }
                break;
        }
        return $this->response->groups_members_handle($course, $user, $group);
    }
}
