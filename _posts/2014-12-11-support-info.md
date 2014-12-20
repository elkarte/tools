---
layout: post
title: "Support Info"
date: 2014-12-10
comments: false
short: "Allows read-only reference to relevant settings within an ElkArte installation"
license: BSD
version: 1.0
author: "ElkArte Contributors"
thumbnail: /assets/images/info/thumbnail.jpg
download: https://github.com/elkarte/tools/tree/master/repair_settings
images:
  - System Info: /assets/images/info/sysinfo.jpg
  - Filecheck Version: /assets/images/info/filecheck.jpg
  - Errorlog Output: /assets/images/info/errorlog.jpg
  - Database Check: /assets/images/info/dbcheck.jpg
  - Installed Addons: /assets/images/info/addons.jpg
---

##Installation
Download the files using the below link.  Then add them to the root directory of your forum (where Settings.php and SSI.php can be found). 
Navigate with your browser to your usual forum URL, but instead of yoursitename/index.php, direct your browser to 
yoursitename/status.php

###Why should I use elkinfo.php?
It is designed to allow an admin, or an authorized user, to view key settings, server information, and statistics in a 
live environment without needing to allow an individual admin access. elkinfo.php will not allow any changes to be made to settings 
or information through its interface.

If you are logged in as an administrator, you should immediately see the information the script provides. Please note the password at 
the top. You are able to change the password by selecting the 'regenerate' button next to the password, and it will invalidate 
the prior password and place a new one in its place. You simply give the person requesting the information from the script a link to 
the script, and the password, and they will be able to see the information.

If you are not an administrator, you will see a password prompt. If you were provided a password for the script, enter it here and you 
will be able to see the information output by the script. If you were not provided that password, you will either need to request the 
password, or request temporary admin access.

###When should I use the script
elkinfo.php should be used when requesting support from a community member. The script is designed to streamline 
the time taken in researching settings and troubleshooting, in an attempt to improve the quality of our support efforts.

###Is elkinfo.php secure and safe to use?
As mentioned, elkinfo.php is completely read only. No changes can be made to your setup through it. It should be regarded with 
the security of a phpinfo.php file as it provides a detailed view of your server settings, but no passwords, etc are ever revealed.