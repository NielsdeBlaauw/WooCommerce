<?php
class Mollie_WC_Helper_Settings
{
    const DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK = '3:00';
    /**
     * @return bool
     */
    public function isTestModeEnabled ()
    {
        return trim(get_option($this->getSettingId('test_mode_enabled'))) === 'yes';
    }

    /**
     * @param bool $test_mode
     * @return null|string
     */
    public function getApiKey ($test_mode = false)
    {
        $setting_id = $test_mode ? 'test_api_key' : 'live_api_key';

        return trim(get_option($this->getSettingId($setting_id)));
    }

    /**
     * Description send to Mollie
     *
     * @return string|null
     */
    public function getPaymentDescription ()
    {
        return trim(get_option($this->getSettingId('payment_description')));
    }

	/**
	 * Order status for cancelled payments
	 *
	 * @return string|null
	 */
	public function getOrderStatusCancelledPayments ()
	{
		return trim(get_option($this->getSettingId('order_status_cancelled_payments')));
	}

    /**
     * @return string
     */
    protected function getPaymentLocaleSetting ()
    {
        $default_value = 'wp_locale';

        return trim(get_option($this->getSettingId('payment_locale'), $default_value));
    }

    /**
     * @return string|null
     */
    public function getPaymentLocale ()
    {
        $setting = $this->getPaymentLocaleSetting();

        if (!empty($setting))
        {
            if ($setting == 'wp_locale')
            {
                // Send current locale to Mollie
                return $this->getCurrentLocale();
            }
            else
            {
                // Send specific locale to Mollie
                return $setting;
            }
        }

        // Do not send locale to Mollie, use browser language
        return null;
    }

    /**
     * Get current locale
     *
     * @return string
     */
    public function getCurrentLocale ()
    {
        return apply_filters('wpml_current_language', get_locale());
    }

    /**
     * Store customer details at Mollie
     *
     * @return string
     */
    public function shouldStoreCustomer ()
    {
        return get_option($this->getSettingId('customer_details'), 'yes') === 'yes';
    }

    /**
     * @return bool
     */
    public function isDebugEnabled ()
    {
        return get_option($this->getSettingId('debug'), 'yes') === 'yes';
    }

    /**
     * @return string
     */
    public function getGlobalSettingsUrl ()
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout#' . Mollie_WC_Plugin::PLUGIN_ID);
    }

    /**
     * @return string
     */
    public function getLogsUrl ()
    {
        return admin_url('admin.php?page=wc-status&tab=logs');
    }

    /**
     * Get plugin status
     *
     * - Check compatibility
     * - Check Mollie API connectivity
     *
     * @return string
     */
    protected function getPluginStatus ()
    {
        $status = Mollie_WC_Plugin::getStatusHelper();

        if (!$status->isCompatible())
        {
            // Just stop here!
            return ''
                . '<div class="notice notice-error">'
                . '<p><strong>' . __('Error', 'mollie-payments-for-woocommerce') . ':</strong> ' . implode('<br/>', $status->getErrors())
                . '</p></div>';
        }

        try
        {
            // Check compatibility
            $status->getMollieApiStatus();

            $api_status       = ''
                . '<p>' . __('Mollie status:', 'mollie-payments-for-woocommerce')
                . ' <span style="color:green; font-weight:bold;">' . __('Connected', 'mollie-payments-for-woocommerce') . '</span>'
                . '</p>';
            $api_status_type = 'updated';
        }
        catch (Mollie_WC_Exception_CouldNotConnectToMollie $e)
        {
            $api_status = ''
                . '<p style="font-weight:bold;"><span style="color:red;">Communicating with Mollie failed:</span> ' . esc_html($e->getMessage()) . '</p>'
                . '<p>Please check the following conditions. You can ask your system administrator to help with this.</p>'

                . '<ul style="color: #2D60B0;">'
                . ' <li>Please check if you\'ve inserted your API key correctly.</li>'
                . ' <li>Make sure outside connections to <strong>' . esc_html(Mollie_WC_Helper_Api::getApiEndpoint()) . '</strong> are not blocked.</li>'
                . ' <li>Make sure SSL v3 is disabled on your server. Mollie does not support SSL v3.</li>'
                . ' <li>Make sure your server is up-to-date and the latest security patches have been installed.</li>'
                . '</ul><br/>'

                . '<p>Please contact <a href="mailto:info@mollie.com">info@mollie.com</a> if this still does not fix your problem.</p>';

            $api_status_type = 'error';
        }
        catch (Mollie_WC_Exception_InvalidApiKey $e)
        {
            $api_status      = '<p style="color:red; font-weight:bold;">' . esc_html($e->getMessage()) . '</p>';
            $api_status_type = 'error';
        }

        return ''
            . '<div id="message" class="' . $api_status_type . ' fade notice">'
            . $api_status
            . '</div>';
    }

    /**
     * @param string $gateway_class_name
     * @return string
     */
    protected function getGatewaySettingsUrl ($gateway_class_name)
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . sanitize_title(strtolower($gateway_class_name)));
    }

    protected function getMollieMethods ()
    {
        $content = '';

	    $data_helper     = Mollie_WC_Plugin::getDataHelper();
	    $settings_helper = Mollie_WC_Plugin::getSettingsHelper();

        try
        {

            // Is Test mode enabled?
            $test_mode       = $settings_helper->isTestModeEnabled();

            if (isset($_GET['refresh-methods']) && check_admin_referer('refresh-methods'))
            {
                /* Reload active Mollie methods */
                $data_helper->getAllPaymentMethods($test_mode, $use_cache = false);
            }

            $icon_available     = ' <span style="color: green; cursor: help;" title="' . __('Gateway enabled', 'mollie-payments-for-woocommerce'). '">' . strtolower(__('Enabled', 'mollie-payments-for-woocommerce')) . '</span>';
            $icon_no_available  = ' <span style="color: red; cursor: help;" title="' . __('Gateway disabled', 'mollie-payments-for-woocommerce'). '">' . strtolower(__('Disabled', 'mollie-payments-for-woocommerce')) . '</span>';

            $content .= '<br /><br />';

            if ($test_mode)
            {
                $content .= '<strong>' . __('Test mode enabled.', 'mollie-payments-for-woocommerce') . '</strong> ';
            }

            $content .= sprintf(
                /* translators: The surrounding %s's Will be replaced by a link to the Mollie profile */
                __('The following payment methods are activated in your %sMollie profile%s:', 'mollie-payments-for-woocommerce'),
                '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                '</a>'
            );

            $refresh_methods_url = wp_nonce_url(
                add_query_arg(array('refresh-methods' => 1)),
                'refresh-methods'
            ) . '#' . Mollie_WC_Plugin::PLUGIN_ID;

            $content .= ' (<a href="' . esc_attr($refresh_methods_url) . '">' . strtolower(__('Refresh', 'mollie-payments-for-woocommerce')) . '</a>)';

            $content .= '<ul style="width: 1000px">';

            foreach (Mollie_WC_Plugin::$GATEWAYS as $gateway_classname)
            {
                $gateway = new $gateway_classname;

                if ($gateway instanceof Mollie_WC_Gateway_Abstract)
                {
                    $content .= '<li style="float: left; width: 33%;">';

                    $content .= '<img src="' . esc_attr($gateway->getIconUrl()) . '" alt="' . esc_attr($gateway->getDefaultTitle()) . '" title="' . esc_attr($gateway->getDefaultTitle()) . '" style="width: 25px; vertical-align: bottom;" />';
                    $content .= ' ' . esc_html($gateway->getDefaultTitle());

                    if ($gateway->is_available())
                    {
                        $content .= $icon_available;
                    }
                    else
                    {
                        $content .= $icon_no_available;
                    }

	                $content .= ' <a href="' . $this->getGatewaySettingsUrl( $gateway_classname ) . '">' . strtolower( __( 'Edit', 'mollie-payments-for-woocommerce' ) ) . '</a>';

	                $content .= '</li>';
                }
            }

            $content .= '</ul>';
            $content .= '<div class="clear"></div>';

            // Make sure users also enable iDEAL when they enable SEPA Direct Debit
	        // iDEAL is needed for the first payment of subscriptions with SEPA Direct Debit
	        $content = $this->checkDirectDebitStatus( $content );

        }
        catch (Mollie_WC_Exception_InvalidApiKey $e)
        {
            // Ignore
        }

        return $content;
    }

    /**
     * @param array $settings
     * @return array
     */
    public function addGlobalSettingsFields (array $settings)
    {
        wp_register_script('mollie_wc_admin_settings', Mollie_WC_Plugin::getPluginUrl('/assets/js/settings.js'), array('jquery'), Mollie_WC_Plugin::PLUGIN_VERSION);
        wp_enqueue_script('mollie_wc_admin_settings');

        $content = ''
            . $this->getPluginStatus()
            . $this->getMollieMethods();

        /* translators: Default payment description. {order_number} and {order_date} are available tags. */
        $default_payment_description = __('Order {order_number}', 'mollie-payments-for-woocommerce');
        $payment_description_tags    = '<code>{order_number}</code>, <code>{order_date}</code>';

        $debug_desc = __('Log plugin events.', 'mollie-payments-for-woocommerce');

        // For WooCommerce 2.2.0+ display view logs link
        if (version_compare(Mollie_WC_Plugin::getStatusHelper()->getWooCommerceVersion(), '2.2.0', ">="))
        {
            $debug_desc .= ' <a href="' . $this->getLogsUrl() . '">' . __('View logs', 'mollie-payments-for-woocommerce') . '</a>';
        }
        // Display location of log files
        else
        {
            /* translators: Placeholder 1: Location of the log files */
            $debug_desc .= ' ' . sprintf(__('Log files are saved to <code>%s</code>', 'mollie-payments-for-woocommerce'), defined('WC_LOG_DIR') ? WC_LOG_DIR : WC()->plugin_path() . '/logs/');
        }

        // Global Mollie settings
        $mollie_settings = array(
            array(
                'id'    => $this->getSettingId('title'),
                'title' => __('Mollie settings', 'mollie-payments-for-woocommerce'),
                'type'  => 'title',
                'desc'  => '<p id="' . Mollie_WC_Plugin::PLUGIN_ID . '">' . $content . '</p>'
                         . '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'mollie-payments-for-woocommerce') . '</p>',
            ),
            array(
                'id'                => $this->getSettingId('live_api_key'),
                'title'             => __('Live API key', 'mollie-payments-for-woocommerce'),
                'default'           => '',
                'type'              => 'text',
                'desc'              => sprintf(
                    /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%s</strong> API key in your %sMollie profile%s', 'mollie-payments-for-woocommerce'),
                    'live',
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                ),
                'css'               => 'width: 350px',
                'placeholder'       => $live_placeholder = __('Live API key should start with live_', 'mollie-payments-for-woocommerce'),
                'custom_attributes' => array(
                    'placeholder' => $live_placeholder,
                    'pattern'     => '^live_\w+$',
                ),
            ),
            array(
                'id'                => $this->getSettingId('test_mode_enabled'),
                'title'             => __('Enable test mode', 'mollie-payments-for-woocommerce'),
                'default'           => 'no',
                'type'              => 'checkbox',
                'desc_tip'          => __('Enable test mode if you want to test the plugin without using real payments.', 'mollie-payments-for-woocommerce'),
            ),
            array(
                'id'                => $this->getSettingId('test_api_key'),
                'title'             => __('Test API key', 'mollie-payments-for-woocommerce'),
                'default'           => '',
                'type'              => 'text',
                'desc'              => sprintf(
                    /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __('The API key is used to connect to Mollie. You can find your <strong>%s</strong> API key in your %sMollie profile%s', 'mollie-payments-for-woocommerce'),
                    'test',
                    '<a href="https://www.mollie.com/dashboard/settings/profiles" target="_blank">',
                    '</a>'
                ),
                'css'               => 'width: 350px',
                'placeholder'       => $test_placeholder = __('Test API key should start with test_', 'mollie-payments-for-woocommerce'),
                'custom_attributes' => array(
                    'placeholder' => $test_placeholder,
                    'pattern'     => '^test_\w+$',
                ),
            ),
            array(
                'id'      => $this->getSettingId('payment_description'),
                'title'   => __('Description', 'mollie-payments-for-woocommerce'),
                'type'    => 'text',
                /* translators: Placeholder 1: Default payment description, placeholder 2: list of available tags */
                'desc'    => sprintf(__('Payment description send to Mollie. Default <code>%s</code><br/>You can use the following tags: %s', 'mollie-payments-for-woocommerce'), $default_payment_description, $payment_description_tags),
                'default' => $default_payment_description,
                'css'     => 'width: 350px',
            ),
	        array(
		        'id'      => $this->getSettingId('order_status_cancelled_payments'),
		        'title'   => __('Order status after cancelled payment', 'mollie-payments-for-woocommerce'),
		        'type'    => 'select',
		        'options' => array(
			        'pending'          => __('Pending', 'woocommerce'),
			        'cancelled'     => __('Cancelled', 'woocommerce'),
		        ),
		        'desc'    => __('Status for orders when a payment is cancelled. Default: pending. Orders with status Pending can be paid with another payment method, customers can try again. Cancelled orders are final. Set this to Cancelled if you only have one payment method or don\'t want customers to re-try paying with a different payment method.', 'mollie-payments-for-woocommerce'),
		        'default' => 'pending',
	        ),
	        array(
                'id'      => $this->getSettingId('payment_locale'),
                'title'   => __('Payment screen language', 'mollie-payments-for-woocommerce'),
                'type'    => 'select',
                'options' => array(
                    ''          => __('Detect using browser language', 'mollie-payments-for-woocommerce')  . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')',
                    /* translators: Placeholder 1: Current WordPress locale */
                    'wp_locale' => sprintf(__('Send WordPress language (%s)', 'mollie-payments-for-woocommerce'), $this->getCurrentLocale()),
                    'nl_NL'     => __('Dutch', 'mollie-payments-for-woocommerce'),
                    'nl_BE'     => __('Flemish (Belgium)', 'mollie-payments-for-woocommerce'),
                    'en'        => __('English', 'mollie-payments-for-woocommerce'),
                    'de'        => __('German', 'mollie-payments-for-woocommerce'),
                    'es'        => __('Spanish', 'mollie-payments-for-woocommerce'),
                    'fr_FR'     => __('French', 'mollie-payments-for-woocommerce'),
                    'fr_BE'     => __('French (Belgium)', 'mollie-payments-for-woocommerce'),
                ),
                'desc'    => sprintf(
                	__('The option \'Detect using browser language\' is usually more accurate. Only use \'Send WordPress language\' if you are sure all languages/locales on your website are supported by Mollie %s(see \'locale\' under \'Parameters\')%s. Currently supported locales: <code>en_US</code>, <code>de_AT</code>, <code>de_CH</code>, <code>de_DE</code>, <code>es_ES</code>, <code>fr_BE</code>, <code>fr_FR</code>, <code>nl_BE</code>, <code>nl_NL</code>.', 'mollie-payments-for-woocommerce'),
	                '<a href="https://www.mollie.com/nl/docs/reference/payments/create" target="_blank">',
	                '</a>'
                ),
                'default' => '',
            ),
            array(
                'id'                => $this->getSettingId('customer_details'),
                'title'             => __('Store customer details at Mollie', 'mollie-payments-for-woocommerce'),
                /* translators: Placeholder 1: enabled or disabled */
                'desc'              => sprintf(__('Should Mollie store customers name and email address for Single Click Payments? Default <code>%s</code>. Required if WooCommerce Subscriptions is being used!', 'mollie-payments-for-woocommerce'), strtolower(__('Enabled', 'mollie-payments-for-woocommerce'))),
                'type'              => 'checkbox',
                'default'           => 'yes',

            ),
            array(
                'id'      => $this->getSettingId('debug'),
                'title'   => __('Debug Log', 'mollie-payments-for-woocommerce'),
                'type'    => 'checkbox',
                'desc'    => $debug_desc,
                'default' => 'yes',
            ),
            array(
                'id'   => $this->getSettingId('sectionend'),
                'type' => 'sectionend',
            ),
        );

        return $this->mergeSettings($settings, $mollie_settings);
    }

    public function getPaymentConfirmationCheckTime()
    {
        $time = strtotime(self::DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK);
        $date = new DateTime();

        if ($date->getTimestamp() > $time){
            $date->setTimestamp($time);
            $date->add(new DateInterval('P1D'));
        } else {
            $date->setTimestamp($time);
        }


        return $date->getTimestamp();
    }

    /**
     * @param string $setting
     * @return string
     */
    protected function getSettingId ($setting)
    {
        global $wp_version;

        $setting_id        = Mollie_WC_Plugin::PLUGIN_ID . '_' . trim($setting);
        $setting_id_length = strlen($setting_id);

        $max_option_name_length = 191;

        /**
         * Prior to WooPress version 4.4.0, the maximum length for wp_options.option_name is 64 characters.
         * @see https://core.trac.wordpress.org/changeset/34030
         */
        if ($wp_version < '4.4.0') {
            $max_option_name_length = 64;
        }

        if ($setting_id_length > $max_option_name_length)
        {
            trigger_error("Setting id $setting_id ($setting_id_length) to long for database column wp_options.option_name which is varchar($max_option_name_length).", E_USER_WARNING);
        }

        return $setting_id;
    }

    /**
     * @param array $settings
     * @param array $mollie_settings
     * @return array
     */
    protected function mergeSettings(array $settings, array $mollie_settings)
    {
        $new_settings           = array();
        $mollie_settings_merged = false;

        // Find payment gateway options index
        foreach ($settings as $index => $setting) {
            if (isset($setting['id']) && $setting['id'] == 'payment_gateways_options'
                && (!isset($setting['type']) || $setting['type'] != 'sectionend')
            ) {
                $new_settings           = array_merge($new_settings, $mollie_settings);
                $mollie_settings_merged = true;
            }

            $new_settings[] = $setting;
        }

        // Mollie settings not merged yet, payment_gateways_options not found
        if (!$mollie_settings_merged)
        {
            // Append Mollie settings
            $new_settings = array_merge($new_settings, $mollie_settings);
        }

        return $new_settings;
    }

	/**
	 * @param $content
	 *
	 * @return string
	 */
	protected function checkDirectDebitStatus( $content ) {

		$ideal_gateway = new Mollie_WC_Gateway_iDEAL();
		$sepa_gateway  = new Mollie_WC_Gateway_DirectDebit();

		if ( ( class_exists( 'WC_Subscription' ) ) && ( $ideal_gateway->is_available() ) && ( ! $sepa_gateway->is_available() ) ) {

			$content .= '<div class="notice notice-warning is-dismissible"><p>';
			$content .= __( 'You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL.', 'mollie-payments-for-woocommerce' );
			$content .= '</p></div> ';

			$content .= '<strong><p>';
			$content .= __( 'You have WooCommerce Subscriptions activated, but not SEPA Direct Debit. Enable SEPA Direct Debit if you want to allow customers to pay subscriptions with iDEAL.', 'mollie-payments-for-woocommerce' );
			$content .= '</p></strong> ';

			return $content;
		}

		return $content;
	}
}
