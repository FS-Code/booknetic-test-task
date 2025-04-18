<?php

namespace BookneticApp\Backend\Appointments\Helpers;

use BookneticApp\Models\Appointment;
use BookneticApp\Models\ServiceStaff;
use BookneticApp\Models\Staff;
use BookneticApp\Providers\Helpers\Date;
use BookneticApp\Providers\DB\DB;
use BookneticApp\Providers\Helpers\Helper;

class AnyStaffService
{

	public static function staffByService( $serviceId, $locationId, $sortByRule = false, $date = null )
	{
		$staffIDs = [];
		$queryAppend = '';
		if( $serviceId > 0 )
		{
			$queryArgs = [ $serviceId ];
			if( $locationId > 0 )
			{
				$queryAppend = ' AND FIND_IN_SET(%d,`locations`)';
				$queryArgs[] = $locationId;
			}

			$staffList = DB::DB()->get_results(DB::DB()->prepare('SELECT * FROM `'.DB::table('service_staff').'` tb1 WHERE `service_id`=%d AND (SELECT count(0) FROM `'.DB::table('staff').'` WHERE id=tb1.`staff_id` AND `is_active`=1'.DB::tenantFilter().$queryAppend.')>0', $queryArgs), ARRAY_A);
			foreach ( $staffList AS $staff )
			{
				$staffIDs[] = (int)$staff['staff_id'];
			}
		}
		else
		{
			$allStaff = Staff::where('is_active', 1)->fetchAll();
			foreach ( $allStaff AS $staff )
			{
				$staffLocations = empty( $staff['locations'] ) ? [] : explode( ',', $staff['locations'] );

				if( !($locationId > 0) || in_array( $locationId, $staffLocations ) )
				{
					$staffIDs[] = (int)$staff['id'];
				}
			}
		}

		if( !empty( $staffIDs ) && $sortByRule && !empty( $date ) )
		{
			$staffIDs = self::sortStaffByRule( $staffIDs, $date, $serviceId );
		}

		return $staffIDs;
	}

	public static function sortStaffByRule( $staffIDs, $date, $service )
	{
		$rule = Helper::getOption('any_staff_rule', 'least_assigned_by_day');

		if( $rule == 'most_expensive' || $rule == 'least_expensive' )
		{
			$getStaff = ServiceStaff::where('staff_id', $staffIDs);

			if( $service > 0 )
			{
				$getStaff = $getStaff->where('service_id', $service);
			}

			$getStaff = $getStaff->orderBy('price ' . ($rule == 'least_expensive' ? 'ASC' : 'DESC'))->fetchAll();
		}
		else
		{
			preg_match('/_([a-z]+)$/', $rule, $dateRule);
			$dateRule = isset($dateRule[1]) ? $dateRule[1] : '';

			if( $dateRule == 'day' )
			{
				$startDate	= Date::epoch($date, 'today');
				$endDate	= Date::epoch($date, 'tomorrow');
			}
			else if( $dateRule == 'week' )
			{
				$startDate	= Date::epoch($date, 'monday this week');
				$endDate	= Date::epoch($date, 'monday next week');
			}
			else
			{
				$startDate	= Date::epoch($date, 'first day of this month');
				$endDate	= Date::epoch($date, 'first day of next month');
			}

			$orderType = strpos( $rule, 'most_' ) === 0 ? 'DESC' : 'ASC';

			$subQuery = Appointment::where('staff_id', DB::field('id', 'staff'))
			                       ->where('starts_at', '>=', $startDate)
			                       ->where('ends_at', '<=', $endDate)
				->select('count(0)');

			$getStaff = Staff::select('id')
				->selectSubQuery( $subQuery, 'appointments_count' )
				->where('id', $staffIDs)
				->orderBy('appointments_count ' . $orderType)
				->fetchAll();
		}

		$sortedList = [];
		foreach ( (!empty($getStaff) ? $getStaff : []) AS $staff )
		{
			$sortedList[] = isset( $staff->staff_id ) ? (string)$staff->staff_id : (string)$staff->id;
		}

		foreach ( $staffIDs AS $staffID )
		{
			if( !in_array( (string)$staffID, $sortedList ) )
			{
				$sortedList[] = (string)$staffID;
			}
		}

		return $sortedList;
	}

}