<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ctrl_scrape extends CI_Controller {

    public $language;

    public function __construct() {
        parent::__construct();
        $this->language = 'es';
        $this->load->database();
        $this->load->library('session');
        $this->load->model('mod_users');
        $this->load->model('mod_askemdros');
        $this->load->library('db_config');
    }

    public function index($db = 'nestle1904') {
        if (!is_cli()) {
            echo "Este script solo puede ser ejecutado desde la línea de comandos (CLI).\n";
            echo "Ejemplo: php index.php scrape index nestle1904\n";
            return;
        }

        if ($db !== 'nestle1904' && $db !== 'ETCBC4') {
            echo "Solo se soportan nestle1904 y ETCBC4\n";
            return;
        }

        $this->mod_askemdros->setup($db, $db);
        $books = $this->db_config->bookorder;

        foreach ($books as $bookInfo) {
            $bookName = $bookInfo[0];
            $chaptersRange = $bookInfo[1];
            
            $this->scrape_book($db, $bookName, $chaptersRange);
        }
        
        echo "\nProceso finalizado.\n";
    }

    private function scrape_book($db, $bookName, $chaptersRange) {
        echo "Scraping $bookName... ";
        $all_data = array(
            'book' => $bookName,
            'database' => $db,
            'verses' => array()
        );

        if (strpos($chaptersRange, '-') !== false) {
            list($start, $end) = explode('-', $chaptersRange);
        } else {
            $start = $end = $chaptersRange;
        }

        for ($chapter = $start; $chapter <= $end; $chapter++) {
            try {
                $this->mod_askemdros->show_text($db, $bookName, $chapter, 0, 0, false);
                $dict = json_decode($this->mod_askemdros->dictionaries_json);
                
                if (!$dict || !isset($dict->monadObjects[0][0])) continue;

                $words = $dict->monadObjects[0][0];
                $current_verse = -1;
                $verse_data = null;

                foreach ($words as $word) {
                    $bcv = $word->bcv; 
                    $v = $bcv[2];

                    if ($v != $current_verse) {
                        if ($verse_data) {
                            $all_data['verses'][] = $verse_data;
                        }
                        $current_verse = $v;
                        $verse_data = array(
                            'verse' => $v,
                            'words' => array()
                        );
                    }

                    $mo = $word->mo;
                    $features = $mo->features;

                    if ($db === 'ETCBC4') {
                        $word_entry = array(
                            'Palabra' => '',
                            'Texto' => $word->text ?? '',
                            'Transliteración' => $features->g_word_translit ?? '',
                            'Forma in el texto' => array(
                                'Lexical stem' => $features->g_lex_utf8 ?? '',
                                'Formación de raíz' => '-',
                                'Preformativo' => (isset($features->g_pfm_utf8) && $features->g_pfm_utf8 !== 'NA' && $features->g_pfm_utf8 !== '') ? $features->g_pfm_utf8 : '-',
                                'Terminación verbal' => (isset($features->g_vbe_utf8) && $features->g_vbe_utf8 !== 'NA' && $features->g_vbe_utf8 !== '') ? $features->g_vbe_utf8 : '-',
                                'Terminación nominal' => (isset($features->g_nme_utf8) && $features->g_nme_utf8 !== 'NA' && $features->g_nme_utf8 !== '') ? $features->g_nme_utf8 : '-',
                                'Sufijo pronominal' => (isset($features->g_prs_utf8) && $features->g_prs_utf8 !== 'NA' && $features->g_prs_utf8 !== '') ? $features->g_prs_utf8 : '-',
                                'Final univalente' => (isset($features->g_uvf_utf8) && $features->g_uvf_utf8 !== 'NA' && $features->g_uvf_utf8 !== '') ? $features->g_uvf_utf8 : '-',
                                'Qere' => (isset($features->qere_utf8) && $features->qere_utf8 !== 'NA' && $features->qere_utf8 !== '') ? $features->qere_utf8 : '-'
                            ),
                            'Lexema' => array(
                                'Lexema (con variante)' => $features->g_lex_utf8 ?? '',
                                'Lexema (transliterado)' => (isset($features->g_lex_translit) && $features->g_lex_translit !== 'NA') ? $features->g_lex_translit : '',
                                'Ocurrencias' => (isset($features->lexeme_occurrences) && $features->lexeme_occurrences !== 'NA') ? $features->lexeme_occurrences : '',
                                'Rango de frecuencia' => (isset($features->frequency_rank) && $features->frequency_rank !== 'NA') ? $features->frequency_rank : '',
                                'Parte del discurso' => (isset($features->sp) && $features->sp !== 'NA') ? $features->sp : '',
                                'Frase parte dependiente del discurso' => (isset($features->sp) && $features->sp !== 'NA') ? $features->sp : '',
                                'Conjunto lexical' => 'ninguno',
                                'Clase verbal' => 'N/A',
                                'Enlace' => '-'
                            ),
                            'Morfología' => array(
                                'Raíz' => 'Ø',
                                'Tiempo' => (isset($features->vt) && $features->vt !== 'NA') ? $features->vt : 'Ninguno',
                                'Estado' => (isset($features->st) && $features->st !== 'NA') ? $features->st : 'Ninguno',
                                'Persona, género, número' => 
                                    ((isset($features->ps) && $features->ps !== 'NA') ? $features->ps : '-') . 
                                    ((isset($features->gn) && $features->gn !== 'NA') ? $features->gn : '-') . 
                                    ((isset($features->nu) && $features->nu !== 'NA') ? $features->nu : '-'),
                                'Sufijo: Persona, género, número' => 
                                    ((isset($features->prs_ps) && $features->prs_ps !== 'NA') ? $features->prs_ps : '-') . 
                                    ((isset($features->prs_gn) && $features->prs_gn !== 'NA') ? $features->prs_gn : '-') . 
                                    ((isset($features->prs_nu) && $features->prs_nu !== 'NA') ? $features->prs_nu : '-')
                            ),
                            'Glosas' => array(
                                'Español' => $features->spanish ?? ''
                            )
                        );
                    } else { // nestle1904
                        $word_entry = array(
                            'Palabra' => '',
                            'Texto' => $word->text ?? '',
                            'Lexema' => array(
                                'Lexema' => $features->lemma ?? '',
                                'Número de Concordancia Strong' => (isset($features->strongs) && $features->strongs !== 'NA') ? $features->strongs : '',
                                '¿No confiable Strong?' => (isset($features->strongs_unreliable) && $features->strongs_unreliable === 'true') ? 'si' : 'no',
                                'Ocurrencias' => (isset($features->lexeme_occurrences) && $features->lexeme_occurrences !== 'NA') ? $features->lexeme_occurrences : '',
                                'Rango de frecuencia' => (isset($features->frequency_rank) && $features->frequency_rank !== 'NA') ? $features->frequency_rank : '',
                                'Parte del discurso' => (isset($features->psp) && $features->psp !== 'NA') ? $features->psp : '',
                                'Verb type' => (isset($features->verb_type) && $features->verb_type !== 'NA') ? $features->verb_type : 'N/A',
                                'Noun declension' => (isset($features->noun_declension) && $features->noun_declension !== 'NA') ? $features->noun_declension : 'N/A',
                                'Noun stem' => (isset($features->noun_stem) && $features->noun_stem !== 'NA') ? $features->noun_stem : 'N/A'
                            ),
                            'Morfología' => array(
                                'Caso' => (isset($features->case) && $features->case !== 'NA') ? $features->case : 'N/A',
                                'Persona, género, número' => 
                                    ((isset($features->person) && $features->person !== 'NA') ? $features->person : '-') . 
                                    ((isset($features->gender) && $features->gender !== 'NA') ? $features->gender : '-') . 
                                    ((isset($features->number) && $features->number !== 'NA') ? $features->number : '-'),
                                'Número Posesor' => (isset($features->possessor_number) && $features->possessor_number !== 'NA') ? $features->possessor_number : 'N/A',
                                'Tiempo' => (isset($features->tense) && $features->tense !== 'NA') ? $features->tense : 'N/A',
                                'Modo' => (isset($features->mood) && $features->mood !== 'NA') ? $features->mood : 'N/A',
                                'Voz' => (isset($features->voice) && $features->voice !== 'NA') ? $features->voice : 'N/A',
                                'Extra' => 'N/A'
                            ),
                            'Glosas' => array(
                                'Español' => $features->spanish ?? ''
                            )
                        );
                    }
                    
                    $verse_data['words'][] = $word_entry;
                }
                if ($verse_data) {
                    $all_data['verses'][] = $verse_data;
                }
            } catch (Exception $e) {
                echo "\nError en $bookName capítulo $chapter: " . $e->getMessage() . "\n";
            }
        }

        $output_dir = FCPATH . 'scraped_data/' . $db;
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }

        $filename = $output_dir . '/' . $bookName . '.json';
        file_put_contents($filename, json_encode($all_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "OK\n";
    }

    private function extract_morphology($db, $features) {
        return array();
    }
}
