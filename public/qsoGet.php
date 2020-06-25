<?php
//
// Description
// ===========
// This method will return all the information about an qso.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the qso is attached to.
// qso_id:          The ID of the qso to get the details for.
//
// Returns
// -------
//
function qruqsp_fielddaylog_qsoGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'qso_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'QSO'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'fielddaylog', 'private', 'checkAccess');
    $rc = qruqsp_fielddaylog_checkAccess($ciniki, $args['tnid'], 'qruqsp.fielddaylog.qsoGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'qruqsp_fielddaylog_settings', 'tnid', $args['tnid'], 'qruqsp.fielddaylog', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.fielddaylog.16', 'msg'=>'', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new QSO
    //
    if( $args['qso_id'] == 0 ) {
        $qso = array('id'=>0,
            'qso_dt'=>'',
            'callsign'=>'',
            'class'=>'',
            'section'=>'',
            'band'=>'',
            'mode'=>'',
            'frequency'=>'',
            'operator'=>'',
            'flags'=>0,
            'notes'=>'',
        );
    }

    //
    // Get the details for an existing QSO
    //
    else {
        $strsql = "SELECT qruqsp_fielddaylog_qsos.id, "
            . "qruqsp_fielddaylog_qsos.qso_dt, "
            . "qruqsp_fielddaylog_qsos.callsign, "
            . "qruqsp_fielddaylog_qsos.class, "
            . "qruqsp_fielddaylog_qsos.section, "
            . "qruqsp_fielddaylog_qsos.band, "
            . "qruqsp_fielddaylog_qsos.mode, "
            . "qruqsp_fielddaylog_qsos.frequency, "
            . "qruqsp_fielddaylog_qsos.operator, "
            . "qruqsp_fielddaylog_qsos.flags, "
            . "qruqsp_fielddaylog_qsos.notes "
            . "FROM qruqsp_fielddaylog_qsos "
            . "WHERE qruqsp_fielddaylog_qsos.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND qruqsp_fielddaylog_qsos.id = '" . ciniki_core_dbQuote($ciniki, $args['qso_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.fielddaylog', array(
            array('container'=>'qsos', 'fname'=>'id', 
                'fields'=>array('qso_dt', 'callsign', 'class', 'section', 'band', 'mode', 'frequency', 'operator', 'flags', 'notes'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.fielddaylog.7', 'msg'=>'QSO not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['qsos'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.fielddaylog.8', 'msg'=>'Unable to find QSO'));
        }
        $qso = $rc['qsos'][0];
    }

    return array('stat'=>'ok', 'qso'=>$qso, 'settings'=>$settings);
}
?>
