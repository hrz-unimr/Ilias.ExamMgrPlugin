<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

// Some custom Exceptions to improve error handling/reporting.

class NameMismatchException extends Exception {
    public function __construct($firstName, $lastName) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        parent::__construct("Name $firstName $lastName does not match LDAP data.");
    }
}

class AccountNotFoundException extends Exception {
}

class MatriculationNotFoundException extends Exception {
}

class NameNotFoundException extends Exception {
}

class NameNotUniqueException extends Exception {
}

class HandledGuzzleException extends Exception {
}
