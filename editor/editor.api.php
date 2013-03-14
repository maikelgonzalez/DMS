<?php

/*
 * Storefront should fetch and organize the latest items from the PL store.
 * It extends the PageLines API class and has access to its methods.
 **/
class EditorStoreFront extends PageLinesAPI {

	function __construct(){
		$this->data_url = $this->base_url . '/v4/all';
		$this->username = get_pagelines_credentials( 'user' );
		$this->password = get_pagelines_credentials( 'pass' );
		$this->bootstrap();
	}

	/**
	 *
	 *  Bootstrap draft data, must load before page does.
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function bootstrap(){
		global $pldraft;
		if( is_object( $pldraft ) && 'draft' == $pldraft->mode )
			$this->get_latest();
	}

	/**
	 *
	 *  Get all store data for json head data.
	 *  @TODO make paginated??
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function get_latest(){

			$data = $this->get( 'store_mixed', array( $this, 'json_get' ), array( $this->data_url ) );
			return $this->sort( $this->make_array( json_decode( $data ) ) );
	}

	/**
	 *
	 *  Unused as yet.
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function sort( $data ){

		return $data;
	}
}

/*
 * This class handles all interaction with the PageLines APIs
 * !IMPORTANT - This class can be EXTENDED by sub classes that use the API. e.g. the store, account management, etc..
 **/
class PageLinesAPI {

	var $prot = array( 'https://', 'http://' );
	var $base_url = 'api.pagelines.com';

	/**
	 *
	 *  Write cache data
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function put( $data, $id, $timeout = 3600 ) {
		if( $data && $id )
			set_transient( sprintf( 'plapi_%s', $id ), $data, $timeout );
	}

	/**
	 *
	 *  Get cache data
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function get( $id, $callback = false, $args = array() ){

		$data = '';

		if( false === ( $data = get_transient( sprintf( 'plapi_%s', $id ) ) ) && $callback ) {

			$data = call_user_func_array( $callback, $args );
			if( '' != $data )
				$this->put( $data, $id );
		}
		return $data;
	}
	/**
	 *
	 *  Delete cache data
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function del( $id ) {
		delete_transient( sprintf( 'plapi_%s', $id ) );
	}

	/**
	 *
	 *  Make sure something is an array.
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function make_array( $data ) {

		if( is_array( $data ) )
			return $data;

		if( is_object( $data ) )
			return json_decode( json_encode( $data ), true );

		return array();
	}

	/**
	 *
	 *  Fetch remote json from API server.
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function json_get( $url ) {

		$options = array(
			'timeout'	=>	15,
			'body' => array(
				'username'	=>	( $this->username != '' ) ? $this->username : false,
				'password'	=>	( $this->password != '' ) ? $this->password : false,
			)
		);
		$f  = wp_remote_retrieve_body( $this->try_api( $url, $options ) );
		return $f;
	}

	/**
	 *
	 *  Get remote object with POST.
	 *
	 *  @package PageLines Framework
	 *  @since 3.0
	 */
	function try_api( $url, $args ) {

		$defaults = array(
			'sslverify'	=>	false,
			'timeout'	=>	5,
			'body'		=> array()
		);
		$options = wp_parse_args( $args, $defaults );

		foreach( $this->prot as $type ) {
			// sometimes wamp does not have curl!
			if ( $type === 'https://' && ! function_exists( 'curl_init' ) )
				continue;
			$r = wp_remote_post( $type . $url, $options );
			if ( !is_wp_error($r) && is_array( $r ) ) {
				return $r;
			}
		}
		return false;
	}
}

// API wrapper functions.

/**
 *  Get data from cache.
 *
 *  @package PageLines Framework
 *	@since 3.0
 */
function pl_cache_get( $id, $callback = false, $args = array() ) {
	global $storeapi;
	if( is_object( $storeapi ) )
		return $storeapi->get( $id, $callback, $args );
	else
		return false;
}

/**
 *  Write data to cache.
 *
 *  @package PageLines Framework
 *	@since 3.0
 */
function pl_cache_put( $data, $id, $time = 3600 ) {
	global $storeapi;
	if( $id && $data && is_object( $storeapi ) )
		$storeapi->put( $data, $id, $time );
}

/**
 *  Delete from cache.
 *
 *  @package PageLines Framework
 *	@since 3.0
 */
function pl_cache_del( $id ) {
	delete_transient( sprintf( 'plapi_%s', $id ) );
}

/**
 *  Clear draft caches.
 *
 *  @package PageLines Framework
 *	@since 3.0
 */
function pl_flush_draft_caches() {

	$caches = array( 'draft_core_raw', 'draft_core_compiled', 'draft_sections_compiled' );
	foreach( $caches as $key ) {
		pl_cache_del( $key );
	}
}