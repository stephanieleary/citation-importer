<?php

// Add the following code (or similar) to a new plugin file:

add_filter( 'citation_importer_fielddata', 'filter_citation_importer_source_fields', 10, 3 );

function filter_citation_importer_source_fields( $fields, $post, $item ) {
	
	// Reset the source to the journal/book title only
	$fields['source'] = sanitize_text_field( $item['container-title'][0] );
	
	// Add separate fields for the volume, issue, and page numbers
	if ( isset( $item['volume'] ) && !empty( $item['volume'] ) )
		$fields['volume'] = sanitize_text_field( $item['volume'] );
	if ( isset( $item['issue'] ) &&!empty( $item['issue'] ) )
		$fields['issue'] = sanitize_text_field( $item['issue'] );
	if ( isset( $item['page'] ) &&!empty( $item['page'] ) )
		$fields['page'] = sanitize_text_field( $item['page'] );
	
	// Return the $fields array, or this won't work!	
	return $fields;
}