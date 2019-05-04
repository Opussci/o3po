<?php

/**
 * A class to represent bibliography entries.
 *
 * @link       https://quantum-journal.org/o3po/
 * @since      0.3.0
 *
 * @package    O3PO
 * @subpackage O3PO/includes
 */

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-o3po-author.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-o3po-utility.php';

/**
 * A class to represent bibliography entries.
 *
 * @package    O3PO
 * @subpackage O3PO/includes
 * @author     Christian Gogolin <o3po@quantum-journal.org>
 */
class O3PO_Bibentry {

        /**
         * Array holding the meta-data of this bibtentry
         *
         * @sinde 0.3.0
         * @access private
         */
    private $meta_data;


        /**
         * Array of all supported meta-data fields
         *
         * @sinde 0.3.0
         * @access private
         */
    private static $meta_data_fields = array(
        'authors',
        'chapter',
        'collectiontitle',
        'day',
        'doi',
        'editors',
        'eprint',
        'howpublished',
        'institution',
        'isbn',
        'issn',
        'issue',
        'month',
        'page',
        'publisher',
        'ref',
        'title',
        'type',
        'url',
        'venue',
        'volume',
        'year',
    );

        /**
         * Construct a bibtentry.
         *
         * Only the entries in $meta_data whose keys are listed in
         * $meta_data_fields are taken into account.
         * All fields that are not arrays are converted to string.
         * Fields that are in $meta_data_fields but for which no data
         * is provided are initialized to an empty string.
         *
         * @sinde 0.3.0
         * @access public
         * @param array $meta_data The meta-data to store in this bibentry
         */
    public function __construct( $meta_data=array() ) {

        foreach(static::$meta_data_fields as $field)
            if(isset($meta_data[$field]))
            {
                if(is_array($meta_data[$field]))
                    $this->meta_data[$field] = $meta_data[$field];
                else
                    $this->meta_data[$field] = (string)$meta_data[$field];
            }
            else
                $this->meta_data[$field] = '';
    }

        /**
         * Get the value of a given field
         *
         * @since 0.3.0
         * @access public
         * @param string $field The key of the field to get.
         * @return mixed string or array stored in the field.
         */
    public function get( $field ) {

        return $this->meta_data[$field];
    }

        /**
         * Oxford comma separated list of all author and editor surnames.
         *
         * @since 0.3.0
         * @access public
         * @return string Oxford comma separated list of all author surnames.
         */
    public function get_surnames() {

        $surnames = array();
        if(!empty($this->get('authors')) and is_array($this->get('authors')))
            foreach ($this->get('authors') as $author) {
                $surnames[] = $author->get_surname();
            }
        if(!empty($this->get('editors')) and is_array($this->get('editors')))
            foreach ($this->get('editors') as $editor) {
                $surnames[] = $editor->get_surname();
            }

        return O3PO_Utility::oxford_comma_implode($surnames);
    }


        /**
         * Formated author and editor list
         *
         * Editors are indicated with the string Editor: or Editors: before their names
         *
         * @since 0.3.0
         * @access public
         * @return string Oxford comma separated list of all authors and editors.
         */
    public function get_formated_authors() {

        $result = '';
        $author_names = array();
        if(!empty($this->get('authors')) and is_array($this->get('authors')))
        {
            $author_names = array();
            foreach ($this->get('authors') as $author) {
                $author_names[] = $author->get_name();
            }
            $result .= O3PO_Utility::oxford_comma_implode($author_names);
        }
        $editor_names = array();
        if(!empty($this->get('editors')) and is_array($this->get('editors')))
        {
            foreach ($this->get('editors') as $editor) {
                $editor_names[] = $editor->get_name();
            }
            if(count($author_names) > 0)
                $result .= ', ';
            if(count($editor_names) == 1)
                $result .= 'Editor: ';
            elseif(count($editor_names) > 1)
                $result .= 'Editors: ';
            $result .= O3PO_Utility::oxford_comma_implode($editor_names);
        }

        return trim($result);
    }


        /**
         * HTML representation of the bibentry.
         *
         * @since 0.3.0
         * @access public
         * @param $doi_url_prefix        Prefix to use for DOI links.
         * @param $arxiv_url_abs_prefix  Prefix to use for arXiv links.
         * @return string                HTHL representation of the bibentry.
         */
    public function get_formated_html( $doi_url_prefix, $arxiv_url_abs_prefix ) {

        $bibitem_html = '';

        $bibitem_html .= esc_html($this->get_formated_authors());
        if(!empty($bibitem_html))
            $bibitem_html .= ', ';

        if(!empty($this->get('title')))
            $bibitem_html .= '"' . esc_html($this->get('title')) . '", ';

        if(!empty($this->get('eprint')))
            $bibitem_html .= '<a href="' . esc_attr($arxiv_url_abs_prefix . $this->get('eprint')) . '">' . esc_html("arXiv:" . $this->get('eprint')) . '</a>';
        if(!empty($this->get('doi')) and !empty($this->get('eprint')))
            $bibitem_html .= ", ";
        if(!empty($this->get('doi')))
            $bibitem_html .= '<a href="' . esc_attr($doi_url_prefix . $this->get('doi')) . '">' . esc_html($this->get_cite_as_text()) . '</a>';

        if(empty($this->get('doi')) and empty($this->get('eprint')))
            $bibitem_html .= esc_html($this->get_cite_as_text());

        $bibitem_html = trim($bibitem_html, ' ,') . '.';

        return $bibitem_html;
    }


        /**
         * How to cite this bibentry.
         *
         * @since 0.3.0
         * @access public
         * @return string  Text describing how to cite this bibentry.
         */
    public function get_cite_as_text() {

        $citation_cite_as = '';

        if(!empty($this->get('type')) and strtolower($this->get('type')) !== 'book')
            $citation_cite_as .= ucfirst($this->get('type')) . " ";
        if(!empty($this->get('venue')))
            $citation_cite_as .= $this->get('venue') . " ";
        if(!empty($this->get('collectiontitle')))
            $citation_cite_as .= $this->get('collectiontitle') . " ";
        if(!empty($this->get('publisher')) and $this->get('type') == "book")
            $citation_cite_as .= $this->get('publisher') . " ";
        if(!empty($this->get('institution')))
            $citation_cite_as .= $this->get('institution') . " ";
        if(!empty($this->get('howpublished')))
            $citation_cite_as .= "(" . $this->get('howpublished') . ") ";
        if(!empty($this->get('volume')))
            $citation_cite_as .= $this->get('volume');
        if(!empty($this->get('volume')) and !empty($this->get('issue')))
            $citation_cite_as .= " ";
        if(!empty($this->get('issue')))
            $citation_cite_as .= $this->get('issue');
        if((!empty($this->get('volume')) or !empty($this->get('issue'))) and !empty($this->get('page')))
            $citation_cite_as .= ", ";
        if(!empty($this->get('page')))
            $citation_cite_as .= $this->get('page') . " ";
        /* if(!empty($this->get('eprint'))) */
        /*     $citation_cite_as .= 'arXiv:'. $this->get('eprint') . " "; */
        if(!empty($this->get('year')))
            $citation_cite_as .= '(' . $this->get('year') . ")";
        /* if(!empty($this->get('doi'))) */
        /*     $citation_cite_as .= ' doi:'. $this->get('doi'); */
        if(!empty($this->get('isbn')))
            $citation_cite_as .= ' ISBN:'. $this->get('isbn');
        /* if(!empty($this->get('url'))) */
        /*     $citation_cite_as .= $this->get('url') . ' '; */

        return trim($citation_cite_as, ' ');
    }


        /**
         * Merge two bibentries.
         *
         * Bibentries are merged field wise, in case of collisions
         * $bibitem1 takes preference over $bibitem2.
         *
         * @since 0.3.0
         * @access public
         * @param O3PO_Bibentry $bibitem1 First bibentry to merge.
         * @param O3PO_Bibentry $bibitem2 First bibentry to merge.
         * @return O3PO_Bibentry          Merged bibentry.
         */
    public static function merge($bibitem1, $bibitem2) {

        $merged_meta_data = array();
        foreach(static::$meta_data_fields as $field)
        {
            $merged_meta_data[$field] = $bibitem1->get($field);
            if(empty($merged_meta_data[$field]))
                $merged_meta_data[$field] = $bibitem2->get($field);
        }

        return new O3PO_Bibentry($merged_meta_data);
    }

        /**
         * Compare two bibentries.
         *
         * @since 0.3.0
         * @access public
         * @return bool     True if bibentries are considered similar enough to probably represent the same bibliographic item, false otherwise.
         */
    public static function match($bibitem1, $bibitem2) {

        if(!empty($bibitem1->get('eprint')) and !empty($bibitem2->get('eprint')))
        {
            if($bibitem1->get('eprint') === $bibitem2->get('eprint'))
                return true;
            else
                return false;
        }
        elseif(!empty($bibitem1->get('doi')) and !empty($bibitem2->get('doi')))
        {
            if($bibitem1->get('doi') === $bibitem2->get('doi'))
                return true;
            else
                return false;
        }
        else #now we do some heuristics to catch the remaining duplicates:
        {
            $years_similar = false;
            if(!empty($bibitem1->get('year')) and !empty($bibitem2->get('year')) and abs($bibitem1->get('year') - $bibitem2->get('year'))<=5 )
                $years_similar = true;

            if($years_similar)
            {
                $titles_similar = false;
                $titles_very_similar = false;
                if(!empty($bibitem1->get('title')) and !empty($bibitem2->get('title')))
                {
                    $t1 = substr(strtolower($bibitem1->get('title')), 0, 255);
                    $t2 = substr(strtolower($bibitem2->get('title')), 0, 255);
                    $l1 = strlen($t1); #length in bytes
                    $l2 = strlen($t2);
                    $lmin = min($l1, $l2);
                    $lev = levenshtein($t1, $t2);
                    if($lev <= 0.2*$lmin or $lev <= 5)
                        $titles_similar = true;
                    if($lev <= 0.1*$lmin)
                        $titles_very_similar = true;
                }
                if($titles_similar)
                {
                    $authors_similar = false;
                    $authors_very_similar = false;
                    if(!empty($bibitem1->get_surnames()) and !empty($bibitem2->get_surnames()))
                    {
                        $a1 = substr(strtolower($bibitem1->get_surnames()), 0, 255);
                        $a2 = substr(strtolower($bibitem2->get_surnames()), 0, 255);
                        $l1 = strlen($a1); #length in bytes
                        $l2 = strlen($a2);
                        $lmin = min($l1, $l2);
                        $lev = levenshtein($a1, $a2);
                        if($lev <= 0.2*$lmin or $lev <= 2)
                            $authors_similar = true;
                        if($lev <= 0.1*$lmin)
                            $authors_very_similar = true;
                    }
                    if(($titles_similar and $authors_similar and ( $titles_very_similar or $authors_very_similar )))
                        return true;
                }
            }
        }

        return false;
    }


        /**
         * Merge two arrays of bibentries.
         *
         * Merges $array2 into $array1. The content of the entries in $array1
         * takes preference over those in $array2, entries in $array2 that
         * were not merged are appended at the end. Array keys in $array1
         * are preserved.
         *
         * If $remove_dulicates is true all duplicates are removed from the
         * final array by merging them into the first matching entry.
         * Non-removed array keys are preserved.
         *
         * @param array $array1           First array of bibtentries.
         * @param array $array2           Second array of bibtentries.
         * @param bool $remove_dulicates  Whether to remove duplicates.
         * @return array                  Merged array of bibentries.
         */
    public static function merge_bibitem_arrays($array1, $array2, $remove_dulicates=true ) {

        if(empty($array1) and empty($array2))
            return array();
        if(empty($array1))
            if($remove_dulicates)
                return static::remove_duplicates($array2, true);
            else
                return $array2;
        if(empty($array2))
            if($remove_dulicates)
                return static::remove_duplicates($array1, true);
            else
                return $array1;

        $merged = $array1;
        foreach($array2 as $key2 => $bibitem2){
            $merged_at_least_once = false;
            foreach($array1 as $key1 => $bibitem1){
                if(static::match($bibitem1, $bibitem2))
                {
                    $merged[$key1] = static::merge($bibitem1, $bibitem2);
                    $merged_at_least_once = true;
                }
            }
            if(!$merged_at_least_once)
                $merged[] = $bibitem2;
        }
        if($remove_dulicates)
            $merged = static::remove_duplicates($merged, true);

        return $merged;
    }


        /**
         * Remove duplicates from an array of bibentries.
         *
         * Duplicates are identified by comparing pairs with the help of match().
         *
         * @since 0.3.0
         * @access public
         * @param array $array  Array of bibentries from which duplicates are to be removed.
         * @param bool $merge   Whether to merge duplicates or simply discard them.
         */
    public static function remove_duplicates($array, $merge=false) {

        $keys_to_unset = array();
        $num_elem1=0;
        foreach($array as $key1 => $bibitem1){
            $num_elem2=0;
            foreach($array as $key2 => $bibitem2){
                if($num_elem2 > $num_elem1)
                {
                    if(static::match($bibitem1, $bibitem2))
                    {
                        if($merge)
                            $array[$key1] = static::merge($bibitem1, $bibitem2);
                        $keys_to_unset[$key2] = true;
                    }
                }
                $num_elem2 += 1;
            }
            $num_elem1 += 1;
        }
        foreach($keys_to_unset as $key => $val)
        {
            unset($array[$key]);
        }

        return $array;
    }

}
