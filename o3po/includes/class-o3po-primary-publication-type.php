<?php

/**
 * Class representing the primary publication type.
 *
 * Each publication type is connected to a WordPress custom post type and
 * individual publications are represented by posts of that type.
 *
 * @link       http://example.com
 * @since      0.1.0
 *
 * @package    O3PO
 * @subpackage O3PO/includes
 */

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-o3po-utility.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-o3po-latex.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-o3po-publication-type.php';

/**
 * Class representing the primary publication type.
 *
 * @since      0.1.0
 * @package    O3PO
 * @subpackage O3PO/includes
 * @author     Christian Gogolin <o3po@quantum-journal.org>
 */
class O3PO_PrimaryPublicationType extends O3PO_PublicationType {

        /**
         * Construct this publication type.
         *
         * Constructs and registers this publication type in the array
         * static::$active_publication_types. Throws an error in case a
         * publication type with the same $publication_type_name is alreay
         * registered.
         *
         * @since    0.1.0
         * @access   public
         * @param    object               $journal        The journal this publication type is associated with.
         * @param    O3PO_Environment     $environment    The evironment in which this post type is to be created.
         */
    public function __construct( $journal, $environment ) {

        parent::__construct($journal, 4, $environment);

    }

        /**
         * Render the admin panel meta box.
         *
         * @since     0.1.0
         * @access    public
         * @param     Post    $post    Post for which the meta box is to be rendered.
         */
    public function render_metabox( $post ) {

        $post_id = $post->ID;
        $post_type = get_post_type($post_id);
            // If the post type doesn't fit do nothing
        if ( $this->get_publication_type_name() !== $post_type )
            return;

        parent::render_metabox( $post );

        $this->the_admin_panel_intro_text($post_id);
        $this->the_admin_panel_howto($post_id);
        $this->the_admin_panel_validation_result($post_id);
        echo '<table class="form-table">';
        $this->the_admin_panel_eprint($post_id);
        $this->the_admin_panel_title($post_id);
        $this->the_admin_panel_corresponding_author_email($post_id);
        $this->the_admin_panel_buffer_email($post_id);
        $this->the_admin_panel_fermats_library($post_id);
        $this->the_admin_panel_authors($post_id);
        $this->the_admin_panel_affiliations($post_id);
        $this->the_admin_panel_date_volume_pages($post_id);
        $this->the_admin_panel_abstract($post_id);
        $this->the_admin_panel_doi($post_id);
        $this->the_admin_panel_feature_image_caption($post_id);
        $this->the_admin_panel_popular_summary($post_id);
        $this->the_admin_panel_bibliography($post_id);
        $this->the_admin_panel_crossref($post_id);
        $this->the_admin_panel_doaj($post_id);
        $this->the_admin_panel_arxiv($post_id);
        echo '</table>';

    }

        /**
         * Callback function for handling the data enterd into the meta-box
         * when a correspnding post is saved.
         *
         * Calls save_meta_data() and validate_and_process_data() as well as
         * a bunch of other methods such as on_post_actually_published() when appropriate
         * to actually do the processing. Also ensures that the post is forced to private
         * as long as there are still validation ERRORs or REVIEW requests.
         *
         * Warning: This is already called when a New Post is created and not
         * only when the "Publish" or "Update" button is pressed!
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post whose meta data is to be saved.
         * */
    protected function save_meta_data( $post_id ) {

        parent::save_meta_data($post_id);

        $post_type = get_post_type($post_id);

		$new_abstract = isset( $_POST[ $post_type . '_abstract' ] ) ? $_POST[ $post_type . '_abstract' ] : '';
		$new_abstract_mathml = isset( $_POST[ $post_type . '_abstract_mathml' ] ) ? $_POST[ $post_type . '_abstract_mathml' ] : '';
		$new_eprint = isset( $_POST[ $post_type . '_eprint' ] ) ? sanitize_text_field( $_POST[ $post_type . '_eprint' ] ) : '';
        $old_eprint = get_post_meta( $post_id, $post_type . '_eprint', true );
        if ($old_eprint === $new_eprint)
			update_post_meta( $post_id, $post_type . '_eprint_was_changed_on_last_save', "false" );
		else
			update_post_meta( $post_id, $post_type . '_eprint_was_changed_on_last_save', "true" );
        $new_fermats_library = isset($_POST[ $post_type . '_fermats_library' ]) ? $_POST[ $post_type . '_fermats_library' ] : '';
		$new_fermats_library_permalink = isset( $_POST[ $post_type . '_fermats_library_permalink' ] ) ? sanitize_text_field( $_POST[ $post_type . '_fermats_library_permalink' ] ) : '';
		$new_feature_image_caption = isset( $_POST[ $post_type . '_feature_image_caption' ] ) ? $_POST[ $post_type . '_feature_image_caption' ] : '';
		$new_popular_summary = isset( $_POST[ $post_type . '_popular_summary' ] ) ? $_POST[ $post_type . '_popular_summary' ] : '';

        $arxiv_fetch_results = '';
		if ( ( isset($_POST[$post_type . '_fetch_metadata_from_arxiv'] ) or $old_eprint !== $new_eprint ) and !empty($new_eprint) and preg_match("/^(quant-ph\/[0-9]{6,}|[0-9]{4}\.[0-9]{4,})v[0-9]*$/", $new_eprint) === 1 ) {
			$response = wp_remote_get( $this->get_journal_property('arxiv_url_abs_prefix') . $new_eprint, array('timeout'=> 10) );
			if( !is_wp_error($response) ) {
                    // $header = $response['headers'];
				$html = $response['body'];
				$dom = new DOMDocument;
				@$dom->loadHTML($html);
				$x_path = new DOMXPath($dom);

				$arxiv_author_links = $x_path->query("/html/body//div[@class='authors']/a");
                if(!empty($arxiv_author_links))
                {
                    foreach ($arxiv_author_links as $x => $arxiv_author_link) {
                        $arxiv_author_names = preg_split('/\s+(?=\S+$)/', $arxiv_author_link->nodeValue);
                        if ( !empty($arxiv_author_names[0]) )
                            $new_author_given_names[$x] = $arxiv_author_names[0];
                        if ( !empty($arxiv_author_names[1]) )
                            $new_author_surnames[$x] = $arxiv_author_names[1];
                        else
                            $arxiv_fetch_results .= "ERROR: Failed to fetch surname of author ".($x+1)." from the arXiv.\n";
                        $new_number_authors = $x+1;
                    }
                    if(isset($new_number_authors)) update_post_meta( $post_id, $post_type . '_number_authors', $new_number_authors );
                    if(isset($new_author_given_names)) update_post_meta( $post_id, $post_type . '_author_given_names', $new_author_given_names );
                    if(isset($new_author_surnames)) update_post_meta( $post_id, $post_type . '_author_surnames', $new_author_surnames );
                }
                else
                    $arxiv_fetch_results .= "ERROR: Failed to fetch author information of ".$new_eprint." from the arXiv.\n";

				$arxiv_titles = $x_path->query("/html/body//h1[contains(@class, 'title')]/text()[last()]");
                if( !empty($arxiv_titles[0]))
                    $arxiv_title_text = preg_replace("/[\r\n\s]+/", " ", trim( $arxiv_titles[0]->nodeValue ) );
				if ( !empty($arxiv_title_text) ) {
					$new_title = addslashes( O3PO_Latex::latex_to_utf8_outside_math_mode($arxiv_title_text) );
                    update_post_meta( $post_id, $post_type . '_title', $new_title );
                }
				else
					$arxiv_fetch_results .= "ERROR: Failed to fetch title of ".$new_eprint." from the arXiv.\n";

				$arxiv_abstracts = $x_path->query("/html/body//blockquote[contains(@class, 'abstract')]/text()[position()>1]");
                $arxiv_abstract_text = "";
                foreach($arxiv_abstracts as $arxiv_abstract_par)
                    $arxiv_abstract_text .= preg_replace('!\s+!', ' ', trim($arxiv_abstract_par->nodeValue)) . "\n";
                $arxiv_abstract_text = trim($arxiv_abstract_text);

                if ( !empty($arxiv_abstract_text) )
					$new_abstract = addslashes( O3PO_Latex::latex_to_utf8_outside_math_mode($arxiv_abstract_text) );
				else
					$arxiv_fetch_results .= "ERROR: Failed to fetch abstract of ".$new_eprint." from the arXiv.\n";

				$arxiv_license_urls = $x_path->query("/html/body//div[contains(@class, 'abs-license')]/a/@href");
				if( !empty($arxiv_license_urls) )
					foreach ($arxiv_license_urls as $x => $arxiv_license_url) {
						if( preg_match('#creativecommons.org/licenses/(by-nc-sa|by-sa|by)/4.0/#', $arxiv_license_url->nodeValue) !== 1)
                            if(!$this->environment->is_test_environment())
                                $arxiv_fetch_results .= "ERROR: It seems like ".$new_eprint." is not published under one of the three creative commons license (CC BY 4.0, CC BY-SA 4.0, or CC BY-NC-SA 4.0) on the arXiv. Please inform the authors that this is mandtory and remind them that we will publish under CC BY 4.0 and that, by our terms and conditions, they grant us the right to do so.\n";
                            else
                                $arxiv_fetch_results .= "WARNING: It seems like ".$new_eprint." is not published under a creative commons license on the arXiv.\n" ;
					}
				else
                    $arxiv_fetch_results .= "ERROR: No license informatin found for ".$new_eprint." on the arXiv.\n";
			}
			else
			{
				$arxiv_fetch_results .= "ERROR: Failed to fetch html from " . $this->get_journal_property('arxiv_url_abs_prefix') . $new_eprint . " " . $response->get_error_message() . "\n";
			}
			if ( empty($arxiv_fetch_results) ) $arxiv_fetch_results .= "SUCCESS: Fetched metadata from " . $this->get_journal_property('arxiv_url_abs_prefix') . $new_eprint . "\n";
            update_post_meta( $post_id, $post_type . '_arxiv_fetch_results', $arxiv_fetch_results );
		}

		update_post_meta( $post_id, $post_type . '_abstract', $new_abstract );
		update_post_meta( $post_id, $post_type . '_abstract_mathml', $new_abstract_mathml );
		update_post_meta( $post_id, $post_type . '_eprint', $new_eprint );
		update_post_meta( $post_id, $post_type . '_fermats_library', $new_fermats_library );
		update_post_meta( $post_id, $post_type . '_fermats_library_permalink', $new_fermats_library_permalink );
		update_post_meta( $post_id, $post_type . '_feature_image_caption', $new_feature_image_caption );
		update_post_meta( $post_id, $post_type . '_popular_summary', $new_popular_summary );

    }

        /**
         * Validate and process the meta-data that was saved in save_meta_data().
         *
         * @since    0.1.0
         * @access   protected
         * @param    int          $post_id   The id of the post whose meta-data is to be validated and processed.
         */
    protected function validate_and_process_data( $post_id ) {

        $post_type = get_post_type($post_id);

        $abstract = get_post_meta( $post_id, $post_type . '_abstract', true );
        $abstract_mathml = get_post_meta( $post_id, $post_type . '_abstract_mathml', true );
        $eprint = get_post_meta( $post_id, $post_type . '_eprint', true );
        $eprint_was_changed_on_last_save = get_post_meta( $post_id, $post_type . '_eprint_was_changed_on_last_save', true );
        $doi_suffix = get_post_meta( $post_id, $post_type . '_doi_suffix', true );
        $doi_suffix_was_changed_on_last_save = get_post_meta( $post_id, $post_type . '_doi_suffix_was_changed_on_last_save', true );
        $arxiv_fetch_results = get_post_meta( $post_id, $post_type . '_arxiv_fetch_results', true );
        $arxiv_pdf_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', true );
        $arxiv_source_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_source_attach_ids', true );
        $fermats_library = get_post_meta( $post_id, $post_type . '_fermats_library', true );
        $fermats_library_permalink = get_post_meta( $post_id, $post_type . '_fermats_library_permalink', true );
        $fermats_library_has_been_notifed_date = get_post_meta( $post_id, $post_type . '_fermats_library_has_been_notifed_date', true );
		$corresponding_author_has_been_notifed_date = get_post_meta( $post_id, $post_type . '_corresponding_author_has_been_notifed_date', true );
        $date_published = get_post_meta( $post_id, $post_type . '_date_published', true );

            // Set the category
        $term_id = term_exists( ucfirst($this->get_publication_type_name()), 'category' );
        if($term_id == 0)
        {
            wp_insert_term( ucfirst($this->get_publication_type_name()), 'category');
            $term_id = term_exists( ucfirst($this->get_publication_type_name()), 'category' );
        }
        wp_set_post_terms( $post_id, $term_id, 'category' );

        $validation_result = '';
        if( strpos($arxiv_fetch_results, 'ERROR') !== false or strpos($arxiv_fetch_results, 'REVIEW') !== false or strpos($arxiv_fetch_results, 'WARNING') !== false)
            $validation_result .= $arxiv_fetch_results;

        $post_date = get_the_date( 'Y-m-d', $post_id );
        $today_date = current_time( 'Y-m-d' );
        if ($date_published !== $post_date)
            $validation_result .= "ERROR: The publication date of this post (" . $post_date . ") set in the Publish box on the right does not match the publication date (" . $date_published . ") of this " . $post_type . " given in the input field below.\n";
        if ($date_published !== $today_date and empty($corresponding_author_has_been_notifed_date) )
            $validation_result .= "WARNING: The publication date of this post (" . $post_date . ") is not set to today's date (" . $today_date . ") but the post of this " . $post_type . " also does not appear to have already been published in the past.\n";
        if ($eprint_was_changed_on_last_save === "true")
            $validation_result .= "REVIEW: The eprint was set to ". $eprint . ".\n";
        if ( empty( $eprint ) )
            $validation_result .= "ERROR: Eprint is empty.\n";
        else if (strpos($eprint, 'v') === false or preg_match("/^(quant-ph\/[0-9]{5,}|[0-9]{4}\.[0-9]{4,})v[0-9]*$/", $eprint ) !== 1 )
            $validation_result .= "ERROR: Eprint does not contain the specific arXiv version, i.e., ????.????v3.\n";

            // Download PDF form the arXiv
        if( !empty( $doi_suffix ) and !empty( $eprint ) and (isset($_POST[$post_type . '_download_arxiv_pdf']) or empty($arxiv_pdf_attach_ids) or $eprint_was_changed_on_last_save === "true" or $doi_suffix_was_changed_on_last_save === "true" ) )
        {
            $pdf_download_url = $this->get_journal_property('arxiv_url_pdf_prefix') . $eprint;
            $pdf_download_result = $this->environment->download_to_media_library($pdf_download_url, $doi_suffix, 'pdf', 'application/pdf', $post_id );
        }

            // Download SOURCE form the arXiv (This can yield either a .tex or a .tar.gz file!)
        if( !empty( $doi_suffix ) and !empty( $eprint ) and (isset($_POST[$post_type . '_download_arxiv_source']) or empty($arxiv_source_attach_ids) or $eprint_was_changed_on_last_save === "true" or $doi_suffix_was_changed_on_last_save === 'true') )
        {
            $source_download_url = $this->get_journal_property('arxiv_url_source_prefix') . $eprint;
            $source_download_result = $this->environment->download_to_media_library($source_download_url, $doi_suffix, '', '', $post_id );
        }

        if ( !empty( $pdf_download_result['error'] ) ) {
            $validation_result .= "ERROR: Exception while downloading the pdf from the arXiv" . (!empty($pdf_download_url) ? " (".$pdf_download_url.")" : "") . ": " . $pdf_download_result['error'] . "\n";
        } else if (!empty($pdf_download_result)) {
                // $validation_result .= "The file is " . $pdf_download_result['file'] . "\n"; // Full path to the file
                // $validation_result .= "The URL is " . $pdf_download_result['url'] . "\n";  // URL to the file in the uploads dir
                // $validation_result .= "The mime/type is " . $pdf_download_result['type'] . "\n"; // MIME type of the file
                // $validation_result .= "The attach_id is " . $pdf_download_result['attach_id'] . "\n";
            $arxiv_pdf_attach_ids[] = $pdf_download_result['attach_id'];
            update_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', $arxiv_pdf_attach_ids );
            $validation_result .= "REVIEW: The pdf was downloaded successfully from the arXiv.\n";
        }
        if ( !empty( $source_download_result['error'] ) ) {
            $validation_result .= "ERROR: Exception while downloading the source of the " . $this->get_publication_type_name() . " from the arXiv" . (!empty($source_download_url) ? " (".$source_download_url.")" : "") . ": " . $source_download_result['error'] . "\n";
        } else if (!empty($source_download_result)) {
                // $validation_result .= "The file is " . $source_download_result['file'] . "\n"; // Full path to the file
                // $validation_result .= "The URL is " . $source_download_result['url'] . "\n";  // URL to the file in the uploads dir
                // $validation_result .= "The mime/type is " . $source_download_result['type'] . "\n"; // MIME type of the file
                // $validation_result .= "The attach_id is " . $source_download_result['attach_id'] . "\n";
            $arxiv_source_attach_ids[] = $source_download_result['attach_id'];
            update_post_meta( $post_id, $post_type . '_arxiv_source_attach_ids', $arxiv_source_attach_ids );

                // this is not always actually a tar.gz file! Sometimes the arXiv just gives us a .tex!
            $path_source = $source_download_result['file'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $path_source);
            finfo_close($finfo);
            $validation_result .= "REVIEW: The source was downloaded successfully from the arXiv to " . $path_source . " and is of mime-type " . $mime_type . "\n";

                /* We now start parsing the downloaded source code.
                 * Depending on the manuscript we either got a single uncompressed .tex file
                 * or a tar.gz archive which we have to extract and then analyse.
                 *
                 * TODO: The following should be broken up into several functions to make it easer to understand and reuse the code. */
            $bbl = '';
            if ( preg_match('#text/.*tex#', $mime_type) && substr($path_source, -4) === '.tex' ) { // We got a single file
                try {
                    $filecontents = $this->environment->file_get_contents_utf8($path_source);
                    preg_match('/(\\\\begin{thebibliography}.*\\\\end{thebibliography}|\\\\begin{references}.*\\\\end{references})/s', $filecontents  , $bib);
                    if(!empty($bib[0])) {
                        $bbl .= $bib[0] . "\n";
                        $validation_result .= "REVIEW: Found bibliographic information.\n";
                    }
                } catch (Exception $e) {
                    $validation_result .= "WARNING: While processing the source tex file " . $path_source . " an exception occurred: " . $e->getMessage() . "\n";
                }
            } else if ( preg_match('#application/.*(tar|gz|gzip)#', $mime_type) && substr($path_source, -7) === '.tar.gz' ) { // We got an archive
                try {

                        //Unpack
                    $path_tar = preg_replace('/\.gz$/', '', $path_source);
                    $path_folder = preg_replace('/\.tar$/', '', $path_tar) . '_extracted/';

                    $phar_gz = new PharData($path_source);
                    $phar_gz->decompress(); // *.tar.gz -> *.tar
                    $phar_tar = new PharData($path_tar);
                    $phar_tar->extractTo($path_folder);

                    $old_author_orcids = get_post_meta( $post_id, $post_type . '_author_orcids', true );

                        //Loop over the relevant files
                    foreach(new RecursiveIteratorIterator( new RecursiveDirectoryIterator($path_folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry ) {
                        if($entry->isFile() && ( substr($entry->getPathname(), -4) === '.bbl' || substr($entry->getPathname(), -4) === '.tex' ) )
                        {
                            $filecontents = $this->environment->file_get_contents_utf8($entry->getPathname());
                            $filecontents_without_comments = preg_replace('#(?<!\\\\)%.*#', '', $filecontents);//remove all comments

                                //Extract all the user defined tex macros and collect them
                            $author_latex_macro_definitions_from_this_file = O3PO_Latex::extract_latex_macros($filecontents_without_comments);
                            if(!empty($author_latex_macro_definitions_from_this_file))
                            {
                                if(!isset($new_author_latex_macro_definitions))
                                    $new_author_latex_macro_definitions = array();
                                $new_author_latex_macro_definitions = array_merge_recursive($new_author_latex_macro_definitions, $author_latex_macro_definitions_from_this_file);
                            }

                                //Look for a bibliography and extract it
                            preg_match('/\\\\begin{thebibliography}.*\\\\end{thebibliography}/s', $filecontents_without_comments  , $bib);//we search the fiel with comments removed to not accidentially pic up a commented out bibliography
                            if(!empty($bib[0])) {
                                $bbl .= $bib[0] . "\n";
                                $validation_result .= "REVIEW: Found BibTeX or manually formated bibliographic information in " . $entry->getPathname() . ".\n";
                            } else if( substr($entry->getPathname(), -4) === '.bbl' && strpos( $filecontents, 'biblatex auxiliary file' ) != false )  {
                                $bbl .= $filecontents . "\n";//here comments must be preserved as they contain clues for parsing
                                $validation_result .= "REVIEW: Found BibLaTeX formated bibliographic information in " . $entry . "\n";
                            }
                                /* We expand the latex macros in $bbl after we have gone through all files and then
                                 * add slashes just before saving $bbl with update_post_meta() */

                                // Extract author, affiliation and similar information from the source
                            preg_match_all('#\\\\(author|affiliation|affil|orcid|homepage)([^{]*)(?=\{((?:[^{}]++|\{(?3)\})*)\})#', $filecontents_without_comments, $author_info);//matches balanced parenthesis (Note the use of (?3) here!) to test changes go here https://regex101.com/r/bVHadc/1
                            if(!empty($author_info[0]) && !empty($author_info[3]))
                            {
                                $validation_result .= "REVIEW: Affiliations, ORCIDs, and author URLs updated from arxiv source. Please check.\n";

                                $new_author_orcids = array();
                                $new_author_urls = array();
                                $new_author_affiliations = array();
                                $new_affiliations = array();
                                $author_number = -1;
                                $authors_since_last_affiliation = array();
                                $was_affiliation_since_last_author = false;

                                for($x = 0; $x < count($author_info[1]) ; $x++) {
                                    if( $author_info[1][$x] === 'author')
                                    {
                                        $author_number += 1;

                                            /* It is difficult to extract the author name from the source
                                             * as the LaTeX \author macro gives no clue about what is the
                                             * given name and what is the surname. We hence ignore
                                             * $author_info[3][$x] for now and rely on the information
                                             * fetched from the abstrract page of the arXiv.*/

                                        if($was_affiliation_since_last_author)
                                            $authors_since_last_affiliation = array();
                                        $authors_since_last_affiliation[] = $author_number;

                                            // we interpret the optional argument of \author[1,2]{Foo Bar} as the list of affiliation numbers for compatibility with autblk
                                        if(!empty($author_info[2][$x]) )
                                        {
                                            preg_match_all('/\[([0-9,]*)\]/', $author_info[2][$x], $affiliations_from_optional_argument);
                                            if(!empty($affiliations_from_optional_argument[1][0]))
                                                $new_author_affiliations[$author_number] = $affiliations_from_optional_argument[1][0];
                                        }
                                    }
                                    else if( $author_info[1][$x] === 'orcid')
                                        $new_author_orcids[$author_number] = empty($old_author_orcids[$x]) ? $author_info[3][$x] : $old_author_orcids[$x];
                                    else if( $author_info[1][$x] === 'homepage')
                                        $new_author_urls[$author_number] = $author_info[3][$x];
                                    else if( $author_info[1][$x] === 'affiliation')
                                    {
                                        $current_affiliation = trim($author_info[3][$x], ' {}');
                                        if(!empty($new_author_latex_macro_definitions))
                                            $current_affiliation = O3PO_Latex::expand_latex_macros($new_author_latex_macro_definitions, $current_affiliation); //We only expand the macros defined in the current file and those we have previously seen hoping that this is enough
                                        $current_affiliation = O3PO_Latex::latex_to_utf8_outside_math_mode($current_affiliation);
                                        $current_affiliation = trim(preg_replace('#\s\s+#', ' ', $current_affiliation), ' ');
                                        if(!in_array($current_affiliation, $new_affiliations))
                                            $new_affiliations[] = $current_affiliation;

                                        foreach($authors_since_last_affiliation as $author_number_since_last_affiliation)
                                        {
                                            if(empty($new_author_affiliations[$author_number_since_last_affiliation]))
                                                $new_author_affiliations[$author_number_since_last_affiliation] = '';
                                            else
                                                $new_author_affiliations[$author_number_since_last_affiliation] .= ',';
                                            $new_author_affiliations[$author_number_since_last_affiliation] .= (array_search($current_affiliation, $new_affiliations , true)+1);
                                        }
                                        $was_affiliation_since_last_author = true;
                                    }
                                    else if( $author_info[1][$x] === 'affil')
                                    {
                                        $current_affiliation = trim($author_info[3][$x], ' {}');
                                        if(!empty($new_author_latex_macro_definitions))
                                            $current_affiliation = O3PO_Latex::expand_latex_macros($new_author_latex_macro_definitions, $current_affiliation); //We only expand the macros defined in the current file and those we have previously seen hoping that this is enough
                                        $current_affiliation = O3PO_Latex::latex_to_utf8_outside_math_mode($current_affiliation);

                                        preg_match('/[0-9]*/', $author_info[2][$x], $affiliation_symb_from_optional_argument);
                                        if(!empty($affiliation_symb_from_optional_argument[0]) && is_int($affiliation_symb_from_optional_argument[0]))
                                            $current_affiliation_num = intval($affiliation_symb_from_optional_argument[0])-1;
                                        else
                                            $current_affiliation_num = count($new_affiliations);

                                        $new_affiliations[$current_affiliation_num] = $current_affiliation;
                                    }
                                }

                                if(!empty($new_author_orcids))
                                    update_post_meta( $post_id, $post_type . '_author_orcids', $new_author_orcids );
                                if(!empty($new_author_affiliations))
                                    update_post_meta( $post_id, $post_type . '_author_affiliations', $new_author_affiliations);
                                if(!empty($new_affiliations)) {
                                    update_post_meta( $post_id, $post_type . '_affiliations',  array_map('addslashes', $new_affiliations));
                                    update_post_meta( $post_id, $post_type . '_number_affiliations', count($new_affiliations) );
                                }
                            }
                        }
                    }
                    if(!empty($new_author_latex_macro_definitions))
                    {
                            //add slashes before update_post_meta()
                        $new_author_latex_macro_definitions_with_slashes = array();
                        for($i=0; $i < count($new_author_latex_macro_definitions); $i++)
                        {
                            $new_author_latex_macro_definitions_with_slashes[$i] = array_map('addslashes', $new_author_latex_macro_definitions[$i]);
                        }
                        update_post_meta( $post_id, $post_type . '_author_latex_macro_definitions', $new_author_latex_macro_definitions_with_slashes);
                    }
                } catch (Exception $e) {
                    $validation_result .= "ERROR: While processing the source files an exception occurred: " . $e->getMessage() . "\n";
                } finally {
                    try {
                        unlink($path_tar);
                        $this->environment->save_recursive_remove_dir($path_folder, $path_folder);
                    } catch (Exception $e) {
                        $validation_result .= "ERROR: While processing the source files an exception occurred: " . $e->getMessage() . "\n";
                    }
                }
            } else {
                $validation_result .= "ERROR: Extension of source file " . $path_source . " and mime-type " . $mime_type . " do not match or are neither .tex nor .tar.gz.\n";

            }
            if(!empty($bbl)) {
                $validation_result .= "REVIEW: Bibliographic information updated.\n";

                if(!empty($new_author_latex_macro_definitions))
                {
                    $new_author_latex_macro_definitions_without_specials = O3PO_Latex::remove_special_macros_to_ignore_in_bbl($new_author_latex_macro_definitions);

                    $bbl = O3PO_Latex::expand_latex_macros($new_author_latex_macro_definitions_without_specials, $bbl);
                }
                $bbl = addslashes($bbl);
                update_post_meta( $post_id, $post_type . '_bbl', $bbl );

            }
        }

        if ( empty($abstract) )
            $validation_result .= "ERROR: Abstract is empty.\n" ;
        else if ( preg_match('/[<>]/', $abstract ) )
            $validation_result .= "WARNING: Abstract contains < or > signs. If they are meant to represent math, the formulas should be enclosed in dollar signs and they should be replaced with \\\\lt and \\\\gt respectively (similarly <= and >= should be replaced by \\\\leq and \\\\geq).\n" ;
        if ( empty($abstract_mathml) && preg_match('/[^\\\\]\$.*[^\\\\]\$/' , $abstract ) )
            $validation_result .= "ERROR: Abstract contains math but no MathML variant was saved so far. This is normal if you have only just added this manuscript, the error should go away the next time you press Update.\n";

        $add_licensing_information_result = static::add_licensing_information_to_last_pdf_from_arxiv($post_id);
        if(!empty($add_licensing_information_result))
            $validation_result .= $add_licensing_information_result . "\n";

        $validation_result .= parent::validate_and_process_data($post_id);

        return $validation_result;
    }

        /**
         * Add licensing information to latest arXiv pdf.
         *
         * @since     0.1.0
         * @acesss    public
         * @param     int     $post_id     Id of the post whose meta data is to be saved.
         */
    public function add_licensing_information_to_last_pdf_from_arxiv( $post_id ) {

        if( ini_get('safe_mode') )
            return "WARNING: Adding meta-data to pdfs only works if PHP is not in safe mode"; // See below for why.
        if(php_uname('s')!=='Linux')
            return "WARNING: Adding meta-data to pdfs is currently only supported on Linux";
        $exiftool_command_name = 'exiftool';
        $exiftool_in_path = exec('command -v ' . $exiftool_command_name . ' 2>&1 > /dev/null; echo $?');

        $exiftool_not_found_message = "ERROR: Adding meta-data to pdfs requires the external programm exiftool but the exiftool binary was not found.";
        if($exiftool_in_path !== '0')
            return $exiftool_not_found_message;
        $exiftool_binary_path = exec('which ' . $exiftool_command_name);
        if($exiftool_binary_path===null)
            return $exiftool_not_found_message;

        $post_type = get_post_type($post_id);
        $arxiv_pdf_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', true );
        if(empty($arxiv_pdf_attach_ids))
            return "ERROR: Cannot add licensing information, no pdf attached to post " . $post_id ;
        $path = get_attached_file(end($arxiv_pdf_attach_ids));
        if(empty($path))
            return "ERROR: Cannot add licensing information, no file found for pdf attachment of post " . $post_id;
        $paper_doi = get_post_meta( $post_id, $post_type . '_doi_prefix', true ) . '/' .  get_post_meta( $post_id, $post_type . '_doi_suffix', true );
        if(empty($paper_doi))
            return "ERROR: Cannot add licensing information, DOI not set" ;
        $url = $this->get_journal_property('doi_url_prefix') . $paper_doi;
        $web_statement_url = get_site_url() . '/' . $this->get_publication_type_name_plural() . '/' . get_post_meta( $post_id, $post_type . '_doi_suffix', true ) . '/web-statement/';

        $command  = $exiftool_binary_path;
            /* For more information on the scheme see https://wiki.creativecommons.org/wiki/XMP */
        $command .= ' -XMP-xmpRights:Marked=' . escapeshellarg('True');
        $command .= ' -XMP-xmpRights:UsageTerms=' .  escapeshellarg('This work is published under the ' . $this->get_journal_property('license_name') . ' license ' . $this->get_journal_property('license_url') . ' verify at ' . $web_statement_url);
        $command .= ' -XMP-xmpRights:WebStatement=' . escapeshellarg($web_statement_url);
        $command .= ' -XMP-dc:Rights=' . escapeshellarg($this->get_journal_property('license_type') . ' ' . $this->get_journal_property('license_version'));
        $command .= ' -XMP-cc:license=' . escapeshellarg($this->get_journal_property('license_url'));
        $command .= ' -XMP-cc:attributionURL=' . escapeshellarg($url);
        $command .= ' -XMP-cc:attributionName=' . escapeshellarg(static::get_formated_authors($post_id) . ", " . get_the_title( $post_id ) . ", " . static::get_formated_citation($post_id));

        $command .= ' ' . escapeshellarg($path);

        exec($command, $output, $exit_code); // We can not use escapeshellcmd() here as it escapes even the content of arguments enclosed in '' and this breaks things. In PHP safe mode escapeshellcmd() is forcefully run inside exec(), which is why we cannot add licencing information in safe mode.

        if($exit_code!=0)
            return "ERROR: Exiftool (" . $command . ") finished with exit code=" . $exit_code . " for file " . $path . " the output was: " . implode($output," ");

        return "INFO: Licensing information (" . $this->get_journal_property('license_type') . ' ' . $this->get_journal_property('license_version') . ") and meta-data of " . $path . " added/updated";
    }

        /**
         * Do things when the post is finally published.
         *
         * Is called from save_metabox().
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post that is actually published publicly.
         */
    protected function on_post_actually_published( $post_id ) {

        $validation_result = parent::on_post_actually_published($post_id);

        $post_type = get_post_type($post_id);

        $corresponding_author_has_been_notifed_date = get_post_meta( $post_id, $post_type . '_corresponding_author_has_been_notifed_date', true );
        $corresponding_author_email = get_post_meta( $post_id, $post_type . '_corresponding_author_email', true );
        $eprint = get_post_meta( $post_id, $post_type . '_eprint', true );
        $fermats_library = get_post_meta( $post_id, $post_type . '_fermats_library', true );
        $fermats_library_permalink = get_post_meta( $post_id, $post_type . '_fermats_library_permalink', true );
        $fermats_library_has_been_notifed_date = get_post_meta( $post_id, $post_type . '_fermats_library_has_been_notifed_date', true );
        $doi = static::get_doi($post_id);
        $doi_suffix = get_post_meta( $post_id, $post_type . '_doi_suffix', true );
        $title = get_post_meta( $post_id, $post_type . '_title', true );
        $journal = get_post_meta( $post_id, $post_type . '_journal', true );
		$post_url = get_permalink( $post_id );

            // Send Emails about the submission to us
        $to = ($this->environment->is_test_environment() ? $this->get_journal_property('developer_email') : $this->get_journal_property('publisher_email') );
        $headers = array( 'From: ' . $this->get_journal_property('publisher_email'));
        $subject  = ($this->environment->is_test_environment() ? 'TEST ' : '') . 'A ' . $this->get_publication_type_name() . ' has been published/updated by ' . $journal;
        $message  = ($this->environment->is_test_environment() ? 'TEST ' : '') . $journal . " has published/updated the following " . $this->get_publication_type_name() . ":\n\n";
        $message .= "Title:  " . $title . "\n";
        $message .= "Authos: " . static::get_formated_authors($post_id) . "\n";
        $message .= "URL:    " . $post_url . "\n";
        $message .= "DOI:    " . $this->get_journal_property('doi_url_prefix') . $doi . "\n";

        $successfully_sent = wp_mail( $to, $subject, $message, $headers);

        if(!$successfully_sent)
            $validation_result .= 'WARNING: Error sending email notifation of publication to publisher.' . "\n";

            /* We do not send trackbacks for Papers as it is against arXiv's policies.
             * Instead we have a doi feed through wich arXiv can automatically
             * pull and set dois.*/
        /*     // Send a trackback to the arXiv */
        /* if(!empty($eprint) && !$this->environment->is_test_environment()) { */
        /*     $eprint_without_version = preg_replace('#v[0-9]*$#', '', $eprint); */
        /*     $trackback_result = trackback( $this->get_journal_property('arxiv_url_trackback_prefix') . $eprint_without_version , $title, static::get_trackback_excerpt($post_id), $post_id ); */
        /*     $validation_result .= "INFO: A trackback was sent to " . $this->get_journal_property('arxiv_url_trackback_prefix') . $eprint_without_version . " and the response was: " . $trackback_result . ".\n" ; */
        /* } */

            // Send email notifying authors of publication
		if( empty($corresponding_author_has_been_notifed_date) ) {

            $to = ($this->environment->is_test_environment() ? $this->get_journal_property('developer_email') : $corresponding_author_email);
			$headers = array( 'Cc: ' . ($this->environment->is_test_environment() ? $this->get_journal_property('developer_email') : $this->get_journal_property('publisher_email') ), 'From: ' . $this->get_journal_property('publisher_email'));
			$subject  = ($this->environment->is_test_environment() ? 'TEST ' : '') . $journal . " has published your " . $this->get_publication_type_name();
			$message  = ($this->environment->is_test_environment() ? 'TEST ' : '') . "Dear " . static::get_formated_authors($post_id) . ",\n\n";
			$message .= "Congratulations! Your " . $this->get_publication_type_name() . " '" . $title . "' has been published by " . $journal . " and is now available under:\n\n";
			$message .= $post_url . "\n\n";
			$message .= "Your work has been assigned the following journal reference and DOI\n\n";
			$message .= "Journal reference: " . static::get_formated_citation($post_id) . "\n";
			$message .= "DOI:               " . $this->get_journal_property('doi_url_prefix') . $doi . "\n\n";
			$message .= "We kindly ask you to log in on the arXiv under https://arxiv.org/user/login and add this information to the page of your work there. Thank you very much!\n\n";
			$message .= "In case you have an ORCID you can go to http://search.crossref.org/?q=" . str_replace('/', '%2F', $doi)  . " to conveniently add your new publication to your profile.\n\n";
			$message .= "Please be patient, it can take several hours until the DOI has been activated by Crossref.\n\n";
			$message .= "If you have any feedback or ideas for how to improve the peer-review and publishing process, or any other question, please let us know under " . $this->get_journal_property('publisher_email') . ".\n\n";
			$message .= "Best regards,\n\n";
			$message .= "Christian, Lídia, and Marcus\n";
			$message .= "Executive Board\n";
			$successfully_sent = wp_mail( $to, $subject, $message, $headers);

            if($successfully_sent) {
                update_post_meta( $post_id, $post_type . '_corresponding_author_has_been_notifed_date', date("Y-m-d") );
            }
            else
            {
                $validation_result .= 'WARNING: Sending email to corresponding author failed.' . "\n";
            }
		}

            // Send email to Fermat's library
		if(($fermats_library === "checked" && empty($fermats_library_has_been_notifed_date))) {

            $fermats_library_permalink = $this->get_journal_property('fermats_library_url_prefix') . $doi_suffix;

            $to = ($this->environment->is_test_environment() ? $this->get_journal_property('developer_email') : $this->get_journal_property('fermats_library_email'));
			$headers = array( 'Cc: ' . ($this->environment->is_test_environment() ? $this->get_journal_property('developer_email') : $this->get_journal_property('publisher_email') ), 'From: ' . $this->get_journal_property('publisher_email'));
			$subject  = ($this->environment->is_test_environment() ? 'TEST ' : '') . $journal . " has a new " . $this->get_publication_type_name() . " for Fermat's library";
			$message  = ($this->environment->is_test_environment() ? 'TEST ' : '') . "Dear team at Fermat's library,\n\n";
			$message .= $journal . " has published the following " . $this->get_publication_type_name() . ":\n\n";
			$message .= "Title:     " . $title . "\n";
			$message .= "Author(s): " . static::get_formated_authors($post_id) . "\n";
			$message .= "URL:       " . $post_url . "\n";
			$message .= "DOI:       " . $this->get_journal_property('doi_url_prefix') . $doi . "\n";
			$message .= "\n";
			$message .= "Please post it on Fermat's library under the permalink: " . $fermats_library_permalink . "\n";
			$message .= "Thank you very much!\n\n";
			$message .= "Kind regards,\n\n";
			$message .= "The Executive Board\n";
			$successfully_sent = wp_mail( $to, $subject, $message, $headers);

            if($successfully_sent) {
                update_post_meta( $post_id, $post_type . '_fermats_library_permalink', $fermats_library_permalink );
                update_post_meta( $post_id, $post_type . '_fermats_library_has_been_notifed_date', date("Y-m-d") );
            }
            else
                $validation_result .= "WARNING: Error sending email to fermat's library." . "\n";
        }

        return $validation_result;
    }

        /**
         * Get an excerpt for trackbaks.
         */
    /* private static function get_trackback_excerpt($post_id) { */
    /*     $post_type = get_post_type($post_id); */

    /*     if ( $post_type === $this->get_publication_type_name() ) { */
    /*         $abstract = get_post_meta( $post_id, $post_type . '_abstract', true ); */
    /*         $doi = static::get_doi( $post_id ); */
    /*         $authors = static::get_formated_authors($post_id); */
    /*         $excerpt = ''; */
    /*         $excerpt .= '<h2>' . esc_html($authors) . '</h2>'; */
    /*         $excerpt .= '<a href="' . $this->get_journal_property('doi_url_prefix') . $doi . '">' . $this->get_journal_property('doi_url_prefix') . $doi . '</a>'; */
    /*         $excerpt .= '<p>' . esc_html($abstract) . '</p>'; */
    /*         $excerpt = str_replace(']]>', ']]&gt;', $excerpt); */
    /*         $excerpt = wp_html_excerpt($excerpt, 252, '&#8230;'); */
    /*         return $excerpt; */
    /*     } */
    /*     else */
    /*         return ''; */
    /* } */

        /**
         * Outptus the html formated volume of this publication
         * type.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    public static function get_formated_volume_html( $post_id ) {

        $post_type = get_post_type($post_id);
        $volumen_num = get_post_meta( $post_id, $post_type . '_volume', true );

        return '<a href="/volumes/' . esc_attr($volumen_num) . '">volume ' . esc_html($volumen_num) . '</a>';
    }

        /**
         * Echo a howto for the admin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_howto( $post_id ) {

        $post_type = get_post_type($post_id);

        $eprint = get_post_meta( $post_id, $post_type . '_eprint', true );
		$arxiv_pdf_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', true );
        $popular_summary = get_post_meta( $post_id, $post_type . '_popular_summary', true );
        $feature_image_caption = get_post_meta( $post_id, $post_type . '_feature_image_caption', true );
        $feature_image_path = $this->environment->get_feature_image_path($post_id);
        $fermats_library = get_post_meta( $post_id, $post_type . '_fermats_library', true );

        echo "<h4>How to publish in 10 easy steps</h4>" . "\n";
		echo '<table style="width:100%">' . "\n";
        echo '<tr><td>Step 0</td><td>Check on <a href="'. $this->get_journal_property('scholastica_manuscripts_url').'" target="_blank">Scholastica that the manuscript has actually been accepted</a>!</td></tr>' . "\n";
        echo '<tr><td>Step 1</td><td>Put the eprint number in the box below and press "Publish"'.(empty($eprint) ? "" : " DONE!").'</td></tr>' . "\n";
        if(!empty($arxiv_pdf_attach_ids))
        {
            echo '<tr><td>Step 2</td><td>Review the validation results below.</td></tr>' . "\n";
            echo '<tr><td>Step 3</td><td>Open the <a href="'.esc_attr(wp_get_attachment_url(end($arxiv_pdf_attach_ids))).'" target="_blank">pdf</a> and cross check the content of the following fields:</td></tr>' . "\n";
            echo '<tr><td></td><td>Title</td></tr>' . "\n";
            echo '<tr><td></td><td>Abstract</td></tr>' . "\n";
            echo '<tr><td></td><td>Authors names</td></tr>' . "\n";
            echo '<tr><td></td><td>Affiliations (number, association, spelling)</td></tr>' . "\n";
            echo '<tr><td></td><td>References (total number, DOIs)</td></tr>' . "\n";
            echo '<tr><td>Step 4</td><td>Only if requested by the authors: Tick the opt-in to fermats library box.'.(empty($fermats_library==="checked") ? "" : " DONE!").'</td></tr>';
            echo '<tr><td>Step 5</td><td>If provided by the authors: Copy over the <a href="#paper_popular_summary">popular summary</a>.'.(empty($popular_summary) ? "" : " DONE!").'</td></tr>';
            echo '<tr><td>Step 6</td><td>If provided by the authors: Copy over the <a href="#paper_feature_image_caption">feature image caption</a>.'.(empty($feature_image_caption) ? "" : " DONE!").'</td></tr>';
            echo '<tr><td>Step 7</td><td>If provided by the authors: Edit the feature image to a suitable format (large enough, aspect ration 2:1) and set it as <a href="#postimagediv">feature image</a>.'.(empty($feature_image_path) ? "" : " DONE!").'</td></tr>';
            echo '<tr><td>Step 8</td><td>Click the Update button and address all remaining warnings and errors in the validation results below.</td></tr>';
            echo '<tr><td>Step 9</td><td>Once all is resolved, click edit next to <a href="#submitdiv">Visibility</a> in the Publish box and select Public. Then press the Publish button. '.(get_post_status( $post_id ) !== 'publish' ? "" : " DONE!").'</td></tr>';
        }
        echo '</table>' . "\n";

    }

        /**
         * Echo the eprint part of the admin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_eprint( $post_id ) {

        $post_type = get_post_type($post_id);

        $eprint = get_post_meta( $post_id, $post_type . '_eprint', true );

        if( empty( $eprint ) ) $eprint = '';
		echo '	<tr>';
		echo '		<th><label for="' . $post_type . '_eprint" class="' . $post_type . '_eprint_label">' . 'Eprint' . '</label></th>';
		echo '		<td>';
		echo '			<input type="text" id="' . $post_type . '_eprint" name="' . $post_type . '_eprint" class="' . $post_type . '_eprint_field required" placeholder="' . esc_attr__( '', 'qj-plugin' ) . '" value="' . esc_attr__( $eprint ) . '">';
		echo '                  <input type="checkbox" name="' . $post_type . '_fetch_metadata_from_arxiv"' . (empty($eprint) ? 'checked' : '' ) . '>Fetch title, authors, and abstract from the arXiv upon next Save/Update';
		echo '			<p>(The arXiv identifier including the version and, for old eprints, the the prefix, so this should look like 1701.1234v5 or quant-ph/123456v3.)</p>';
		echo '		</td>';
		echo '	</tr>';

    }

        /**
         * Echo the Ferma's library part of the admin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_fermats_library ( $post_id ) {

        $post_type = get_post_type($post_id);

        $fermats_library = get_post_meta( $post_id, $post_type . '_fermats_library', true );
		$fermats_library_permalink = get_post_meta( $post_id, $post_type . '_fermats_library_permalink', true );
		$fermats_library_permalink_worked = get_post_meta( $post_id, $post_type . '_fermats_library_permalink_worked', true );
		$fermats_library_has_been_notifed_date = get_post_meta( $post_id, $post_type . '_fermats_library_has_been_notifed_date', true );
		if( empty( $fermats_library ) ) $fermats_library = '' ;
		if( empty( $fermats_library_permalink ) ) $fermats_library_permalink = '' ;
		if( empty( $fermats_library_permalink_worked ) ) $fermats_library_permalink_worked = 'false' ;

        echo '	<tr>';
		echo '		<th><label for="' . $post_type . '_fermats_library" class="' . $post_type . '_fermats_library_label">' . 'Fermat&#39;s library' . '</label></th>';
		echo '		<td>';
		echo '                  <input type="checkbox" name="' . $post_type . '_fermats_library" value="checked"' . $fermats_library . '>Opt-in for Fermat&#39;s library.' . ( !empty($fermats_library_has_been_notifed_date) ? " Fermat&#39;s library has been automatically notified on " . $fermats_library_has_been_notifed_date . '.' : ' Fermat&#39;s library has not been notified so far.' ) . '<br />';
		echo '			<input ' . (!empty($fermats_library_has_been_notifed_date) ? 'readonly' : '' ) . ' style="width:100%;" type="text" id="' . $post_type . '_fermats_library_permalink" name="' . $post_type . '_fermats_library_permalink" class="' . $post_type . '_fermats_library_permalink_field" placeholder="' . esc_attr__( '', 'qj-plugin' ) . '" value="' . esc_attr__( $fermats_library_permalink ) . '"><br />(If you leave blank the permalink field it is automatically generated when the email is sent and can then no longer be modified.)';
		echo '		</td>';
		echo '	</tr>';

    }

        /**
         * Echo the abstract part of the admin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_abstract( $post_id ) {

        $post_type = get_post_type($post_id);

        $abstract = get_post_meta( $post_id, $post_type . '_abstract', true );
		$abstract_mathml = get_post_meta( $post_id, $post_type . '_abstract_mathml', true );

		if( empty( $abstract ) ) $abstract = '';
		if( empty( $abstract_mathml ) ) $abstract_mathml = '';

        echo '	<tr>';
        echo '		<th><label for="' . $post_type . '_abstract" class="' . $post_type . '_abstract_label">' . 'Abstract' . '</label></th>';
		echo '		<td>';
		echo '			<textarea rows="10" style="width:100%;" name="' . $post_type . '_abstract" id="' . $post_type . '_abstract" class="preview_and_mathml required">' . esc_html($abstract) . '</textarea><p>(Just like the title, the abstract may contain special characters typed out as é or ç for example. Do not use LaTeX notation for special characters. In contrary, mathematical formulas must be entered in LaTeX notation surrounded by $ signs. Type \\$ for an actual dollar symbol. Beware that the automatic import sometimes confuses a \\langle or \\rangle with a smaller or larger sign and fix these manually. If a formula is detected a live preview and the corresponding MathML code is shown above this help text.)</p>';
		echo '		</td>';
		echo '	</tr>';

    }

        /**
         * Echo the feature image caption part of the amdin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_feature_image_caption( $post_id ) {

        $post_type = get_post_type($post_id);

        $feature_image_caption = get_post_meta( $post_id, $post_type . '_feature_image_caption', true );

        if( empty( $feature_image_caption ) ) $feature_image_caption = '' ;

		echo '	<tr>';
		echo '		<th><label for="' . $post_type . '_feature_image_caption" class="' . $post_type . '_feature_image_caption_label">' . 'Feature image caption' . '</label></th>';
		echo '		<td>';
		echo '			<textarea rows="6" style="width:100%;" name="' . $post_type . '_feature_image_caption" id="' . $post_type . '_feature_image_caption">' . esc_attr__( $feature_image_caption ) . '</textarea><p>(Please upload images sent by the authors as feature image via the button on the right. Please add here a caption in case the ' . $this->get_publication_type_name() . ' has a feature image.)</p>';
		echo '		</td>';
		echo '	</tr>';

    }

        /**
         * Echo the popular summary part of the admin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_popular_summary( $post_id ) {

        $post_type = get_post_type($post_id);

        $popular_summary = get_post_meta( $post_id, $post_type . '_popular_summary', true );

		if( empty( $popular_summary ) ) $popular_summary = '' ;

        echo '	<tr>';
		echo '		<th><label for="' . $post_type . '_popular_summary" class="' . $post_type . '_popular_summary_label">' . 'Popular summary' . '</label></th>';
		echo '		<td>';
		echo '			<textarea rows="6" style="width:100%;" name="' . $post_type . '_popular_summary" id="' . $post_type . '_popular_summary">' . esc_attr__( $popular_summary ) . '</textarea><p>(Popular summary if provided by the authors.)</p>';
		echo '		</td>';
		echo '	</tr>';
    }

        /**
         * Echo the arXiv part of the admin panel.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected function the_admin_panel_arxiv( $post_id ) {

        $post_type = get_post_type($post_id);

        $arxiv_fetch_results = get_post_meta( $post_id, $post_type . '_arxiv_fetch_results', true );
		$arxiv_pdf_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', true );
		$arxiv_source_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_source_attach_ids', true );

		if ( !empty($arxiv_fetch_results) ) {
			echo '	<tr>';
			echo '		<th><label for="' . $post_type . '_arxiv_fetch_results" class="' . $post_type . '_arxiv_fetch_results_label">' . 'ArXiv fetch result' . '</label></th>';
			echo '		<td>';
			echo '			<textarea rows="' . (substr_count( $arxiv_fetch_results, "\n" )+1) . '" cols="65" readonly>' . esc_attr__( $arxiv_fetch_results ) . '</textarea><p>(The result of fetching metadata from the arXiv.)</p>';
			echo '		</td>';
			echo '	</tr>';
		}

		if ( !empty($arxiv_pdf_attach_ids) ) {
			echo '	<tr>';
			echo '		<th><label for="' . $post_type . '_arxiv_pdf_ids" class="' . $post_type . '_arxiv_pdf_ids_label">' . 'PDFs from arXiv' . '</label></th>';
			echo '		<td>';
			echo '                  <input type="checkbox" name="' . $post_type . '_download_arxiv_pdf">Download the pdf from the arXiv again upon next Save/Update.';
			foreach ($arxiv_pdf_attach_ids as $arxiv_pdf_attach_id) {
				echo '<p>ID: <a href="post.php?post=' . $arxiv_pdf_attach_id . '%26action=edit" target="_blank">' . $arxiv_pdf_attach_id . '</a> Url: <a href="' . wp_get_attachment_url( $arxiv_pdf_attach_id ) . '" target="_blank">' . wp_get_attachment_url( $arxiv_pdf_attach_id ) . "</a></p>\n";
			}
			echo '		</td>';
			echo '	</tr>';
		}
		if ( !empty($arxiv_source_attach_ids) ) {
			echo '	<tr>';
			echo '		<th><label for="' . $post_type . '_arxiv_source_ids" class="' . $post_type . '_arxiv_source_ids_label">' . 'Source files from arXiv' . '</label></th>';
			echo '		<td>';
			echo '                  <input type="checkbox" name="' . $post_type . '_download_arxiv_source">Download the source from the arXiv again upon next Save/Update.';
			foreach ($arxiv_source_attach_ids as $arxiv_source_attach_id) {
				echo '<p>ID: <a href="post.php?post=' . $arxiv_source_attach_id . '%26action=edit" target="_blank">' . $arxiv_source_attach_id . '</a> Url: <a href="' . wp_get_attachment_url( $arxiv_source_attach_id ) . '" target="_blank">' . wp_get_attachment_url( $arxiv_source_attach_id ) . "</a></p>\n";
			}
			echo '		</td>';
			echo '	</tr>';
		}

    }

        /**
         * Get the url of the latest arXiv pdf.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    public function get_last_arxiv_pdf_url( $post_id ) {

        $post_type = get_post_type($post_id);

        $arxiv_pdf_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', true );
        if ( empty($arxiv_pdf_attach_ids) )
            return '';
        $last_url = '';
        foreach ($arxiv_pdf_attach_ids as $arxiv_pdf_attach_id) {
            $last_url = wp_get_attachment_url( $arxiv_pdf_attach_id );
        }

        return $last_url;
    }

        /**
         * Get the path of the last arXiv pdf.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    public static function get_last_arxiv_pdf_path( $post_id ) {
        $post_type = get_post_type($post_id);

        $arxiv_pdf_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_pdf_attach_ids', true );
        if ( empty($arxiv_pdf_attach_ids) )
            return '';
        $last_path = '';
        foreach ($arxiv_pdf_attach_ids as $arxiv_pdf_attach_id) {
            $last_path = get_attached_file( $arxiv_pdf_attach_id );
        }

        return $last_path;
    }

        /**
         * Get the url of the last arXiv soruce.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    protected static function get_last_arxiv_source_url( $post_id ) {
        $post_type = get_post_type($post_id);

        $arxiv_source_attach_ids = get_post_meta( $post_id, $post_type . '_arxiv_source_attach_ids', true );
        if ( empty($arxiv_source_attach_ids) )
            return '';
        $last_url = '';
        foreach ($arxiv_source_attach_ids as $arxiv_source_attach_id) {
		$last_url = wp_get_attachment_url( $arxiv_source_attach_id );
        }

        return $last_url;
    }

        /**
         * Decide whether or not the Fermat's library permalink should be shown.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    public static function show_fermats_library_permalink( $post_id ) {

        $post_type = get_post_type($post_id);

        $fermats_library = get_post_meta( $post_id, $post_type . '_fermats_library', true );
        $fermats_library_permalink = get_post_meta( $post_id, $post_type . '_fermats_library_permalink', true );
        $fermats_library_has_been_notifed_date = get_post_meta( $post_id, $post_type . '_fermats_library_has_been_notifed_date', true );
        $fermats_library_permalink_worked = get_post_meta( $post_id, $post_type . '_fermats_library_permalink_worked', true );

        if( $fermats_library === 'checked' and $fermats_library_permalink_worked === 'true' and !empty($fermats_library_has_been_notifed_date) and !empty($fermats_library_permalink) )
            return true;
        if ( $fermats_library === 'checked' and !empty($fermats_library_has_been_notifed_date) and !empty($fermats_library_permalink) )
            $response = wp_remote_get( $fermats_library_permalink );

        if ( !empty($response) && wp_remote_retrieve_response_code($response) == 200 ) {
            update_post_meta( $post_id, $post_type . '_fermats_library_permalink_worked', "true" );
            return true;
        }
        else
            return false;
    }

        /**
         * Get the content for for the rss feed.
         *
         * To be added to the 'the_content_feed' and 'the_excerpt_rss' filter.
         *
         * @since     0.1.0
         * @access    public
         * @param     string     $content     Content to be ammended.
         */
    public function get_feed_content( $content ) {

        global $post;
        $post_id = $post->ID;
        $post_type = get_post_type($post_id);

        if ( $post_type === $this->get_publication_type_name() ) {
            $old_content = $content;
            $abstract = get_post_meta( $post_id, $post_type . '_abstract', true );
            $doi = static::get_doi( $post_id );
            $content = '';
            $content .= '<p>' . static::get_formated_citation($post_id) . '</p>';
            $content .= '<a href="' . $this->get_journal_property('doi_url_prefix') . $doi . '">' . $this->get_journal_property('doi_url_prefix') . $doi . '</a>';
            $content .= '<p>' . esc_html($abstract) . '</p>';
            $content .= $old_content;
            return $content;
        }
        else
            return $content;

    }

        /**
         * Get the excertp
         *
         * Used to generate the excerpt for lists of posts.
         *
         * To be added to the 'get_the_excerpt' filter. Use this filter instead of 'the_excerpt' to also affect get_the_excerpt()!
         *
         * @since 0.1.0
         * @access    public
         * @param     string     $content     Content to be ammended.
         */
    public static function get_the_excerpt( $content ) {

        global $post;
        $post_id = $post->ID;
        $post_type = get_post_type($post_id);

        if ( $post_type === $this->get_publication_type_name() ) {
            $old_content = $content;
            $content = '';
            $content .= '<p class="authors-in-excerpt">' . static::get_formated_authors( $post_id ) . ',</p>' . "\n";
            $content .= '<p class="citation-in-excerpt">' . static::get_formated_citation($post_id) . ' <a href="' . $this->get_journal_property('doi_url_prefix') . static::get_doi($post_id) . '">' . $this->get_journal_property('doi_url_prefix') . static::get_doi($post_id) . '</a>' . "\n";
            $content .= '<p><a href="' . get_permalink($post_id) . '" class="abstract-in-excerpt">';
            $trimmer_abstract = wp_html_excerpt( get_post_meta( $post_id, $post_type . '_abstract', true ), 190, '&#8230;');
            while( preg_match_all('/(?<!\\\\)\$/', $trimmer_abstract) % 2 !== 0 )
            {
                empty($i) ? $i = 1 : $i += 1;
                $trimmer_abstract = wp_html_excerpt( get_post_meta( $post_id, $post_type . '_abstract', true ), 190+$i, '&#8230;');
            }
            $content .= esc_html ( $trimmer_abstract );
            $content .= '</a></p>';
            $content .= $old_content;
        }

        return $content;
    }

        /**
         * Get the pretty permalink of the pdf associated with a post.
         *
         * For Google Scholar the full text must be available in a
         * subdirectory of the abstract page and anyway it is nice to have a
         * consistent api for downloading the fulltext pdf. The following
         * functions are added to the 'init' and 'parse_request' hooks and
         * thereby make any url of the form [post-type-name]/<doi-suffix>/pdf/ return
         * the associated pdf.
         *
         * @since     0.1.0
         * @access    public
         * @param     int     $post_id     Id of the post.
         */
    public function get_pdf_pretty_permalink( $post_id ) {

        $post_type = get_post_type($post_id);
        if ( $post_type !== $this->get_publication_type_name() || empty(static::get_last_arxiv_pdf_url( $post_id )) )
            return '';

        return get_permalink( $post_id ) . "pdf/";
    }

        /**
         * Add a /pdf endpoint for serving the full text pdf.
         *
         * To be added to the 'init' action.
         *
         * @since    0.1.0
         * @access   public
         */
    public function add_pdf_endpoint() {

        add_rewrite_endpoint( 'pdf', EP_PERMALINK | EP_PAGES );
            // flush_rewrite_rules( true );  //// <---------- REMOVE THIS WHEN DONE TESTING

    }


        /**
         * Handle request to the /pdf endpoint for serving the full text pdf.
         *
         * To be added to the 'parse_request' action.
         *
         * @since     0.1.0
         * @access    public
         * @param     WP_Query   $wp_query   The WP_Query to be handled.
         */
   public function handle_pdf_endpoint_request( $wp_query ) {

        if ( !isset( $wp_query->query_vars[ 'pdf' ] ) )
            return;
        if ( !isset($wp_query->query_vars[ 'post_type' ]) or $wp_query->query_vars[ 'post_type' ] !== $this->get_publication_type_name())
            return;

        $post_id = url_to_postid( '/' . $this->get_publication_type_name_plural() . '/' . $wp_query->query_vars[ $this->get_publication_type_name() ] . '/');
        if ( empty($post_id) )
        {
            header('Content-Type: text/plain');
            echo "ERROR: post_id is empty";
            exit();
        }
        $post_type = get_post_type($post_id);
        $doi_suffix = get_post_meta( $post_id, $post_type . '_doi_suffix', true );
        $file_path = static::get_last_arxiv_pdf_path($post_id);
        if ( empty($file_path) )
        {
            header('Content-Type: text/plain');
            echo "ERROR: file_path is empty";
            exit();
        }

        header('Content-Type: application/pdf');
        header("Content-Disposition: inline; filename=" . $doi_suffix . ".pdf" );//always return the same file name even if local revision number has changed
        readfile($file_path);
        exit();

    }


       /**
        * Add /web-statement end point for serving a web statement of the licence.
        *
        * To be added to the 'init' action.
        *
        * @since 0.1.0
        */
    public static function add_web_statement_endpoint() {

        add_rewrite_endpoint( 'web-statement', EP_PERMALINK | EP_PAGES );
            //flush_rewrite_rules( true );  //// <---------- REMOVE THIS WHEN DONE TESTING

    }

       /**
        * Handle requests to the /web-statement end point for serving a web statement of the licence.
        *
        * To be added to the 'parse_request' action.
        *
        * @since     0.1.0
        * @access    public
        * @param     WP_Query   $wp_query   The WP_Query to be handled.
        * */
    public function handle_web_statement_endpoint_request( $wp_query ) {

        if ( !isset( $wp_query->query_vars[ 'web-statement' ] ) )
            return;
        if ( !isset($wp_query->query_vars[ 'post_type' ]) or $wp_query->query_vars[ 'post_type' ] !== $this->get_publication_type_name())
            return;

        $post_id = url_to_postid( '/' . $this->get_publication_type_name_plural() . '/' . $wp_query->query_vars[ $this->get_publication_type_name() ] . '/');
        if ( empty($post_id) )
        {
            header('Content-Type: text/plain');
            echo "ERROR: post_id is empty";
            exit();
        }
        $post_type = get_post_type($post_id);
        $doi_suffix = get_post_meta( $post_id, $post_type . '_doi_suffix', true );
        $file_path = static::get_last_arxiv_pdf_path($post_id);
        if ( empty($file_path) )
        {
            header('Content-Type: text/plain');
            echo "ERROR: file_path is empty";
            exit();
        }

        $sha1 = get_transient($post_id . '_web_statement_sha1');
        if(empty($sha1))
        {
            $sha1 = strtoupper(O3PO_Utility::base_convert_arbitrary_precision(sha1_file($file_path), 16, 32));
            set_transient($post_id . '_web_statement_sha1', $sha1, 10*60);
        }

        header('Content-Type: text/html');
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n";
        echo '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n";
        echo '<head></head>' . "\n";
        echo '<body>' . "\n";
        echo '<span about="urn:sha1:' . $sha1 . '">' . "\n";
        echo $doi_suffix . '.pdf' . ' is licensed under' . "\n";
        echo '<a about="urn:sha1:' . $sha1 . '" rel="license" href="' . esc_attr($this->get_journal_property('license_url')) . '">' . esc_html($this->get_journal_property('license_name')) . '</a>' . "\n";
        echo '</span>' . "\n";
        echo '</body>' . "\n";
        echo '</html>' . "\n";
        exit();

    }

        /**
        * Add /arxiv_paper_doi_feed end point for serving a feed of recent papers for the arXiv.
        *
        * To be added to the 'init' action.
        *
        * @since     0.1.0
        * @access    public
        */
    public static function add_axiv_paper_doi_feed_endpoint() {

        add_rewrite_endpoint( 'arxiv_paper_doi_feed', EP_ROOT );
            // flush_rewrite_rules( true );  //// <---------- ONLY COMMENT IN WHILE TESTING

    }

        /**
        * Handle requests to the /arxiv_paper_doi_feed end point for serving a feed of recent papers for the arXiv.
        *
        * To be added to the 'parse_request' action.
        *
        * @since    0.1.0
        * @access   public
        * @param    WP_Query   $wp_query   The WP_Query to be handled.
        */
    public function handle_arxiv_paper_doi_feed_endpoint_request( $wp_query ) {

        if ( !isset( $wp_query->query_vars[ 'arxiv_paper_doi_feed' ] ) )
            return;

        $date=getdate();

        header('Content-Type: text/xml');
        header("Content-Disposition: inline; filename=arxiv_doi_feed.xml" );
        $identifier = $this->get_journal_property('arxiv_doi_feed_identifier');
        echo '<?xml version="1.0" encoding="UTF-8"?>'. "\n";
        echo '<preprint xmlns="http://arxiv.org/doi_feed" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" identifier="' . $identifier . '" version="DOI SnappyFeed v1.0" xsi:schemaLocation="http://arxiv.org/doi_feed http://arxiv.org/schemas/doi_feed.xsd">' . "\n";
        echo '  <date year="' . $date['year'] . '" month="' . $date['mon'] . '" day="' . $date['mday'] . '"/>' . "\n";

        query_posts(array('post_status' => 'publish', 'post_type' => $this->get_publication_type_name(), 'date_query'    => array(
                              'column'  => 'post_date',
                              'after'   => '- 90 days'                                                                         ) ));
        while(have_posts()) {
            the_post();
            $post_id = get_the_ID();
            $post_type = get_post_type($post_id);
            $eprint = get_post_meta( $post_id, $post_type . '_eprint', true );
            $eprint_without_version = preg_replace('#v[0-9]*$#', '', $eprint);
            $citation = rtrim(static::get_formated_citation($post_id), '.');
            $doi = static::get_doi($post_id);
            echo '  <article doi="' . $doi .'" preprint_id="arXiv:' . $eprint_without_version . '" journal_ref="' . $citation . '"/>' . "\n";
        }
        wp_reset_query();


        echo '</preprint>' . "\n";
        exit();

    }

        /**
         * Output meta tags describing this publication type.
         *
         * @since     0.1.0
         * @access    public
         */
    public function the_meta_tags() {

        $post_id = get_the_ID();
        $post_type = get_post_type($post_id);
        $eprint = get_post_meta( $post_id, $post_type . '_eprint', true );

        if ( !is_single() || $post_type !== $this->get_publication_type_name())
            return;

        parent::the_meta_tags();

        $pdf_url = static::get_pdf_pretty_permalink($post_id);

            // Highwire Press tags
        if(!empty($pdf_url)) echo '<meta name="citation_pdf_url" content="' . $pdf_url . '">'."\n";
        if(!empty($eprint)) echo '<meta name="citation_arxiv_id" content="' . $eprint . '">'."\n";

    }
}
