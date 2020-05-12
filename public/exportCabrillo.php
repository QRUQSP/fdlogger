<?php
//
// Description
// -----------
// This method will return everything for the UI for Field Day Logger
//
// Cabrillo spec found at: http://wwrof.org/cabrillo/cabrillo-specification-v3/
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get QSO for.
//
// Returns
// -------
//
function qruqsp_fielddaylog_exportCabrillo($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'fielddaylog', 'private', 'checkAccess');
    $rc = qruqsp_fielddaylog_checkAccess($ciniki, $args['tnid'], 'qruqsp.fielddaylog.qsoList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'qruqsp_fielddaylog_settings', 'tnid', $args['tnid'], 'qruqsp.fielddaylog', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.fielddaylog.9', 'msg'=>'', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');

    //
    // Get the list of qsos
    //
    $strsql = "SELECT qruqsp_fielddaylog_qsos.id, "
        . "qruqsp_fielddaylog_qsos.qso_dt, "
        . "DATE_FORMAT(qruqsp_fielddaylog_qsos.qso_dt, '%Y-%m-%d %H%i') AS qso_dt_display, "
        . "qruqsp_fielddaylog_qsos.callsign, "
        . "qruqsp_fielddaylog_qsos.class, "
        . "qruqsp_fielddaylog_qsos.section, "
        . "qruqsp_fielddaylog_qsos.band, "
        . "qruqsp_fielddaylog_qsos.mode, "
        . "qruqsp_fielddaylog_qsos.frequency, "
        . "qruqsp_fielddaylog_qsos.operator "
        . "FROM qruqsp_fielddaylog_qsos "
        . "WHERE qruqsp_fielddaylog_qsos.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND YEAR(qso_dt) = 2020 "
        . "ORDER BY qso_dt ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.fielddaylog', array(
        array('container'=>'qsos', 'fname'=>'id', 
            'fields'=>array('id', 'qso_dt', 'qso_dt_display', 'callsign', 'class', 'section', 'band', 'mode', 'frequency', 'operator'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $qsos = isset($rc['qsos']) ? $rc['qsos'] : array();

    //
    // Process QSOs
    //
    $cabrillo_qsos = '';
    $bands = array();
    $modes = array();
    $qso_points = 0;
    foreach($qsos as $qso) {
        if( !in_array($qso['band'], $bands) ) {
            $bands[] = $qso['band'];
        }
        if( !in_array($qso['mode'], $modes) ) {
            $modes[] = $qso['mode'];
        }
        if( $qso['mode'] == 'CW' || $qso['mode'] == 'DIG' ) {
            $qso_points += 2;
        } else {
            $qso_points += 1;
        }
        $cabrillo_qsos .= "QSO: ";
        if( $qso['frequency'] != '' ) {
            $qso['frequency'] = preg_replace("/[^0-9]/", "", $qso['frequency']);
        } else {
            switch($qso['band']) {
                case 160: $qso['frequency'] = 1800; break;
                case 80: $qso['frequency'] = 3500; break;
                case 40: $qso['frequency'] = 7000; break;
                case 20: $qso['frequency'] = 14000; break;
                case 15: $qso['frequency'] = 21000; break;
                case 10: $qso['frequency'] = 28000; break;
                case 6: $qso['frequency'] = 50; break;
                case 2: $qso['frequency'] = 144; break;
                case 220: $qso['frequency'] = 222; break;
                case 440: $qso['frequency'] = 70; break;
            }
        }
        $cabrillo_qsos .= sprintf(" %5s", $qso['frequency']);

        if( $qso['mode'] == 'DIG' ) {
            $cabrillo_qsos .= " DG";
        } else {
            $cabrillo_qsos .= " " . $qso['mode'];
        }
        $cabrillo_qsos .= " " . $qso['qso_dt_display'];
        $cabrillo_qsos .= sprintf(" %-13s", (isset($settings['callsign']) ? $settings['callsign'] : ''));
        $cabrillo_qsos .= sprintf(" %-6s", (isset($settings['class']) ? $settings['class'] : ''));
        $cabrillo_qsos .= sprintf(" %-3s", (isset($settings['section']) ? $settings['section'] : ''));
        $cabrillo_qsos .= sprintf(" %-13s", $qso['callsign']);
        $cabrillo_qsos .= sprintf(" %-4s", $qso['class']);
        $cabrillo_qsos .= sprintf(" %-3s", $qso['section']);
        $cabrillo_qsos .= "\n";
    }

    $cabrillo = '';
    $cabrillo .= "START-OF-LOG: 3.0\n";
    $cabrillo .= "LOCATION: " . (isset($settings['section']) ? $settings['section'] : '') . "\n";
    $cabrillo .= "CALLSIGN: " . (isset($settings['callsign']) ? $settings['callsign'] : '') . "\n";
    $cabrillo .= "CONTEST: ARRL-FIELD-DAY\n";
    $cabrillo .= "CLUB: " . (isset($settings['club']) ? $settings['club'] : '') . "\n";
    $cabrillo .= "CATEGORY-OPERATOR: " . (isset($settings['category-operator']) ? $settings['category-operator'] : '') . "\n";
    $cabrillo .= "CATEGORY-ASSISTED: " . (isset($settings['category-assisted']) ? $settings['category-assisted'] : '') . "\n";
    if( count($bands) > 1 ) {
        $cabrillo .= "CATEGORY-BAND: ALL\n";
    } elseif( count($bands) == 1 ) {
        $cabrillo .= "CATEGORY-BAND: " . $bands[0] . "\n";
    } else {
        $cabrillo .= "CATEGORY-BAND: \n";
    }
    if( count($modes) > 1 ) {
        $cabrillo .= "CATEGORY-MODE: MIXED\n";
    } elseif( count($modes) == 1 ) {
        $cabrillo .= "CATEGORY-MODE: " . $modes[0] . "\n";
    } else {
        $cabrillo .= "CATEGORY-MODE: \n";
    }
    if( isset($settings['category-power']) && $settings['category-power'] == 'QRP-BATTERY' ) {
        $cabrillo .= "CATEGORY-POWER: QRP\n";
        $score = $qso_points * 5;
    } elseif( isset($settings['category-power']) && $settings['category-power'] == 'QRP' ) {
        $cabrillo .= "CATEGORY-POWER: QRP\n";
        $score = $qso_points * 2;
    } elseif( isset($settings['category-power']) && $settings['category-power'] == 'LOW' ) {
        $cabrillo .= "CATEGORY-POWER: LOW\n";
        $score = $qso_points * 2;
    } else {
        $cabrillo .= "CATEGORY-POWER: " . (isset($settings['category-power']) ? $settings['category-power'] : '') . "\n";
        $score = $qso_points;
    }
    $cabrillo .= "CATEGORY-STATION: " . (isset($settings['category-station']) ? $settings['category-station'] : 'FIXED') . "\n";
    $cabrillo .= "CATEGORY-TRANSMITTER: " . (isset($settings['category-transmitter']) ? $settings['category-transmitter'] : '') . "\n";
    $cabrillo .= "CLAIMED-SCORE: " . $score . "\n";
    $cabrillo .= "NAME: " . (isset($settings['name']) ? $settings['name'] : '') . "\n";
    $cabrillo .= "ADDRESS: " . (isset($settings['address']) ? $settings['address'] : '') . "\n";
    $cabrillo .= "ADDRESS-CITY: " . (isset($settings['city']) ? $settings['city'] : '') . "\n";
    $cabrillo .= "ADDRESS-STATE-PROVINCE: " . (isset($settings['state']) ? $settings['state'] : '') . "\n";
    $cabrillo .= "ADDRESS-POSTALCODE: " . (isset($settings['postal']) ? $settings['postal'] : '') . "\n";
    $cabrillo .= "ADDRESS-COUNTRY: " . (isset($settings['country']) ? $settings['country'] : '') . "\n";

    $cabrillo .= "CREATED-BY: QRUQSP.org FieldDayLogger2020\n";

    $cabrillo .= $cabrillo_qsos;
    $cabrillo .= "END-OF-LOG:\n";

    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT', true, 200);
    header("Content-type: text/plain");
    header('Content-Disposition: attachment; filename="fieldday2020.log"');

    print $cabrillo;
    
    return array('stat'=>'exit');
}
?>
