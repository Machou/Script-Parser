<?php
// Configuration
$style_includeheader = "color: #888888;";
$style_aninclude = "background: #f8fcf8; border: solid 1px #008800; margin-bottom: 20px;";
$style_thecode = "background: #f8fcfc; border: solid 1px #000088";
$style_line_number = "color: #000000;";
$style_normal = "color: #000000;";
$style_internal = "color: #000088; font-weight: bold;";
$style_function = "color: #000088;";
$style_classes = "color: #880000; font-weight: bold;";
$style_variable = "color: #008800;";
$style_string = "color: #880000;";
$style_comment = "color: #aaaaaa;";

/* *** NE PAS EDITER EN DESSOUS EN CETTE LIGNE *** */
$tps_begin = microtime();

// Les différents statuts possibles au cours de l'analyse
define('S_NORMAL', 1);
define('S_INTERNAL', 2);
define('S_FUNCTION', 3);
define('S_CLASSES', 4);
define('S_VARIABLE', 5);
define('S_STRING_GUILL', 6);
define('S_STRING_APOS', 7);
define('S_COMMENT', 8);
define('S_MULTILINE_COMMENT', 9);
$styles = [
		"linenumber" => $style_line_number,
		"normal" => $style_normal,
		"internal" => $style_internal,
		"function" => $style_function,
		"classes" => $style_classes,
		"variable" => $style_variable,
		"string" => $style_string,
		"comment" => $style_comment,
		"includeheader" => $style_includeheader,
		"aninclude" => $style_aninclude,
		"thecode" => $style_thecode
	];

$args = [];

if($_GET)
{
	if($_GET['showincs']) $args['showincs'] = $_GET['showincs'];
	if($_GET['indent']) $args['indent'] = $_GET['indent'];
	if($_GET['lnumber']) $args['lnumber'] = $_GET['lnumber'];
	if($_GET['xhtml']) $args['xhtml'] = $_GET['xhtml'];
}

elseif($_POST)
{
	if($_POST['showincs']) $args['showincs'] = $_POST['showincs'];
	if($_POST['indent']) $args['indent'] = $_POST['indent'];
	if($_POST['lnumber']) $args['lnumber'] = $_POST['lnumber'];
	if($_POST['xhtml']) $args['xhtml'] = $_POST['xhtml'];
}

elseif($_SERVER['argv'])
{
	$args['showincs'] = array_search("showincs", $_SERVER['argv']);
	$args['indent'] = array_search("indent", $_SERVER['argv']);
	$args['lnumber'] = array_search("lnumber", $_SERVER['argv']);
	$args['xhtml'] = array_search("xhtml", $_SERVER['argv']);
}

// Fonctions internes à php
$ifuncs_t = (get_defined_functions());
$ifuncs = $ifuncs_t['internal'];
unset($ifuncs_t);

// Mots réservés
$iwords = [
	"__CLASS__",
	"__FILE__",
	"__FUNCTION__",
	"__LINE__",
	"__METHOD__",
	"abstract",
	"and",
	"array",
	"as",
	"break",
	"case",
	"catch",
	"cfunction",
	"class",
	"clone",
	"const",
	"continue",
	"declare",
	"default",
	"die",
	"do",
	"echo",
	"else",
	"elseif",
	"empty()",
	"enddeclare",
	"endfor",
	"endforeach",
	"endif",
	"endswitch",
	"endwhile",
	"eval",
	"exception",
	"exit",
	"extends",
	"extends",
	"false",
	"final",
	"for",
	"foreach",
	"function",
	"global",
	"if",
	"implements",
	"include_once",
	"include",
	"interface",
	"isset",
	"list",
	"new",
	"old_function",
	"or",
	"php_user_filter",
	"print",
	"private",
	"protected",
	"public",
	"require_once",
	"require",
	"return",
	"static",
	"switch",
	"throw",
	"true",
	"try",
	"unset",
	"use",
	"var",
	"while",
	"xor"
];

// Les mots et les fonctions créent l'ensemble des "choses internes"
$internals = array_merge($ifuncs, $iwords);
unset($ifuncs, $iwords);

// Ceux ci seront pour les fonctions définies par le script et ses includes
$lfuncs = [];
$lfuncslinks = [];

// Et celui ci sera pour les classes définies par le script
$lclasses = [];

// Les tags html qui modifient l'indentation :
$tags_inc_indent = [
	"abbr",
	"article",
	"aside",
	"blockquote",
	"body",
	"canvas",
	"code",
	"colgroup",
	"details",
	"div",
	"dir",
	"fieldset",
	"figcaption",
	"figure",
	"footer",
	"form",
	"head",
	"header",
	"hgroup",
	"html",
	"menu",
	"nav",
	"ol",
	"optgroup",
	"ruby",
	"section",
	"select",
	"style",
	"summary",
	"svg",
	"table",
	"textarea",
	"td",
	"tbody",
	"tfoot",
	"th",
	"thread",
	"tr",
	"ul",
	"map"
];

// Le début de page
function print_header()
{
	if($GLOBALS['args']['xhtml'])
	{
		return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
			<html>
			<head>
			<title>Script Parser</title>
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\" />
			<style type=\"text/css\">
			.linenumber {" . $GLOBALS['styles']['linenumber'] . "}
			.normal {" . $GLOBALS['styles']['normal'] . "}
			.internal {" . $GLOBALS['styles']['internal'] . "}
			.function {" . $GLOBALS['styles']['function'] . "}
			.classes {" . $GLOBALS['styles']['classes'] . "}
			.variable {" . $GLOBALS['styles']['variable'] . "}
			.string {" . $GLOBALS['styles']['string'] . "}
			.comment {" . $GLOBALS['styles']['comment'] . "}
			.includeheader {" . $GLOBALS['styles']['includeheader'] . "}
			.aninclude {" . $GLOBALS['styles']['aninclude'] . "}
			.thecode {" . $GLOBALS['styles']['thecode'] . "}
			img {border: 0}
			</style>
			</head>
			<body>";
	}

	else
	{
		if($_GET || $_POST) header("Content-Type: text/plain");
	}
}

// le bas de page
function print_footer()
{
	if($GLOBALS['args']['xhtml']) return "
		</body>
		</html>";
}

// On commence par voir par où on est appellés et si on a bien le paramètre
function initialize(&$input, $afile)
{
	if($afile)
	{
		$input = file('./repository/' . $afile);
	}

	elseif($_GET AND $_GET['file'])
	{
		if(strstr($_GET['file'], "..") !== false) die("Problème !!!");
		$input = file('./repository/' . $_GET['file']);
	}

	elseif($_SERVER['argv'] AND $_SERVER['argv'][1])
	{
		$input = file("./repository/" . $_SERVER['argv'][1]);
	}

	elseif($_POST AND $_POST['file_txt'])
	{
		$input = explode("\n", stripslashes($_POST['file_txt']));
	}

	else
	{
		die('Paramètre manquant.');
	}
}

// Pour les changements de statuts
function set_status(&$s, $new_status)
{
	static $prec = 0;

	if(!$s) $s = S_NORMAL;
	$output = '';
	if($new_status >= 0)
	{
		if($prec)
		{
			$output.= '</span>';
		}

		if($new_status == 0)
		{
			$s = $prec;
		}

		else
		{
			$prec = $s;
			$s = $new_status;
		}

		switch ($s)
		{
		case S_NORMAL:
			$output.= '<span class="normal">';
			break;

		case S_INTERNAL:
			$output.= '<span class="internal">';
			break;

		case S_FUNCTION:
			$output.= '<span class="function">';
			break;

		case S_CLASSES:
			$output.= '<span class="classes">';
			break;

		case S_VARIABLE:
			$output.= '<span class="variable">';
			break;

		case S_STRING_GUILL:
		case S_STRING_APOS:
			$output.= '<span class="string">';
			break;

		case S_MULTILINE_COMMENT:
		case S_COMMENT:
			$output.= '<span class="comment">';
			break;
		}
	}

	else
	{
		$output.= "</span>";
		$prec = 0;
		$s = 0;
	}

	if($GLOBALS['args']['xhtml']) return $output;
}

function ahref($word, $style, $href = NULL)
{
	if(!$href) $href = '#'.$word;

	if($GLOBALS['args']['xhtml']) return '<a class="'.$style.'" href="'.$href.'">'.$word.'</a>';
	else return $word;
}

function aname($word)
{
	if($GLOBALS['args']['xhtml']) return '<a name="'.$word.'"></a>';
}

function parsefile($xhtml = true, $indent = true, $numerote = true, $fic = NULL)
{
	$var_valid_chars = "abcdefghijklmnopqrstuvwxyz_-0123456789";
	$word_valid_chars = "abcdefghijklmnopqrstuvwxyz_0123456789";

	// Celui ci contiendra les lignes du script à étudier
	$input = [];

	// On initialise la machine
	initialize($input, $fic);
	$output = '';
	$html_indentation = 0;

	// Numérotation de lignes
	if($numerote)
	{
		$nb_fmt = intval(log(count($input) , 10)) + 1;
		$nb_line = 0;
	}

	// Par défaut on est normal
	$output .= set_status($status, S_NORMAL);

	// On initialise l'indentation
	$previous_line_terminated = true;
	$indentation = 0;
	$output_line = '';

	// Parser On / Off
	$isParsing = false;

	// On traite les lignes les unes après les autres
	foreach($input as $line)
	{
		// En début de ligne, on n'a pas de mot en cours
		$word = '';
		if($indent)
		{
			$line = trim($line)."\n";
		}

		// On remplace quelques entités
		$line = str_replace('&', '&amp;', $line);
		$line = str_replace('<', '&lt;', $line);
		$line = str_replace('>', '&gt;', $line);

		// Pour chaque ligne, on parcourt chaque caractère
		for($i = 0; $i < strlen($line); ++$i)
		{
			if($status < S_STRING_GUILL)
			{
				if(substr($line, $i, 5) == "&lt;?")
				{
					$isParsing = true;
					$output_line.= '&lt;?';
					$i+= 5;
				}

				elseif(substr($line, $i, 5) == '?&gt;')
				{
					$isParsing = false;
					$output_line.= '?&gt;';
					$i+= 5;
				}
			}

			if(!$isParsing)
			{
				$output_line .= $line[$i];
				continue;
			}

			// Début de chaîne
			if($line[$i] == '\'')
			{
				$j = 0;
				while($line[$i - 1 - $j] == '\\')
					$j++;

				if(!($j % 2))
				{
					if($status == S_NORMAL)
					{
						$output_line .= set_status($status, S_STRING_APOS);
						$output_line .= $line[$i];
						continue;
					}

					elseif($status == S_STRING_APOS)
					{
						$output_line .= $line[$i];
						$output_line .= set_status($status, S_NORMAL);
						continue;
					}
				}
			}

			// Début de chaîne
			if($line[$i] == '"')
			{
				$j = 0;
				while($line[$i - 1 - $j] == '\\')
					$j++;

				if(!($j % 2))
				{
					if($status == S_NORMAL)
					{
						$output_line .= set_status($status, S_STRING_GUILL);
						$output_line .= $line[$i];
						continue;
					}

					elseif($status == S_STRING_GUILL)
					{
						$output_line .= $line[$i];
						$output_line .= set_status($status, S_NORMAL);
						continue;
					}
				}
			}

			// Début de commentaire monoligne
			if($status < S_STRING_GUILL AND $line[$i] == '/' AND $line[$i + 1] == '/')
			{
				$output_line .= set_status($status, S_COMMENT);
				$output_line .= $line[$i++];
				$output_line .= $line[$i];
				continue;

				// Début de commentaire multiligne
			}

			elseif($status < S_STRING_GUILL AND $line[$i] == '/' AND $line[$i + 1] == '*')
			{
				$output_line .= set_status($status, S_MULTILINE_COMMENT);
				$output_line .= $line[$i++];
				$output_line .= $line[$i];
				continue;

				// Fin de commentaire
			}

			elseif($status == S_MULTILINE_COMMENT AND $line[$i] == '*' AND $line[$i + 1] == '/')
			{
				$output_line .= $line[$i++];
				$output_line .= $line[$i];
				$output_line .= set_status($status, S_NORMAL);
				continue;
			}

			// Variable
			if(($status < S_STRING_APOS) AND ($line[$i] == '$'))
			{
				$output_line .= set_status($status, S_VARIABLE);
				$output_line .= $line[$i];
				$token = '';
				while (strpos($var_valid_chars, strtolower($line[$i + 1])) !== false)
				{
					if($line[$i + 1] == '-')
					{
						$output_line .= $token;
						$token = '';
						if(substr($line, $i + 2, 4) == '&gt;')
						{
							$output_line .= '-&gt;';
							$i+= 5;
							continue;
						}

						else
						{
							break;
						}
					}

					$token .= $line[++$i];
				}

				while($line[$i + 1] == ' ')
					$token.= $line[++$i];

				if($line[$i + 1] == '(')
				{
					$output_line .= set_status($status, 0);
					$output_line .= set_status($status, S_FUNCTION);
					$output_line .= $token;
					$output_line .= set_status($status, S_NORMAL);
				}

				else
				{
					$output_line .= $token;
					$output_line .= set_status($status, 0);
				}

				continue;
			}

			elseif(($status == S_NORMAL) AND substr($line, $i, 9) == 'function ')
			{
				// Définition de fonction
				$output_line .= set_status($status, S_INTERNAL);
				$output_line .= 'function ';
				$output_line .= set_status($status, S_NORMAL);
				$i+= 9;

				while($line[$i] == ' ')
					$output_line.= $line[$i++];

				$new_func = '';
				$output_line.= set_status($status, S_FUNCTION);
				while($line[$i] != '(')
				{
					$new_func .= $line[$i];
					$output_line .= $line[$i++];
				}

				$new_func = trim($new_func);
				$output_line .= aname($new_func);
				if(strlen($new_func))
				{
					$GLOBALS['lfuncs'][] = $new_func;
					$GLOBALS['lfuncslinks'][] = $fic;
				}

				$output_line .= set_status($status, S_NORMAL);
			}

			elseif(($status == S_NORMAL) AND substr($line, $i, 6) == 'class ')
			{
				// Définition de classe
				$output_line .= set_status($status, S_INTERNAL);
				$output_line .= 'class ';
				$output_line .= set_status($status, S_NORMAL);
				$i+= 6;

				while ($line[$i] == ' ')
					$output_line.= $line[$i++];

				$new_class = '';
				$output_line .= set_status($status, S_CLASSES);
				while(strpos($word_valid_chars, strtolower($line[$i])) !== FALSE)
				{
					$new_class .= $line[$i];
					$output_line .= $line[$i++];
				}

				$new_class = trim($new_class);
				$output_line .= aname($new_class);
				if(strlen($new_class))
				{
					$GLOBALS['lclasses'][] = $new_class;
				}

				$output_line .= set_status($status, S_NORMAL);
			}

			// On s'intéresse à notre mot en cours si on est en mode normal, sinon, non

			if($status != S_NORMAL)
			{
				if(($indent) AND ($status >= S_STRING_GUILL) AND ($line[$i] == '{'))
				{
					++$str_begin_count;
				}

				elseif(($indent) AND ($status >= S_STRING_GUILL) AND ($line[$i] == '}'))
				{
					++$str_end_count;
				}

				$output_line.= $line[$i];
			}

			elseif(strpos($word_valid_chars, strtolower($line[$i])) !== false)
			{
				// Si le caractère est un caractère valide pour la création d'un mot, on l'ajoute au mot en cours

				$word.= $line[$i];
			}

			else
			{
				// On a un mot;
				// On regarde s'il est référencé dans les mots clés internes e les fonctions ou classes locales
				// Si oui, on l'affiche comme tel

				$ipos = array_search($word, $GLOBALS['internals']);
				$lfpos = array_search($word, $GLOBALS['lfuncs']);
				$lcpos = array_search($word, $GLOBALS['lclasses']);

				if($ipos !== false)
				{
					$output_line .= ahref($word, "internal", "http://fr.php.net/manual-lookup.php?lang=fr&amp;pattern=$word");
				}

				elseif($lfpos !== false)
				{
					if($GLOBALS['args']['showincs'] OR ($fic == $GLOBALS['lfuncslinks'][$lfpos]))
					{
						$output_line.= ahref($word, "function");
					}

					else
					{
						$output_line .= ahref($word, "function", "sp.php?file=" . $GLOBALS['lfuncslinks'][$lfpos] . "&amp;showincs=0&amp;xhtml=1&amp;lnumber=$numerote#" . $GLOBALS['lfuncs'][$lfpos]);
					}
				}

				elseif($lcpos !== FALSE)
				{
					$output_line .= ahref($word, "classes");
				}

				else
				{
					$output_line .= $word;
				}

				$word = '';
				if($indent)
				{
					if($line[$i] == '{')
					{
						++$begin_count;
					}

					elseif($line[$i] == '}')
					{
						++$end_count;
					}
				}

				$output_line .= $line[$i];
			}
		}

		$line = rtrim($line);

		// Indentation
		if($indent)
		{
			if(($isParsing AND !($status == S_NORMAL AND (substr($line, -8) == "&lt;?php" OR substr($line, -5) == "&lt;?") AND strlen($line) > 8)) OR (!$isParsing AND ($status == S_NORMAL AND substr($line, -5) == "?&gt;")))
			{
				$indentation+= $begin_count - $end_count;
				for($i = 0; $i < ($indentation - $begin_count); ++$i)
				{
					$output_line = "\t$output_line";
				}

				if(!$previous_line_terminated)
				{
					if(!($status == S_NORMAL AND $line[0] == '{'))
						$output_line = "\t$output_line";
				}

				$str_begin_count = $str_end_count = $begin_count = $end_count = 0;
			}

			else
			{
				$btpos = 0;
				$tags = array_fill(0, count($GLOBALS['tags_inc_indent']) , 0);
				while(($btpos = strpos($output_line, "&lt;", $btpos)) !== false)
				{
					$btpos+= 4;
					$supp_etpos = strpos($output_line, "&gt;", $btpos);
					$sp_etpos = strpos($output_line, " ", $btpos);

					if($supp_etpos && $sp_etpos)
						$etpos = ($supp_etpos > $sp_etpos) ? $sp_etpos : $supp_etpos;

					elseif($supp_etpos)
						$etpos = $supp_etpos;

					elseif($sp_etpos)
						$etpos = $sp_etpos;

					else($etpos = false);

					if($etpos !== false)
					{
						if($output_line[$btpos] == '/')
						{
							$tag = substr($output_line, $btpos + 1, $etpos - $btpos - 1);
							if(($arr_tag_pos = array_search("$tag", $GLOBALS['tags_inc_indent'])) !== false)
							{
								--$tags[$arr_tag_pos];
							}
						}
						else
						{
							$tag = substr($output_line, $btpos, $etpos - $btpos);
							if(($arr_tag_pos = array_search("$tag", $GLOBALS['tags_inc_indent'])) !== false)
							{
								++$tags[$arr_tag_pos];
							}
						}
					}
				}

				$later_indent = 0;
				foreach($tags as $val)
				{
					if($val < 0)
						$html_indentation += $val;

					else
						$later_indent += $val;
				}

				for($i = 0; $i < $html_indentation; ++$i)
				{
					$output_line = "\t$output_line";
				}

				$html_indentation += $later_indent;
			}
		}

		// On numérote ?
		if($numerote)
		{
			if($xhtml)
			{
				$output .= sprintf("<span class=\"linenumber\">%0" . $nb_fmt . "d: </span>", ++$nb_line);
			}
			else
			{
				$output .= sprintf("%0" . $nb_fmt . "d: ", ++$nb_line);
			}
		}

		$output.= "$output_line";
		$output_line = '';

		// La ligne est terminée ?
		if($isParsing && $indent)
		{
			$first_char = $line[0];
			$last_char = substr($line, -1);
			$previous_line_terminated = ((strlen($line) == 0) && $previous_line_terminated) || (($status >= S_COMMENT) && $previous_line_terminated) || ($status < S_STRING_GUILL && ((($last_char == ';') && (substr($line, -5) != "&amp;") && (substr($line, -4) != "&lt;") && (substr($line, -4) != "&gt;")) || ($last_char == ':') || ($last_char == '{') || ($last_char == '}') || (substr($line, -5) == "&lt;?") || (substr($line, -8) == "&lt;?php") || (substr($line, -4) == "?&gt;") || (substr($line, -2) == "*/")));
		}

		if($status == S_COMMENT)
		{
			$output_line.= set_status($status, S_NORMAL);
		}
	}

	$output.= set_status($status, -1);

	// On affiche le bas de la page
	if(!$xhtml)
	{
		$output = str_replace('&amp;', '&', $output);
		$output = str_replace('&lt;', '<', $output);
		$output = str_replace('&gt;', '>', $output);
	}

	return $output;
}

// La session pour la gestion du repository
session_start();

// On affiche le début de page
echo print_header();

// On traite les includes s'il y en a
if(isset($_SESSION["includes"]))
{
	foreach($_SESSION["includes"] as $f)
	{
		if(file_exists("./repository/$f"))
		{
			if($args['xhtml'])
			{
				$str = "<span class=\"includeheader\">" . substr($f, strlen(session_id())) . "</span>\n<div class=\"aninclude\">\n<pre>";
			}

			$str.= parsefile($args['xhtml'], $args['indent'], $args['lnumber'], "$f");
			if($args['xhtml'])
				$str.= "</pre>\n</div>\n";

			if($args['showincs'])
				echo "$str\n\n";
		}
	}
}

if($args['xhtml'])
	echo "<div class=\"thecode\">\n<pre>";

echo parsefile($args['xhtml'], $args['indent'], $args['lnumber']);

if($args['xhtml']) echo "</pre>\n</div>\n";
$tps_tot = ((float)array_sum(explode(' ', microtime()))) - ((float)array_sum(explode(' ', $tps_begin)));
printf("<div>Temps d'exécution: %0.3f secs</div>", $tps_tot);
echo '<p><a href="http://validator.w3.org/check?uri=referer"><img src="http://www.w3.org/Icons/valid-xhtml10" alt="Valid XHTML 1.0!" height="31" width="88" /></a></p>';

// On affiche la fin
echo print_footer();
