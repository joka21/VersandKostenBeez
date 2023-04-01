<?php

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if ( ! class_exists( 'Versand_Kosten_Beez_Shipping_Method' ) && class_exists( 'WC_Shipping_Method' ) ) {
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

            public function __construct(){                   
                $this->tax_status           = "none";
                $this->availability         = 'including';
                $this->countries            = array('DE');

                $this->init();
            }

            function init(){
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

                // Save settings in admin if you have any defined
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_review_order_before_cart_contents', 'validate_order' , 10 );
                add_action( 'woocommerce_after_checkout_validation', 'validate_order' , 10 );
            
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

                // set cookie
                setcookie("plz", $plz, time() + (86400 * 30), "/");

                $rate = array(
                    'id'    => $this->id,       // ID for the rate
                    'label' => $this->title,    // Label for the rate
                    'cost'  => $versandkosten,  // Amount for shipping or an array of costs (for per item shipping)
                );
                $this->add_rate( $rate );
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
             * @return array
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
                    isset($this->spritpreis) && isset($this->spritverbrauch) && isset($this->lohnkosten) && isset($this->wartungskosten) && isset($this->beentladen) &&
                    $this->spritpreis !== null && $this->spritverbrauch !== null && $this->lohnkosten !== null && $this->wartungskosten !== null && $this->beentladen !== null
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
                $plz = $posted['shipping_postcode'];
                if(!$this->validate_german_zip($plz)){
                    $this->add_notice('Der eingegebene Wert entspricht keiner belieferbaren deutschen Postleitzahl.', 'error');
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
        }
    }
    
    function add_shipping_method( $methods ){
        $methods[] = 'Versand_Kosten_Beez_Shipping_Method';
        return $methods;
    }

    // Register the shipping method
    add_filter( 'woocommerce_shipping_methods', 'add_shipping_method');
}
?>