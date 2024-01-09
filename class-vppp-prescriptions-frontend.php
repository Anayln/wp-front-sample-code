<?php

class Prescription_Frontend {
	private $db_interface_prescriptions;
	private $plugin_name;
	private $version;
	private $db_interface_vet_practices;
	private $db_interface_veterinarians;
	private $db_interface_pets;

	public function __construct( $plugin_name, $version, $db_prescriptions,$db_interface_vet_practices,$db_interface_veterinarians,$db_interface_pets ) {

		$this->plugin_name       = $plugin_name;
		$this->version           = $version;
		$this->db_interface_prescriptions = $db_prescriptions;
		$this->db_interface_vet_practices = $db_interface_vet_practices;
		$this->db_interface_veterinarians = $db_interface_veterinarians;
		$this->db_interface_pets = $db_interface_pets;
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name.'-prescription', plugin_dir_url( __FILE__ ) . 'css/vppp-prescriptions-frontend.css', array(), $this->version, 'all' );		
	}

	function show_prescription_record() {

		if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'vetpharmacy_ajax_nonce')) {
			$response['msg'] = 'Noce not verify';
			$response['variant'] = 'error';
			wp_send_json($response);
		}

		$response['variant'] = 'success';
		$response['msg'] = 'data fetch successfully';
		$response['prescription_data'] = array();
		$response['pet_data'] = array();
		$response['vet_practice_data'] = array();
		$response['practice_address'] = array();
		$response['veterinarian_data'] = array();
		$response['products_data'] = array();
		$response['orders_data'] = array();

		$prescription_id = intval($_REQUEST['prescription_id']) ;
		$prescription_detail_query = $this->db_interface_prescriptions->get_single_prescription($prescription_id);

		$response['prescription_data']['prescribed_date'] = $prescription_detail_query['prescribed_date'];
		$response['prescription_data']['prescription_expiry_date'] = $prescription_detail_query['prescription_expiry_date'];
		$response['prescription_data']['owner_id'] = $prescription_detail_query['owner_id'];
		$response['prescription_data']['owner_firstname'] = $prescription_detail_query['owner_firstname'];
		$response['prescription_data']['owner_lastname'] = $prescription_detail_query['owner_lastname'];
		$response['prescription_data']['pet_id'] = $prescription_detail_query['pet_id'];
		$response['prescription_data']['vpp_vet_id'] = $prescription_detail_query['vpp_vet_id'];
		$response['prescription_data']['vpp_vet_practice_id'] = $prescription_detail_query['vpp_vet_practice_id'];
		$response['prescription_data']['prescription_status'] = $prescription_detail_query['prescription_status'];

		$pet_detail_query = $this->db_interface_pets->get_single_pet($prescription_detail_query['pet_id']);
		$vet_practices_detail_query = $this->db_interface_vet_practices->get_single_vet_practice($prescription_detail_query['vpp_vet_practice_id']);
		$veterinarians_detail_query = $this->db_interface_veterinarians->get_single_veterinarian($prescription_detail_query['vpp_vet_id']);

		$response['pet_data']['pet_name'] = $pet_detail_query['pet_name'];
		$response['pet_data']['birthday'] = $pet_detail_query['birthday'];
		$response['pet_data']['species'] = $pet_detail_query['species'];
		$response['pet_data']['breed'] = $pet_detail_query['breed'];
		$response['pet_data']['sex'] = $pet_detail_query['sex'];

		$response['vet_practice_data']['clinic_name'] = $vet_practices_detail_query['clinic_name'];
		$response['vet_practice_data']['clinic_address'] = !empty($vet_practices_detail_query['clinic_address']) ? unserialize($vet_practices_detail_query['clinic_address']) : array();
		$clinic_address = unserialize($vet_practices_detail_query['clinic_address']);		
		$response['vet_practice_data']['contact_email'] = $vet_practices_detail_query['contact_email'];
		$response['vet_practice_data']['phone'] = $vet_practices_detail_query['phone'];

		$response['practice_address']['street_address'] = $clinic_address['street_address'];
		$response['practice_address']['suburb'] = $clinic_address['suburb'];
		$response['practice_address']['city'] = $clinic_address['city'];
		$response['practice_address']['postcode'] = $clinic_address['postcode'];

		$response['veterinarian_data']['vet_name'] = $veterinarians_detail_query['vet_name'];
		$response['veterinarian_data']['registration_number'] = $veterinarians_detail_query['registration_number'];
		$response['veterinarian_data']['vet_status'] = $veterinarians_detail_query['vet_status'];
		$response['veterinarian_data']['vet_status_expiry'] = $veterinarians_detail_query['vet_status_expiry'];
		$response['veterinarian_data']['vet_practice_id'] = $veterinarians_detail_query['vet_practice_id'];
		$response['veterinarian_data']['vet_locale'] = $veterinarians_detail_query['vet_locale'];		

		$product_data = array();
		$order_data = array();		
		$prescription_product = $this->db_interface_prescriptions->get_prescription_meta($prescription_id);
		foreach($prescription_product as $key => $value) {			
			$product_data[] = array(
				'product_id' =>  $value['product_id'],
				'product_title' => get_the_title(intval( $value['product_id'])),
				'product_link' => get_permalink(intval( $value['product_id'])),
				'repeats' => $value['repeats'],
				'instructions' => $value['instructions'],
				'quantity' => $value['quantity'],
				'dispensed' => $value['dispensed']
			);
		}

		$response['products_data'] = $product_data;

		$prescription_order = $this->db_interface_prescriptions->get_prescription_assign_order($prescription_id);
		foreach ($prescription_order as $order) {
			$order_id = $order['order_id'];
			$presc_items = !empty($order['presc_items']) ? unserialize($order['presc_items']) : array();
			$filled_items = !empty($order['filled']) ? unserialize($order['filled']) : array();		
			$order_entry = array(
				'order_id' => $order_id,
				'presc_items' => array(),
				'filled_item' => array(),
			);
			if(!empty($presc_items)){
				foreach ($presc_items as $prescqty) {
					$product_id = intval($prescqty['product_id']);
					$product = wc_get_product($product_id);
					$quantity = intval($prescqty['quantity']);
					$product_prescription_only_option = $product->get_meta("product_prescription_only_option");
					$human_medication_option = $product->get_meta("human_medication_option");
					if ($product_prescription_only_option === 'yes' || $human_medication_option === 'yes') {
						$order_entry['presc_items'][] = array(
							'product_name' => $product ? $product->get_title() : '',
							'product_link' => get_permalink($product_id),
							'product_quantity' => $quantity,
						);
					}
				}
			}
			if(!empty($filled_items)){
				foreach ($filled_items as $filledqty) {
					$product_id = intval($filledqty['product_id']);
					$product = wc_get_product($product_id);
					$quantity = intval($filledqty['quantity']);
					$product_prescription_only_option = $product->get_meta("product_prescription_only_option");
					$human_medication_option = $product->get_meta("human_medication_option");
					if ($product_prescription_only_option === 'yes' || $human_medication_option === 'yes') {
						$order_entry['filled_item'][] = array(
							'product_filled' => $quantity,
						);
					}
				}
			}
			$order_data[] = $order_entry;
		}
		$response['orders_data'] = $order_data;		
		
		wp_send_json($response);
	}

	public function add_prescriptions_my_account_tab($menu_links) {
		$menu_links = array_slice( $menu_links, 0, 1, true )
		+ array( 'prescriptions' => 'Prescriptions' )
		+ array_slice( $menu_links, 1, null, true );
		return $menu_links;
	}

	public function add_prescriptions_my_account_endpoint() {
		add_rewrite_endpoint( 'prescriptions', EP_PAGES );
	}

	public function prescriptions_endpoint_content() {
		include 'partials/template-prescriptions.php';
	}	
}