<?php

class GO_Subscriptions_Test extends WP_UnitTestCase
{
	public function setUp()
	{
		// clear WP's object caches
		$this->flush_cache();

		// clear memcache if enabled
		$save_handler = ini_get( 'session.save_handler' );
		$save_path = ini_get( 'session.save_path' );

		try
		{
			if ( ! $save_path )
			{
				$save_path = 'tcp://127.0.0.1:11211';
			}

			$memcache = new Memcache;

			$save_path = str_replace( 'tcp://', '', $save_path );
			$save_path = explode( ':', $save_path );

			$memcache->connect( $save_path[0], $save_path[1] );
			$memcache->flush();
		}
		catch( Exception $e )
		{
			var_dump( $e );
		}//END catch

		// override go-subscriptions' configuration. because the way it's
		// loaded by go-subscriptions, we can't just use our own filter,
		// but have to set the singleton's config class var
		go_subscriptions()->config['subscriptions_blog_id'] = 4;
	}//END setUp

	/**
	 * baseline test just to make sure we can even bring up the singleton
	 */
	public function test_singleton()
	{
		$this->assertTrue( is_object( go_subscriptions() ) );
	}//END test_singleton

	/**
	 * test the get_converted_url() function
	 */
	public function test_get_converted_url()
	{
		$user = $this->create_user(
			array(
				'user_nicename' => 'pacman',
				'user_login' => 'pacman',
				'user_email' => 'pacman_testtest@gigaom.com',
			)
		);

		$this->assertTrue( FALSE !== $user );

		go_subscriptions()->update_converted_meta(
			$user->ID,
			array(
				'converted_post_id' => 218186,
				'converted_vertical' => 'mobile',
			)
		);

		// set up the phpunit test's db with David Card's report:
		// "Sizing the EU app economy"
		// http://research.gigaom.com/report/sizing-the-eu-app-economy/
		$this->seed_test_db();

		$converted_url = go_subscriptions()->get_converted_url( $user );

		$this->assertFalse( empty( $converted_url ) );
		$this->assertTrue( 0 < strpos( $converted_url, '218186' ) );
		$this->assertTrue( 0 < stripos( $converted_url, 'research' ) );
	}//END test_get_converted_url

	private function create_user( $args )
	{
		if ( $user = get_user_by( 'slug', $args['user_nicename'] ) )
		{
			return $user;
		}
		else
		{
			$user_id = wp_insert_user( $args );
			if ( is_wp_error( $user_id ) )
			{
				var_dump( $user_id );
				return FALSE;
			}
			return get_user_by( 'id', $user_id );
		}
	}//END create_user

	// set up our test db with a Research report
	private function seed_test_db()
	{
		// add data to the Research blog
		switch_to_blog( 4 );

		global $wpdb;
		$wpdb->query( 'CREATE TABLE IF NOT EXISTS ' . $wpdb->posts . ' LIKE wp_4_posts' );

		$res = $wpdb->get_row( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE ID = 218186' );
		if ( NULL === $res )
		{
			$wpdb->query( 'INSERT INTO ' . $wpdb->posts . ' ( SELECT * FROM wp_4_posts WHERE ID = 218186 )' );
		}

		$res = $wpdb->get_row( 'SELECT option_id FROM ' . $wpdb->options . ' WHERE option_name = "permalink_structure"' );
		if ( NULL === $res )
		{
			$wpdb->query( 'INSERT INTO ' . $wpdb->options . ' VALUES( NULL, "permalink_structure", "/%year%/%monthnum%/%postname%/", "yes" )' );
		}

		$res = $wpdb->get_row( 'SELECT option_id FROM ' . $wpdb->options . ' WHERE option_name = "home"' );
		if ( NULL === $res )
		{
			$wpdb->query( 'INSERT INTO ' . $wpdb->options . ' VALUES( NULL, "home", "http://research.local.test.org", "yes" )' );
		}

		$res = $wpdb->get_row( 'SELECT option_id FROM ' . $wpdb->options . ' WHERE option_name = "siteurl"' );
		if ( NULL === $res )
		{
			$wpdb->query( 'INSERT INTO ' . $wpdb->options . ' VALUES( NULL, "siteurl", "http://research.local.test.org", "yes" )' );
		}

		restore_current_blog();
	}//END seed_test_db
}//END class