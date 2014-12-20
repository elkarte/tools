---
layout: post
title: "Database Cleanup"
date: 2014-06-28
comments: false
short: "Restore a database to its initial state"
license: BSD
version: 1.0
author: "ElkArte Contributors"
thumbnail: /assets/images/dbclean/thumbnail.jpg
download: https://github.com/elkarte/tools/tree/master/databasecleanup
images:
  - Extra columns example: /assets/images/dbclean/extra_columns.jpg
  - Extra settings example: /assets/images/dbclean/extra_settings.jpg
  - Extra tables example: /assets/images/dbclean/extra_tables.jpg
---

This utility can restore your database to an initial install state.

It examines your current database looking for added tables, columns added to default tables and settings.  
These are items that are normally added by addons or other modifications.  Items that the script detects
as not part of the core package are listed with an option to remove them from your install.

This is intended to clean up a database after many addons have been added, tested, removed over time.

Note the package is **NOT** aware of what addons you have installed, so remove tables / columns / settings 
with care.  Only remove what you know is no longer installed on your system and that you no longer want to 
use.  

The script will not run until you confirm (promise) that you have backed up your database since the changes it
makes are *permanent* and *irreversible*.

###Installation

Copy the files from the below link to the root of you forum installation.  Then from your browser
navigate to sitename/databasecleanup.php  From there follow the prompts.