#!/usr/bin/env php
<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage billing
 * @author     Adam Armstrong <adama@memetic.org>
 * @copyright  (C) 2006 - 2012 Adam Armstrong
 */

chdir(dirname($argv[0]));

require 'includes/defaults.inc.php';
require 'config.php';
require 'includes/definitions.inc.php';
require 'includes/functions.php';

$options = getopt('r');

if (isset($options['r'])) {
    echo "Clearing history table.\n";
    dbQuery('TRUNCATE TABLE `bill_history`');
}

foreach (dbFetchRows('SELECT * FROM `bills` ORDER BY `bill_id`') as $bill) {
    echo str_pad($bill['bill_id'].' '.$bill['bill_name'], 30)." \n";

    $i = 0;
    while ($i <= 24) {
        unset($class);
        unset($rate_data);
        $day_data = getDates($bill['bill_day'], $i);

        $datefrom = $day_data['0'];
        $dateto   = $day_data['1'];

        $check = dbFetchRow('SELECT * FROM `bill_history` WHERE bill_id = ? AND bill_datefrom = ? AND bill_dateto = ? LIMIT 1', array($bill['bill_id'], $datefrom, $dateto));

        $period = getPeriod($bill['bill_id'], $datefrom, $dateto);

        $date_updated = str_replace('-', '', str_replace(':', '', str_replace(' ', '', $check['updated'])));

        if ($period > 0 && $dateto > $date_updated) {
            $rate_data    = getRates($bill['bill_id'], $datefrom, $dateto);
            $rate_95th    = $rate_data['rate_95th'];
            $dir_95th     = $rate_data['dir_95th'];
            $total_data   = $rate_data['total_data'];
            $rate_average = $rate_data['rate_average'];

            if ($bill['bill_type'] == 'cdr') {
                $type         = 'CDR';
                $allowed      = $bill['bill_cdr'];
                $used         = $rate_data['rate_95th'];
                $allowed_text = format_si($allowed).'bps';
                $used_text    = format_si($used).'bps';
                $overuse      = ($used - $allowed);
                $overuse      = (($overuse <= 0) ? '0' : $overuse);
                $percent      = round((($rate_data['rate_95th'] / $bill['bill_cdr']) * 100), 2);
            } elseif ($bill['bill_type'] == 'quota') {
                $type         = 'Quota';
                $allowed      = $bill['bill_quota'];
                $used         = $rate_data['total_data'];
                $allowed_text = format_bytes_billing($allowed);
                $used_text    = format_bytes_billing($used);
                $overuse      = ($used - $allowed);
                $overuse      = (($overuse <= 0) ? '0' : $overuse);
                $percent      = round((($rate_data['total_data'] / $bill['bill_quota']) * 100), 2);
            }

            echo strftime('%x @ %X', strtotime($datefrom)).' to '.strftime('%x @ %X', strtotime($dateto)).' '.str_pad($type, 8).' '.str_pad($allowed_text, 10).' '.str_pad($used_text, 10).' '.$percent.'%';

            if ($i == '0') {
                $update = array(
                    'rate_95th'        => $rate_data['rate_95th'],
                    'rate_95th_in'     => $rate_data['rate_95th_in'],
                    'rate_95th_out'    => $rate_data['rate_95th_out'],
                    'dir_95th'         => $rate_data['dir_95th'],
                    'total_data'       => $rate_data['total_data'],
                    'total_data_in'    => $rate_data['total_data_in'],
                    'total_data_out'   => $rate_data['total_data_out'],
                    'rate_average'     => $rate_data['rate_average'],
                    'rate_average_in'  => $rate_data['rate_average_in'],
                    'rate_average_out' => $rate_data['rate_average_out'],
                    'bill_last_calc'   => array('NOW()'),
                );

                dbUpdate($update, 'bills', '`bill_id` = ?', array($bill['bill_id']));
                echo ' Updated! ';
            }

            if ($check['bill_id'] == $bill['bill_id']) {
                $update = array(
                    'rate_95th'        => $rate_data['rate_95th'],
                    'rate_95th_in'     => $rate_data['rate_95th_in'],
                    'rate_95th_out'    => $rate_data['rate_95th_out'],
                    'dir_95th'         => $rate_data['dir_95th'],
                    'rate_average'     => $rate_data['rate_average'],
                    'rate_average_in'  => $rate_data['rate_average_in'],
                    'rate_average_out' => $rate_data['rate_average_out'],
                    'traf_total'       => $rate_data['total_data'],
                    'traf_in'          => $rate_data['total_data_in'],
                    'traf_out'         => $rate_data['total_data_out'],
                    'bill_used'        => $used,
                    'bill_overuse'     => $overuse,
                    'bill_percent'     => $percent,
                    'updated'          => array('NOW()'),
                );

                dbUpdate($update, 'bill_history', '`bill_hist_id` = ?', array($check['bill_hist_id']));
                echo ' Updated history! ';
            } else {
                $update = array(
                    'rate_95th'        => $rate_data['rate_95th'],
                    'rate_95th_in'     => $rate_data['rate_95th_in'],
                    'rate_95th_out'    => $rate_data['rate_95th_out'],
                    'dir_95th'         => $rate_data['dir_95th'],
                    'rate_average'     => $rate_data['rate_average'],
                    'rate_average_in'  => $rate_data['rate_average_in'],
                    'rate_average_out' => $rate_data['rate_average_out'],
                    'traf_total'       => $rate_data['total_data'],
                    'traf_in'          => $rate_data['total_data_in'],
                    'traf_out'         => $rate_data['total_data_out'],
                    'bill_datefrom'    => $datefrom,
                    'bill_dateto'      => $dateto,
                    'bill_type'        => $type,
                    'bill_allowed'     => $allowed,
                    'bill_used'        => $used,
                    'bill_overuse'     => $overuse,
                    'bill_percent'     => $percent,
                    'bill_datefrom'    => $datefrom,
                    'bill_dateto'      => $dateto,
                    'bill_id'          => $bill['bill_id'],
                );
                dbInsert($update, 'bill_history');
                echo ' Generated history! ';
            }//end if
            echo "\n\n";
        }//end if

        $i++;
    }//end while
}//end foreach
