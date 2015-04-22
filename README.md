
Requirements: php5-curl

git clone of ILIAS-eLearning/ILIAS (trunk or release_5-0)
Make ILIAS accessible via web server
Setup ILIAS client: http://host:port/subdir/setup/setup.php
Configure ILIAS
* Test and Assessment: Unique user criteria = login
* LDAP-auth
cd to ILIAS'' webroot
mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr/
cd Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr/
Put exam manager code there (git clone, unzip, ...), plugin.php has to  be in Customizing/global/plugins/Services/Repository/RepositoryObject/ExamMgr/
Get composer (https://getcomposer.org/download/, curl -sS https://getcomposer.org/installer | php)
php composer.phar install
Go to Administration->Plugins, Actions->Update, Actions->Activate, Actions->Configure (create a dummy room)
Create category for e-assessment requests in repository
Allow "User" (or any other role) to "Create" eAssessment manager objects in this category

