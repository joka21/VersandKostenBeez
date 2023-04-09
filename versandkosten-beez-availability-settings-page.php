<?php

require_once('versandkosten-beez-shipping-availability-dao.php');
require_once('versandkosten-beez-lieferwoche.php');
if(!class_exists('VersandkostenBeezAvailabilitySettingsPage')) :
    class VersandkostenBeezAvailabilitySettingsPage {
        private VersandkostenBeezAvailabilityDao $shipping_availability_controller;

        private $option_group_output = 'Versandkosten Beez';
        private $option_group = 'Versandkosten Beez';
        private $option_name = 'VersandkostenBeezAvailabilitySettingsPage';


        public function __construct(){
            $this->init();
        }

        public function init(){
            $this->shipping_availability_controller = VersandkostenBeezAvailabilityDao::getInstance();
            add_action( 'admin_init', array($this, 'settings_init' ));
            add_action( 'admin_menu', array($this, 'versandkosten_beez_options_page' ));
        }

        /**
         * custom option and settings
         */
        function settings_init() {
            // Register a new setting page
            register_setting($this->option_group, $this->option_name);

            // Register a new section
            add_settings_section(
                $this->option_group .'_section_user',
                __( 'Lieferwochen-Management', $this->option_group_output ), array($this, 'versandkosten_beez_section_user_callback'),
                $this->option_group
            );
        }


        /**
         * User section callback function.
         *
         * @param array $args  The settings array, defining title, id, callback.
         */
        function versandkosten_beez_section_user_callback( $args ) {
            ?>
            <p id="<?php echo esc_attr( $args['id'] ); ?>">
                <?php esc_html_e( 'Hier kann die Kapazität für Lieferwochen eingestellt werden.', $this->option_group ); ?>
            </p>
            <?php
        }

        /**
         * Add the top level menu page.
         */
        function versandkosten_beez_options_page() {
            add_menu_page(
                $this->option_group,
                'Versandkosten Beez Lieferwochen Management',
                'manage_options',
                $this->option_group,
                array($this, 'versandkosten_beez_options_page_html')
            );
        }

        private function loadSettingsHtml(){
            $year = $_GET['year'] ?? date('Y');

            // add change to of year (left, right, input)
            $this->loadYearChangeHtml($year);

            // add table / list with all weeks of the year
            $this->loadWeeksTableHtml($year);

        }

        private function loadYearChangeHTML($year){
            ?>
            <div class="year-change">
                <button onclick="window.location.href = '<?php echo admin_url('admin.php?page=Versandkosten Beez&year=');?>' + (parseInt(document.getElementsByName('year')[0].value) - 1)">Vorheriges Jahr</button>
                <input type="number" name="year" value="<?php echo $year;?>" onchange="window.location.href = '<?php echo admin_url('admin.php?page=Versandkosten Beez&year=');?>' + this.value">
                <button onclick="window.location.href = '<?php echo admin_url('admin.php?page=Versandkosten Beez&year=');?>' + (parseInt(document.getElementsByName('year')[0].value) + 1)">Nächstes Jahr</button>
            </div>
            <?php
        }

        public function processForm(){
            $year = $_GET['year'] ?? date('Y');

            $changed = false;
            $success = true;
            foreach($_POST as $key => $value){
                if(strpos($key, 'week_') !== false){
                    $week = substr($key, 5);
                    $success = $this->shipping_availability_controller->set_availabilty($week, $year, $value) & $success;
                    $changed = true;
                }
            }

            if($changed){
                if($success){
                    $this->loadSuccessMessage();
                } else {
                    $this->loadErrorMessage();
                }
            }
        }

        function loadSuccessMessage(){
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Die Änderungen wurden erfolgreich gespeichert!', 'sample-text-domain' ); ?></p>
            </div>
            <?php
        }

        function loadErrorMessage(){
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e( 'Es ist ein Fehler aufgetreten!', 'sample-text-domain' ); ?></p>
            </div>
            <?php
        }

        private function loadWeeksTableHtml($year){
            ?>
                <form action="<?php echo admin_url('admin.php?page=Versandkosten Beez&year='.$year);?>" method="post">
                    <table class="weeks-table">
                        <thead>
                            <tr>
                                <th>Woche</th>
                                <th>Maximale Kapazität</th>
                                <th>Verwendete Kapazität</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                for($i = 1; $i <= 52; $i++){
                                    //ignore all weeks that are already in the past
                                    if($i < date('W') && $year <= date('Y')){
                                        continue;
                                    }

                                    $week = new VersandkostenBeezLieferwoche($i, $year);
                            ?>
                                <tr style="text-align: right;">
                                    <td><?php echo "KW ".$week->getWeek()." (".$week->getStart()." - ".$week->getEnd()." ";?></td>
                                    <td><input type="number" name="week_<?php echo $week->getWeek();?>" value="<?php echo $week->getMaxCapacity();?>"></td>
                                    <td><?php echo $week->getTakenCapacity();?></td>
                                </tr>
                            <?php
                                }
                            ?>
                        </tbody>
                    </table>

                    <?php
                    // add save button
                    $this->loadSaveButtonHtml();
                    ?>

                </form>

            <?php
        }

        private function loadSaveButtonHtml(){
            ?>
            <div class="save-button">
                <input type="submit" value="Speichern">
            </div>
            <?php
        }


        /**
         * Top level menu callback function
         */
        function versandkosten_beez_options_page_html() {
            // check user capabilities
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $this->processForm();

            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <?php $this->loadSettingsHtml();?>
            </div>
            <?php
        }
    }

endif;