<?php
/*
Plugin Name: bbPress New Topics
Plugin URI: http://bavotasan.com/2014/bbpress-new-topics-plugin/
Description: Displays a "new" label on topics that are unread or have unread replies for all keymasters, moderators and participants.
Author: c.bavota
Version: 1.0.1
Author URI: http://bavotasan.com
Text Domain: new-topics
Domain Path: /languages
License: GPL2
*/

/*  Copyright 2015  c.bavota  (email : cbavota@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Plugin version
if ( ! defined( 'NEW_TOPICS_VERSION' ) ) {
	define( 'NEW_TOPICS_VERSION', '1.0.1' );
}

//update_user_meta( 1, 'bbp_new_topics', array( 2895 ) );
if ( ! class_exists( 'BBP_New_Topics' ) ) {
    class BBP_New_Topics {
        /**
         * Construct the plugin object
         */
        public function __construct() {
            add_action( 'admin_init', array( $this, 'admin_init' ) );

            add_action( 'bbp_enqueue_scripts', array( $this, 'bbp_enqueue_scripts' ) );

			add_action( 'bbp_new_topic', array( $this, 'bbp_new_topic' ), 10, 4 );
			add_action( 'bbp_new_reply', array( $this, 'bbp_new_reply' ), 10, 5 );

			add_filter( 'bbp_get_topic_class', array( $this, 'bbp_get_topic_class' ), 10, 2 );

			add_action( 'bbp_theme_before_topic_title', array( $this, 'bbp_theme_before_topic_title' ) );
			add_action( 'bbp_template_before_single_topic', array( $this, 'bbp_template_before_single_topic' ) );
			add_action( 'bbp_theme_before_forum_title', array( $this, 'bbp_theme_before_forum_title' ) );
        }

        public function admin_init() {
            load_plugin_textdomain( 'new-topics', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }

        /**
         * Add stylesheet on bbPress pages
         *
         * This functions is attached to the 'bbp_enqueue_scripts' action hook
         *
         * @since 1.0.0
         */
        public function bbp_enqueue_scripts( $hook ) {
			wp_enqueue_style( 'bbp_new_topics', plugins_url( 'css/new-topics.css', __FILE__ ), '', NEW_TOPICS_VERSION );
        }

		/**
		 * Loops through all keymasters and moderators and creates an array
		 * of topic IDs for topics that are unread or have unread replies.
		 *
		 * @since 1.0.0
		 */
		public function bbp_register_newest_topic( $topic_id, $author ) {
			$bbp_admins = $this->bbp_get_admins();

			foreach ( $bbp_admins as $admin ) {
				if ( $author != $admin->ID ) {
					$new_topics_array = (array) get_user_meta( $admin->ID, 'bbp_new_topics', true );

					if ( ! in_array( $topic_id, array_filter( $new_topics_array ) ) ) {
						$new_topics_array[] = (int) $topic_id;

						update_user_meta( $admin->ID, 'bbp_new_topics', $new_topics_array );
					}
				}
			}
		}

		public function bbp_new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {
			$this->bbp_register_newest_topic( $topic_id, $topic_author );
		}

		public function bbp_new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
			$this->bbp_register_newest_topic( $topic_id, $reply_author );
		}

		/**
		 * Adds the class 'new-topic' to all topics that appear in the unread topics array.
		 *
		 * @since 1.0.0
		 */
		public function bbp_get_topic_class( $classes, $topic_id ) {
			$user_id = get_current_user_id();
			$new_topics_array = (array) get_user_meta( $user_id, 'bbp_new_topics', true );

		    if ( in_array( $topic_id, array_filter( $new_topics_array ) ) )
		        $classes[] = 'new-topic';

		    return $classes;
		}

		/**
		 * Adds 'New' label to all topics that appear in the unread topics array.
		 *
		 * @since 1.0.0
		 */
		public function bbp_theme_before_topic_title() {
			$user_id = get_current_user_id();
			$topic_id = bbp_get_topic_id();
			$new_topics_array = (array) get_user_meta( $user_id, 'bbp_new_topics', true );

		    if ( in_array( $topic_id, array_filter( $new_topics_array ) ) ) {
				echo '<span class="new-topic-notifier">' . __( 'New', 'new-topics' ) . '</span> ';
			}
		}

		/**
		 * Remove topic from unread topics array upon viewing.
		 *
		 * @since 1.0.1
		 */
		public function bbp_template_before_single_topic() {
			$user_id = get_current_user_id();
			$topic_id = bbp_get_topic_id();
			$new_topics_array = (array) get_user_meta( $user_id, 'bbp_new_topics', true );

		    if ( in_array( (int) $topic_id, $new_topics_array ) ) {
				unset( $new_topics_array[array_search( (int) $topic_id, $new_topics_array )] );

				update_user_meta( $user_id, 'bbp_new_topics', (array) $new_topics_array );
			}
		}


		/**
		 * Adds 'New' label to all forums that contain topics which appear in the unread topics array.
		 *
		 * @since 1.0.1
		 */
		public function bbp_theme_before_forum_title(){
			$user_id = get_current_user_id();
			$forum_id = bbp_get_forum_id();
			$new_topics_array = (array) get_user_meta( $user_id, 'bbp_new_topics', true );

			global $wpdb;
			$new_query = 'SELECT post_id FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = "_bbp_forum_id" AND meta_value = "' . $forum_id. '"';

			$result = $wpdb->get_results( $new_query );

			foreach( $result as $row ) {
				if ( in_array( (int) $row->post_id, array_filter( $new_topics_array ) ) ) {
					echo '<span class="new-topic-notifier">' . __( 'New', 'new-topics' ) . '</span> ';
				}
			}
		}

		/**
		 * Gathers all keymasters, moderators and participants.
		 *
		 * @since 1.0.0
		 */
		public function bbp_get_admins() {
            $keymaster = get_users( array(
                'role' => 'bbp_keymaster',
            ) );

            $moderators = get_users( array(
                'role' => 'bbp_moderator',
            ) );

            $participant = get_users( array(
                'role' => 'bbp_participant',
            ) );

            return array_merge( $keymaster, $moderators, $participant );
		}
    }
}
$bbp_new_topics = new BBP_New_Topics();