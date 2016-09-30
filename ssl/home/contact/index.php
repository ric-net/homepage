<?php
/**
 * MicroEngine MailForm
 * http://microengine.jp/mailform/
 *
 * @copyright Copyright (C) 2014 MicroEngine Inc.
 */

/**
 * ME_MAILFORM_DIR 設定
 * me_mailform ディレクトリの名を変更した場合に定義する。
 *
 * 定義例
 * define('ME_MAILFORM_DIR', '/me_mailform_contact/');
 */
define('ME_MAILFORM_DIR', '/me_mailform/');

/**
 * DATA_ROOT 設定
 * dataディレクトリの位置を変更した場合に定義する。
 *
 * 定義例
 * ファイルシステムのルートディレクトリからのパスを、DATA_ROOT定数に指定する。
 * define('DATA_ROOT', '/full/path/to/data');
 */
define('DATA_ROOT', dirname(__FILE__) . ME_MAILFORM_DIR . 'data');

require_once('.' . ME_MAILFORM_DIR . 'script/Me_MailForm.php');
$me_MailForm = new Me_MailForm();
$me_MailForm->run();
