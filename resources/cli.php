<?php

/*
 * This file should only be accessed via the command line using:
 *
 * php /path/to/resources/cli.php
 */

define('IS_CLI', true);

require(dirname(__FILE__, 2) . '/public/index.php'); // Modify this path if necessary