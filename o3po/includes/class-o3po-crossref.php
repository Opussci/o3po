<?php

/**
 * Encapsulates the interface with the external service Crossref.
 *
 * @link       http://example.com
 * @since      0.3.0
 *
 * @package    O3PO
 * @subpackage O3PO/includes
 */

/**
 * Encapsulates the interface with the external service Crossref.
 *
 * Provides methods to interface with Crossref.
 *
 * @package    O3PO
 * @subpackage O3PO/includes
 * @author     Christian Gogolin <o3po@quantum-journal.org>
 */
class O3PO_Crossref {

        /**
         * Http post meta data to Crossref.
         *
         * Submits meta-data in Crossref's xml format via http post to their servers.
         *
         * @since    0.3.0
         * @access   private
         * @param    string   $doi_batch_id    Batch id of this upload.
         * @param    string   $crossref_xml    The xml to upload.
         * @param    string   $crossref_id     The id for which to submit this upload.
         * @param    string   $crossref_pw     The password corresponding to the crossref_id.
         * @param    string   $crossref_url    The url of the crossref server to upload to.
         */
    public static function remote_post_meta_data_to_crossref( $doi_batch_id, $crossref_xml, $crossref_id, $crossref_pw, $crossref_url ) {

            // Construct the HTTP POST call
		$boundary = wp_generate_password(24);
		$headers = array( 'content-type' => 'multipart/form-data; boundary=' . $boundary );
		$post_fields = array(
			'operation' => 'doMDUpload',
 			'login_id' => $crossref_id,
 			'login_passwd' => $crossref_pw,
                             );
        $payload = '';
            // Add the standard POST fields
		foreach ( $post_fields as $name => $value ) {
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $payload .= $value;
            $payload .= "\r\n";
		}
            // Attach the xml data
		if ( !empty($crossref_xml) && !empty($doi_batch_id) ) {
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . 'fname' . '"; filename="' . $doi_batch_id . '.xml' . '"' . "\r\n";
            $payload .= 'Content-Type: text/xml' . "\r\n";
            $payload .= "\r\n";
            $payload .= $crossref_xml;
            $payload .= "\r\n";
		}
		$payload .= '--' . $boundary . '--';

		$response = wp_remote_post( $crossref_url, array(
                                        'headers' => $headers,
                                        'body' => $payload,
                                                         ) );

        return $response;
    }

        /**
         * Retrieve cited-by information for a given doi from Crossref.
         *
         * Uses Crossref's cited-by service.
         *
         * @since    0.3.0
         * @access   private
         * @param    string   $crossref_url    The url of the crossref server from which to fetch cited by information.
         * @param    string   $crossref_id     The id for which to submit this upload.
         * @param    string   $crossref_pw     The password corresponding to the crossref_id.
         * @param    string   $doi             The doi for which cited-by data is to be retrieved.
         */
    private static function remote_get_cited_by( $crossref_url, $crossref_id, $crossref_pw, $doi ) {

        $request_url = $crossref_url . '?usr=' . urlencode($crossref_id).  '&pwd=' . urlencode($crossref_pw) . '&doi=' . urlencode($doi) . '&include_postedcontent=true';

        return  wp_remote_get($request_url);
    }


        /**
         * Retrieve cited-by information for all citations under a given doi prefix.
         *
         * Uses Crossref's cited-by service.
         *
         * @since    0.3.0
         * @access   private
         * @param    string   $crossref_url    The url of the crossref server to upload to.
         * @param    string   $crossref_id     The id for which to submit this upload.
         * @param    string   $crossref_pw     The password corresponding to the crossref_id.
         * @param    string   $doi_prefix      The doi prefix for which cited-by data is to be retrieved.
         * @param    string   $startDate       The date from which on the cited by data is to be included in the format YYYY-mm-dd
         */
    private static function remote_get_all_cited_by( $crossref_url, $crossref_id, $crossref_pw, $doi_prefix, $startDate ) {

        $request_url = $crossref_url . '?usr=' . urlencode($crossref_id).  '&pwd=' . urlencode($crossref_pw) . '&doi=' . urlencode($doi_prefix) . '&startDate=' . $startDate . '&include_postedcontent=true';

        return wp_remote_get($request_url, array('timeout' => 20));
    }

        /**
         * Retrieve cited-by information in xml format from Crossref.
         *
         * Uses Crossref's cited-by service to retrieve information about works
         * citing the given DOI in xml format.
         *
         * See http://data.crossref.org/reports/help/schema_doc/crossref_query_output2.0/query_output2.0.html
         * for more information.
         *
         * @since    0.3.0
         * @access   public
         * @param    string   $crossref_url    The url of the crossref server to upload to.
         * @param    string   $crossref_id     The id for which to submit this upload.
         * @param    string   $crossref_pw     The password corresponding to the crossref_id.
         * @param    string   $doi             The doi for which cited-by data is to be retrieved.
         */
    public static function get_cited_by_xml_body( $crossref_url, $crossref_id, $crossref_pw, $doi ) {

        try
        {
            $response = static::remote_get_cited_by($crossref_url, $crossref_id, $crossref_pw, $doi);

            if(is_wp_error($response))
                return $response;
            else if(empty($response['body']) )
                return '';
            else
            {
                $use_errors=libxml_use_internal_errors(true);
                $xml = simplexml_load_string($response['body']);
                libxml_use_internal_errors($use_errors);
                if ($xml === false) {
                    $error = "Could not load cited-by data from Crossref (maybe the DOI is not yet active?)";
                    foreach(libxml_get_errors() as $e) {
                        $error .= " " . $e->message;
                    }
                    $error .= '.';
                    throw new Exception($error);
                }
                $body = $xml->query_result->body[0];
                return $body;
            }
        } catch (Exception $e) {
            return new WP_Error($e->getMessage());
        }
    }


        /**
         * Retrieve cited-by information in xml format from Crossref.
         *
         * Uses Crossref's cited-by service to retrieve information about works
         * citing the given DOI in xml format.
         *
         * The response from crossref is stored in a transient for 12 hours
         * so that subsequent calls to this method are fast and do not create
         * additional traffic.
         *
         * @since    0.3.0
         * @access   public
         * @param    string   $crossref_url    The url of the crossref server to upload to.
         * @param    string   $crossref_id     The id for which to submit this upload.
         * @param    string   $crossref_pw     The password corresponding to the crossref_id.
         * @param    string   $doi             The doi for which cited-by data is to be retrieved.
         * @param    int      $storage_time    The number of seconds to cache the response by Crossref.
         */
    public static function get_all_citation_counts( $crossref_url, $crossref_id, $crossref_pw, $doi_prefix, $start_date, $storage_time=60*60*12 ) {

        try
        {
            $xml_string = get_transient('all_cited_by_xml_' . $crossref_id . "_" . $doi_prefix . "_" . $start_date);

            if(false === $xml_string)
            {
                $response = static::remote_get_all_cited_by($crossref_url, $crossref_id, $crossref_pw, $doi_prefix, $start_date);

                if(is_wp_error($response))
                    return null;
                else if(empty($response['body']) )
                    return null;
                else
                {
                    $xml_string = $response['body'];
                }
            }

            $use_errors=libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xml_string);
            libxml_use_internal_errors($use_errors);

            if ($xml === false)
                return null;

            set_transient('all_cited_by_xml_' . $crossref_id . "_" . $doi_prefix . "_" . $start_date, $xml_string, $storage_time);

            echo("now printing: ");

            $citations = array();
            foreach($xml->query_result->body->children() as $forward_link )
            {
                $doi = (string)$forward_link->attributes()->doi;
                if(!isset($citations[$doi]))
                    $citations[$doi] = 0;
                $citations[$doi] += 1;
            }

            return $citations;

        } catch (Exception $e) {
            echo("Exception!");

            return null;
        }
    }

}
