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

// Entry
$_['entry_token']           = 'Token';
$_['entry_postcode']        = 'Origin postcode';
$_['entry_msg_prazo']       = 'Delivery time message';
$_['entry_weight_class']    = 'Kilogram weight class';
$_['entry_length_class']    = 'Centimetre length class';
$_['entry_timeout']         = 'Timeout (seconds)';
$_['entry_attempts']        = 'Attempts';
$_['entry_debug']           = 'Debug';
$_['entry_status']          = 'Status';
$_['entry_sort_order']      = 'Sort Order';

// Help
$_['help_token']            = 'Token generated in the Frenet panel. For security, the saved token is never displayed.';
$_['help_msg_prazo']        = 'Rate name at checkout: %s is the service and %d the delivery days. Leave blank to show the service only.';
$_['help_weight_class']     = 'Store weight class that matches the kilogram. Each product weight is converted to it.';
$_['help_length_class']     = 'Store length class that matches the centimetre. Each product dimension is converted to it.';
$_['help_timeout']          = 'How long each quote attempt waits for Frenet to answer, from 2 to 10 seconds.';
$_['help_attempts']         = 'How many times the quote is sent when Frenet does not answer or answers with a server error, from 1 to 3. An invalid token and other 4xx errors are never retried.';
$_['help_debug']            = 'Writes every quote request and response to frenet.log, without the token. Errors are always written.';
$_['placeholder_msg_prazo'] = '%s - Average %d day(s)';

// Error
$_['error_permission']      = 'Warning: You do not have permission to modify Frenet shipping!';
