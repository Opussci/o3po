<?php

require_once dirname( __FILE__ ) . '/../o3po/includes/class-o3po-relevanssi.php';

class O3PO_RelevanssiTest extends PHPUnit_Framework_TestCase
{

    public function test_initialize_settings()
    {
        $file_data = get_file_data(dirname( __FILE__ ) . '/../o3po/o3po.php', array(
                                       'Version' => 'Version',
                                       'Plugin Name' => 'Plugin Name',
                                       'Text Domain' => 'Text Domain'
                                                   ));

        $settings = O3PO_Settings::instance();
        $settings->configure($file_data['Text Domain'], $file_data['Plugin Name'], $file_data['Version'], array( $this, 'fake_get_active_publication_type_names'));

        $this->assertInstanceOf(O3PO_Settings::class, $settings);

        return $settings;
    }


    public function test_exclude_mime_types_by_regexp() {

        $this->assertFalse(O3PO_Relevanssi::exclude_mime_types_by_regexp(false, 6));
        $this->assertTrue(O3PO_Relevanssi::exclude_mime_types_by_regexp(false, 7));
        $this->assertTrue(O3PO_Relevanssi::exclude_mime_types_by_regexp(false, 13));

    }

    public function test_index_pdf_attachment_if_not_already_done() {

        $this->assertFalse(O3PO_Relevanssi::index_pdf_attachment_if_not_already_done(6)); #this is a pdf attachment but relevanssi_index_pdf is not defined

            /**
             * Mirrors the implementation in pdf-upload.php by relevanssi
             */
        function relevanssi_index_pdf($post_id, $ajax = false, $send_file = null ) {

            global $posts;

            if( $posts[$post_id]['post_type'] !== 'attachment' or $posts[$post_id]['mime_type'] !== 'application/pdf' )
                return array('success' => false);

            update_post_meta($post_id, '_relevanssi_pdf_content', "this is the fake pdf content");

            return array('success' => true);
        }

        $this->assertFalse(O3PO_Relevanssi::index_pdf_attachment_if_not_already_done(2)); #this is not a pdf attachment

        $this->assertTrue(O3PO_Relevanssi::index_pdf_attachment_if_not_already_done(6));  #this is a pdf attachment that was not yet indexed

        $this->assertFalse(O3PO_Relevanssi::index_pdf_attachment_if_not_already_done(6));  #now it has already been indexed
    }


}
