<?php

if ( ! class_exists( 'Versand_Kosten_Beez_Shipping_Method' )) :
    class Versand_Kosten_Beez_Shipping_Method extends WC_Shipping_Method {
        public $id                   = 'versand-kosten-beez-shipping-method';
        public $method_title         = ('Versand Kosten Beez Einstellungen');
        public $method_description   = ('Einstellungen für die Berechnung der Versandkosten' );

        private float $spritpreis;
        private float $spritverbrauch;
        private float $lohnkosten;
        private float $wartungskosten;
        private float $beentladen;
        private int $origin_plz;

        private VersandkostenBeezAvailabilityDao $breez_shipping_availability_controller;

        public function __construct(){
            $this->tax_status           = "none";
            $this->availability         = 'including';
            $this->countries            = array('DE');

            $this->init();
        }

        private static bool $hooks_loaded = false;
        function init(){
            //Load database connection
            $this->breez_shipping_availability_controller = VersandkostenBeezAvailabilityDao::getInstance();

            //Load the settings API
            $this->init_form_fields();  // This is part of the settings API. Override the method to add your own settings
            $this->init_settings();     // This is part of the settings API. Loads settings you previously init.

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
                add_action('woocommerce_admin_field_display_some_text_in_admin', array($this, 'display_some_text_in_admin'));

                add_action('woocommerce_review_order_before_cart_contents', array($this, 'validate_order'));
                add_action('woocommerce_after_checkout_validation', array($this, 'validate_order'));

                // process checkout hook
                add_action('woocommerce_checkout_process', array($this, 'checkout_order'));

                // add lieferwoche to cart item
                add_filter('woocommerce_add_cart_item_data', array($this, 'custom_add_cart_item_data'), 10, 3);
                add_filter('woocommerce_get_item_data', array($this, 'custom_get_item_data'), 10, 2);
                add_action('woocommerce_checkout_create_order_line_item', array($this, 'custom_create_order_line_item'), 10, 4);
            }
        }

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

                                                                                      
        public function calculate_shipping( $package = array()){
            $plz = $package["destination"]["postcode"];
            $versandkosten = $this->get_shipping_costs($plz);

            // set cookie if they're different
            if($plz !== $_COOKIE["plz"]){
                setcookie("plz", $plz, time() + (86400 * 30), "/");
            }

            //get distinct lieferwoche from cart items
            $lieferwochen = array();
            //get lieferwoche from cart item data
            foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
                $lieferwochen[] = $cart_item["lieferwoche"]['woche'];
            }

            $lieferwochen = array_unique($lieferwochen);

            $versandkosten *= count($lieferwochen);

            foreach($lieferwochen as $lieferwoche){
                $rate = array(
                    'id'    => $this->id,       // ID for the rate
                    'label' => $this->title,    // Label for the rate
                    'cost'  => $versandkosten,  // Amount for shipping or an array of costs (for per item shipping)
                );
                $this->add_rate( $rate );
            }
        }

        /**
         * Validate German zip code with GeoNames API
         * Premium-Lizenzen möglich
         * @param string $plz
         * @return bool
         */
        public function validate_german_zip($plz) {
            if(!preg_match('/^\d{5}$/',$plz)) return false;
            $rw = $this->get_plz_info($plz) != null;
            return $rw;
        }

        /**
         * Get city info with GeoNames API
         * @License: Creative COmmons Attribution 4.0 License -> https://creativecommons.org/licenses/by/4.0/
         * @param string $plz
         * @return array | bool
         */
        public function get_plz_info($plz) {
            if(!preg_match('/^\d{5}$/',$plz)) return false;
            $url = 'http://geonames.org/postalCodeLookupJSON?postalcode='.$plz.'&country=DE';
            $response = file_get_contents($url);
            $resp_arr = json_decode($response,true);
            return $resp_arr["postalcodes"][0];
        }


        /**
         * Calculate distance and duration between two zip codes with Google Maps API
         * TODO: Google Maps API key ersetzen
         * @param string $origin_zip
         * @param string $destination_zip
         * @param string $travel_mode
         * @return array $distance, $duration in meters and seconds
         * @throws Exception
         */
        private function get_distance_duration($origin_zip, $destination_zip, $travel_mode = 'driving') {
            //DUMMY-WERT:
            if($destination_zip == "96332"){
                return array('distance' => 0, 'duration' => 0);
            }
            return array('distance' => 120000, 'duration' => 7200);


            $api_key = 'your_api_key';
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
            $params = array(
                'origins' => $origin_zip . ', Germany',
                'destinations' => $destination_zip . ', Germany',
                'mode' => $travel_mode,
                'key' => $api_key,
            );
            $url .= '?' . http_build_query($params);
            $response = file_get_contents($url);
            $data = json_decode($response);
            if ($data->status == 'OK') {
                $distance = $data->rows[0]->elements[0]->distance->value;
                $duration = $data->rows[0]->elements[0]->duration->value;
                return array('distance' => $distance, 'duration' => $duration);
            } else {
                $this->add_notice('Es kam zu einem Fehler beim Zugriff auf die Google Maps API', 'error');
            }
        }


        /**
        * Shipping cost function.
        * @param string $destination_plz
        * @return double $versandkosten
        */
        public function get_shipping_costs($destination_plz) {
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
                $this->add_notice('Der eingegebene Wert entspricht keiner belieferbaren deutschen Postleitzahl.', 'error');
            }
        }


        /**
         * Calculate shipping costs
         * @param double $entfernung in kilometers
         * @param double $fahrzeit in hours
         * @return double $versandkosten
         */
        private function calculate_shipping_costs($entfernung, $fahrzeit){
            if(isset($entfernung) && isset($fahrzeit) && $entfernung !== null && $fahrzeit !== null &&
                isset($this->spritpreis) && isset($this->spritverbrauch) && isset($this->lohnkosten) && isset($this->wartungskosten) && isset($this->beentladen)
            ){
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
            $this->add_notice('Es sind nicht alle Variablen für die Versandkostenberechnung gesetzt.', 'error');
        }


        function validate_order($posted){
            GLOBAL $woocommerce;
            $plz = $posted['shipping_postcode'];
            if(!$this->validate_german_zip($plz)){
                $this->add_notice('Der eingegebene Wert entspricht keiner belieferbaren deutschen Postleitzahl.', 'error');
            }

            //check if all products have a lieferwoche attribute
            foreach ( $woocommerce->cart->get_cart_contents() as $cart_item ) {
                $lieferwoche = $cart_item["lieferwoche"];
                if(!$this->breez_shipping_availability_controller->is_available($lieferwoche['woche'], $lieferwoche['jahr'])){
                    $this->add_notice('Bitte wählen Sie auf der Produktseite eine Lieferwoche aus.', 'error');
                }
            }
        }

        function checkout_order($order_id){
            $order = new WC_Order($order_id);

            //get distinct lieferwoche from cart items
            $lieferwochen = array();
            //get lieferwoche from cart item data
            foreach ( $order->get_items() as $cart_item ) {
                $lieferwochen[] = $cart_item["lieferwoche"]['woche'];
            }
            $lieferwochen = array_unique($lieferwochen);

            foreach($lieferwochen as $lieferwoche){
                if(!$this->breez_shipping_availability_controller->is_available($lieferwoche['woche'], $lieferwoche['jahr'])){
                    $this->add_notice('Bitte wählen Sie auf der Produktseite eine Lieferwoche aus.', 'error');
                    return;
                }
            }

            foreach($lieferwochen as $lieferwoche){
                $this->breez_shipping_availability_controller->decrease_availabilty($lieferwoche['woche'], $lieferwoche['jahr'], $order_id);
            }
        }

        /**
         * Add notice to cart or checkout page or else throws an exception
         * @param string $message
         * @param string $notice_type
         * @return void
         * @throws Exception
         */
        function add_notice($message, $notice_type){
            if(is_checkout() || is_cart()){
                if(!wc_has_notice(__($message, 'woocommerce'), $notice_type)){
                    wc_add_notice(__($message, 'woocommerce'), $notice_type);
                }
            }else{
                throw new Exception($message);
            }
        }

        public function get_lieferwochen(){
            $lieferwochen = array();
            for($i = 0; $i < 4; $i++) {
                $current_calendar_week = date("W", strtotime("+" . $i . " week monday"));
                $current_calendar_year = date("Y", strtotime("+" . $i . " week monday"));

                $lieferwochen[] = $this->get_lieferwochen_info($current_calendar_week, $current_calendar_year);
            }
            return $lieferwochen;
        }

        public function get_lieferwochen_info($week, $year){
            // get monday and friday of the week in that year
            $start_date = date("d.m.Y", strtotime($year . "W" . $week . "1"));
            $end_date = date("d.m.Y", strtotime($year . "W" . $week . "5"));

            return array(
                "week" => $week,
                "year" => $year,
                "start" => $start_date,
                "end" => $end_date,
                "enabled" => $this->breez_shipping_availability_controller->is_available($week, date("Y"))
            );
        }


        /**
         * Add shipping week information to cart item
         */
        function custom_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
            $lieferwoche = $_POST['lieferwoche'] ?? "";
            $lieferjahr = $_POST['lieferjahr'] ?? "";
            if($lieferwoche !== "" && $lieferjahr !== "" && $this->breez_shipping_availability_controller->is_available($lieferwoche, $lieferjahr)){
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
         */
        function custom_get_item_data( $item_data, $cart_item_data ) {
            if( isset( $cart_item_data['lieferwoche'] ) ) {
                $week = $cart_item_data['lieferwoche']['woche'];
                $year = $cart_item_data['lieferwoche']['jahr'];

                $lieferwochen_info = $this->get_lieferwochen_info($week, $year);
                $start = $lieferwochen_info['start'];
                $end = $lieferwochen_info['end'];
                $enabled = $lieferwochen_info['enabled'];

                if($enabled) {
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
            $this->add_notice("Ein Fehler ist bei der Verarbeitung der Lieferwoche augetreten.", "error");
        }

        function custom_create_order_line_item( $item, $cart_item_key, $values, $order ) {
            if( isset( $values['lieferwoche'] ) ) {
                $week = $values['lieferwoche']['woche'];
                $year = $values['lieferwoche']['jahr'];

                $lieferwochen_info = $this->get_lieferwochen_info($week, $year);
                $start = $lieferwochen_info['start'];
                $end = $lieferwochen_info['end'];

                if(!$lieferwochen_info['enabled']) {
                    $this->add_notice("Die Lieferung in der KW $week ($start - $end) ist nicht verfügbar. Bitte entfernen Sie die betroffenen Produkte aus dem Warenkorb.", "error");
                    //throw new Exception("Die Lieferung in der KW $week ($start - $end) ist nicht verfügbar. Bitte entfernen Sie die betroffenen Produkte aus dem Warenkorb.");
                }

                $item->add_meta_data(
                    __( 'Lieferwoche', 'versandkosten-beez' ),
                    wc_clean("KW $week ($start - $end)"),
                    true
                );

            }
        }


        public function add_shipping_week_selection(){
            ?>
            <div id="shipping-informations-week">
                <label>Lieferwoche *</label>
                <select id="select-lieferwochen">
                    <option value="">--Bitte auswählen --</option>
                    <?php
                    $lieferwochen = $this->get_lieferwochen();
                    foreach($lieferwochen as $lieferwoche) {
                        $week = $lieferwoche["week"];
                        $year = $lieferwoche["year"];
                        $start = $lieferwoche["start"];
                        $end = $lieferwoche["end"];
                        $enabled = $lieferwoche["enabled"];

                        echo "<option data-year='$year' value='$week'";
                        if($enabled) {
                            echo ">KW $week ($start - $end)";
                        } else {
                            echo "disabled>
                                            KW $week ($start - $end) - ausgebucht!";
                        }
                        echo "</option>";
                    }
                    ?>
                </select>
            </div>

            <?php
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