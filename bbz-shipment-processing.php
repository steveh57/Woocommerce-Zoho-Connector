<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * BBZ Shipment Order Processing
 */
 const BBZ_MAX_TRACKING_BATCH = 5;  //limit the number we add each time.
 
 function bbz_load_shipments_from_zoho () {
 
	$zoho = new zoho_shipmentorders;

	
	$filter = array('filter_by' => 'Status.Shipped');
	$live_shipments = $zoho->get_shipmentorders ($filter);
	
	if (is_wp_error ($live_shipments) ){
		$live_shipments->add ('bbz-so-001', 'Error getting live shipments', array('filter'=>$filter));
		return $live_shipments;
	}
	
	$db = new bbz_shipmentdb;
	$db_shipments = $db->getall_ids();
	//echo 'db_shipments<br>';
	//echo '<pre>'; print_r ($db_shipments); echo '</pre>';
	
	// find any new shipments
	$new_shipments = array ();
	$ship_count = 0;
	foreach ($live_shipments as $shipment) {
		if (!in_array($shipment['shipment_id'], $db_shipments)) {  // check if we already have this one
			echo $shipment['shipment_id'].' not in db<br>';	
			$result = true; 
			$db->insert_zoho_shipment ($shipment);
			// and then create a tracking
			if (is_wp_error ($result) ) {
				$result->add('bbz-sp-001', 'Zoho load shipment failed', array (
					'processed'=>$new_shipments,
					'failed'=>$shipment
					));
				bbz_debug ($result,'',false);  //display error and continue
			} else {
				// success, we have a new shipment
				if ($shipment ['carrier'] == 'DX') {
					// For DX we need the postcode and country
					$ship_detail = $zoho->get_shipmentorder_by_id($shipment['shipment_id']);
					if (is_wp_error ($ship_detail) ){
						$ship_detail->add ('bbz-so-001', 'Error getting live shipments', array('filter'=>$filter));
						bbz_debug ($ship_detail, '', false);
					}
					$shipment['zip'] = $ship_detail ['shipping_address']['zip'];
					$shipment['country'] = $ship_detail ['shipping_address']['country'];
				}
				if ($shipment ['carrier'] == 'DX') {  //testing only load DX
				$new_shipments[] = $shipment;
				$ship_count += 1;
				}  //testing only load DX
				if ( $ship_count >= BBZ_MAX_TRACKING_BATCH) break;  // limit number to add at one time
				
			}
		}
	}
	bbz_debug ($new_shipments, 'New shipments to add', false);
	
	if (!empty ($new_shipments) ) {
		$tracking = new bbz_tracking;
		$result = $tracking->create ($new_shipments);
		if (is_wp_error ($result) ){
			$result->add ('bbz-so-001', 'Error creating tracking in shipment-processing', $new_shipments);
			bbz_debug ($result, '', false);
		}
		return $result;
	}
	
function bbz_update_shipments_db () {
	$db = new bbz_shipmentdb;
	$db_shipments = $db->get_active_shipments();
	bbz_debug ($db_shipments, 'Active shipments in db', false);
	
	$tracking = new bbz_tracking;
	foreach ($db_shipments as $shipment)
		$result = $tracking->get ($shipment);  // returns updated shipment record
		if (is_wp_error ($result) ){
			$result->add ('bbz-so-011', 'Error getting tracking in bbz_update_shipments_db', $shipment);
			bbz_debug ($result, '', false);
		}
		
		// Now update db with new status etc.
		$result = $db->update_shipment ($shipment);
		if (is_wp_error ($result) ){
			$result->add ('bbz-so-012', 'Error updating db in bbz_update_shipments_db', $shipment);
			bbz_debug ($result, '', false);
		}
		
	}	
}

 ?>