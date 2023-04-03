<?php
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ( ! class_exists( 'Breez_shipping_availability_controller' )){
            class Breez_shipping_availability_controller{
                private $table_name_capacity;
                private $table_name_takenavailability;
                private $wpdb;
                
                public function __construct(){
                    //create database connection with wordpress
                    global $wpdb;
                    $this->wpdb = $wpdb;
                    $this->table_name_capacity = $wpdb->prefix . 'breez_shipping_availability_capicity';
                    $this->table_name_takenavailability = $wpdb->prefix . 'breez_shipping_availability_takenavailability';
                    $this->create_table();
                }

                public function create_table(){
                    $sql = "
                        CREATE TABLE IF NOT EXISTS $this->table_name_capacity (
                        calender_week int(2) NOT NULL UNIQUE,
                        year int(4) NOT NULL,
                        availability int(1) NOT NULL,
                        PRIMARY KEY (calender_week, year);

                        CREATE TABLE IF NOT EXISTS $this->table_name_takenavailability (
                            calender_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            order_id int(11) NOT NULL,
                            PRIMARY KEY (calender_week, year, order_id)
                            FOREIGN KEY (calender_week, year) REFERENCES $this->table_name_capacity(calender_week, year)
                            ON DELETE CASCADE
                            CHECK (

                                SELECT availability FROM $this->table_name_capacity 
                                WHERE calender_week = $this->table_name_takenavailability.calender_week and
                                year = $this->table_name_takenavailability.year) 
                                
                                >

                                (SELECT count(*) FROM $this->table_name_takenavailability
                                WHERE calender_week = $this->table_name_takenavailability.calender_week and
                                year = $this->table_name_takenavailability.year
                        );
                    ";
                    $this->wpdb->query($sql);
                }

                public function set_availabilty($calender_week, $year, $availability){
                    $sql = "INSERT INTO $this->table_name_capacity (calender_week, year, availability) VALUES ($calender_week, $year, $availability) ON DUPLICATE KEY UPDATE availability = $availability";
                    $this->wpdb->query($sql);
                }

                public function get_taken_availability($calender_week, $year){
                    $sql = "SELECT count(*) FROM $this->table_name_takenavailability WHERE calender_week = $calender_week and year = $year";
                    return $this->wpdb->get_var($sql);
                }

                public function get_availabilty($calender_week, $year){
                    $sql = "SELECT availability FROM $this->table_name_capacity WHERE calender_week = $calender_week and year = $year";
                    return $this->wpdb->get_var($sql);
                }

                public function check_availabilty($calender_week, $year){
                    // get current day info
                    $current_day = date('w');
                    $current_week = date('W');
                    $current_year = date('Y');

                    // check if the availability is not 0 and if the week is not in the past and today is not friday or later
                    return ($this->get_availabilty($calender_week, $year) && $this->get_taken_availability($calender_week, $year)) &&
                            ($calender_week >= $current_week && $year >= $current_year && $current_day < 5);
                }

                public function decrease_availabilty($calender_week, $year, $order_id){
                    check_availabilty($calender_week, $year);
                    $sql = "INSERT INTO $this->table_name_takenavailability (calender_week, year, order_id) VALUES ($calender_week, $year, $order_id)";
                    $this->wpdb->query($sql);
                }

                public function increase_availabilty($calender_week, $year, $order_id){
                    $sql = "DELETE FROM $this->table_name_takenavailability WHERE calender_week = $calender_week and year = $year and order_id = $order_id";
                    $this->wpdb->query($sql);
                }
            }
        }
    }

?>