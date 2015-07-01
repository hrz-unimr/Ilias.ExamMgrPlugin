<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once 'class.ilExamMgrExceptions.php';
require_once "class.ilExamMgrStudent.php";
require_once "class.ilExamMgrLDAP.php";


/**
 * Class to handle copy/pasted input from the "user upload" form.
 */
class ilExamMgrStudentParser {

    /**
     * Parse multiple lines from textarea to get student data.
     * Separator is guessed from first line (tab, comma, semicolon).
     * 
     * @param string $text Plain text from the input element.
     * @param int $matr Index of column for matriculation no (1 based, 0=n/a)
     * @param int $first Index of column for first name
     * @param int $last Index of column for last name
     * @param int $acc Index of column for account id
     * @return array ([array with data for verified students],
     *                [error messages for problem students])
     */
    public function parseText($text, $matr, $first, $last, $acc){
        $problems = array();
        $good = array();

        switch(count(array_filter(array($matr, $first, $last, $acc)))) {
        case 0:
            throw new Exception("No column selected");
        case 1:
            $sep = null;
            break;
        default:
            $separators = array("\t", ";", ",");
            foreach($separators as $s) {
                if(strpos($text, $s) !== FALSE) {
                    $sep = $s;
                    break;
                }
            }
            if(!isset($sep)) {
                throw new Exception("Could not guess Separator.");
            }
        }

        if(!($matr || $acc || ($first && $last))) {
            throw new Exception("Need at least matriculation no. or student account name or (both first and last name).");
        }

        foreach(array_count_values(array($matr, $first, $last, $acc)) as $val=>$num){
            if($val == 0) {
                continue;
            }
            if($num > 1) {
                throw new Exception("Duplicate assignment for column.");
            }
        }


        foreach(preg_split("/(\r\n|\n|\r)/", $text) as $line) {
            $parts = explode("#", $line);
            $line = $parts[0];
            if(trim($line) == '') {
                continue;
            }

            if(is_null($sep)) {
                $vals = array(trim($line));
            } else {
                $vals = array_map(trim, explode($sep, $line));
            }
            $matriculation = $matr ? $vals[$matr-1] : NULL;
            $firstName = $first ? $vals[$first-1] : "";
            $lastName = $last ? $vals[$last-1] : "";
            $account = $acc ? $vals[$acc-1]: "";

            try {
                $newStud = $this->checkLDAP($firstName, $lastName, $matriculation, $account);
                $good[] = $newStud;
            } catch (NameMismatchException $e) {
                $problems[] = "$line # Name mismatch: LDAP says {$e->lastName}, {$e->firstName}";
            } catch (MatriculationNotFoundException $e) {
                $problems[] = "$line # Matriculation no. not found";
            } catch (AccountNotFoundException $e) {
                $problems[] = "$line # Account not found";
            } catch (NameNotFoundException $e) {
                $problems[] = "$line # Name not found in LDAP";
            } catch (NameNotUniqueException $e) {
                $problems[] = "$line # Name is not unique in LDAP, provide matriculation no. or account";
            }

        }
        return array($good, $problems);
    }

    /**
     * Check if the given data matches a student account in LDAP, and fetch
     * additional data for that account.
     * If a matriculation number or account name is given, it is used in the
     * LDAP query and the user's name is compared with the passed name.
     * If no matriculation number is given, LDAP is queried with first and
     * last name to determine the corresponding account.
     * @throws MatriculationNotFoundException if matriculation number is not found in LDAP
     * @throws NameMismatchException if the passed name is different from LDAP
     * @throws NameNotUniqueException if the passed name is not unique
     * @throws NameNotFoundException if the passed name is not found
     * @return array with account data
     */
    public function checkLDAP($firstName, $lastName, $matriculation=NULL, $account="") {     // TODO: use array for args?

        $ldapSearcher = new ilExamMgrLDAP();

        if(!is_null($matriculation)) {
            $result = $ldapSearcher->searchStudentMatriculation($matriculation);
            if(count($result) == 0) {
                throw new MatriculationNotFoundException();
            }
            $result = $result->getFirst();
            $ldapFirstName = $result->firstName;
            $ldapLastName = $result->lastName;
            if(!$this->checkName($result, $firstName, $lastName)) {
                throw new NameMismatchException($ldapFirstName, $ldapLastName);
            }

        } else if(!empty($account)) {
            $result = $ldapSearcher->searchStudentAccount($account);
            if(count($result) == 0) {
                throw new AccountNotFoundException();
            } 
            $result = $result->getFirst();
            $ldapFirstName = $result->firstName;
            $ldapLastName = $result->lastName;
            if(!$this->checkName($result, $firstName, $lastName)) {
                throw new NameMismatchException($ldapFirstName, $ldapLastName);
            }

        } else { // search with lastname+firstname only
            $result = $ldapSearcher->searchStudentName($firstName, $lastName);
            if(count($result) == 0) {
                throw new NameNotFoundException();
            } elseif(count($result) != 1) {
                throw new NameNotUniqueException();
            }
            $ldapFirstName = $firstName;
            $ldapLastName = $lastName;
            $result = $result->getFirst();
        }
        $matriculation = $result->matriculation;
        $account = $result->account;
        $gender = $result->gender;
        return array($ldapFirstName, $ldapLastName, $matriculation, null, $account, $gender);
    }

    /**
     * Check if a LDAP result matches the name given in the input.
     *
     * @param object LDAP result
     * @param string $first First name.
     * @param string $last Last name.
     * @return bool false if a name is given but differs from the LDAP result's name.
     */
    private function checkName($result, $first, $last) {
        if(($last && $result->lastName != $last) || ($first && $result->firstName != $first)) {
               return false;
        }
        return true;
    }
}
