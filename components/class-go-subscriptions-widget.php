<?php

class GO_Subscriptions_Widget extends WP_Widget
{
	public function __construct()
	{
		parent::__construct(
			'go_subscriptions_widget',
			'GO Subscription Form',
			array(
				'description' => __( 'Provides Pro subscription/sign-up forms', 'go_subscriptions' ),
			)
		);
	}//end __construct

	public function form( $instance )
	{
		if ( isset( $instance['title'] ) )
		{
			$title = $instance['title'];
		}
		else
		{
			$title = __( 'Subscription Form', 'go_subscriptions' );
		}

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}//end form

	public function update( $new_instance, $unused_old_instance )
	{
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}//end update

	public function widget( $args, $instance )
	{
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo go_subscriptions()->get_subscription_form( $args );
	}//end widget
}//end class
