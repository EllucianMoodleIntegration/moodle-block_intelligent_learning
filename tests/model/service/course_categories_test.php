<?php

global $CFG;

require_once($CFG->dirroot.'/blocks/intelligent_learning/locallib.php');
require_once($CFG->dirroot.'/blocks/intelligent_learning/model/service/course_categories.php');
require_once($CFG->dirroot.'/blocks/intelligent_learning/model/response.php');

class blocks_intelligent_learning_model_service_course_categories_test extends advanced_testcase {
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_add() {
        global $DB;

        $data = array(
            'name'      => 'testphpunit',
            'parent'	=> 0,
            'depth'		=> 1,
        );

        $server   = $this->getMockForAbstractClass('mr_server_abstract', array(), '', false);
        $response = $this->getMockForAbstractClass('mr_server_response_abstract', array(), '', false);

        $service = new blocks_intelligent_learning_model_service_course_categories($server, $response);

        $reflection = new ReflectionMethod('blocks_intelligent_learning_model_service_course_categories', 'add');
        $reflection->setAccessible(true);

        $coursecategory = $reflection->invoke($service, $data);

        foreach ($data as $name => $value) {
            $this->assertTrue(property_exists($coursecategory, $name));

            $this->assertEquals($value, $coursecategory->$name);
        }
    }
    
    
//Commenting the test below since getMock() does not work with newer PHPUnit version
//and unable to mock the add protected function without errors & warnings    

    /*
    public function test_handle_create() {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<data>
    <datum action="create">
        <mapping name="name">testphpunitcategory</mapping>
    </datum>
</data>
XML;

        $server   = $this->getMockForAbstractClass('mr_server_abstract', array(), '', false);
        $response = $this->getMockBuilder('blocks_intelligent_learning_model_response')
            ->disableOriginalConstructor()
            ->getMock();

        $response->expects($this->once())
            ->method('coursecategories_handle')
            ->withAnyParameters();

        $service = $this->getMock('blocks_intelligent_learning_model_service_course_categories', array('add'), array($server, $response));

        $service->expects($this->once())
            ->method('add')
            ->withAnyParameters()
            ->will($this->returnValue(new stdClass()));

        $service->handle($xml);
    }
    */
}