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
include_once($CFG->dirroot.'/mod/quiz/attemptlib.php');
/**
 * User service model
 *
 * @author Mark Nielsen
 * @author Sam Chaffee
 * @package block_intelligent_learning
 */
class blocks_intelligent_learning_model_service_user extends blocks_intelligent_learning_model_service_abstract {

    /**
     * Synced user fields
     *
     * @var array
     */
    protected $userfields = array(
        'username',
        'middlename',
        'password',
        'idnumber',
        'firstname',
        'lastname',
        'email',
        'auth',
        'icq',
        'skype',
        'yahoo',
        'aim',
        'man',
        'phone1',
        'phone2',
        'institution',
        'address',
        'city',
        'country',
        'lang',
        'timezone',
    );

    /**
     * Get a user's recent activity for a course
     *
     * @param string $username The user's username; either username or userid is required
     * @param string $userid - The user's id; either username or userid is required
     * @param string $course The course's idnumber - if not passed, all user courses are returned
     * @param int $fromdate Unix timestamp of the start date
     * @param bool $collapse Send only most recent update to an activity, count the rest
     * @param int $percourse Limit number of activities per course
     * @param $maxdays - maximum number of days from the start date of a class that it should stay in
     * @return string
     */
    public function get_user_course_recent_activity($maxdays, $username = null, $userid = null, $course = null, $fromdate = null, $collapse = false, $percourse = 10) {
        global $USER;

        // Allow items to be retrieved using either username or idnumber.
        if ((is_null($userid)) && (is_null($username))) {
            throw new Exception("Either a username or userid is required");
        }
        if (!is_null($username)) {
            $user      = $this->helper->connector->get_user($username);
        } else {
            $user      = $this->helper->connector->get_user_by_id($userid);
        }
        $courses   = $this->helper->connector->get_courses($user, $course, $maxdays);
        $percourse = clean_param($percourse, PARAM_INT);

        // Switch user global out for cap checks and the like.
        $olduser = $USER;
        $USER    = $user;

        $recentactivity = $this->helper->connector->get_recent_activity($fromdate, $courses, $collapse, $percourse);

        // Restore.
        $USER = $olduser;

        return $this->response->user_get_user_course_recent_activity($user, $courses, $recentactivity);
    }

    /**
     * Get a user's due activities for a course
     *
     * @param int $todate Unix timestamp of the end date
     * @param string $username The user's username; either username or userid is required
     * @param string $userid - The user's id; either username or userid is required  
     * @param string $course The course's idnumber - if not passed, all user courses are returned
     * @param bool $deschtml Include HTML description
     * @param $maxdays - maximum number of days from the start date of a class that it should stay in
     * @return string
     */
    public function get_user_course_activities_due( $todate, $maxdays, $username = null, $userid = null, $course = null, $deschtml = true) {
        global $CFG, $DB;

        $todate    = clean_param($todate, PARAM_INT);
        $maxevents = 100;

        // Allow items to be retrieved using either username or idnumber.
        if ((is_null($userid)) && (is_null($username))) {
            throw new Exception("Either a username or userid is required");
        }
        if (!is_null($username)) {
            $user      = $this->helper->connector->get_user($username);
        } else {
            $user      = $this->helper->connector->get_user_by_id($userid);
        }

        $courses = $this->helper->connector->get_courses($user, $course, $maxdays);

        if (!empty($CFG->gradebookroles)) {
            $gradebookroles = explode(',', $CFG->gradebookroles);
        } else {
            $gradebookroles = array();
        }

        $dueactivities = array();
        foreach ($courses as $course) {
            $activities = array();

            // See if they have a gradebook role in the course.
            if (!$roles = get_user_roles(context_course::instance($course->id), $user->id)) {
                // Shouldn't happen, they should already be confirmed to have a role in this course.
                unset($courses[$course->id]);
                continue;
            }
            $found = false;
            foreach ($roles as $role) {
                if (in_array($role->roleid, $gradebookroles)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                unset($courses[$course->id]);
                continue;
            }

            $params = array(
                $course->id, CONTEXT_MODULE, $user->id, $todate,
                $course->id, CONTEXT_MODULE, $user->id, $todate,
                $course->id, CONTEXT_MODULE, $user->id, $todate,
                $course->id, CONTEXT_MODULE, $user->id, $todate,
            );

            $sql = "SELECT cm.id AS cmid, m.name AS type, m.name AS module, q.name, q.intro AS descriptionhtml, q.timeclose AS duedate, cm.visible, c.id AS contextid
                      FROM {quiz} q
                INNER JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = ?
                INNER JOIN {modules} m ON cm.module = m.id AND m.name = 'quiz'
                INNER JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = ?
           LEFT OUTER JOIN {quiz_grades} g ON q.id = g.quiz AND g.userid = ?
                     WHERE q.timeclose < ?
                       AND g.id IS NULL
                     UNION ALL
                    SELECT cm.id AS cmid, m.name AS type, m.name AS module, l.name, null AS descriptionhtml, l.deadline AS duedate, cm.visible, c.id AS contextid
                      FROM {lesson} l
                INNER JOIN {course_modules} cm ON cm.instance = l.id AND cm.course = ?
                INNER JOIN {modules} m ON cm.module = m.id AND m.name = 'lesson'
                INNER JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = ?
           LEFT OUTER JOIN {lesson_grades} g ON l.id = g.lessonid AND g.userid = ?
                     WHERE l.deadline < ?
                       AND g.id IS NULL
                     UNION ALL
                    SELECT cm.id AS cmid, 'assignment' AS type, m.name AS module, a.name, a.intro AS descriptionhtml, a.duedate, cm.visible, c.id AS contextid
                      FROM {assign} a
                INNER JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = ?
                INNER JOIN {modules} m ON cm.module = m.id AND m.name = 'assign'
                INNER JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = ?
           LEFT OUTER JOIN {assign_submission} s ON a.id = s.assignment AND s.userid = ?
                     WHERE a.duedate < ?
                       AND s.id IS NULL
                  ORDER BY duedate";

            $activities = $DB->get_records_sql($sql, $params);

            // Only grab when we have activities to process.
            if (!empty($activities)) {
                $modinfo = get_fast_modinfo($course, $user->id);
            }

            // Unset hidden activities.
            foreach ($activities as $key => $activity) {
                // Check to see if the activity is visible to the user.
                if (empty($modinfo->cms[$activity->cmid]) || (!$modinfo->cms[$activity->cmid]->uservisible &&
                    empty($modinfo->cms[$activity->cmid]->availableinfo))) {
                    unset($activities[$key]);
                    continue;
                }

                if (!empty($activity->descriptionhtml)) {
                    if (mb_detect_encoding($activity->descriptionhtml) != 'UTF-8') {
                        $activity->descriptionhtml = mb_convert_encoding($activity->descriptionhtml, 'UTF-8');
                    }
                    $html = file_rewrite_pluginfile_urls($activity->descriptionhtml, 'pluginfile.php', $activity->contextid, 'mod_'.$activity->module, 'intro', null);
                    $html = format_text($html, FORMAT_HTML);
                    $text = trim(html_to_text($html, 0));
                } else {
                    $html = $text = '';
                }

                $activities[$key]->descriptionhtml = !empty($deschtml) ? $html : null;
                $activities[$key]->descriptiontext = $text;
                $activities[$key]->accessible      = $modinfo->cms[$activity->cmid]->uservisible;
            }

            // Add to our main array.
            $dueactivities[$course->id] = $activities;
        }
        return $this->response->user_get_user_course_activities_due($user, $courses, $dueactivities);
    }
    /**
     * Get a user's course calendar events
     *     
     * @param int $fromdate Unix timestamp of the start date
     * @param int $todate Unix timestamp of the end date
     * @param string $username - The user's username; either username or id is required
     * @param string $userid - The user's id; either username or id is required
     * @param string $course The course's idnumber - if not passed, all user courses are returned
     * @param $maxdays - maximum number of days from the start date of a class that it should stay in
     * @return string
     */
    public function get_user_course_events($fromdate, $todate, $maxdays, $username = null, $userid = null, $course = null) {
        global $CFG, $COURSE;

        require_once($CFG->dirroot.'/calendar/lib.php');

        // Test times:
        // 1259740800.
        // 1259913600.

        $fromdate  = clean_param($fromdate, PARAM_INT);
        $todate    = clean_param($todate, PARAM_INT);
        $maxevents = 100;

        // Allow items to be retrieved using either username or idnumber.
        if ((is_null($userid)) && (is_null($username))) {
            throw new Exception("Either a username or userid is required");
        }
        if (!is_null($username)) {
            $user      = $this->helper->connector->get_user($username);
        } else {
            $user      = $this->helper->connector->get_user_by_id($userid);
        }

        $courses = $this->helper->connector->get_courses($user, $course, $maxdays);

        if ($fromdate > $todate) {
            throw new Exception("From date ($fromdate) is greater than to date ($todate)");
        }

        $events       = array();
        $daysinfuture = ($todate - $fromdate) / DAYSECS;

        foreach ($courses as $course) {
            $COURSE = $course;

            $groups = groups_get_user_groups($course->id, $user->id);
            if (!empty($groups[0])) {
                $groups = $groups[0];
            } else {
                $groups = false;
            }
			
			//unable to use recommended calendar block get_content since the input/output differ from what is needed here
			//so implementing here call to calendar_get_events directly.
            $events[$course->id] = $this->get_upcoming_calendar(array($course->id), $groups, $user->id, $daysinfuture, 100, $fromdate);
        }
        return $this->response->user_get_user_course_events($user, $courses, $events);
    }

    /**
     * Get grades for Datatel
     *
     * @param int $batchsize The number of grade records to return (No more than 50)
     * @param int $lastid The last grade ID processed
     * @return string
     */
    public function get_user_course_grades($batchsize = 50, $lastid = null, $starttime = 0, $endtime = null) {
        global $DB;

        $batchsize = clean_param($batchsize, PARAM_INT);
        $starttime = clean_param($starttime, PARAM_INT);

        $sqlparams = array();

        if ($batchsize > 50) {
            $batchsize = 50;
        }
        if (!is_null($lastid)) {
            $lastid    = clean_param($lastid, PARAM_INT);
            $lastidsql = " AND l.id > :lastid";
            $sqlparams['lastid'] = $lastid;
        } else {
            $lastidsql = '';
        }
        if (is_null($endtime)) {
            $endtime = time() - 1;
        } else {
            $endtime = clean_param($endtime, PARAM_INT);
        }
        if ($starttime > $endtime) {
            throw new Exception("The start time ($starttime) cannot be more than the end time ($endtime)");
        }

        $sqlparams = array_merge($sqlparams, array(
            'siteid' => SITEID,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ));
        $sql = "SELECT l.*, l.course AS courseid, u.username, u.idnumber AS useridnumber, c.idnumber AS courseidnumber,
                      u1.username AS mt1username, u2.username AS mt2username, u3.username AS mt3username,
                      u4.username AS mt4username, u5.username AS mt5username, u6.username AS mt6username,
                      u7.username AS finalgradeusername, u8.username AS expiredateusername,
                      u9.username AS lastaccessusername, u9.username AS neverattendedusername
                 FROM {block_intelligent_learning} l
           INNER JOIN {user} u ON l.userid = u.id
           INNER JOIN {course} c ON l.course = c.id
      LEFT OUTER JOIN {user} u1 ON l.mt1userid = u1.id
      LEFT OUTER JOIN {user} u2 ON l.mt2userid = u2.id
      LEFT OUTER JOIN {user} u3 ON l.mt3userid = u3.id
      LEFT OUTER JOIN {user} u4 ON l.mt4userid = u4.id
      LEFT OUTER JOIN {user} u5 ON l.mt5userid = u5.id
      LEFT OUTER JOIN {user} u6 ON l.mt6userid = u6.id
      LEFT OUTER JOIN {user} u7 ON l.finalgradeuserid = u7.id
      LEFT OUTER JOIN {user} u8 ON l.expiredateuserid = u8.id
      LEFT OUTER JOIN {user} u9 ON l.lastaccessuserid = u9.id
      LEFT OUTER JOIN {user} u10 ON l.neverattendeduserid = u10.id
                WHERE l.course != :siteid
                  AND l.timemodified > :starttime
                  AND l.timemodified < :endtime
                      $lastidsql
             ORDER BY l.id ASC";

        try {
            $grades = $DB->get_records_sql($sql, $sqlparams, 0, $batchsize);
        } catch (dml_exception $e) {
            throw new Exception("Couldn't read grades from database");
        }

        if (!$grades) {
            $grades = array();
            $lastid = '';
        } else {
            $last   = end($grades);
            $lastid = $last->id;
        }
        return $this->response->user_get_user_course_grades($grades, $batchsize, $lastid, $starttime, $endtime);
    }

    /**
     * User Provisioning
     *
     * @param string $xml XML Data
     * @return string
     */
    public function handle($xml) {
        global $CFG, $DB;

        list($action, $data) = $this->helper->xmlreader->validate_xml($xml, $this);

        if (empty($data['username'])) {
            throw new Exception('No username passed, required');
        }
        $username = clean_param($data['username'], PARAM_TEXT);
        $username = core_text::strtolower($username);

        if (mb_strlen($username) > 100) {
            throw new Exception("The username is over 100 characters long and cannot be used by Moodle: $username");
        }
        // Update data array.
        $data['username'] = $username;
        $idnumber = $data['idnumber'];

        // Try to get user that we are operating on.
        $user = $DB->get_record('user', array('mnethostid' => $CFG->mnet_localhost_id, 'username' => $username));

        // Try to get user based on the idnumber
        // empty $(user) returns true if null, empty string, 0, false
         if(empty($user)) {
            $user = $DB->get_record('user', array('mnethostid' => $CFG->mnet_localhost_id, 'idnumber' => $idnumber));
        }

        switch($action) {
            case 'create':
            case 'add':
            case 'update':
                if ($user) {
                    $this->update($user, $data);
                } else {
                    $user = $this->add($data);
                }
                break;
             case 'updatePronoun':
                if ($user) {
                    $this->updatePronoun($user, $data);
                }
                break;
            case 'delete':
            case 'drop':
                if ($user and !@delete_user($user)) {
                    throw new Exception("Failed to deleted user with username = $user->username and id = $user->id");
                }
                break;
            default:
                throw new Exception("Invalid action found: $action.  Valid actions: create, update and delete");
        }
        return $this->response->user_handle($user);
    }

    /**
     * Add a user
     *
     * @param array $data User data
     * @return int
     */
    private function add($data) {
        global $CFG, $DB;

        $user = array();
        foreach ($this->userfields as $field) {
            if (isset($data[$field])) {
                $user[$field] = $data[$field];
            }
        }

        $user = (object) truncate_userinfo($user);
        $user->middlename = $data['middlename'];
        $user->modified   = time();
        $user->confirmed  = 1;
        $user->deleted    = 0;
        $user->mnethostid = $CFG->mnet_localhost_id;
        if (empty($user->auth)) {
            $user->auth = 'manual';
        }
        if (empty($user->lang)) {
            $user->lang = $CFG->lang;
        }
        if (isset($user->password)) {
            $user->password = hash_internal_user_password($user->password);
        } else {
            $user->password = '';
        }

        try {
            $userid = $DB->insert_record('user', $user);
        } catch (dml_exception $e) {
            throw new Exception("Failed to insert user with username = $user->username");
        }

        try {
            $user = $DB->get_record('user', array('id' => $userid));
        } catch (dml_exception $e) {
            throw new Exception("Failed to get user object from database id = $userid");
        }

        return $user;
    }

    /**
     * Update a user
     *
     * @param object $user Current user
     * @param array $data New user data
     * @return void
     */
    private function update($user, $data = null) {
        global $DB;

        // Truncate first!
        $data = truncate_userinfo($data);

        $update = false;
        $record = new stdClass;
        foreach ($data as $key => $value) {
            if (!in_array($key, $this->userfields)) {
                continue;
            }
            // Special field processing.
            switch ($key) {
                case 'password':
                    $value = hash_internal_user_password($value);
                    break;
            }
            if ($key != 'id' and isset($user->$key) and $user->$key != $value) {
                $record->$key = $value;
                $update = true;
            }
        }
        if ($update) {
            // Make sure this is set properly.
            $record->id = $user->id;
            $record->middlename = $data['middlename'];

            try {
                $DB->update_record('user', $record);
            } catch (dml_exception $e) {
                throw new Exception("Failed to update user with username = $user->username and id = $user->id");
            }
        }
    }

    
     /**
     * Update a user with Pronoun data
     *
     * @param object $user Current user
     * @param array $data New user data with Pronoun
     * @return void
     */
    private function updatePronoun($user, $data) {
        global $DB;
        try {
            $payload = new stdClass();
            $payload->userid = $user->id;
            $payload->data = $data['pronoun'];
            //check if pronoun field exist or not
            $record = $DB->get_record('user_info_field', array('shortname' => 'Pronouns'));
            //get pronoun field id and user field id to insert/update
            if ($record) {
            $payload->fieldid = $record->id;
            //check if the person record has pronoun or not
            $userPronounData = $DB->get_record('user_info_data', array('userid' => $payload->userid, 'fieldid' => $payload->fieldid));
                if ($userPronounData) {
                    $payload->id = $userPronounData->id;
                    $DB->update_record('user_info_data', $payload);
                } else {
                    $DB->insert_record('user_info_data', $payload);
                }
            } else {
                //get default category data
                $strdefaultcategory = get_string('profiledefaultcategory', 'admin');
                $categorydata = $DB->get_record('user_info_category', ['name' => $strdefaultcategory]);
                $catId = $categorydata->id;
                $catSortOrder = $categorydata->sortorder;
                // Create a new profile field for pronoun under default category.
                $fieldData = new stdClass();
                $fieldData->datatype = 'text';
                $fieldData->shortname = 'Pronouns';
                $fieldData->name = 'Pronouns';
                $fieldData->description = '';
                $fieldData->required = false;
                $fieldData->locked = false;
                $fieldData->forceunique = false;
                $fieldData->signup = false;
                $fieldData->visible = '2';
                $fieldData->categoryid = $catId;
                $fieldData->sortorder = $catSortOrder;
                //insert pronoun field
                $fieldPronoun = $DB->insert_record('user_info_field', $fieldData);
                $payload->fieldid = $fieldPronoun;
                //insert pronoun data for user
                $DB->insert_record('user_info_data', $payload);
            }
        } catch (Exception $e) {
            throw new Exception("Failed to update user with username = $user->username and id = $user->id");
        }
    }
    

/**
 * Gets the upcoming calendar event
 *
 * @param array $courses array of courses
 * @param array|int|bool $groups array of groups, group id or boolean for all/no group events
 * @param array|int|bool $users array of users, user id or boolean for all/no user events
 * @param int $daysinfuture number of days in the future we 'll look
 * @param int $maxevents maximum number of events
 * @param int $fromtime start time
 * @return array $output array of upcoming events
 */
private function get_upcoming_calendar($courses, $groups, $users, $daysinfuture, $maxevents, $fromtime=0) {
    global $CFG, $COURSE, $DB;

    $display = new stdClass;
    $display->range = $daysinfuture; // How many days in the future we 'll look
    $display->maxevents = $maxevents;

    $output = array();

    $processed = 0;
    $now = time(); // We 'll need this later
    $usermidnighttoday = usergetmidnight($now);

    if ($fromtime) {
        $display->tstart = $fromtime;
    } else {
        $display->tstart = $usermidnighttoday;
    }
    

    // This works correctly with respect to the user's DST, but it is accurate
    // only because $fromtime is always the exact midnight of some day!
    $display->tend = usergetmidnight($display->tstart + DAYSECS * $display->range + 3 * HOURSECS) - 1;

    // Get the events matching our criteria
    $events = calendar_get_events($display->tstart, $display->tend, $users, $groups, $courses);

    $hrefparams = array();
    if(!empty($courses)) {
        $courses = array_diff($courses, array(SITEID));
        if(count($courses) == 1) {
            $hrefparams['course'] = reset($courses);
        }
    }

    if ($events !== false) {
        foreach($events as $event) {
            if (!empty($event->modulename)) {
                if ($event->courseid == $COURSE->id) {
                    if (isset($modinfo->instances[$event->modulename][$event->instance])) {
                        $cm = $modinfo->instances[$event->modulename][$event->instance];
                        if (!$cm->uservisible) {
                            continue;
                        }
                    }
                } else {
                    if (!$cm = get_coursemodule_from_instance($event->modulename, $event->instance)) {
                        continue;
                    }
                    if (!\core_availability\info_module::is_user_visible($cm, 0, false)) {
                        continue;
                    }
                }
            }

            if ($processed >= $display->maxevents) {
                break;
            }

            $event->time = calendar_format_event_time($event, $now, $hrefparams);
            $output[] = $event;
            ++$processed;
        }
    }
    return $output;
}

 
  //////////////////////////////////////NEW ILP API starts here/////////////////////////////////////////////////////////////


    /**
    * Test function to test connectivity from ILP
    * @param string $message
    * @return array
    */
    public function about_moodle($message) {
        $result['Version'] = moodle_major_version();
        $result['Message'] = $message;
        return $result;
    }



    /**
    * Get activities from users in  selected
    *
    * @param array   $studentIds
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return array
    */
    public function get_user_activities($studentIds, $startDate = null, $endDate = null, $maxdays = null) {

        global $DB;
        
        //die(var_export($courses, false));
        $results = array ();
        $userids = array ();

        if (strlen($studentIds) > 0) {
            //add quotes to incoming list
            $userids = explode(",", $studentIds);
        }
        $userids = clean_param_array($userids, PARAM_NOTAGS);
        
        foreach ($userids as $userid) {
            $sql_param = array ();
            $sql_param['userid'] = $userid;

            $sql = "SELECT u.id,
                                   u.idnumber as sourcedid,
                                   u.firstname,
                                   u.lastname,
                                   u.email
                              FROM {user} u
                              WHERE u.idnumber = :userid";
    
            $user = $DB->get_record_sql($sql, $sql_param);
            if (!empty ($user)) {
            $sections = $this->helper->connector->get_courses($user, "", $maxdays);
            //die(var_export($sections, false));
            foreach ($sections as $section){
            if(!empty($section->idnumber)){
            $result = array ();
            $courses = array();
            $courses[] = $section->id;
            //die(var_export($courses , true));

            $result['id'] = $user->id;
            $result['sourcedid'] = $user->sourcedid;
            $result['courseid'] = $section->idnumber;
            $result['firstname'] = $user->firstname;
            $result['lastname'] = $user->lastname;
            $result['email'] = $user->email;
        
	        //Use legacy logging if selected or standard logging
	        $manager = get_log_manager();
			$allreaders = $manager->get_readers();
			if (isset($allreaders['logstore_legacy'])) {
	    		$reader = $allreaders['logstore_legacy'];
	    	} else {
	        	$reader = reset($allreaders);
	    	}
	    	
	    	// If reader is not a sql_internal_table_reader and not legacy store then return.
	    	if (!($reader instanceof \core\log\sql_internal_table_reader) && !($reader instanceof logstore_legacy\log\store)) {
	        	throw new Exception('Either standard or legacy logging needs to be set up.');
	    	}

            $result['AssessmentsBegun'] = self :: get_count_assessments_begun($user->id, $courses, $startDate, $endDate);
            $result['AssessmentsFinished'] = self :: get_count_assessments_finished($user->id, $courses, $startDate, $endDate);

            $result['AssignmentsRead'] = self :: get_assignments_read($user->id, $courses, $startDate, $endDate, $reader);
            $result['AssignmentsSubmitted'] = self :: get_assignments_submissions($user->id, $courses, $startDate, $endDate);

            $result['ContentPagesViewed'] = self :: get_count_contentpages_viewed($user->id, $courses, $startDate, $endDate, $reader);

            $result['DiscussionPostsCreated'] = self :: get_count_forum_posts($user->id, $courses, $startDate, $endDate);
            $result['DiscussionPostsRead'] = self :: get_count_forum_posts_read($user->id, $courses, $startDate, $endDate, $reader);

            $result['NumberCMSSessions'] = self :: get_count_sessions($user->id, $courses, $startDate, $endDate, $reader);

            $result['CalendarEntriesAdded'] = self :: get_count_calendar_added($user->id, $courses, $startDate, $endDate, $reader);
            
            
            //die(var_export($result , true));
            $results[] = array (
                'activity' => $result
            );
            }
            }
         }
      }

        return $this->response->standard($results);

    }

    /**
     * Get Total number of calendar entries added
     * @param int   $userid
     * @param int[] $courses
     * @param int   $startdate
     * @param int   $enddate
     * @return int
     */
    private static function get_count_calendar_added($userid, array $courses, $startdate = null, $enddate = null, $reader) {
        global $DB;
	    if ($reader instanceof logstore_legacy\log\store) {
	    	$coursecol = 'course';
	    	$timecol = 'time';
	    	$modulecol = 'module';
	    	$moduleval = 'calendar';
	    	$actionval = 'add';
	    	$logtable = 'log';
	    } else {
	    	$coursecol = 'courseid';
	    	$timecol = 'timecreated';
	    	$modulecol = 'target';
	    	$moduleval = 'calendar_event';
	    	$actionval = 'created';
	    	$logtable = $reader->get_internal_log_table_name();
	    }
        $sql_param = array ();
        list ($user_sql, $sql_param1) = $DB->get_in_or_equal($userid, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param1);
        $select = "(userid $user_sql)";
        $select .= " AND ($modulecol ='$moduleval') AND (action='$actionval')";
        if (empty ($courses)) {
            list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
            $sql_param = array_merge($sql_param, $sql_param2);
            $select .= " AND ($coursecol $course_sql)";
        }
        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $select .= " AND ($timecol >= :startdate)";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $select .= " AND ($timecol < :enddate)";
        }

        $result = $DB->count_records_select($logtable, $select, $sql_param);
        return $result;
    }

    
    /**
    * Get count of assessments begun by user in course/s
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_count_assessments_begun($userid, array $courses, $startdate = null, $enddate = null) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;

        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

        $sql = "SELECT COUNT(DISTINCT(qa.quiz))
                                FROM {quiz} q
                                JOIN {quiz_attempts} qa ON q.id = qa.quiz
                               WHERE q.course $course_sql AND qa.state =" . var_export(quiz_attempt::IN_PROGRESS, true) . "
                                 AND qa.userid = :userid";

        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND qa.timestart >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND qa.timestart < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);

    }

    /**
    * Get count of assessments finished by user in course/s
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_count_assessments_finished($userid, array $courses, $startdate = null, $enddate = null) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;
        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

        $sql = "SELECT COUNT(*)
                                FROM {quiz} q
                                JOIN {quiz_grades} qg ON qg.quiz = q.id
                               WHERE q.course $course_sql
                                 AND qg.userid = :userid";

        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND qg.timemodified >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND qg.timemodified < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);
    }

    /**
     * Get count of assignments submitted by user in course/s
     *
     * @param integer $userid
     * @param array $courses
     * @param integer $startdate - timestamp
     * @param integer $enddate - timestamp
     *
     * @return integer
     */
    private static function get_assignments_submissions($userid, array $courses, $startdate = null, $enddate = null) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;
        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

        $sql = "SELECT COUNT(*)
                                  FROM {assign} a
                                  JOIN {assign_submission} asub ON a.id = asub.assignment
                                 WHERE a.course $course_sql
                                   AND asub.userid = :userid AND asub.status='submitted'";

        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND asub.timecreated >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND asub.timecreated < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);
    }

    /**
    * Get count of assignments readed by user in course/s
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_assignments_read($userid, array $courses, $startdate = null, $enddate = null, $reader) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;
        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

	    if ($reader instanceof logstore_legacy\log\store) {
	    	$timecol = 'time';
	        $sql = "SELECT COUNT(DISTINCT(cmid))
	                                FROM {log} l
	                               WHERE l.course $course_sql
	                                 AND l.module = 'assign'
	                                 AND l.action = 'view'
	                                 AND l.userid = :userid";
		} else {
	    	$timecol = 'timecreated';
	    	$logtable = $reader->get_internal_log_table_name();
	        $sql = "SELECT COUNT(DISTINCT(contextinstanceid))
	                                FROM {".$logtable."} l
	                               WHERE l.courseid $course_sql
	                                 AND l.component = 'mod_assign'
	                                 AND l.action = 'viewed'
	                                 AND l.userid = :userid";
		}

        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND l.$timecol >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND l.$timecol < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);
    }

    /**
    * Get count of content pages viewed by user in course/s
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_count_contentpages_viewed($userid, array $courses, $startdate = null, $enddate = null, $reader) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;
        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

	    if ($reader instanceof logstore_legacy\log\store) {
	    	$timecol = 'time';
        	$sql = "SELECT COUNT(DISTINCT(cmid))
                    FROM {log} l
                    JOIN {modules} m ON m.name = l.module
                    JOIN {course_modules} cm ON cm.module = m.id AND cm.id = l.cmid
                   	WHERE cm.course $course_sql
                    AND l.action LIKE '%view%'
                    AND l.userid = :userid";
		} else {
	    	$timecol = 'timecreated';
	    	$logtable = $reader->get_internal_log_table_name();
	        $sql = "SELECT COUNT(DISTINCT(contextinstanceid))
                    FROM {".$logtable."} l
                    JOIN {modules} m ON l.component = 'mod_' + m.name
                    JOIN {course_modules} cm ON cm.module = m.id AND cm.id = l.contextinstanceid
                   	WHERE cm.course $course_sql
                    AND l.action LIKE '%view%'
                    AND l.userid = :userid";
		}


        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND l.$timecol >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND l.$timecol < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);
    }

    /**
    * Get count of posts in forum created by user in course/s
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_count_forum_posts($userid, array $courses, $startdate = null, $enddate = null) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;
        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

        $sql = "SELECT COUNT(*)
                              FROM {forum_posts} fp
                              JOIN {forum_discussions} fd ON fd.id = fp.discussion
                             WHERE fd.course $course_sql
                               AND fp.userid = :userid";

        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND fp.created >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND fp.created < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);
    }

    /**
    * Get count of posts in forum readed by user in course/s
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_count_forum_posts_read($userid, array $courses, $startdate = null, $enddate = null, $reader) {

        global $DB;

        $sql_param = array ();
        $sql_param['userid'] = $userid;
        list ($course_sql, $sql_param2) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $sql_param = array_merge($sql_param, $sql_param2);

	    if ($reader instanceof logstore_legacy\log\store) {
	    	$timecol = 'time';
	        $sql = "SELECT COUNT(DISTINCT(info))
					FROM {log} l
					WHERE l.course $course_sql
					AND l.module = 'forum'
					AND l.action = 'view discussion'
					AND l.userid = :userid";
		} else {
	    	$timecol = 'timecreated';
	    	$logtable = $reader->get_internal_log_table_name();
	        $sql = "SELECT COUNT(DISTINCT(objectid))
					FROM {".$logtable."} l
					WHERE l.courseid $course_sql
					AND l.component = 'mod_forum'
					AND l.target = 'discussion'
					AND l.action = 'viewed'
					AND l.userid = :userid";
		}

        if ($startdate !== null) {
            $sql_param['startdate'] = $startdate;
            $sql .= " AND l.$timecol >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_param['enddate'] = $enddate +DAYSECS;
            $sql .= " AND l.$timecol < :enddate";
        }

        return $DB->count_records_sql($sql, $sql_param);
    }

    /**
    * Get count of sessions login by user in moodle
    *
    * @param integer $userid
    * @param array $courses
    * @param integer $startdate - timestamp
    * @param integer $enddate - timestamp
    *
    * @return integer
    */
    private static function get_count_sessions($userid, $courses, $startdate = null, $enddate = null, $reader) {

        global $DB;

	    if ($reader instanceof logstore_legacy\log\store) {
	    	$timecol = 'time';
	    	$logtable = 'log';
	        $sqlr = array (
	            'userid' => $userid,
	            'course' => $courses,
	            'module' => 'course',
	            'action' => 'view',
	            
	        );
		} else {
	    	$timecol = 'timecreated';
	    	$logtable = $reader->get_internal_log_table_name();
	        $sqlr = array (
	            'userid' => $userid,
	            'courseid' => $courses,
	            'target' => 'course',
	            'action' => 'viewed',	            
	        );
	    }
	    
        $sql_params = array ();
        $where = '';
        foreach ($sqlr as $param => $value) {
            if (empty ($value)) {
                continue;
            }
            list ($where_sql, $sql_param) = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED);
            $sql_params = array_merge($sql_params, $sql_param);
            if (!empty ($where)) {
                $where .= ' AND';
            }
            $where .= " {$param} {$where_sql}";
        }

        if ($startdate !== null) {
            $sql_params['startdate'] = $startdate;
            $where .= " AND $timecol >= :startdate";
        }
        if (($enddate !== null) && ($enddate >= $startdate)) {
            $sql_params['enddate'] = $enddate +DAYSECS;
            $where .= " AND $timecol < :enddate";
        }
        return $DB->count_records_select($logtable, $where, $sql_params);
    }
    
    /**
    * Get a user's grades
    *
    * @param string $userIds A comma separated list of (external) UserIds
    * @param string $sectionIds A comma separated list of (external) SectionIds
    * @param string $startDate Unix timestamp of the date to get logs AFTER
    * @param string $endDate Unix timestamp of the date to get logs BEFORE
    * @return array of objects
    */
    public function get_user_grades($userIds = NULL, $sectionIds = NULL, $startDate = NULL, $endDate = NULL, $RecursiveLevel = 0) {
        global $DB;
       
        $returnData = $this->get_updated_user_grades($userIds, $sectionIds, $startDate, $endDate, $RecursiveLevel);
        $response = array ();
        foreach ($returnData as $grade) {
            $dataresponse = array (
                'grade' => array (
                    'gradeid' => $grade['gradeid'],
                    'finalgrade' => $grade['finalgrade'],
                    'assessmentid' => $grade['assessmentid'],
                    'userid' => $grade['userid'],
                    'sourceid' => $grade['sourceid'],
                    'needsupdate' => $grade['needsupdate']
                )
            );
            $response[] = $dataresponse;

        }
        //die(var_export($dataresponse, true));
        return $this->response->standard($response);

    }
    
    
    /**
    * Get a user's grades internal
    *
    * @param string $userIds A comma separated list of (external) UserIds
    * @param string $sectionIds A comma separated list of (external) SectionIds
    * @param string $startDate Unix timestamp of the date to get logs AFTER
    * @param string $endDate Unix timestamp of the date to get logs BEFORE
    * @return array of objects
    */
    private function get_updated_user_grades($userIds = NULL, $sectionIds = NULL, $startDate = NULL, $endDate = NULL, $RecursiveLevel = 0) {
        global $DB;

        if ($RecursiveLevel > 3) {
            throw new Exception("RecursiveLevel Too High: " . $RecursiveLevel . " Section Ids:" . var_export($sectionIds, true));
        }
        
        //TODO: Should we ignore grades if they have hidden = true;

        //Note: A unique ID must always be the first column in the result.  Otherwise $DV->get_records_sql 
        //      will group up the results by whatever the first column is.
        $sql = "select 
                            {grade_grades}.id as GradeId, 
                            {grade_grades}.finalgrade, 
                            {grade_grades}.hidden,
                            {grade_items}.id as AssessmentId, 
                            {user}.idnumber as UserId,
                            {course}.idnumber as SourceId,
                            {course}.id as CourseId,
                            {grade_items}.needsupdate as NeedsUpdate
                        from 
                            {grade_grades},
                            {user},
                            {course},
                            {grade_items}
                        where 
                            {user}.id   = {grade_grades}.userid and
                            {course}.id = {grade_items}.courseid and
                            {grade_items}.id = {grade_grades}.itemid and ({grade_items}.itemtype = 'mod' OR {grade_items}.itemtype = 'manual')";

        // Start to add additional conditions to the query
        $addAnd = " and ";
        $params = array ();

        // Add Userids to the query
        if (strlen($userIds) > 0) {
            //add quotes to incoming list
            $UserIDList = explode(",", $userIds);
            $UserIDList = clean_param_array($UserIDList, PARAM_NOTAGS);
            
            list($inusersql, $userparams) = $DB->get_in_or_equal($UserIDList);
            $sql .= $addAnd . ' {user}.idnumber ' . $inusersql;
            $params = array_merge($params, $userparams);
        }

        // Add sectionIds to the query
        if (strlen($sectionIds) > 0) {
            //add quotes to incoming list
            $SectionIdList = explode(",", $sectionIds);
            $SectionIdList = clean_param_array($SectionIdList, PARAM_NOTAGS);
            list($incoursesql, $courseparams) = $DB->get_in_or_equal($SectionIdList);
            $sql .= $addAnd . ' {course}.idnumber ' . $incoursesql;
            $params = array_merge($params, $courseparams);
        }

        //Add the start date to the query
        if ($startDate != null) {
            $sql .= $addAnd . " {grade_grades}.timemodified > ? ";
            $params[] = $startDate;
        }

        //Add the end date to the query
        if ($endDate != null) {
            $sql .= $addAnd . " {grade_grades}.timemodified < ?  ";
            $params[] = $endDate;
        }

        //TODO: how to format this error
        if (count($params) < 1) {
            throw new Exception("Error, atleast one parameter is required");
        }
        //die(var_export($params, true));
        $data = $DB->get_records_sql($sql, $params);
        $returnData = array ();
        $updateData = array ();

        foreach ($data as $value) {
            $value = (array) $value;

            if ($value["needsupdate"] == 1) {
                //Keep a list of sectionIds to re-run after they are updated
                $updateData[] = $value["sourceid"];
                //Re-run the courseId
                grade_regrade_final_grades($value["courseid"]);
            } else {
                $returnData[] = $value;
            }
        }
        if (count($updateData) > 0) {
            $sectionIds = implode(",", $updateData);
            $newData = $this-> get_user_grades($userIds, $sectionIds, $startDate, $endDate, $RecursiveLevel++);
            $returnData = array_merge($newData, $returnData);
        }

       return $returnData;

    }
    
    
    

    /**
    * Get a user's activity logs
    *
    * @param string $userIds A comma separated list of (external) UserIds
    * @param string $sectionIds A comma separated list of (external) SectionIds
    * @param string $startDate Unix timestamp of the date to get logs AFTER
    * @param string $endDate Unix timestamp of the date to get logs BEFORE
    * @return array of objects
    */

    public function get_user_activity_logs($userIds = NULL, $sectionIds = NULL, $startDate = NULL, $endDate = NULL) {
        global $DB;
        
        //Use legacy logging if selected or standard logging
        $manager = get_log_manager();
		$allreaders = $manager->get_readers();
		if (isset($allreaders['logstore_legacy'])) {
    		$reader = $allreaders['logstore_legacy'];
    	} else {
        	$reader = reset($allreaders);
    	}
    	
    	// If reader is not a sql_internal_table_reader and not legacy store then return.
    	if (!($reader instanceof \core\log\sql_internal_table_reader) && !($reader instanceof logstore_legacy\log\store)) {
        	throw new Exception('Either standard or legacy logging needs to be set up.');
    	}
        
        //Note: A unique ID must always be the first column in the result.  Otherwise $DV->get_records_sql 
        //      will group up the results by whatever the first column is.
        $unionsql = " union ";
	    if ($reader instanceof logstore_legacy\log\store) {
	    	$timecol = 'time';
	    	$logtable = 'log';
	    	$sql = "select 
	                    l.id,
	                    l.course, 
	                    l.userid,
	                    l.time, 
	                    u.idnumber as UserID, 
	                    l.module, 
	                    c.idnumber as CourseID, 
	                    l.action
	
	                    from 
	                    {log} l,
	                    {user} u,
	                    {course} c
	
	                    where
	                    u.id = l.userid and
	                    l.course = c.id ";
		} else {
	    	$timecol = 'timecreated';
	    	$logtable = $reader->get_internal_log_table_name();
	    	$sql = "select 
	                    l.id,
	                    l.courseid as course, 
	                    l.userid,
	                    l.timecreated as time, 
	                    u.idnumber as UserID, 
	                    l.target, 
	                    c.idnumber as CourseID, 
	                    l.action,
	                    l.component
	
	                    from 
	                    {".$logtable."} l,
	                    {user} u,
	                    {course} c
	
	                    where
	                    u.id = l.userid and
	                    l.courseid = c.id ";
	    	$unionsql .= "select 
	                    l.id,
	                    l.courseid as course, 
	                    l.userid,
	                    l.timecreated as time, 
	                    u.idnumber as UserID, 
	                    l.target, 
	                    null as CourseID, 
	                    l.action,
	                    l.component
	
	                    from 
	                    {".$logtable."} l,
	                    {user} u
	
	                    where
	                    u.id = l.userid and
	                    l.courseid = 0 and 
	                    l.action in ('loggedin', 'loggedout') ";
	    }               

        // Start to add additional conditions to the query
        $addAnd = " and ";
        $params = array ();

        // Add Userids to the query
        if (strlen($userIds) > 0) {
            //add quotes to incoming list
            $UserIDList = explode(",", $userIds);
            $firstInList = true;
            $UserOutput = "";
            foreach ($UserIDList as $id) {
                if ($firstInList) {
                    $firstInList = false;
                } else {
                    $UserOutput .= ",";
                }

                $UserOutput .= "'" . clean_param($id, PARAM_TEXT) . "'";
            }

            $sql .= $addAnd . " u.idnumber in ( $UserOutput ) ";
            $unionsql .= $addAnd . " u.idnumber in ( $UserOutput ) ";
        }

        // Add sectionIds to the query
        if (strlen($sectionIds) > 0) {
            //add quotes to incoming list
            $SectionIdList = explode(",", $sectionIds);
            $firstInList = true;
            $SectionOutput = "";
            foreach ($SectionIdList as $id) {
                if ($firstInList) {
                    $firstInList = false;
                } else {
                    $SectionOutput .= ",";
                }

                $SectionOutput .= "'" . clean_param($id, PARAM_TEXT) . "'";
            }

            $sql .= $addAnd . " c.idnumber in ( $SectionOutput ) ";
        }

        //Add the start date to the query
        if ($startDate != null) {
            $sql .= $addAnd . " l.$timecol > ? ";
            $params[] = $startDate;
        }

        //Add the end date to the query
        if ($endDate != null) {
            $sql .= $addAnd . " l.$timecol < ?  ";
            $params[] = $endDate;
        }
        if ($logtable != 'log' && $startDate != null) {
            $unionsql .= $addAnd . " l.$timecol > ? ";
            $params[] = $startDate;
        }

        if ($logtable != 'log' && $endDate != null) {
            $unionsql .= $addAnd . " l.$timecol < ?  ";
            $params[] = $endDate;
        }

        //TODO: how to format this error
        if (count($params) < 1) {
            throw new Exception("Error, atleast one parameter is required");
        }

		if ($logtable != 'log') {
			$sql .= $unionsql;
		}
        $data = $DB->get_records_sql($sql, $params);

        $activityresponse = array ();
        foreach ($data as $item) {
            $dataresponse = array (
                'activity' => array (
                    'id' => $item->id,
                    'course' => $item->course,
                    'userid' => $item->userid,
                    'time' => $item->time,
                    'module' => $logtable == 'log'? $item->module : 
                    	($item->component==='core'? $item->target: str_replace('mod_', '', $item->component)),
                    'courseid' => $item->courseid,
                    'action' => $item->action
                )
            );
            $activityresponse[] = $dataresponse;

        }
        //die(var_export($activityresponse, true));
        return $this->response->standard($activityresponse);

    }

    /**
    * Get a user's activity logs
    *
    * @param string $sectionIds A comma separated list of (external) SectionIds
    * @param string $startDate Unix timestamp of the date to get logs AFTER
    * @param string $endDate Unix timestamp of the date to get logs BEFORE
    * @return array of objects
    */
    public function get_grade_items($sectionIds = NULL, $startDate = NULL, $endDate = NULL) {

        
        //Note: A unique ID must always be the first column in the result.  Otherwise $DV->get_records_sql 
        //      will group up the results by whatever the first column is.
        global $DB;
        $sql = "select 
                            {grade_items}.id,
                            {grade_items}.categoryid, 
                            {grade_items}.itemname, 
                            {grade_items}.itemtype, 
                            {grade_items}.itemmodule, 
                            {grade_items}.itemnumber, 
                            {grade_items}.grademax,
                            {course}.idnumber as SectionId
                        from 
                            {grade_items}, 
                            {course} 
                        where 
                            {course}.id = courseid and ({grade_items}.itemtype = 'mod' OR {grade_items}.itemtype = 'manual' or {grade_items}.itemtype='course')";

        //Add the additional criteria to the query

        $addAnd = " and ";
        $params = array ();
        //die(var_export($sectionIds,true));
        // Add sectionIds to the query
        if (strlen($sectionIds) > 0) {
            //add quotes to incoming list
            $SectionIdList = explode(",", $sectionIds);
            $firstInList = true;
            $SectionOutput = "";
            foreach ($SectionIdList as $id) {
                if ($firstInList) {
                    $firstInList = false;
                } else {
                    $SectionOutput .= ",";
                }

                $SectionOutput .= "'" . clean_param($id, PARAM_TEXT) . "'";
            }

            $sql .= $addAnd . " {course}.idnumber in ( $SectionOutput ) ";
        }

        //Add the start date to the query
        if ($startDate != null) {
            $sql .= $addAnd . " {grade_items}.timemodified > ? ";
            $params[] = $startDate;
        }
        //Add the end date to the query
        if ($endDate != null) {
            $sql .= $addAnd . " {grade_items}.timemodified < ? ";
            $params[] = $endDate;
        }

        //TODO: how to format this error
        if (count($params) < 1) {
            throw new Exception("Error, atleast one parameter is required");
        }

        $data = $DB->get_records_sql($sql, $params);

        $response = array ();
        foreach ($data as $item) {
            $dataresponse = array (
                'item' => array (
                    'id' => $item->id,
                    'categoryid' => $item->categoryid,
                    'itemtype' => $item->itemtype,
                    'itemname' => $item->itemname,
                    'grademax' => $item->grademax,
                    'sectionid' => $item->sectionid,
                    'itemmodule' => $item->itemmodule
                )
            );
            $response[] = $dataresponse;
        }

        return $this->response->standard($response);
    }
    
    
    //////////////////////////////NEW ILP API Ends Here/////////////////////////////////////////////////////////////////////
 
 

}