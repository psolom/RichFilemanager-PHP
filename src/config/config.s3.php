<?php
/**
 *  RichFilemanager configuration file for S3 storage.
 *
 *	Based on the config file for Local storage.
 *	Contains the options which are specific to S3 storage.
 *
 *	@license	MIT License
 *  @author     Pavel Solomienko <https://github.com/servocoder/>
 *  @copyright	Authors
 */

$config = require 'config.local.php';

/**
 * Default value "false".
 * Whether to store images thumbnails locally (faster; save traffic and requests)
 */
$config['images']['thumbnail']['useLocalStorage'] = false;

/**
 * Default value "true".
 * Whether to perform bulk operations on "folders" (rename/move/copy)
 * NOTE: S3 is not a filesystem, it operates with "objects" and it has no such thing as "folder".
 * When you are performing operation like delete/rename/move/copy on "directory" the plugin actually performs
 * multiple operations for each object prefixed with the "directory" name in the background. The more objects you have
 * in your "directory", the more requests will be sent to simulate the "recursive mode".
 * DELETE requests are not charged so they are not restricted with with option.
 *
 * Links with some explanations:
 * http://stackoverflow.com/a/12523414/1789808
 * http://stackoverflow.com/questions/33363254/aws-s3-rename-directory-object
 * http://stackoverflow.com/questions/33000329/cost-of-renaming-a-folder-in-aws-s3-bucket
 */
$config['allowBulk'] = true;


/*******************************************************************************
 * S3 SETTINGS
 * Check options description: https://github.com/frostealth/yii2-aws-s3
 ******************************************************************************/

$config['credentials'] = [
    'region' => 'your region',
    'bucket' => 'your aws s3 bucket',
    // Aws\Credentials\CredentialsInterface|array|callable
    'credentials' => [
        'key' => 'your aws s3 key',
        'secret' => 'your aws s3 secret',
    ],
    'defaultAcl' => '',
    //'cdnHostname' => 'http://example.cloudfront.net',
    'debug' => false, // bool|array
    /*'options' => [
        'upload' => [ // Configuration specific for upload actions
            'ServerSideEncryption' => false, // bool|string (AES256,aws:kms)
        ],
    ],*/
];

return $config;