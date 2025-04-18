<?php

namespace BookneticApp\Backend\Appointments\Helpers;

use BookneticApp\Providers\Helpers\Date;
use BookneticApp\Providers\Helpers\Helper;
use Exception;

class TimeSlotService extends ServiceDefaults implements \JsonSerializable
{

	private $date;
	private $time;

	private $isBookable;

	public function __construct( $date, $time )
	{
		$this->date = $date;
		$this->time = $time;
	}


	public function getDate( $formatDate = false )
	{
		return $formatDate ? Date::datee( $this->date ) : $this->date;
	}

	public function getTime( $formatTime = false )
	{
		return $formatTime ? Date::time( $this->time ) : $this->time;
	}

    public function getTimestamp()
    {
        return Date::epoch( $this->date . ' ' . $this->time );
    }

    public function setIsBookable( $bool )
    {
        $this->isBookable = $bool;
        return $this;
    }

	public function isBookable()
	{
		if( ! is_null( $this->isBookable ) )
            return $this->isBookable;

        $this->initIsBookable(); //initialize value for isBookable property

        return $this->isBookable;
	}

	public function getInfo()
	{
        $result = ['info'=>[]];
		$allTimeslotsForToday = new CalendarService( Date::dateSQL( $this->getDate(), '-1 days' ), Date::dateSQL( $this->getDate(), '+1 days' ) );
		$allTimeslotsForToday->setDefaultsFrom( $this );
		$slots = $allTimeslotsForToday->getCalendar('timestamp');

        // todo  True will be an option in the future
        $result['combinedSlots'] = true ? $this->combinedSlots($slots['dates']) : [];

        if (array_key_exists($this->getTimestamp(), $slots['dates']))
        {
            $result['info'] = $slots['dates'][$this->getTimestamp()];
        }

		return $result;
	}

    private function combinedSlots($items)
    {
        $newArr = [];
        $lastEndTimestamp = null;
        $lastKey = null;

        foreach ($items as $key => $item) {
            $currentItemEndTimestamp = $key + $item["duration"] * 60;

            if ($lastEndTimestamp == $key) {
                $newArr[$lastKey]['end'] = $currentItemEndTimestamp;
            } else {
                $lastKey = $key;
                $newArr[$lastKey] = ["start" => $key, "end" => $currentItemEndTimestamp];
            }
            $lastEndTimestamp = $currentItemEndTimestamp;
        }
        return $newArr;
    }

	public function toArr()
	{
		return [
			'date'              =>  $this->getDate(),
			'time'              =>  $this->getTime(),
			'date_format'       =>  $this->getDate( true ),
			'time_format'       =>  $this->getTime( true ),
			'is_bookable'       =>  $this->isBookable()
		];
	}

	public function jsonSerialize()
	{
		return $this->toArr();
	}

    private function initIsBookable()
    {
        $dayDif                  = (int)( (Date::epoch( $this->date ) - Date::epoch()) / 60 / 60 / 24 );
        $availableDaysForBooking = Helper::getOption('available_days_for_booking', '365');

        if( ! $this->calledFromBackEnd && $dayDif > $availableDaysForBooking )
        {
            $this->isBookable = false;
            return;
        }

        $result               = $this->getInfo();
        $selectedTimeSlotInfo = $result[ 'info' ];

        if( empty( $selectedTimeSlotInfo ) )
        {
            $appointmentStart = $this->getTimestamp();

            $appointmentEnd   = $appointmentStart + $this->getServiceInf()->duration * 60  + ExtrasService::calcExtrasDuration( $this->serviceExtras );

            $this->isBookable = false;

            foreach ( $result[ 'combinedSlots' ] as $combinedSlot )
            {
                if ( $appointmentStart >= $combinedSlot[ 'start' ] && $appointmentEnd <= $combinedSlot[ 'end' ] )
                {
                    $this->isBookable = true;
                    break;
                }
            }

            return;
        }

        if( ( $selectedTimeSlotInfo[ 'weight' ] + $this->totalCustomerCount ) > $selectedTimeSlotInfo[ 'max_capacity' ] )
        {
            $this->isBookable = false;
            return;
        }

        $this->isBookable = true;
    }

}