<?php
/**
 * Integration
 */
if ( ! class_exists( 'Versand_Kosten_Beez_Integration' ) ) :
    class Versand_Kosten_Beez_Integration extends WC_Integration {
        /**
         * Init and hook in the integration.
         */
        public function __construct() {
            global $woocommerce;
            $this->id                 = 'versand-kosten-beez-integration';
            $this->method_title       = __( 'Versand Kosten Beez Integration');
            $this->method_description = __( 'Berechnet die Versandkosten!');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->spritpreis = $this->get_option( 'spritpreis' );
            $this->spritverbrauch = $this->get_option( 'spritverbrauch' );
            $this->lohnkosten = $this->get_option( 'lohnkosten' );
            $this->abschreibung_pro_km = $this->get_option( 'abschreibung_pro_km' );
            $this->wartungskosten = $this->get_option( 'wartungskosten' );
            $this->origin_zip = $this->get_option( 'plz1_input' );

            // Test bezüglich der Berechnung
            $this->plz1 = $this->get_option( 'plz1_input' );
            $this->plz2 = $this->get_option( 'plz2_input' );
            /*
            $this->entfernung_output = $this->get_option( 'entfernung_output' );
            $this->fahrzeit_output = $this->get_option( 'fahrzeit_output' );
            $this->kosten_output = $this->get_option( 'kosten_output' );*/


            // Actions.
            add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
        }


        /**
        * Initialize integration settings form fields.
        */
        public function init_form_fields() {

            $this->form_fields = array(
                'spritpreis' => array(
                    'title'             => __( 'Spritpreis'),
                    'type'              => 'number',
                    'description'       => __( 'Aktueller Spritpreis' ),
                    'desc_tip'          => true,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'spritverbrauch' => array(
                    'title'             => __( 'Spritverbrauch'),
                    'type'              => 'number',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'lohnkosten' => array(
                    'title'             => __( 'Lohnkosten pro Stunde'),
                    'type'              => 'number',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'abschreibung_pro_km' => array(
                    'title'             => __( 'Abschreibung pro km'),
                    'type'              => 'number',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'wartungskosten' => array(
                    'title'             => __( 'Wartungskosten pro km'),
                    'type'              => 'number',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'origin_zip' => array(
                    'title'             => __( 'Postleitzahl des Versandorts'),
                    'type'              => 'number',
                    'validate'          => 'validate_german_zip',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'plz2' => array(
                    'title'             => __( 'plz2'),
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                /*
                'entfernung_output' => array(
                    'title'             => __( 'Entfernung'),
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => ($this->plz1 != null && $this->plz2 != null) ? calculate_distance_and_duration($this->plz1, $this->plz2)['distance'] : '',
                    'css'      => 'width:170px;',
                ),
                'fahrzeit_output' => array(
                    'title'             => __( 'Fahtzeit'),
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => ($this->plz1 != null && $this->plz2 != null) ? calculate_distance_and_duration($this->plz1, $this->plz2)['duration'] : '',
                    'css'      => 'width:170px;',
                ),
                'kosten_output' => array(
                    'title'             => __( 'Kosten'),
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => ($this->plz1 != null && $this->plz2 != null) ? calculate_shipping($this->plz1, $this->plz2) : '',,
                    'css'      => 'width:170px;',
                ),*/
                
            );
        }

        /*
         * Validate German zip codes 
         * */
        function validate_german_zip($zip) {
            $url = 'https://www.postdirekt.de/plzserver/PlzAjaxServlet';
            $params = array(
                'finda' => 'city',
                'lang' => 'de_DE',
                'cacheable' => 'true',
                'city' => '',
                'location' => '',
                'state' => '',
                'plz' => $zip,
                'submit' => 'Suchen',
            );
            $url .= '?' . http_build_query($params);
            $response = file_get_contents($url);
            $data = json_decode($response);
            return $data->success;
        }
        

        /*
         * Calculate distance and duration between two zip codes with Google Maps API
         * */
        function get_distance_duration($origin_zip, $destination_zip, $travel_mode = 'driving') {
            $api_key = 'your_api_key'; // TODO: Replace with your Google Maps API key
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
                return false;
            }
        }
        

        /**
        * Calculate shipping function.
        *
        * @param array $package Order package.
        * @return Integer $versandkosten
        */
        public function calculate_shipping($destination_plz) {
            if(validate_german_zip($destination_plz)){
                $result = get_distance_duration($this->$origin_zip, $destination_plz);
                if ($result) {
                    // Convert meters to kilometers and seconds to hours
                    $entfernung = $result['distance'] / 1000;
                    $fahrzeit = $result['duration'] / 3600;

                    // Berechnung der Kilometerkosten
                    $kosten_pro_km = ($this->$spritpreis * $this->$spritverbrauch)/100 - $this->$abschreibung_pro_km + $this->$wartungskosten;

                    // Berechnung der Versandkosten
                    $versandkosten = ($entfernung * $kosten_pro_km + ($this->$lohnkosten * $fahrzeit)) * 2 ;
                    $versandkosten = round($kosten, 2);
                    return $versandkosten;

                } else {
                    throw new Exception('Error in Google Maps API');
                    return false;
                }
            }else{
                throw new Exception('Invalid German Zip Code');
                return false;
            }
        }
    }
endif; 
?>