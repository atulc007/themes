<?php
/* $Id$ */
// vim: expandtab sw=4 ts=4 sts=4:


/**
 * Formats the INSERT statements depending on the target (screen/file) of the
 * sql dump
 *
 * @param   string  the insert statement
 *
 * @global  string  the buffer containing formatted strings
 */
function PMA_myHandler($sql_insert)
{
    global $tmp_buffer;

    // Kanji encoding convert feature appended by Y.Kawada (2001/2/21)
    if (function_exists('PMA_kanji_str_conv')) {
        $sql_insert = PMA_kanji_str_conv($sql_insert, $GLOBALS['knjenc'], isset($GLOBALS['xkana']) ? $GLOBALS['xkana'] : '');
    }

    // Defines the end of line delimiter to use
    $eol_dlm = (isset($GLOBALS['extended_ins']) && ($GLOBALS['current_row'] < $GLOBALS['rows_cnt']))
             ? ','
             : ';';
    $tmp_buffer .= $sql_insert . $eol_dlm . $GLOBALS['crlf'];
} // end of the 'PMA_myHandler()' function


/**
 * Formats the INSERT statements depending on the target (screen/file) of the
 * cvs export
 *
 * Revisions: 2001-05-07, Lem9: added $add_character
 *            2001-07-12, loic1: $crlf should be used only if there is no EOL
 *                               character defined by the user
 *
 * @param   string  the insert statement
 *
 * @global  string  the character to add at the end of lines
 * @global  string  the buffer containing formatted strings
 */
function PMA_myCsvHandler($sql_insert)
{
    global $add_character;
    global $tmp_buffer;

    // Kanji encoding convert feature appended by Y.Kawada (2001/2/21)
    if (function_exists('PMA_kanji_str_conv')) {
        $sql_insert = PMA_kanji_str_conv($sql_insert, $GLOBALS['knjenc'], isset($GLOBALS['xkana']) ? $GLOBALS['xkana'] : '');
    }
    $tmp_buffer .= $sql_insert . $add_character;
} // end of the 'PMA_myCsvHandler()' function



/**
 * Get the variables sent or posted to this script and a core script
 */
require('./libraries/grab_globals.lib.php3');
require('./libraries/common.lib.php3');
require('./libraries/build_dump.lib.php3');
require('./libraries/zip.lib.php3');

/**
 * Defines the url to return to in case of error in a sql statement
 */
$err_url = 'tbl_properties.php3?' . PMA_generate_common_url($db, isset($table) ? $table : '');


/**
 * Increase time limit for script execution and initializes some variables
 */
@set_time_limit($cfg['ExecTimeLimit']);
$dump_buffer = '';
// Defines the default <CR><LF> format
$crlf        = PMA_whichCrlf();


/**
 * Ensure zipped formats are associated with the download feature
 */
if (empty($asfile)
    && (!empty($zip) || !empty($gzip) || !empty($bzip))) {
    $asfile = 1;
}


/**
 * Send headers depending on whether the user chose to download a dump file
 * or not
 */
// No download
if (empty($asfile)) {
    $backup_cfgServer = $cfg['Server'];
    include('./header.inc.php3');
    $cfg['Server'] = $backup_cfgServer;
    unset($backup_cfgServer);
    echo '<div align="' . $cell_align_left . '">' . "\n";
    echo '    <pre>' . "\n";
} // end if

// Download
else {
    // Defines filename and extension, and also mime types
    if (!isset($table)) {
        $filename = $db;
    } else {
        $filename = $table;
    }
    if (!(isset($cfg['AllowAnywhereRecoding']) && $cfg['AllowAnywhereRecoding'] && $allow_recoding)) {
        $filename = PMA_convert_string($charset, 'iso-8859-1', $filename);
    } else {
        $filename = PMA_convert_string($convcharset, 'iso-8859-1', $filename);
    }

    // Generate basic dump extension
    if ($what == 'csv' || $what == 'excel') {
        $ext       = 'csv';
        $mime_type = 'text/x-csv';
    } else if ($what == 'xml') {
        $ext       = 'xml';
        $mime_type = 'text/xml';
    } else if ($what == 'latex') {
        $ext        = 'tex';
        $mime_type = 'application/x-tex';
    } else {
        $ext       = 'sql';
        // loic1: 'application/octet-stream' is the registered IANA type but
        //        MSIE and Opera seems to prefer 'application/octetstream'
        $mime_type = (PMA_USR_BROWSER_AGENT == 'IE' || PMA_USR_BROWSER_AGENT == 'OPERA')
                   ? 'application/octetstream'
                   : 'application/octet-stream';
    }

    // If dump is going to be copressed, set correct mime_type and add
    // compression to extension
    if (isset($bzip) && $bzip == 'bzip') {
        $ext       .= '.bz2';
        $mime_type = 'application/x-bzip';
    } else if (isset($gzip) && $gzip == 'gzip') {
        $ext       .= '.gz';
        $mime_type = 'application/x-gzip';
    } else if (isset($zip) && $zip == 'zip') {
        $ext       .= '.zip';
        $mime_type = 'application/x-zip';
    }

    $now = gmdate('D, d M Y H:i:s') . ' GMT';
} // end download


/**
 * Builds the dump
 */
// Gets the number of tables if a dump of a database has been required
if (!isset($table)) {
    $tables     = PMA_mysql_list_tables($db);
    $num_tables = ($tables) ? @mysql_numrows($tables) : 0;
} else {
    $num_tables = 1;
    $single     = TRUE;
}

// No table -> error message
if ($num_tables == 0) {
    echo '# ' . $strNoTablesFound;
}
// At least one table -> do the work
else {
    // No csv or xml or latex format -> add some comments at the top

    if ($what != 'csv' &&  $what != 'excel' && $what != 'xml' && $what != 'latex') {
        $dump_buffer       .= '# phpMyAdmin MySQL-Dump' . $crlf
                           .  '# version ' . PMA_VERSION . $crlf
                           .  '# http://www.phpmyadmin.net/ (download page)' . $crlf
                           .  '#' . $crlf
                           .  '# ' . $strHost . ': ' . $cfg['Server']['host'];
        if (!empty($cfg['Server']['port'])) {
            $dump_buffer   .= ':' . $cfg['Server']['port'];
        }
        $formatted_db_name = (isset($use_backquotes))
                           ? PMA_backquote($db)
                           : '\'' . $db . '\'';
        $dump_buffer       .= $crlf
                           .  '# ' . $strGenTime . ': ' . PMA_localisedDate() . $crlf
                           .  '# ' . $strServerVersion . ': ' . substr(PMA_MYSQL_INT_VERSION, 0, 1) . '.' . (int) substr(PMA_MYSQL_INT_VERSION, 1, 2) . '.' . (int) substr(PMA_MYSQL_INT_VERSION, 3) . $crlf
                           .  '# ' . $strPHPVersion . ': ' . phpversion() . $crlf
                           .  '# ' . $strDatabase . ': ' . $formatted_db_name . $crlf;

        $i = 0;
        if (isset($table_select)) {
            $tmp_select = implode($table_select, '|');
            $tmp_select = '|' . $tmp_select . '|';
        }
        while ($i < $num_tables) {
            if (!isset($single)) {
                $table = PMA_mysql_tablename($tables, $i);
            }
            if (isset($tmp_select) && !strpos(' ' . $tmp_select, '|' . $table . '|')) {
                $i++;
            } else {
                $formatted_table_name = (isset($use_backquotes))
                                      ? PMA_backquote($table)
                                      : '\'' . $table . '\'';
                // If only datas, no need to displays table name
                if ($what != 'dataonly') {
                    $dump_buffer .= '# --------------------------------------------------------' . $crlf
                                 .  $crlf . '#' . $crlf
                                 .  '# ' . $strTableStructure . ' ' . $formatted_table_name . $crlf
                                 .  '#' . $crlf . $crlf
                                 .  PMA_getTableDef($db, $table, $crlf, $err_url) . ';' . $crlf;
                }
                if (function_exists('PMA_kanji_str_conv')) { // Y.Kawada
                    $dump_buffer = PMA_kanji_str_conv($dump_buffer, $knjenc, isset($xkana) ? $xkana : '');
                }
                // At least data
                if (($what == 'data') || ($what == 'dataonly')) {
                    $tcmt = $crlf . '#' . $crlf
                                 .  '# ' . $strDumpingData . ' ' . $formatted_table_name . $crlf
                                 .  '#' . $crlf .$crlf;
                    if (function_exists('PMA_kanji_str_conv')) { // Y.Kawada
                        $dump_buffer .= PMA_kanji_str_conv($tcmt, $knjenc, isset($xkana) ? $xkana : '');
                    } else {
                        $dump_buffer .= $tcmt;
                    }
                    $tmp_buffer  = '';
                    if (!isset($limit_from) || !isset($limit_to)) {
                        $limit_from = $limit_to = 0;
                    }
                    PMA_getTableContent($db, $table, $limit_from, $limit_to, 'PMA_myHandler', $err_url, (isset($sql_query)?urldecode($sql_query):''));

                    $dump_buffer .= $tmp_buffer;
                } // end if
                $i++;
            } // end if-else
        } // end while

        // staybyte: don't remove, it makes easier to select & copy from
        // browser
        $dump_buffer .= $crlf;
    } // end 'no csv or xml' case

    // 'xml' case
    else if ($GLOBALS['what'] == 'xml') {
        // first add the xml tag
        $dump_buffer         .= '<?xml version="1.0" encoding="' . (empty($asfile) ? $charset : (isset($charset_of_file) ? $charset_of_file : $charset)) . '"?>' . $crlf . $crlf;
        // some comments
        $dump_buffer         .= '<!--' . $crlf
                             .  '-' . $crlf
                             .  '- phpMyAdmin XML-Dump' . $crlf
                             .  '- version ' . PMA_VERSION . $crlf
                             .  '- http://www.phpmyadmin.net/ (download page)' . $crlf
                             .  '-' . $crlf
                             .  '- ' . $strHost . ': ' . $cfg['Server']['host'];
        if (!empty($cfg['Server']['port'])) {
            $dump_buffer     .= ':' . $cfg['Server']['port'];
        }
        $dump_buffer         .= $crlf
                             .  '- ' . $strGenTime . ': ' . PMA_localisedDate() . $crlf
                             .  '- ' . $strServerVersion . ': ' . substr(PMA_MYSQL_INT_VERSION, 0, 1) . '.' . (int) substr(PMA_MYSQL_INT_VERSION, 1, 2) . '.' . (int) substr(PMA_MYSQL_INT_VERSION, 3) . $crlf
                             .  '- ' . $strPHPVersion . ': ' . phpversion() . $crlf
                             .  '- ' . $strDatabase . ': \'' . $db . '\'' . $crlf
                             .  '-' . $crlf
                             .  '-->' . $crlf . $crlf;
        // Now build the structure
        // todo: Make db and table names XML compatible
        $dump_buffer         .= '<' . $db . '>' . $crlf;
        if (isset($table_select)) {
            $tmp_select = implode($table_select, '|');
            $tmp_select = '|' . $tmp_select . '|';
        }
        $i = 0;
        while ($i < $num_tables) {
            if (!isset($single)) {
                $table = PMA_mysql_tablename($tables, $i);
            }
            if (!isset($limit_from) || !isset($limit_to)) {
                $limit_from = $limit_to = 0;
            }
            if ((isset($tmp_select) && strpos(' ' . $tmp_select, '|' . $table . '|'))
                || (!isset($tmp_select) && !empty($table))) {
                $dump_buffer .= PMA_getTableXML($db, $table, $limit_from, $limit_to, $crlf, $err_url, 
                    (isset($sql_query)?urldecode($sql_query):''));
            }
            $i++;
        }
        $dump_buffer         .= '</' . $db . '>' . $crlf;
    } // end 'xml' case

    // latex case
    else if ($GLOBALS['what'] == 'latex') {


        $dump_buffer   .=    '% ' . $crlf
            .  '% phpMyAdmin LaTeX-Dump' . $crlf
            .  '% version ' . PMA_VERSION . $crlf
            .  '% http://www.phpmyadmin.net/ (download page)' . $crlf
            .  '%' . $crlf
            .  '% ' . $strHost . ': ' . $cfg['Server']['host'] . $crlf
            .  '%' . $crlf;

        if (isset($table_select)) {
            $tmp_select = implode($table_select, '|');
            $tmp_select = '|' . $tmp_select . '|';
        }

        $i = 0;
        while ($i < $num_tables) {

            if (!isset($single)) {
                $table = PMA_mysql_tablename($tables, $i);
            }
            if (!isset($limit_from) || !isset($limit_to)) {
                $limit_from = $limit_to = 0;
            }
            if ((isset($tmp_select) && strpos(' ' . $tmp_select, '|' . $table . '|'))
                || (!isset($tmp_select) && !empty($table))) {

                // to do: add option for the formatting ( c, l, r, p)
                $dump_buffer .= PMA_getTableLatex($db, $table, $environment, $limit_from, $limit_to, $crlf, $err_url, 
                    (isset($sql_query)?urldecode($sql_query):''));
            }
            $i++;
        }

    } //end latex case

    // 'csv' case
    else {
        // Handles the EOL character
        if ($GLOBALS['what'] == 'excel') {
            $add_character = "\015\012";
        } else if (empty($add_character)) {
            $add_character = $GLOBALS['crlf'];
        } else {
            if (get_magic_quotes_gpc()) {
                $add_character = stripslashes($add_character);
            }
            $add_character = str_replace('\\r', "\015", $add_character);
            $add_character = str_replace('\\n', "\012", $add_character);
            $add_character = str_replace('\\t', "\011", $add_character);
        } // end if

        $tmp_buffer = '';
        PMA_getTableCsv($db, $table, $limit_from, $limit_to, $separator, $enclosed, $escaped, 'PMA_myCsvHandler', $err_url
            , (isset($sql_query)?urldecode($sql_query):''));
        $dump_buffer .= $tmp_buffer;
    } // end 'csv case
} // end building the dump


/**
 * Send the dump as a file...
 */
if (!empty($asfile)) {
    // Convert the charset if required.
    if ($GLOBALS['cfg']['AllowAnywhereRecoding'] && $GLOBALS['allow_recoding']
        && isset($GLOBALS['charset_of_file']) && $GLOBALS['charset_of_file'] != $GLOBALS['charset']
        && (!empty($GLOBALS['asfile']))) {
        $dump_buffer = PMA_convert_string($GLOBALS['charset'], $GLOBALS['charset_of_file'], $dump_buffer);
    }

    // Do the compression
    // 1. as a gzipped file
    if (isset($zip) && $zip == 'zip') {
        if (PMA_PHP_INT_VERSION >= 40000 && @function_exists('gzcompress')) {
            if ($what == 'csv' || $what == 'excel') {
                $extbis = '.csv';
            } else if ($what == 'xml') {
                $extbis = '.xml';
            } else {
                $extbis = '.sql';
            }
            $zipfile = new zipfile();
            $zipfile -> addFile($dump_buffer, $filename . $extbis);
            $dump_buffer = $zipfile -> file();
        }
    }
    // 2. as a bzipped file
    else if (isset($bzip) && $bzip == 'bzip') {
        if (PMA_PHP_INT_VERSION >= 40004 && @function_exists('bzcompress')) {
            $dump_buffer = bzcompress($dump_buffer);
            if ($dump_buffer === -8) {
                include('./header.inc.php3');
                echo sprintf($strBzError, '<a href="http://bugs.php.net/bug.php?id=17300" target="_blank">17300</a>');
                include('./footer.inc.php3');
                exit;
            }
        }
    }
    // 3. as a gzipped file
    else if (isset($gzip) && $gzip == 'gzip') {
        if (PMA_PHP_INT_VERSION >= 40004 && @function_exists('gzencode')) {
            // without the optional parameter level because it bug
            $dump_buffer = gzencode($dump_buffer);
        }
    }

    // finally send the headers and the file
    header('Content-Type: ' . $mime_type);
    header('Expires: ' . $now);
    // lem9 & loic1: IE need specific headers
    if (PMA_USR_BROWSER_AGENT == 'IE') {
        header('Content-Disposition: inline; filename="' . $filename . '.' . $ext . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '.' . $ext . '"');
        header('Pragma: no-cache');
    }
    echo $dump_buffer;
}
/**
 * Displays the dump...
 */
else {
    echo htmlspecialchars($dump_buffer);
    /**
     * Close the html tags and add the footers in dump is displayed on screen
     */
    echo '    </pre>' . "\n";
    echo '</div>' . "\n";
    echo "\n";
    include('./footer.inc.php3');
} // end if
?>
