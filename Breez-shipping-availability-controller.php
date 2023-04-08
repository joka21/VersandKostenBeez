<?php
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ( ! class_exists( 'Breez_shipping_availability_controller' )){
            class Breez_shipping_availability_controller{
                private $table_name_capacity;
                private $table_name_takenavailability;
                private $wpdb;

                private static ?Breez_shipping_availability_controller $instance = null;

                public static function getInstance(){
                    if(self::$instance == null){
                        self::$instance = new Breez_shipping_availability_controller();
                    }
                    return self::$instance;
                }
                
                private function __construct(){
                    //create database connection with wordpress
                    global $wpdb;
                    $this->wpdb = $wpdb;
                    $this->table_name_capacity = $wpdb->prefix . 'breez_shipping_availability_capicity';
                    $this->table_name_takenavailability = $wpdb->prefix . 'breez_shipping_availability_takenavailability';
                    $this->create_table();
                }

                private function drop_table($name){
                    $sql = "
                        DROP TABLE IF EXISTS $name;
                    ";
                    $this->wpdb->query($sql);
                }

                public function create_table(){
                    $this->drop_table($this->table_name_takenavailability);
                    $this->drop_table($this->table_name_capacity );

                    $sql1 = "                        
                        CREATE TABLE IF NOT EXISTS $this->table_name_capacity (
                            calendar_week int(2) NOT NULL UNIQUE,
                            year int(4) NOT NULL,
                            availability int(1) NOT NULL DEFAULT 0,
                            PRIMARY KEY (calendar_week, year)
                        );";

                    $this->wpdb->query($sql1);

                    $sql2 = "
                        CREATE TABLE IF NOT EXISTS $this->table_name_takenavailability (
                            calendar_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            order_id int(11) NOT NULL,
                            PRIMARY KEY (calendar_week, year, order_id),
                            FOREIGN KEY (calendar_week, year) REFERENCES $this->table_name_capacity(calendar_week, year)
                            ON DELETE CASCADE
                        );
                    ";
                    $this->wpdb->query($sql2);
                }

                public function set_availabilty($calendar_week, $year, $availability){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $availability = (int)$availability;

                    $sql = "INSERT INTO $this->table_name_capacity (calendar_week, year, availability) VALUES (%i, %i, %i) ON DUPLICATE KEY UPDATE availability = %i";
                    $this->wpdb->prepare($sql, $calendar_week, $year, $availability);

                }

                public function get_taken_availability($calendar_week, $year){
                    $sql = "SELECT count(*) FROM $this->table_name_takenavailability WHERE calendar_week = %i and year = %i";
                    return $this->wpdb->prepare($sql, $calendar_week, $year)[0];
                }

                public function get_db_availabilty($calendar_week, $year){
                    $sql = "SELECT availability FROM $this->table_name_capacity WHERE calendar_week = $calendar_week and year = $year";
                    return $this->wpdb->get_var($sql);
                }

                public function is_available($calendar_week, $year){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    /*
                    // get current day info
                    $current_day = date('w');
                    $current_week = date('W');
                    $current_year = date('Y');

                    // check if the availability is not 0 and if the week is not in the past and today is not friday or later
                    return ($this->get_db_availabilty($calendar_week, $year) && $this->get_taken_availability($calendar_week, $year)) &&
                            ($calendar_week >= $current_week && $year >= $current_year && $current_day < 5);
                    */

                    return $calendar_week !== 15;
                }

                public function decrease_availabilty($calendar_week, $year, $order_id){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $order_id = (int)$order_id;

                    if(!$this->is_available($calendar_week, $year)){
                        return false;
                    }

                    $sql = "INSERT INTO $this->table_name_takenavailability (calendar_week, year, order_id) VALUES (%i, %i, %i) ON DUPLICATE KEY UPDATE order_id = %i";
                    return 0 != $this->wpdb->query($this->wpdb->prepare($sql, array($calendar_week, $year, $order_id)));
                }

                public function increase_availabilty($calendar_week, $year, $order_id){
                    $sql = "DELETE FROM $this->table_name_takenavailability WHERE calendar_week = $calendar_week and year = $year and order_id = $order_id";
                    $this->wpdb->query($sql);
                }
            }
        }
    }

?>