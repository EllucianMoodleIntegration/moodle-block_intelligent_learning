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
 * ILP Integration block class definition
 *
 * @author Sam Chaffee
 * @package block_intelligent_learning
 **/
require($CFG->dirroot . '/local/mr/bootstrap.php');
class block_intelligent_learning extends block_list {
    public function init() {
        $this->title = get_string('pluginname', 'block_intelligent_learning');
    }

    public function applicable_formats() {
        return array('all' => false, 'site' => true, 'course-view' => true);
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $CFG, $USER, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        if (empty($this->instance) or empty($COURSE->idnumber)) {
            return $this->content;
        }

        $helper            = new mr_helper('blocks/intelligent_learning');
        $config            = get_config('blocks/intelligent_learning');
        $ilpurl            = $config->ilpurl;
        $attendancepid     = $config->attendancepid;
        $retentionalertpid = $config->retentionalertpid;
        $retentionlink     = $config->retentionalertlink;
        $stgradebookpid    = $config->stgradebookpid;
        $attendancelink    = $config->dailyattendancelink;
        $midtermgradecolumns = $config->midtermgradecolumns;

        // Check to make sure the settings have been set.
        if (empty($ilpurl)) {
            $this->content->items[] = html_writer::tag('span', get_string('needsadminsetup', 'block_intelligent_learning'), array('class' => 'error'));
            if (has_capability('moodle/site:config', context_system::instance())) {
                $this->content->items[] = html_writer::link("$CFG->wwwroot/admin/settings.php?section=blocksettingintelligent_learning", get_string('configure', 'block_intelligent_learning'));
            }
            return $this->content;
        }

        $backtodatatellink = get_string('backtodatatel', 'block_intelligent_learning');
        $this->content->items[] = html_writer::link($ilpurl, $backtodatatellink, array('title' => $backtodatatellink));

        $syscontext = context_system::instance();
        $pinned     = ($syscontext->id == $this->instance->parentcontextid) && ($this->instance->showinsubcontexts == 1);

        if (empty($pinned)) {
            $editcap = has_capability('block/intelligent_learning:edit', context_block::instance($this->instance->id));
        } else {
            $editcap = has_capability('block/intelligent_learning:edit', context_course::instance($COURSE->id));
        }

        if ($COURSE->id != SITEID and $editcap) {
            if ($retentionlink) {
                $retentionalertlink = get_string('retentionalert', 'block_intelligent_learning');
                $this->content->items[] = html_writer::link("$CFG->wwwroot/blocks/intelligent_learning/view.php?controller=retentionalert&action=edit&courseid=$COURSE->id", $retentionalertlink, array('title' => $retentionalertlink));
            }

            $gradebookapp = $config->gradebookapp;
            if ($gradebookapp == 'moodle') {
                if ($helper->gradeperiod()) {
                    if ($midtermgradecolumns > 0) {
                        $midtermgradelink = get_string('midtermgrades', 'block_intelligent_learning');
                        $this->content->items[] = html_writer::link("$CFG->wwwroot/blocks/intelligent_learning/view.php?controller=midtermgrades&action=edit&courseid=$COURSE->id", $midtermgradelink, array('title' => $midtermgradelink));
                    }

                    $finalgradelink = get_string('finalgrades', 'block_intelligent_learning');
                    $this->content->items[] = html_writer::link("$CFG->wwwroot/blocks/intelligent_learning/view.php?controller=finalgrades&action=edit&courseid=$COURSE->id", $finalgradelink, array('title' => $finalgradelink));
                }
                if (!empty($config->showlastattendance)) {
                    // Last date of attendance link.
                    $lastdatelink = get_string('lastattendance', 'block_intelligent_learning');
                    $this->content->items[] = html_writer::link("$CFG->wwwroot/blocks/intelligent_learning/view.php?controller=lastattendance&action=edit&courseid=$COURSE->id", $lastdatelink, array('title' => $lastdatelink));
                }

            } else { // ...$gradebookapp is 'ilp'.
                $ilpgradeslink = get_string('ilpgradebook', 'block_intelligent_learning');
                $efa = base64_encode("A.FACULTY.ID=$USER->idnumber&A.GRADEBOOK.ID=$COURSE->idnumber");
                $this->content->items[] = html_writer::link("$ilpurl/pages/WebAdvisor.aspx?title=Gradebook&type=p&pid=$stgradebookpid&EFA=$efa", $ilpgradeslink, array('title' => $ilpgradeslink, 'target' => '_blank'));
            }

            // Attendance link.
            if (!empty($attendancelink)) {
                $attendancelink = get_string('attendancedaily', 'block_intelligent_learning');
                $efa = base64_encode("A.COURSE.SECTIONS.ID=$COURSE->idnumber");
                $this->content->items[] = html_writer::link("$ilpurl/pages/WebAdvisor.aspx?title=Daily+Attendance&type=p&pid=$attendancepid&EFA=$efa", $attendancelink, array('title' => $attendancelink, 'target' => '_blank'));
            }
        }

        return $this->content;
    }

    /**
     * Overridden to prevent output when editing is turned on and course idnumber
     * is not set.
     *
     * @global object $COURSE
     * @param string $output
     * @return mixed block_contents
     */
    public function get_content_for_output($output) {
        global $COURSE;

        if (empty($COURSE->idnumber) && ($COURSE->id != SITEID)) {
            return false;
        }
        return parent::get_content_for_output($output);
    }
}