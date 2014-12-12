---
layout: post
title: "Database Cleanup"
date: 2014-08-22 16:25:06 -0700
comments: false
short: "Restore a database to its initial state"
license: BSD
version: 1.0
author: "ElkArte Contributors"
image: https://raw.githubusercontent.com/elkarte/Elkarte/development/themes/default/images/thumbnail.png
download: https://github.com/elkarte/tools/tree/master/databasecleanup
---

This utility can restore your database to an initial install state.

It examines your current database looking for added table, columns to tables and settings.  These
are items that are normally added by addons or other modifications.  Items that the script detects
as not part of the core package are listed with an option to remove them from your install.

This is intended to clean up a database after many addons have been added, tested, removed over time.

###Installation

Copy the files from the below link to the root of you forum installation.  Then from your browser
navigate to sitename/databasecleanup.php  From there follow the prompts.