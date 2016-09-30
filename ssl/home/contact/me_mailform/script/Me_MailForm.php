<?php
/**
 * MicroEngine MailForm
 * http://microengine.jp/mailform/
 *
 * @copyright Copyright (C) 2014-2015 MicroEngine Inc.
 * @version 1.0.2
 */
require_once('simple_html_dom.php');
/**
 * メールフォーム
 */
class Me_MailForm
{
    /** システム文字コード */
    const SYSTEM_CHAR_CODE = 'UTF-8';
    /** CSV文字コード */
    const CSV_CHAR_CODE = 'SJIS-win';
    /** 入力ステップ名 */
    const ENTRY = 'entry';
    /** 確認ステップ名 */
    const CONFIRM = 'confirm';
    /** 送信ステップ名 */
    const SEND = 'send';
    /** 郵便番号検索 */
    const ZIPCODE = 'zipcode';
    /** CAPTCHA */
    const CAPTCHA = 'captcha';
    /** エラーメッセージ用要素のIDにつける接尾辞 */
    const ERROR_ID_SUFFIX = '_error';
    /** ステップパラメータ用 name属性の値 */
    const STEP_PARAMETER = '_step';
    /** 戻るボタン用 name属性の値 */
    const BACK_PARAMETER = '_back';
    /** 基本設定ファイルパス */
    const CONFIG_FILE = '/config/config.ini';
    /** Mail 設定ファイルパス */
    const MAIL_CONFIG_FILE = '/config/mail.ini';
    /** Message 設定ファイルパス */
    const MESSAGE_CONFIG_FILE = '/config/message.ini';
    /** アイテム（フォーム項目）設定ファイルパス */
    const ITEM_FILE = '/config/item.ini';
    /** 本文ファイルパス */
    const BODY_FILE = '/config/body.txt';
    /** 自動返信本文ファイルパス */
    const REPLY_BODY_FILE = '/config/reply_body.txt';
    /** メールログディレクトリ */
    const MAIL_LOG_DIR = '/log/';
    /** メールログファイル名 */
    const MAIL_LOG_FILENAME = 'qdmail.log';
    /** メールエラーログファイル名 */
    const MAIL_ERROR_LOG_FILENAME = 'qdmail_error.log';
    /** CSV保存ディレクトリ */
    const CSV_SAVE_DIR = '/csv/';
    /** メール文字セット */
    const MAIL_CHARSET = 'ISO-2022-JP';
    /** メールエンコード */
    const MAIL_ENCODING = '7bit';

    /**
     * 設定配列
     * @var array
     */
    private $config;
    /**
     * Mail 設定配列
     * @var array
     */
    private $mail_config;
    /**
     * Message 設定配列
     * @var array
     */
    private $message_config;
    /**
     * アイテム（フォーム項目）配列
     * @var array
     */
    private $form_item;
    /**
     * ステップ名
     * @var string
     */
    private $step;
    /**
     * INPUT要素の値更新フラグ
     * @var boolean
     */
    private $update_value = false;
    /**
     * エラー発生フラグ
     * @var boolean
     */
    private $is_error = false;
    /**
     * 文字コード変換フラグ
     * @var boolean
     */
    private $convert_char_code = false;
    /**
     * 処理時のタイムスタンプ
     * @var int
     */
    private $now;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        // 内部文字コード指定
        mb_internal_encoding(self::SYSTEM_CHAR_CODE);

        // 設定ファイル読み込み
        $this->load_config();

        // PHP_VERSION_IDを定義
        $this->define_php_version_id();

        // エラーレベル設定
        $this->set_error_level();

        // Cookie設定
        if (PHP_VERSION_ID >= 50200) {
            ini_set('session.cookie_httponly', 1);
        }

        // キャッシュ設定
        $this->set_cache_limiter();

        // セッション開始
        session_name('Me_MailForm');
        session_start();

        // magic_quotes_gpc対策
        $this->against_magic_quotes();

        // テンプレートファイルの文字コード設定
        $this->set_template_char_code();

        // タイムゾーン設定
        $this->set_timezone();
    }

    /**
     * アクション実行
     */
    public function run()
    {
        // ステップ判定
        $this->set_step();

        // tokenチェック
        $this->check_token();

        // POST値をアイテムにセットする
        if ($this->step === self::ENTRY ||$this->step === self::CONFIRM || $this->step === self::SEND) {
            $this->set_item_value();
        }

        // POST値の変換・バリデーション
        if ($this->step === self::CONFIRM || $this->step === self::SEND) {
            // convert
            $this->convert();
            // validation
            $this->validate();
        }

        // ステップ毎の処理を実行
        $action = $this->step;
        $this->$action();
    }

    /**
     * 入力ステップ処理
     */
    private function entry()
    {
        // テンプレートを取得
        $html = $this->get_template($this->config['step'][$this->step]);

        // フォーム要素を取得
        $form = $this->get_form($html);

        // フォームに値をセットする
        $this->set_form($form);

        // エラーメッセージ見出し
        // 入力画面のテンプレートには、エラー発生を知らせるための表示をしておく。
        // エラーが発生しなかったら、その要素を削除する。
        if (!$this->is_error) {
            $html->find('#' . $this->message_config['message']['error_message_id'], 0)->outertext = '';
        }

        // ステップパラメータと token をフォームに追加
        $form->find('text', 0)->outertext .= $this->get_hidden_tag(self::STEP_PARAMETER, $this->get_next_step()) . $this->get_token();

        // HTML書き出し
        $this->render($html);
    }

    /**
     * 確認ステップ処理
     */
    private function confirm()
    {
        // テンプレートを取得
        $html = $this->get_template($this->config['step'][$this->step]);

        // アイテムの値をテンプレートに埋め込む
        $this->output_item_value($html);

        // フォーム要素を取得
        $form = $this->get_form($html);

        // hidden要素と token をフォームに追加
        $form->innertext .= $this->get_hidden_tags() . $this->get_token();

        // HTML書き出し
        $this->render($html);
    }

    /**
     * 送信ステップ
     */
    private function send()
    {
        // to 設定確認
        if (strlen($this->mail_config['mail']['to']) < 1) {
            $this->error_screen($this->message_config['message']['msg_unset_to']);
        }

        // 通し番号処理
        $this->issue_serial();

        // 送信者情報
        $this->set_sender_info();
        // CSV保存
        $this->save_csv();

        // メール送信処理
        $this->send_mail();

        // CAPTCHAのキーを削除
        unset($_SESSION['captcha_keystring']);

        if (isset($this->config['flow']['redirect'])) {
            // リダイレクト処理
            header('Location: ' . $this->config['flow']['redirect']);
            exit;
        } else {
            // テンプレートを取得
            $html = $this->get_template($this->config['step'][$this->step]);

            // アイテムの値をテンプレートに埋め込む
            $this->output_item_value($html);

            // HTML書き出し
            $this->render($html);
        }
    }

    /**
     * 郵便番号検索
     */
    private function zipcode()
    {
        $code = mb_convert_kana($_GET['zipcode'], 'n');
        if (!is_numeric($code)) {
            echo json_encode(false);
            return;
        }

        try {
            $con = $this->get_pdo_instance();
            $sql = 'select * from ZIPCODE where code = :code';
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':code', $code);
            $stmt->execute();
        } catch (PDOException $e) {
            $this->error_screen('Exception: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->error_screen('Exception: ' . $e->getMessage());
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($result);
        return;
    }

    /**
     * CAPTCHA
     */
    private function captcha()
    {
        include('.' . ME_MAILFORM_DIR . 'script/kcaptcha/kcaptcha.php');
        $captcha = new KCAPTCHA();

        if($_COOKIE[session_name()] || $_GET[session_name()]){
            $_SESSION['captcha_keystring'] = $captcha->getKeyString();
        }
    }

    /**
     * 設定ファイル読み込み
     */
    private function load_config()
    {
        $this->config = parse_ini_file(DATA_ROOT . self::CONFIG_FILE, true);
        $this->mail_config = parse_ini_file(DATA_ROOT . self::MAIL_CONFIG_FILE, true);
        $this->message_config = parse_ini_file(DATA_ROOT . self::MESSAGE_CONFIG_FILE, true);
        $this->form_item = parse_ini_file(DATA_ROOT . self::ITEM_FILE, true);
    }

    /**
     * PHP_VERSION_IDを定義
     */
    private function define_php_version_id()
    {
        if (!defined('PHP_VERSION_ID')) {
            $version = explode('.', PHP_VERSION);
            define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
        }
    }

    /**
     * エラーレベル設定
     */
    private function set_error_level()
    {
        // エラーレベル設定
        if (isset($this->config['global']['debug']) && $this->config['global']['debug']) {
            if (PHP_VERSION_ID >= 50400) {
                error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);
            } else {
                error_reporting(E_ALL ^ E_NOTICE);
            }
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
        }
    }

    /**
     * キャッシュ設定
     */
    private function set_cache_limiter()
    {
        // 確認画面を使わない場合は、キャッシュ設定は行わない。
        if (!$this->config['flow']['use_confirm']) {
            return;
        }
        $cache_limiter = 'private_no_expire';
        if (isset($this->config['global']['session.cache_limiter'])) {
            $cache_limiter = $this->config['global']['session.cache_limiter'];
        }
        session_cache_limiter($cache_limiter);
    }

    /**
     * magic_quotes_gpc対策
     */
    private function against_magic_quotes()
    {
        if (get_magic_quotes_gpc()) {
            $_POST = $this->stripslashes_deep($_POST);
        }
    }

    /**
     * クォートを再帰的に取り除く
     * @param mixed $arr
     */
    private function stripslashes_deep($arr)
    {
        return is_array($arr) ?
            array_map(array('Me_MailForm', 'stripslashes_deep'), $arr) :
            stripslashes($arr);
    }

    /**
     * テンプレートファイルの文字コード設定
     */
    private function set_template_char_code()
    {
        if (is_null($this->config['global']['char_code'])) {
            $this->config['global']['char_code'] = self::SYSTEM_CHAR_CODE;
        }
        $this->convert_char_code = ($this->config['global']['char_code'] !== self::SYSTEM_CHAR_CODE);
    }

    /**
     * タイムゾーン設定
     */
    private function set_timezone()
    {
        if (isset($this->config['Date']['date.timezone'])) {
            date_default_timezone_set($this->config['Date']['date.timezone']);
        }
        // 処理時用のタイムスタンプをセット
        $this->now = time();
    }

    /**
     * ステップ判定
     */
    private function set_step()
    {
        if (isset($_GET['zipcode'])) {
            $this->step = self::ZIPCODE;
            return;
        }
        if (isset($_GET['captcha'])) {
            $this->step = self::CAPTCHA;
            return;
        }

        switch ($_POST[self::STEP_PARAMETER]) {
            case self::SEND:
                $this->step = self::SEND;
                break;
            case self::CONFIRM:
                $this->step = self::CONFIRM;
                break;
            case self::ENTRY:
            default:
                $this->step = self::ENTRY;
                break;
        }
        // 戻るボタンが押された場合
        if (isset($_POST[self::BACK_PARAMETER]) ||
            (isset($_POST[self::BACK_PARAMETER . '_x']) && isset($_POST[self::BACK_PARAMETER . '_y']))
        ) {
            $this->step = self::ENTRY;
            $this->update_value = true;
        }
        return;
    }

    /**
     * tokenチェック
     */
    private function check_token()
    {
        if ($this->step === self::SEND && $this->config['security']['token']
            && (!isset($_SESSION['_token']) || $_SESSION['_token'] !== $_POST['_token'])) {
            $this->error_screen($this->message_config['message']['msg_token']);
        }
    }

    /**
     * アイテムに値をセットする
     */
    private function set_item_value()
    {
        // postされた値をアイテムの値にセットする
        foreach (array_keys($this->form_item) as $name) {
            if (isset($_POST[$name])) {
                $this->form_item[$name]['value'] = $this->convert_encoding($_POST[$name]);
            }
        }
    }

    /**
     * 文字列変換
     */
    private function convert()
    {
        foreach ($this->form_item as &$item) {
            // 全角・半角変換
            if ($item['convert_kana'] && strlen($item['value']) > 0) {
                $item['value'] = mb_convert_kana($item['value'], $item['convert_kana']);
            }
            // 大文字変換
            if ($item['convert_upper'] && strlen($item['value']) > 0) {
                $item['value'] = mb_strtoupper($item['value']);
            }
            // 大文字変換
            if ($item['convert_lower'] && strlen($item['value']) > 0) {
                $item['value'] = mb_strtolower($item['value']);
            }
        }
    }

    /**
     * 入力値チェック
     */
    private function validate()
    {
        // validation
        foreach ($this->form_item as &$item) {
            // メールアドレス書式簡易チェック
            if ($item['type'] === 'email' && strlen($item['value']) > 0
                && !preg_match('/^([a-z0-9_]|\-|\.|\+)+@(([a-z0-9_]|\-)+\.)+[a-z]{2,6}$/i', $item['value'])) {
                $this->set_error_message($item, 'msg_email', array('{label}'=>$item['label']));
                $this->is_error = true;
                continue;
            }
            // 最大文字数チェック
            if ($item['maxlength'] && mb_strlen($item['value']) > $item['maxlength']) {
                $this->set_error_message($item, 'msg_maxlength',
                    array('{maxlength}'=>$item['maxlength'], '{label}'=>$item['label']));
                $this->is_error = true;
                continue;
            }
            // 必須項目チェック
            if ($item['required'] && strlen($item['value']) === 0) {
                if ($item['type'] === 'select' || $item['type'] === 'radio') {
                    $this->set_error_message($item, 'msg_required_option', array('{label}'=>$item['label']));
                } else if ($item['type'] === 'checkbox') {
                    $this->set_error_message($item, 'msg_required_check', array('{label}'=>$item['label']));
                } else {
                    $this->set_error_message($item, 'msg_required', array('{label}'=>$item['label']));
                }
                $this->is_error = true;
                continue;
            }
            // 半角数字のみかどうかチェックする
            if ($item['numeric'] && strlen($item['value']) > 0 && !preg_match('/^[0-9]*$/', $item['value'])) {
                $this->set_error_message($item, 'msg_numeric', array('{label}'=>$item['label']));
                $this->is_error = true;
                continue;
            }
            // 電話番号かどうかチェックする
            if ($item['phone'] && strlen($item['value']) > 0 && !preg_match('/^\d{2,5}-\d{1,4}-\d{4}$/', $item['value'])) {
                $this->set_error_message($item, 'msg_phone', array('{label}'=>$item['label']));
                $this->is_error = true;
                continue;
            }
            // 郵便番号かどうかチェックする
            if ($item['postal'] && strlen($item['value']) > 0 && !preg_match('/^\d{3}-\d{4}$/', $item['value'])) {
                $this->set_error_message($item, 'msg_postal', array('{label}'=>$item['label']));
                $this->is_error = true;
                continue;
            }
            // 入力値が同じかどうかチェックする
            if (strlen($item['equal_to']) > 0) {
                $equal_to_item = $this->form_item[$item['equal_to']];
                if ($item['value'] !== $equal_to_item['value']) {
                    $this->set_error_message($item, 'msg_equal_to',
                        array('{label}'=>$item['label'], '{equal_to_label}'=>$equal_to_item['label']));
                    $this->is_error = true;
                    continue;
                }
            }
            // CAPTCHA
            if ($item['type'] === 'captcha') {
                if (!isset($_SESSION['captcha_keystring']) || $_SESSION['captcha_keystring'] !== $item['value']) {
                    $this->set_error_message($item, 'msg_captcha', array('{label}'=>$item['label']));
                    $this->is_error = true;
                    continue;
                }
            }
        }

        // エラーメッセージ
        if ($this->is_error) {
            $this->step = self::ENTRY;
            $this->update_value = true;
        }
    }

    /**
     * テンプレート取得
     * @param string $template_name
     * @return simple_html_dom
     */
    private function get_template($template_name)
    {
        $template_path = '.' . ME_MAILFORM_DIR . 'template/' . $template_name;
        // テンプレート内容
        $template = '';

        if (pathinfo($template_path, PATHINFO_EXTENSION) === 'php') {
            // 拡張子がphpであれば、includeしてPHPとして評価する。

            // バッファリング制御
            ob_start();
            include($template_path);
            $template = ob_get_contents();
            //バッファを削除
            ob_end_clean();
        } else {
            // php でなければ、そのまま読み込む
            $template = file_get_contents($template_path);
        }
        // 文字コード変換
        $template = $this->convert_encoding($template);

        return str_get_html($template, true, true, self::SYSTEM_CHAR_CODE, false);
    }

    /**
     * フォーム要素を取得
     * @param simple_html_dom $html
     * @return simple_html_dom $form
     */
    private function get_form($html)
    {
        $form = null;
        if (isset($this->config['global']['form_name'])) {
            $form = $html->find('form[name=' . $this->config['global']['form_name'] . ']', 0);
        }
        if ($form === null) {
            // form_name設定が空もしくは、該当のformが無い場合は一つ目のformを対象とする。
            $form = $html->find('form', 0);
        }
        return $form;
    }

    /**
     * formに値をセットする
     * @param simple_html_dom $form
     */
    private function set_form($form)
    {
        foreach ($this->form_item as $name => $item) {
            // エラー発生時もしくは、戻るボタンが押された場合は、INPUT要素の値を書き換える。
            // 初回アクセス時は、書き換えないのでテンプレートの状態が初期値となる。
            if ($this->update_value) {
                switch ($item['type']) {
                    case 'textarea':
                        $form->find('textarea[name=' . $name . ']', 0)->innertext = $this->html_escape($item['value']);
                        break;
                    case 'select':
                        if ($item['multiple']) {
                            $option_list = $form->find('select[name=' . $name . '[]]', 0)->find('option');
                            foreach ($option_list as $option) {
                                $selected = null;
                                if (is_array($item['value'])) {
                                    foreach ($item['value'] as $val) {
                                        if ($option->value === $val) {
                                            $selected = (strlen($val) > 0) ? 'selected' : null;
                                            continue;
                                        }
                                    }
                                }
                                $option->selected = $selected;
                            }
                        } else {
                            $option_elem = $form->find('select[name=' . $name . ']', 0)->find('option[selected], option[selected=selected]', 0);
                            if ($option_elem !== null) {
                                $option_elem->selected = null;
                            }
                            $form->find('select[name=' . $name . ']', 0)->find('option[value=' . $item['value'] . ']', 0)->selected = 'selected';
                        }
                        break;
                    case 'radio':
                        foreach ($form->find('input[name=' . $name . ']') as $radio) {
                            if ($radio->value === $item['value']) {
                                $radio->checked = 'checked';
                            } else if ($radio->checked !== null) {
                                $radio->checked = null;
                            }
                        }
                        break;
                    case 'checkbox':
                        if ($item['multiple']) {
                            $checkbox_list = $form->find('input[name=' . $name . '[]]');
                            foreach ($checkbox_list as $checkbox) {
                                $checked = null;
                                if (is_array($item['value'])) {
                                    foreach ($item['value'] as $val) {
                                        if ($checkbox->value === $val) {
                                            $checked = (strlen($val) > 0) ? 'checked' : null;
                                            continue;
                                        }
                                    }
                                }
                                $checkbox->checked = $checked;
                            }
                        } else {
                            $checked = (strlen($item['value']) > 0) ? 'checked' : null;
                            $form->find('input[name=' . $name . ']', 0)->checked = $checked;
                        }
                        break;
                    case 'captcha':
                        // 初期状態のままにする。
                        break;
                    default:
                        $form->find('input[name=' . $name . ']', 0)->value = $this->html_escape($item['value']);
                        break;
                }
            }

            // フォーム項目名にサフィックスを追加したID名を持つ要素をテンプレート内に用意しておく
            // エラー発生時はその要素の中にエラーメッセージを表示する
            // エラーが発生しなかったら、その要素を削除する
            $error_elem = $form->find('#' . $name . self::ERROR_ID_SUFFIX, 0);
            if ($error_elem !== null && strlen($item['error']) > 0) {
                $error_elem->innertext = $item['error'];
            } else if ($error_elem !== null) {
                $error_elem->outertext = '';
            }
        }
    }

    /**
     * 文字コード変換
     * 必要に応じて文字コード変換をして文字列を返す
     * @param string $str
     * @return string $str
     */
    private function convert_encoding($str)
    {
        if (is_array($str)) {
            return array_map(array('Me_MailForm', 'convert_encoding'), $str);
        } else if (is_object($str) || is_null($str)) {
            return '';
        }

        if ($this->convert_char_code) {
            $str = mb_convert_encoding($str, self::SYSTEM_CHAR_CODE, $this->config['global']['char_code']);
        }
        return $str;
    }

    /**
     * 次のステップ名を返す
     * @return string $next_step
     */
    private function get_next_step()
    {
        $next_step = self::SEND;
        if ($this->step === self::ENTRY && $this->config['flow']['use_confirm']) {
            $next_step = self::CONFIRM;
        }
        return $next_step;
    }

    /**
     * アイテム毎に値をセットする
     * @param simple_html_dom $html
     */
    private function output_item_value($html)
    {
        foreach ($this->form_item as $name => $item) {
            $item_elem = $html->find('#' . $name, 0);
            if ($item_elem === null) {
                continue;
            }
            switch ($item['type']) {
                case 'textarea':
                    $item_elem->innertext = $this->nl2br_escape($item['value']);
                    break;
                case 'select':
                    $item_value = $item['value'];
                    if ($item['multiple'] && is_array($item['value'])) {
                        $item_value = implode($this->config['select']['delimiter'], $item['value']);
                    }
                    $item_elem->innertext = $this->nl2br_escape($item_value);
                    break;
                case 'checkbox':
                    $item_value = $item['value'];
                    if ($item['multiple'] && is_array($item['value'])) {
                        $item_value = implode($this->config['checkbox']['delimiter'], $item['value']);
                    }
                    $item_elem->innertext = $this->nl2br_escape($item_value);
                    break;
                default:
                    $item_elem->innertext = $this->html_escape($item['value']);
                    break;
            }
       }
    }

    /**
     * 全アイテムのhiddenタグを生成する
     * @return string $hidden
     */
    private function get_hidden_tags()
    {
        // ステップパラメータを取得
        $hidden = $this->get_hidden_tag(self::STEP_PARAMETER, $this->get_next_step());

        foreach ($this->form_item as $name => $item) {
            if (($item['type'] === 'select' || $item['type'] === 'checkbox')
                && $item['multiple'] && is_array($item['value'])) {
                foreach ($item['value'] as $value) {
                    $hidden .= $this->get_hidden_tag($name . '[]', $value);
                }
            } else {
                $hidden .= $this->get_hidden_tag($name, $item['value']);
            }
        }
        return $hidden;
    }

    /**
     * hiddenタグを生成する
     * @param string $name
     * @param string $value
     * @return string
     */
    private function get_hidden_tag($name, $value)
    {
        return '<input type="hidden" name="' . $name . '" value="'
                . $this->html_escape($value) . '"' . $this->self_closing_tag() . '>';
    }

    /**
     * メール送信処理
     */
    private function send_mail()
    {
        require_once('qdmail.php');
        require_once('qdsmtp.php');

        // qdmailオブジェクト作成
        $mail = $this->get_qdmail();

        // toアドレス
        $to = $this->mail_config['mail']['to'];

        // 送信先振り分け
        if (isset($this->mail_config['sorting']['item_name'])) {
            $value_key = array_search($this->form_item[$this->mail_config['sorting']['item_name']]['value'],
                $this->mail_config['sorting']);
            $email_key = str_replace('value', 'email', $value_key);
            $to_address = $this->mail_config['sorting'][$email_key];
            if ($to_address) {
                $to = $to_address;
            }
        }

        $from = array($this->mail_config['mail']['from'], $this->mail_config['mail']['from_name']);
        if (isset($this->mail_config['mail']['from_item'])) {
            if (isset($this->mail_config['mail']['from_name_item'])) {
                $from = array(
                    $this->form_item[$this->mail_config['mail']['from_item']]['value'],
                    $this->form_item[$this->mail_config['mail']['from_name_item']]['value']
                );
            } else {
                $from = $this->form_item[$this->mail_config['mail']['from_item']]['value'];
            }
        }
        // サブジェクト取得
        $subject = $this->replace_text($this->mail_config['mail']['subject'], true);
        // メール本文取得
        $body = $this->replace_text(file_get_contents(DATA_ROOT . self::BODY_FILE), false);

        $mail->to($this->multi_address($to));
        if (isset($this->mail_config['mail']['cc'])) {
            $mail->cc($this->multi_address($this->mail_config['mail']['cc']));
        }
        if (isset($this->mail_config['mail']['bcc'])) {
            $mail->bcc($this->multi_address($this->mail_config['mail']['bcc']));
        }
        $mail->from($from);
        $mail->subject($subject);
        $mail->text($body);

        // メール送信
        if (!$mail->send()) {
            $this->error_screen($this->message_config['message']['msg_send']);
        }

        // 自動返信メール処理
        if (isset($this->mail_config['reply_mail']['reply_to'])) {
            $mail->cc = array();
            $mail->bcc = array();
            $reply_to = $this->mail_config['reply_mail']['reply_to'];
            $to = $this->form_item[$reply_to]['value'];
            if (isset($this->mail_config['reply_mail']['reply_from'])) {
                if (isset($this->mail_config['reply_mail']['reply_from_name'])) {
                    $from = array($this->mail_config['reply_mail']['reply_from'], $this->mail_config['reply_mail']['reply_from_name']);
                } else {
                    $from = $this->mail_config['reply_mail']['reply_from'];
                }
                $mail->from($from);
            }
            if (isset($this->mail_config['reply_mail']['reply_subject'])) {
                // サブジェクト取得
                $subject = $this->replace_text($this->mail_config['reply_mail']['reply_subject'], true);
            }
            // 自動返信用本文ファイルがあれば使う
            if (file_exists(DATA_ROOT . self::REPLY_BODY_FILE)) {
                // メール本文取得
                $body = $this->replace_text(file_get_contents(DATA_ROOT . self::REPLY_BODY_FILE), false);
            }
            if (strlen($to) > 0) {
                $mail->to($to);
                if (isset($this->mail_config['reply_mail']['reply_cc'])) {
                    $mail->cc($this->multi_address($this->mail_config['reply_mail']['reply_cc']));
                }
                if (isset($this->mail_config['reply_mail']['reply_bcc'])) {
                    $mail->bcc($this->multi_address($this->mail_config['reply_mail']['reply_bcc']));
                }
                $mail->subject($subject);
                $mail->text($body);
                // メール送信
                if (!$mail->send()) {
                    $this->error_screen($this->message_config['message']['msg_send']);
                }
            }
        }
    }

    /**
     * 基本設定を行った状態のqdmailオブジェクトを返す
     * @return Qdmail $mail
     */
    private function get_qdmail()
    {
        $mail = new Qdmail();
        $charset = (isset($this->mail_config['mail']['charset'])) ? $this->mail_config['mail']['charset'] : self::MAIL_CHARSET;
        $encoding = (isset($this->mail_config['mail']['encoding'])) ? $this->mail_config['mail']['encoding'] : self::MAIL_ENCODING;
        $mail->charset($charset, $encoding);

        // エラー表示制御
        $mail->error_display = $this->mail_config['library']['error_display'];

        // smtpセクションがあればSMTPサーバーを経由して送信する
        if (!empty($this->mail_config['smtp'])) {
            $param = array();
            switch ($this->mail_config['smtp']['protocol']) {
                case 'POP_BEFORE':
                    $param['pop_host'] = $this->mail_config['smtp']['pop_host'];
                case 'SMTP_AUTH':
                    $param['user'] = $this->mail_config['smtp']['user'];
                    $param['pass'] = $this->mail_config['smtp']['password'];
                default:
                    $param['host'] = $this->mail_config['smtp']['host'];
                    $param['port'] = $this->mail_config['smtp']['port'];
                    $param['from'] = $this->mail_config['mail']['from'];
                    $param['protocol'] = $this->mail_config['smtp']['protocol'];
                    break;
            }
            $mail->smtp(true);
            $mail->smtpServer($param);
        }

        // ログ
        if ($this->mail_config['library']['log_level']) {
            $mail->logLevel($this->mail_config['library']['log_level']);
            $mail->logPath(DATA_ROOT . self::MAIL_LOG_DIR);
            $mail->logFilename(self::MAIL_LOG_FILENAME);
        }
        // エラーログ
        if ($this->mail_config['library']['error_log_level']) {
            $mail->errorlogLevel($this->mail_config['library']['error_log_level']);
            $mail->errorlogPath(DATA_ROOT . self::MAIL_LOG_DIR);
            $mail->errorlogFilename(self::MAIL_ERROR_LOG_FILENAME);
        }

        return $mail;
    }

    /**
     * データベースハンドル生成
     * @param string $db_name DB名
     * @return object $pdo_instance
     */
    private function get_pdo_instance($db_name = 'zipcode')
    {
        $dsn = 'sqlite:' . DATA_ROOT . '/db/' . $db_name . '.sqlite';
        try {
            $pdo_instance = new PDO($dsn);
            $pdo_instance->beginTransaction();
            $pdo_instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->error_screen('Exception: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->error_screen('Exception: ' . $e->getMessage());
        }
        return $pdo_instance;
    }

    /**
     * 通し番号発行
     */
    private function issue_serial()
    {
        if (!$this->config['serial']['serial']) {
            return;
        }

        try {
            // レコード登録
            $con = $this->get_pdo_instance('serial');
            $sql = 'insert into serial (create_date) values (:create_date);';
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':create_date', date('Y-m-d H:i:s', $this->now));
            $stmt->execute();

            // シリアル値取得
            $sql = 'select last_insert_rowid();';
            $stmt = $con->prepare($sql);
            $stmt->execute();
            $serial_no = (int) $stmt->fetchColumn();
            // コミット前にカーソルを閉じる
            $stmt->closeCursor();

            // コミット
            $con->commit();
        } catch (PDOException $e) {
            $this->error_screen('Exception: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->error_screen('Exception: ' . $e->getMessage());
        }

        // オフセット指定
        if (isset($this->config['serial']['serial_offset'])) {
            $serial_no += $this->config['serial']['serial_offset'];
        }

        // 最大値指定
        if (isset($this->config['serial']['serial_max'])) {
            $serial_no = $serial_no % (int) $this->config['serial']['serial_max'];
        }

        // フォーマット
        if (isset($this->config['serial']['serial_format'])) {
            $serial_no = sprintf($this->config['serial']['serial_format'], $serial_no);
        }

        // 日付フォーマット
        if (isset($this->config['serial']['serial_date_format'])) {
            $serial_no = str_replace('{_date}', date(date($this->config['serial']['serial_date_format'], $this->now)), $serial_no);
        }

        // form_itemに保存
        $this->form_item['_serial'] = array(
            'type' => 'reserved',
            'label' => $this->config['serial']['label'],
            'value' => $serial_no,
        );
    }

    /**
     * 送信者情報をform_itemに保存
     */
    private function set_sender_info()
    {
        $this->form_item['_date'] = array('type'=>'reserved','label'=>'日付','value'=>date($this->config['Date']['date_format'], $this->now));
        $this->form_item['_ip'] = array('type'=>'reserved','label'=>'IP','value'=>$_SERVER['REMOTE_ADDR']);
        $this->form_item['_host'] = array('type'=>'reserved','label'=>'ホスト','value'=>gethostbyaddr($_SERVER['REMOTE_ADDR']));
        $this->form_item['_ua'] = array('type'=>'reserved','label'=>'ユーザーエージェント','value'=>$_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * CSV保存
     * @return boolean
     */
    private function save_csv()
    {
        // CSVファイル名の指定が無ければ保存しない。
        if (is_null($this->config['csv']['csv_file'])) {
            return true;
        }
        // CSVファイルパス
        $file_path = DATA_ROOT . self::CSV_SAVE_DIR . $this->config['csv']['csv_file'];

        // 書き込みできることを確認
        if (!$this->writable_file($file_path)) {
            $this->error_screen('CSVファイルの書き込み権限がありません。');
        }

        $csv_lines = array();
        $form_item = $this->form_item;

        // 保存アイテムリストが指定されている場合
        if (isset($this->config['csv']['item_list'])) {
            $tmp_form_item = array();
            $item_list = $this->str_to_array($this->config['csv']['item_list']);
            foreach ($item_list as $name) {
                $tmp_form_item[$name] = $form_item[$name];
            }
            $form_item = $tmp_form_item;
        }

        // 文字コード設定
        if (empty($this->config['csv']['char_code'])) {
            $this->config['csv']['char_code'] = self::CSV_CHAR_CODE;
        }

        // 初回はヘッダーを作成
        if (!file_exists($file_path)) {
            $csv = array();
            foreach ($form_item as $item) {
                $csv[] = $item['label'];
            }
            // BOMが指定されてUTF-8の場合は、先頭にBOMを追加する。
            if ($this->config['csv']['bom'] && strtoupper($this->config['csv']['char_code']) === 'UTF-8') {
                $csv[0] = "\xEF\xBB\xBF" . $csv[0];
            }
            $csv_lines[] = $csv;
        }
        // データ行を作成
        $csv = array();
        foreach ($form_item as $name => $item) {
            $value = $item['value'];
            if ($item['type'] === 'checkbox' && is_array($value)) {
                $value = implode($this->config['checkbox']['delimiter'], $value);
            } else if ($item['type'] === 'select' && is_array($value)) {
                $value = implode($this->config['select']['delimiter'], $value);
            }
            $csv[] = $value;
        }
        $csv_lines[] = $csv;
        $this->write_csv($file_path, $csv_lines);

        return true;
    }

    /**
     * ファイルの書き込み権限を確認
     * @param string $file_path
     * @return boolean
     */
    private function writable_file($file_path)
    {
        $exist = file_exists($file_path);
        if ($exist) {
            // ファイルがあれば、そのファイルの書き込み権限を確認
            if (!is_writable($file_path)) {
                return false;
            }
        } else {
            // ファイルがなければ、ディレクトリの書き込み権限を確認
            if (!is_writable(dirname($file_path))) {
                return false;
            }
        }
        return true;
    }

    /**
     * CSVファイル書き出し
     * @param string $file_path
     * @param array $csv_lines
     */
    private function write_csv($file_path, $csv_lines)
    {
        $handle = fopen($file_path, 'a');
		if (flock($handle, LOCK_EX)) {
            foreach ($csv_lines as $csv) {
                if (strtoupper($this->config['csv']['char_code']) !== self::SYSTEM_CHAR_CODE) {
                    mb_convert_variables($this->config['csv']['char_code'], self::SYSTEM_CHAR_CODE, $csv);
                }
                fputcsv($handle, $csv);
            }
		}
		flock($handle,LOCK_UN);
		fclose($handle);
    }

    /**
     * エラー画面出力
     * @param string $message エラーメッセージ
     */
    private function error_screen($message)
    {
        // テンプレートを取得
        $html = $this->get_template($this->config['step']['error']);
        $html->find('#' . $this->message_config['message']['error_message_id'], 0)->outertext = $this->html_escape($message);

        // HTML書き出し
        $this->render($html);

        // 処理終了
        die;
    }

    /**
     * HTML書き出し
     * @param simple_html_dom $html
     */
    private function render($html)
    {
        include_once 'Me_Guard.php';
        Me_Guard::render($html, $this->convert_char_code, $this->config['global']['char_code'], self::SYSTEM_CHAR_CODE);
    }

    /**
     * メール本文に入力値を置換する
     * @param string $str
     * @param boolean $subject_mode
     * @return string $str
     */
    private function replace_text($str, $subject_mode = false)
    {
        foreach ($this->form_item as $name => $item) {
            if ($subject_mode && $item['type'] === 'textarea') {
                continue;
            }
            $value = $item['value'];
            if ($item['type'] === 'checkbox' && is_array($value)) {
                $value = implode($this->config['checkbox']['delimiter'], $value);
            } else if ($item['type'] === 'select' && is_array($value)) {
                $value = implode($this->config['select']['delimiter'], $value);
            }
            $str = str_replace('{' . $name . '}', $value, $str);
        }
        return $str;
    }

    /**
     * item.iniもしくはmessage.iniからエラーメッセージを取得して$itemにセットする
     * @param array & $item
     * @param string $key
     * @param array $replace
     */
    private function set_error_message(&$item, $key, $replace = array())
    {
        if (strlen($item[$key]) > 0) {
            // item固有のエラーメッセージを持っている場合
            $item['error'] = $item[$key];
        } else {
            // 共通エラーメッセージ
            $item['error'] = $this->message_config['message'][$key];
        }
        // 置換処理
        foreach ($replace as $search_key => $value) {
            $item['error'] = str_replace($search_key, $value, $item['error']);
        }
    }

    /**
     * トークンを生成してinput要素を返す
     */
    private function get_token()
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = sha1(session_id() . microtime());
        }
        return $this->get_hidden_tag('_token', $this->html_escape($_SESSION['_token']));
    }

    /**
     * html特殊文字をエンティティ化する
     * @param string $str エンティティ化対象文字列
     * @return string 特殊文字をエンティティ化した文字列
     */
    private function html_escape($str)
    {
        return htmlspecialchars($str, ENT_QUOTES, self::SYSTEM_CHAR_CODE);
    }

    /**
     * html escapeしたうえで、改行をbrタグに変換する
     * @param string $str 出力する文字列
     * @return string
     */
    private function nl2br_escape($str)
    {
        if (PHP_VERSION_ID >= 50300) {
            if ($this->config['global']['xhtml']) {
                return nl2br($this->html_escape($str), true);
            } else {
                return nl2br($this->html_escape($str), false);
            }
        } else {
            return nl2br($this->html_escape($str));
        }
    }

    /**
     * xhtmlの場合は " /" を返す
     * @return string
     */
    private function self_closing_tag()
    {
        return ($this->config['global']['xhtml']) ? ' /' : '';
    }

    /**
     * カンマ区切りの文字列を分割して配列で返す
     * @param string $str
     * @return array $value_list
     */
    private function str_to_array($str)
    {
        $value_list = array();
        foreach (explode(',', $str) as $value) {
            if (strlen($value) > 0) {
                $value_list[] = trim($value);
            }
        }
        return $value_list;
    }

    /**
     * 複数のtoアドレスを指定できるqdmail用の形式に変換する
     * @param string $config_value
     * @return array $address_list
     */
    private function multi_address($config_value)
    {
        $address_list = array();
        foreach (explode(',', $config_value) as $value) {
            if (strlen($value) > 0) {
                $address_list[] = array(trim($value), '');
            }
        }
        return $address_list;
    }
}
