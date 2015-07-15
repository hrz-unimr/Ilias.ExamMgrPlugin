
Installation
------------

Requirements: php5-curl, [ILIAS REST-Plugin v0.8](https://github.com/hrz-unimr/Ilias.RESTPlugin) on assessment ILIAS instance.

* Install ILIAS (`git clone` of [ILIAS-eLearning/ILIAS](https://github.com/ILIAS-eLearning/ILIAS) (trunk or release_5-0) or any other method)
* Make ILIAS accessible via web server
* Setup ILIAS client: http://host:port/subdir/setup/setup.php
* Configure ILIAS
  * Test and Assessment: Unique user criteria = login (to transfer tests between ILIAS instances)
  * LDAP-auth
* Copy the `Customizing` folder from this repo in the ILIAS web root and `cd` into `Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr`
* Get composer (https://getcomposer.org/download/, or just `curl -sS https://getcomposer.org/installer | php`)
* `php composer.phar install`
* In ILIAS, go to Administration->Plugins, Actions->Update, Actions->Activate, Actions->Configure (create a dummy room)
* Create category for e-assessment requests in repository
* Allow "User" (or any other role) to "Create" eAssessment manager objects in this category
* Install [ILIAS REST-Plugin v0.8](https://github.com/hrz-unimr/Ilias.RESTPlugin) on the assessment ILIAS instance.
* Copy RESTExtension/routes/routes.php to the REST plugin's extensions directory (in a new folder, e.g. .../RESTController/extensions/examPlugin/routes/routes.php)
* Configure a REST client (Administration->Plugins->REST->fancy anguar.js interface) 
  * ClientCredentials grant type, root's user id (or a dedicated user who can create courses, add users, enroll users to courses, import tests)
  * Route permissions:

          POST	/examPlugin/putTest/:targetRefId
          GET	/examPlugin/clean_accounts/:test_ref_id
          GET	/examPlugin/refIdByPath/:path+
          GET	/examPlugin/getTest/:ref_id
          GET	/admin/repository/categories/:ref_id
          POST	/v1/courses
          GET	/v1/courses/:ref_id
          POST	/v1/courses/enroll
          POST	/v1/users
* Configure ExamPlugin (Administration->Plugins->examMgr), enter the credentials for the REST access and other data (site specific)
