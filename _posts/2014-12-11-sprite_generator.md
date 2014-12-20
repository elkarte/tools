---
layout: post
title: "Sprite Generator"
date: 2014-08-05
comments: false
short: "Creates the default theme sprites with your choice of icons"
license: BSD
version: 1.0
author: "ElkArte Contributors"
thumbnail: /assets/images/spritegen/thumbnail.jpg
download: https://github.com/elkarte/tools/tree/master/spritecreator
images:
  - Header Sprite: /assets/images/spritegen/header.jpg
---

This utility will simplify the creation of ElkArte sprites for use in custom themes or if you just
want to changing the ones in the default theme.  This allows you to use the existing CSS and just 
update the default sprite images with new ones that match the default CSS rules.

### Installation

*  Copy the archive to an directory where your web site can access it, the base of your forum is fine.
*  You will find several directory that are named after the sprite files and they must be named like that.
*  In each sub directory, you will find the icons used in the sprite.  If you don't like one, replace it with yours but you must keep the name the same.  This is **important**, the name must be the same.
*  Keep doing step 3 until you are happy with your icon collection
*  Run sprite_gen.php, select from the drop down list the sprite you want to create.
*  If all goes well, you will be presented with the new sprite image in your window and that image is saved in the sub directory.
*  Copy the new sprite file to your theme (overwrite the old) and enjoy.  The existing CSS will "just work"

### Example

*  You unzip to your root directory (where SSI.php is located)
*  You don't like the board icons, so navigate to /board_icons
*  Overwrite the icons in that folder (on.png, on2,png, redirect.png, etc etc) to the images you want (maintain the same names !)
*  In your browser go to ```www.myforum.com/sprite_gen.php```
*  Select "board icon sprites" in the drop down and press create
*  You should see and image of the new sprite AND board_icons.php will have been updated in /board_icons (the directory in step 1)
*  Copy that file to the correct directory in you theme and enjoy the new icons

####Sprites

This utility can currently create the following sprites

*  Header Sprite
*  Board icons Sprite
*  Quick buttons Sprite
*  Expand Collapse Sprite
*  Admin Sprite
*  Topic Sprite