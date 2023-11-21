<?php

namespace articlefeedbackpe;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AFPE_Main {
    /**
     * @var array|false|string
     */
    private $plugin_domain;

    /**
     * @var array|false|string
     */
    private $version;

    /**
     * @since    1.0.0
     * @access   private
     * @var array|false|string
     */
    private $plugin_name;

    /**
     *
     */
    public function __construct() {
        $this->plugin_domain    = 'article-feedback-pe';
        $this->version          = '1.0.1';
        $this->plugin_name      = 'Article Feedback by Projects Engine';

        if ( is_admin() ) {
			add_action( 'load-post.php',     array( $this, 'meta_boxes' ) );
			add_action( 'load-post-new.php', array( $this, 'meta_boxes' ) );
		}

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        $this->run();
    }

    public function register_routes() {
        register_rest_route( $this->plugin_domain . '/v1', '/voting', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'voting' ),
            'validate_callback' => function() {
                return true;
            },
            'permission_callback' => '__return_true',
        ));

        register_rest_route( $this->plugin_domain . '/v1', '/voting-results', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'voting_results' ),
            'validate_callback' => function() {
                return true;
            },
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Add and save meta box hooks.
     *
	 * @return void
	 */
	public function meta_boxes() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
	}

    /**
     * Add meta box.
     *
	 * @param $post_type
     * @param $post
	 *
	 */
	public function add_meta_box( $post_type, $post ) {
		// Limit meta box to certain post types.

		if ( 'post' === $post_type) {
			add_meta_box(
				'voting_details',
				__( 'Voting details', $this->plugin_domain ),
				array( $this, 'render_voting_meta_box_content' ),
				$post_type,
				'advanced',
				'high',
				array( 'id' => 'voting_details', 'class' => 'voting-details' )
			);
		}
	}

    	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_voting_meta_box_content( $post, $meta_box ) {
        $lastModified = $post->post_modified;
		$id = $meta_box['args']['id'];
		$class = $meta_box['args']['class'];

		// Add a nonce field, so we can check for it later.
		wp_nonce_field( 'voting_details_box', 'voting_details_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
        $yes = get_post_meta( $post->ID, '_helpful_yes', true ) ?: 0;
        $no = get_post_meta( $post->ID, '_helpful_no', true ) ?: 0;

        $totalVotes = intval( $yes ) + intval( $no );

        $totalYes = ( 0 !== $totalVotes ) ? intval($yes / $totalVotes * 100 ) : 0;
        $totalNo = ( 0 !== $totalVotes ) ? intval( $no / $totalVotes * 100 ) : 0;

		// Display the form, using the current value.
		?>

		<div class="c-voting <?php echo $class; ?>">
            <div class="c-voting__inner">
                <span>Yes: <?php echo  $totalYes .'%'; ?></span>
                <span>No: <?php echo  $totalNo .'%'; ?></span>
            </div>
		</div>

		<?php
	}

    /**
     * Helpful article.
     *
     * @param WP_REST_Request $request
     * @return void
     */
    function voting( WP_REST_Request $request ) {
        $post = $request->get_params();
        $id = $post['post_id'];
        $answer = $post['answer'];

        $helpful = get_post_meta( $id, '_helpful_' . $answer, true );

        ( $helpful ) ?
            update_post_meta( $id, '_helpful_' . $answer, ++$helpful ) :
            add_post_meta( $id, '_helpful_' . $answer, 1 );

        $yes = get_post_meta( $id, '_helpful_yes', true ) ?: 0;
        $no = get_post_meta( $id, '_helpful_no', true ) ?: 0;

        $totalVotes = intval( $yes ) + intval( $no );

        $totalYes = ( 0 !== $totalVotes ) ? intval($yes / $totalVotes * 100 ) : 0;
        $totalNo = ( 0 !== $totalVotes ) ? intval( $no / $totalVotes * 100 ) : 0;

        setcookie( 'voting_done', 1, time() + 3600, '/', '', false, false );

        return new WP_REST_Response( [
            'response' 	=> true, 
            'message' 	=> __( 'Yes ' . $totalYes . '% No ' . $totalNo . '%', 'projectsengine' ),
            'class' 	=> 'alert-error',
        ] );
    }

        /**
     * Helpful article.
     *
     * @param WP_REST_Request $request
     * @return void
     */
    function voting_results( WP_REST_Request $request ) {
        $post   = $request->get_params();
        $id     = $post['post_id'];

        $yes = get_post_meta( $id, '_helpful_yes', true ) ?: 0;
        $no = get_post_meta( $id, '_helpful_no', true ) ?: 0;

        $totalVotes = intval( $yes ) + intval( $no );

        $totalYes = ( 0 !== $totalVotes ) ? intval($yes / $totalVotes * 100 ) : 0;
        $totalNo = ( 0 !== $totalVotes ) ? intval( $no / $totalVotes * 100 ) : 0;

        return new WP_REST_Response( [
            'response' 	=> true, 
            'message' 	=> __( 'Yes ' . $totalYes . '% No ' . $totalNo . '%', 'projectsengine' ),
            'class' 	=> 'alert-error',
        ] );
    }

    public function run() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_filter( 'the_content', array( $this, 'add_voting_content' ) );
    }

    /**
     * Public scripts.
     *
     * @return void
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 'voting-style', plugins_url('../../assets/css/public.css', __FILE__), array() );
        wp_enqueue_script( 'voting-script', plugins_url( '../../assets/js/public.js', __FILE__ ), array(),  $this->version, array( 'strategy' => 'async', 'in_footer' => true ) );
        wp_localize_script( 'voting-script', 'voting_pe', array(
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'namespace' => $this->plugin_domain . '/v1',
            'post_id'   => get_queried_object_id()
        ) );
    }

    /**
     * Admin scripts and styles.
     *
     * @return void
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_style( 'voting-admin-style', plugins_url('../../assets/css/admin.css', __FILE__), array() );
        wp_enqueue_script( 'voting-admin-script', plugins_url( '../../assets/js/admin.js', __FILE__ ), array(),  $this->version, array( 'strategy' => 'async', 'in_footer' => true ) );
    }

    public function add_voting_content( $content ) {
        $votingContent = '';

        if( is_single() ) {
            ob_start(); ?>

            <div class="c-question-v1">
                <div class="c-question__inner">
                    <div class="c-question__title-v1">Was this article helpful?</div>
                    <div class="c-question__answers" id="question__answers">
                        <button alt="Was this article helpful?" data-answer="yes" class="-green">
                            <img src="/wp-content/plugins/article-feedback-pe/assets/img/smiley.png" alt="Was this article helpful?" title="Was this article helpful?"/>
                        </button>
                        <button alt="Was this article helpful?" data-answer="no">
                            <img src="/wp-content/plugins/article-feedback-pe/assets/img/sad.png" alt="Was this article helpful?" title="Was this article helpful?"/>
                        </button>
                    </div>
                    <div class="c-question__loader"></div>
                </div>
            </div>

            <?php

            $votingContent = ob_get_contents();
            ob_get_clean();
        }

        return $content . $votingContent;
    }
}