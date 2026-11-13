<?php
// Heading
$_['heading_title']         = 'Frenet';

// Text
$_['text_extension']        = 'Extensions';
$_['text_success']          = 'Success: You have modified Frenet shipping!';
$_['text_edit']             = 'Edit Frenet Shipping';
$_['text_version']          = 'Frenet module %s';
$_['text_migrated']         = 'The key and password of the previous version were removed: Frenet now authenticates with the token only.';
$_['text_token_required']   = 'Frenet cannot quote without a token. Generate it in the Frenet panel and paste it into the Token field.';
$_['text_token_saved']      = 'Token saved. Leave blank to keep it.';
$_['text_panel']            = 'Open the Frenet panel';
$_['text_adjusted']         = '%s adjusted to %d (accepts %d to %d).';
$_['text_default_used']     = '%s must be greater than zero: %s used.';
$_['text_adjusted_min']     = '%s only accepts whole numbers from 0: used %d.';
$_['text_package_over_correios'] = 'The maximum package weight is over %s kg, the Correios limit: heavier packages get no PAC or SEDEX.';
$_['text_total_installed']  = 'The fee of carts without shipping was installed in Extensions > Order Totals as Frenet - virtual products fee.';
$_['text_geo_zone_missing']  = 'The geo zone chosen for Frenet was deleted, so Frenet shipping is offered to no address. Choose another geo zone and save.';

// Entry
$_['entry_token']           = 'Token';
$_['entry_postcode']        = 'Origin postcode';
$_['entry_msg_prazo']       = 'Delivery time message';
$_['entry_weight_class']    = 'Kilogram weight class';
$_['entry_length_class']    = 'Centimetre length class';
$_['entry_minimum_height']  = 'Minimum height (cm)';
$_['entry_minimum_width']   = 'Minimum width (cm)';
$_['entry_minimum_length']  = 'Minimum length (cm)';
$_['entry_default_weight']  = 'Default weight (kg)';
$_['entry_package_split']   = 'Package split';
$_['entry_package_max_weight'] = 'Maximum package weight (kg)';
$_['entry_timeout']         = 'Timeout (seconds)';
$_['entry_attempts']        = 'Attempts';
$_['entry_debug']           = 'Debug';
$_['entry_status']          = 'Status';
$_['entry_sort_order']      = 'Sort Order';
$_['entry_delivery_time']   = 'Show delivery time';
$_['entry_additional_days'] = 'Additional days';
$_['entry_tax_class']       = 'Tax Class';
$_['entry_geo_zone']        = 'Geo Zone';
$_['entry_simulator']       = 'Product page simulator';
$_['entry_simulator_limit'] = 'Simulator limit per IP';
$_['entry_virtual_fee']     = 'Virtual products fee';
$_['entry_virtual_fee_amount'] = 'Fee amount';
$_['entry_virtual_fee_name'] = 'Fee name';

// Help
$_['help_token']            = 'Token generated in the Frenet panel. For security, the saved token is never displayed.';
$_['help_msg_prazo']        = 'Rate name at checkout: %s is the service and %d the delivery days. Leave blank to use the example message.';
$_['help_weight_class']     = 'Store weight class that matches the kilogram. Each product weight is converted to it.';
$_['help_length_class']     = 'Store length class that matches the centimetre. Each product dimension is converted to it.';
$_['help_minimum_height']   = 'Smallest height sent to Frenet: a lower product, or one without height, goes with this value. Default: 2 cm, the Correios minimum.';
$_['help_minimum_width']    = 'Smallest width sent to Frenet: a narrower product, or one without width, goes with this value. Default: 11 cm, the Correios minimum.';
$_['help_minimum_length']   = 'Smallest length sent to Frenet: a shorter product, or one without length, goes with this value. Default: 16 cm, the Correios minimum.';
$_['help_default_weight']   = 'Weight sent to Frenet for a product registered without one. Default: 1 kg.';
$_['help_package_split']    = 'Quotes a cart heavier than the maximum package weight in packages, so Correios is offered again: each Correios service comes with the sum of the packages and the longest delivery time, and only when every package was quoted. The other carriers are quoted with the whole cart. Works up to 10 packages; a product heavier than the package makes the cart be quoted whole.';
$_['help_package_max_weight'] = 'Weight of each package in the package split. Default: 30 kg, the Correios limit; above it, heavier packages get no PAC or SEDEX.';
$_['help_timeout']          = 'How long each quote attempt waits for Frenet to answer, from 2 to 10 seconds.';
$_['help_attempts']         = 'How many times the quote is sent when Frenet does not answer or answers with a server error, from 1 to 3. It also retries 429 (too many requests) and 408; an invalid token and the other 4xx errors are never retried.';
$_['help_debug']            = 'Writes every quote request and response to frenet.log, without the token. Errors are always written.';
$_['help_delivery_time']    = 'Shows the Frenet delivery time plus the additional days in the rate name, with the message below.';
$_['help_additional_days']  = 'Days added to the delivery time of every Frenet service, such as the time to pick the order. Also added when the carrier informs no delivery time.';
$_['help_geo_zone']         = 'Frenet shipping is only offered to addresses in this geo zone. With All Zones, it is offered to any address.';
$_['help_simulator']        = 'Shows on the product page a postcode field that estimates the Frenet rates of the product, with the cart prices and delivery times.';
$_['help_simulator_limit']  = 'How many estimates each IP may ask per minute. Above it, the simulator asks to wait and the block goes to frenet.log. Use 0 for no limit.';
$_['help_virtual_fee']      = 'Charges a fixed fee, once per order, when the cart has a product without shipping (Requires Shipping: No), such as a processing fee. With shipped products, the fee is added to every Frenet rate; with only products without shipping, it shows as a line of the order totals.';
$_['help_virtual_fee_amount'] = 'Amount charged once per order, in the store default currency, with the Tax Class of the Frenet shipping.';
$_['help_virtual_fee_name'] = 'Name shown to the customer in the cart, checkout and order. Leave blank to use the example name.';
$_['placeholder_virtual_fee_amount'] = '4.90';
$_['placeholder_virtual_fee_name'] = 'Order processing fee';
$_['placeholder_msg_prazo'] = '%s - Average %d day(s)';

// Error
$_['error_permission']      = 'Warning: You do not have permission to modify Frenet shipping!';
$_['error_warning']         = 'Warning: the settings were not saved. Fix the highlighted fields.';
$_['error_postcode']        = 'The origin postcode must have 8 digits, such as 01310-100.';
$_['error_simulator_limit_value'] = 'Negative values are not allowed. Use 0 or a positive whole number.';
$_['error_virtual_fee_amount'] = 'The fee amount must be greater than zero, such as 4.90.';
$_['error_token']           = 'Enter the token generated in the Frenet panel to enable the shipping.';
