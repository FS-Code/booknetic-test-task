<?php

namespace BookneticApp\Frontend\Controller;

use BookneticApp\Backend\Appearance\Helpers\Theme;
use BookneticApp\Backend\Appointments\Helpers\AppointmentChangeStatus;
use BookneticApp\Backend\Appointments\Helpers\AppointmentRequests;
use BookneticApp\Backend\Appointments\Helpers\CalendarService;
use BookneticApp\Models\Appearance;
use BookneticApp\Models\ExtraCategory;
use BookneticApp\Models\Location;
use BookneticApp\Models\Translation;
use BookneticApp\Providers\Core\Capabilities;
use BookneticApp\Backend\Appointments\Helpers\AppointmentService;
use BookneticApp\Models\Appointment;
use BookneticApp\Models\Customer;
use BookneticApp\Models\Service;
use BookneticApp\Models\ServiceCategory;
use BookneticApp\Models\ServiceExtra;
use BookneticApp\Models\ServiceStaff;
use BookneticApp\Models\Staff;
use BookneticApp\Providers\Core\Permission;
use BookneticApp\Providers\Helpers\Date;
use BookneticApp\Providers\DB\DB;
use BookneticApp\Frontend;
use BookneticApp\Providers\Core\FrontendAjax;
use BookneticApp\Providers\Common\PaymentGatewayService;
use BookneticApp\Providers\Helpers\Helper;

class Ajax extends FrontendAjax
{
    private $categories;

	public function __construct()
	{

	}

	public function get_data_location()
	{
		$appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

		$locations = $appointmentObj->getAvailableLocations()->withTranslations()->fetchAll();

		return $this->view('booking_panel.locations', [
			'locations'		=>	$locations
		]);
	}

    public function get_booking_panel_necessary_files()
    {
        // Array of files to be included
        // 'type' can be either 'js' or 'css'
        // 'src' is the file source location
        // 'id' is an identifier for the file
        $files = [
            [
                'type' => 'js',
                'src'  => Helper::assets('js/booknetic-popup.js', 'front-end' ),
                'id'   => 'booknetic-popup',
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets('css/booknetic-popup.css', 'front-end' ),
                'id'   => 'booknetic-popup',
            ],
            [
                'type' => 'js',
                'src'  => Helper::assets('js/booknetic.js', 'front-end' ),
                'id'   => 'booknetic',
            ],
            [
                'type' => 'js',
                'src'  => Helper::assets('js/select2.min.js' ),
                'id'   => 'select2-bkntc',
            ],
            [
                'type' => 'js',
                'src'  => Helper::assets('js/datepicker.min.js', 'front-end'),
                'id'   => 'booknetic.datapicker',
            ],
            [
                'type' => 'js',
                'src'  => Helper::assets('js/jquery.nicescroll.min.js', 'front-end'),
                'id'   => 'jquery.nicescroll',
            ],
            [
                'type' => 'js',
                'src'  => Helper::assets('js/intlTelInput.min.js', 'front-end'),
                'id'   => 'intlTelInput',
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets( 'css/bootstrap-booknetic.css', 'front-end' ),
                'id'   => 'bootstrap-booknetic'
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets( 'css/booknetic.css', 'front-end' ),
                'id'   => 'booknetic'
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets( 'css/select2.min.css' ),
                'id'   => 'select2'
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets( 'css/select2-bootstrap.css' ),
                'id'   => 'select2-bootstrap'
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets('css/select2.min.css'),
                'id'   => 'select2'
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets( 'css/datepicker.min.css', 'front-end' ),
                'id'   => 'booknetic.datapicker'
            ],
            [
                'type' => 'css',
                'src'  => Helper::assets( 'css/intlTelInput.min.css', 'front-end' ),
                'id'   => 'intlTelInput'
            ],
        ];

        $theme = Helper::_post( 'theme' , 0 , 'int' );

        if( $theme > 0 )
        {
            $theme = Appearance::get( $theme );
        }

        if( empty( $theme ) )
        {
            $theme = Appearance::where( 'is_default', '1' )->fetch();
        }

        $fontFamily = $theme ? $theme[ 'fontfamily' ] : 'Poppins';
        $files[]    = [
            'type' => 'css',
            'src'  => '//fonts.googleapis.com/css?family='.urlencode( $fontFamily ).':200,200i,300,300i,400,400i,500,500i,600,600i,700&display=swap',
            'id'   => 'Booknetic-font'
        ];


        //retrieves the data necessary for the booknetic.js script to work.
        $bookneticJSData = Helper::getBookneticJSData();

        if( Helper::getOption( 'google_recaptcha', 'off', false ) == 'on' )
        {
            $siteKey   = Helper::getOption('google_recaptcha_site_key', '', false );
            $secretKey = Helper::getOption('google_recaptcha_secret_key', '', false );

            if( ! empty( $siteKey ) && ! empty( $secretKey ) )
            {
                $files[] = [
                    'type' => 'js',
                    'src'  => 'https://www.google.com/recaptcha/api.js?render=' . urlencode( $siteKey ),
                    'id'   => 'google-recaptcha'
                ];

                $bookneticJSData[ 'google_recaptcha_site_key' ] = $siteKey;
            }
        }

        $scripts = [ 'window.BookneticData = ' . json_encode( $bookneticJSData ) . ';' ];

        $theme_id = $theme ? $theme['id'] : 0;

        if( $theme_id > 0 )
        {
            $themeCssFile = Theme::getThemeCss( $theme_id );

            $files[] = [
                'type' => 'css',
                'src'  => str_replace( [ 'http://', 'https://' ], '//', $themeCssFile ),
                'id'   => 'booknetic-theme'
            ];
        }

        $results = apply_filters( 'bkntc_add_files_through_ajax', [ 'files' => $files, 'scripts' => $scripts ] );

        return $this->response( true, [ 'results' => $results ] );
    }

    public function get_booking_panel()
    {
        add_shortcode('booknetic', [\BookneticApp\Providers\Core\Frontend::class, 'addBookneticShortCode']);

        $atts = [
            'location'   => Helper::_post('location' , '' , 'int'),
            'staff'      => Helper::_post('staff' , '' , 'int'),
            'service'    => Helper::_post('service' , '' , 'int'),
            'category'   => Helper::_post('category' , '' , 'int'),
            'theme'      => Helper::_post('theme' , '' , 'int'),
        ];

        $shortcode = "booknetic";

        foreach ($atts as $key=>$value ) {
            if( ! empty( $value ) )
            {
                $shortcode .= " $key=$value";
            }
        }

        $bookneticShortcode =  do_shortcode( "[$shortcode]" );

        return $bookneticShortcode;
	}

	public function get_data_staff()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

		$staffList      = Staff::where('is_active', 1)->withTranslations()->orderBy('id');

        if( $appointmentObj->serviceCategoryId > 0 )
        {
            $categoriesFiltr = Helper::getAllSubCategories( $appointmentObj->serviceCategoryId );

            $services = Service::select(['id'])->where('category_id' , 'in' ,array_values($categoriesFiltr))->fetchAll();

            $servicesIdList = array_map(function ($service){
                return $service->id;
            },$services);

            $servicesStaffList = ServiceStaff::select(['staff_id'])->where('service_id' , 'in' ,$servicesIdList)->fetchAll();

            $filterStaffIdList = array_map(function ($serviceStaff){
                return $serviceStaff->staff_id;
            },$servicesStaffList);

            $staffList->where('id' ,'in' , $filterStaffIdList);
        }


		if( $appointmentObj->locationId > 0 )
		{
			$staffList->whereFindInSet( 'locations', $appointmentObj->locationId );
		}

		if( $appointmentObj->serviceId > 0 )
		{
			$subQuery = ServiceStaff::where('service_id', $appointmentObj->serviceId)
				->where( 'staff_id', DB::field( 'id', 'staff' ) )
				->select('count(0)');

			$staffList->where( $subQuery, '>', 0 );
		}

		$staffList = $staffList->fetchAll();

		if( $appointmentObj->getTimeslotsCount() > 0 )
		{
			$onlyAvailableStaffList = [];

			foreach ( $staffList AS $staffInf )
			{
				$appointmentObj->staffId            = $staffInf->id;
				$appointmentObj->timeslots    = null;
				$staffIsOkay                        = true;

				foreach ($appointmentObj->getAllTimeslots() AS $timeSlot )
				{
					if( ! $timeSlot->isBookable() )
					{
						$staffIsOkay = false;
						break;
					}
				}

				if( $staffIsOkay )
					$onlyAvailableStaffList[] = $staffInf;

				$appointmentObj->staffId = null;
				$appointmentObj->timeslots = null;
			}

			$staffList = $onlyAvailableStaffList;
		}

        $staffList = array_map(function ($staff){
            $staff['name']          = htmlspecialchars($staff['name']);
            $staff['email']         = htmlspecialchars($staff['email']);
            $staff['phone_number']  = htmlspecialchars($staff['phone_number']);
            $staff['profession']    = htmlspecialchars($staff['profession']);
            return $staff;
        } , $staffList);

		return $this->view('booking_panel.staff', [
			'staff'		=>	$staffList
		]);
	}

	public function get_data_service()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

		$queryAttrs = [ $appointmentObj->staffId ];
		if( $appointmentObj->serviceCategoryId > 0 )
        {
            $categoriesFiltr = Helper::getAllSubCategories( $appointmentObj->serviceCategoryId );
        }

		$locationFilter = '';
		if( $appointmentObj->locationId > 0 && !( $appointmentObj->staffId > 0 ) )
		{
			$locationFilter = " AND tb1.`id` IN (SELECT `service_id` FROM `".DB::table('service_staff')."` WHERE `staff_id` IN (SELECT `id` FROM `".DB::table('staff')."` WHERE FIND_IN_SET('{$appointmentObj->locationId}', IFNULL(`locations`, ''))))";
		}

		$services = DB::DB()->get_results(
			DB::DB()->prepare( "
				SELECT
					tb1.*,
					IFNULL(tb2.price, tb1.price) AS real_price,
					(SELECT count(0) FROM `" . DB::table('service_extras') . "` WHERE service_id=tb1.id AND `is_active`=1) AS extras_count,
					(SELECT `data_value` FROM `" . DB::table('data') . "` WHERE `table_name`='services' AND `data_key`='only_visible_to_staff' AND `row_id`=tb1.id ) AS only_visible_to_staff
				FROM `" . DB::table('services') . "` tb1 
				".( $appointmentObj->staffId > 0 ? 'INNER' : 'LEFT' )." JOIN `" . DB::table('service_staff') . "` tb2 ON tb2.service_id=tb1.id AND tb2.staff_id=%d
				WHERE tb1.`is_active`=1 AND (SELECT count(0) FROM `" . DB::table('service_staff') . "` WHERE service_id=tb1.id)>0 ".DB::tenantFilter()." ".$locationFilter."
				" . ( $appointmentObj->serviceCategoryId > 0 && !empty( $categoriesFiltr ) ? "AND tb1.category_id IN (". implode(',', $categoriesFiltr) . ")" : "" ) . "
				ORDER BY tb1.category_id, tb1.id", $queryAttrs ),
			ARRAY_A
		);

        if ( empty( $services ) )
            goto end;

        // Burda raw query isletdiyimiz ucun modelin default behaviour-u ile translate ede bilmirik
        $categoryIds = [];
        $serviceIds = array_map( function ($service) use ( &$categoryIds ) {
            $categoryIds[] = $service['category_id'];
            return $service['id'];
        }, $services );

        $categoryTranslations = Translation::where( 'row_id', 'in', $categoryIds )
            ->where( 'table_name', 'service_categories' )
            ->where( 'locale', Helper::getLocaleForFrontend() )
            ->fetchAll();
        $servicesOrder = json_decode(Helper::getOption( "services_order" ), true);
        $orderedServices = [];
        if ( ! empty( $servicesOrder ) ) {
            $serviceIds = [];
            foreach ( $servicesOrder as $k => $v ) {
                $serviceIds = array_merge( $serviceIds, $v );
            }
            foreach ( $serviceIds as $item ) {
                foreach ( $services as $k => $service ) {
                    if ( $service["id"] == $item ) {
                        $orderedServices[] = $service;
                        unset( $services[$k] );
                    }
                }
            }
        } else {
            $allCategories = Helper::assocByKey(ServiceCategory::fetchAll(), 'id');
            $categories = $this->flatToTree( $allCategories );
            $categoriesIds = $this->pluckChildren($categories);

            foreach ($categoriesIds as $categoryId) {
                foreach ( $services as $k => $service ) {
                    if ($service["category_id"] == $categoryId) {
                        $orderedServices[] = $service;
                        unset( $services[$k] );
                    }
                }
            }
        }
        $services = array_merge( $orderedServices, $services );

        $serviceTranslations = Translation::where( 'row_id', 'in', $serviceIds )
            ->where( 'table_name', 'services' )
            ->where( 'locale', Helper::getLocaleForFrontend() )
            ->fetchAll();
        // END

		$onlyVisibleToStaff = [];

		foreach ( $services as $k => &$service )
		{
			if ( isset($service['only_visible_to_staff']) && (int) $service['only_visible_to_staff'] === 1 )
			{
				$onlyVisibleToStaff[] = $k;
				continue;
			}

            $categoryDetails = $this->__getServiceCategoryName( $service['category_id']);

            $services[$k]['category_name'] =  $this->findServiceTranslation( $categoryTranslations, $service[ 'category_id' ], 'name', $categoryDetails['name'] );
			$services[$k]['category_parent_id'] = $categoryDetails['parent_id'];
            $services[$k]['name'] = htmlspecialchars( $this->findServiceTranslation( $serviceTranslations, $service[ 'id' ], 'name', $service['name'] ) );
            $note = htmlspecialchars( $this->findServiceTranslation( $serviceTranslations, $service[ 'id' ], 'note', $service['notes'] ) );

			$services[$k]['notes'] = $note;

			$wrappedNote = Helper::cutText( $note, 180 );
			$wrappedNoteLines = explode("\n", $wrappedNote);
			$hasManyLines = is_array($wrappedNoteLines) && count($wrappedNoteLines) > 2;

			if($hasManyLines)
			{
				$wrappedNote = implode("\n", [$wrappedNoteLines[0], $wrappedNoteLines[1]]);
			}

			$shouldWrap = (mb_strlen( $note ) > 180) || $hasManyLines;

			$services[$k]['wrapped_note'] = $wrappedNote;
			$services[$k]['should_wrap'] = $shouldWrap;
		}

		foreach ( $onlyVisibleToStaff as $k )
		{
			unset( $services[$k] );
		}

        end:
		return $this->view('booking_panel.services', [
			'services'		=>	$services
		]);
	}

    private function pluckChildren($data = [], $isChildren = true, $relationKey = "parent_id", $customChildrenKey = "child")
    {
        $idSet = [];
        $key = $isChildren ? $customChildrenKey : $relationKey;

        foreach ($data as $item) {
            $idSet[] = $item['id'];

            if (!empty($item[$key])) {
                $idSet = array_merge($idSet, $this->pluckChildren($item[$key]));
            }

        }

        return $idSet;
    }

    private function flatToTree( $elements, $parentId = 0 ) {

        $branch = array();

        foreach ($elements as $element) {
            if ( $element['parent_id'] == $parentId ) {

                $children = $this->flatToTree( $elements, $element['id'] );
                if ($children) {
                    $element['child'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

    protected function findServiceTranslation( $data, $id, $columnName, $defaultValue = "" ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $item ):
                if (
                    isset( $item [ 'row_id' ] ) &&
                    isset( $item[ 'column_name' ] ) &&
                    $item[ 'row_id' ] === $id &&
                    $item[ 'column_name' ] === $columnName
                )
                {
                    return $item[ 'value' ];
                }
            endforeach;
        }

        return $defaultValue;
    }

	public function get_data_service_extras()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

		$extras	= ServiceExtra::withTranslations()->where('is_active', 1)->where('max_quantity', '>', 0)->orderBy('-category_id DESC');

        if ( Helper::getOption( 'show_all_service_extras', 'off' )=='off' )
        {
            $extras = $extras->where( 'service_id', $appointmentObj->serviceId );
        }

        $extras = $extras->fetchAll();

        $extraCategories = array_column( $extras, 'category_id' );

        $categoryLastExtras = [];

        if ( ! empty( $extraCategories ) )
        {
            $extraCategories = ExtraCategory::select([ 'id', 'name' ])->where( 'id', 'IN' , $extraCategories )->fetchAll();

            $extraCategories = array_combine( array_column( $extraCategories, 'id' ), $extraCategories );

            foreach ( array_reverse( $extras ) as $extra )
            {
                if ( ! is_null( $extra->category_id ) && ! in_array( $extra->category_id, $categoryLastExtras ) )
                {
                    $categoryLastExtras[ $extra->id ] = $extra->category_id;
                }
            }

        }

		foreach ( $extras as &$extraInf )
		{
			$wrappedNote = Helper::cutText( $extraInf[ 'notes' ], 180 );
			$wrappedNoteLines = explode("\n", $wrappedNote);
			$hasManyLines = is_array($wrappedNoteLines) && count($wrappedNoteLines) > 2;

			if($hasManyLines)
			{
				$wrappedNote = implode("\n", [$wrappedNoteLines[0], $wrappedNoteLines[1]]);
			}

			$shouldWrap = (mb_strlen( $extraInf[ 'notes' ] ) > 180) || $hasManyLines;

			$extraInf['wrapped_note'] = $wrappedNote;
			$extraInf['should_wrap'] = $shouldWrap;
		}

		return $this->view('booking_panel.extras', [
			'extras'		        =>	$extras,
            'service_name'          =>  htmlspecialchars($appointmentObj->serviceInf->name),
			'extra_categories'	    =>	$extraCategories,
            'category_last_extras'  => $categoryLastExtras,
		]);
	}

    /**
     * @throws \Exception
     */
    public function get_data_date_time()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

		if( ! $appointmentObj->serviceInf )
		{
			return $this->response( false, bkntc__('Please fill in all required fields correctly!') );
		}

		$month			= Helper::_post('month', null, 'int', [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ]);
		$year			= Helper::_post('year', Date::format('Y'), 'int');

        if($month === null )
        {
            $defaultStartMonth = Helper::getOption('booking_panel_default_start_month');
            $month = empty($defaultStartMonth) ? Date::format('m') : $defaultStartMonth;
        }

		$date_start		= Date::dateSQL( $year . '-' . $month . '-01' );
		$date_end		= Date::format('Y-m-t', $year . '-' . $month . '-01' );

		// check for "Limited booking days" settings...
        $available_days_for_booking = Helper::getOption('available_days_for_booking', '365');
        if ( $available_days_for_booking > -1 ) {
            $limitEndDate = Date::epoch( $available_days_for_booking > 0 ? '+' . $available_days_for_booking . ' days' : Date::format('d.m.Y 23:59:59'));

            if( Date::epoch( $date_end ) > $limitEndDate )
            {
                $date_end = Date::dateSQL( $limitEndDate );
            }
        }

        $recurringStartDate = '';

		if( $appointmentObj->isRecurring() )
		{
			$service_type = 'recurring_' . ( in_array( $appointmentObj->serviceInf->repeat_type, ['daily', 'weekly', 'monthly'] ) ? $appointmentObj->serviceInf->repeat_type : 'daily' );
            $calendarData = null;

            $startDateInt = ( new CalendarService( $date_start, $date_end ) )->setDefaultsFrom( $appointmentObj )->getFirstAvailableDay();

            $minTimePriorBooking = Date::epoch( '+' . Helper::getOption( 'min_time_req_prior_booking', '0' ) . ' minutes' );

            if ( $minTimePriorBooking > $startDateInt )
            {
                $startDateInt = $minTimePriorBooking;
            }

            $recurringStartDate = Date::datee( $startDateInt );
        }
		else
		{
			$service_type = 'non_recurring';

			$calendarData = new CalendarService( $date_start, $date_end );
			$calendarData = $calendarData->setDefaultsFrom( $appointmentObj )->getCalendar();

			$calendarData['hide_available_slots'] = Helper::getOption('hide_available_slots', 'off');
		}

		return $this->view('booking_panel.date_time_' . $service_type, [
			'date_based'	        =>	$appointmentObj->serviceInf->duration >= 1440,
			'service_max_capacity'	=>  (int) $appointmentObj->serviceInf->max_capacity > 0 ? (int) $appointmentObj->serviceInf->max_capacity : 1,
            'recurring_start_date'  => $recurringStartDate

		], [
			'data'			    =>	$calendarData,
			'service_type'	    =>	$service_type,
			'time_show_format'  =>  Helper::getOption('time_view_type_in_front', '1'),
			'calendar_start_month'  =>  (int)$month,
			'service_info'	    =>	[
				'date_based'		=>	$appointmentObj->isDateBasedService(),
				'repeat_type'		=>	htmlspecialchars( $appointmentObj->serviceInf->repeat_type ),
				'repeat_frequency'	=>	htmlspecialchars( $appointmentObj->serviceInf->repeat_frequency ),
				'full_period_type'	=>	htmlspecialchars( $appointmentObj->serviceInf->full_period_type ),
				'full_period_value'	=>	(int)$appointmentObj->serviceInf->full_period_value
			]
		]);
	}

	// isokay
	public function get_data_recurring_info()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

        if( $appointmentObj->staffId == 0 )
            $appointmentObj->staffId = -1;

		if( ! $appointmentObj->isRecurring() )
		{
			return $this->response(false, bkntc__('Please select service'));
		}

		try {
			$appointmentObj->validateRecurringData();
		}
		catch ( \Exception $e )
		{
			return $this->response( false, $e->getMessage() );
		}

		$recurringAppointments = AppointmentService::getRecurringDates( $appointmentObj );

		if( ! count( $recurringAppointments ) )
		{
			return $this->response(false , bkntc__('Please choose dates' ));
		}

		return $this->view('booking_panel.recurring_information', [
			'appointmentObj'    => $appointmentObj,
			'appointments'      => $recurringAppointments
		]);
	}

	public function get_data_information()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

//		if( $appointmentObj->serviceId <= 0 )
//		{
//			$checkAllFormsIsTheSame = DB::DB()->get_results('SELECT * FROM `'.DB::table('forms').'` WHERE (SELECT count(0) FROM `'.DB::table('services').'` WHERE FIND_IN_SET(`id`, `service_ids`) AND `is_active`=1)<(SELECT count(0) FROM `'.DB::table('services').'` WHERE `is_active`=1)' . DB::tenantFilter(), ARRAY_A);
//
//            if( !$checkAllFormsIsTheSame )
//			{
//				$firstRandomService = Service::where('is_active', '1')->limit(1)->fetch();
//				$appointmentObj->serviceId = $firstRandomService->id;
//			}
//		}

		// Logged in user data
		$name		= '';
		$surname	= '';
		$email		= '';
		$phone 		= '';
        $emailDisabled = false;


		if( is_user_logged_in() )
		{
            $emailDisabled = true;
            $wpUserId = get_current_user_id();
            $checkCustomerExists = Customer::where('user_id', $wpUserId)->fetch();

            if ($checkCustomerExists)
            {
                $name		= $checkCustomerExists->first_name;
                $surname	= $checkCustomerExists->last_name;
                $email		= $checkCustomerExists->email;
                $phone		= $checkCustomerExists->phone_number;
            }
            else
            {
                $userData = wp_get_current_user();

                $name		= $userData->first_name;
                $surname	= $userData->last_name;
                $email		= $userData->user_email;
                $phone		= get_user_meta( $wpUserId, 'billing_phone', true );
            }
        }
        else
        {
            $appointmentCount = count($appointmentRequests->appointments);
            if ( $appointmentCount > 1 )
            {
                $lastAppointment = $appointmentRequests->appointments[$appointmentCount-2];
                $name       = isset($lastAppointment->customerData['first_name']) ? $lastAppointment->customerData['first_name'] : '';
                $surname    = isset($lastAppointment->customerData['last_name']) ? $lastAppointment->customerData['last_name'] : '';
                $email      = isset($lastAppointment->customerData['email']) ? $lastAppointment->customerData['email'] : '';
                $phone      = isset($lastAppointment->customerData['phone']) ? $lastAppointment->customerData['phone'] : '';
            }
        }

		$emailIsRequired = Helper::getOption('set_email_as_required', 'on');
		$phoneIsRequired = Helper::getOption('set_phone_as_required', 'off');

		$howManyPeopleCanBring = false;

		foreach ($appointmentObj->getAllTimeslots() AS $appointments )
		{
            if ( ! Service::getData( $appointmentObj->serviceId, "bring_people",1 ) )
            {
                break;
            }

			$timeslotInf = $appointments->getInfo()['info'];

            if(empty($timeslotInf)) continue;
			$availableSpaces = $timeslotInf['max_capacity'] - $timeslotInf['weight'] - 1;

			if( $howManyPeopleCanBring === false || $availableSpaces < $howManyPeopleCanBring )
			{
				$howManyPeopleCanBring = $availableSpaces;
			}
		}

		return $this->view('booking_panel.information', [
			'service'                   => $appointmentObj->serviceId,

			'name'				        => $name,
			'surname'			        => $surname,
			'email'				        => $email,
			'phone'				        => $phone,

			'email_is_required'	        => $emailIsRequired,
			'phone_is_required'	        => $phoneIsRequired,
            'email_disabled'            => $emailDisabled,

			'show_only_name'            => Helper::getOption('separate_first_and_last_name', 'on') == 'off',

			'how_many_people_can_bring' =>  $howManyPeopleCanBring
		]);
	}

    public function get_data_cart()
    {
        $currentIndex = Helper::_post('current' , 0 ,'int');
        $appointmentRequests = AppointmentRequests::load();

        return $this->view( 'booking_panel.cart', [
            'appointmentList'   => $appointmentRequests ,
            'current_index'     => $currentIndex
        ] );
    }

	// isokay
	public function get_data_confirm_details()
	{

        $appointmentRequests = AppointmentRequests::load();

        if( ! $appointmentRequests->validate() )
        {
            return $this->response(false,['errors'=>$appointmentRequests->getErrors()]);
        }

        $appointmentObj = $appointmentRequests->currentRequest();

		$hide_confirm_step      = Helper::getOption('hide_confirm_details_step', 'off') == 'on';
		$hide_price_section	    = Helper::getOption('hide_price_section', 'off');
		$hideMethodSelecting    = $appointmentRequests->getSubTotal(true) <= 0 || Helper::getOption('disable_payment_options', 'off') == 'on';

        $arr = [
            PaymentGatewayService::getInstalledGatewayNames()
        ];

        foreach ($appointmentRequests->appointments as $appointmentRequestData)
        {
            $serviceCustomPaymentMethods = $appointmentRequestData->serviceInf->getData( 'custom_payment_methods' );
            $serviceCustomPaymentMethods = json_decode( $serviceCustomPaymentMethods ,true );
            $arr[] = empty( $serviceCustomPaymentMethods ) ? PaymentGatewayService::getEnabledGatewayNames() : $serviceCustomPaymentMethods;
        }

        //todo: i don't remember why i check this variables with isset, because they aren't defined in this function,
        //      but it appears that it was defiend before, or got here from some buffer ( ob_start )
        if ( ! isset( $showDepositLabel ) ) $showDepositLabel = false;
        if ( ! isset( $depositPrice ) ) $depositPrice = 0;

        foreach ( $appointmentRequests->appointments AS $appointment )
        {
            if ( $appointment->hasDeposit() )
            {
                $showDepositLabel = true;
                $depositPrice += $appointment->getDepositPrice(true);
            }
            else
            {
                $depositPrice += $appointment->getSubTotal();
            }


        }

        $allowedPaymentMethods = call_user_func_array('array_intersect' , $arr);

        $hideMethodSelecting = apply_filters('bkntc_hide_method_selecting',$hideMethodSelecting , $appointmentRequests);
		return $this->view('booking_panel.confirm_details', [
			'appointmentData'           =>  $appointmentObj,
            'custom_payment_methods'    =>  $allowedPaymentMethods,
            'appointment_requests'      =>  $appointmentRequests,
			'hide_confirm_step'		    =>	$hide_confirm_step,
            'hide_payments'			    =>	$hideMethodSelecting,
            'hide_price_section'        =>  $hide_price_section == 'on',
            'has_deposit'               =>  $showDepositLabel,
            'deposit_price'             =>  $depositPrice,
            'has_duplicate_booking'     =>  $appointmentObj->hasDuplicateBooking()
		], [
            'has_deposit'               =>  $appointmentObj->hasDeposit()
        ] );
	}

	// isokay
	public function confirm()
	{
		if( ! Capabilities::tenantCan( 'receive_appointments' ) )
			return $this->response( false );

		try
		{
			AjaxHelper::validateGoogleReCaptcha();
		}
		catch ( \Exception $e )
		{
			return $this->response( false, $e->getMessage() );
		}

        $appointmentRequests = AppointmentRequests::load();

        if( ! $appointmentRequests->validate() )
        {
            return $this->response(false,$appointmentRequests->getFirstError());
        }

        foreach ($appointmentRequests->appointments as $appointment)
        {
            if( $appointment->isRecurring() && empty( $appointment->recurringAppointmentsList ) )
            {
                return $this->response(false, bkntc__('Please fill in all required fields correctly!'));
            }
        }

		do_action( 'bkntc_booking_step_confirmation_validation', $appointmentRequests );

		$paymentGateway = PaymentGatewayService::find( $appointmentRequests->paymentMethod );

		if ( ( ! $paymentGateway || ! $paymentGateway->isEnabled( $appointmentRequests ) ) && $appointmentRequests->paymentMethod !== 'local' )
		{
			return $this->response( false, bkntc__( 'Payment method is not supported' ) );
		}

        if ( $appointmentRequests->paymentMethod === 'local' && ! $paymentGateway->isEnabled( $appointmentRequests ) )
        {
            return $this->response( false, bkntc__( 'Payment method is not supported' ) );
        }

        foreach ($appointmentRequests->appointments as $appointment)
        {
            $appointment->registerNewCustomer();
        }

		AppointmentService::createAppointment( $appointmentRequests );

		$payment = $paymentGateway->doPayment( $appointmentRequests );

		$responseStatus = is_bool( $payment->status ) ? $payment->status : false;
		$responseData   = is_array( $payment->data ) ? $payment->data : [];

		$responseData['id']                     = $appointmentRequests->appointments[0]->getFirstAppointmentId();
		$responseData['google_calendar_url']    = AjaxHelper::addToGoogleCalendarURL( $appointmentRequests->appointments[0] );
        $responseData['icalendar_url']          = AjaxHelper::addToiCalendarURL( $appointmentRequests->appointments[0] );
		$responseData['payment_id']             = Appointment::get( $responseData['id'] )->payment_id;
		$responseData['payable_today']          = $appointmentRequests->getPayableToday();
		$responseData['sub_total']              = $appointmentRequests->getSubTotal(true);
        $responseData['customer_id']            = $appointmentRequests->appointments[0]->customerId;

        if( array_key_exists( 'remote_payment_id', $responseData ) )
        {
            Appointment::setData( $responseData[ 'id' ], 'remote_payment_id', $responseData[ 'remote_payment_id' ] );
        }

        if ($appointmentRequests->appointments[0]->createdAt != null)
        {
            $timeLimit = Helper::getOption('max_time_limit_for_payment', 10);

            if ( $timeLimit > 1440 ) //1 day
            {
                $timeLimit = 1440;
            }

            $responseData["expires_at"] = $appointmentRequests->appointments[0]->createdAt + ($timeLimit * 60);
        }

		return $this->response( $responseStatus, $responseData );
	}

	public function delete_unpaid_appointment()
	{
		$paymentId                    = Helper::_post('payment_id', '', 'string');
        $appointmentList = Appointment::where('payment_id' , $paymentId )->where('payment_status' ,'<>','paid')->fetchAll();

		if( empty($appointmentList) )
		{
			return $this->response( true );
		}

        foreach ($appointmentList as $appointment)
        {
            AppointmentService::deleteAppointment( $appointment->id );
        }

		return $this->response( true );
	}

    // doit: bu evvel backendin ajaxin simulyasiya edirdi, baxaq umumi helpere cixaraq sonda
	public function get_available_times_all()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

        $search		= Helper::_post('q', '', 'string');
        $dayOfWeek	= Helper::_post('day_number', 1, 'int');

        if( $dayOfWeek != -1 )
        {
            $dayOfWeek -= 1;
        }

        $calendarServ = new CalendarService();

        $calendarServ->setStaffId( $appointmentObj->staffId )
            ->setServiceInf( $appointmentObj->serviceInf )
            ->setLocationId( $appointmentObj->locationId );

        return $this->response(true, [
            'results' => $calendarServ->getCalendarByDayOfWeek( $dayOfWeek, $search )
        ]);
	}

    public function get_recurring_available_dates()
    {
        $appointmentInf = AppointmentRequests::load()->currentRequest();

        if( Helper::isSaaSVersion() )
        {
            Permission::setTenantId( $appointmentInf->tenant_id );
        }

        $startDate = $appointmentInf->recurringStartDate;
        $endDate = $appointmentInf->recurringEndDate;

        $calendarData = new CalendarService( $startDate , $endDate );
        $calendarData->setStaffId( $appointmentInf->staffId )
            ->setLocationId( $appointmentInf->locationId )
            ->setServiceId( $appointmentInf->serviceId )
            ->setServiceExtras( $appointmentInf->getServiceExtras() )
            ->setShowExistingTimeSlots( true );
        $calendarData = $calendarData->getCalendar();

        $availableDates = array_keys( array_filter($calendarData['dates'], function ($item)
        {
            return ! empty($item);
        }));

        $availableDates = array_map(function ($availableDate){
            return Date::convertDateFormat($availableDate);
        },$availableDates);

        $appointments  =json_decode($appointmentInf->getData('appointments','[]','str'),1);

        $appointments = array_map(function ($arr){
            return Date::convertDateFormat($arr[0]);
        },$appointments);

        $availableDates = array_filter($availableDates,function ($date) use($appointments)
        {
           return ! in_array($date,$appointments);
        });

        $availableDates = array_values($availableDates);

        return $this->response(true, [ 'available_dates' => $availableDates ] );
    }

    public function get_available_times()
	{
		$ajax = new \BookneticApp\Backend\Appointments\Ajax();
        return $ajax->get_available_times( false );
	}

    // doit: bu evvel backendin ajaxin simulyasiya edirdi, baxaq umumi helpere cixaraq sonda
	public function get_day_offs()
	{
        $appointmentRequests = AppointmentRequests::load();

        $appointmentObj = $appointmentRequests->currentRequest();

        if(
            ! Date::isValid( $appointmentObj->recurringStartDate )
            || ! Date::isValid( $appointmentObj->recurringEndDate )
            || $appointmentObj->serviceId <= 0
        )
        {
            return $this->response(false, bkntc__('Please fill in all required fields correctly!'));
        }

        $calendarService = new CalendarService( $appointmentObj->recurringStartDate, $appointmentObj->recurringEndDate );
        $calendarService->setDefaultsFrom( $appointmentObj );

        return $this->response( true, $calendarService->getDayOffs() );
	}

	private function __getServiceCategoryName( $categId )
	{
		if( is_null( $this->categories ) )
		{
			$this->categories = ServiceCategory::fetchAll();
		}

		$categNames   = [];
        $categParents = 0;
		$attempts = 0;
		while( $categId > 0 && $attempts < 10 )
		{
			$attempts++;
			foreach ( $this->categories AS $category )
			{
				if( $category['id'] == $categId )
				{
					$categNames[] = $category['name'];
                    if ( $attempts == 1 ) $categParents = $category['parent_id'];
					$categId = $category['parent_id'];
					break;
				}
			}
		}

		return [
            'name'      => implode(' > ', array_reverse($categNames)),
            'parent_id' => $categParents,
        ];
	}
    public function change_status()
    {
        $token = Helper::_post('bkntc_token', 0, 'string');

        $response = AppointmentChangeStatus::validateToken($token);
        if ( $response !== true) return $this->response(false, $response);

        $tokenParts = explode('.', $token);
        $header = json_decode( base64_decode( $tokenParts[0] ), true );
        $payload = json_decode( base64_decode( $tokenParts[1] ), true );


        $id = $header['id'];
        $status = $payload['changeTo'];

        if ( ! array_key_exists($status, Helper::getAppointmentStatuses()) )
            return $this->response(false, [ 'error_msg' => bkntc__('Something went wrong.') ] );

        AppointmentService::setStatus($id, $status);

        return $this->response( true );
    }
}
