<?php

/**
 * Info Alphaindex: Displays the alphabetical index of a specified namespace.
 *
 * Version: 1.2
 * last modified: 2012-03-25 19:00:00
 * @license     GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author      Hubert Molière  <hubert.moliere@alternet.fr>
 * Modified by  Nicolas H. <prog@a-et-n.com>
 * Modified by  Jonesy <jonesy@oryma.org>
 * Modified by  Samuele Tognini <samuele@netsons.org>
 * Modified by  Danny Götte <danny.goette@fem.tu-ilmenau.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/search.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_alphaindex extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'substition';}
    
    function getAllowedTypes() { return array('baseonly', 'substition', 'formatting', 'paragraphs', 'protected'); }

    function getPType() { return 'block'; }
    
    /**
     * Where to sort in?
     */
    function getSort(){
        return 139;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{alphaindex>.+?}}',$mode,'plugin_alphaindex');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
	global $ID;
        $level = 0;
        $nons = true;
        $match = substr($match, 13, -2);
        
        // split namespaces
        $match = preg_split('/\|/u', $match, 2);
        
        // split level
        $ns_opt = preg_split('/\#/u', $match[0], 2);
        
        // namespace name
        $ns = $ns_opt[0];
        
        // add @NS@ option
        if(empty($ns) || $ns == '@NS@') {
            $pos = strrpos($ID,':');
              if($pos != false){
	                $ns = substr($ID,0,$pos);
              } else {
	              $ns = '.';
              }
        }

	// add @ID@ option
        if($ns == '@ID@') {
                $ns = $ID;
        }
 
        // level;
        if (is_numeric($ns_opt[1])) {
            $level = $ns_opt[1];
        }
        
        $match = explode(" ", $match[1]);
        
        // namespaces option
        $nons = in_array('nons', $match);
  
        // reverse listing option
        $reverse = in_array('reverse', $match);
        
        // reverse listing option
        $dates = in_array('dates', $match);

        // only list namespaces option
        $nsonly = in_array('nsonly', $match);

        // no top heading
        $notopheading = in_array('notopheading', $match);
        
        // no section heading
        $noheading = in_array('noheading', $match);

        // multi-columns option
        $incol = in_array('incol', $match);
        
        return array($ns, array('level' => $level, 'nons' => $nons, 'incol' => $incol, 'reverse' => $reverse, 'dates' => $dates, 'nsonly' => $nsonly, 'notopheading' => $notopheading, 'noheading' => $noheading));
    }

    /**
     * Render output
     */
    function render($mode, &$renderer, $data) {
        global $conf;

        if($mode == 'xhtml'){
            $renderer->info['cache'] = FALSE;
            $alpha_data = $this->_alpha_index($data, $renderer);
            if ((!@$n)) {
                if ($this->getConf('empty_msg')) {
                    $n = $this->getConf('empty_msg');
                } else {
                    $n = 'No index for <b>{{ns}}</b>';
                }
            }
            
            $alpha_data = str_replace('{{ns}}', cleanID($data[0]), $alpha_data);

            $alpha_data = p_render('xhtml', p_get_instructions($alpha_data), $info);

            // remove toc, section edit buttons and category tags
            $patterns = array('!<div class="toc">.*?(</div>\n</div>)!s',
                            '#<!-- SECTION \[(\d*-\d*)\] -->#e',
                            '!<div class="category">.*?</div>!s');
            $replace  = array('','','');
            $alpha_data = preg_replace($patterns, $replace, $alpha_data);
            $renderer->doc .= '<div id="alphaindex_content">' ;
            $renderer->doc .= $alpha_data;
            $renderer->doc .= '</div>' ;
            return true;
        }
        
        return false;
    }

    /**
     * Return the alphabetical index
     * @author Hubert MOLIERE <hubert.moliere@alternet.fr>
     *
     * This function is a hack of Indexmenu _tree_menu($ns)
     * @author Samuele Tognini <samuele@samuele.netsons.org>
     *
     * This function is a simple hack of DokuWiki html_index($ns)
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _alpha_index($myns, &$renderer) {
        global $conf;
        global $ID;

        $ns = $myns[0];
        $opts = $myns[1];

        // Articles deletion configuration
        $articlesDeletionPatterns = array();
        if($this->getConf('articles_deletion')) {
            $articlesDeletionPatterns = explode('|', $this->getConf('articles_deletion'));
            $articlesDeletion = true;
        } else {
            $articlesDeletion = false;
        }

        // Hide pages configuration
        $hidepages = array();
        if($this->getConf('hidepages')) {
            $hidepages = explode('|', $this->getConf('hidepages'));
        }

        // template configuration
        if($this->getConf('title_tpl')) {
            $titleTpl = $this->getConf('title_tpl');
        } else {
            $titleTpl = '===== Index =====';
        }
        
        if($this->getConf('begin_letter_tpl')) {
            $beginLetterTpl = $this->getConf('begin_letter_tpl');
        } else {
            $beginLetterTpl = '==== {{letter}} ====';
        }
        
        if($this->getConf('entry_tpl')) {
            $entryTpl = $this->getConf('entry_tpl');
        } else {
            $entryTpl = '  * [[{{link}}|{{name}}]]';
        }
        
        if($this->getConf('end_letter_tpl')) {
            $endLetterTpl = $this->getConf('end_letter_tpl');
        } else {
            $endLetterTpl = '';
        }

        if($ns == '.') {
            $ns = dirname(str_replace(':','/',$ID));
            if ($ns == '.') $ns = '';
        } else {
            $ns = cleanID($ns);
        }

        $ns  = utf8_encodeFN(str_replace(':','/',$ns));
        $data = array();
        // Searches for files below the given datadir and calls for every file the function alphaindex_search_index
        search($data, $conf['datadir'], 'alphaindex_search_index', $opts, "/" . $ns);

        $nb_data = count($data);
        $alpha_data = array();

        // alphabetical ordering
        for($cpt = 0; $cpt <$nb_data; $cpt++) {
            $tmpData = $data[$cpt]['id'];

            $pos = strrpos(utf8_decode($tmpData), ':');
            if($conf['useheading']) {
                $pageName = p_get_first_heading($tmpData);
                
                if($pageName == NULL) {
                    if($pos != FALSE) {
                        $pageName = utf8_substr($tmpData, $pos+1, utf8_strlen($tmpData));
                    } else {
                        $pageName = $tmpData;
                    }
                    $pageName = str_replace('_', ' ', $pageName);
                }
            } else {
                if($pos != FALSE) {
                    $pageName = utf8_substr($tmpData, $pos+1, utf8_strlen($tmpData));
                } else {
                    $pageName = $tmpData;
                }
                
                $pageName = str_replace('_', ' ', $pageName);
            }
            $pageNameArticle = '';

            // if the current page is not a page to hide
            if(!in_array($pageName, $hidepages)) {
                // Articles deletion
                if($articlesDeletion) {
                    foreach($articlesDeletionPatterns as $pattern) {
                        if(eregi($pattern, $pageName, $result)) {
                            $pageName = eregi_replace($pattern, '', $pageName);
                            $pageNameArticle = ucfirst(trim($result[0]));
                        }
                    }
                }
                
                // Fix for useheading - Decide if the heading is used or the pagename
                if($this->getConf('metadata_title')) {
                    $tmp = p_get_metadata($data[$cpt]['id']);
                    if(isset($tmp['title'])) $pageName = $tmp['title'];
        		}

        		// R�cup�ration de la premi�re lettre du mot et classement
                $firstLetter = utf8_deaccent(utf8_strtolower(utf8_substr($pageName, 0, 1)));
                
                if(is_numeric($firstLetter)) {
                	$first4 = utf8_deaccent(utf8_strtolower(utf8_substr($pageName, 0, 4)));
                    if ($opts['dates'] && is_numeric($first4) ) {
                    	$firstLetter = $first4;
                	}elseif($this->getConf('numerical_index')) {
                        $firstLetter = $this->getConf('numerical_index');
                    } else {
                        $firstLetter = '0-9';
                    }
                }

                if($this->getConf('articles_moving')) {
                    $articleMoving = $this->getConf('articles_moving');
                } else {
                    $articleMoving = 1;
                }
                if($articleMoving == 0) {
                    $pageName = $pageNameArticle.' '.$pageName;
                } else if (($articleMoving == 1)&&($pageNameArticle != '')) {
                    $pageName = $pageName.' ('.$pageNameArticle.')';
                }

                $data[$cpt]['id2'] = ucfirst($pageName);
                $alpha_data[$firstLetter][] = $data[$cpt];
            }
        }

        // array sorting by key
        if (!$opts['reverse']) {
        	ksort($alpha_data);
        } else {
        	
        	// reverse option
        	krsort($alpha_data);	
        }

        // Display of results

        // alphabetical index
	if (!$opts['notopheading']) {
        	$alphaOutput .= $titleTpl . "\n";
	}
        $nb_data = count($alpha_data);
        
        foreach($alpha_data as $key => $currentLetter) {
        	
            // Sorting of $currentLetter array, using the reverse option if given
            if ($opts['reverse'] || ($opts['dates'] && is_numeric($currentLetter))) {
        		// reverse option (changed param order to affect sorting)
    			usort($currentLetter, create_function('$b, $a', "return strnatcasecmp(\$a['id2'], \$b['id2']);"));
        	} else {
        		// normal sorting
	    	    usort($currentLetter, create_function('$a, $b', "return strnatcasecmp(\$a['id2'], \$b['id2']);"));
        	}
        	
            $begin = str_replace("{{letter}}" ,utf8_strtoupper($key), $beginLetterTpl);
	    if (!$opts['noheading']) {
            	$alphaOutput .= $begin."\n";
            }
            foreach($currentLetter as $currentLetterEntry) {
                $link = str_replace("{{link}}" ,$currentLetterEntry['id'], $entryTpl);
                $alphaOutput .= str_replace("{{name}}" ,$currentLetterEntry['id2'], $link);
                $alphaOutput .= "\n";
            }

            $end = str_replace("{{letter}}" ,utf8_strtoupper($key), $endLetterTpl);
            $alphaOutput .= $end."\n";
        }

        return $alphaOutput;
    }

} // Alphaindex class end

/**
 * Build the browsable index of pages
 *
 * $opts['ns'] is the current namespace
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * modified by Samuele Tognini <samuele@samuele.netsons.org>
 */
function alphaindex_search_index(&$data, $base, $file, $type, $lvl, $opts) {
    $return = true;
    $item = array();
   
    if($type == 'd'){
        if ($opts['level'] == $lvl) $return = false;
        if ($opts['nons']) return $return;
    }elseif($type == 'f' && !preg_match('#\.txt$#',$file)){
        // don't add the page
        return false;
    }
    
    if ($type == 'f' && $opts['nsonly']) {
    	return false;	
    }

    $id = pathID($file);

    // check hidden
    if($type=='f' && isHiddenPage($id)){
        return false;
    }

    // check ACL
    if($type == 'f' && auth_quickaclcheck($id) < AUTH_READ) {
        return false;
    }

    // Set all pages at first level
    if ($opts['nons']) {
        $lvl = 1;
    }

    $data[] = array( 'id' => $id,
		 'type'  => $type,
		 'level' => $lvl,
		 'open'  => $return);
    
    return $return;
}
