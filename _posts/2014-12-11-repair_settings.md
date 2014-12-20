---
layout: post
title: "Repair Settings"
date: 2014-12-10
comments: false
short: "Detect and correct common errors in your site settings"
license: BSD
version: 1.0
author: "ElkArte Contributors"
thumbnail: /assets/images/repair/thumbnail.jpg
download: https://github.com/elkarte/tools/tree/master/repair_settings
images:
  - Critical Settings: /assets/images/repair/critical.jpg
  - Database Settings: /assets/images/repair/database.jpg
  - Paths: /assets/images/repair/paths.jpg
  - Overall: /assets/images/repair/repair_settings.jpg
  - Theme Settings: /assets/images/repair/themes.jpg
---

##Installation
Download the files using the below link.  Then add them to the root directory of your forum (where Settings.php can be found). 
Navigate with your browser to your usual forum URL, but instead of yoursitename/index.php, direct your browser to yoursitename/repair_settings.php.

**NOTICE:** The repair_settings.php tool displays sensitive information about your server, namely the database user and password. Make sure that you delete it once you have finished the maintenance!

##What it Does
In most cases, the Repair Settings Tool can detect the correct value for each of the fields and settings listed below. The tool will display a link under each item with a recommended value; clicking these links will update the corresponding fields with the recommended value. If you want the recommended value to be used for all of the settings on the page, click on the link Restore all settings at the bottom of the page.

###Critical Settings
This section contains settings that can often be a source of problems on your fourm.

*  Maintenance Mode - You can choose to turn maintenance mode on or off for your forum.
*  Language File - This changes the language of your forum.
*  Cookie Name - This changes the name of the cookie that your forum creates for the user's browser.
*  Queryless URLs - Turns the use of queries in the forum's URLs on and off (../index.php/topic,1.0.html compared to ../index.php?topic=1.0).
*  Output Compression - Turns output compression on and off.
*  Compress css/js - Turns css/js compilation on and off.
*  Database driven sessions - Sets whether sessions are managed by the database.
*  Set ElkArte default theme as overall forum default for all users.

###Database Settings
This section includes all of the settings that are needed to connect to the database of your forum:

*  Server
*  Database name
*  Username
*  Password
*  SSI Username (optional)
*  SSI Password (optional)
*  Table prefix
*  Connection Type (Standard or Persistent)

###Paths & URLs
In this section, you can validate that the directory paths and URLs are set correctly:

*  Forum URL
*  Forum Directory
*  Sources Directory
*  Cache Directory
*  Attachment Directory
*  Avatar URL
*  Avatar Directory
*  Smileys URL
*  Smileys Directory

###Paths & URLs for Themes

This section enables you to check that the directory paths and URLs are correct for the default theme as well as for any other themes that you have installed.

The Repair Settings Tool will allow you to keep your custom theme as default and not reset to the default theme, unless you select the option Set ElkArte default theme as overall forum default for all users, which is found in the Critical Settings as mentioned above.

###Options

The following options are available:

*  Restore all settings - This changes all the settings to the recommended values, which saves you the effort of clicking on each individual link for the recommended values. There is no guarantee that the tool will detect all of the correct settings, so you should use this button with caution.
*  Remove all hooks - If addons have installed hooks and a clean install is desired, clicking Remove all hooks will ensure that all addons have all hooks removed/disabled. Do not use this button unless you know what you are doing as it will disable all addons that use hooks.