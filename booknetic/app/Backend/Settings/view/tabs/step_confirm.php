<?php
defined( 'ABSPATH' ) or die();

use BookneticApp\Providers\Helpers\Helper;
?>
<div class="form-group col-md-12">
    <div class="form-control-checkbox">
        <label for="input_disable_payment_options"><?php echo bkntc__('Hide payment methods section')?>:</label>
        <div class="fs_onoffswitch">
            <input type="checkbox" class="fs_onoffswitch-checkbox bkntc_confirm_details_checkbox" data-slug="disable_payment_options" id="input_disable_payment_options"<?php echo Helper::getOption('disable_payment_options', 'off')=='on'?' checked':''?>>
            <label class="fs_onoffswitch-label" for="input_disable_payment_options"></label>
        </div>
    </div>
</div>

<div class="form-group col-md-12">
    <div class="form-control-checkbox">
        <label for="input_hide_discount_row"><?php echo bkntc__('Do not show the discount row if a discount is not added')?>:</label>
        <div class="fs_onoffswitch">
            <input type="checkbox" class="fs_onoffswitch-checkbox bkntc_confirm_details_checkbox" data-slug="hide_discount_row"  id="input_hide_discount_row"<?php echo Helper::getOption('hide_discount_row', 'off')=='on'?' checked':''?>>
            <label class="fs_onoffswitch-label" for="input_hide_discount_row"></label>
        </div>
    </div>
</div>

<div class="form-group col-md-12">
    <div class="form-control-checkbox">
        <label for="input_hide_price_section"><?php echo bkntc__('Hide price section')?>:</label>
        <div class="fs_onoffswitch">
            <input type="checkbox" class="fs_onoffswitch-checkbox bkntc_confirm_details_checkbox" data-slug="hide_price_section" id="input_hide_price_section"<?php echo Helper::getOption('hide_price_section', 'off')=='on'?' checked':''?>>
            <label class="fs_onoffswitch-label" for="input_hide_price_section"></label>
        </div>
    </div>
</div>