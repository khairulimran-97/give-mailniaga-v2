<?php
/**
 * Base newsletter class
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, GiveWP
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



/**
 * Give_mailniaga
 *
 * @since 1.2.2
 */
class Give_Mailniaga_Sendy {

    /**
     * The ID for this newsletter Add-on, such as 'mailniaga'.
     */
    public $id;

    /**
     * The label for the Add-on, probably just shown as the title of the metabox.
     */
    public $label;

    /**
     * Newsletter lists retrieved from the API
     */
    public $lists;

    /**
     * Text shown on the checkout, if none is set in the settings
     */
    public $checkout_label;


    /**
     * Give_Newsletter constructor.
     *
     * @param string $_id
     * @param string $_label
     */
    public function __construct( $_id = 'mailniaga-sendy', $_label = 'Mailniaga v1' ) {

        $this->id    = $_id;
        $this->label = $_label;

        add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );

        add_action( 'save_post', array( $this, 'save_metabox' ) );

        add_filter( 'give_get_sections_addons', array( $this, 'register_sections' ) );
        add_filter( 'give_get_settings_addons', array( $this, 'register_settings' ) );

        add_action( 'give_donation_form_before_submit', array( $this, 'donation_form_field' ), 100, 1 );

        add_action( 'cmb2_save_options-page_fields', array( $this, 'save_settings' ), 10, 4 );

        add_action( 'give_insert_payment', array( $this, 'completed_donation_signup' ), 10, 2 );

        add_action( 'give_admin_field_mailniaga_sendy_list_select', array( $this, 'default_list_field' ), 10, 2 );

        add_action( 'wp_ajax_give_reset_mailniaga_sendy_lists', array( $this, 'give_reset_mailniaga_sendy_lists' ) );

    }

    /**
     * Output the signup checkbox on the checkout screen, if enabled.
     *
     * @param int $form_id
     */
    public function donation_form_field( $form_id ) {

        $enable_mailniaga_form  = give_get_meta( $form_id, '_give_mailniaga_sendy_enable', true );
        $disable_mailniaga_form = give_get_meta( $form_id, '_give_mailniaga_sendy_disable', true );

        // Check disable vars to see if this form should have the opt-in field.
        if (
            ! $this->show_checkout_signup() && 'true' !== $enable_mailniaga_form
            || 'true' === $disable_mailniaga_form
        ) {
            return;
        }

        $global_field_label    = give_get_option( 'give_mailniaga_sendy_label' );
        $custom_checkout_label = give_get_meta( $form_id, '_give_mailniaga_sendy_custom_label', true );

        // What's the label gonna be?
        if ( ! empty( $custom_checkout_label ) ) {
            $this->checkout_label = trim( $custom_checkout_label );
        } elseif ( ! empty( $global_field_label ) ) {
            $this->checkout_label = trim( $global_field_label );
        } else {
            $this->checkout_label = esc_html__( 'Subscribe to our newsletter MailNiaga', 'give-mailniaga-sendy' );
        }

        // Should the opt-on be checked or unchecked by default?
        $form_checked_by_default   = give_get_meta( $form_id, '_give_mailniaga_sendy_checked_default', true );
        $global_checked_by_default = give_get_option( 'give_mailniaga_sendy_checked_default' );
        $checked_option            = 'on';

        if ( ! empty( $form_checked_by_default ) ) {
            // Nothing to do here, option already set above.
            $checked_option = $form_checked_by_default;
        } elseif ( ! empty( $global_checked_by_default ) ) {
            $checked_option = $global_checked_by_default;
        }

        ob_start(); ?>
        <fieldset id="give_<?php echo $this->id . '_' . $form_id; ?>" class="give-mailniaga-sendy-fieldset">
            <p>
                <label for="give_<?php echo $this->id . '_' . $form_id; ?>_signup">
                    <input name="give_<?php echo $this->id; ?>_signup"
                           id="give_<?php echo $this->id . '_' . $form_id; ?>_signup"
                           type="checkbox" <?php echo( $checked_option !== 'no' ? 'checked="checked"' : '' ); ?> />
                    <span><?php echo $this->checkout_label; ?></span>
                </label>
            </p>
        </fieldset>
        <?php
        echo ob_get_clean();
    }

    /**
     * Retrieves the lists from Mailniaga
     *
     * @return array|bool
     */
	public function get_lists() {

		$api_key = give_get_option('give_mailniaga_sendy_api');

		// Sanity check
		if (empty($api_key)) {
			return false;
		}

		// API endpoint
		$api_endpoint = 'https://newsletter.aplikasiniaga.com/api/lists/get-lists.php';

		// API data
		$api_data = array(
			'api_key'  => $api_key,
			'brand_id' => give_get_option( 'give_mailniaga_sendy_brand' ),
		);

		// Fetch lists from the API
		$response = wp_remote_post($api_endpoint, array(
			'body' => $api_data,
		));

		// Check for errors
		if (is_wp_error($response)) {
			if (!wp_doing_ajax()) {
				echo '<div class="error updated"><p>' . esc_html__('Error fetching lists from the API.', 'your-text-domain') . '</p></div>';
			}
			return false;
		}

		// Parse JSON response
		$list_data = json_decode(wp_remote_retrieve_body($response), true);

		// Check if the response is valid and contains the expected data
		if (empty($list_data) || !is_array($list_data)) {
			if (!wp_doing_ajax()) {
				echo '<div class="error updated"><p>' . esc_html__('Invalid response from the API.', 'your-text-domain') . '</p></div>';
			}
			return false;
		}

		// Create array for select field
		$lists = array();

		// Extract id and name from each list
		foreach ($list_data as $list) {
			if (isset($list['id']) && isset($list['name'])) {
				$lists[$list['id']] = $list['name'];
			}
		}

		// Return the list data
		return $lists;
	}



    /**
     * Register sections.
     *
     * @param array $sections List of sections.
     *
     * @return mixed
     * @since  1.2.3
     * @access public
     *
     */
    public function register_sections( $sections ) {
        $sections['mailniaga-sendy-settings'] = __( 'Mailniaga v1 Settings', 'give-mailniaga-sendy' );

        return $sections;
    }

    /**
     * Registers the plugin settings
     *
     * @param $settings
     *
     * @return array
     */
    public function register_settings( $settings ) {

        switch ( give_get_current_setting_section() ) {

            case 'mailniaga-sendy-settings':
                $settings = array(
                    array(
                        'id'   => 'give_title_mailniaga_sendy',
                        'type' => 'title',
                    ),
                    array(
                        'id'   => 'give_mailniaga_sendy_settings',
                        'name' => __( 'Mailniaga Settings', 'give-mailniaga-sendy' ),
                        'desc' => '<hr>',
                        'type' => 'give_title',
                    ),
                    array(
                        'id'   => 'give_mailniaga_sendy_api',
                        'name' => __( 'API Key', 'give-mailniaga-sendy' ),
                        'desc' => __( 'Enter your Mailniaga API Key. You will need to register with the Mailniaga Developer Network to get an API Key.', 'give-mailniaga-sendy' ),
                        'type' => 'text',
                    ),
	                array(
		                'id'   => 'give_mailniaga_sendy_brand',
		                'name' => __( 'Brand ID', 'give-mailniaga-sendy' ),
		                'desc' => __( 'Enter your Mailniaga Brand Id. Brand ID can be found on your url app?i=*', 'give-mailniaga' ),
		                'type' => 'number',
	                ),
                    array(
                        'id'   => 'give_mailniaga_sendy_show_checkout_signup',
                        'name' => __( 'Enable Globally?', 'give-mailniaga-sendy' ),
                        'desc' => __( 'Allow customers to sign-up for the list selected below on all forms? Note: the list(s) can be customized per form.', 'give-mailniaga-sendy' ),
                        'type' => 'checkbox',
                    ),
                    array(
                        'id'      => 'give_mailniaga_sendy_checked_default',
                        'name'    => __( 'Opt-in Default', 'give-mailniaga-sendy' ),
                        'desc'    => __( 'Would you like the newsletter opt-in checkbox checked by default? This option can be customized per form.', 'give-mailniaga-sendy' ),
                        'options' => array(
                            'yes' => __( 'Checked', 'give-mailniaga-sendy' ),
                            'no'  => __( 'Unchecked', 'give-mailniaga-sendy' ),
                        ),
                        'default' => 'yes',
                        'type'    => 'radio_inline',
                    ),
                    array(
                        'id'   => 'give_mailniaga_sendy_list',
                        'name' => __( 'Default List', 'give-mailniaga-sendy' ),
                        'desc' => __( 'Enter your List ID. It will be in the form of a number.  Note: the list(s) can be customized per form.', 'give-mailniaga-sendy' ),
                        'type' => 'mailniaga_sendy_list_select',
                    ),
                    array(
                        'id'         => 'give_mailniaga_sendy_label',
                        'name'       => __( 'Global Label', 'give-mailniaga-sendy' ),
                        'desc'       => __( 'This is the text shown by default next to the Mailniaga sign up checkbox. Yes, this can also be customized per form.', 'give-mailniaga-sendy' ),
                        'type'       => 'text',
                        'attributes' => array(
                            'placeholder' => __( 'Subscribe to our newsletter MailNiaga', 'give-mailniaga-sendy' ),
                        ),
                    ),
                    array(
                        'id'   => 'give_title_mailniaga',
                        'type' => 'sectionend',
                    ),
                );
                break;
        }

        return $settings;

    }


    /**
     * Flush the CC list transient on save
     *
     * Hooks into give options save action and deleted transient
     *
     * @return mixed
     */
    public function save_settings() {

        $api_option = give_get_option( 'give_mailniaga_sendy_api' );

        if ( isset( $api_option ) && ! empty( $api_option ) ) {
            delete_transient( 'give_mailniaga_sendy_list_data' );
        }

    }

    /**
     * Determines if the checkout signup option should be displayed.
     */
    public function show_checkout_signup() {

        $show_checkout_signup = give_get_option( 'give_mailniaga_sendy_show_checkout_signup' );

        return ! empty( $show_checkout_signup );
    }

    /**
     * Subscribe Email
     *
     * Subscribe an email to a list
     *
     * @param array $user_info
     * @param bool  $list_id
     * @param int   $payment_id
     *
     * @return bool
     */
	public function subscribe_email( $user_info = array(), $list_uids = false, $payment_id ) {

		$api_token = give_get_option( 'give_mailniaga_sendy_api' );

		// Sanity check: Check to ensure our API token is present before anything
		if ( ! isset( $api_token ) || strlen( trim( $api_token ) ) === 0 ) {
			return false;
		}

		if ( isset( $user_info['email'] ) && strlen( $user_info['email'] ) > 1 ) {

			$api_endpoint = 'https://manage.mailniaga.com/api/v1/subscribers';

			$lists = is_array( $list_uids ) ? $list_uids : array( $list_uids );

			foreach ( $lists as $list_uid ) {
				$post_data = array(
					'api_token'  => $api_token,
					'list_uid'   => $list_uid,
					'EMAIL'      => $user_info['email'],
					'FIRST_NAME' => isset( $user_info['first_name'] ) ? $user_info['first_name'] : '',
					'LAST_NAME'  => isset( $user_info['last_name'] ) ? $user_info['last_name'] : '',
					'tag'        => 'givewp, donation',
				);

				$ch = curl_init();

				curl_setopt( $ch, CURLOPT_URL, $api_endpoint );
				curl_setopt( $ch, CURLOPT_POST, 1 );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Accept: application/json' ) );

				$response = curl_exec( $ch );

				curl_close( $ch );

				give_insert_payment_note( $payment_id, __( 'Mailniaga API Response: ', 'give-mailniaga-sendy' ) . $response );

				if ( curl_errno( $ch ) ) {
					give_record_log(
						esc_html__( 'Mailniaga API Error', 'give-mailniaga-sendy' ),
						curl_error( $ch ),
						0,
						'gateway_error'
					);


					give_insert_payment_note( $payment_id, __( 'Mailniaga API Error: ', 'give-mailniaga-sendy' ) . curl_error( $ch ) );

					return false;
				}

				$decoded_response = json_decode( $response );

				if ( ! isset( $decoded_response->success ) || ! $decoded_response->success ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}




	/**
     * Complete Donation Sign up
     *
     * Check if a customer needs to be subscribed on completed donation on a specific form
     *
     * @param $payment_id
     * @param $payment_data array
     */
    public function completed_donation_signup( $payment_id, $payment_data ) {

        // check to see if the user has elected to subscribe.
        if ( ! isset( $_POST['give_mailniaga_sendy_signup'] ) || 'on' !== $_POST['give_mailniaga_sendy_signup'] ) {
            return;
        }

        $form_lists = give_get_meta( $payment_data['give_form_id'], '_give_' . $this->id, true );

        // Check if $form_lists is set
        if ( empty( $form_lists ) ) {
            // Not set so use global list.
            $form_lists = array( 0 => give_get_option( 'give_mailniaga_sendy_list' ) );
        }

        // Add meta to the donation post that this donation opted-in to CC.
        add_post_meta( $payment_id, '_give_mailniaga_sendy_donation_optin_status', $form_lists );

        // Subscribe if array.
        if ( is_array( $form_lists ) ) {
            $lists = array_unique( $form_lists );
            foreach ( $lists as $list ) {
                // Subscribe the donor to the email lists.
                $this->subscribe_email( $payment_data['user_info'], $list, $payment_id );
            }
        } else {
            // Subscribe to single.
            $this->subscribe_email( $payment_data['user_info'], $form_lists, $payment_id );
        }

    }


    /**
     * Display the metabox, which is a list of newsletter lists
     */
    public function render_metabox() {

        global $post;

        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'give_mailniaga_sendy_meta_box', 'give_mailniaga_sendy_meta_box_nonce' );

        // Using a custom label?
        $custom_label = give_get_meta( $post->ID, '_give_mailniaga_sendy_custom_label', true );

        // Global label
        $global_label = give_get_option( 'give_mailniaga_sendy_label', esc_html__( 'Signup for the MailNiaga', 'give-mailniaga-sendy' ) );

        // Globally enabled option
        $globally_enabled = give_get_option( 'give_mailniaga_sendy_show_checkout_signup' );
        $enable_option    = give_get_meta( $post->ID, '_give_mailniaga_sendy_enable', true );
        $disable_option   = give_get_meta( $post->ID, '_give_mailniaga_sendy_disable', true );
        $checked_option   = give_get_meta( $post->ID, '_give_mailniaga_sendy_checked_default', true );

        // Output option to DISABLE CC for this form
        if ( give_is_setting_enabled( $globally_enabled ) ) {
            ?>
            <p style="margin: 1em 0 0;"><label>
                    <input type="checkbox" name="_give_mailniaga_sendy_disable" class="give-mailniaga-sendy-disable"
                           value="true" <?php echo checked( 'true', $disable_option, false ); ?>>
                    <?php echo '&nbsp;' . esc_html__( 'Disable Mailniaga Opt-in', 'give-mailniaga-sendy' ); ?>
                </label></p>

            <?php
        } else {
            // Output option to ENABLE CC for this form
            ?>
            <p style="margin: 1em 0 0;"><label>
                    <input type="checkbox" name="_give_mailniaga_sendy_enable" class="give-mailniaga-sendy-enable"
                           value="true" <?php echo checked( 'true', $enable_option, false ); ?>>
                    <?php echo '&nbsp;' . esc_html__( 'Enable Mailniaga Opt-in', 'give-mailniaga-sendy' ); ?>
                </label></p>
            <?php
        }

        // Display the form, using the current value.
        ?>
        <div class="give-mailniaga-sendy-field-wrap" <?php echo( $globally_enabled == false && empty( $enable_option ) ? "style='display:none;'" : '' ); ?>>
            <p>
                <label for="_give_mailniaga_sendy_custom_label"
                       style="font-weight:bold;"><?php echo esc_html__( 'Custom Label', 'give-mailniaga-sendy' ); ?></label>
                <span class="give-field-description"
                      style="margin: 0 0 10px;"><?php echo esc_html__( 'Customize the label for the Mailniaga opt-in checkbox', 'give-mailniaga-sendy' ); ?></span>
                <input type="text" id="_give_mailniaga_sendy_custom_label" name="_give_mailniaga_sendy_custom_label"
                       value="<?php echo esc_attr( $custom_label ); ?>"
                       placeholder="<?php echo esc_attr( $global_label ); ?>" size="25"/>
            </p>

            <div>
                <label for="_give_mailniaga_sendy_checked_default"
                       style="font-weight:bold;"><?php esc_html_e( 'Opt-in Default', 'give-mailniaga-sendy' ); ?></label>
                <span class="give-field-description"
                      style="margin: 0 0 10px;"><?php esc_html_e( 'Customize the newsletter opt-in option for this form.', 'give-mailniaga-sendy' ); ?></span>

                <ul class="give-radio-list give-list">
                    <li>
                        <input type="radio" class="give-option" name="_give_mailniaga_sendy_checked_default"
                               id="give_mailniaga_sendy_checked_default1"
                               value="" <?php echo checked( '', $checked_option, false ); ?>>
                        <label
                            for="give_mailniaga_sendy_checked_default1"><?php esc_html_e( 'Global Option', 'give-mailniaga-sendy' ); ?></label>
                    </li>

                    <li>
                        <input type="radio" class="give-option" name="_give_mailniaga_sendy_checked_default"
                               id="give_mailniaga_sendy_checked_default2"
                               value="yes" <?php echo checked( 'yes', $checked_option, false ); ?>>
                        <label
                            for="give_mailniaga_sendy_checked_default2"><?php esc_html_e( 'Checked', 'give-mailniaga-sendy' ); ?></label>
                    </li>
                    <li>
                        <input type="radio" class="give-option" name="_give_mailniaga_sendy_checked_default"
                               id="give_mailniaga_sendy_checked_default3"
                               value="no" <?php echo checked( 'no', $checked_option, false ); ?>>
                        <label
                            for="give_mailniaga_sendy_checked_default3"><?php esc_html_e( 'Unchecked', 'give-mailniaga-sendy' ); ?></label>
                    </li>
                </ul>

            </div>

            <div>
                <label for="give_mailniaga_sendy_lists"
                       style="font-weight:bold; float:left;"><?php esc_html_e( 'Email Lists', 'give-mailniaga-sendy' ); ?>
                </label>

                <button class="give-reset-mailniaga-sendy-button button button-small"
                        style="float:left; margin: -2px 0 0 15px;"
                        data-action="give_reset_mailniaga_lists"
                        data-field_type="checkbox"><?php esc_html_e( 'Refresh Lists', 'give-mailniaga-sendy' ); ?></button>

                <span class="give-spinner spinner" style="float:left;margin: 0 0 0 10px;"></span>

                <span class="give-field-description"
                      style="margin: 10px 0; clear: both;"><?php esc_html_e( 'Customize the list(s) you wish donors to subscribe to if they opt-in.', 'give-mailniaga-sendy' ); ?>
			    </span>

                <?php
                // CC List.
                $checked = (array) give_get_meta( $post->ID, '_give_' . esc_attr( $this->id ), true );

                // No post meta yet? Default to global.
                if ( isset( $checked[0] ) && empty( $checked[0] ) ) {
                    $checked = array( 0 => give_get_option( 'give_mailniaga_sendy_list' ) );
                }
                ?>

                <div class="give-mailniaga-sendy-list-wrap">
                    <?php
                    if ( $lists = $this->get_lists() ) :
                        foreach ( $lists as $list_id => $list_name ) {
                            ?>
                            <label class="list">
                                <input type="checkbox" name="_give_<?php echo esc_attr( $this->id ); ?>[]"
                                       value="<?php echo esc_attr( $list_id ); ?>" <?php echo checked( true, in_array( $list_id, $checked ), false ); ?>>
                                <span><?php echo $list_name; ?></span>
                            </label>

                            <?php
                        }
                    endif;
                    ?>
                </div>

            </div>
        </div>

        <?php
    }


    /**
     * Save the metabox data.
     *
     * @param int $post_id The ID of the post being saved.
     *
     * @return int|bool
     */
    public function save_metabox( $post_id ) {

        /**
         * We need to verify this came from our screen and with proper authorization,
         * because the save_post action can be triggered at other times.
         */
        // Check if our nonce is set.
        if ( ! isset( $_POST['give_mailniaga_sendy_meta_box_nonce'] ) ) {
            return false;
        }

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $_POST['give_mailniaga_sendy_meta_box_nonce'], 'give_mailniaga_sendy_meta_box' ) ) {
            return false;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return false;
        }

        // Check the user's permissions.
        if ( 'give_forms' === $_POST['post_type'] ) {

            if ( ! current_user_can( 'edit_give_forms', $post_id ) ) {
                return $post_id;
            }
        } else {

            if ( ! current_user_can( 'edit_give_forms', $post_id ) ) {
                return $post_id;
            }
        }

        // OK, its safe for us to save the data now.
        // Sanitize the user input.
        $give_mailniaga_sendy_custom_label = isset( $_POST['_give_mailniaga_sendy_custom_label'] ) ? sanitize_text_field( $_POST['_give_mailniaga_sendy_custom_label'] ) : '';
        $give_mailniaga_sendy_custom_lists = isset( $_POST['_give_mailniaga_sendy'] ) ? $_POST['_give_mailniaga_sendy'] : give_get_option( 'give_mailniaga_sendy_list' );
        $give_mailniaga_sendy_enable       = isset( $_POST['_give_mailniaga_sendy_enable'] ) ? esc_html( $_POST['_give_mailniaga_sendy_enable'] ) : '';
        $give_mailniaga_sendy_disable      = isset( $_POST['_give_mailniaga_sendy_disable'] ) ? esc_html( $_POST['_give_mailniaga_sendy_disable'] ) : '';
        $give_mailniaga_sendy_checked      = isset( $_POST['_give_mailniaga_sendy_checked_default'] ) ? esc_html( $_POST['_give_mailniaga_sendy_checked_default'] ) : '';

        // Update the meta fields.
        update_post_meta( $post_id, '_give_mailniaga_sendy_custom_label', $give_mailniaga_sendy_custom_label );
        update_post_meta( $post_id, '_give_mailniaga_sendy', $give_mailniaga_sendy_custom_lists );
        update_post_meta( $post_id, '_give_mailniaga_sendy_enable', $give_mailniaga_sendy_enable );
        update_post_meta( $post_id, '_give_mailniaga_sendy_disable', $give_mailniaga_sendy_disable );
        update_post_meta( $post_id, '_give_mailniaga_sendy_checked_default', $give_mailniaga_sendy_checked );

    }

    /**
     * Register the metabox on the 'give_forms' post type
     */
    public function add_metabox() {

        if ( current_user_can( 'edit_give_forms', get_the_ID() ) ) {
            add_meta_box( 'give_' . $this->id, $this->label, array( $this, 'render_metabox' ), 'give_forms', 'side' );
        }

    }


    /**
     * Give add mailniaga list select with refresh button.
     *
     * @param $field
     * @param $value
     */
    public function default_list_field( $field, $value ) {

        $lists        = $this->get_lists();
        $list_options = $this->get_list_options( $lists, $value );

        ob_start();
        ?>
        <tr valign="top" class="give-mailniaga-sendy-lists">
            <th scope="row" class="titledesc">
                <label for="<?php echo "{$field['id']}_day"; ?>">
                    <?php echo esc_attr( $field['name'] ); ?>
                </label>
            </th>
            <td class="give-forminp give-forminp-api_key">
                <?php if ( ! empty( $list_options ) ) : ?>

                    <select class="give-mailniaga-sendy-list-select" name="<?php echo "{$field['id']}"; ?>"
                            id="<?php echo "{$field['id']}"; ?>">
                        <?php echo $list_options; ?>
                    </select>

                    <button class="give-reset-mailniaga-sendy-button button-secondary"
                            style="margin:0 0 0 2px !important;"
                            data-action="give_reset_mailniaga_lists"
                            data-field_type="select"><?php echo esc_html__( 'Refresh Lists', 'give-mailniaga-sendy' ); ?></button>
                    <span class="give-spinner spinner"></span>

                <?php else : ?>
                    <p><?php _e( 'Your Mailniaga lists will display here once pulled from the API. Please enter valid API keys above to retrieve your mailing lists.', 'give-mailniaga-sendy' ); ?></p>

                <?php endif; ?>
                <p class="give-field-description"><?php echo "{$field['desc']}"; ?></p>
            </td>
        </tr>

        <?php
        return ob_get_contents();
    }

    /**
     * Get the list options in an appropriate field format. This is used to output on page load and also refresh via
     * AJAX.
     *
     * @param        $lists
     * @param array  $value
     * @param string $field_type
     *
     * @return string
     */
	public function get_list_options( $lists, $value = array(), $field_type = 'select' ) {
		$options = '';

		// Make API request to fetch lists
		$api_url = 'https://newsletter.aplikasiniaga.com/api/lists/get-lists.php';
		$api_data = array(
			'api_key'        => give_get_option( 'give_mailniaga_sendy_api' ),
			'brand_id'       => give_get_option( 'give_mailniaga_sendy_brand' ),
			'include_hidden' => 'no', // Change to 'yes' if you want to include hidden lists
		);

		$api_response = wp_remote_post( $api_url, array(
			'body' => $api_data,
		) );

		if ( is_wp_error( $api_response ) ) {
			// Handle error, you can log or display a message
			return $options;
		}

		$lists = json_decode( wp_remote_retrieve_body( $api_response ), true );

		if ( empty( $lists ) || ! is_array( $lists ) ) {
			// Handle empty or invalid API response
			return $options;
		}

		if ( is_string( $value ) ) {
			$value = (array) $value;
		}

		if ( 'select' === $field_type ) {
			// Select options
			foreach ( $lists as $list ) {
				$options .= '<option value="' . esc_attr( $list['id'] ) . '"' . selected( true, in_array( $list['id'], $value ), false ) . '>' . esc_html( $list['name'] ) . '</option>';
			}
		} else {
			// Checkboxes.
			foreach ( $lists as $list ) {
				$options .= '<label class="list"><input type="checkbox" name="_give_' . esc_attr( $this->id ) . '[]"  value="' . esc_attr( $list['id'] ) . '" ' . checked( true, in_array( $list['id'], $value ), false ) . '> <span>' . esc_html( $list['name'] ) . '</span></label>';
			}
		}

		return $options;
	}



    /**
     * AJAX reset Mailniaga lists.
     */
    public function give_reset_mailniaga_lists() {

        // Delete transient.
        delete_transient( 'give_mailniaga_sendy_lists' );

        $field_type = isset( $_POST['field_type'] ) ? give_clean( $_POST['field_type'] ) : '';
        $post_id    = isset( $_POST['post_id'] ) ? give_clean( $_POST['post_id'] ) : '';

        if ( 'select' === $field_type ) {
            $lists = $this->get_list_options( $this->get_lists(), give_get_option( 'give_mailniaga_sendy_list' ) );
        } elseif ( ! empty( $post_id ) ) {
            $lists = $this->get_list_options( $this->get_lists(), give_get_meta( $post_id, '_give_mailniaga_sendy', true ), 'checkboxes' );
        } else {
            wp_send_json_error();
        }

        $return = array(
            'lists' => $lists,
        );

        wp_send_json_success( $return );
    }

}

return new Give_Mailniaga_Sendy();
