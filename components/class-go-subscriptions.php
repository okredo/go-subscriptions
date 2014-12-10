<?php

class GO_Subscriptions
{
	public $id_base = 'go-subscriptions';
	public $signup_form_id = 'go_subscriptions_signup_form';
	public $version = '2';
	public $signin_message_key = 'go-subscriptions-sign-in-message';

	private $config = NULL;

	// custom post types we need to filter user caps for
	private $protected_post_types = array(
		'go-report',
		'go-report-section',
	);
	private $filters = array(
		'read_post',
		'comment',
	);

	/**
	 * constructor
	 *
	 * @param $config array of configuration settings
	 */
	public function __construct()
	{
		// capture a few URLs to redirect to the homepage
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		// filter for caps for additional post types. we want this to run
		// after the default priority, after the baseline subscription-related
		// caps are filtered
		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 11, 3 );

		// add custom roles
		add_filter( 'go_roles', array( $this, 'go_roles' ) );

		// get a user's converted post id
		add_filter( 'go_subscriptions_thankyoucta', array( $this, 'go_subscriptions_thankyoucta' ), 10, 2 );

		if ( is_admin() )
		{
			add_action( 'wp_ajax_go-subscriptions-signup-form', array( $this, 'ajax_signup_form' ) );
			add_action( 'wp_ajax_nopriv_go-subscriptions-signup-form', array( $this, 'ajax_signup_form' ) );
			add_action( 'wp_ajax_go-subscriptions-signup', array( $this, 'ajax_signup' ) );
			add_action( 'wp_ajax_nopriv_go-subscriptions-signup', array( $this, 'ajax_signup' ) );
		}
		else
		{
			//@TODO verify that this is still being used.
			add_shortcode( 'go_subscriptions_signup_form', array( $this, 'signup_form' ) );

			// this is mapped to the "/subscription/sign-up/" permalink
			add_shortcode( 'go_subscriptions_subscription_form', array( $this, 'subscription_form' ) );
			add_shortcode( 'go_subscriptions_thankyou', array( $this, 'get_thankyou' ) );

			add_action( 'init', array( $this, 'init' ) );
		}// end else

		// on any other blog, we do not want/need the rest of this plugin's
		// functionality
		if ( $this->config( 'accounts_blog_id' ) != get_current_blog_id() )
		{
			return;
		}

		add_filter( 'site_option_welcome_user_email', array( $this, 'site_option_welcome_user_email' ), 9 );

		if ( ! is_admin() )
		{
			//@TODO: check if we're still using this shortcode or not
			add_shortcode( 'go_subscriptions_signup_button', array( $this, 'signup_button' ) );
			add_action( 'wp_update_comment_count', array( $this, 'update_comment_count' ), 11, 2 );
		}//end if

		add_filter( 'login_message', array( $this, 'login_message' ), 10, 1 );
	}//end __construct

	/**
	 * hooked to WordPress init
	 */
	public function init()
	{
		// doing this here rather than on the wp_enqueue_scripts hook so that it is done before pre_get_posts
		$this->wp_enqueue_scripts();
	}//end init

	/**
	 * hooked to the plugins_loaded action to redirect certain URLs to the login page
	 *
	 * @TODO would this be better served as a rewrite rule?
	 */
	public function plugins_loaded()
	{
		// redirect all these variations to the login url
		if (
			in_array( $_SERVER['REQUEST_URI'],
					  array( '/subscription', '/subscription/', '/register', 'register/' ) )
			|| ( $this->config( 'signin_path' ) == $_SERVER['REQUEST_URI'] && ! is_main_site() )
		)
		{
			wp_redirect( $this->config( 'signin_url' ) );
			exit;
		}
	}//end plugins_loaded

	/**
	 * returns our current configuration, or a value in the configuration.
	 *
	 * @param string $key (optional) key to a configuration value
	 * @return mixed Returns the config array, or a config value if
	 *  $key is not NULL, or NULL if $key is specified but isn't set in
	 *  our config file.
	 */
	public function config( $key = NULL )
	{
		if ( empty( $this->config ) )
		{
			$this->config = apply_filters(
				'go_config',
				array(),
				'go-subscriptions'
			);

			if ( empty( $this->config ) )
			{
				do_action( 'go_slog', 'go-subscriptions', 'Unable to load go-subscriptions\' config file' );
			}
		}//END if

		if ( ! empty( $key ) )
		{
			return isset( $this->config[ $key ] ) ? $this->config[ $key ] : NULL ;
		}

		return $this->config;
	}//END config

	/**
	 * embeddable signup form
	 */
	public function ajax_signup_form()
	{
		nocache_headers();

		$atts = array( 'go-subscriptions' => array() );

		if ( isset( $_GET['go-subscriptions'] ) )
		{
			$get_vars = $_GET['go-subscriptions'];

			if ( isset( $get_vars['referring_url'] ) )
			{
				$atts['go-subscriptions']['referring_url'] = $get_vars['referring_url'];
			}

			if ( isset( $get_vars['converted_post_id'] ) )
			{
				$converted_post_id = absint( $get_vars['converted_post_id'] );

				// lets do some validation. we only want to set this if we're
				// on a single page and the post is not a page
				if (
					0 < $converted_post_id &&
					! is_page( $converted_post_id ) &&
					is_single( $converted_post_id )
				)
				{
					$atts['go-subscriptions']['converted_post_id'] = $converted_post_id;
				}
			}//END if

			// do not bother to set converted vertical if we don't have
			// a converted post id
			if (
				isset( $get_vars['converted_vertical'] ) &&
				isset( $atts['go-subscriptions']['converted_post_id'] )
			)
			{
				$atts['go-subscriptions']['converted_vertical'] = sanitize_title_with_dashes( $get_vars['converted_vertical'] );
			}

			if ( isset( $get_vars['redirect'] ) )
			{
				$atts['go-subscriptions']['redirect'] = $get_vars['redirect'];
			}

			if ( isset( $get_vars['error'] ) )
			{
				$atts['go-subscriptions']['error'] = $get_vars['error'];
			}
		}//end if

		// is there a header for the signup pop-up dialog box?
		if ( $header = $this->config( 'signup_popup_header' ) )
		{
			$atts['go-subscriptions']['header'] = $header;
		}

		echo $this->signup_form( $atts );
		die;
	}//end ajax_signup_form

	/**
	 * Get the first form in the 2-step process
	 */
	public function signup_form( $atts = array() )
	{
		$arr = ( is_array( $atts ) && isset( $atts['go-subscriptions'] ) ) ? $atts['go-subscriptions'] : array();

		// setup default values
		$converted_post_id = 0;
		$converted_post = get_post();

		if (
			$converted_post &&
			! is_page( $converted_post->ID ) &&
			is_single( $converted_post->ID )
		)
		{
			$converted_post_id = $converted_post->ID;
		}

		$section_taxonomy_terms = array( '' );
		if ( 0 < $converted_post_id )
		{
			$section_taxonomy_terms = wp_get_object_terms(
				$converted_post_id,
				$this->config( 'section_taxonomy' ),
				array(
					'orderby' => 'count',
					'order' => 'DESC',
					'fields' => 'slugs',
				)
			);
		}//END if

		$default_arr = array(
			'company'            => '',
			'converted_post_id'  => $converted_post_id,
			'email'              => '',
			'redirect'           => $this->config( 'signup_path' ),
			'title'              => '',
			'converted_vertical' => array_shift( $section_taxonomy_terms ),
		);

		// we may get here for a signed-in user who has no email
		$user = wp_get_current_user();

		if ( 0 < $user->ID )
		{
			$profile_data = apply_filters( 'go_user_profile_get_meta', array(), $user->ID );
			$default_arr['company'] = isset( $profile_data['company'] ) ? $profile_data['company'] : '';
			$default_arr['title'] = isset( $profile_data['title'] ) ? $profile_data['title'] : '';
		}

		$arr['advisory_annual_cost'] = $this->config( 'advisory_annual_cost' );
		$arr['advisory_monthly_cost'] = $this->config( 'advisory_monthly_cost' );

		// we'll take only non-empty values from $arr. rest will be filled
		// with values from $dafault_arr
		$arr = array_merge( $default_arr, array_filter( $arr ) );

		// override the defaults with _REQUEST if available
		foreach ( $arr as $k => $v )
		{
			$arr[ $k ] = isset( $_REQUEST['go-subscriptions'][ $k ] ) ? $_REQUEST['go-subscriptions'][ $k ] : $v;
		}// end foreach

		// this will let our post handler know if the post came from this form
		$arr['form_id'] = $this->signup_form_id;

		// it's important to make sure this admin ajax url will be executed
		// on Accounts, else we create users with unexpected roles on
		// other blogs (e.g. 'subscriber' may be the default role on
		// Research, which we definitely do not want).
		$arr['ajax_url'] = get_admin_url( $this->config( 'accounts_blog_id' ), '/admin-ajax.php', 'https' );

		return $this->get_template_part( 'signup-form.php', $arr );
	}//end signup_form

	/**
	 * process data submited from the step-1 sign-up form
	 */
	public function ajax_signup()
	{
		$result = $this->get_signup_redirect_url( $this->config( 'signup_path' ) );

		$post_vars['go-subscriptions'] = $result['post_vars'];

		if ( ! empty( $result['error'] ) )
		{
			$post_vars['go-subscriptions']['error'] = $result['error'];
		}

		// encode the post var values
		foreach ( $post_vars['go-subscriptions'] as $key => $val )
		{
			$post_vars['go-subscriptions'][ $key ] = rawurlencode( stripslashes( $val ) );
		}

		// apply our filter to get the final redirect url
		$result['redirect_url'] = apply_filters( 'go_subscriptions_signup', $result['redirect_url'], $result['user'], $post_vars );

		wp_redirect( $result['redirect_url'] );
		die;
	}//end ajax_signup

	public function get_signup_redirect_url( $redirect_url )
	{
		$result = array(
			'redirect_url' => $redirect_url,
			'post_vars' => NULL,
			'user' => NULL,
			'error' => NULL,
		);

		// is this a valid post from our signup page? this action must
		// match the one in templates/signup-form.php
		//
		// skip this test for authenticated users who do not have
		// an email address yet because these users did not get a wp session
		// token on the pop-up signup form, but they do now, so their nonce
		// will not pass this test. see https://github.com/GigaOM/legacy-pro/issues/4452#issuecomment-60812703
		// @TODO address this after deploy to figure out why we don't have
		// a session token in the first sign-up form admin ajax call
		$user = wp_get_current_user();
		if (
			// only do this check if the user is not signed in or if
			// we have the user's email address
			(
				! is_user_logged_in() ||
				! empty( $user->user_email )
			)
			&&
			! check_admin_referer( 'go_subscriptions_signup' )
		)
		{
			$result['error'] = 'Invalid data source';
			return $result;
		}//END if

		// do we have the post data we expect?
		if ( ! isset( $_POST['go-subscriptions'] ) || empty( $_POST['go-subscriptions'] ) )
		{
			$result['error'] = 'Missing post data';
			return $result;
		}

		// make a copy of the post vars to update it before passing them on
		$result['post_vars'] = $_POST['go-subscriptions'];

		// email validation
		if ( empty( $result['post_vars']['email'] ) )
		{
			$result['error'] = 'Please enter an email address.';
			return $result;
		}

		if ( ! is_email( $result['post_vars']['email'] ) )
		{
			$result['error'] = 'Please enter a valid email address.';
			return $result;
		}

		// if subscription request is advisory, we also must validate
		// the company name
		if ( ! empty( $result['post_vars']['sub_request'] ) && 'advisory' == $result['post_vars']['sub_request'] )
		{
			if ( empty( $result['post_vars']['company'] ) )
			{
				$result['error'] = 'Please enter an advisory team name.';
				return $result;
			}

			// the advisory name must not already exist (as a go-enterprise)
			if ( $enterprise_post = get_page_by_path( sanitize_title( $result['post_vars']['company'] ), 'OBJECT', 'go-enterprise' ) )
			{
				$result['error'] = 'An existing team has that name ("' . $result['post_vars']['company'] . '"). Please enter a unique team name to differentiate your team.';
				return $result;
			}
		}//END if

		$return = $this->create_guest_user( $_POST['go-subscriptions'], $this->config( 'default_signup_role' ) );

		if ( preg_match( '#wiframe/#', $_SERVER['REQUEST_URI'] ) )
		{
			// if this is a wijax request, let's redirect to the page we are already on
			$result['redirect_url'] = home_url( $_SERVER['REQUEST_URI'] );
		}
		else
		{
			$result['redirect_url'] = isset( $_POST['go-subscriptions']['redirect'] ) ? wp_validate_redirect( $_POST['go-subscriptions']['redirect'], $this->config( 'subscription_path' ) ) : $this->config( 'subscription_path' );
		}

		if ( is_wp_error( $return ) )
		{
			// we could be in here if a guest or individual user is upgrading
			// to an advisory subscription
			if ( 'email-exists' == $return->get_error_code() )
			{
				// we are OK to redirect to the CC capture, the user has an
				// account
				$result['user'] = $return->get_error_data( 'email-exists' );

				// if the user already has an account with an active
				// subscription, or is an admin, redirect to the sign-in page
				if (
					$result['user']->ID &&
					$result['user']->has_cap( 'subscriber' ) &&
					empty( $result['post_vars']['sub_request'] )
				)
				{
					$result['error'] = 'Email already linked to a subscription';
					$result['redirect_url'] = $this->config( 'signin_url' ) . '?action=lostpassword&has_subscription';
					return $result;
				}//end if

				$result['post_vars']['email'] = $result['user']->user_email;
				$result['post_vars']['is_subscriber'] = $result['user']->has_cap( 'subscriber' );

				// is this an upgrade to an advisory subscription?
				if ( ! empty( $result['post_vars']['sub_request'] ) && 'advisory' == $result['post_vars']['sub_request'] )
				{
					// fire the go_subscriptions_created_guest_user action
					// to give go-advisories a chance to create a new advisory post
					do_action( 'go_subscriptions_created_guest_user', $result['user'], $result['post_vars'] );
				}
			}//end if
			else
			{
				$result['redirect_url'] = $this->config( 'signup_path' );
				if ( ! empty( $result['post_vars']['sub_request'] ) )
				{
					$result['redirect_url'] = esc_url_raw( $result['redirect_url'] . $result['post_vars']['sub_request'] . '/' );
				}
				$result['error'] = urlencode( $return->get_error_message() );
			}//end else
		}//end if
		else
		{
			// there were no errors, the user is created, log them in.
			$this->login_user( $return );

			$result['user'] = get_user_by( 'id', $return );

			// fire the go_subscriptions_created_guest_user action
			// to give go-advisories a chance to create a new advisory post
			do_action( 'go_subscriptions_created_guest_user', $result['user'], $result['post_vars'] );

			// send advisory member who're not subscribers yet to the step-2
			// CC form
			if ( current_user_can( 'moderate_advisory_team' ) && ! current_user_can( 'subscriber-advisory' ) )
			{
				if ( empty( $result['post_vars']['redirect_url'] ) )
				{
					$result['redirect_url'] = $this->config( 'subscription_path' );
				}
				else
				{
					$result['redirect_url'] = add_query_arg( 'redirect_url', wp_validate_redirect( $result['post_vars']['redirect_url'] ), $this->config( 'subscription_path' ) );
				}
			}//END if
			else
			{
				if ( empty( $result['post_vars']['redirect_url'] ) )
				{
					$result['redirect_url'] = $this->config( 'thankyou_path' );
				}
				else
				{
					$result['redirect_url'] = add_query_arg( 'redirect_url', wp_validate_redirect( $result['post_vars']['redirect_url'] ), $this->config( 'thankyou_path' ) );
				}
			}//END else
		}//end else

		return $result;
	}//end get_signup_redirect_url

	/**
	 * the handler for "go_subscriptions_subscription_form" shortcode.
	 *
	 * @param array $atts attributes needed by the form
	 * @return string the subscription form
	 */
	public function subscription_form( $atts )
	{
		// check if we have any shortcode attributes
		$atts = shortcode_atts( array( 'sub' => '' ), $atts );

		if ( ! isset( $_GET['go-subscriptions'] ) )
		{
			$_GET['go-subscriptions'] = array();
		}

		if ( empty( $_GET['go-subscriptions']['sub_request'] ) )
		{
			$_GET['go-subscriptions']['sub_request'] = $atts['sub'];
		}

		$user = wp_get_current_user();

		if (
			0 >= $user->ID ||
			empty( $user->user_email ) ||
			! user_can( $user, 'subscriber' )
		)
		{
			// we don't have a user yet, but we may have an email for
			// a non-subscriber
			if (
				! empty( $_GET['go-subscriptions']['email'] ) &&
				isset( $_GET['go-subscriptions']['is_subscriber'] ) &&
				empty( $_GET['go-subscriptions']['is_subscriber'] )
			)
			{
				$user = get_user_by( 'email', $_GET['go-subscriptions']['email'] );
			}
		}//END if

		$form = $this->signup_form( $_GET ); // step-1 signup form
		$skip_filter = FALSE;

		// we track user roles on Accounts
		switch_to_blog( $this->config( 'accounts_blog_id' ) );

		// if this is a request from the corporate purchase page, we may
		// have to alert the user if the requested subscription plan is at
		// or below the user's current subscription level
		// @TODO this whole block can probably be refactored and simplified
		if ( is_user_logged_in() )
		{
			if ( empty( $_GET['go-subscriptions']['sub_request'] ) )
			{
				// requested basic free plan but already birdied
				if ( user_can( $user, 'guest-prospect' ) )
				{
					$form = $this->config( 'basic_plan_redux_message' );
					$skip_filter = TRUE;
				}
				elseif ( user_can( $user, 'subscriber' ) )
				{
					$skip_filter = TRUE;

					if ( user_can( $user, 'subscriber-advisory' ) )
					{
						$form = $this->config( 'advisory_plan_redux_message' );
					}
					elseif ( user_can( $user, 'subscriber-enterprise' ) )
					{
						$form = $this->config( 'corporate_plan_redux_message' );
					}
					else
					{
						$form = $this->config( 'individual_plan_redux_message' );
					}
				}//END elseif
			}//END if
			elseif ( 'individual' == $_GET['go-subscriptions']['sub_request'] )
			{
				// requested individual plan but already an individual,
				// advisory, or a corporate subscriber
				if ( user_can( $user, 'subscriber' ) )
				{
					$skip_filter = TRUE;
					if (
						! user_can( $user, 'subscriber-advisory' ) &&
						! user_can( $user, 'subscriber-enterprise' )
					)
					{
						$form = $this->config( 'individual_plan_redux_message' );
					}
					elseif ( user_can( $user, 'subscriber-advisory' ) )
					{
						$form = $this->config( 'advisory_plan_redux_message' );
					}
					elseif ( user_can( $user, 'subscriber-enterprise' ) )
					{
						$form = $this->config( 'corporate_plan_redux_message' );
					}
				}//END if
			}//END elseif
			elseif ( 'advisory' == $_GET['go-subscriptions']['sub_request'] )
			{
				// requested advisory plan but already an advisory or
				// corporate subscriber
				if ( user_can( $user, 'subscriber-advisory' ) )
				{
					$form = $this->config( 'advisory_plan_redux_message' );
					$skip_filter = TRUE;
				}
				elseif ( user_can( $user, 'subscriber-enterprise' ) )
				{
					$form = $this->config( 'corporate_plan_redux_message' );
					$skip_filter = TRUE;
				}
			}//END elseif
		}//END if

		restore_current_blog();

		return $skip_filter ? $form : apply_filters( 'go_subscriptions_signup_form', $form, $user->ID, $_GET );
	}//end subscription_form

	/**
	 * Creates a guest user if a user does not already exist with given email
	 *
	 * @param $user_arr array Array of user data
	 * @param $role array Role to assign on creation
	 * @param $log_in_user bool to determine whether or not to log user in. If this is part of a batch onboard this must be false.
	 * @return mixed WP_User if we're able to create a new guest user,
	 *  or WP_Error if we cannot create the user for some reason
	 */
	public function create_guest_user( $user_arr, $role = 'guest', $log_in_user = TRUE )
	{
		// user array must contain: email
		$result = $this->validate_clean_user( $user_arr );

		if ( is_wp_error( $result ) )
		{
			return $result;
		}

		$user = go_user_profile()->create_guest_user( $user_arr['email'], $role, $log_in_user );

		if ( ! $user )
		{
			return new WP_Error( 'account-fail', 'An error occurred and we were not able to create an account', $user_arr );
		}
		elseif ( is_wp_error( $user ) )
		{
			return $user; // the original WP_Error from create_guest_user()
		}

		$user_arr['username'] = $user->user_login;
		$user_arr['ID'] = $user->ID;

		$base_user = array(
			'ID' => $user_arr['ID'],
			'first_name' => isset( $user_arr['first_name'] ) ? $user_arr['first_name'] : '',
			'last_name' => isset( $user_arr['last_name'] ) ? $user_arr['last_name'] : '',
		);

		wp_update_user( $base_user );

		if ( isset( $user_arr['title'] ) && $user_arr['title'] )
		{
			do_action( 'go_user_profile_update_meta', $user_arr['ID'], 'title', $user_arr['title'] );
		} // end if

		if ( isset( $user_arr['company'] ) && $user_arr['company'] )
		{
			do_action( 'go_user_profile_update_meta', $user_arr['ID'], 'company', $user_arr['company'] );
		} // end if

		$converted_data = array();

		if ( ! empty( $user_arr['converted_post_id'] ) )
		{
			$converted_data['converted_post_id'] = $user_arr['converted_post_id'];
		}// end if

		if ( ! empty( $user_arr['converted_vertical'] ) )
		{
			$converted_data['converted_vertical'] = $user_arr['converted_vertical'];
		}// end if

		$this->update_converted( $user_arr['ID'], $converted_data );


		do_action( 'go_slog', 'subscriptions-create_guest_user', 'Created guest user', $user_arr );

		do_action( 'go_subscriptions_new_subscriber', get_user_by( 'id', $user_arr['ID'] ) );

		return $user_arr['ID'];
	}//end create_guest_user

	/**
	 * register and enqueue scripts and styles
	 */
	public function wp_enqueue_scripts()
	{
		$script_config = apply_filters( 'go_config', array( 'version' => 1 ), 'go-script-version' );

		wp_register_style( 'go-subscriptions', plugins_url( 'css/go-subscriptions.css', __FILE__ ), array(), $script_config['version'] );

		wp_enqueue_style( 'go-subscriptions' );
	}//end wp_enqueue_scripts

	/**
	 * helper function to get the converted meta
	 */
	public function get_converted_meta( $user_id )
	{
		// the metakey is legacy and note that it uses all underscores
		// instead of go-subscriptions_converted_meta
		// @TODO: rename go_subscriptions_converted_meta to
		// go-subscriptions_converted_meta
		return get_user_meta( $user_id, 'go_subscriptions_converted_meta', TRUE );
	}//end get_converted_meta

	/**
	 * helper function to update the converted meta
	 */
	public function update_converted_meta( $user_id, $converted_meta )
	{
		// the metakey is legacy and note that it uses all underscores
		// instead of go-subscriptions_converted_meta
		// @TODO: rename go_subscriptions_converted_meta to
		// go-subscriptions_converted_meta
		return update_user_meta( $user_id, 'go_subscriptions_converted_meta', $converted_meta );
	}//end update_converted_meta

	/**
	 * Additional user cap filtering that take into account of some of
	 * our custom post types
	 *
	 * @param $all_caps array of capabilities they have (to be filtered)
	 * @param $unused_meta_caps array of the required capabilities they need to have for a successful current_user_can
	 * @param $args array [0] Requested capability
	 *                    [1] User ID
 	 *                    [2] Associated object ID
	 */
	public function user_has_cap( $all_caps, $unused_meta_caps, $args )
	{
		$cap = $args[0];

		// is this a request for one of the caps we want to filter?
		if ( ! in_array( $cap, $this->filters ) )
		{
			return $all_caps;
		}

		if ( 3 > count( $args ) )
		{
			return $all_caps; // we don't have a post id to test against
		}

		$post_id = $args[2];

		// bail if the post isn't valid or isn't published
		if (
			( ! $post = get_post( $post_id ) )
			||
			(
				'publish' != $post->post_status &&
				! ( 'inherit' == $post->post_status && 'go-report-section' == $post->post_type )
			)
		)
		{
			return $all_caps;
		}//end if

		switch ( $cap )
		{
			case 'read_post':
				return $this->user_has_cap_read_post( $all_caps, $post );

			case 'comment':
				return $this->user_has_cap_comment( $all_caps, $post );
		}//END switch

		// really shouldn't get here...
		return $all_caps;
	}//END user_has_cap

	/**
	 * alters the "read_post" capability if appropriate
	 *
	 * @param $all_caps array of capabilities they have (to be filtered)
	 * @param $post WP_Post object the check is relative to
	 */
	private function user_has_cap_read_post( $all_caps, $post )
	{
		// as long as it is not a protected post type, we want users to be able to read posts
		if ( ! in_array( $post->post_type, $this->protected_post_types ) )
		{
			$all_caps['read_post'] = $all_caps['read'] = TRUE;
		}
		// check their account state
		elseif ( current_user_can( 'subscriber' ) )
		{
			// they have an active account, they can read
			$all_caps['read_post'] = $all_caps['read'] = TRUE;
		}
		// if they can edit, they can read
		elseif ( current_user_can( 'edit_posts' ) )
		{
			$all_caps['read_post'] = $all_caps['read'] = TRUE;
		}

		return $all_caps;
	}//END user_has_cap_read_post

	/**
	 * adds a "comment" capability if appropriate
	 *
	 * @param $all_caps array of capabilities they have (to be filtered)
	 * @param $post WP_Post object the check is relative to
	 */
	private function user_has_cap_comment( $all_caps, $post )
	{
		// in case something else added a comment cap, let's remove it
		if ( isset( $all_caps['comment'] ) )
		{
			unset( $all_caps['comment'] );
		}

		// if comments are closed for the post, no comment cap for you!
		if ( ! comments_open( $post->ID ) )
		{
			return $all_caps;
		}

		// if they can edit, they can comment
		if ( current_user_can( 'edit_posts' ) )
		{
			$all_caps['comment'] = TRUE;
		}
		// check their account state, if they have an active account, they can comment
		elseif ( current_user_can( 'subscriber' ) )
		{
			$all_caps['comment'] = TRUE;
		}

		return $all_caps;
	}//END user_has_cap_comment

	/**
	 * hook to go_roles to add custom roles
	 *
	 * @param $roles array all existing roles
	 * @return array filtered to add custom roles
	 */
	public function go_roles( $roles )
	{
		if ( is_array( $this->config( 'roles' ) ) )
		{
			$roles += $this->config( 'roles' );
		}

		return $roles;
	}//end go_roles

	/**
	 * hook to the go_subscriptions_thankyoucta filter to return custom
	 * contents for the cta button on the thank you page
	 *
	 * @param array $cta_contents ['text']  button text and title
	 *                            ['url']   button href
	 *                            ['class'] button class
	 *                            ['sub_text_html'] html for any subtitle below the button
	 * @param int $user_id user id
	 */
	public function go_subscriptions_thankyoucta( $cta_contents, $user_id )
	{
		// validate the user
		if ( ! $user = get_user_by( 'id', $user_id ) )
		{
			return $cta_contents;
		}

		// check if we have a converted post or not
		$converted_meta = $this->get_converted_meta( $user_id );

		if ( empty( $converted_meta['converted_post_id'] ) )
		{
			return $cta_contents;
		}

		// we do. lets update the button
		$cta_contents['text']  = 'Continue to report';
		$cta_contents['url']   = get_permalink( $converted_meta['converted_post_id'] );
		$cta_contents['class']  = 'button-large';
		$cta_contents['sub_text_html'] = '<br /><em>' . get_the_title( $converted_meta['converted_post_id'] ) . '</em>';

		return $cta_contents;
	}//END go_subscriptions_thankyoucta

	/**
	 * get a signup button
	 * @TODO: check if we can remove this function
	 */
	public function signup_button( $args )
	{
		$args = $this->signup_args( $args );

		if ( ! empty( $args['size'] ) )
		{
			$args['class'] = 'button-' . $args['size'];
		}

		return $this->get_template_patr( 'signup-button.php', $args );
	}//end signup_button

	/**
	 * Get the final "thank you" page in the subscription process
	 */
	public function get_thankyou( $atts = array() )
	{
		// make sure the user receives newsletter subscriptions:
		if ( is_user_logged_in() )
		{
			do_action( 'go_subscriptions_new_subscriber', wp_get_current_user() );
		}

		$atts = shortcode_atts( array(), $atts );

		wp_enqueue_style( 'thanks' );

		// do shortcode makes sure that any shortcodes that are in the template get parsed.
		return do_shortcode( $this->get_template_part( 'thanks.php', $atts ) );
	}//end get_thankyou

	/**
	 * construct a (form) field name based on our plugin id
	 *
	 * @param string $name specific name of the field
	 * @return the field name based on our $id_base and $name
	 */
	private function get_field_name( $name )
	{
		return $this->id_base . '[' . $name . ']';
	}//END get_field_name

	/**
	 * Get the template part in an output buffer and return it
	 *
	 * @param string $template_name
	 * @param array $template_variables used in included templates
	 *
	 * @todo Rudimentary part/child theme file_exists() checks
	 */
	public function get_template_part( $template_name, $template_variables = array() )
	{
		ob_start();
		include( __DIR__ . '/templates/' . $template_name );
		return ob_get_clean();
	}//end get_template_part

	/**
	 * log a given user id in
	 *
	 * @param $user_id int WordPress user id
	 * @param $durable boolean (optional) indicate whether this is a one time or permanent cookie
	 */
	public function login_user( $user_id, $durable = FALSE )
	{
		wp_set_auth_cookie( $user_id, $durable, TRUE );

		do_action( 'wp_login', $user_id );
	}//end login_user

	/**
	 * hooked to the WordPress login_message filter
	 *
	 * @param $message string the login message to be filtered
	 * @return string the login message
	 */
	public function login_message( $message )
	{
		global $errors;

		if ( isset( $_GET['has_subscription'] ) )
		{
			$message = preg_replace( '#<p class="message">#', '<p class="message">It looks like you already have a subscription! ', $message );
		}
		elseif ( ! empty( $_GET[ $this->signin_message_key ] ) )
		{
			$message_id = absint( $_GET[ $this->signin_message_key ] );

			$signin_messages = $this->config( 'signin_messages' );
			if ( ! empty( $signin_messages[ $message_id ] ) )
			{
				$message = $signin_messages[ $message_id ];
			}
		}//END elseif

		return $message;
	}//end login_message

	/**
	 * Send the welcome email, assumed template is configured in Mandrill
	 *
	 * @param $user_id int WordPress user id
	 * @param $subscription array details about the new subscription
	 */
	public function send_welcome_email( $user_id, $subscription )
	{
		$user = get_user_by( 'id', $user_id );

		if ( ! $user )
		{
			do_action( 'go_slog', 'subscriptions-user_register_error', 'failed to load new user', $user_id );
			return;
		}

		// generate a password for new users
		$password = wp_generate_password( 8, false );
		// note: wp_set_password will clear the user cache and result in the current logged in user being logged out.
		wp_set_password( $password, $user_id );

		switch_to_blog( $this->config( 'subscriptions_blog_id' ) ); // make sure our urls go to research

		$args = array(
			'SITE_URL' => network_site_url(),
			'SIGNIN_URL' => $this->config( 'signin_url' ),
			'TEMPLATE_URL' => get_template_directory_uri(),
			'STYLESHEET_URL' => preg_replace( '/^https:/', 'http:', get_stylesheet_directory_uri() ),
			'DATE_YEAR' => date( 'Y' ),
			'TEMPORARY_PASSWORD' => $password,
			'TOPIC_SOCIAL_URL' => site_url( '/topic/social/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'TOPIC_MOBILE_URL' => site_url( '/topic/mobile/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'TOPIC_CONSUMER_URL' => site_url( '/topic/consumer/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'TOPIC_CLOUD_URL' => site_url( '/topic/cloud/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'TOPIC_CLEANTECH_URL' => site_url( '/topic/cleantech/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'TOPIC_BUYERSLENS_URL' => site_url( '/topic/buyers-lens/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'ABOUT_URL' => site_url( '/about/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'ANALYST_URL' => site_url( '/analysts/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
			'NEWSLETTER_URL' => network_site_url( '/newsletters/?utm_source=email&utm_medium=email&utm_campaign=welcome&utm_content=message1220' ),
		);

		restore_current_blog();

		$email_template = 'research-welcome';

		// check if the user is in the midst of a trial period and use
		// the trial template if so
		if ( ! empty( $subscription['sub_trial_started_at'] ) && ! empty( $subscription['sub_trial_ends_at'] ) )
		{
			$trial_start  = strtotime( $subscription['sub_trial_started_at'] );
			$trial_end    = strtotime( $subscription['sub_trial_ends_at'] );
			$now          = time();

			if ( $now >= $trial_start && $now <= $trial_end )
			{
				$email_template .= '-trial';
			}
		}//END if

		// build the email params for the go-subscriptions-welcome-email filter
		$email_args = array(
			'to' => $user->user_email,
			'subject' => 'Welcome!',
			'headers' => array(),
			'content' => '<placeholder>', // to be filled in my Mandrill
		);

		$email_args['headers']['Content-Type']   = 'text/html';
		$email_args['headers']['X-MC-Template']  = $email_template;
		$email_args['headers']['X-MC-MergeVars'] = json_encode( $args );
		$email_args['headers']['From'] = 'your_email@he.re';

		// From and possibly Bcc and/or Cc to be filled by the filter call
		$email_args = apply_filters( 'go_subscriptions_welcome_email', $email_args, $user );

		// convert the headers to a format usable by wp_mail
		$headers = array();
		foreach ( $email_args['headers'] as $key => $val )
		{
			$headers[] = $key . ': ' . $val;
		}

		wp_mail( $email_args['to'], $email_args['subject'], $email_args['content'], $headers );
	}//end send_welcome_email

	/**
	 * initialize arguments for signups
	 *
	 * @param $args array all the things
	 */
	public function signup_args( $args )
	{
		$args = shortcode_atts( array(
			'path'        => '', // the path is sanitized in home_url()
			'redirect'    => $this->config( 'subscription_path' ), // where do we redirect on a successful subscription
			'button_text' => '', // deprecated
			'text'        => '',
			'sub_text'    => '',
			'pre_text'    => '',
			'coupon'      => '',
			'size'        => 'medium',
			'standalone'  => false,
		), $args );

		$args['text'] = $args['text'] ?: $args['button_text'];

		if ( empty( $args['text'] ) )
		{
			if ( ! is_user_logged_in() || ! current_user_can( 'expired_subscriber' ) )
			{
				$args['text'] = 'Try it free for one week';
			}
			else
			{
				$args['text'] = 'Start your subscription';
			}
		}//end if

		if ( empty( $args['path'] ) )
		{
			// the permalink will give back a full URL, but we only need a path relative to the WP base installed path
			// so, we strip the home_url() part out of the permalink
			$url = str_replace( home_url(), '', get_permalink( get_queried_object_id() ) . '#signup-form' );
			$args['path'] = add_query_arg(
				array(
					'force_ssl' => 1,
					'coupon' => $args['coupon'],
				),
				$url
			);
		}//end if

		$args['referring_url'] = home_url( $args['path'], 'https' );

		if ( current_user_can( 'login_with_key' ) )
		{
			$args['url'] = $this->config( 'subscription_path' );
			$args['nojs'] = true;
		}
		else
		{
			$args['url'] = $this->config( 'signup_path' );
			$args['nojs'] = false;
		}

		return $args;
	}//end signup_args

	/**
	 * updates conversion information for tracking where signup referrals came from
	 *
	 * @param $user_id int WordPress user id
	 * @param $converted_data array metadata
	 */
	private function update_converted( $user_id, $converted_data )
	{
		// no data to store - bail!
		if ( empty( $converted_data ) )
		{
			return;
		}

		$this->update_converted_meta( $user_id, $converted_data );

		// reset the comment count to include conversions if we have a post_id
		if ( ! empty( $converted_data['converted_post_id'] ) )
		{
			wp_update_comment_count( $converted_data['converted_post_id'] );
		}
	}//end update_converted

	/**
	 * Retrieve the url for the post mapped to by the user's converted post
	 * id user meta. The signature of this function is designed to be usable
	 * by the field mapping config files of `go-syncuser`-related plugins
	 * (`go-mcsync`, `go-mailchimp`, and `go-marketo` at least).
	 *
	 * @param WP_User $user a WP_User object
	 * @return the post url corresponding to the user's converted post id,
	 *  or an empty string if converted post id does not exist or cannot
	 *  be mapped to a post url.
	 */
	public function get_converted_url( $user )
	{
		if ( empty( $user->ID ) )
		{
			return '';
		}

		// check if the user has a converted post id user meta
		$converted_meta = $this->get_converted_meta( $user->ID );

		if ( empty( $converted_meta['converted_post_id'] ) || ! is_numeric( $converted_meta['converted_post_id'] ) )
		{
			return '';
		}

		return get_site_url( $this->config( 'subscriptions_blog_id' ), '/?p=' . absint( $converted_meta['converted_post_id'] ) );
	}//end get_converted_url

	/**
	 * hooked to wp_update_comment_count
	 */
	public function update_comment_count( $post_id, $new )
	{
		global $wpdb;
		$sql = "
		SELECT COUNT(*)
		  FROM {$wpdb->usermeta}
		  WHERE meta_value like '%\"converted_post_id\";s:%:\"%d\";%'
			AND meta_key = 'go_subscriptions_converted_meta'
		";

		$conversions = (int) $wpdb->get_var( $wpdb->prepare( $sql, $post_id ) );
		$total = $new + $conversions;

		$wpdb->update( $wpdb->posts, array( 'comment_count' => $total ), array( 'ID' => $post_id ) );
	}//end update_comment_count

	/**
	 * Validates and cleans an array of user elements
	 *
	 * @param $user_arr array of user attributes
	 * @return array of cleaned user attributes or a WP_Error on failure
	 */
	private function validate_clean_user( $user_arr )
	{
		// Get the email
		$user_arr['email'] = sanitize_email( $user_arr['email'] );

		// error checking, an empty email is and unrecoverable error, anything else should be a valid, filtered address
		if ( empty( $user_arr['email'] ) )
		{
			return new WP_Error( 'email-invalid', 'Please enter a valid email address.', $user_arr );
		}

		$user_arr['first_name'] = isset( $user_arr['first_name'] ) ? sanitize_text_field( $user_arr['first_name'] ) : '';
		$user_arr['last_name'] = isset( $user_arr['last_name'] ) ? sanitize_text_field( $user_arr['last_name'] ) : '';
		$user_arr['title'] = isset( $user_arr['title'] ) ? sanitize_text_field( $user_arr['title'] ) : '';
		$user_arr['company'] = isset( $user_arr['company'] ) ? sanitize_text_field( $user_arr['company'] ) : '';

		return $user_arr;
	}//end validate_clean_user

	/**
	 * hooked to the site_option_welcome_user_email filter to alter the welcome email
	 */
	public function site_option_welcome_user_email( $unused_text )
	{
		return $this->config( 'welcome_user_email_text' );
	}//end site_option_welcome_user_email
}//end class

/**
 * singleton function for go_subscriptions
 */
function go_subscriptions()
{
	global $go_subscriptions;

	if ( ! isset( $go_subscriptions ) || ! is_object( $go_subscriptions ) )
	{
		$go_subscriptions = new GO_Subscriptions();
	}

	return $go_subscriptions;
}//end go_subscriptions
