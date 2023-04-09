<?php

if(!class_exists('VersandkostenBeezLieferwoche')):
    require_once('versandkosten-beez-shipping-availability-dao.php');

    date_default_timezone_set("Europe/Berlin");

    class VersandkostenBeezLieferwoche{
        private int $week;
        private int $year;
        private string $start;
        private string $end;
        private bool $enabled;
        private int $taken_capacity;
        private int $max_capacity;

        function __construct($week, $year){
            $VersandkostenBeezAvailabilityDao = VersandkostenBeezAvailabilityDao::getInstance();

            $this->week = $week;
            $this->year = $year;
            $this->enabled = $VersandkostenBeezAvailabilityDao->is_available($week, $year);
            $this->max_capacity = $VersandkostenBeezAvailabilityDao->get_max_availability($week, $year);
            $this->taken_capacity = $VersandkostenBeezAvailabilityDao->get_taken_availability($week, $year);

            $this->calculateStartAndEnd($week, $year);
        }


        private function calculateStartAndEnd($week, $year)
        {
            // get start (monday) and end (friday) of the week
            $start = new DateTime();
            $start->setISODate($year, $week);
            $start->setTime(0, 0, 0);
            $this->start = $start->format('d.m.Y');

            $end = new DateTime();
            $end->setISODate($year, $week);
            $end->setTime(23, 59, 59);
            $end->modify('+4 days');
            $this->end = $end->format('d.m.Y');
        }

        public function getWeek(){
            return $this->week;
        }

        public function getYear(){
            return $this->year;
        }

        public function getStart(){
            return $this->start;
        }

        public function getEnd(){
            return $this->end;
        }

        public function getEnabled(){
            return $this->enabled;
        }

        public function getTakenCapacity(){
            return $this->taken_capacity;
        }

        public function getMaxCapacity(){
            return $this->max_capacity;
        }

        public function getAvailableCapacity(){
            return $this->max_capacity - $this->taken_capacity;
        }

        public function isAvailable(){
            return $this->getAvailableCapacity() > 0;
        }

    }
endif;