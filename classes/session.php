<?php

/**
 * Habari Session class
 * Manages sessions for the PHP session routines
 *
 * @package habari
 */

class Session
{
	/**
	 * Initialize the session handlers
	 */
	static function init()
	{
		session_set_save_handler(
			array( 'Session', 'open' ),
			array( 'Session', 'close' ),
			array( 'Session', 'read' ),
			array( 'Session', 'write' ),
			array( 'Session', 'destroy' ),
			array( 'Session', 'gc' )
		);
		register_shutdown_function( 'session_write_close' );
		if ( ! isset( $_SESSION ) ) {
			session_start();
		}
		return true;
	}

	/**
	 * Executed when opening a session.
	 * Not useful for Habari
	 */
	static function open( $save_path, $session_name )
	{
		// Does this function need to do anything?
		return true;
	}

	/**
	 * Executed when closing a session.
	 * Not useful for Habari
	 */
	static function close()
	{
		// Does this function need to do anything?
		return true;
	}

	/**
	 * Read session data from the database to return into the $_SESSION global.
	 * Verifies against a number of parameters for security purposes.
	 *
	 * @param string $session_id The id generated by PHP for the session.
	 * @return string The retrieved session.
	 */
	static function read( $session_id )
	{
		// for offline testing
		$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		// not always set, even by real browsers
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$session = DB::get_row( 'SELECT * FROM ' . DB::table( 'sessions' ) . ' WHERE token = ?', array( $session_id ) );

		// Verify session exists
		if ( !$session ) {
			return false;
		}

		$dodelete = false;

		if ( !defined( 'SESSION_SKIP_SUBNET' ) || SESSION_SKIP_SUBNET != true ) {
			// Verify on the same subnet
			$subnet = self::get_subnet( $remote_address );
			if ( $session->subnet != $subnet ) {
				$dodelete = true;
			}
		}

		// Verify expiry
		if ( time() > $session->expires ) {
			$dodelete = true;
		}

		// Verify User Agent
		if ( $user_agent != $session->ua ) {
			$dodelete = true;
		}

		// Let plugins ultimately decide
		$dodelete = Plugins::filter( 'session_read', $dodelete, $session, $session_id );

		if ( $dodelete ) {
			$sql = 'DELETE FROM ' . DB::table( 'sessions' ) . ' WHERE token = ?';
			$args = array( $session_id );
			$sql = Plugins::filter( 'sessions_clean', $sql, 'read', $args );
			DB::query( $sql, $args );
			return false;
		}

		// Do garbage collection, since PHP is bad at it
		$probability = ini_get( 'session.gc_probability' );
		// Allow plugins to control the probability of a gc event, return >=100 to always collect garbage
		$probability = Plugins::filter( 'gc_probability', ( is_numeric($probability) && $probability > 0 ) ? $probability : 1 );
		if( rand(1, 100) <= $probability ) {
			self::gc( ini_get( 'session.gc_maxlifetime' ) );
		}

		return $session->data;
	}

	/**
	 * Commit $_SESSION data to the database for this user.
	 *
	 * @param string $session_id The PHP-generated session id
	 * @param string $data Data from session stored as a string
	 */
	static function write( $session_id, $data )
	{
		// for offline testing
		$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		// not always set, even by real browsers
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		// Should we write this data?  Don't bother for search spiders, for example.
		$dowrite = true;
		$dowrite = Plugins::filter( 'session_write', $dowrite, $session_id, $data );

		if ( $dowrite ) {
			// DB::update() checks if the record key exists, and inserts if not
			$record = array(
				'subnet' => self::get_subnet( $remote_address ),
				'expires' => HabariDateTime::date_create( time() )->int + ini_get('session.gc_maxlifetime'),
				'ua' => $user_agent,
				'data' => $data,
			);
			DB::update(
				DB::table( 'sessions' ),
				$record,
				array( 'token' => $session_id )
			);
		}
	}

	/**
	 * Destroy stored session data by session id
	 *
	 * @param string $session_id The PHP generated session id
	 * @return
	 */
	static function destroy( $session_id )
	{
		$sql = 'DELETE FROM ' . DB::table( 'sessions' ) . ' WHERE token = ?';
		$args = array( $session_id );
		$sql = Plugins::filter( 'sessions_clean', $sql, 'destroy', $args );
		DB::query( $sql, $args );
		return true;
	}

	/**
	 * Session garbage collection deletes expired sessions
	 *
	 * @param mixed $max_lifetime Unused - The session expiration time, in seconds.
	 */
	static function gc( $max_lifetime )
	{
		$sql = 'DELETE FROM ' . DB::table( 'sessions' ) . ' WHERE expires < ?';
		$args = array( HabariDateTime::date_create( time() )->int );
		$sql = Plugins::filter( 'sessions_clean', $sql, 'gc', $args );
		DB::query( $sql, $args );
		return true;
	}

	/**
	 * Sets the user_id attached to the current session
	 *
	 * @param integer $user_id The user id of the current user
	 */
	static function set_userid( $user_id )
	{
		DB::query( 'UPDATE ' . DB::table( 'sessions' ) . ' SET user_id = ? WHERE token = ?', array( $user_id, session_id() ) );
	}


	/**
	 * Clear the user_id attached to sessions, delete other sessions that are associated to the user_id
	 * @param integer $user_id The user_id to clear.
	 */
	static function clear_userid( $user_id )
	{
		DB::query( 'DELETE FROM ' . DB::table( 'sessions' ) . ' WHERE user_id = ? AND token <> ?', array( $user_id, session_id() ) );
		DB::query( 'UPDATE ' . DB::table( 'sessions' ) . ' SET user_id = NULL WHERE token = ?', array( session_id() ) );
	}

	/**
	 * Adds a value to a session set
	 *
	 * @param string $set Name of the set
	 * @param mixed $value value to store
	 * @param string $key Optional unique key for the set under which to store the value
	 */
	static function add_to_set( $set, $value, $key = null )
	{
		if ( !isset( $_SESSION[$set] ) ) {
			$_SESSION[$set]= array();
		}
		if ( $key ) {
			$_SESSION[$set][$key]= $value;
		}
		else {
			$_SESSION[$set][]= $value;
		}
	}

	/**
	 * Store a notice message in the user's session
	 *
	 * @param string $notice The notice message
	 * @param string $key An optional id that would guarantee a single unique message for this key
	 */
	static function notice( $notice, $key = null )
	{
		self::add_to_set( 'notices', $notice, $key );
	}

	/**
	 * Store an error message in the user's session
	 *
	 * @param string $error The error message
	 * @param string $key An optional id that would guarantee a single unique message for this key
	 */
	static function error( $error, $key = null )
	{
		self::add_to_set( 'errors', $error, $key );
	}

	/**
	 * Return a set of messages
	 *
	 * @param string $set The name of the message set
	 * @param boolean $clear true to clear the messages from the session upon receipt
	 * @return array An array of message strings
	 */
	static function get_set( $set, $clear = true )
	{
		if ( !isset( $_SESSION[$set] ) ) {
			$set_array = array();
		}
		else {
			$set_array = $_SESSION[$set];
		}
		if ( $clear ) {
			unset( $_SESSION[$set] );
		}
		return $set_array;
	}

	/**
	 * Get all notice messsages from the user session
	 *
	 * @param boolean $clear true to clear the messages from the session upon receipt
	 * @return array And array of notice messages
	 */
	static function get_notices( $clear = true )
	{
		return self::get_set( 'notices', $clear );
	}

	/**
	 * Retrieve a specific notice from stored errors.
	 *
	 * @param string $key ID of the notice to retrieve
	 * @param boolean $clear true to clear the notice from the session upon receipt
	 * @return string Return the notice message
	 */
	static function get_notice( $key, $clear = true )
	{
		$notices = self::get_notices( false );
		if ( isset( $notices[$key] ) ) {
			$notice = $notices[$key];
			if ( $clear ) {
				self::remove_notice( $key );
			}
			return $notice;
		}
	}

	/**
	 * Get all error messsages from the user session
	 *
	 * @param boolean $clear true to clear the messages from the session upon receipt
	 * @return array And array of error messages
	 */
	static function get_errors( $clear = true )
	{
		return self::get_set( 'errors', $clear );
	}

	/**
	 * Retrieve a specific error from stored errors.
	 *
	 * @param string $key ID of the error to retrieve
	 * @param boolean $clear true to clear the error from the session upon receipt
	 * @return string Return the error message
	 */
	static function get_error( $key, $clear = true )
	{
		$errors = self::get_errors( false );
		if ( isset( $errors[$key] ) ) {
			$error = $errors[$key];
			if ( $clear ) {
				self::remove_error( $key );
			}
			return $error;
		}
	}

	/**
	 * Removes a specific notice from the stored notices.
	 *
	 * @param string $key ID of the notice to remove
	 * @return boolean True or false depending if the notice was removed successfully.
	 */
	static function remove_notice( $key )
	{
		unset( $_SESSION['notices'][$key] );
		return ( !isset( $_SESSION['notices'][$key] ) ? true : false );
	}

	/**
	 * Removes a specific error from the stored errors.
	 *
	 * @param string $key ID of the error to remove
	 * @return boolean True or false depending if the error was removed successfully.
	 */
	static function remove_error( $key )
	{
		unset( $_SESSION['errors'][$key] );
		return ( !isset( $_SESSION['errors'][$key] ) ? true : false );
	}

	/**
	 * Return output of notice and error messages
	 *
	 * @param boolean $clear true to clear the messages from the session upon receipt
	 * @param array $callback a reference to a callback function for formatting the the messages
	 * @return mixed output of messages
	 */
	static function messages_get( $clear = true, $callback = null )
	{
		$errors = self::get_errors( $clear );
		$notices = self::get_notices( $clear );

		// if callback is 'array', then just return the raw data
		if ( $callback == 'array' ) {
			$output = array_merge( $errors, $notices );
		}
		// if a function is passed in $callback, call it
		else if ( isset( $callback ) && is_callable( $callback ) ) {
			$output = call_user_func( $callback, $notices, $errors );
		}
		// default to html output
		else {
			$output = Format::html_messages( $notices, $errors );
		}

		return $output;
	}

	/**
	 * Output notice and error messages 
	 *
	 * @param boolean $clear true to clear the messages from the session upon receipt
	 * @param boolean $use_humane_msg true to use the humane messages system, false
	 * to use and unordered list
	 */
	static function messages_out( $clear = true, $callback = null )
	{
		echo self::messages_get( $clear, $callback );
	}

	/**
	 * Determine if there are messages that should be displayed
	 * Messages are not cleared when calling this function.
	 *
	 * @return boolean true if there are messages to display.
	 */
	static function has_messages()
	{
		return ( count( self::get_notices( false ) + self::get_errors( false ) ) ) ? true : false;
	}

	/**
	 * Determine if there are error messages to display
	 *
	 * @param string $key Optional key of the unique error message
	 * @return boolean true if there are errors, false if not
	 */
	static function has_errors( $key = null )
	{
		if ( isset( $key ) ) {
			return isset( $_SESSION['errors'][$key] );
		}
		else {
			return count( self::get_errors( false ) ) ? true : false;
		}
	}
	
	protected static function get_subnet( $remote_address = '' ) {
		
		$long_addr = ip2long( $remote_address );
		
		if ( $long_addr >= ip2long('0.0.0.0') && $long_addr <= ip2long('127.255.255.255') ) {
			// class A
			return sprintf("%u", $long_addr) >> 24;
		}
		else if ( $long_addr >= ip2long('128.0.0.0') && $long_addr <= ip2long('191.255.255.255') ) {
			// class B
			return sprintf("%u", $long_addr) >> 16;
		}
		else {
			// class C, D, or something we missed
			return sprintf("%u", $long_addr) >> 8;
		}
		
	}

}

?>
