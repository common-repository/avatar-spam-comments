<?php
/*
Plugin Name: Avatar Spam Comments
Plugin URI: http://tito.pandubrahmanto.com/
Description: Add an extra filter to the comments moderation screen (placed where indicated in the screenshot) for commenters that have an avatar that have been marked as spam.
Version: 1.0
Author: Tito Pandu Brahmanto
Author URI: http://tito.pandubrahmanto.com/
License: TODO
*/

if( ! defined( 'AVSPCM_VERSION' ) ) {
    define( 'AVSPCM_VERSION', '1.0' );
} // end if

/**
* AvatarSpamComments
*/
class AvatarSpamComments
{
	/*--------------------------------------------*
     * Constructor
     *--------------------------------------------*/

    /**
     * Static property to hold our singleton instance
     *
     * @since   1.0
     */
    static $instance = false;

    /**
     * Instance emailHaveGravatarArray
     *
     * @since   1.0
     */
    private $emailHaveGravatarArray = array();

	function __construct()
	{
		add_action( 'current_screen', array( $this, 'commentsLazyHook' ), 10, 2 );
	}

	/**
     * If an instance exists, this returns it.  If not, it creates one and
     * retuns it.
     *
     * @since   1.0
     */
    public static function getInstance() 
    {

        if ( !self::$instance ) {
            self::$instance = new self;
        } // end if

        return self::$instance;

    } // end getInstance

    /**
     * Only show comments that author has gravatar.
     *
     * @param  array  $clauses
     * @param  object $wp_comment_query
     * @return array
     */
    public function listSpamCommentsHasGravatar( $clauses, $wp_comment_query )
    {
        $emailHaveGravatar = $this->emailHaveGravatarArray;
        $last_key = end(array_keys($emailHaveGravatar));

        global $wpdb;

        $clauses['where'] .= $wpdb->prepare( ' AND (', null );
        foreach ($emailHaveGravatar as $key => $email) {
            if ($key == $last_key) {
                // last element
                $clauses['where'] .= $wpdb->prepare( ' comment_author_email = %s', $email );
            }
            else
            {
                $clauses['where'] .= $wpdb->prepare( ' comment_author_email = %s OR', $email );
            }
        }
        
        $clauses['where'] .= $wpdb->prepare( ' )', null );

        return $clauses;
    }

    /**
     * Add link to specific post comments with counter
     */
    public function avatarSpamCommentsPageLink( $status_links )
    {
        add_filter( 'comments_clauses', array( $this, 'listSpamCommentsHasGravatar' ), 10, 2 );
        $count = get_comments( 'status=spam&count=1' );
        $counter = ($count) ? $count : 0;

        if( isset( $_GET['filter_spam_avatar'] ) ) 
        {
            $status_links['all'] = '<a href="edit-comments.php?comment_status=all">All</a>';
            $status_links['spam'] = str_replace('class="current"', "", $status_links['spam']);
            $status_links['spam_with_avatar'] = '<a href="edit-comments.php?comment_status=spam&filter_spam_avatar=1" class="current">Spam with Avatar <span class="count">(<span class="spam-count">'.$counter.'</span>)</span></a>';
        } 
        else 
        {
            $status_links['spam_with_avatar'] = '<a href="edit-comments.php?comment_status=spam&filter_spam_avatar=1">Spam with Avatar <span class="count">(<span class="spam-count">'.$counter.'</span>)</span></a>';
        }

        return $status_links;
    }

    /**
     * Checks to see if the specified email address has a Gravatar image.
     *
     * @param   $email_address  The email of the address of the user to check
     * @return                    Whether or not the user has a gravatar
     * @since   1.0
     */
    public function hasGravatar( $email_address ) 
    {

        // Build the Gravatar URL by hasing the email address
        $url = 'http://www.gravatar.com/avatar/' . md5( strtolower( trim ( $email_address ) ) ) . '?d=404';

        // Now check the headers...
        if ( ini_get( 'allow_url_fopen' ) ) {
            $headers = @get_headers( $url );
        } else {
            $ch = curl_init( $url );

            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_HEADER, true );
            curl_setopt( $ch, CURLOPT_NOBODY, true );

            $content = curl_exec( $ch );

            curl_close( $ch );

            $headers = array($content);
        }
        

        // If 200 is found, the user has a Gravatar; otherwise, they don't.
        return preg_match( '|200|', $headers[0] ) ? true : false;

    } // end example_hasGravatar

    public function emailHaveGravatar()
    {
        $commentsQuery = new WP_Comment_Query;
        $spams = $commentsQuery->query( array('status' => 'spam') );
        $authorEmailHaveGravatar = array();
        foreach ($spams as $key => $spam) {
            if ( $this->hasGravatar( $spam->comment_author_email ) ) 
            {
                $authorEmailHaveGravatar[] = $spam->comment_author_email;
            }
        }
        return $authorEmailHaveGravatar;
    }

    /**
     * Delay hooking our clauses filter to ensure it's only applied when needed.
     */
    public function commentsLazyHook( $screen )
    {

        if ( $screen->id != 'edit-comments' )
            return;

        // With session
        $this->emailHaveGravatarArray = $this->emailHaveGravatar();
        // Check if our Query Var is defined    
        if( isset( $_GET['filter_spam_avatar'] ) )
        {
            add_filter( 'comments_clauses', array( $this, 'listSpamCommentsHasGravatar' ), 10, 2 );
        }

        add_filter( 'comment_status_links', array( $this, 'avatarSpamCommentsPageLink' ) );

    } // end commentsLazyHook
}

/**
 * Instantiates the plugin using the plugins_loaded hook and the
 * Singleton Pattern.
 */
function AvatarSpamComments() {
    AvatarSpamComments::getInstance();
} // end AvatarSpamComments
add_action( 'plugins_loaded', 'AvatarSpamComments' );
