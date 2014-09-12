<?php
if ( apply_filters( 'go_site_locked', FALSE ) )
{
	go_site_lock()->lock_screen( 'Signing up for an account' );
	return;
}//end if
?>

<div class="go-subscriptions-signup clearfix">
	<?php
	if ( isset( $template_variables['error'] ) )
	{
		?>
		<div class="go-signup-error"><?php echo $template_variables['error'];?></div>
		<?php
	}// end if

	?>
	<h3>Access to Gigaom Research for one week, completely Free</h3>
	<div id="form-wrapper">
		<form id="go-subscriptions-signup" class="boxed" method="post" action="<?php echo ! defined( 'IS_WIJAX' ) ? network_site_url( '/subscription/sign-up/', 'https' ) : '';?>">
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $template_variables['form_id'] );?>" />
			<input type="hidden" name="converted_post_id" value="<?php echo isset( $template_variables['converted_post_id'] ) ? absint( $template_variables['converted_post_id'] ) : '';?>" />
			<input type="hidden" name="converted_vertical" value="<?php echo isset( $template_variables['converted_vertical'] ) ? esc_attr( $template_variables['converted_vertical'] ) : '';?>" />
			<input type="hidden" name="redirect" value="<?php echo esc_attr( $template_variables['redirect'] );?>" />
			<input type="hidden" name="referring_url" value="<?php echo isset( $template_variables['referring_url'] ) ? esc_url( $template_variables['referring_url'] ) : '';?>" />
			<?php wp_nonce_field( 'go_subscriptions_signup' ); ?>

			<ul>
				<li class="field-container email">
					<label for="email">Email address</label>
					<input type="email" name="email" value="<?php echo isset( $template_variables['email'] ) ? esc_attr( $template_variables['email'] ) : ''; ?>"/>
				</li>
				<li class="field-container company">
					<label for="company">Company name</label>
					<input type="text" name="company" value="<?php echo isset( $template_variables['company'] ) ? esc_attr( $template_variables['company'] ) : ''; ?>" />
				</li>
				<li class="field-container title">
					<label for="title">Title</label>
					<input type="text" name="title" value="<?php echo isset( $template_variables['title'] ) ? esc_attr( $template_variables['title'] ) : ''; ?>" />
				</li>
			</ul>

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
			<h2>Get a <strong>full year</strong> of unlimited emerging technology research and analysis for only <strong>$299</strong>.</h2>
			<p>With over 200 independent analysts in our network covering cloud, data, mobile, social, connected-consumer, and cleantech, Gigaom Research gives you the in-depth analysis you need to succeed.</p>
		</div>
	</div>

	<h3 class="skip-to-login">
		Already have an account?<br/>
		<a href="<?php echo wp_login_url(); ?>" class="sign-in-link sign-in">Sign in</a> now.
	</h3>
</div>

