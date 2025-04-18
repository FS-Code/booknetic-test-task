<?php

namespace BookneticApp\Backend\Appointments\Helpers;

use BookneticApp\Models\Appointment;
use BookneticApp\Models\Customer;
use BookneticApp\Models\Location;
use BookneticApp\Models\Service;
use BookneticApp\Models\ServiceExtra;
use BookneticApp\Models\ServiceStaff;
use BookneticApp\Models\Staff;
use BookneticApp\Providers\Helpers\Date;
use BookneticApp\Providers\DB\DB;
use BookneticApp\Providers\Helpers\Helper;
use BookneticApp\Providers\Helpers\Math;
use BookneticApp\Providers\Core\Permission;
use Exception;

/**
 * Class AppointmentRequestData
 * @package BookneticApp\Backend\Appointments\Helpers
 *
 * @property Appointment|null $appointmentInf
 * @property Service|null $serviceInf
 * @property Staff|null $staffInf
 * @property Location|null $locationInf
 * @property ServiceStaff|null $serviceStaffInf
 */
class AppointmentRequestData
{

    use ARDHelper;

	/**
	 * @var AppointmentPriceObject[]
	 */
	private $prices = [];
	private $payableToday;

	public $appointmentId;

	public $date;
	public $time;

	public $note;

	public $locationId;
	public $staffId;
	public $serviceId;
	public $serviceCategoryId;

	private $serviceExtras;

	public $totalCustomerCount;

	public $customerData;
	public $customerId = -1;
    public $setBillingData = false;
	public $newCustomerPass;
    public $status;
    public $weight;

	public $paymentMethod;

	public $recurringStartDate;
	public $recurringEndDate;
	public $recurringTimes;
	public $recurringAppointmentsList;

	public $clientTimezone;

	public $createdAppointments = [];

	public $calledFromBackend = false;

	public $timeslots;
    public $createdAt = null;

    private $rawData = [];

    private $appointmentRequests;

    /**
     * @return AppointmentRequests
     */
    public function getAppointmentRequests()
    {
        return $this->appointmentRequests;
    }

    /**
     * @param AppointmentRequests $appointmentRequests
     * @return AppointmentRequestData
     */
    public function setAppointmentRequests($appointmentRequests)
    {
        $this->appointmentRequests = $appointmentRequests;
        return $this;
    }

	/**
	 * Magic methoddan istifade ederek ashagidaki Infolari collect edir.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name )
	{
		switch ( $name ){
			case 'appointmentInf':
				$this->appointmentInf = Appointment::get( $this->appointmentId );
				break;
			case 'serviceInf':
				$this->serviceInf = Service::withTranslations()->get( $this->serviceId );
				break;
			case 'locationInf':
				$this->locationInf = Location::withTranslations()->get( $this->locationId );
				break;
			case 'serviceStaffInf':
                $serviceStaffInf = ServiceStaff::withTranslations()->where( 'service_id', $this->serviceId )->where( 'staff_id', $this->staffId )->fetch();
                if( !empty( $serviceStaffInf ) )
                    $this->serviceStaffInf = $serviceStaffInf;

				break;
			case 'staffInf':
				$this->staffInf = Staff::withTranslations()->get( $this->staffId );
				break;
		}

		return isset( $this->$name ) ? $this->$name : null;
	}

    public static function fromArray( $arr, $calledFromBackend = false )
    {
        $instance = new AppointmentRequestData();
        $instance->rawData = $arr;

        if( $calledFromBackend )
        {
            $instance->calledFromBackend();
        }

        $instance->initDefaultProperties();
        $instance->handleCustomerInfo();
        $instance->handleCalendarServiceCartAppointment();
        $instance->initDefaultPrices();
//        do_action( 'bkntc_appointment_request_data_load', $instance );

        return $instance;
    }

    public function getData($key, $default = null, $check_type = null, $whiteList = [])
    {
        $res = isset($this->rawData[$key]) ? $this->rawData[$key] : $default;

        if( $res !== $default && !is_null( $check_type ) )
        {
            if( $check_type == 'num' || $check_type == 'int' || $check_type == 'integer' )
            {
                $res = is_numeric( $res ) ? (int)$res : $default;
            }
            else if($check_type == 'str' || $check_type == 'string')
            {
                $res = is_string( $res ) ? trim( stripslashes_deep((string)$res) ) : $default;
            }
            else if($check_type == 'arr' || $check_type == 'array')
            {
                $res = is_array( $res ) ? stripslashes_deep((array)$res) : $default;
            }
            else if($check_type == 'float')
            {
                $res = is_numeric( $res ) ? (float)$res : $default;
            }
            else if($check_type == 'email')
            {
                $res = is_string( $res ) && filter_var($res, FILTER_VALIDATE_EMAIL) !== false ? trim( (string)$res ) : $default;
            }
            else if($check_type == 'json')
            {
                $res = json_decode( (string)$res, true );
                $res = is_array( $res ) ? $res : $default;
            }
        }

        if( !empty( $whiteList ) && !in_array( $res , $whiteList ) )
        {
            $res = $default;
        }

        return $res;
    }

	/**
	 * POST Request ile gelen datalar burada emal olunaraq uygun propertieslere oturulur.
	 */
	public function initDefaultProperties()
	{
		$this->appointmentId				=	$this->getData('id', 0, 'int');

		$this->locationId				    =	$this->getData('location', 0, 'int');
		$this->staffId					    =	$this->getData('staff', 0, 'int');
		$this->serviceId				    =	$this->getData('service', 0, 'int');
		$this->serviceCategoryId	        =   $this->getData('service_category', 0, 'int');

		$this->date						    =	$this->getData('date', '', 'str');
		$this->time						    =	$this->getData('time', '', 'str');

		$this->note						    =	$this->getData('note', '', 'str');

		$this->totalCustomerCount		    =	$this->getData('brought_people_count', 0 , 'int') + 1;

		$this->customerData				    =	$this->getData('customer_data', [], 'arr');

		$this->paymentMethod			    =	$this->getData('payment_method', '', 'str' );

		$this->clientTimezone			    =   Helper::_post('client_time_zone', '-', 'string');

		$this->recurringStartDate		    =	$this->getData('recurring_start_date', '', 'string');
		$this->recurringEndDate			    =	$this->getData('recurring_end_date', '', 'string');
		$this->recurringTimes			    =	$this->getData('recurring_times', '', 'string');
		$this->recurringAppointmentsList	=	$this->getData('appointments', '', 'string');

		$this->recurringAppointmentsList	=	json_decode( $this->recurringAppointmentsList, true );
		$this->recurringAppointmentsList	=	is_array( $this->recurringAppointmentsList ) ? $this->recurringAppointmentsList : [];

		$this->date                         = Date::reformatDateFromCustomFormat( $this->date );
		$this->recurringStartDate           = Date::reformatDateFromCustomFormat( $this->recurringStartDate );
		$this->recurringEndDate             = Date::reformatDateFromCustomFormat( $this->recurringEndDate );

		if( $this->calledFromBackend )
		{
			$this->paymentMethod = 'local';
		}

		if( $this->locationId == -1 )
		{
			$locationInf = $this->getAvailableLocations()->limit(1)->fetch();
			$this->locationId = !empty( $locationInf ) ? $locationInf->id : null;
		}

        // doit: bu niye elave olunub? frontda critical bug yaradirdi, commente alindi
//		if( $this->isDateBasedService() )
//		{
//			$this->time = '00:00';
//		}
	}


	public function getServiceExtras( $filterCustomerId = false )
	{
		if( is_null( $this->serviceExtras ) )
		{
			$serviceExtras = $this->getData('service_extras', [], 'arr');

			$this->serviceExtras = [];

			foreach ( $serviceExtras AS $extraDetails )
			{
				if( ! isset( $extraDetails['extra'] ) || ! isset( $extraDetails['quantity'] )
				    || ! ( is_numeric( $extraDetails['extra'] ) && $extraDetails['extra'] > 0 )
					|| ! ( is_numeric( $extraDetails['quantity'] ) && $extraDetails['quantity'] > 0 )
				)
				{
					continue;
				}

				$extraObj = ServiceExtra::where( 'id', $extraDetails['extra'] );

                if ( Helper::getOption('show_all_service_extras', 'off')=='off' )
                {
                    $extraObj = $extraObj->where( 'service_id', $this->serviceId );
                }

                $extraObj = $extraObj->fetch();

				if( $extraObj && $extraObj['max_quantity'] >= $extraDetails['quantity'] && $extraObj['min_quantity'] <= $extraDetails['quantity'] )
				{
					$extraObj['quantity'] = $extraDetails['quantity'];

                    $extraObj['customer'] = $this->customerId;

					$this->serviceExtras[] = $extraObj;
				}
			}

            /*doit add_filter()*/
			$this->serviceExtras = apply_filters( 'bkntc_appointment_data_service_extras', $this->serviceExtras, $this );
		}

		if( ! $filterCustomerId )
		{
			return $this->serviceExtras;
		}

		$filteredExtras = [];
		foreach ( $this->serviceExtras AS $extra )
		{
			if( $filterCustomerId == $extra['customer'] )
			{
				$filteredExtras[] = $extra;
			}
		}

		return $filteredExtras;
	}

	public function getExtrasPrice( $filterCustomerId = false )
	{
		return ExtrasService::calcExtrasPrice( $this->getServiceExtras( $filterCustomerId ) );
	}

	public function getExtrasDuration( $filterCustomerId = false )
	{
		return ExtrasService::calcExtrasDuration( $this->getServiceExtras( $filterCustomerId ) );
	}

	/**
	 * Front-end booking zamani mushteri melumatlari yoxlanilir, onun yeni mushteri ve ya movcud mushteri olmasi arashdirilir.
	 */
	public function handleCustomerInfo()
	{
		if( $this->calledFromBackend )
		{
			$this->customerId           =	$this->getData('customer_id', '', 'int');
			$this->status               =	$this->getData('status', '', 'string');
			$this->weight               =	$this->getData('weight', '', 'int');

			$this->totalCustomerCount   =   0;

            $busyStatuses = Helper::getBusyAppointmentStatuses();

            if ( in_array( $this->status , $busyStatuses ) )
            {
                $this->totalCustomerCount = (int)$this->weight;
            }
		}
		else
		{
            if( ! empty( $this->customerData['email'] ) )
            {
                $wpUserId = Permission::userId();

                if ( $wpUserId > 0 )
                {
                    $checkCustomerExists = Customer::where('user_id', $wpUserId)->fetch();
                }
                else
                {
                    $checkCustomerExists = Customer::where('email', $this->customerData['email'])->fetch();
                }

                if ( $checkCustomerExists )
                {
                    $this->customerId = $checkCustomerExists->id;
                }
            }
            $this->status = Helper::getDefaultAppointmentStatus();
            $this->weight = $this->totalCustomerCount;
		}
	}

	/**
	 * Front-end`de booking zamani yoxlanilir, eger yeni mushteridirse, o halda onun datalari bazaya elave olunur
	 * ve yeni mushteri olaraq qeydiyyatdan kechirilir.
	 */
	public function registerNewCustomer()
	{
		if( $this->customerId == -1 )
		{
			$wpUserId           = Permission::userId();
			$customerWPUserId   = $wpUserId > 0 ? $wpUserId : null;

            if ( ! empty( $this->customerData[ 'email' ] ) )
            {
                $bookneticCustomer = Customer::where([ 'email' => $this->customerData['email'] ])->fetch();
            }
            else if ( ! empty( $this->customerData[ 'phone' ] ) )
            {
                $bookneticCustomer = Customer::where( 'phone_number', $this->customerData[ 'phone' ] )->fetch();
            }
            else
            {
                $bookneticCustomer = null;
            }

            $existingCustomer = empty( $bookneticCustomer ) ? null : $bookneticCustomer->id;

            if(
                is_null( $customerWPUserId )
                && Helper::getOption('new_wp_user_on_new_booking', 'off', false) == 'on'
                && ! empty( $this->customerData['email'] )
            )
            {
                $newCustomerPass = wp_generate_password( 8, false );

                //not sure why we first insert the user then checking for errors, we could just check if wp user exists before this...
                $customerWPUserId = wp_insert_user( [
                    'user_login'	=>	$this->customerData['email'],
                    'user_email'	=>	$this->customerData['email'],
                    'display_name'	=>	$this->customerData['first_name'] . ' ' . $this->customerData['last_name'],
                    'first_name'	=>	$this->customerData['first_name'],
                    'last_name'		=>	$this->customerData['last_name'],
                    'role'			=>	'booknetic_customer',
                    'user_pass'		=>	$newCustomerPass
                ] );

                //if error thrown, it means there's already a WordPress user associated with this email
                if( is_wp_error( $customerWPUserId ) )
                {
                    $user = get_user_by('email',$this->customerData['email']);

                    $customerWPUserId = $user ? $user->ID : null;
                }
                else
                {
                    //save the billing data, not sure why we save this. Probably old code form V2
                    if( !empty( $this->customerData['phone'] ) )
                    {
                        add_user_meta( $customerWPUserId, 'billing_phone', $this->customerData['phone'], true );
                    }
                    add_user_meta($customerWPUserId , 'booknetic_new_wp_user' , true,true);
                }
            }
            else
            {
                $userInfo = wp_get_current_user();

                if( ! Helper::checkUserRole( $userInfo, [ 'administrator', 'booknetic_customer', 'booknetic_staff', 'booknetic_saas_tenant' ] ) )
                {
                    $userInfo->set_role('booknetic_customer');
                }
            }


            //todo: needs to update user_id column if customer is created beforehand ( WordPress user is created after )
            if ( is_null( $existingCustomer ) )
            {
                Customer::insert( [
                    'user_id'		=>	$customerWPUserId,
                    'first_name'	=>	$this->customerData['first_name'],
                    'last_name'		=>	$this->customerData['last_name'],
                    'phone_number'	=>	$this->customerData['phone'],
                    'email'			=>	$this->customerData['email'],
                    'created_at'	=>	date('Y-m-d'),
                ] );
            }

			$this->newCustomerPass    =  isset( $newCustomerPass ) ? $newCustomerPass : '';
            $this->customerId         = is_null( $existingCustomer ) ? DB::lastInsertedId() : $existingCustomer ;

            if ( !empty($this->serviceExtras) )
            {
                foreach ($this->serviceExtras as $k => $serviceExtra)
                {
                    if ($serviceExtra['customer'] == -1)
                    {
                        $this->serviceExtras[$k]['customer'] = $this->customerId;
                    }
                }
            }

			// do_action( 'bkntc_customer_created', $this->customerId, $this->newCustomerPass );
		}

	}

	/**
	 * Eger Any Staff optionu sechilibse o halda staffId=-1 olacaq.
	 * Sechilmish serviceId ve locationId`e uygun gelen butun stafflari listeleyir
	 * Ve o stafflarin her birinin sechilmish date & time`a uygunlugunu yoxlayir.
	 * Uygun gelen stafflardan 1-cisi staffId`e verilir.
	 * Qeyd: Stafflarin siralamasi Settingsdeki algoritmaya uygun olaraq alinir ki, 1-ci staffi goturende uygun staff gelsin.
	 */
	public function handleAnyStaffOption()
	{
		if( $this->staffId != -1 )
			return;

		$availableStaffIDs = AnyStaffService::staffByService( $this->serviceInf->id, $this->locationId, true, $this->date );

		foreach ( $availableStaffIDs AS $staffID )
		{
			$this->staffId          = $staffID;
			unset( $this->staffInf );
			$this->timeslots  = null;

            if( $this->isRecurring() )
                RecurringAppointmentService::emptyRecurringDates();

			$staffIsOkay            = true;

			foreach ($this->getAllTimeslots() AS $timeSlot )
			{
				if( ! $timeSlot->isBookable() )
				{
					$staffIsOkay = false;
					break;
				}
			}

			if( $staffIsOkay )
				break;

			$this->staffId          = -1;
			$this->timeslots  = null;
			unset( $this->staffInf );
		}
	}

	/**
	 * Eger recurring deilse sechilmish date ve time`e uygun eks halda ise butun recurring date & time`lari arraya yigib geri qaytarir.
	 *
	 * @return TimeSlotService[]
	 */
	public function getAllTimeslots()
	{
		if( is_null( $this->timeslots ) )
		{
			$this->timeslots      = [];
			$listAppointments           = $this->isRecurring() ? $this->recurringAppointmentsList : [ [ $this->date , $this->time ] ];

			foreach( $listAppointments AS $appointmentDateAndTime )
			{
				if( empty( $appointmentDateAndTime[0] ) || ( ! $this->isDateBasedService() && empty( $appointmentDateAndTime[1] ) ) )
					continue;

				$timeSlot = new TimeSlotService( $appointmentDateAndTime[0], $appointmentDateAndTime[1] );
				$timeSlot->setDefaultsFrom( $this );

				$this->timeslots[] = $timeSlot;
			}
		}

        //niye?
        if( $this->isRecurring() && empty($this->timeslots) && empty( $this->recurringAppointmentsList ) && !empty( json_decode( $this->recurringTimes, 1 ) ) )
        {
            $this->timeslots = RecurringAppointmentService::getRecurringDates($this);
        }

		return $this->timeslots;
	}

	/**
	 * Recurringi de nezere alaraq butun appointmentlerin sayi.
	 * Tebiiki recurring deyilse geri 1 qaytaracaq.
	 *
	 * @return int
	 */
	public function getTimeslotsCount()
	{
		return count( $this->getAllTimeslots() );
	}

	/**
	 * Eger Service`nin settingsinde butun appointmentleri odemelidir deye sechilibse (The Customer pays full amount of recurring appointments):
	 * O halda butun appointmentlerin sayi qaytarilir, eks halda yalniz birinci appointmenti odemeli olacacg deye 1 qaytarilir.
	 *
	 * @return int
	 */
	public function getPayableAppointmentsCount()
	{
		if( $this->isRecurring() && $this->serviceInf->recurring_payment_type == 'first_month' )
			return 1;

		return $this->getTimeslotsCount();
	}

    /**
     * @throws Exception
     */
    public function validate()
    {
        do_action('bkntc_appointment_request_before_data_validate',$this);

        $this->validateAppointmentsAvailability();
        $this->validateBasicData();
        $this->validateRecurringData();
        $this->validateCustomerData();

        do_action('bkntc_appointment_request_data_validate' , $this );
    }

    /**
     * Eger recurring appointmentdirse o halda sechilmish butun date&time`lari, eks halda yalniz 1 date&time sechilir.
     * Her birinin booking uchun uygun oldugunu validasiya edir.
     * @throws Exception
     */
	public function validateAppointmentsAvailability()
	{
        if( $this->isEdit() &&  ! in_array($this->status , Helper::getBusyAppointmentStatuses()) &&  $this->appointmentInf->starts_at == $this->getAllTimeslots()[0]->getTimestamp() )
            return;

		foreach ($this->getAllTimeslots() AS $timeSlot )
		{
			if( ! $timeSlot->isBookable() )
			{
                if( $this->isEdit() && $this->canBookInsideOfWorkingHours() )
                    return;

                throw new Exception( bkntc__( 'Please select a valid time! ( %s %s is busy! )', [
                    Date::datee( $timeSlot->getDate(), false, true ),
                    Date::time( $timeSlot->getTime(), false, true )
                ] ) );
			}
		}
	}

    private function canBookInsideOfWorkingHours()
    {
        if( ! Permission::isAdministrator() || Helper::getOption( 'allow_admins_to_book_outside_working_hours', 'off' ) !== 'on' )
            return false;

        foreach ( $this->getAllTimeslots() as $timeslot )
        {
            $timeslot->setInsideWorkingHouse( true );
            $timeslot->setIsBookable( null );

            if( ! $timeslot->isBookable() )
                return false;
        }

        return true;
    }

	public function validateBasicData()
	{
		if(
			empty( $this->locationId )
			|| empty( $this->serviceId )
			|| empty( $this->staffId )
		)
		{
			throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
		}

		if( ! $this->isRecurring() && ( ! Date::isValid( $this->date ) || ( ! $this->isDateBasedService() && ! Date::isValid( $this->time ) ) ) )
		{
			throw new \Exception( bkntc__('Please fill the "Date" and "Time" field correctly!') );
		}

		if( ! $this->serviceStaffInf )
		{
			throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
		}

		if( $this->isEdit() && ! $this->appointmentInf )
		{
			throw new \Exception( bkntc__('Appointment not found or permission denied!') );
		}

		if( ! $this->locationInf || ! $this->staffInf )
		{
			throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
		}

		$isStaffAllowed = Staff::where( 'id', $this->staffId )->whereFindInSet( 'locations', $this->locationInf->id )->fetch();

		if ( empty( $isStaffAllowed ) )
		{
			throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
		}
	}

    public function validateCustomerData()
	{
		if( $this->calledFromBackend )
		{
			if( empty( $this->customerId ) )
			{
				throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
			}

            $checkCustomerExists = Customer::get( $this->customerId );
            if( ! $checkCustomerExists )
            {
                throw new \Exception( bkntc__('Please select customers!') );
            }

			return;
		}

        if( ! empty( $this->customerData['email'] ) )
        {
            $wpUserId = Permission::userId();

            if ( $wpUserId > 0 )
            {
                $checkCustomerExists = Customer::where('user_id', $wpUserId)->fetch();

                if ( $checkCustomerExists )
                {
                    if ( $checkCustomerExists->email != $this->customerData['email'] )
                    {
                        throw new \Exception( bkntc__('You cannot use any email other than your own email.') );
                    }
                }

            }
            else
            {
                $checkCustomerExists = Customer::where('email', $this->customerData['email'])->fetch();
            }

            if ( $checkCustomerExists )
            {
                $this->customerId = $checkCustomerExists->id;

                if (
                    ( $checkCustomerExists->phone_number != $this->customerData['phone'] && ! empty( $this->customerData['phone'] ) ) ||
                    ( $checkCustomerExists->first_name != $this->customerData['first_name'] && ! empty( $this->customerData['first_name'] ) ) ||
                    ( $checkCustomerExists->last_name != $this->customerData['last_name'] && ! empty( $this->customerData['last_name'] ) )
                )
                {
                    $this->setBillingData = true;
                }
            }
        }

		$customer_inputs = ['first_name', 'last_name', 'email', 'phone'];

		foreach ( $customer_inputs AS $required_input_name )
		{
			if( !isset( $this->customerData[ $required_input_name ] ) || !is_string( $this->customerData[ $required_input_name ] ) )
			{
				throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
			}
		}

		foreach ( $this->customerData AS $input_name => $customer_datum )
		{
			if( !((in_array( $input_name, $customer_inputs ) || ( strpos( $input_name, 'custom_field' ) === 0)) && is_string($customer_datum)) )
			{
				throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
			}
		}

        $email_required = Helper::getOption('set_email_as_required', 'on');
        $phone_required = Helper::getOption('set_phone_as_required', 'off');

        if ( ! filter_var( $this->customerData['email'], FILTER_VALIDATE_EMAIL ) && $email_required === 'on')
        {
	        throw new \Exception( bkntc__('Please enter a valid email address!') );
        }

        if ( empty( $this->customerData['phone'] ) && $phone_required === 'on')
        {
	        throw new \Exception( bkntc__('Please enter a valid phone number!') );
        }
    }

	public function validateRecurringData()
	{
		if( ! $this->isRecurring() )
			return;

		if( ! empty( $this->recurringAppointmentsList ) )
		{
			foreach( $this->recurringAppointmentsList AS $appointmentElement )
			{
				if(
					!(
						isset( $appointmentElement[0] ) && is_string( $appointmentElement[0] ) && Date::isValid( $appointmentElement[0] ) &&
						isset( $appointmentElement[1] ) && is_string( $appointmentElement[1] ) && Date::isValid( $appointmentElement[1] )
					)
				)
				{
					throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
				}
			}
		}
		else
		{
			if( empty( $this->recurringStartDate ) || empty( $this->recurringEndDate ) )
			{
				throw new \Exception( bkntc__('Please fill "Start date" and "End date" fields!') );
			}

			$recurringType      = $this->serviceInf['repeat_type'];
			$repeat_frequency   = (int)$this->serviceInf['repeat_frequency'];

			if( $recurringType == 'weekly' )
			{
				$recurringTimes = json_decode( $this->recurringTimes, true );

				if( !is_array( $recurringTimes ) || empty( $recurringTimes ) )
				{
					throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
				}

				if( $repeat_frequency > 0 && $repeat_frequency != count( $recurringTimes ) )
				{
					throw new \Exception( bkntc__('Repeat frequency is %d for selected service!', [ (int)$repeat_frequency ]) );
				}
			}
			else if( $recurringType == 'daily' )
			{
				$everyNdays = (int)$this->recurringTimes;

				if( !( $everyNdays > 0) )
				{
					throw new \Exception( bkntc__('Repeat frequency is is invalid!') );
				}

				if( $repeat_frequency > 0 && $repeat_frequency != $everyNdays )
				{
					throw new \Exception( bkntc__('Repeat frequency is %d for selected service!' , [ (int)$repeat_frequency ]) );
				}

				if( empty( $this->time ) || ! Date::isValid( $this->time ) )
				{
					throw new \Exception( bkntc__('Please fill "Time" field!') );
				}
			}
			else if( $recurringType == 'monthly' )
			{
				$recurringTimes = explode(':', (string)$this->recurringTimes);

				if( count( $recurringTimes ) !== 2 )
				{
					throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
				}

				$monthlyType = $recurringTimes[0];
				$monthlyDays = $recurringTimes[1];

				if( $monthlyType == 'specific_day' )
				{
					$monthlyDays = empty($monthlyDays) ? [] : explode(',', $monthlyDays);
                    if( $repeat_frequency > 0  && count($monthlyDays) != $repeat_frequency )
                    {
                        throw new \Exception( bkntc__('Repeat frequency is %d for selected service!' , [ (int)$repeat_frequency ]) );
                    }
				}

				if( empty( $this->time ) || ! Date::isValid( $this->time ) || empty( $monthlyDays ) )
				{
					throw new \Exception( bkntc__('Please fill "Time" field!') );
				}
			}

			if( $this->serviceInf['full_period_value'] > 0 )
			{
				$fullPeriodValue	= (int)$this->serviceInf['full_period_value'];
				$fullPeriodType		= (string)$this->serviceInf['full_period_type'];

				if( $fullPeriodType == 'time' )
				{
					if( count( AppointmentService::getRecurringDates( $this ) ) != $this->serviceInf['full_period_value'] )
					{
						throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
					}
				}
				else if( $fullPeriodType == 'day' || $fullPeriodType == 'week' || $fullPeriodType == 'month' )
				{
					$checkDate = Date::epoch( Date::epoch( $this->recurringStartDate, '+' . $fullPeriodValue . ' ' . $fullPeriodType ), '-1 days' );

					if( $checkDate != Date::epoch( $this->recurringEndDate ) )
					{
						throw new \Exception( bkntc__('Please fill in all required fields correctly!') );
					}
				}
				else
				{
					throw new \Exception( bkntc__('Error! Full period is wrong on Service options! Please edit your service info!') );
				}
			}
		}
	}


	/**
	 * Sechilmish servisin qiymetini geri qaytarir.
	 *
	 * @return float
	 */
	public function getServicePrice()
	{
		if ( $this->serviceStaffInf && $this->serviceStaffInf->price != -1 )
		{
			return Math::floor( $this->serviceStaffInf->price );
		}

		return Math::floor( $this->serviceInf->price );
	}

	/**
	 * Checkout uchun pricelari burdan idare ede bilersiz.
	 * Yeni price elave ede ve ya movcud priceni chagirib uzerinde editler etmek mumkundur.
	 * Her price oz uniqueKey-i ile tanidilir.
	 * Meselen eger "tax" adinda yeni bir price elave etmek isteyirsiznizse, onu bu methodla chagirib, sonra ise meblegi menimsede bilersiz.
	 * Yeginda elave etdiyiniz "tax" adli price da subTotal`in uzerinde toplanacaq avtomatik olarag.
	 * Bu qaydada sonsuz sayda pricelar elave etmek olar ve elave olunan her price da Confirm sehifesindeki checkoutda gorunecek.
	 *
	 * @param string $uniqueKey
	 *
	 * @return AppointmentPriceObject
	 */
	public function price($uniqueKey, $groupByKey = null)
	{
		if( ! isset( $this->prices[ $uniqueKey ] ) )
		{
			if( !isset( $this->prices ) )
			{
				$this->prices = [];
			}

			$this->prices[ $uniqueKey ] = new AppointmentPriceObject( $uniqueKey, $groupByKey );
			$this->prices[ $uniqueKey ]->setAppointmentsCount( $this->getPayableAppointmentsCount() );
		}

		return $this->prices[ $uniqueKey ];
	}

	/**
	 * Elave olunan price`larin siyahisini array sheklinde geri qaytarir.
	 *
	 * @param false $groupPrices - Pricelari hansi parametre gore quruplashdirmagi mueyyenleshdirir. Esasen service_extra`lar uchun istifade olunur. Chunki servis extralar front-endde her biri ayri row kimi dushduyu teqdirde database`e insert zamani onlar qruplashir ve "service_extra" ile insert olunur. Insert zamani bura true gelir, diger hallarda false.
	 *
	 * @return AppointmentPriceObject[]
	 */
	public function getPrices( $groupPrices = false )
	{
		$prices = $this->prices;

		if( ! $groupPrices || empty( $prices ) )
			return $prices;

		/**
		 * @var $groupedPrices AppointmentPriceObject[]
		 */
		$groupedPrices = [];

		foreach ( $prices AS $priceKey => $priceObj )
		{
			if( ! is_null( $priceObj->getGroupByKey() ) )
			{
				$priceKey = $priceObj->getGroupByKey();
			}

			if( isset( $groupedPrices[ $priceKey ] ) )
			{
				$groupedPrices[ $priceKey ]->setPrice( $groupedPrices[ $priceKey ]->getPrice() + $priceObj->getPrice() );
			}
			else
			{
				$groupedPrices[ $priceKey ] = clone $priceObj;
			}
		}

		return $groupedPrices;
	}

	/**
	 * Confirmation stepindeki checkout sectionu uchun pricelarin sirali HTML formasi...
	 *
	 * @return string
	 */
	public function getPricesHTML()
	{
		$pricesHTML = '';

		foreach ( $this->getPrices() AS $price )
		{
			$pricesHTML .= '<div class="booknetic_confirm_details ' . ($price->isHidden() ? ' booknetic_hidden' : '') . '" data-price-id="' . htmlspecialchars($price->getId()) . '">
				<div class="booknetic_confirm_details_title">' . $price->getLabel() . '</div>
				<div class="booknetic_confirm_details_price">' . $price->getPriceView( true ) . '</div>
			</div>';
		}

		return $pricesHTML;
	}

	/**
	 * Butun menimsedilen price`larin toplamini geri qaytarir.
	 * Default olaraq 1 appointment uchun toplam meblegi geri qaytarir.
	 * Eger recurring appointmentdirse ve butun appointmentlerin cemi toplamini almag isteyirsizse argumente true deyerini gonderin.
	 *
	 * @param bool $sumForAllRecurringAppointments
	 *
	 * @return float
	 */
	public function getSubTotal( $sumForAllRecurringAppointments = false )
	{
		$subTotal = 0;

		foreach ( $this->getPrices() AS $priceInf )
		{
			$subTotal += $priceInf->getPrice( $sumForAllRecurringAppointments );
		}

		return $subTotal;
	}

	/**
	 * Paymente gonderilecek hisseni geri qaytarir.
	 * Meselen subTotal 100$ edirse ve 50% deposit varsa, paymente 50$ gedecek.
	 * Default olaraq 1 appointment uchun olan meblegi geri qaytarir.
	 * Eger recurring appointmentdirse ve butun appointmentlerin toplamini almag isteyirsizse argumente true deyerini gonderin.
	 *
	 * @param bool $sumForAllRecurringAppointments
	 *
	 * @return float
	 */
	public function getPayableToday( $sumForAllRecurringAppointments = false )
	{
		if( is_null( $this->payableToday ) )
		{
			if( $this->hasDeposit() )
			{
				$payableToday = $this->getDepositPrice();
			}
			else
			{
				$payableToday = $this->getSubTotal();
			}

			$payableToday = Math::floor( $payableToday );
		}
		else
		{
			$payableToday = $this->payableToday;
		}

		return $sumForAllRecurringAppointments ? Math::floor( $payableToday * $this->getPayableAppointmentsCount() ) : $payableToday;
	}

	/**
	 * Odemeye gonderilecek meblegi deyishdirmek uchun olan method.
	 *
	 * @param float $amount
	 */
	public function setPayableToday( $amount )
	{
		$this->payableToday = $amount;
	}

	/**
	 * Odemeye geden meblegin vergi hissesini geri qaytarir.
	 * Meselen Paypal-da API ile vergi hissesini ayri menimsetmek lazimdi.
	 * Default olaraq 1 appointment uchun olan meblegi geri qaytarir.
	 * Eger recurring appointmentdirse ve butun appointmentlerin toplamini almag isteyirsizse argumente true deyerini gonderin.
	 *
	 * @param bool $sumForAllRecurringAppointments
	 *
	 * @return float
	 */
	public function getPayableTodayPrice( $priceUniqueKey, $sumForAllRecurringAppointments = false )
	{
		$price = $this->getSubTotal( $sumForAllRecurringAppointments ) > 0 ? $this->getPayableToday( $sumForAllRecurringAppointments ) / $this->getSubTotal( $sumForAllRecurringAppointments ) * $this->price($priceUniqueKey)->getPrice( $sumForAllRecurringAppointments ) : 0;

		return Math::floor( $price );
	}

	/**
	 * Deposit odenilmeli olan meblegi geri qaytarir
	 * Default olaraq 1 appointment uchun olan meblegi geri qaytarir.
	 * Eger recurring appointmentdirse ve butun appointmentlerin toplamini almag isteyirsizse argumente true deyerini gonderin.
	 *
	 * @param bool $sumForAllRecurringAppointments
	 *
	 * @return float
	 */
	public function getDepositPrice( $sumForAllRecurringAppointments = false )
	{
		if( $this->serviceStaffInf == null || $this->serviceStaffInf['price'] == -1 )
		{
			$deposit		= $this->serviceInf['deposit'];
			$deposit_type	= $this->serviceInf['deposit_type'];
		}
		else
		{
			$deposit		= $this->serviceStaffInf['deposit'];
			$deposit_type	= $this->serviceStaffInf['deposit_type'];
		}

        $subTotal = $this->getSubTotal( $sumForAllRecurringAppointments );

        if( $deposit_type == 'price' )
		{
			$result =  $sumForAllRecurringAppointments ? Math::floor( $deposit * $this->getPayableAppointmentsCount() ) : Math::floor( $deposit );
		}
		else
		{
			$result = Math::floor( $subTotal * $deposit / 100 );
		}

        if( $result > $subTotal )
            $result = $subTotal;

        return $result;
	}

	/**
	 * Deposit odenishin olub olmadigini check etmek uchundur.
	 *
	 * @return bool
	 */
	public function hasDeposit()
	{
		if( $this->serviceStaffInf == null || $this->serviceStaffInf['price'] == -1 )
		{
			$deposit		= $this->serviceInf['deposit'];
			$deposit_type	= $this->serviceInf['deposit_type'];
		}
		else
		{
			$deposit		= $this->serviceStaffInf['deposit'];
			$deposit_type	= $this->serviceStaffInf['deposit_type'];
		}

		if( $deposit == 0 )
			return false;

		if( $deposit_type == 'price' && $deposit == $this->getServicePrice() )
			return false;

		if( $deposit_type == 'percent' && ($deposit <= 0 || $deposit >= 100) )
			return false;

		if( Helper::_post('deposit_full_amount', 0, 'int', [ 0, 1 ]) === 1 && Helper::getOption('deposit_can_pay_full_amount', 'on') == 'on' )
			return false;

		return true;
	}

	/**
	 * Default pricelari menimsetmek uchundur. Meselen: "service_price", extralarin qiymetleri ve s.
	 */
	public function initDefaultPrices()
	{
		if( empty( $this->serviceId ) )
			return;

        $customerId = $this->customerId;

        $servicePrice = $this->price('service_price')->setIsMergeable(false);
        $servicePrice->setPrice( $this->getServicePrice() * $this->weight );
        $servicePrice->setLabel( Service::getTranslatedAttribute( $this->serviceId, 'name', $this->serviceInf->name ) . ( $this->weight > 1 ? ' [x' . $this->weight . ']' : '' ) );

        foreach ( $this->getServiceExtras($customerId) as $extra )
        {
            $addExtraPrice = $this->price('service_extra_' . $extra['id'], 'service_extra');
            $addExtraPrice->setPrice( $extra['price'] * $extra['quantity'] );
            $addExtraPrice->setLabel( ServiceExtra::getTranslatedAttribute( $extra[ 'id' ], 'name', $extra['name'] ).' [x' . ( $extra['quantity'] ) . ']' );
            $addExtraPrice->setIsMergeable(false);
        }

        $discountPrice = $this->price('discount');
        $discountPrice->setPrice( 0 );
        $discountPrice->setLabel( bkntc__('Discount') );
        $discountPrice->setNegativeOrPositive(-1);

        if( Helper::getOption('hide_discount_row', 'off') == 'on' )
        {
            $discountPrice->setHidden( true );
        }
	}


	public function isRecurring()
	{
        if (empty($this->serviceInf['is_recurring']))
            return false;

		return $this->serviceInf['is_recurring'] == 1 && ! $this->isEdit();
	}

	public function isDateBasedService()
	{
		return $this->serviceInf['duration'] >= 24 * 60;
	}


	public function getDateTimeView()
	{
        if ($this->isRecurring() && empty($this->recurringStartDate)) {
            return '-';
        }

        if (empty($this->date)) return '-';

		if( $this->isRecurring() )
		{
			$dateTimeView = Date::datee( $this->recurringStartDate ) . ' / ' . Date::datee( $this->recurringEndDate );
		}
		else if( ! $this->isDateBasedService() )
		{
			$dateTime = $this->date . ' ' . $this->time;

			$dateTimeView = Date::datee( $dateTime, false, true ) . ' / ' . Date::time( $dateTime, false, true );

			if( Helper::getOption('time_view_type_in_front', '1') == '1' )
			{
				$dateTimeView .= '-' . Date::time( $dateTime, '+' . $this->getAppointmentDuration() . ' minutes', true );
			}
		}
		else
		{
			$dateTime = $this->date . ' ' . $this->time;

			$dateTimeView = Date::datee( $dateTime, false, true );

		}

		return $dateTimeView;
	}

	/**
	 * Appointmentin umumi muddetini hesablayir
	 *
	 * @return int
	 */
	public function getAppointmentDuration()
	{
		return $this->serviceInf->duration + $this->getExtrasDuration();
	}

	/**
	 * Recurring ola bileceyini de nezere alaraq ilk appointmentin ID-sini geri qaytarir.
	 *
	 * @return int
	 */
	public function getFirstAppointmentId()
	{
		return reset( $this->createdAppointments );
	}

	/**
	 * Back-end`den load olursa eger bu object o halda bu methoda true deyeri gonderilmelidir.
	 *
	 * @param bool $isBackend
	 *
	 * @return $this
	 */
	public function calledFromBackend( $bool = true )
	{
		$this->calledFromBackend = $bool;

		return $this;
	}

	/**
	 * Appointmentin editi prosesi olub olmadigini yoxlamag uchun method.
	 *
	 * @return bool
	 */
	public function isEdit()
	{
		return $this->calledFromBackend && $this->appointmentId > 0;
	}

    /**
     * Checks if the customer ( if customer_id is defined ) already has made an appointment for the same timeslot ( starting time usually considered )
     *
     * @return bool
     */
    #[\ReturnTypeWillChange] //escape from php 8.1 deprecation
    public function hasDuplicateBooking()
    {
        if ( ! $this->calledFromBackend && ! $this->isRecurring() && $this->customerId > 0 )
        {
            foreach ( $this->getAllTimeslots() AS $timeslot )
            {
                return (bool) Appointment::where( 'starts_at', $timeslot->getTimestamp() )
//                    ->where( 'ends_at', $timeslot->getTimestamp() + ((int) $this->serviceInf->duration + (int) $this->getExtrasDuration()) * 60 ) //allow if needed
                    ->where( 'location_id', $this->locationId )
                    ->where( 'service_id', $this->serviceId )
                    ->where( 'staff_id', $this->staffId )
                    ->where( 'customer_id', $this->customerId )
                    ->fetchAll();
            }
        }

        return false;
    }

    /**
     * Simulates the selected timeslot as an appointment, so the calendarService would consider the selected timeslot as busy appointment.
     *
     * @return void
     */
    private function handleCalendarServiceCartAppointment()
    {
        $listAppointments = $this->isRecurring() ? $this->recurringAppointmentsList : [ [ $this->date , $this->time ] ];

        foreach( $listAppointments AS $appointmentDateAndTime )
        {
            if( empty( $appointmentDateAndTime[0] ) || ( ! $this->isDateBasedService() && empty( $appointmentDateAndTime[1] ) ) )
                return;
        }

        if( $this->staffId != -1 )
            return;

        $this->handleAnyStaffOption();

        add_filter('bkntc_staff_appointments', function ( $staffAppointments, CalendarService $calendarService )
        {
            // note: anystaff olduqda bütün stafflara əlavə olunur, digər halda staff_id eynidirsə
            if ($this->staffId != $calendarService->getStaffId() && $this->staffId > 0)
                return $staffAppointments;

            foreach ($this->getAllTimeslots() as $timeslot)
            {
                // add or merge $timeslot into $staffAppointments
                $merged = false;
                foreach ($staffAppointments as $staffAppointment)
                {
                    if (
                        $staffAppointment->starts_at == $timeslot->getTimestamp() &&
                        $staffAppointment->service_id == $timeslot->getServiceId() &&
                        $staffAppointment->location_id == $timeslot->getLocationId()
                    )
                    {
                        $staffAppointment->total_weight += $this->weight;
                        $merged = true;
                        break;
                    }
                }

                if ( $merged )
                {
                    continue;
                }

                $appointment = new Appointment();
                $appointment->staff_id = $timeslot->getStaffId();
                $appointment->location_id = $timeslot->getLocationId();
                $appointment->service_id = $timeslot->getServiceId();
                $appointment->starts_at = $timeslot->getTimestamp();
                $appointment->ends_at = $timeslot->getTimestamp() + ((int) $this->serviceInf->duration + (int) $this->getExtrasDuration()) * 60;
                $appointment->busy_from = $timeslot->getTimestamp() - ((int) $this->serviceInf->buffer_before) * 60;
                $appointment->busy_to = $timeslot->getTimestamp() + ((int) $this->serviceInf->duration + (int) $this->getExtrasDuration() + (int) $this->serviceInf->buffer_after) * 60;
                $appointment->total_weight = $this->weight;
                $staffAppointments[] = $appointment;
            }

            return $staffAppointments;
        }, 10, 2);
    }

}
