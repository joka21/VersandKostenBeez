<?php

require_once ('versandkosten-beez-lieferwoche.php');
if ( ! class_exists( 'Versand_Kosten_Beez_Shipping_Method' )) :
    class Versand_Kosten_Beez_Shipping_Method extends WC_Shipping_Method {

        // Variables for calculations
        private float $spritpreis;
        private float $spritverbrauch;
        private float $lohnkosten;
        private float $wartungskosten;
        private float $beentladen;
        private int $origin_plz;

        // Database connection for availability
        private VersandkostenBeezAvailabilityDao $versandkostenBeezAvailabilityDao;

        /**
         * Constructor for the versandkosten_beez
         * @access public
         * @return void
         */
        public function __construct(){
            parent::__construct();

            $this->id                   = 'versand-kosten-beez-shipping-method';
            $this->method_title         = ('Versand Kosten Beez Einstellungen');
            $this->method_description   = ('Einstellungen für die Berechnung der Versandkosten' );
            $this->tax_status           = "none";
            $this->init();
        }

        // This is a static variable to make sure the hooks are only loaded once
        private static bool $hooks_loaded = false;

        /**
         * Init the Versandkosten Beez shipping method
         * @return void
         */
        function init(){
            //Load database connection
            $this->versandkostenBeezAvailabilityDao = VersandkostenBeezAvailabilityDao::getInstance();

            //Load the settings API
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->spritpreis           = doubleval($this->get_option( 'spritpreis' ));
            $this->spritverbrauch       = doubleval($this->get_option( 'spritverbrauch'));
            $this->lohnkosten           = doubleval($this->get_option( 'lohnkosten' ));
            $this->wartungskosten       = doubleval($this->get_option( 'wartungskosten' ));
            $this->beentladen           = doubleval($this->get_option( 'beentladen' ));
            $this->origin_plz           = intval($this->get_option( 'origin_plz' ));

            // Define enabled and title
            $this->enabled              = $this->get_option( 'enabled' ) ?? "yes";
            $this->title                = $this->get_option( 'title' ) ?? "Versandkosten";

            if(!self::$hooks_loaded) {
                self::$hooks_loaded = true;

                // Save settings in admin if you have any defined
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

                add_filter( 'woocommerce_cart_no_shipping_available_html', array($this, 'change_no_shipping_message'));
                add_filter( 'woocommerce_no_shipping_available_html',  array($this, 'change_no_shipping_message'));

                // process checkout hook
                add_action('woocommerce_order_status_changed',  array($this, 'check_order_status_changed'), 10, 3);

                // add lieferwoche to cart item
                add_filter('woocommerce_add_cart_item_data', array($this, 'add_shipping_information_to_cart_item'), 10, 3);
                add_filter('woocommerce_get_item_data', array($this, 'show_shipping_information_in_cart'), 10, 2);
                add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_shipping_week_information_to_order'), 10, 4);
            }
        }

        /**
         * Initialize settings form fields
         * @return void
         */
        function init_form_fields(){
            $this->form_fields = array(
                'spritpreis' => array(
                    'title'             => __( 'Spritpreis'),
                    'description'       => __( 'Aktueller Spritpreis pro Liter'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'spritverbrauch' => array(
                    'title'             => __( 'Spritverbrauch / 100km'),
                    'description'       => __( 'Spritverbrauch in Litern pro 100 Kilometer'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'lohnkosten' => array(
                    'title'             => __( 'Lohnkosten'),
                    'description'       => __( 'Lohnkosten in € pro Stunde'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'wartungskosten' => array(
                    'title'             => __( 'Wartungskosten / 10000km'),
                    'description'       => __( 'Wartungskosten in € pro 10000 km'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'beentladen' => array(
                    'title'             => __( 'Be- und Entladung'),
                    'description'       => __( 'Be- und Entladungskosten €'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'origin_plz' => array(
                    'title'             => __( 'Versand Postleitzahl'),
                    'description'       => __( 'Postleitzahl des Versandorts'),
                    'type'              => 'text',
                    'css'      => 'width:170px;',
                ),
                'title' => array(
                    'title'             => __( 'Versandkosten Titel'),
                    'description'       => __( 'Bezeichnung der Versandkosten im Warenkorb'),
                    'type'              => 'text',
                    'css'      => 'width:170px;',
                ),
                'enabled' => array(
                    'title'             => __( 'Versandkosten aktivieren'),
                    'description'       => __( 'Stellt ein, ob diese Versandkosten-Methode aktiviert ist'),
                    'type'              => 'checkbox',
                ),

            );                                                                                                                                                                                        
        }


        /**
         * Display a custom error message if this shipping method is not available
         * @param $message
         * @return array|string
         */
        public function change_no_shipping_message($message){
            return WC()->session->get('error_message_shipping_breez') ?? $message;
        }

        /**
         * Check if this shipping method is available based on the package and defined settings.
         * @param $package
         * @return bool
         * @throws Exception
         */
        public function is_available($package = array()){
            //validate plz
            $plz = $package["destination"]["postcode"] ?? '';
            if($this->validate_german_zip($plz) === false) {
                return false;
            }

            //get distinct lieferwoche from cart items
            $lieferwochen = array();
            foreach (WC()->cart->get_cart_contents() as $cart_item) {
                $lieferwochen[] = $cart_item["lieferwoche"];
            }

            //check if all lieferwochen are available
            $all_available = $this->versandkostenBeezAvailabilityDao->are_all_orders_available($lieferwochen);
            if($all_available['status'] === false) {
                //add notice
                $info = "";
                foreach($all_available['lieferwochen'] as $lieferwoche) {
                    $info .= "KW " . $lieferwoche['woche'] . " " . $lieferwoche['jahr'];
                    //only add comma if not last element
                    if ($lieferwoche !== end($all_available['lieferwochen'])) {
                        $info .= ", ";
                    }
                }
                $this->add_notice('Die folgenden Lieferwochen sind nicht mehr verfügbar: '.$info, 'error', true);
                WC()->session->set('error_message_shipping_breez', 'Die folgenden Lieferwochen sind nicht mehr verfügbar: '.$info);
                return false;
            }

            WC()->session->set('error_message_shipping_breez', '');
            return true;
        }


        /**
         * Calculate shipping costs
         * @access public
         * @param mixed $package
         * @return void
         * @throws Exception
         */
        public function calculate_shipping( $package = array()){
            if($this->is_available($package) === false) {
                throw new Exception("Versandkosten können nicht berechnet werden");
            }

            $plz = $package["destination"]["postcode"] ?? '';
            $versandkosten = $this->get_shipping_costs($plz);

            // set cookie if they're different
            if ($plz !== ($_COOKIE["plz"] ?? '')) {
                setcookie("plz", $plz, time() + (86400 * 30), "/");
            }

            //get distinct lieferwoche from cart items
            $lieferwochen = array();
            foreach (WC()->cart->get_cart_contents() as $cart_item) {
                $lieferwochen[] = $cart_item["lieferwoche"]['woche'];
            }

            // remove duplicates
            $lieferwochen = array_map("unserialize", array_unique(array_map("serialize", $lieferwochen)));

            // multiply shipping costs with number of weeks
            $versandkosten *= count($lieferwochen);

            // add shipping costs
            $rate = array(
                'id' => $this->id,
                'label' => $this->title,
                'cost' => $versandkosten,
            );
            $this->add_rate($rate);
        }


        /**
         * Validate German zip code with GeoNames API
         * @param string $plz
         * @return bool
         */
        public function validate_german_zip($plz) {
            if(!preg_match('/^\d{5}$/',$plz)) return false;
            return $this->get_plz_info($plz) != null;
        }

        /**
         * Get city info with GeoNames API
         * @param string $plz
         * @return array | bool
         */
        public function get_plz_info($plz) {
            if(!preg_match('/^\d{5}$/',$plz)) return false;

            // get cache
            $cache_key = 'plz_info_'.$plz;
            $cache = get_transient($cache_key);
            if($cache !== false) {
                return $cache;
            }

            $url = 'http://geonames.org/postalCodeLookupJSON?postalcode='.$plz.'&country=DE';
            $response = file_get_contents($url);
            $resp_arr = json_decode($response,true);

            // cache response for 1 day
            set_transient($cache_key, $resp_arr["postalcodes"][0], 60*60*24*30);

            return $resp_arr["postalcodes"][0] ?? null;
        }

        /**
         * Calculate distance and duration between two zip codes with Google Maps API
         * @param string $origin_zip
         * @param string $destination_zip
         * @param string $travel_mode
         * @return array $distance, $duration in meters and seconds
         * @throws Exception
         */
        private function get_distance_duration(string $origin_zip, string $destination_zip, string $travel_mode = 'driving') : array
        {
            $api_key = 'AIzaSyA0fWtV58YretdjZUQ7xAv4alaSsTOECLQ';

            // check if zip code is cached
            $cache_key = 'distance_duration_' . $origin_zip . '_' . $destination_zip . '_' . $travel_mode;
            $distance_duration = get_transient($cache_key);
            if ($distance_duration !== false) {
                return $distance_duration;
            }

            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
            $params = array(
                'origins' => $origin_zip . ', Germany',
                'destinations' => $destination_zip . ', Germany',
                'mode' => $travel_mode,
                'units' => 'metric',
                'key' => $api_key,
            );
            $url .= '?' . http_build_query($params);
            $response = file_get_contents($url);
            $data = json_decode($response);
            if ($data->status == 'OK') {
                $distance = $data->rows[0]->elements[0]->distance->value;
                $duration = $data->rows[0]->elements[0]->duration->value;

                // cache result for 30 days
                $ret = array('distance' => $distance, 'duration' => $duration);
                set_transient($cache_key, $ret, 60 * 60 * 24 * 30);
                return $ret;
            } else {
                $this->add_notice('Es kam zu einem Fehler beim Zugriff auf die Google Maps API', 'error');
                return(array('distance' => 0, 'duration' => 0));
            }
        }


        /**
         * Shipping cost function.
         * @param string $destination_plz
         * @return double $versandkosten
         * @throws Exception
         */
        public function get_shipping_costs(string $destination_plz) : float
        {
            // Validate German zip code
            if($this->validate_german_zip($destination_plz)){
                // Get distance and duration between origin and destination
                $result = $this->get_distance_duration($this->origin_plz, $destination_plz);

                // Convert meters to kilometers and seconds to hours
                $entfernung = $result['distance'] / 1000;
                $fahrzeit = $result['duration'] / 3600;

                // Calculate shipping costs and return
                return $this->calculate_shipping_costs($entfernung, $fahrzeit);
            }else{
                $this->add_notice('Der eingegebene Wert ist keine belieferbare deutsche Postleitzahl.', 'error');
                return 0;
            }
        }


        /**
         * Calculate shipping costs
         * @param double $entfernung in kilometers
         * @param double $fahrzeit in hours
         * @return double $versandkosten
         * @throws Exception
         */
        private function calculate_shipping_costs($entfernung, $fahrzeit): float
        {
            if(isset($entfernung) && isset($fahrzeit) && isset($this->spritpreis) && isset($this->spritverbrauch) && isset($this->lohnkosten) && isset($this->wartungskosten) && isset($this->beentladen)) {
                // Convert all values to double
                $entfernung = doubleval($entfernung);
                $fahrzeit = doubleval($fahrzeit);
                $spritpreis = doubleval($this->spritpreis);
                $spritverbrauch = doubleval($this->spritverbrauch);
                $lohnkosten = doubleval($this->lohnkosten);
                $wartungskosten = doubleval($this->wartungskosten);
                $beendladen = doubleval($this->beentladen);

                // Calculate costs per km
                $kosten_pro_km = (($spritpreis * $spritverbrauch) / 100)  + ($wartungskosten / 10000);

                // Calculate total costs
                $versandkosten = ((($entfernung * $kosten_pro_km) + ($lohnkosten * $fahrzeit)) * 2) + $beendladen;

                // round to 2 decimal places and return
                return round($versandkosten, 2);
            }

            $this->add_notice('Es sind nicht alle Variablen für die Versandkostenberechnung gesetzt. Bitte kontaktieren Sie den Admin.', 'error');
            return 0;
        }



        /**
         * Get the next delivery weeks (default 4)
         * @param int $amount
         * @return array
         */
        public function get_lieferwochen(int $amount = 4) : array
        {
            $lieferwochen = array();
            for($i = 0; $i < $amount; $i++) {
                // if today is friday or later increase $i
                $current_week_offset = $i;
                if(date("N") >= 5){
                    $current_week_offset++;
                }

                $current_calendar_week = date("W", strtotime("+" . ($current_week_offset - 1) . " week monday"));
                $current_calendar_year = date("Y", strtotime("+" . ($current_week_offset - 1) . " week monday"));

                $lieferwochen[] = new VersandkostenBeezLieferwoche($current_calendar_week, $current_calendar_year);
            }
            return $lieferwochen;
        }

        /**
         * Add shipping week information to cart item
         * @throws Exception
         */
        function add_shipping_information_to_cart_item($cart_item_data, $product_id, $variation_id ) : array
        {
            $lieferwoche = $_POST['lieferwoche'] ?? null;
            $lieferjahr = $_POST['lieferjahr'] ?? null;
            if($lieferwoche !== null && $lieferjahr !== null && $this->versandkostenBeezAvailabilityDao->is_available($lieferwoche, $lieferjahr)){
                //change cart data of item with lieferwoche
                $cart_item_data['lieferwoche'] = array(
                    'woche' =>$lieferwoche,
                    'jahr' => $lieferjahr
                );
            }else{
                throw new Exception("Die ausgewählte Lieferwoche ist nicht verfügbar.");
            }
            return $cart_item_data;
        }

        /**
         * Show shipping week information in cart and checkout
         * @throws Exception
         */
        function show_shipping_information_in_cart($item_data, $cart_item_data ) : array
        {
            // Check if shipping week is set
            if( isset( $cart_item_data['lieferwoche'] ) ) {
                $week = $cart_item_data['lieferwoche']['woche'] ?? "";
                $year = $cart_item_data['lieferwoche']['jahr'] ?? "";

                $lieferwochen_info = new VersandkostenBeezLieferwoche($week, $year);
                $start = $lieferwochen_info->getStart();
                $end = $lieferwochen_info->getEnd();

                if($lieferwochen_info->getEnabled()) {
                    $item_data[] = array(
                        'key' => __('Lieferwoche', 'versandkosten-beez'),
                        'value' => wc_clean("KW $week ($start - $end)")
                    );
                }else{
                    $item_data[] = array(
                        'key' => __('Fehler', 'versandkosten-beez'),
                        'value' => "Die Lieferung in der KW $week ($start - $end) ist nicht verfügbar. Bitte entfernen Sie das Produkt aus dem Warenkorb."
                    );
                }
                return $item_data;
            }
            $this->add_notice("Ein Fehler ist bei der Verarbeitung der Lieferwoche augetreten.", "error", true);
            return $item_data;
        }

        /**
         * Add shipping week information to order
         * @throws Exception
         */
        function add_shipping_week_information_to_order($item, $cart_item_key, $values, $order ) {
            if( isset( $values['lieferwoche'] ) ) {
                $week = $values['lieferwoche']['woche'] ?? "";
                $year = $values['lieferwoche']['jahr'] ?? "";

                $lieferwochen_info = new VersandkostenBeezLieferwoche($week, $year);
                $start = $lieferwochen_info->getStart();
                $end = $lieferwochen_info->getEnd();

                // check if week is available
                if(!$lieferwochen_info->getEnabled()) {
                    $this->add_notice("Die Lieferung in der KW $week ($start - $end) ist nicht verfügbar. Bitte entfernen Sie die betroffenen Produkte aus dem Warenkorb.", "error", true);
                    return;
                }

                // add shipping week to order
                $item->add_meta_data(
                    __( 'Lieferwoche', 'versandkosten-beez' ),
                    wc_clean("KW $week ($start - $end)"),
                    true
                );

                // add shipping week to order meta
                $existing_meta = $order->get_meta('hidden_lieferwoche');
                if(!is_array($existing_meta)){
                    $existing_meta = array();
                }

                $existing_meta[] = array('woche' => $week, 'jahr' => $year);
                $order->add_meta_data('hidden_lieferwoche',
                    $existing_meta,
                    true
                );
            }
        }


        /**
         * Manage order status changes
         * @param $order_id int
         * @param $old_status string
         * @param $new_status string
         * @return void
         * @throws Exception
         */
        function check_order_status_changed($order_id, $old_status, $new_status)
        {
            $order = wc_get_order($order_id);

            if($new_status == "cancelled" || $new_status == "failed" || $new_status == "refunded"){
                //Change taken capacity if order is cancelled
                $lieferwochen = $order->get_meta('hidden_lieferwoche') ?? array();
                if(count($lieferwochen) != 0) {
                    foreach ($lieferwochen as $lieferwoche) {
                        $this->versandkostenBeezAvailabilityDao->increase_availabilty($lieferwoche['woche'], $lieferwoche['jahr'], $order_id);
                    }
                }
            }else {
                $this->checkout_order($order_id);
            }

        }

        /**
         * Manage order checkout
         * @param $order_id int
         * @return void
         * @throws Exception
         */
        function checkout_order(int $order_id)
        {
            $order = wc_get_order($order_id);
            $lieferwochen = $order->get_meta('hidden_lieferwoche') ?? array();
            if(count($lieferwochen) != 0) {
                // make sure the order was not already processed
                if($this->versandkostenBeezAvailabilityDao->is_order_taking_availability($order_id)){
                    return;
                }

                // only distinct lieferwochen
                $lieferwochen = array_map("unserialize", array_unique(array_map("serialize", $lieferwochen)));

                // check if all lieferwochen are still available
                $all_available = $this->versandkostenBeezAvailabilityDao->are_all_orders_available($lieferwochen);
                if(!$all_available['status']){
                    $lieferwochen_string = "";
                    foreach($all_available['lieferwochen'] as $lieferwoche){
                        $lieferwochen_string .= "KW ".$lieferwoche['woche'] . " ". $lieferwoche['jahr'] . ", ";
                    }
                    $order->update_status('failed', 'Die folgenden Lieferwochen sind nicht mehr verfügbar: '.$lieferwochen_string);
                    return;
                }

                // take availability
                foreach ($lieferwochen as $lieferwoche) {
                    if(!$this->versandkostenBeezAvailabilityDao->decrease_availabilty($lieferwoche['woche'], $lieferwoche['jahr'], $order_id)){
                        $order->update_status('failed', 'Lieferwoche KW '.$lieferwoche['woche'] .' ist nicht mehr verfügbar');
                        $this->add_notice("Die Lieferwoche KW ".$lieferwoche['woche'] ." ist nicht mehr verfügbar", "error", true);
                    };
                }
            }
        }


        /**
         * Add notice to cart or checkout page or else throws an exception
         * @param string $message
         * @param string $notice_type
         * @return void
         * @throws Exception
         */
        function add_notice($message, $notice_type, $enforce_notice = false){
            if(is_checkout() || is_cart() || $enforce_notice){
                //make notice dismisible
                $notice_type = $notice_type . ' dismissible';
                if(!wc_has_notice(__($message, 'woocommerce'), $notice_type)){
                    wc_add_notice(__($message, 'woocommerce'), $notice_type);
                }
            }else{
                throw new Exception($message);
            }
        }

    }


    // Add the shipping method to WooCommerce
    function add_shipping_method( $methods ){
        $methods[] = 'Versand_Kosten_Beez_Shipping_Method';
        return $methods;
    }

    // Register the shipping method
    add_filter( 'woocommerce_shipping_methods', 'add_shipping_method');
endif;

?>