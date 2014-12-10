#### ElkArte Utility Scripts

This repository contains a several useful scripts for ElkArte, such as install/upgrade, repair scripts, database cleaning, etc.

All scripts in this repository are under BSD 3-clause license, unless specified otherwise.

Most of the scripts are developed or maintained by [emanuele45](https://github.com/emanuele45).

#### Description

* **databasecleanup**: Analyses a database and compares it to a fresh install.  Displays added settings and columns with options to remove.
* **populate**: Can be used to populate a forum with dummy users / topics / posts (useful for testing), originally written by SlammedDime http://code.mattzuba.com/populator
* **repair_settings**: Use to detect the correct value for a number of important fields and settings on your forum.  Useful to fix broken installs.
* **elkinfo**: A script that will provide detailed information to help with support issues. Output includes details of the system, PHP, file versions, database, error log, addons installed.  Can provide password access to output for trusted users.
* **status**: A script that can be used to analyse mySQL database performance and provide suggestions on how to improve settings (experimental).
* **sprite_gen**: Used to generate theme sprites with your own set of images.  Creates admin, board, expcol, header, quick buttons and topic icon sprites
* **install_script**: A template that can be used to create manual installation scripts for mods. At the moment the hook part is fully working, the database part is still WIP.
* **ban_script**: A script that allows perform multiple user banning at once. You can provide a list of usernames that you want to ban or you can ask the script to scan a board you have collected all the users you want to ban in (the name must be the subject of the topic).
* **fix_packages**: After a large upgrade (to cleanup forum) the mods are still marked as installed, with this script you can invert that state.

#### Repo

Feel free to fork this repository and make your desired changes.

Please see the [Developer's Certificate of Origin](https://github.com/elkarte/tools/blob/master/DCO.txt) in the repository, by this 
you acknowledge that you can, and do, license your code under the license of the project.

#### How to contribute:
* Fork the repository. If you are not used to Github, please check out [fork a repository](http://help.github.com/fork-a-repo).
* Branch your repository, to commit the desired changes.
 * An easy way to do so, is to define an alias for the git commit command, which includes -s switch (reference: [How to create Git aliases](http://githacks.com/post/1168909216/how-to-create-git-aliases))
* Send us a pull request.
 * This implies that your submission is done under the license of the project.