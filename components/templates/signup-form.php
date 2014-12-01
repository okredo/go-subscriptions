<?php
if ( apply_filters( 'go_site_locked', FALSE ) )
{
	go_sitelock()->lock_screen( 'Signing up for an account' );
	return;
}//end if

// company name is required for advisory subscriptions
$is_advisory_signup = ! empty( $template_variables['sub_request'] ) && 'advisory' == $template_variables['sub_request'];

// conditionally populate email and company fields if a guest user is logged
// in and on the sign-up form
if ( is_user_logged_in() )
{
	$current_user = wp_get_current_user();
	if ( ! isset( $template_variables['email'] ) || empty( $template_variables['email'] ) )
	{
		$template_variables['email'] = $current_user->user_email;
	}

	if ( ! isset( $template_variables['company'] ) || empty( $template_variables['company'] ) )
	{
		$profile_data = apply_filters( 'go_user_profile_get_meta', array(), $current_user->ID );
		if ( $profile_data['company'] )
		{
			$template_variables['company'] = $profile_data['company'];
		}//END if
	}//END if
}//END if
?>

<div class="go-subscriptions-signup clearfix">
	<?php
	if ( isset( $template_variables['error'] ) )
	{
		?>
		<div class="go-signup-error"><?php echo stripslashes( urldecode( $template_variables['error'] ) );?></div>
		<?php
	}// end if

	if ( ! empty( $template_variables['header'] ) )
	{
		echo '<header>' . esc_html( $template_variables['header'] ) . "</header>\n";
	}
	?>
	<div id="form-wrapper">
		<form id="go-subscriptions-signup" class="boxed" method="post" action="<?php echo esc_attr( $template_variables['ajax_url'] ); ?>">
			<input type="hidden" name="action" value="go-subscriptions-signup" />
			<input type="hidden" name="go-subscriptions[form_id]" value="<?php echo esc_attr( $template_variables['form_id'] );?>" />
			<input type="hidden" name="go-subscriptions[converted_post_id]" value="<?php echo isset( $template_variables['converted_post_id'] ) ? absint( $template_variables['converted_post_id'] ) : '';?>" />
			<input type="hidden" name="go-subscriptions[converted_vertical]" value="<?php echo isset( $template_variables['converted_vertical'] ) ? esc_attr( $template_variables['converted_vertical'] ) : '';?>" />
			<input type="hidden" name="go-subscriptions[redirect]" value="<?php echo esc_attr( $template_variables['redirect'] );?>" />
			<input type="hidden" name="go-subscriptions[referring_url]" value="<?php echo isset( $template_variables['referring_url'] ) ? esc_url( $template_variables['referring_url'] ) : '';?>" />
			<input type="hidden" name="go-subscriptions[sub_request]" value="<?php echo esc_attr( $template_variables['sub_request'] );?>" />
			<?php wp_nonce_field( 'go_subscriptions_signup' ); ?>

			<ul>
				<li class="field-container email required">
					<label for="go-subscriptions-email">Email address</label>
					<input <?php echo ! empty( $template_variables['email'] ) ? 'readonly class="readonly"' : ''; ?>
						id="go-subscriptions-email"
						type="email"
						name="go-subscriptions[email]"
						value="<?php echo isset( $template_variables['email'] ) ? esc_attr( $template_variables['email'] ) : ''; ?>"/>
					<?php
					if ( $is_advisory_signup )
					{
						// email domain notification bumpdown message
						?>
						<button id="email-domain-alert" class="button link"></button>
						<div class="hide boxed" data-trigger="email-domain-alert">
							<span class="bumpdown-arrow"></span>
							<a class="bumpdown-close" title="Close"><i class="goicon icon-x"></i></a>
							<h1>About Consumer Email Addresses</h1>
							<p id="email-domain-alert-msg"></p>
						</div>
						<?php
						}//END if
					?>
				</li>
				<li class="field-container company <?php echo $is_advisory_signup ? 'required' : ''; ?>">
					<label for="go-subscriptions-company"><?php echo apply_filters( 'go_subscriptions_signup_company_label', 'Company name', $is_advisory_signup ) ?></label>
					<input id="go-subscriptions-company" type="text" name="go-subscriptions[company]" value="<?php echo isset( $template_variables['company'] ) ? esc_attr( $template_variables['company'] ) : ''; ?>" />
				</li>
				<!-- hidden by CSS for now -->
				<li class="field-container title">
					<label for="go-subscriptions-title">Title</label>
					<input id="go-subscriptions-title" type="text" name="go-subscriptions[title]" value="<?php echo isset( $template_variables['title'] ) ? esc_attr( $template_variables['title'] ) : ''; ?>" />
				</li>
			</ul>
				<?php
				// payment option
				if ( $is_advisory_signup )
				{
				?>
					<span class="go-radio-title">Payment options:</span>
					<label for="annual-payment-plan">
						<input type="radio" class="go-radio" name="go-subscriptions[payment_plan]" id="annual-payment-plan" value="advisory-annual" checked>
						<span>One payment of $5000 per year</span>
					</label>
					<label for="monthly-payment-plan">
						<input type="radio" class="go-radio" name="go-subscriptions[payment_plan]" id="monthly-payment-plan" value="advisory-monthly">
						<span>12 payments of $500 per month</span>
					</label>
				<?php
				}//END if
				?>

			<div class="well">
				<button class="button primary" type="submit">Continue</button>
			</div>
		</form>

		<p>
			<strong>Required</strong>
			Your email address is your sign-in ID for Gigaom Research.
			By continuing, you are agreeing to our <a href="http://gigaom.com/terms-of-service/">Terms of Service</a>
			and <a href="http://gigaom.com/privacy-policy/">Privacy Policy</a>.
		</p>
	</div>

	<div id="marketing-box">
		<?php include get_stylesheet_directory() . '/img/research.svg'; ?>
		<div>
			<h2>Register to gain access to <strong>any</strong> single report for <strong>free</strong>.</h2>
			<p>When you register with Gigaom Research, you gain access to the full text of any report of your choosing. We also make other reports available from time to time available only to registered users. With over 200 independent analysts in our network, Gigaom Research gives you the in-depth analysis you need to succeed.</p>
		</div>
	</div>

	<header class="skip-to-login">
		Already have an account?<br/>
		<a href="<?php echo wp_login_url(); ?>" class="sign-in-link sign-in">Sign in</a> now.
	</header>
</div>

