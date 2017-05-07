PHP connector for Rich Filemanager
========================

This package is the part of [RichFilemanager](https://github.com/servocoder/RichFilemanager) project.

Requires PHP >= 5.4.0


Introduction
------------

PHP connector provides a flexible way to manage you files at different king of storages.
There are 2 storages supported out of the box:

- [Local filesystem storage](#local-filesystem-storage)
- [AWS S3 storage](#aws-s3-storage)

Configuration details for each are described below.
You create implementation for any other storage that you wish by implementing Api and Storage classes.


Installation
------------

```sh
composer require servocoder/richfilemanager-php
```

NOTE: Most likely you won't have to install PHP connector separately. It's sufficient to run composer of the main package.
Check out the [installation guide](https://github.com/servocoder/RichFilemanager/wiki/PHP-connector) of RichFilemanager main package for PHP connector.

**AWS PHP SDK**

If you are going to use AWS S3 storage make sure that AWS PHP SDK package version >= 3.18.0 is added to the "require" section of
RichFilemanager [composer.json](https://github.com/servocoder/RichFilemanager/blob/master/composer.json) file:

```json
{
  "require": {
    "servocoder/richfilemanager-php": "*",
    "aws/aws-sdk-php": "^3.18.0"
  }
}
```

FYI - Amazon PHP SDK installation guide:
https://docs.aws.amazon.com/aws-sdk-php/v3/guide/getting-started/installation.html


Entry point setup
-----------------

RichFilemanager provides [entry point script](https://github.com/servocoder/RichFilemanager/blob/master/connectors/php/filemanager.php) out of the box,
so you don't have to create it from scratch. In this section you can find explanations and examples to setup the entry script.

1. Initiate application.

```php
$app = new \RFM\Application();
```

2. Create and set storage class instance.
Usually you will use a single storage, but it's possible initiate instances for various storages to use both in API.
For example, AWS S3 API can use S3 storage instance to manage original files and Local storage to manage image thumbnails.
More details in the [Configuration](#configuration) section. 
   
```php
// local filesystem storage
$local = new \RFM\Repository\Local\Storage();
$app->setStorage($local);

// AWS S3 storage instance
$s3 = new \RFM\Repository\S3\Storage();
$app->setStorage($s3);
```

3. Create and set API class instance. You can set only one API instance unlike storage instances. 

```php
// local filesystem API
$app->api = new RFM\Api\LocalApi();
```

OR

```php
// AWS S3 API
$app->api = new RFM\Api\AwsS3Api();
```

4. Run application. 

```php
$app->run();
```


Configuration
-------------

Configuration files are included in the package, but you can easily redefine any options upon creating storage instance.
To do this you have to pass array of options to the storage class constructor. See examples below.


#### Local filesystem storage

Check out [configuration file](https://github.com/servocoder/RichFilemanager-PHP/blob/master/src/config/config.local.php) options.
Each option in the file is well commented and won't be duplicated in this article.

Example to override the default options in the configuration file:

```php
$config = [
    'security' => [
        'readOnly' => true,
        'extensions' => [
            'policy' => 'ALLOW_LIST',
            'restrictions' => [
                'jpg',
                'jpe',
                'jpeg',
                'gif',
                'png',
                'html',
            ],
        ],
    ],
];

$local = new \RFM\Repository\Local\Storage($config);
```


#### AWS S3 storage

Check out [configuration file](https://github.com/servocoder/RichFilemanager-PHP/blob/master/src/config/config.s3.php) options.

Most of the configurations options for AWS S3 storage are the same as for Local filesystem storage, and come from `config.local.php` file.

Note that according to default configuration image thumbnails will be stored at the AWS S3 storage along with other files.
This example demonstrates how to change this behavior and store thumbnails to the "s3_thumbs" directory at the local storage:

```php
$config_s3 = [
    'images' => [
        'thumbnail' => [
            'dir' => 's3_thumbs',
            'useLocalStorage' => true,
        ],
    ],
    'credentials' => [
        'region' => 'your region',
        'bucket' => 'your aws s3 bucket',
        'credentials' => [
            'key' => 'your aws s3 key',
            'secret' => 'your aws s3 secret',
        ],
        'defaultAcl' => \RFM\Repository\S3\StorageHelper::ACL_PUBLIC_READ,
        'debug' => false,
    ],
];

$s3 = new \RFM\Repository\S3\Storage($config_s3);
```


Security
--------

Since the RichFilemanager is able to manipulate files on your server, it is necessary to secure safely your application.

The `security` section of the [configuration file](https://github.com/servocoder/RichFilemanager-PHP/blob/master/src/config/config.local.php)
defines options which give you a wide range of customizations in the security aspect. Learn the comments carefully to understand the use of each.


#### Server scripts execution
     
By default, all server scripts execution are disabled in the default `userfiles` folder.
See [.htaccess](https://github.com/servocoder/RichFilemanager/blob/master/userfiles/.htaccess) and [IIS](https://github.com/servocoder/RichFilemanager/blob/master/userfiles/web.config) files content.


#### User storage folder access

By default, everyone is able to access [user storage folder](#specify-user-storage-folder).
To make your application secure the [entry script](https://github.com/servocoder/RichFilemanager/blob/master/connectors/php/filemanager.php)
provides a few predefined functions which allow you to define your own authentication mechanism.


1. `fm_authenticate()` - Authenticate the user, for example to check a password login, or restrict client IP address.
If function returns _false_, the user will see an error. You can change it to redirect the user to a login page instead.

This function is called for every server connection. It must return _true_.

```php
session_start();

function fm_authenticate()
{
    return $_SESSION['user_type'] === "admin";
}
```

NOTE: This function only authorizes the user to connect and/or load the initial page.
Authorization for individual files or dirs is provided by the functions below.


2. `fm_has_read_permission()` - Perform custom individual-file READ permission checks.

This function is called before any filesystem read operation, where `$filepath` is the absolute path to file or directory being read.
It must return _true_, otherwise the read operation will be denied.

```php
function fm_has_read_permission($filepath)
{
    if ($filepath === "/var/www/userfiles/some_file.txt") {
        return false;    
    }
    
    return true;
}
```

NOTE: This is not the only permissions check that must pass. The read operation must also pass:
* Filesystem permissions (if any), e.g. POSIX `rwx` permissions on Linux
* The `$filepath` must be allowed according to the `patterns` and `extensions` configuration options


3. `fm_has_write_permission()` - Perform custom individual-file WRITE permission checks.

This function is called before any filesystem write operation, where `$filepath` is the absolute path to file or directory being written to.
It must return _true_, otherwise the write operation will be denied.

```php
function fm_has_write_permission($filepath)
{
    if ($filepath === "/var/www/userfiles/some_file.txt") {
        return false;    
    }
    
    return true;
}
```

NOTE: This is not the only permissions check that must pass. The write operation must also pass:
* Filesystem permissions (if any), e.g. POSIX `rwx` permissions on Linux
* The `$filepath` must be allowed according to the `patterns` and `extensions` configuration options
* `read_only` configuration option must be set to _false_, otherwise all writes are disabled


Specify user storage folder
---------------------------

#### Local storage folder

There are 2 configuration options which affects the location of a storage folder of user files:

    serverRoot (bool)
    fileRoot (bool|string)

By combining values of these options you can change target location of storage folder.

_**serverRoot**_ - "true" by default, means that storage folder location is defined relative to the server document root folder.
Set value to "false" in case the storage folder of user files is located outside server root folder.
If `fileRoot` options is set to "false", `serverRoot` value is ignored - always "true".

_**fileRoot**_ - "false" by default, means that storage folder is located under server document root folder and named "userfiles".
You can set specific path to the storage folder of user files instead of "false" value with the following rules:
- absolute path in case `serverRoot` set to "false", e.g. "/var/www/html/filemanager/userfiles/"
- relative path in case `serverRoot` set to "true", e.g. "/filemanager/userfiles/"


You could change the options values as it's described in the [Configuration](#configuration) section in two ways:

##### 1. Upon configuring storage instance

```php
$config = [
    "options" => [
        "serverRoot" => true,
        "fileRoot" => false,
    ],
];

$local = new \RFM\Repository\Local\Storage($config);
```

##### 2. Using "setRoot" storage method

```php
$local = new \RFM\Repository\Local\Storage();

$local->setRoot('user_folder', true, true);
```

Parameters of the `setRoot` method are as follows:

1. Relative or absolute path to folder (see examples below)
2. whether to create folder if it does not exist 
3. same as "serverRoot" configuration option


##### Local storage folder setup examples

1. Default case - user folder located inside the RichFilemanager root folder

The default user folder is named "**userfiles**" and located inside the RichFilemanager root folder.
After the application is deployed it should automatically detect the "**userfiles**" folder location,
so you don't need to make any changes in configuration options, which looks as follows by default:

```php
    "serverRoot" => true,
    "fileRoot" => false,
```

2. Specify user folder located UNDER server document root folder

* Setup configuration options

```php
    "serverRoot" => true,
    "fileRoot" => "/filemanager/files/", // relative path to a storage folder of user files
```

* Utilize `setRoot` method (alternative way)

```php
    $local->setRoot("/filemanager/files/", true, true);
```


3. Specify user folder located OUTSIDE server document root folder

* Setup configuration options

```php
    "serverRoot" => false,
    "fileRoot" => "/var/www/html/filemanager/files/", // absolute server path
```

* Utilize `setRoot` method (alternative way)

```php
    $local->setRoot("/var/www/html/filemanager/files/", true, false);
```

**_IMPORTANT_**: If a storage folder of user files is located outside server document root folder, then the application is unable to define absolute URL to user files.
RichFilemanager still able to preview the files, but by reading them via connector URL instead of using absolute URL.

That means the preview URL will look similar to: 

    http://mydomain.com/my_project/filemanager/connectors/php/filemanager.php?mode=readfile&path=/image.jpg
    
Instead of absolute direct URL:
    
    http://mydomain.com/my_project/filemanager/files/image.jpg
    
This may cause problems in case integration RichFilemanager with WYSIWYG editors.

Luckily in most cases it's possible to specify URL to access storage folder explicitly.
See [Handle preview URL](https://github.com/servocoder/RichFilemanager/wiki/Handle-preview-URL) RichFilemanager wiki article for the details.


##### Setting dynamic user folder based on session
 
This example shows how to set storage folder path dynamically based on session variable.

```php
session_start();

// supposed that user folder name is stored in "userfolder" session variable
$folderPath = "/filemanager/files/" . $_SESSION["userfolder"];

$app = new \RFM\Application();

$local = new \RFM\Repository\Local\Storage();

// set relative path to storage root folder
$local->setRoot($folderPath, true);

$app->setStorage($local);

// set application API
$app->api = new RFM\Api\LocalApi();

$app->run();
```


#### AWS S3 storage folder

Since AWS S3 storage root folder depends on your S3 bucket configuration you are only able to change user folder under the bucket.
Use "setRoot" storage method:

```php
$s3 = new \RFM\Repository\S3\Storage();

$s3->setRoot('user_folder', true);
```

Parameters of the `setRoot` method are as follows:

1. Relative path to S3 storage "folder" under the bucket.
2. Whether to create folder if it does not exist 



Debug and Logging
-----------------

If you have any problem using RichFilemanager you may want to see what's happening.

All logs are stored at your local filesystem, so you have to configure your [Local filesystem storage](#local-filesystem-storage)

To enable logger set `logger`.`enabled` option to _true_, also you can specify full path to logfile with `logger`.`file` option:

```php
$config = [
    'logger' => [
        'enabled' => true,
        'file' => '/var/log/filemanager.log',
    ],
];

$local = new \RFM\Repository\Local\Storage($config);
```

Notice that, by default, logs are disabled and logfile location is defined by `sys_get_temp_dir()` PHP function:
- Linux: _/tmp/filemanager.log_
- Windows 7: _C:\Users\\%username%\AppData\Local\Temp\filemanager.log_



MIT LICENSE
-----------

Released under the [MIT license](http://opensource.org/licenses/MIT).