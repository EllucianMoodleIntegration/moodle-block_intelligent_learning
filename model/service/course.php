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
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/course/format/lib.php');
require_once("$CFG->dirroot/enrol/meta/locallib.php");

/**
 * Course Service Model
 *
 * @author Mark Nielsen
 * @author Sam Chaffee
 * @author Ellucian
 * @package block_intelligent_learning
 */
class blocks_intelligent_learning_model_service_course extends blocks_intelligent_learning_model_service_abstract {
    /**
     * Synced course fields
     *
     * @var array
     */
    private $coursefields = array(
        'shortname',
        'category',
        'fullname',
        'idnumber',
        'summary',
        'format',
        'showgrades',
        'startdate',
        'numsections',
        'visible',
        'groupmode',
        'groupmodeforce',
        'enddate',
        'automaticenddate',
    );

    /**
     * Course Provisioning
     *
     * @param string $xml XML with course data
     * @throws Exception
     * @return string
     */
    public function handle($xml) {
        global $DB;

        list($action, $data) = $this->helper->xmlreader->validate_xml($xml, $this);

        if($action == 'course_grade_user' && empty($data['id'])){
			throw new Exception('No id passed, required');
		}else if (empty($data['idnumber']) && $action != 'course_grade_user') {
            throw new Exception('No idnumber passed, required');
        }

        // Try to get the course that we are operating on.
        $course = false;
        if(!empty($data['idnumber'])){
            if ($courseid = $DB->get_field('course', 'id', array('idnumber' => $data['idnumber']))) {
                $course = course_get_format($courseid)->get_course();
                // Validate if the course object is valid
                if (empty($course->idnumber) || empty($course->id)) {
                    $course = false; // invalidate the course
                }
            }
        }

        switch($action) {
            case 'course_grade_user':          
                return $this->course_grade_user($data);
            break;
            case 'create':
            case 'add':
            case 'update':
            case 'change':
                if ($course) {
                    $this->update($course, $data);
                } else {
                    if (empty($data['shortname'])) {
                        throw new Exception('No shortname passed, required when creating a course');
                    }
                    if (empty($data['fullname'])) {
                        throw new Exception('No fullname passed, required when creating a course');
                    }
                    $course = $this->add($data);
                }
                break;
            case 'remove':
            case 'delete':
            case 'drop':
                if ($course) {
                     /*
                        If a crosslist is getting deleted then make the child section visible
                        and then delete the crosslist, the crosslist has a enrol status of meta in enrol table for child section mapping to the parent crosslist course
                    */
                    $courseRecord = $DB->get_record('course', array('idnumber' => $course->idnumber), '*', MUST_EXIST);
                    $childSections = $DB->get_records('enrol', array('enrol' => 'meta', 'courseid' => $courseRecord->id), null, '*');
                    foreach ($childSections as $childSection) {
                        $sectionRecord = $DB->get_record('course', array('id' => $childSection->customint1), '*', MUST_EXIST);
                        $sectionRecord->visible = true;
                        $DB->update_record('course', $sectionRecord);
                    }
                    if (!@delete_course($course, false)) {
                        throw new Exception("Failed to delete course (idnumber = $course->idnumber)");
                    }
                } else {
                    throw new Exception('Course does not exist, cannot proceed with delete operation.');
                }
                break;
            default:
                throw new Exception("Invalid action found: $action.  Valid actions: create, update, change and remove");
        }
        return $this->response->course_handle($course);
        
        // throw new Exception('No shortname passed, required when creating a course'.$abc->id);
    }

    protected function course_grade_user($data){
        try {
            $id = $data['id'];
            $pagenumber = isset($data['pagenumber']) ? $data['pagenumber'] : 1;
            $perpage = isset($data['perpage']) ? $data['perpage'] : 50;
            $offset = $pagenumber*$perpage - $perpage;
            global $DB;    

            $teacher = [];
            $course = [];
            $user = [];
            $roles = [];
           
            
            $courseSql = "SELECT cm.id as courseid,
                            cm.shortname as courseshortname,
                            cm.category as coursecategoryid,
                            cm.sortorder as coursecategorysortorder,
                            cm.fullname as coursefullname,
                            cm.fullname as coursedisplayname,
                            cm.idnumber as courseidnumber,
                            cm.summary as coursesummary,
                            cm.summaryformat as coursesummaryformat,
                            cm.format as courseformat,
                            cm.showgrades as courseshowgrades,
                            cm.newsitems as coursenewsitems,
                            cm.startdate as coursestartdate,
                            cm.enddate as courseenddate,
                            cm.maxbytes as coursemaxbytes,
                            cm.showreports as courseshowreports,
                            cm.visible as coursevisible,
                            cm.groupmode as coursegroupmode,
                            cm.groupmodeforce as coursegroupmodeforce,
                            cm.defaultgroupingid as coursedefaultgroupingid,
                            cm.timecreated as coursetimecreated,
                            cm.timemodified as coursetimemodified,
                            cm.enablecompletion as courseenablecompletion,
                            cm.completionnotify as coursecompletionnotify,
                            cm.lang as courselang 
                            from {course} as cm where cm.id = $id";
                $courseRecords = $DB->get_records_sql($courseSql);
                $context = context_course::instance($id);
               
                $usersSql = "SELECT  u.id as userid,
                                u.username as username,
                                u.firstname as userfirstname,
                                u.lastname as userlastname,
                                u.email as useremail,
                                u.department as userdepartment,
                                u.idnumber as useridnumber,
                                u.picture as userpicture,
                                u.picture as userpicture
                                from {enrol} as enrol
                                left join {user_enrolments} as ue on enrol.id = ue.enrolid 
                                left join {role_assignments} as ra on ra.userid = ue.userid 
                                left join {user} as u on u.id = ra.userid 
                                where enrol.courseid= $id and ue.enrolid = enrol.id ";
                $records = $DB->get_records_sql($usersSql);
               
                $gradeSql = "SELECT 
                        gg.finalgrade as  finalgrade,
                        gg.rawgrade as rawgrade,
                        gg.userid as userid,
                        gi.courseid
                        from {grade_items} as gi
                        left join {grade_grades} as gg on gg.itemid = gi.id
                        where gi.courseid = $id and gi.itemname IS NULL ";
                  
                $gradeRecords = $DB->get_records_sql($gradeSql);
               
                foreach($courseRecords as $record){
                    $course = array(
                        "id"=> $record->courseid,
                        "shortname"=> $record->courseshortname,
                        "categoryid"=> $record->coursecategoryid,
                        "fullname"=> $record->coursefullname,
                        "displayname"=>  $record->coursefullname,
                        "idnumber"=>  $record->courseidnumber,
                        "summary"=>  $record->coursesummary,
                        "summaryformat"=>  $record->coursesummaryformat,
                        "format"=>  $record->courseformat,
                        "showgrades"=>  $record->courseshowgrades,
                        "newsitems"=>  $record->coursenewsitems,
                        "startdate"=>  $record->coursestartdate,
                        "enddate"=>  $record->courseenddate,
                        "numsections"=>  $record->coursenumsections,
                        "maxbytes"=>  $record->coursemaxbytes,
                        "showreports"=>  $record->courseshowreports,
                        "visible"=>  $record->coursevisible,
                        "hiddensections"=>  $record->coursehiddensections,
                        "groupmode"=>  $record->coursegroupmode,
                        "groupmodeforce"=>  $record->coursegroupmodeforce,
                        "defaultgroupingid"=>  $record->coursedefaultgroupingid,
                        "timecreated"=>  $record->coursetimecreated,
                        "timemodified"=>  $record->coursetimemodified,
                        "enablecompletion"=>  $record->courseenablecompletion,
                        "completionnotify"=>  $record->coursecompletionnotify,
                        "lang"=>  $record->courselang,
                    );
                }
                
               
                $userId = '';
                $user = [];
                $courseCategoryId = $course["categoryid"];
                //sets exceededCategoryCutoff field in course if it exceeds the cutoff time
                if ($this->exceededCategoryGradeCutOff($courseCategoryId)) {
                    $course["exceededCategoryCutoff"] = true;
                }
                
                foreach($records as $record) {
                    $userId = $record->userid;
                    $roles = [];
                    $userRoles = get_user_roles($context, $userId, true);
                    foreach( $userRoles as $role){
                        $roles[] = array("role"=>array(
                            "roleid" => $role->id,
                            "name" => $role->name,
                            "shortname" => $role->shortname,
                            "sortorder" => $role->sortorder,
                        ));
                    }

                    $grade = $this->getGradeDetails($gradeRecords, $record->userid);
                    $grades = array(
                        "courseid" => $grade->courseid,
                        "grade" => $grade->finalgrade,
                        "rawgrade" => $grade->rawgrade
                    );

                    $user[] =array( "user" => array(
                        "id"=> $record->userid,
                        "username"=> $record->username,
                        "firstname"=> $record->userfirstname,
                        "lastname"=> $record->userlastname,
                        "fullname"=> $record->userfirstname." ".$record->userlastname,
                        "email"=> $record->useremail,
                        "department"=> $record->userdepartment,
                        "idnumber"=> $record->useridnumber,
                        "profileimageurlsmall"=> $record->userprofileimageurlsmall,
                        "profileimageurl"=> $record->userprofileimageurl,
                        'grades' => $grades,
                        'roles' => $roles
                    ));
                }
            
            return $this->response->standard(array('course'=> $course, 'enrollments' =>  $user ));
           
        } catch (Exception $e) {
            throw new Exception( $e->getMessage());
        }
    }

     /**
	 * @param courseCategoryId The course categoryId present in mdl_course_categories
     * returns boolean if the time exceeds the cutoff time
	 */
   public function exceededCategoryGradeCutOff($courseCategoryId) {
        $config = get_config('blocks/intelligent_learning', 'categorycutoff');
        $categoryArr = [];
        if (!empty($config)) {
            parse_str($config, $categoryArr);
            if (array_key_exists($courseCategoryId, $categoryArr)) {
                return (time() > $categoryArr[$courseCategoryId]);
            }
        }
    
        return false;
   }

    private function getGradeDetails($allGrades, $userId){
        foreach($allGrades as $grade){
            if($grade->userid == $userId){
                return $grade;
            }
        }
    }

    /**
     * Add a course
     *
     * @param array $data Course data
     * @throws Exception
     * @return object
     */
    protected function add($data) {
        global $DB;

        $course = array();
        foreach ($this->coursefields as $field) {
            if (isset($data[$field])) {
                $course[$field] = $data[$field];
            }
        }
        $course   = (object) $course;
        $defaults = array(
            'startdate'      => time() + 3600 * 24,
            'summary'        => '',
            'format'         => 'weeks',
            'guest'          => 0,
            'numsections'	 => 10,
            'idnumber'       => '',
            'newsitems'      => 5,
            'showgrades'     => 1,
            'groupmode'      => 0,
            'groupmodeforce' => 0,
            'visible'        => 1,
            'automaticenddate'	=> 1,
        );

        $courseconfigs = get_config('moodlecourse');
        if (!empty($courseconfigs)) {
            foreach ($courseconfigs as $name => $value) {
				$defaults[$name] = $value;
            }
        }

        // Apply defaults to the course object.
        foreach ($defaults as $key => $value) {
            if (!isset($course->$key) or (!is_numeric($course->$key) and empty($course->$key))) {
                $course->$key = $value;
            }
        }
        
        // If data contains a valid end date then disable the Automatic End Date setting
        if (isset($course->enddate) and is_numeric($course->enddate) and $course->enddate > 0) {
        	$course->automaticenddate = 0;
        }

        // Last adjustments.
        fix_course_sortorder();  // KEEP (Packs sort order).
        unset($course->id);
        $course->category    = $this->process_category($course);
        $course->timecreated = time();
        $course->shortname   = substr($course->shortname, 0, 100);
        $course->sortorder   = $DB->get_field('course', 'COALESCE(MAX(sortorder)+1, 100) AS max', array('category' => $course->category));

        if (isset($course->idnumber)) {
            $course->idnumber = substr($course->idnumber, 0, 100);
        }
		
		//call the library function to create the course since that will take care of creating the sections
		$createdCourse = create_course($course);
		$courseid = $createdCourse->id;

        // Check if this is a metacourse.
        if (isset($data["children"])) {
            $children = $data["children"];
            $crossliststartdate = $data["startdate"];
            $crosslistenddate = $data["enddate"];
            $metacourse = $this->process_metacourse($course, $children, $crossliststartdate, $crosslistenddate);
            
            //if parent course is assigned a valid end date, turn off auto end date setting
            if (!is_null($metacourse) and property_exists($metacourse, 'automaticenddate')) {
            	$course->automaticenddate = $metacourse->automaticenddate;
            }
        }

        // Save course format options.
        course_get_format($courseid)->update_course_format_options($course);

        // Create the context so Moodle queries work OK.
        context_course::instance($courseid);

        // Make sure sort order is correct and category paths are created.
        fix_course_sortorder();

        try {
            $course = course_get_format($courseid)->get_course();

            // Create a default section.
            course_create_sections_if_missing($course, 0);

            blocks_add_default_course_blocks($course);
        } catch (dml_exception $e) {
            throw new Exception("Failed to get course object from database id = $courseid");
        }

        return $course;
    }

    /**
     * Update a course
     *
     * @param object $course Current Moodle course
     * @param array $data New course data
     * @throws Exception
     * @return void
     */
    protected function update($course, $data) {
        global $DB;

        // Process category.
        if (isset($data['category'])) {
            $data['category'] = $this->process_category($data);
        }

        $update = false;
        $record = new stdClass;
        $modifySectionVisibility = get_config('blocks/intelligent_learning', 'modifysectionvisibility');
        $modifyCrosslistvisibility = get_config('blocks/intelligent_learning', 'modifycrosslistvisibility');

        //If the toggle Modify Section Visibility is No for section change-request then we delete the visible field from changeRequest($data)
        if (!isset($data["children"]) && $modifySectionVisibility == '0') {
            unset($data["visible"]); 
        }

        //If the toggle Modify Crosslist Visibility is No for crosslist change-request then we delete the visible field from changeRequest($data)
        if (isset($data["children"]) && $modifyCrosslistvisibility == '0') {
            unset($data["visible"]); 
        }

        foreach ($data as $key => $value) {
            if (!in_array($key, $this->coursefields)) {
                continue;
            }
            if ($key != 'id' and isset($course->$key) and $course->$key != $value) {
                switch ($key) {
                    case 'idnumber':
                    case 'shortname':
                        $record->$key = substr($value, 0, 100);
                        break;
                    default:
                        $record->$key = $value;
                        break;
                }
                $update = true;
            }
        }
        if ($update) {
            // Make sure this is set properly.
            $record->id = $course->id;
            $record->timemodified = time();

            try {
                $DB->update_record('course', $record);

                // Save course format options.
                course_get_format($course->id)->update_course_format_options($record, $course);
            } catch (dml_exception $e) {
                throw new Exception('Failed to update course with id = '.$record->id);
            }
        }
        // Check if this is a metacourse.
        if (isset($data["children"])) {
            $children = $data["children"];
            $crossliststartdate = $data["startdate"];
            $crosslistenddate = $data["enddate"];
            $this->process_metacourse($course, $children, $crossliststartdate, $crosslistenddate);
        }
    }

    /**
     * Process the category from the external database
     *
     * @param object|array $course External course
     * @param int $defaultcategory Default category if category lookup fails
     * @throws Exception
     * @return int
     */
    protected function process_category($course, $defaultcategory = null) {
        global $CFG, $DB;

        if (is_array($course)) {
            $course = (object) $course;
        }

        if (isset($course->category) and is_numeric($course->category)) {
            if ($DB->record_exists('course_categories', array('id' => $course->category))) {
                return $course->category;
            }
        } else if (isset($course->category)) {
            // Apply separator.
            $category   = trim($course->category, '|');
            $categories = explode('|', $category);

            $parentid = $depth = 0;
            foreach ($categories as $catname) {  // Meow!
            	if (empty($catname))
            		continue;
            		
                $depth++;

                //if ($category = $DB->get_record('course_categories', array('name' => $catname, 'parent' => $parentid))) {
                //    $parentid = $category->id;
                if ($coursecategories = $DB->get_records('course_categories', array('name' => $catname, 'parent' => $parentid))) {
                	$category = array_shift($coursecategories);
                    $parentid = $category->id;
                } else {
                    $category = new stdClass();
                    $category->name      = $catname;
                    $category->parent    = $parentid;
                    $category->sortorder = 999;
                    $category->depth     = $depth;

                    try {
                        $category->id = $DB->insert_record('course_categories', $category);
                    } catch (dml_exception $e) {
                        throw new Exception("Could not create the new category: $category->name");
                    }

                    $context = context_coursecat::instance($category->id);
                    $context->mark_dirty();

                    $parentid = $category->id;
                }
            }

            if (!empty($category) and strtolower($category->name) == strtolower(end($categories))) {
                // We found or created our category.
                return $category->id;
            }
        }

        if (!is_null($defaultcategory)) {
            return $defaultcategory;
        }
        return $CFG->defaultrequestcategory;
    }

    /**
     * Processes metacourse handling for the course and its children
     *
     * @param object|array $course External course
     * @param object|array $children List of child courses idnumbers
     * @throws Exception
     * @return int
     */
    protected function process_metacourse($course, $children, $crossliststartdate, $crosslistenddate) {
        global $CFG, $DB;
        $metacourse = null;
        try {
            if (isset($children)) {
                $parentfullname = "";
                $parentcategory = "";
                $parentshortname = "";
                $parentstartdate = $crossliststartdate;
                $parentenddate = $crosslistenddate;
                $parentautomaticenddate = 1;

                $childids = explode(',', $children);
                $enrol      = enrol_get_plugin('meta');

                // Make this a metacourse by adding enrollment entries for each of the child courses.
                $metacourse = $DB->get_record('course', array('idnumber' => $course->idnumber), '*', MUST_EXIST);

                $requestchildren = array();

                if (!empty($children)) {
                    // If children is set but empty, that means we are removing all children from the course; skip this.
                    foreach ($childids as $childidnumber) {
                        $child             = $DB->get_record('course', array('idnumber' => $childidnumber), '*', MUST_EXIST);
                        $existingchild     = $DB->get_record('enrol', array('enrol' => 'meta', 'courseid' => $metacourse->id, 'customint1' => $child->id));

                        $parentfullname .= ", " . $child->fullname;
                        $parentcategory = $child->category;
                        $parentshortname .= ", " . $child->shortname;
                        //Add latest child course end date to parent, if end date exists 
                        if (property_exists($child, 'enddate')) {
                        	
                        	//if auto end date setting not turned off already and start/end dates don't match
                        	//then turn setting off
			            	if ($parentautomaticenddate == 1 and (($child->enddate - $child->startdate) > (24*60*60))) {
			            		$parentautomaticenddate = 0; 
			            	}
                        }

                        // Only add if not a duplicate.
                        if (!isset($existingchild->id)) {
                            $eid        = $enrol->add_instance($metacourse, array('customint1' => $child->id));
                            // Hide child - users will only interact with the parent.
                            $child->visible = false;
                            $DB->update_record('course', $child);
                        }
                        array_push($requestchildren, $child->id);
                    }
                }

                // If there are any children that are no longer in the list, remove the meta-link.
                $currentchildren = array();
                $currentchildren = $DB->get_records('enrol', array('enrol' => 'meta', 'courseid' => $metacourse->id), null, '*');
                if (count($requestchildren) != count($currentchildren)) {
                    foreach ($currentchildren as $checkchild) {
                        if (!in_array($checkchild->customint1, $requestchildren)) {
                            // This child is not in the current list; remove the meta link.
                            //take the uncrosslistedChildSection and set its visibile field to true and update the record
                            $uncrosslistedChildSection = $DB->get_record('course', array('id' => $checkchild->customint1), '*', MUST_EXIST);
                            $uncrosslistedChildSection->visible = true;
                            $DB->update_record('course', $uncrosslistedChildSection);
                            $eid = $enrol->delete_instance($checkchild);
                        }
                    }
                }

                enrol_meta_sync($metacourse->id);

                // Update the course title, category and start date with the values from the children.
               
                if (!empty($parentfullname) && ($parentfullname != "")) {
                    if(empty($metacourse->fullname)){
                        $metacourse->fullname = ltrim($parentfullname, ", ");
                    }
                    if(empty($metacourse->shortname)){
                        $metacourse->shortname = ltrim(substr($parentshortname, 0, 100), ", ");
                    }
                    // $metacourse->fullname = ltrim($parentfullname, ", ");
                    // $metacourse->shortname = ltrim(substr($parentshortname, 0, 100), ", ");
                    $metacourse->startdate = $parentstartdate;
                    if ($metacourse->category == $CFG->defaultrequestcategory) {
                        $metacourse->category = $parentcategory;
                    }
        			if (property_exists($metacourse, 'enddate')) {
                    	$metacourse->enddate = $parentenddate;
                    	$metacourse->automaticenddate = $parentautomaticenddate;
                    }
                    $DB->update_record('course', $metacourse);
                }
            }
        } catch (Exception $e) {
            $errormessage = "Error adding child courses $children to metacourse $course->idnumber. " . $e->getMessage();
            debugging($errormessage);
            throw new Exception($errormessage);
        }
        
        return $metacourse;

    }
}