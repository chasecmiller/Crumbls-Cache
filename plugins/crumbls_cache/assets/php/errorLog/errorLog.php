<?php

namespace crumbls;

class errorLog
{
    public function __construct()
    {
        set_error_handler([$this, 'errorHandler']);//, E_ERROR ^ E_CORE_ERROR ^ E_COMPILE_ERROR ^ E_USER_ERROR ^ E_RECOVERABLE_ERROR ^ E_WARNING ^ E_CORE_WARNING ^ E_COMPILE_WARNING ^ E_USER_WARNING ^ E_NOTICE ^ E_USER_NOTICE ^ E_DEPRECATED ^ E_USER_DEPRECATED ^ E_PARSE);

        add_action('wp_footer', [$this, 'footer_error_msg']); //Add the above function to the WP footer area.

        add_action('wp_head', [$this, 'style_footer_errors']);  //Add the styling to the site header.

    }

    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        echo $errno;
        exit;
        global $php_error_msg; //Add the global variable $php_error_msg, this variable will pass the errors to the footer message on the front-end.
        $errorType = array(
            E_ERROR => 'ERROR',
            E_CORE_ERROR => 'CORE ERROR',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_USER_ERROR => 'USER ERROR',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_WARNING => 'WARNING',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_WARNING => 'USER WARNING',
            E_NOTICE => 'NOTICE',
            E_USER_NOTICE => 'USER NOTICE',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
            E_PARSE => 'PARSING ERROR'
        );

        if (array_key_exists($errno, $errorType)) {
            $errname = $errorType[$errno];
        } else {
            $errname = 'UNKNOWN ERROR';
        }
        ob_start(); ?>
        <div class="error">
            <p>
                <strong><?php echo $errname; ?> Error: [<?php echo $errno; ?>] </strong><?php echo $errstr; ?>
                <strong> <?php echo $errfile; ?></strong> on line <strong><?php echo $errline; ?></strong>
            </p>
        </div>
        <?php
        $php_error_msg = ob_get_clean(); //Store the errors in the global for future use/
        if (is_admin()) {
            echo $php_error_msg;
            exit;
        } //Display the errors *only* if in the admin panel; front-end errors will be handled differently.
        else {
            return;
        }
    }


    function footer_error_msg()
    { //Add the function that will print errors in the site footer area.
        global $php_error_msg;
        echo $php_error_msg;
    }

    function style_footer_errors()
    {  //Function to add styling to the printed errors, to guarantee visibility - edit the CSS however you want.
        echo '<style> div.error{ background: black; color: red; } </style>';
    }

}


