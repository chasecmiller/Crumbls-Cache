<?php

// TEMP DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 'on');

// error handler function
function tempErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }


    // Ignore the common
    if (!strpos($errfile, 'crumbls_cache')) {
        return false;
    }
    $bn = basename($errfile);
    if ($bn == 'MemcacheDriverCollisionDetectorTrait.php') {
        return false;
    }

    switch ($errno) {
        case E_USER_ERROR:
            sendToLog("Fatal: [$errno]  Line $errline File $errfile Version ".PHP_VERSION." OS ".PHP_PS, $errno);
            break;
        case E_USER_WARNING:
            sendToLog("Warning: [$errno] Line $errline File $errfile: $errstr", $errno);
            break;
        case E_USER_NOTICE:
            sendToLog("Notice: [$errno] Line $errline File $errfile: $errstr", $errno);
            break;
        default:
            sendToLog("Unknown: [$errno] Line $errline File $errfile: $errstr", $errno);
    }

    /* Don't execute PHP internal error handler */
    return true;
}

function sendToLog($log, $errno = null) {
    if (is_string($log)) {
        $log = [
            'message' => $log,
        ];
    }
    if (!is_array($log)) {
        return false;
    }
    if (!array_key_exists('tag', $log)) {
        $log['tag'] = ['crumbls', 'cache'];
    }
    if ($errno) {
        $log['tag'][] = $errno;
    }
    if (!array_key_exists('domain', $log)) {
        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
            $log['domain'] = COOKIE_DOMAIN;
        } else {
            $log['domain'] = get_site_url();
        }
    }

    $data_string = json_encode($log);

    $ch = curl_init('http://logs-01.loggly.com/inputs/b7527fc6-fc88-4137-b692-b9badd3eb7a7/tag/http/');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
    );

    $result = curl_exec($ch);

}


//$old = set_error_handler('tempErrorHandler');
add_action('init', function() {
    $old = set_error_handler('tempErrorHandler');
});