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
        private int $temporarily_reserved_capacity;

        function __construct($week, $year, $reservation_uuid = null)
        {
            $VersandkostenBeezAvailabilityDao = VersandkostenBeezAvailabilityDao::getInstance();

            $this->week = $week;
            $this->year = $year;
            $this->enabled = $VersandkostenBeezAvailabilityDao->is_available($week, $year, $reservation_uuid);
            $this->max_capacity = $VersandkostenBeezAvailabilityDao->get_max_availability($week, $year);
            $this->taken_capacity = $VersandkostenBeezAvailabilityDao->get_taken_availability($week, $year, $reservation_uuid);
            $this->temporarily_reserved_capacity = $VersandkostenBeezAvailabilityDao->get_reserved_capacity($week, $year);

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

        public function getWeek(): int
        {
            return $this->week;
        }

        public function getYear(): int
        {
            return $this->year;
        }

        public function getStart(): string
        {
            return $this->start;
        }

        public function getEnd(): string
        {
            return $this->end;
        }

        public function getEnabled(): bool
        {
            return $this->enabled;
        }

        public function getTakenCapacity(): int
        {
            return $this->taken_capacity;
        }

        public function getMaxCapacity(): int
        {
            return $this->max_capacity;
        }

        public function getAvailableCapacity(): int
        {
            return $this->max_capacity - $this->taken_capacity;
        }

        public function getTemporarilyReservedCapacity()
        {
            return $this->temporarily_reserved_capacity;
        }

    }
endif;