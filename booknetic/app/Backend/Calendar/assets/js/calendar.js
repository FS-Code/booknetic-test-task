var FSCalendar,
	FSCalendarResources = [],
	FSCalendarRange = {};

function reloadCalendarFn()
{
	var location	=	$("#calendar_location_filter").val(),
		service		=	$("#calendar_service_filter").val(),
		staff		=	[],
		activeRange	=	FSCalendar.state.dateProfile.activeRange,
		startDate	=	activeRange.start.getUTCFullYear() + '-' + booknetic.zeroPad(parseInt(activeRange.start.getUTCMonth())+1) + '-' + booknetic.zeroPad(activeRange.start.getUTCDate()),
		endDate		=	activeRange.end.getUTCFullYear() + '-' + booknetic.zeroPad(parseInt(activeRange.end.getUTCMonth())+1) + '-' + booknetic.zeroPad(activeRange.end.getUTCDate());

	$(".staff-section > .selected-staff").each(function()
	{
		if( $(this).data('staff') == '0' ) // all staff
		{
			staff = [];
			return;
		}

		staff.push( $(this).data('staff') );
	});

	booknetic.ajax( 'get_calendar', {location: location, service: service, staff: staff, start: startDate, end: endDate}, function(result )
	{
		let weekDays = JSON.parse(result.businessHours.timesheet);

		weekDays.map( (day) => {
			if ( day.day_off == 1 ) {
				day.start = '00:00';
				day.end = '00:00';
			}
			return day;
		} );

		var eventSources = FSCalendar.getEventSources();
		for (var i = 0; i < eventSources.length; i++)
		{
			eventSources[i].remove();
		}

		result['data'].forEach(function(item, key)
		{
			if(typeof item.title != 'undefined')
			{
				result['data'][key].title = booknetic.htmlspecialchars_decode(item.title);
			}
		});
		let arr = [];
		FSCalendarResources = [];
		result['data'].forEach((val)=>{
			if(arr.indexOf(val['staff_id']) === -1){
				arr.push(val['staff_id']);
				FSCalendarResources.push({
					id:val['staff_id'],
					title:val['staff_name'],
				});
			}
		});
		FSCalendar.refetchResources();

		FSCalendar.addEventSource( result['data'] );

		FSCalendarRange = {
			weekDays: weekDays,
			appointments: result[ 'data' ],
			start: new Date( startDate ),
			end: new Date( endDate )
		}

		reloadCalendarHours()
	});
}

function reloadCalendarHours()
{
	if ( FSCalendarRange.settingOption )
	{
		FSCalendarRange.settingOption = false;
		return;
	}

	let appointmentsInRange = FSCalendarRange.appointments.filter( ( appointment ) =>
	{
		return 	( new Date( appointment.start ).getTime() > (new Date(FSCalendar.state.dateProfile.activeRange.start.getTime()).setHours(0,0,0,0) ) ) &&
				( new Date( appointment.end ).getTime() < (new Date(FSCalendar.state.dateProfile.activeRange.end.getTime()).setHours(0,0,0,0) ) );
	});

	let startTime, endTime

	if ( FSCalendar.view.type === 'resourceTimeGridDay' )
	{
		let day = FSCalendar.state.dateProfile.currentRange.start.getDay() - 1;

		if ( day === -1 )
			day = 6;

		startTime = FSCalendarRange.weekDays[ day ].start
		endTime = FSCalendarRange.weekDays[ day ].end

	}
	else if ( FSCalendar.view.type === 'timeGridWeek' )
	{
		const weekDaysWithoutDayOffs = FSCalendarRange.weekDays.filter( (day) => {
			return day.day_off == 0;
		} )

		startTime = weekDaysWithoutDayOffs.reduce( ( accumulator, current ) => {
			return current.start < accumulator.start ? current : accumulator
		} ).start
		endTime = weekDaysWithoutDayOffs.reduce( ( accumulator, current ) => {
			return current.end > accumulator.end ? current : accumulator
		} ).end
	}
	else
	{
		startTime = '00:00';
		endTime = '24:00'
	}


	if ( appointmentsInRange.length <= 0 )
	{
		FSCalendarRange.settingOption = true;

		FSCalendar.batchRendering( function()
		{
			FSCalendar.setOption('minTime', startTime);
			FSCalendar.setOption('maxTime', endTime);
		});

		return;
	}

	let appointmentMaxStartTime = booknetic.reformatTimeFromCustomFormat( appointmentsInRange.reduce( ( accumulator, current ) =>
	{
		return booknetic.reformatTimeFromCustomFormat( accumulator.start_time ) > booknetic.reformatTimeFromCustomFormat( current.start_time ) ? current : accumulator
	}).start_time );

	let appointmentMaxEndTime = booknetic.reformatTimeFromCustomFormat( appointmentsInRange.reduce( ( accumulator, current ) =>
	{
		return booknetic.reformatTimeFromCustomFormat( accumulator.end_time ) > booknetic.reformatTimeFromCustomFormat( current.end_time ) ? accumulator : current
	}).end_time );

	startTime = startTime > appointmentMaxStartTime ? appointmentMaxStartTime : startTime;
	endTime = endTime > appointmentMaxEndTime ? endTime : appointmentMaxEndTime;


	FSCalendarRange.settingOption = true;

	FSCalendar.batchRendering( function()
	{
		FSCalendar.setOption('minTime', startTime);
		FSCalendar.setOption('maxTime', endTime);
	});
}

(function ($)
{
	"use strict";

	$(document).ready(function()
	{
		$(".filters_panel select").select2({
			theme: 'bootstrap',
			placeholder: booknetic.__('select'),
			allowClear: true
		});

		$('[data-toggle="tooltip"]').tooltip();

		$(document).on('click', '.staff_arrow_left', function()
		{
			let staffs = $( '.staff-section' );
			let sl 	   = staffs.scrollLeft();

			if( booknetic.isRtl() )
			{
				sl = sl < staffs[ 0 ].scrollWidth - staffs.outerWidth() ? parseInt(sl) - 100 : sl;
			}
			else
			{
				sl = sl > 0 ? parseInt(sl) - 100 : 0;
			}

			staffs.stop().animate( {scrollLeft: sl} );
		}).on('click', '.staff_arrow_right', function ()
		{
			let staffs = $( '.staff-section' );
			let sr 	   = staffs.scrollLeft();

			sr = parseInt(sr) + 100 ;

			staffs.stop().animate( {scrollLeft: sr} );
		}).on('click', '.staff-section > div', function ()
		{
			if( $(this).hasClass('selected-staff') )
			{
				$(this).removeClass('selected-staff');
			}
			else
			{
				$(this).addClass('selected-staff');
			}

			reloadCalendarFn();
		}).on('change', '.filters_panel select', reloadCalendarFn).on('click', '.create_new_appointment_btn', function ()
		{
			booknetic.loadModal('appointments.add_new', {});
		}).on('click', '.add-appointment-on-calendar', function ()
		{
			var date = $(this).closest('td').data('date');
			booknetic.loadModal('appointments.add_new', {date});
		}).on('mouseenter', '.fc-view-container td', function() {
			const index = $(this).index();
			let td = $(this).parents('table:eq(0)').find('thead').find('tr').find('td:eq('+index+')');
			if(typeof td.attr('data-date') == 'undefined')
			{
				td = $(this).parents('table:eq(0)').find('tbody').find('tr').find('td:eq('+index+')');
				if(typeof td.attr('data-date') == 'undefined')
					return false;
			}
			td.append('<a class="add-appointment-on-calendar" title="'+booknetic.__('new_appointment')+'"><i class="fa fa-plus"></i></a>');
		}).on('mouseleave', '.fc-view-container td', function() {
			const index = $(this).index();
			let td = $(this).parents('table:eq(0)').find('thead').find('tr').find('td:eq('+index+')');
			if(typeof td.attr('data-date') == 'undefined')
			{
				td = $(this).parents('table:eq(0)').find('tbody').find('tr').find('td:eq('+index+')');
				if(typeof td.attr('data-date') == 'undefined')
					return false;
			}
			td.find('a.add-appointment-on-calendar').remove();
		});

		if( timeFormat == 'H:i' )
		{
			var timeFormatObj = {
				hour:   '2-digit',
				minute: '2-digit',
				hour12: false,
				meridiem: false
			};
		}
		else
		{
			var timeFormatObj = {
				hour:   'numeric',
				minute: '2-digit',
				omitZeroMinute: true,
				meridiem: 'short'
			};
		}

		FSCalendar = new FullCalendar.Calendar( $("#fs-calendar")[0],
		{
			header: {
				left: 'prev,today,next',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek,resourceTimeGridDay,listWeek'
			},
			schedulerLicenseKey: '0793382538-fcs-1637838415',
			defaultView: 'dayGridMonth',
			resources: function (info , success , error) {
				success(FSCalendarResources);
			},
			plugins: [ 'interaction', 'dayGrid', 'resourceTimeGrid', 'list' ],
			/*eventMouseEnter: function( mouseEnterInfo ) {
				if( mouseEnterInfo.view.type === "timeGridDay")
				{
					let newHeight = ( $( '.fc-slats' ).find('tr').height() ) * 2 ;

					$(mouseEnterInfo.el).css('height', newHeight);

				}
			},
			eventMouseLeave: function( mouseLeaveInfo  ) {
				if( mouseLeaveInfo.view.type === "timeGridDay")
				{
					$(mouseLeaveInfo.el).css('height', '');
				}
			},*/
			editable: false,
			dir: booknetic.isRtl() ? 'rtl' : 'ltr',
			eventLimit: 2,
			navLinks: true,
			firstDay: weekStartsOn == 'monday' ? 1 : 0,
			allDayText: booknetic.__('all-day'),
			listDayFormat: function ( date )
			{
				let week_days = [booknetic.__("Sun"), booknetic.__("Mon"), booknetic.__("Tue"), booknetic.__("Wed"), booknetic.__("Thu"), booknetic.__("Fri"), booknetic.__("Sat")];

				return week_days[ date.date.marker.getUTCDay() ]
			},
			listDayAltFormat: function ( date )
			{
				var  month = date.date.marker.getUTCMonth() + 1;

				if ( month < 10 )
				{
					month = '0' + month;
				}

				return booknetic.reformatDateFromCustomFormat(dateFormat, date.date.marker.getUTCDate(), month, date.date.marker.getUTCFullYear());
			},

			slotLabelFormat : timeFormatObj,

			datesRender: function()
			{
				// if calendar new loads...
				if( typeof FSCalendarRange.start == 'undefined' )
				{
					reloadCalendarFn();
					return;
				}

				reloadCalendarHours();

				var activeRange	=	FSCalendar.state.dateProfile.activeRange,
					startDate	=	new Date( activeRange.start.getUTCFullYear() + '-' + booknetic.zeroPad(parseInt(activeRange.start.getUTCMonth())+1) + '-' + booknetic.zeroPad(activeRange.start.getUTCDate()) ),
					endDate		=	new Date( activeRange.end.getUTCFullYear() + '-' + booknetic.zeroPad(parseInt(activeRange.end.getUTCMonth())+1) + '-' + booknetic.zeroPad(activeRange.end.getUTCDate()) );

				// if old range, then break
				if( ( FSCalendarRange.start.getTime() <= startDate.getTime() && FSCalendarRange.end.getTime() >= startDate.getTime() ) && ( FSCalendarRange.start.getTime() <= endDate.getTime() && FSCalendarRange.end.getTime() >= endDate.getTime() ) )
					return;

				reloadCalendarFn();
			},

			eventRender: function(info)
			{
				var data = info.event.extendedProps;
				var html = '<div class="calendar_cart" style="color: '+data.text_color+';">';
				html += '<div class="calendar_event_line_1">' + data.start_time + ' - ' + data.end_time + '</div>';

				if( data.service_name == 'gc_event')
					html += '<div class="cart_staff_line calendar_event_line_2"><div class="circle_image"><img src="' + data.gc_icon + '"></div> ' + data.event_title + '</div>';
				else
					html += '<div class="calendar_event_line_2">' + data.service_name + '</div>';

				if( data.customers_count == 1 && data.status )
				{
					data.status.icon = data.status.icon.replace('times-circle', 'times');
					data.status.icon = data.status.icon.replace('fa fa-clock', 'far fa-clock');
					html += '<div class="calendar_event_line_3">' + data.customer + ' <span class="appointment-status-default" style="background-color: ' + data.status.color + '"><i class="' + data.status.icon + '"></i></span></div>';
				}
				else if ( data.service_name != 'gc_event' )
				{
					html += '<div class="calendar_event_line_3">' + booknetic.__('group_appointment') + '</div>';
				}

				html += '<div class="cart_staff_line calendar_event_line_4"><div class="circle_image"><img src="' + data.staff_profile_image + '"></div> ' + data.staff_name + '</div>';
				html += '</div>';

				if( data.duration <= 59 * 60 && (info.view.type == 'timeGridWeek' || info.view.type == 'resourceTimeGridDay' ) )
				{
					html = $(html);

					if( data.duration <= 50 * 60 )
					{
						html.tooltip({
							html: true,
							title: '<div class="calendar_tooltip">' + html[0].outerHTML + '</div>',
							container: $(info.el)
						});

						html.find('.calendar_event_line_4').hide();
					}
					if( data.duration <= 35 * 60 )
					{
						html.find('.calendar_event_line_3').hide();
					}
					if( data.duration <= 23 * 60 )
					{
						html.find('.calendar_event_line_2').hide();
					}

					html.addClass('calendar_mini_event');
				}

				$(info.el).find('.fc-time').html('').hide();

				$(info.el).find('.fc-title').css('width', '100%').empty();
				$(html).appendTo( $(info.el).find('.fc-title') );
			},
			eventPositioned: function(info)
			{
				var data = info.event.extendedProps;

				//Waiting-List patch.
				//When the add-on ( waiting-list ) turned off, status obj of the given appointment becomes null, as the
				//hook written inside the add-on is not triggered ( does: inserts the data )
				//todo: refactor the waiting-list so the related data according to the appointment should be stored independently
				if ( data.status == null )
					return;

				if( data.customers_count == 1 )
				{
					var htmlCustomer = '<div>' + data.customer + ' <span class="appointment-status-'+data.status.color+'"><i class="' + data.status.icon + '"></i></span>' + '</div>';
				}
				else
				{
					var htmlCustomer = '<div>' + booknetic.__('group_appointment') + '</div>';
				}

				$(info.el).find('.fc-list-item-title').after('<td>'+htmlCustomer+'</td>');
				$(info.el).find('.fc-list-item-title').after('<td class="fc-list-item-staff"><div><div class="circle_image"><img src="' + data.staff_profile_image + '"></div> ' + data.staff_name + '</div></td>');

				$(info.view.el).find('.fc-widget-header').attr('colspan', $(info.el).children('td').length);
			},
			eventClick: function (info)
			{
				var id = info.event.extendedProps['appointment_id'];

				booknetic.loadModal('appointments.info', {id: id});
			},

			buttonText: {
				today:  booknetic.__('TODAY'),
				month:  booknetic.__('month'),
				week:   booknetic.__('week'),
				day:    booknetic.__('day'),
				list:   booknetic.__('list')
			},

			titleFormat: function( date )
			{
				let start       = date.date.marker;
				let end         = date.end.marker;
				let diff_days   = Math.round((end.getTime() - start.getTime()) / 1000 / 60 / 60 / 24);
				let month_names = [booknetic.__("January"), booknetic.__("February"), booknetic.__("March"), booknetic.__("April"), booknetic.__("May"), booknetic.__("June"), booknetic.__("July"), booknetic.__("August"), booknetic.__("September"), booknetic.__("October"), booknetic.__("November"), booknetic.__("December")];

				if( diff_days >= 28 ) // month view
				{
					return month_names[start.getUTCMonth()] + ' ' + start.getUTCFullYear();
				}
				else if( diff_days == 1 )
				{
					return booknetic.reformatDateFromCustomFormat(dateFormat, booknetic.zeroPad(start.getUTCDate()), booknetic.zeroPad(start.getUTCMonth()+1), start.getUTCFullYear());
				}
				else
				{
					return booknetic.reformatDateFromCustomFormat(dateFormat, booknetic.zeroPad(start.getUTCDate()), booknetic.zeroPad(start.getUTCMonth()+1), start.getUTCFullYear()) + ' - ' + booknetic.reformatDateFromCustomFormat(dateFormat, booknetic.zeroPad(end.getUTCDate()), booknetic.zeroPad(end.getUTCMonth()+1), end.getUTCFullYear());
				}
			},
			columnHeaderText: function ( date )
			{
				let week_days = [booknetic.__("Sun"), booknetic.__("Mon"), booknetic.__("Tue"), booknetic.__("Wed"), booknetic.__("Thu"), booknetic.__("Fri"), booknetic.__("Sat")];

				if( FSCalendar.view.type == 'timeGridWeek' )
				{
					return week_days[ date.getDay() ] + ', ' + booknetic.reformatDateFromCustomFormat(dateFormat, booknetic.zeroPad(date.getDate()), booknetic.zeroPad(date.getMonth()+1), date.getFullYear());
				}

				return week_days[ date.getDay() ]
			}

		});

		FSCalendar.setOption('locale', fcLocale);
		FSCalendar.render();

		if( $('.starting_guide_icon').css('display') !== 'none' )
		{
			$('.create_new_appointment_btn').css({right: '125px'})
		}
	});

})(jQuery);

