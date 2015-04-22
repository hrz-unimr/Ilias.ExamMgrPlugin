<#1>
<?php
$fields = array(
	'obj_id' => array(
		'type' => 'integer',
		'length' => 4,
		'notnull' => true
	),
	'exam_date' => array(
		'type' => 'date',
		'notnull' => true
	),
	'exam_time' => array(
		'type' => 'time'
    ),
    'num_students' => array(
        'type' => 'integer',
        'length' => 4
    ),
    'status' => array(
        'type' => 'integer',
        'length' => 1
    ),
    'department' => array(
        'type' => 'text',
        'length' => 100
    ),
    'ticket_id' => array(
        'type' => 'integer',
        'length' => 4
    )
);

$ilDB->createTable("rep_robj_xemg_data", $fields);
$ilDB->addPrimaryKey("rep_robj_xemg_data", array("obj_id"));

$fields = array(
    'id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'exam_obj_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'firstname' => array(
        'type' => 'text',
        'length' => 100
    ),
    'lastname' => array(
        'type' => 'text',
        'length' => 100
    ),
    'matriculation' => array(
        'type' => 'text',
        'length' => 10,
        'notnull' => true
    ),
    'ldapuid' => array(
        'type' => 'text',
        'length' => 20,
        'notnull' => true
    ),
    'gender' => array(
        'type' => 'text',
        'length' => 1
    )
);

$ilDB->createTable('rep_robj_xemg_students', $fields);
$ilDB->addPrimaryKey('rep_robj_xemg_students', array("matriculation", "exam_obj_id"));
$ilDB->createSequence('rep_robj_xemg_students');

$fields = array(
    'setting' => array(   // 'key' is reserved word
        'type' => 'text',
        'length' => 100,
        'notnull' => true
    ),
    'value' => array(
        'type' => 'text',
        'length' => 100,
        'notnull' => true
    )
);

$ilDB->createTable('rep_robj_xemg_settings', $fields);

$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_host", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_host_web", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_secure", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_user", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_pass", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_client", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_path", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_apikey", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("assessment_apisecret", "")');


$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("rt_user", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("rt_pass", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("rt_path", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("rt_queue", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("rt_disabled", "")');

$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("ldap_host", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("ldap_port", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("ldap_pass", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("ldap_binddn", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("ldap_basedn_staff", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("ldap_basedn_stud", "")');

$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("smtp_host", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("smtp_port", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("smtp_from", "")');

$fields = array(
    'exam_obj_id' => array(
        'type' => 'integer',
        'length' => 4
    ),
    'timestamp' => array(
        'type' => 'timestamp'
    ),
    'username' => array(    // user is reserved word
        'type' => 'text',
        'length' => 20,
        'notnull' => true
    ),
    'entry' => array(
        'type' => 'text',
        'length' => 1000,
        'notnull' => true
    )
);

$ilDB->createTable('rep_robj_xemg_log', $fields);

$fields = array(
    'id' => array('type' => 'integer', 'length' => 4),
    'obj_id' => array('type' => 'integer', 'length' => 4),
    'begin_ts' => array('type' => 'timestamp'),
    'end_ts' => array('type' => 'timestamp'),  // end is reserved word.
    'type' => array('type' => 'integer', 'length' => 1),
    'title' => array('type' => 'text', 'length' => 100),
    'room' => array('type' => 'integer', 'length' => 4)
);

$ilDB->createTable('rep_robj_xemg_runs', $fields);
$ilDB->addPrimaryKey('rep_robj_xemg_runs', array('id'));
$ilDB->createSequence('rep_robj_xemg_runs');


$fields = array(
    'exam_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
	'name' => array(
		'type' => 'text',
		'length' => 100,
		'notnull' => true
	),
	'email' => array(
        'type' => 'text',
        'length' => 100,
        'notnull' => true
	),
    'ldap_uid' => array(
        'type' => 'text',
        'length' => 10
    )
);

$ilDB->createTable("rep_robj_xemg_orga", $fields);
$ilDB->addPrimaryKey("rep_robj_xemg_orga", array("exam_id", "name"));

$fields = array(
    'run_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
	'student_id' => array(
		'type' => 'integer',
		'length' => 4,
		'notnull' => true
	)
);

$ilDB->createTable("rep_robj_xemg_stud_run", $fields);
$ilDB->addPrimaryKey("rep_robj_xemg_stud_run", array("run_id", "student_id"));

$fields = array(
    'run_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
	'student_id' => array(
		'type' => 'integer',
		'length' => 4,
		'notnull' => true
	)
);


$fields = array(
    'id' => array(
        'type' => 'integer',
        'length' => 4
    ),
    'exam_obj_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
	'remote_crs_ref_id' => array(
		'type' => 'integer',
		'length' => 4,
		'notnull' => true
    ),
    'password' => array(
        'type' => 'text',
        'length' => 20
    )
);

$ilDB->createTable("rep_robj_xemg_rem_crs", $fields);
$ilDB->addPrimaryKey("rep_robj_xemg_rem_crs", array("exam_obj_id", "remote_crs_ref_id"));
$ilDB->createSequence("rep_robj_xemg_rem_crs");

$fields = array(
    'id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'name' => array(
        'type' => 'text',
        'length' => 100,
        'notnull' => true
    ),
    'capacity' => array(
        'type' => 'text',
        'length' => 100,
        'notnull' => true
    )
);

$ilDB->createTable('rep_robj_xemg_rooms', $fields);
$ilDB->addPrimaryKey('rep_robj_xemg_rooms', array("id"));
$ilDB->createSequence('rep_robj_xemg_rooms');

$fields = array(
    'id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'exam_obj_id' => array(
        'type' => 'integer',
        'length' => 4
    ),
    'local_ref_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'remote_ref_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'remote_crs_ref_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    )
);

$ilDB->createTable('rep_robj_xemg_tests', $fields);
$ilDB->addPrimaryKey('rep_robj_xemg_tests', array("id"));
$ilDB->createSequence('rep_robj_xemg_tests');

?>

<#2>
<?php

$ilDB->addTableColumn('rep_robj_xemg_runs', 'course', array("type" => "integer", "length" => 4));
?>

<#3>
<?php

$ilDB->addTableColumn('rep_robj_xemg_stud_run', 'xferd_ldap', array("type"=>"boolean"));
$ilDB->addTableColumn('rep_robj_xemg_stud_run', 'xferd_oneway', array("type"=>"boolean"));
?>

<#4>
<?php

$ilDB->addTableColumn('rep_robj_xemg_data', 'duration', array("type" => "integer", "length" => 4));
$ilDB->addTableColumn('rep_robj_xemg_data', 'institute', array("type" => "text", "length" => 50));
?>

<#5>
<?php
// was used for testing
// ilUtil::sendInfo("Please update REST-Plugin component as well!");
?>

<#6>
<?php
// was used for testing
//ilUtil::sendInfo("Please update REST-Plugin component as well!", true);
?>

<#7>
<?php
$ilDB->modifyTableColumn('rep_robj_xemg_settings', 'value', array("length" => 1000));
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("cal_user", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("cal_pass", "")');
$ilDB->manipulate('INSERT INTO rep_robj_xemg_settings (`setting`, `value`) VALUES ("cal_url", "")');
?>

<#8>
<?php

$ilDB->addTableColumn('rep_robj_xemg_data', 'exam_title', array("type" => "text", "length" => 50));
?>
