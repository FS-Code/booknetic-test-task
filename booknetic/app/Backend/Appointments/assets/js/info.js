(function ($)
{
	"use strict";

	$(document).ready(function()
	{
		$(".fs-modal").on('click', '.delete-btn', function ()
		{
			booknetic.confirm(booknetic.__( 'really_want_to_delete' ), 'danger', 'trash', function()
			{
				var ajaxData = {
					'fs-data-table-action': 'delete',
					'ids': [ $('#add_new_JS_info1').data('appointment-id') ]
				};

				$.post(location.href.replace( /module=\w+/g , 'module=appointments'), ajaxData, function ( result )
				{
					if( $("#fs_data_table_div").length > 0 )
					{
						booknetic.dataTable.reload( $("#fs_data_table_div") );

						booknetic.toast('Deleted!', 'success', 5000);

						booknetic.modalHide($(".fs-modal"));
					}
					else
					{
						location.reload();
					}
				});
			});
		});
		$('.fs-modal').find('#appintment_info_payment_gateway').select2({
			theme: 'bootstrap',
			placeholder: booknetic.__('select'),
			allowClear: true
		});

		$('.fs-modal').on('click' ,'#bkntc_create_payment_link' , function (){
			let paymentGateway = $("#appintment_info_payment_gateway").val();
			let appointmentId = $(this).attr('data-appointment-id');
			let data = new FormData();
			data.append('payment_gateway' , paymentGateway);
			data.append('id' , appointmentId);
			booknetic.ajax( 'appointments.create_payment_link', data, function(result)
			{
				$('.bkntc_payment_link_container').show();
				$('.bkntc_payment_link_container').find(".payment_link").text(result['url'])
			});
		});

		$('.fs-modal').on('click' , '.copy_url_payment_link',function () {
			let val = $('.bkntc_payment_link_container').find(".payment_link").text().trim();
			navigator.clipboard.writeText(val).then(r =>{
				booknetic.toast( booknetic.__('link_copied'), 'success' );
			});
		});


	});

})(jQuery);