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

            // Test bezüglich der Berechnung
            $this->plz1 = $this->get_option( 'plz1_input' );
            $this->plz2 = $this->get_option( 'plz2_input' );
            $this->entfernung_output = $this->get_option( 'entfernung_output' );
            $this->fahrzeit_output = $this->get_option( 'fahrzeit_output' );
            $this->kosten_output = $this->get_option( 'kosten_output' );


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
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'abschreibung_pro_km' => array(
                    'title'             => __( 'Abschreibung pro km'),
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'wartungskosten' => array(
                    'title'             => __( 'Wartungskosten pro km'),
                    'type'              => 'text',
                    'description'       => __( '' ),
                    'desc_tip'          => false,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
                'plz1' => array(
                    'title'             => __( 'plz1'),
                    'type'              => 'text',
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
                ),
                
            );
        }


        /**
         * Calculate distance and duration between two german zip codes.
         * @param $plz1
         * @param $plz2
         * @return array $data  Distance and duration between two zip codes.
         */
        private function calculate_distance_and_duration($plz1, $plz2){
            //calculate distance between two german zip codes
            $url = "https://www.distance24.org/route.json?stops=".$plz1."|".$plz2;
            $json = file_get_contents($url);
            $data = json_decode($json, true);
            return $data;
        }

        /**
        * Calculate shipping function.
        *
        * @param array $package Order package.
        */
        public function calculate_shipping($plz) {
            $data = $this->calculate_distance_and_duration($plz, $plz2);
            $entfernung = $data['distance'];
            $fahrzeit = $data['duration'];

            //TODO: dynamisch gestalten
            $spritpreis = 1.5;
            $spritverbrauch = 7;
            $lohnkosten = 10;
            $abschreibung_pro_km = 0.1;
            $Wartungskosten = 0.1;

            $kosten_pro_km = ($spritpreis * $spritverbrauch)/100 + ($lohnkosten * $fahrzeit) - $abschreibung_pro_km + $Wartungskosten;

            $versandkosten = $entfernung * $kosten_pro_km * 2;
            $versandkosten = round($kosten, 2);
            $versandkosten = $kosten . "€";
            $versandkosten = str_replace(".", ",", $kosten);
            $versandkosten = "Versandkosten: " . $kosten;
            return $versandkosten;
        }
    }
endif; 
?>