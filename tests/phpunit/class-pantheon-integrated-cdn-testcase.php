<?php
/**
 * Common testcase abstractions.
 *
 * @package Pantheon_Integrated_CDN
 */

use Pantheon_Integrated_CDN\Emitter;

/**
 * Class from which all tests inherit.
 */
class Pantheon_Integrated_CDN_Testcase extends WP_UnitTestCase {

	/**
	 * Surrogate keys cleared by the Purger (reported on an action).
	 *
	 * @var array
	 */
	protected $cleared_keys = array();

	/**
	 * Mapping of views to their surrogate keys.
	 *
	 * @var array
	 */
	protected $view_surrogate_keys = array();

	/**
	 * Sets up the testcase.
	 */
	public function setUp() {
		parent::setUp();

		$this->setup_permalink_structure();

		$this->user_id1 = $this->factory->user->create( array( 'user_role' => 'author', 'user_nicename' => 'first-user' ) );
		$this->user_id2 = $this->factory->user->create( array( 'user_role' => 'author', 'user_nicename' => 'second-user' ) );
		$this->user_id3 = $this->factory->user->create( array( 'user_role' => 'author', 'user_nicename' => 'third-user' ) );

		$this->tag_id1 = $this->factory->tag->create( array( 'slug' => 'first-tag' ) );
		$this->tag_id2 = $this->factory->tag->create( array( 'slug' => 'second-tag' ) );
		$this->category_id1 = 1; // This is the default 'uncategorized' category.
		$this->category_id2 = $this->factory->category->create( array( 'slug' => 'second-category' ) );

		$this->post_id1 = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_author'   => $this->user_id1,
			'post_date'     => '2016-10-14 12:00',
			'post_date_gmt' => '2016-10-14 12:00',
			'post_name'     => 'first-post',
		) );
		wp_set_object_terms( $this->post_id1, array( $this->tag_id2 ), 'post_tag' );
		$this->post_id2 = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_author'   => $this->user_id2,
			'post_date'     => '2016-10-14 11:00',
			'post_date_gmt' => '2016-10-14 11:00',
			'post_name'     => 'second-post',
		) );
		$this->post_id3 = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_author'   => $this->user_id2,
			'post_date'     => '2016-10-15 11:00',
			'post_date_gmt' => '2016-10-15 11:00',
			'post_name'     => 'third-post',
		) );
		$this->page_id1 = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_type'     => 'page',
			'post_author'   => $this->user_id1,
			'post_name'     => 'first-page',
		) );
		$this->cleared_keys = array();

		// Primes the mapping of views to their surrogate keys.
		$views = array(
			home_url( '/' ), // Homepage.
			get_permalink( $this->post_id1 ), // Single post.
			get_permalink( $this->post_id2 ), // Single post.
			get_permalink( $this->post_id3 ), // Single post.
			get_permalink( $this->page_id1 ), // Single page.
			get_term_link( $this->tag_id1 ), // Single term.
			get_term_link( $this->tag_id2 ), // Single term.
			get_term_link( $this->category_id1 ), // Single term.
			get_author_posts_url( $this->user_id1 ), // Single author.
			get_author_posts_url( $this->user_id2 ), // Single author.
			get_author_posts_url( $this->user_id3 ), // Single author.
			'/2016/10/14/', // Day archive with posts.
			'2015/10/15/', // Day archive without posts.
			'/2016/10/', // Month archive with posts.
			'/2015/10/', // Month archive without posts.
			'/2016/', // Year archive with posts.
			'/2015/', // Year archive without posts.
		);
		foreach ( $views as $view ) {
			$path = parse_url( $view, PHP_URL_PATH );
			$this->go_to( $view );
			$this->view_surrogate_keys[ $path ] = Emitter::get_surrogate_keys();
		}

		add_action( 'pantheon_integrated_cdn_clear_keys', array( $this, 'action_pantheon_integrated_cdn_clear_keys' ) );
	}

	/**
	 * Hooks into the 'pantheon_integrated_cdn_clear_keys' to listen to cleared keys.
	 *
	 * @param array $keys Surrogate keys being cleared.
	 */
	public function action_pantheon_integrated_cdn_clear_keys( $keys ) {
		$this->cleared_keys = $keys;
	}

	/**
	 * Sets up the permalink structure so our tests have pretty permalinks.
	 */
	private function setup_permalink_structure() {
		global $wp_rewrite;

		$structure = '%year%/%monthnum%/%day%/%postname%/';
		update_option( 'permalink_structure', $structure );

		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );

		create_initial_taxonomies();

		$wp_rewrite->flush_rules();
	}

	/**
	 * Assert cleared keys to match the expected set.
	 *
	 * @param array $expected Surrogate keys expected to be cleared.
	 */
	protected function assertClearedKeys( $expected ) {
		$this->assertArrayValues( $expected, $this->cleared_keys );
	}

	/**
	 * Assert URIs purged by cleared keys to match the expected set.
	 *
	 * @param array $expected URIs expected to be cleared based on cleared keys.
	 */
	protected function assertPurgedURIs( $expected ) {
		$actual = array();
		foreach ( $this->view_surrogate_keys as $view => $keys ) {
			if ( array_intersect( $keys, $this->cleared_keys ) ) {
				$actual[] = $view;
			}
		}
		$this->assertArrayValues( $expected, $actual );
	}

	/**
	 * Assert expected array values to match actual array values.
	 *
	 * Improves upon assertEquals by ensuring arrays are in similar order.
	 *
	 * @param array $expected Expected array values.
	 * @param array $actual Actual array values.
	 */
	protected function assertArrayValues( $expected, $actual ) {
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tear down behaviors after the tests have completed.
	 */
	public function tearDown() {
		$this->cleared_keys = array();
		remove_action( 'pantheon_integrated_cdn_clear_keys', array( $this, 'action_pantheon_integrated_cdn_clear_keys' ) );
		parent::tearDown();
	}

}