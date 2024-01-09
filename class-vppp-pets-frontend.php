<?php

class Pets_Frontend {


	private $db_interface_pets;
	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version, $db_pets ) {

		$this->plugin_name       = $plugin_name;
		$this->version           = $version;
		$this->db_interface_pets = $db_pets;
	}

	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/vppp-pets-frontend.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/vppp-pets-frontend.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name.'-prescriptions-frontend', plugin_dir_url( __FILE__ ) . 'js/vppp-prescriptions-frontend.js', array( 'jquery' ), $this->version, false );

		$localized_data = array(
			'ajaxurl'       => admin_url('admin-ajax.php'),
			'ajax_nonce'    => wp_create_nonce( 'vetpharmacy_ajax_nonce' ),
		);
		
		wp_localize_script($this->plugin_name.'-prescriptions-frontend', 'Vet_Pharmacy_Plugin_frontend', $localized_data);

	}

	public function add_pets_my_account_tab( $menu_links ) {

		$menu_links = array_slice( $menu_links, 0, 1, true )
		+ array( 'my-pets' => 'My Pets' )
		+ array_slice( $menu_links, 1, null, true );

		return $menu_links;
	}

	public function add_pets_my_account_endpoint() {

		add_rewrite_endpoint( 'my-pets', EP_PAGES );
	}

	public function my_pets_endpoint_content() {

		include 'partials/template-my-pets.php';
	}

	public function my_pets_action_handler() {

		if ( !empty( $_POST['add_new_pet'] ) ) {
			self::new_pet_handler();
			return;
		}

		if ( !empty( $_POST['edit_a_pet'] ) ) {
			self::edit_pet_handler();
			return;
		}

		if ( !empty( $_POST['delete_a_pet'] ) ) {
			self::delete_a_pet_handler();
			return;
		}

		return;
	}

	private function new_pet_handler() {

		$nonce = !empty( $_POST['_wpnonce'] ) ? $_POST['_wpnonce']  : '';
		if ( wp_verify_nonce( $nonce, 'new-pet-form-nonce' ) ) {

			$user    = wp_get_current_user();
			$email   = $user->user_email;
			$user_id = $user->ID;

			$pet = array(
				'owner_id'    => strval( $user_id ),
				'owner_email' => $email,
				'pet_name'    => !empty( $_POST['pet_name'] ) ? $_POST['pet_name'] : '',
				'birthday'    => !empty( $_POST['pet_birthday'] ) ? $_POST['pet_birthday'] : '',
				'species'     => !empty( $_POST['pet_species'] ) ? $_POST['pet_species'] : '',
				'breed'       => !empty( $_POST['pet_breed'] ) ? $_POST['pet_breed'] : '',
				'sex'         => !empty( $_POST['pet_sex'] ) ? $_POST['pet_sex'] : '',
			);
			$this->db_interface_pets->pet_db_insert( $pet );
		}
	}

	private function edit_pet_handler() {
		$nonce = !empty( $_POST['_wpnonce'] ) ? $_POST['_wpnonce']  : '';
		if ( wp_verify_nonce( $nonce, 'edit-pet-form-nonce' ) ) {
			$pet = array(
				'pet_name' => !empty( $_POST['pet_name']) ? $_POST['pet_name'] : '',
				'birthday' => !empty( $_POST['pet_birthday']) ? $_POST['pet_birthday'] : '',
				'species'  => !empty( $_POST['pet_species']) ? $_POST['pet_species'] : '',
				'breed'    => !empty( $_POST['pet_breed']) ? $_POST['pet_breed'] : '',
				'sex'      => !empty( $_POST['pet_sex']) ? $_POST['pet_sex'] : '',
				'id'       => !empty( $_POST['edt_pet_id']) ? $_POST['edt_pet_id'] : '',
			);
			$this->db_interface_pets->pet_db_update( $pet );
		}
	}

	private function delete_a_pet_handler() {

		$nonce  = !empty( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '';
		$pet_id = !empty( $_POST['delete_a_pet'] ) ? $_POST['delete_a_pet'] : 0;

		if ( wp_verify_nonce( $nonce, 'delete-a-pet-nonce' . $pet_id )  && !empty($pet_id ) ) {
			$this->db_interface_pets->pet_db_delete( $pet_id );
		}
	}

	// utility
	public static function logit( $obj ) {
		$logn   = array( 'source' => 'vppp-pets' );
		$logger = wc_get_logger();
		$logger->info( var_export( $obj, true ), $logn );
		return;
	}
}
