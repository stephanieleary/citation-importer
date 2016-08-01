<?php
/*
Plugin Name: Citation Importer
Plugin URI: http://stephanieleary.com/
Description: Import an arbitrary HTML citation into a post.
Author: sillybean
Author URI: http://stephanieleary.com/
Version: 0.3.2
Text Domain: import-citation
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! defined( 'WP_LOAD_IMPORTERS' ) )
	return;

/** Display verbose errors */
define( 'IMPORT_DEBUG', false );

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

if ( class_exists( 'WP_Importer' ) ) {
class Citation_Importer extends WP_Importer {

	var $post_type = 'post';
	var $citation = '';
	var $items = array();
	
	function __construct() {}

	function header() {
		echo '<div class="wrap">';
		echo '<h2>'.__( 'Citation Importer', 'import-citation' ).'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function dispatch() {
		if ( empty ( $_GET['step'] ) )
			$step = 0;
		else
			$step = ( int ) $_GET['step'];

		$this->header();

		switch ( $step ) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer( 'citation-import' );
				$transient = $this->lookup();
				$this->display( $transient );
				break;
			case 2 :
				$this->import( $_POST['checked'] );
				break;
		}

		$this->footer();
	}
	
	function greet() { 
		
		$url = add_query_arg( array( 'step' => 1, 'import' => 'citation' ), 'admin.php' );
		$post_types = get_post_types( array( 'public' => true ), 'objects', 'and' );
		?>
		
		<?php 
		printf( __( '<p>Paste your citations or CrossRef DOIs below. To use another agency\'s DOI, append /agency-name. You may also use <a href="http://search.crossref.org/help/search">any of the other searches accepted by crossref.org</a>, but this importer will return only the first match.</p>' ), 'http://search.crossref.org/help/search' ); 
		_e( '<p>You may enter multiple searches as bulleted or numbered lists, or one per line.</p>' ); 
		?>
		<form method="post" action="<?php echo esc_url( $url ); ?>">
			
		<p>
			<select name="post-type">
				<option value="0"><?php _e( 'Import citations as...' ); ?></option>
				<?php foreach ( $post_types as $post_type ) : 
					if ( 'attachment' !== $post_type->name ) : ?>
					<option value="<?php echo esc_attr( $post_type->name ); ?>">
						<?php echo esc_html( $post_type->label ); ?>
					</option>
				<?php endif; endforeach; ?>
			</select>
		</p>
		
		<?php wp_editor( '', 'citation-text', array( 'media_buttons' => false ) ); ?>
		
		<input type="hidden" name="action" value="save" />
		<input type="hidden" name="search_id" value="<?php echo time(); ?>" />
		
		<p class="submit">
			<input type="submit" name="submit" class="button" value="<?php echo esc_attr( __( 'Import Publications', 'import-citation' ) ); ?>" />
		</p>
		<?php wp_nonce_field( 'citation-import' ); ?>
		</form>
	<?php
	}
	
	function lookup() {
		$transient_key = sanitize_key( $_POST['search_id'] );
		
		$citation = preg_replace("/&nbsp;/", "", $_POST['citation-text'] );
		$citation = trim( force_balance_tags( wp_kses_post( $citation ) ) );
		
		$items = $queries = array();
		$is_xml = false;
		libxml_clear_errors();
		libxml_use_internal_errors( false );
		$xml = simplexml_load_string( $citation );
		if ( $xml ) {
			$rows = $xml->xpath('//li');
			$is_xml = true;
		}
		else {
			$rows = explode( "\n", $citation );
		}
		
		foreach ( $rows as $query ) {
			// skip empty rows
			if ( empty( $query ) )
				continue;
			
			if ( $is_xml )
				$query = $query->asXML();
				
			$response = $this->retrieve_items( $query );
			$short_doi = $response['alternative-id'][0];
			$items[$short_doi] = $response;
			$queries[$short_doi] = $query;
		}
		
		if ( is_array( $items ) ) {
			set_transient( 'citation_search_' . $transient_key, json_encode( $items ), 24 * HOUR_IN_SECONDS );
			set_transient( 'citation_query_' . $transient_key, $queries, 24 * HOUR_IN_SECONDS );
			$type = sanitize_text_field( $_POST['post-type'] );
			if ( post_type_exists( $type ) )
				set_transient( 'citation_type_' . $transient_key, $type, 24 * HOUR_IN_SECONDS );
		}
		
		return $transient_key;
	}
	
	
	function retrieve_items( $query = '' ) {
		if ( empty( $query ) )
			return;
		
		// rows=1 returns only the first result. We're feeling lucky.
		$url = 'http://api.crossref.org/works?rows=1&query=' . urlencode( $query );
		
		// use the following for specific DOIs
		//$url = 'http://search.labs.crossref.org/dois?q=' . urlencode( $query );
		
		$headers = array(
			'cache-control' => 'no-cache',
			'vary'  => 'Accept-Encoding',
			'user-agent'  => 'WordPressCitationImporter/0.3.1;' . get_home_url(),
		);
		
		$response = wp_remote_get(
		     $url,
		     array( 'ssl_verify' => true, 'headers' => $headers )
		);
		
		if ( is_wp_error( $response ) )
		    return current_user_can( 'manage_options' ) ? $response->get_error_message() : '';

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		//echo '<pre>' . print_r( $result, true ) . '</pre>'; exit;

		return $result['message']['items'][0];
	}
	
	function display( $transient ) {
		$items = json_decode( get_transient( 'citation_search_' . $transient ), true );
		if ( empty($items) ) {
			printf( '<h4>%s</h4>', __( 'No citations found.', 'import-citation' ) );
			return;
		}
		$url = add_query_arg( array( 'step' => 2, 'import' => 'citation' ), 'admin.php' );
		?>
		<h4><?php _e( 'Citation Search Results', 'import-citation' ); ?></h4>
		<form method="post" action="<?php echo esc_url( $url ); ?>">
		<input type="hidden" name="search_id" value="<?php echo esc_attr( $transient ); ?>" />
 		<table class="wp-list-table widefat striped citations">
			<thead>
			<tr>
				<td class="manage-column column-cb check-column" id="cb">
					<label for="cb-select-all-1" class="screen-reader-text"><?php _e( 'Select All', 'import-citation' ); ?></label>
					<input type="checkbox" id="cb-select-all-1">
				</td>
				<th class="manage-column column-name column-primary" id="name" scope="col">
					<?php _e( 'Publication Title', 'import-citation' ); ?>
				</th>
				<th class="manage-column column-author" id="author" scope="col">
					<?php _e( 'Authors', 'import-citation' ); ?>
				</th>
				<th class="manage-column column-source" id="author" scope="col">
					<?php _e( 'Source', 'import-citation' ); ?>
				</th>
				<th class="manage-column column-date" id="author" scope="col">
					<?php _e( 'Date', 'import-citation' ); ?>
				</th>
				<th class="manage-column column-doi" id="doi" scope="col">
					<?php _e( 'DOI', 'import-citation' ); ?>
				</th>
			</tr>
			</thead>
			<tbody id="the-list">
				
			<?php
			foreach( $items as $item ) :
				$authors = array();
				$short_doi = $item['alternative-id'][0];
				$doi = esc_attr( $item['DOI'] ); 
				?>
				<tr data-doi="<?php echo $short_doi; ?>">
					<th class="check-column" scope="row">
						<label for="checkbox_<?php echo $short_doi; ?>" class="screen-reader-text">
							<?php printf( __( 'Select %s', 'import-citation' ), $item['title'][0] ); ?>
						</label>
						<input type="checkbox" id="checkbox_<?php echo $short_doi; ?>" value="<?php echo $short_doi; ?>" name="checked[]" checked>
					</th>
					<td class="citation column-primary">
						<a href="http://search.crossref.org/?q=<?php echo urlencode( $item['DOI'] ); ?>"><?php echo esc_html( $item['title'][0] ); ?></a>
						<?php
						foreach ( $item['author'] as $author ) {
							$authors[] = $author['given'] . ' ' . $author['family'];
						}
						?>
						</td>
					<td class="authors"><?php echo esc_html( implode( ', ', $authors ) ); ?></td>
					<td class="source"><?php echo esc_html( $item['container-title'][0] ); ?></td>
					<td class="date"><?php echo date( 'Y', strtotime( $item['created']['date-time'] ) ); ?></td>
					<td class="doi"><?php echo $doi; ?></td>
				</tr>
			<?php 
			endforeach;
			?>
				
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="submit" class="button" value="<?php echo esc_attr( __( 'Import Publications', 'import-citation' ) ) ?>" /></p>
		</form>
		<?php
	}
	
	
	function import( $citations ) {
		echo '<h2>'.__( 'Importing citations...', 'import-citation' ).'</h2>';
		
		$transient_key = sanitize_key( $_POST['search_id'] );
		$items = json_decode( get_transient( 'citation_search_' . $transient_key ), true );
		$queries = get_transient( 'citation_query_' . $transient_key );
		$post_type = get_transient( 'citation_type_' . $transient_key );
		
		foreach ( $citations as $short_doi ) {
			if ( !isset( $items[$short_doi] ) ) {
				_e( 'Could not find DOI in stored item index.', 'import-citation' );
				continue;
			}
			$result = $this->insert_post( $items[$short_doi], $post_type, $queries[$short_doi] );
			if ( is_wp_error( $result ) )
				echo $result->get_error_message();
			else
				echo $result;
		}
		do_action( 'import_done', 'citation' );
	}
	
	
	function insert_post( $item = '', $type = 'post', $citation = '' ) {
		
		if ( !is_array( $item ) )
			return;
		
		// start building the WP post object to insert
		$post = $fields = $authors = $terms = array();
		
		$post['post_type'] = $type;
		$post['post_content'] = '';
		$post['post_title'] = $item['title'][0];
		$post['post_excerpt'] = $citation; // original query
		$post['post_status'] = 'publish';
		
		// attempt to retrieve abstract
		$abstract_url = sprintf( 'http://api.crossref.org/works/%s.xml', urlencode( $item['DOI'] ) );
		$abstract_response = wp_remote_get( $abstract_url );
		if ( !is_wp_error( $abstract_response ) ) {
			$pub = wp_remote_retrieve_body( $abstract_response );
			$abstract = $pub->doi_record->journal->journal_article->{'jats:abstract'};
			if ( !empty( $abstract ) )
				$post['post_content'] = $abstract;
		}
		
		$post = apply_filters( 'citation_importer_postdata', $post, $item );
		
		// custom fields		
		foreach ( $item['author'] as $author ) {
			$authors[] = $author['given'] . ' ' . $author['family'];
		}
		$fields['authors'] = implode( ', ', $authors );
		$fields['doi'] = $item['DOI'];
		$fields['url'] = $item['URL'];
		$fields['pub_date'] = $item['created']['date-time'];
		$fields['source'] = $item['container-title'][0];
		if ( !empty( $item['volume'] ) )
			$fields['source'] .= ', vol. ' . $item['volume'];
		if ( !empty( $item['issue'] ) )
			$fields['source'] .= ', issue ' . $item['issue'];
		
		$fields = apply_filters( 'citation_importer_fielddata', $fields, $post, $item );
		
		// taxonomy terms
		$terms['pubtype'] = $item['type']; // slug
		
		$terms = apply_filters( 'citation_importer_termdata', $terms, $post, $item );
		
		//var_dump( $post, $fields, $taxes ); exit;
		
		
		// create post
		$post_id = wp_insert_post( $post );
		
		// handle errors
		if ( !$post_id )
			return __( 'Could not import citation.', 'import-citation' );
			
		if ( is_wp_error( $post_id ) )
			return is_object( 'manage_options' ) ? $post_id->get_error_message() : __( 'Could not import citation.', 'import-citation' );
		
		// if no errors, handle custom fields
		foreach ( $fields as $name => $value ) {
			add_post_meta( $post_id, $name, $value, true );
		}
		
		// handle taxonomies
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects', 'and' );
		foreach ( $taxonomies as $tax ) {
			if ( isset( $terms[$tax->name] ) )
				wp_set_post_terms( $post_id, $terms[$tax->name], $tax->name, false );
		}
		
		// show success
		return sprintf( __( '<p>Imported the citation as <a href="%s%d">%s</a>.</p>', 'import-citation' ), 'post.php?action=edit&post=', $post_id, $post['post_title'] );
	}  // import_post()

} // class
} // class_exists( 'WP_Importer' )

global $citation_importer;
$citation_importer = new Citation_Importer();

register_importer( 'citation', __( 'Citation', 'import-citation' ), __( 'Import an HTML citation.', 'import-citation' ), array( $citation_importer, 'dispatch' ) );