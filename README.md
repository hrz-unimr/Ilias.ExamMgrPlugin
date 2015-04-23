
Installation
------------

Requirements: php5-curl

* Install ILIAS (`git clone` of [ILIAS-eLearning/ILIAS](https://github.com/ILIAS-eLearning/ILIAS) (trunk or release_5-0) or any other method)
* Make ILIAS accessible via web server
* Setup ILIAS client: http://host:port/subdir/setup/setup.php
* Configure ILIAS
  * Test and Assessment: Unique user criteria = login (to transfer tests between ILIAS instances)
  * LDAP-auth
* `cd` to ILIAS webroot
* `mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr/`
* `cd Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr/`
* Put exam manager code there (git clone, unzip, ...), plugin.php has to  be in Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr/
* Get composer (https://getcomposer.org/download/, or just `curl -sS https://getcomposer.org/installer | php`)
* `php composer.phar install`
* In ILIAS, go to Administration->Plugins, Actions->Update, Actions->Activate, Actions->Configure (create a dummy room)
* Create category for e-assessment requests in repository
* Allow "User" (or any other role) to "Create" eAssessment manager objects in this category


