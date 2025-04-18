<?php

namespace BookneticApp\Backend\Calendar;

use BookneticApp\Models\Location;
use BookneticApp\Models\Service;
use BookneticApp\Models\Staff;
use BookneticApp\Providers\Core\Capabilities;

class Controller extends \BookneticApp\Providers\Core\Controller
{

	public function index()
	{
		Capabilities::must( 'calendar' );

		$locations	= Location::fetchAll();
		$services	= Service::fetchAll();
		$staff		= Staff::fetchAll();

		$this->view( 'index' , [
			'locations'	=>	$locations,
			'services'	=>	$services,
			'staff'		=>	$staff
		] );
	}

}
