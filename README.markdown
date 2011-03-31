pushToCloudFiles - Command Line Script for wrapping php-cloudfiles.
========================================================================
Requirements
------------------------------------------------------------------------
    These requirements are from php-cloudfiles. You must be able to run
    this api. 
    External Library: https://github.com/rackspace/php-cloudfiles
    [mandatory] PHP version 5.x (developed against 5.2.0)
    [mandatory] PHP's cURL module
    [mandatory] PHP enabled with mbstring (multi-byte string) support
    [suggested] PEAR FileInfo module (for Content-Type detection)'

Install
------------------------------------------------------------------------
Clone the repository: 
    git clone git://github.com/Adiamante/pushToCloudFiles.git pushToCloudFiles
Initialize the submodules:
    cd pushToCloudFiles
    git submodule init
    git submodule update
Edit pushToCloudFiles.php
    $username='' to your cloud files username
    $apiKey='' your cloud files api key
    Save and close it out.
Done!

Examples
------------------------------------------------------------------------
Running this is incredibly simple. Simply pass a bucket name, directory
source path.
    php pushToCloudFiles.php --bucket=dailyBackup --source=/backup/daily/
This will trigger a push of all files in the source directory to the
bucket that was defined.
If you would like to see what it's doing and a list of files just pass
-v argument
    php pushToCloudFiles.php --bucket=dailyBackup --source=/backup/daily/ -v
You can see progress bars and a list of each file that is being pushed.

Known Restrictions
------------------------------------------------------------------------
There is currently a 5GB limit in cloud files. This script does not split
up large files yet. It's slated for a release later.
It overwrites files in a bucket if you specify an already existing bucket.
This is by design and probably won't change. I hope to add a way to upload
to another bucket then trigger a swap on them to allow for easier backups
or replacement of buckets.


