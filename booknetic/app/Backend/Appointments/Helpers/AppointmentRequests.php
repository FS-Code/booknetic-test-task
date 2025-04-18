<?php

namespace BookneticApp\Backend\Appointments\Helpers;

use BookneticApp\Providers\Helpers\Date;
use BookneticApp\Providers\Helpers\Math;
use BookneticApp\Providers\Helpers\Helper;

class AppointmentRequests
{

    public $paymentMethod;
    public $paymentId;

    /**
     * @var AppointmentRequestData[]
     */
    public $appointments = [];
    private $current = 0;
    private $errors = [];
    public $calledFromBackend;

    public static function load( $calledFromBackend = false)
    {

        $instance = new AppointmentRequests();
        $instance->calledFromBackend = $calledFromBackend;

        $instance->paymentMethod			    =	Helper::_post('payment_method', '', 'str' );
        $instance->current			            =	Helper::_post('current', 0, 'int' );

        if( $calledFromBackend )
        {
            $instance->paymentMethod = 'local';
        }

        $sampleJsonData = json_decode(Helper::_post('cart', '[]', 'str'), true);

        if (empty($sampleJsonData) || !is_array($sampleJsonData))
        {
            $sampleJsonData = [[]];
        }

        foreach ($sampleJsonData as $key => $datum)
        {
            $datum['payment_method'] = $instance->paymentMethod;
            $instance->appointments[] = AppointmentRequestData::fromArray($datum, $calledFromBackend)
                ->setAppointmentRequests($instance);
        }

        foreach ($instance->appointments as $appointment)
        {
            do_action( 'bkntc_appointment_request_data_load', $appointment );
        }

        do_action('bkntc_appointment_requests_load', $instance);

        return $instance;
    }

    public function validate()
    {
        $this->errors = [];

        try
        {
            do_action('bkntc_appointment_requests_validate', $this );
        }
        catch (\Exception $e)
        {
            $this->errors[] = [ 'message' => $e->getMessage() ];
        }

        foreach ($this->appointments as $key=>$appointment)
        {
            try
            {
                $appointment->validate();
            }
            catch (\Exception $e)
            {
                $this->errors[] = [ 'message' => $e->getMessage(), 'cart_item' => $key ];
            }
        }

        try
        {
            do_action('bkntc_after_appointment_requests_validate', $this );
        }
        catch (\Exception $e)
        {
            $this->errors[] = [ 'message' => $e->getMessage() ];
        }

        return empty($this->errors);
    }
    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }


    /**
     * @return false| string
     */
    public function getFirstError()
    {
        $first = reset($this->errors);
        return empty($first) ? false : reset($first);
    }


    /**
     * @return false|AppointmentRequestData
     */
    public function currentRequest()
    {
        return array_key_exists($this->current, $this->appointments) ? $this->appointments[$this->current] : end($this->appointments);
    }

    /**
     * @return float
     */
    public function getPayableToday ()
    {
        $payableToday = 0;

        foreach ( $this->appointments as $appointmentObj )
        {
            $payableToday += $appointmentObj->getPayableToday( true );
        }

        return Math::floor( $payableToday );
    }

    /**
     * @return float
     */
    public function getSubTotal( $sumForAllRecurringAppointments = false )
    {
        $subTotal = 0;

        foreach ( $this->appointments as $appointment )
        {
            $subTotal += $appointment->getSubTotal( $sumForAllRecurringAppointments );
        }

        return Math::floor( $subTotal );
    }

    public function getDepositPrice( $sumForAllRecurringAppointments = false )
    {
        $depositPrice = 0;

        foreach ( $this->appointments as $appointment )
        {
            $depositPrice += $appointment->getDepositPrice( $sumForAllRecurringAppointments );
        }

        return Math::floor( $depositPrice );
    }

    public function getPrices( $sumForAllRecurringAppointments = false )
    {
        if ( count($this->appointments) == 1)
        {
            return $this->appointments[0]->getPrices();
        }

        $pricesTop = [];
        $pricesMerged = [];

        foreach ($this->appointments as $key => $appointment)
        {
            $topPrice = new AppointmentPriceObject('cart-item-' . $key);
            $topPrice->setLabel($appointment->serviceInf->name);
            $newSumForAllRecurringAppointments = $appointment->serviceInf->recurring_payment_type == 'full' && $sumForAllRecurringAppointments;
            foreach ($appointment->getPrices($newSumForAllRecurringAppointments) as $price)
            {
                if (!$price->isMergeable())
                {
                    $topPrice->setPrice( $topPrice->getPrice($newSumForAllRecurringAppointments) + $price->getPrice($newSumForAllRecurringAppointments) );
                }
                else
                {
                    if (array_key_exists($price->getId(), $pricesMerged))
                    {
                        $pricesMerged[$price->getId()]->setPrice( $pricesMerged[$price->getId()]->getPrice() + $price->getPrice($newSumForAllRecurringAppointments) );
                    }
                    else
                    {
                        $newPrice = new AppointmentPriceObject($price->getId());
                        $newPrice->setLabel($price->getLabel());
                        $newPrice->setHidden( $price->isHidden() );
                        $newPrice->setPrice($price->getPrice($newSumForAllRecurringAppointments));
                        $newPrice->setColor( $price->getColor() );
                        $pricesMerged[$price->getId()] = $newPrice;
                    }
                }
            }
            $pricesTop[] = $topPrice;
        }

        return array_merge($pricesTop, array_values($pricesMerged));
    }

    public function getPricesHTML( $sumForAllRecurringAppointments = false )
    {
        $pricesHTML = '';

        foreach ( $this->getPrices($sumForAllRecurringAppointments) AS $price )
        {
            $pricesHTML .= '<div class="booknetic_confirm_details ' . ($price->isHidden() ? ' booknetic_hidden' : '') . '" data-price-id="' . htmlspecialchars($price->getId()) . '">
            <div ' . ($price->getColor() ? 'style="color:' . $price->getColor() . ' !important"' : '') . ' class="booknetic_confirm_details_title">' . $price->getLabel() . '</div>
            <div ' . ($price->getColor() ? 'style="color:' . $price->getColor() . ' !important"' : '') . ' class="booknetic_confirm_details_price">' . $price->getPriceView( $sumForAllRecurringAppointments ) . '</div>
        </div>';
        }

        return $pricesHTML;
    }

}