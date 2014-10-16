<div id="subscribe" class="thanks ok">
	<p class="subtext">Your account is now activated. Please check your email for your subscription confirmation.</p>
	<div class="thanks-primary">
		<div id="thanks-video" class="flex-video">
			<iframe src="//player.vimeo.com/video/77229848?byline=0&amp;portrait=0" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
			<p><a href="http://vimeo.com/77229848">Gigaom Research Tour</a> from <a href="http://vimeo.com/user14399801">Gigaom</a> on <a href="https://vimeo.com">Vimeo</a>.</p>
		</div>

		<p class="continue">
			<?php
			// give others a chance to override the button CTA
			$cta_contents = array(
				'text'  => 'Continue to home page',
				'url'   => home_url( '/' ),
				'class' => 'primary',
				'sub_text_html' => '', // what goes below the button
			);

			$cta_contents = apply_filters( 'go_subscriptions_thankyoucta', $cta_contents, get_current_user_id() );
			?>
			<a class="button <?php echo $cta_contents['class']; ?>" title="<?php echo $cta_contents['text']; ?>" href="<?php echo $cta_contents['url']; ?>"><?php echo $cta_contents['text']; ?></a>
			<?php echo $cta_contents['sub_text_html']; ?>
		</p>
	</div>
	<div id="thanks-actions">
		<?php
		if ( function_exists( 'go_oauth' ) )
		{
			?>
			<div class="thanks-block thanks-connect">
				<p>
					Make logging in easier by connecting to:
				</p>
				[goauth_get_connect_buttons action=login_connect class=social-login-buttons]
			</div>
			<?php
		}//end if

		?>
		<div class="thanks-block thanks-follow">
			Follow us on <a href="https://twitter.com/gigaomresearch">Twitter</a>,
			subscribe via <a href="http://accounts.gigaom.com/newsletters/">email</a>
			or <a href="http://research.gigaom.com/feeds/">RSS feed</a>.
		</div>
		<div class="thanks-block thanks-help">
			Need Help? Send us an <a href="mailto:research-support@gigaom.com">email</a>.
		</div>
	</div>
</div>
<?php
do_action( 'go_subscription_thankyou_page' );
