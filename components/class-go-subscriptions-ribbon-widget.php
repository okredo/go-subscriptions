<?php

class GO_Subscriptions_Ribbon_Widget extends WP_Widget
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$widget_ops = array(
			'classname'   => 'widget-go-subscriptions-ribbon',
			'description' => __( 'Subscription signup ribbon CTA' ),
		);

		parent::__construct( 'go-subscriptions-ribbon', __( 'GO Subscription Ribbon' ), $widget_ops );
	} // END __construct

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $unused_args Widget arguments.
	 * @param array $unused_instance Saved values from database.
	 */
	public function widget( $unused_args, $unused_instance )
	{
		// do not show the ribbon on pages.  This may need to get reviewed, but this avoids showing the ribbon on top of sign up
		if ( is_page() )
		{
			return;
		}

		// if the user is logged in and has an active subscription or can edit posts, hide the ribbon
		if ( is_user_logged_in() && ( current_user_can( 'subscriber' ) || current_user_can( 'edit_posts' ) ) )
		{
			return;
		}

		$cta_text = current_user_can( 'did_trial' ) ? 'Subscribe now' : 'Sign up for a free 7 day trial';

		?>
		<div id="subscription-ribbon" class="go-subscriptions-signup-button row">
			<div class="small-12 columns">
				<div id="subscription-text">Gigaom Research is a premium subscription site</div>
				<div class="ribbon-link"><a href="/subscription/sign-up/" class="go-subscriptions-signup-link"><?php echo $cta_text; ?></a></div>
			</div>
		</div>
		<?php
	}// end widget
}// end class
