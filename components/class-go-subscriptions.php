<?php

class GO_Subscriptions
{
	public $config;
	public $signin_url = '/subscription/sign-in/';
	public $meta_key_prefix = 'go-subscriptions_';
	public $signup_form_id = 'go_subscriptions_signup_form';
	public $version = '2';

	private $signup_form_fill = array();
	private $registered_pages = array();

	// custom post types we need to filter user caps for
	private $protected_post_types = array(
		'go-report',
		'go-report-section',
		'quarterly-wrap-up', // old report type
		'sector-roadmap',    // old report type
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
	public function __construct( $config = false )
	{
		$this->config = apply_filters( 'go_config', $config, 'go-subscriptions' );
		if ( ! $this->config || empty( $this->config ) )
		{
			return;
		}

		// filter for caps for additional post types. we want this to run
		// after the default priority, after the baseline subscription-related
		// caps are filtered
		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 11, 3 );

		// add custom roles
		add_filter( 'go_roles', array( $this, 'go_roles' ) );

		// capture a few URLs to redirect to the homepage
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		if ( is_admin() )
		{
			add_action( 'wp_ajax_go-subscriptions-signup-form', array( $this, 'ajax_signup_form' ) );
			add_action( 'wp_ajax_nopriv_go-subscriptions-signup-form', array( $this, 'ajax_signup_form' ) );
		}
		else
		{
			add_shortcode( 'go_subscriptions_signup_form', array( $this, 'signup_form' ) );
			add_shortcode( 'go_subscriptions_thankyou', array( $this, 'get_thankyou' ) );

			add_action( 'init', array( $this, 'init' ) );
		}// end else

		// on any other blog, we do not want/need the rest of this plugin's
		// functionality
		if ( 'pro' != go_config()->get_property_slug() && 'accounts' != go_config()->get_property_slug() )
		{
			return;
		}

		add_filter( 'site_option_welcome_user_email', array( $this, 'site_option_welcome_user_email' ), 9 );

		if ( ! is_admin() )
		{
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
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] )
		{
			$this->handle_post();
		}

		// doing this here rather than on the wp_enqueue_scripts hook so that it is done before pre_get_posts
		$this->wp_enqueue_scripts();
	}//end init

	/**
	 * Keeping form post handling segregated
	 */
	public function handle_post()
	{
		if ( isset( $_POST['form_id'] ) && $_POST['form_id'] == $this->signup_form_id )
		{
			$this->process_signup_form();
		}
	}//end handle_post

	/**
	 * hooked to the plugins_loaded action to redirect certain URLs to the login page
	 *
	 * @TODO would this be better served as a rewrite rule?
	 */
	public function plugins_loaded()
	{
		$relative_signin_url = $this->signin_url;

		// make this URL absolute, and point at the main site
		$this->signin_url = network_site_url( $this->signin_url, 'https' );

		// redirect all these variations to the login url
		if ( in_array( $_SERVER['REQUEST_URI'], array( '/subscription', '/subscription/', '/register', 'register/' ) )
			|| ( $relative_signin_url == $_SERVER['REQUEST_URI'] && ! is_main_site() )
			)
		{
			wp_redirect( $this->signin_url );
			exit;
		}// end if
	}//end plugins_loaded

	/**
	 * embeddable signup form
	 */
	public function ajax_signup_form()
	{
		nocache_headers();

		$atts = array();

		if ( isset( $_REQUEST['referring_url'] ) )
		{
			$atts['referring_url'] = $_REQUEST['referring_url'];
		}

		if ( isset( $_REQUEST['converted_post_id'] ) )
		{
			$atts['converted_post_id'] = absint( $_REQUEST['converted_post_id'] );
		}

		if ( isset( $_REQUEST['converted_vertical'] ) )
		{
			$atts['converted_vertical'] = sanitize_title_with_dashes( $_REQUEST['converted_vertical'] );
		}

		if ( isset( $_REQUEST['redirect'] ) )
		{
			$atts['redirect'] = $_REQUEST['redirect'];
		}

		echo $this->signup_form( $atts );
		die;
	}//end ajax_signup_form

	/**
	 * Creates a guest user if a user does not already exist with given email
	 *
	 * @param $user_arr array Array of user data
	 * @param $role array Role to assign on creation
	 * @return mixed WP_User if we're able to create a new guest user,
	 *  or WP_Error if we cannot create the user for some reason
	 */
	public function create_guest_user( $user_arr, $role = 'guest-prospect' )
	{
		// user array must contain: email
		$result = $this->validate_clean_user( $user_arr );

		if ( is_wp_error( $result ) )
		{
			return $result;
		}

		$user = go_user_profile()->create_guest_user( $user_arr['email'], $role );

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


		apply_filters( 'go_slog', 'subscriptions-create_guest_user', 'Created guest user', $user_arr );

		do_action( 'go_subscriptions_new_subscriber', get_user_by( 'id', $user_arr['ID'] ) );

		return $user_arr['ID'];
	}//end create_guest_user

	/**
	 * register and enqueue scripts and styles
	 */
	public function wp_enqueue_scripts()
	{
		$script_config = apply_filters( 'go_config', array( 'version' => 1 ), 'go-script-version' );

		wp_register_script(
			'colorbox',
			plugins_url( 'js/external/colorbox/jquery.colorbox.js', __FILE__ ),
			array( 'jquery' ),
			$script_config['version'],
			TRUE
		);
		wp_register_style( 'colorbox', plugins_url( 'js/external/colorbox/colorbox.css', __FILE__ ), array(), $script_config['version'] );
		wp_register_style( 'go-subscriptions', plugins_url( 'css/go-subscriptions.css', __FILE__ ), array(), $script_config['version'] );

		wp_enqueue_script( 'colorbox' );
		wp_enqueue_style( 'colorbox' );
		wp_enqueue_style( 'go-subscriptions' );
	}//end wp_enqueue_scripts

	/**
	 * helper function for getting prefixed subscription meta
	 */
	public function get_subscription_meta( $user_id )
	{
		return get_user_meta( $user_id, $this->meta_key_prefix . 'subscription', TRUE );
	}//end get_subscription_meta

	/**
	 * helper function for updating prefixed subscription meta
	 */
	public function update_subscription_meta( $user_id, $meta )
	{
		return update_user_meta( $user_id, $this->meta_key_prefix . 'subscription', $meta );
	}//end update_subscription_meta

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
			! $post = get_post( $post_id )
			||
			(
				'publish' != $post->post_status &&
				! ( 'inherit' == $post->post_status && 'go-report-section' == $post->post_type
				)
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
		elseif ( isset( $all_caps['read_post'] ) || isset( $all_caps['read'] ) )
		{
			// they DO NOT have an active subscription, but they somehow have the read_post cap
			// explicitly force the removal of this read privilege
			unset( $all_caps['read_post'] );
			unset( $all_caps['read'] );
		}//END elseif

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

		// no commenting on webinars
		if ( 'go-webinar' == $post->post_type )
		{
			return $all_caps;
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
		if ( is_array( $this->config['roles'] ) )
		{
			$roles += $this->config['roles'];
		}

		return $roles;
	}//end go_roles

	/**
	 * get a signup button
	 */
	public function signup_button( $args )
	{
		$args = $this->signup_args( $args );

		if ( ! empty( $args['size'] ) )
		{
			$args['class'] = 'button-' . $args['size'];
		}

		return $this->get_template_part( 'signup-button.php', $args );
	}//end signup_button

	/**
	 * Get the first form in the 2-step process
	 */
	public function signup_form( $atts = array() )
	{
		$arr = array_merge( $this->signup_form_fill, is_array( $atts ) ? $atts : array() );

		// setup default values
		$default_arr = array(
			'company'            => '',
			'converted_post_id'  => get_the_ID(),
			'email'              => '',
			'redirect'           => $this->config['signup_path'],
			'title'              => '',
			'converted_vertical' => array_shift(
				wp_get_object_terms(
					get_the_ID(),
					$this->config['section_taxonomy'],
					array(
						'orderby' => 'count',
						'order' => 'DESC',
						'fields' => 'slugs',
					)
				)
			),
		);

		// we'll take only non-empty values from $arr. rest will be filled
		// with values from $dafault_arr
		$arr = array_merge( $default_arr, array_filter( $arr ) );

		// override the defaults with _REQUEST if available
		foreach ( $arr as $k => $v )
		{
			$arr[ $k ] = isset( $_REQUEST[ $k ] ) ? $_REQUEST[ $k ] : $v;
		}// end foreach

		// this will let our post handler know if the post came from this form
		$arr['form_id'] = $this->signup_form_id;

		return $this->get_template_part( 'signup-form.php', $arr );
	}//end signup_form

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

		$messages = array(
			'Just 1up\'d my knowledge by subscribing to @GigaomPro',
			'Just Level Up\'d my knowledge by subscribing to @GigaomPro',
			'Bigger, Better, Faster! Now rockin\' a subscription to @GigaomPro',
			'Feeling smarter already with a subscription to @GigaomPro',
			'Just invested in my career with a subscription to @GigaomPro',
		);
		$atts['message'] = $messages[ array_rand( $messages ) ];

		wp_enqueue_style( 'thanks' );

		// do shortcode makes sure that any shortcodes that are in the template get parsed.
		return do_shortcode( $this->get_template_part( 'thanks.php', $atts ) );
	}//end get_thankyou

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
	 * get an array of user attributes
	 *
	 * @param $id int WordPress user id
	 * @return array of attributes
	 */
	public function get_user( $id = FALSE )
	{
		$user = array();

		if ( is_numeric( $id ) )
		{
			$current_user = get_user_by( 'id', $id );
		}
		else
		{
			$current_user = wp_get_current_user();
		}

		if ( ! ( $current_user instanceof WP_User ) || $current_user->ID == 0 )
		{
			return FALSE;
		}

		$user['obj'] = $current_user;

		$user['id'] = $current_user->ID;
		$user['email'] = $current_user->user_email;
		$user['first_name'] = $current_user->user_firstname;
		$user['last_name'] = $current_user->user_lastname;

		$profile_data = apply_filters( 'go_user_profile_get_meta', array(), $user['id'] );

		$user['company'] = isset( $profile_data['company'] ) ? $profile_data['company'] : '';
		$user['title'] = isset( $profile_data['title'] ) ? $profile_data['title'] : '';

		return $user;
	}//end get_user

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

		return $message;
	}//end login_message

	/**
	 * process signup form submissions (after step 1 signup)
	 */
	private function process_signup_form()
	{
		if ( ! check_admin_referer( 'go_subscriptions_signup' ) )
		{
			wp_redirect( $this->config['signup_path'] );
			die;
		}
		else
		{
			$return = $this->create_guest_user( $_POST );
		}

		if ( preg_match( '#wiframe/#', $_SERVER['REQUEST_URI'] ) )
		{
			// if this is a wijax request, let's redirect to the page we are already on
			$redirect_url = home_url( $_SERVER['REQUEST_URI'] );
		}
		else
		{
			$redirect_url = isset( $_POST['redirect'] ) ? wp_validate_redirect( $_POST['redirect'], $this->config['subscription_path'] ) : $this->config['subscription_path'];
		}

		if ( is_wp_error( $return ) )
		{
			if ( 'email-exists' == $return->get_error_code() )
			{
				// we are OK to redirect to the CC capture, the user has an account
				$user = $return->get_error_data( 'email-exists' );

				// if the user already has an account and an active subscription, redirect to the login page
				if ( $user->ID && $user->has_cap( 'sub_state_active' ) )
				{
					wp_redirect( $this->signin_url . '?action=lostpassword&has_subscription' );
					exit;
				}

				$redirect_url = add_query_arg( array( 'email' => urlencode( $user->user_email ) ), $redirect_url );
			}//end if
			else
			{
				$this->signup_form_fill = $return->get_error_data();
				$this->signup_form_fill['error'] = $return->get_error_message();
				return;
			}//end else
		}//end if
		else
		{
			// there were no errors, the user is created, log them in.
			$this->login_user( $return );
		}

		wp_redirect( $redirect_url );
		exit;
	}//end process_signup_form

	/**
	 * Send the welcome email, assumed template is configured in Mandrill
	 *
	 * @param $user_id int WordPress user id
	 * @param $subscription array details about the new subscription
	 */
	public function send_welcome_email( $user_id, $subscription )
	{
		$user = $this->get_user( $user_id );

		if ( ! $user )
		{
			apply_filters( 'go_slog', 'subscriptions-user_register_error', 'failed to load new user', $user_id );
			return;
		}

		// generate a password for new users
		$password = wp_generate_password( 8, false );
		// note: wp_set_password will clear the user cache and result in the current logged in user being logged out.
		wp_set_password( $password, $user_id );

		$message = '<placeholder>';	// whatever you place here will be replaced by mandrill

		switch_to_blog( $this->config['subscriptions_blog_id'] ); // make sure our urls go to research

		$args = array(
			'SITE_URL' => network_site_url(),
			'SIGNIN_URL' => $this->signin_url,
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

		$trial_start  = strtotime( $subscription['sub_trial_started_at'] );
		$trial_end    = strtotime( $subscription['sub_trial_ends_at'] );
		$now          = time();

		// if the user is in the midst of a trial, use the trial template
		if ( $now >= $trial_start && $now <= $trial_end )
		{
			$email_template .= '-trial';
		}

		$headers = array();
		$headers[] = 'Content-Type: text/html';
		$headers[] = 'From: research-support@gigaom.com';
		$headers[] = 'Bcc: pro@gigaom.com';
		$headers[] = 'X-MC-Template: ' . $email_template;
		$headers[] = 'X-MC-MergeVars: ' . json_encode( $args );

		wp_mail( $user['email'], 'Welcome to Gigaom Research!', $message, $headers );
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
			'redirect'    => $this->config['subscription_path'], // where do we redirect on a successful subscription
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
			$args['url'] = $this->config['subscription_path'];
			$args['nojs'] = true;
		}
		else
		{
			$args['url'] = $this->config['signup_path'];
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

		return get_site_url( $this->config['subscriptions_blog_id'], '/?p=' . absint( $converted_meta['converted_post_id'] ) );
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
	 * Initialize the widget
	 */
	public function widgets_init()
	{
		require_once __DIR__ . '/class-go-subscriptions-widget.php';
		register_widget( 'GO_Subscriptions_Widget' );

		require_once __DIR__ . '/class-go-subscriptions-ribbon-widget.php';
		register_widget( 'GO_Subscriptions_Ribbon_Widget' );
	}//end widgets_init

	/**
	 * hooked to the site_option_welcome_user_email filter to alter the welcome email
	 */
	public function site_option_welcome_user_email( $text )
	{
		// the formatting of these $text lines are like this so there isn't weird tabbing
		// in the email
		$text = __( 'Dear User,

Your new Gigaom Research account is set up. You can log in with the following information:

Username: USERNAME
Password: PASSWORD
LOGINLINK

Questions? We\'ve got FAQs: http://research.gigaom.com/about/

Thanks!

--The Team @ Gigaom Research' );
		return $text;
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
