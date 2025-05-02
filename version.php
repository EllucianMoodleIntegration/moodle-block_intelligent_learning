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
 * ILP Integration version file
 *
 * @author Sam Chaffee
 * @author Ellucian
 * @package block_intelligent_learning
 *
 **/
    
$plugin->version = 2025013001;
$plugin->requires = 2015111610;	// Moodle 3.0.10 is required, PHP7 required
$plugin->component = 'block_intelligent_learning';
$plugin->release   = '3.2.9 (Build: 2025013001)';
$plugin->dependencies = array(
    'local_mr' => 2010090201,
    'mod_assign' => ANY_VERSION,
    'mod_quiz' => ANY_VERSION,
    'mod_lesson' => ANY_VERSION
);
$plugin->maturity  = MATURITY_STABLE;
