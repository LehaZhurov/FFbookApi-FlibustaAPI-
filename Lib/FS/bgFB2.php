<?php
/*****************************************************************************************
	bgFB2 - простой PHP класс, преобразует HTML в формат FistonBook (fb2)

******************************************************************************************/

require ('BgClearHTML.php');
class bgFB2 {
	public $options = null;
	public function prepare ($content, $options) {
		
		$this->options = $options;
		
		// Разрешенные HTML-теги для FB2
		define( 'BG_FB2_TAGS',
		"img[src|alt],div[id],blockquote[id],
		h1[id],h2[id],h3[id],h4[id],h5[id],h6[id],
		hr,p[id],br,li[id],a[href|name|id],
		table[id],tr[align|valign],th[id|colspan|rowspan|align|valign],td[id|colspan|rowspan|align|valign],
		b,strong,i,em,sub,sup,s,strike,code");
		// Разрешенные HTML-сущности для FB2
		define( 'BG_FB2_ENTITIES',
		"&amp;,&lt;,&gt;,&apos;,&quot;,&nbsp;[ ],&hellip;[...],&ndash;[-],&mdash;[—],&oacute;[o]");
		
		/* Оставляем только разрешенные теги и атрибуты */
//		require_once "../BgClearHTML.php";
		$сhtml = new BgClearHTML();
		
		// Массив разрешенных тегов и атрибутов 
		$allow_attributes = $сhtml->strtoarray ( BG_FB2_TAGS );
		// Оставляем в тексте только разрешенные теги и атрибуты
		$content = $сhtml->prepare ($content, $allow_attributes);

		// Заменяем HTML-сущности
		$content = $this->replaceEntities( $content, BG_FB2_ENTITIES );
		
		// Удаляем переносы строк
		$content = str_replace(PHP_EOL, '',  $content);

		/* Преобразуем html в fb2 */
		$content = '<?xml version="1.0" encoding="UTF-8"?>'. PHP_EOL .
'<FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0" xmlns:l="http://www.w3.org/1999/xlink">'. PHP_EOL .
'<stylesheet type="text/css">' .$options['css']. '</stylesheet>'. PHP_EOL .
$this->discription ($content, $options).PHP_EOL .
PHP_EOL . '<body>'. PHP_EOL .
'<title><p>' .$options['title']. '</p></title>'. 
$this->body ($content, $options).
PHP_EOL . '</body>'. PHP_EOL .
(($options['cover'])?$this->create_binary($options['cover']):'').
$this->images ($content, $options).
'</FictionBook>';

		// Добавляем символы конца строки
		$content = $сhtml->addEOL ($content);
		$block_end = array('section', 'title', 'subtitle', 'cite', 'table'); 
		foreach ($block_end as $tag) {
			$content = str_replace('</'.$tag.'>', '</'.$tag.'>'. PHP_EOL,  $content);
		}
		$content = str_replace('<empty-line/>', '<empty-line/>'. PHP_EOL,  $content);
		$content = str_replace('<section>', '<section>'. PHP_EOL,  $content);
		$content = str_replace('<title>', '<title>'. PHP_EOL,  $content);
		
		unset($сhtml);
		$сhtml=NULL;
		return $content;
	}

	public function save ($file, $content) {
		$put = file_put_contents($file, $content,LOCK_EX);
		return $put;
	}
	
	function discription ($content, $options) {
		$authorName = preg_replace('/\.\,\;\:/is', '', $options['author']);
		$authorName = trim(preg_replace('/\s/is', ' ', $authorName));
		$authorName = explode(" ", $authorName);
		$firstName = (isset($authorName[1]))?$authorName[1]:"";
		$lastName = (isset($authorName[0]))?$authorName[0]:"";
		$content ='<description>'. PHP_EOL .
'<title-info>'. PHP_EOL .
'<genre>' .$options['genre']. '</genre>'. PHP_EOL .
'<author>'. PHP_EOL .
'<first-name>' .$firstName.  '</first-name>'. PHP_EOL .
'<last-name>' .$lastName. '</last-name>'. PHP_EOL .
'</author>'.
'<book-title>' .$options['title']. '</book-title>'. PHP_EOL .
'<lang>' .$options['lang']. '</lang>'. PHP_EOL .
(($options['cover'])?('<coverpage><image l:href="#' .str_replace('gif', 'png', basename($options['cover'])). '"/></coverpage>'. PHP_EOL):'').
'<date value="' .date ( 'Y-m-d' ). '">' .date ( 'd.m.Y' ). '</date>'. PHP_EOL .
'</title-info>'. PHP_EOL .
'<document-info>'. PHP_EOL .
'<id>4bef652469d2b65a5dfee7d5bf9a6d75-AAAA-' . md5($options['title']) . '</id>'. PHP_EOL .
'<author><nickname>' .$options['author']. '</nickname></author>'. PHP_EOL .
'<date xml:lang="' .$options['lang']. '">' .date ( 'd.m.Y' ). '</date>'. PHP_EOL .
'<version>' .$options['version']. '</version>'. PHP_EOL .
'</document-info>'. PHP_EOL .
'<publish-info>'. PHP_EOL .
'<publisher>' .$options['publisher']. '</publisher>'. PHP_EOL . 
'</publish-info>'. PHP_EOL .
'</description>';

		return $content;
	}
	function body ($content, $options) {
		// Заменяем заголовки на жирный текст в ячейках таблиц
		$content = preg_replace_callback('/(<td([^>]*?)>)(.*?)(<\/td>)/is', 
				function ($matches) {
					$content = $matches[3];
					$content = preg_replace('/<h([^>]*?)>/is', '<p><strong>',  $content);
					$content = preg_replace('/<\/h([^>]*?)>/is', '</strong></p>',  $content);
					return $matches[1].$content.$matches[4];
				}, $content);
		
		
		// Разбиваем текст на секции
		$template = '/<h([1-3])(.*?)<\/h\1\>/is';
		preg_match_all($template, $content, $matches, PREG_OFFSET_CAPTURE);

		$text = '<section><title></title>';
		$start = 0;
		$prev_level = 1;
		$num_sections = 1;
		$cnt = count($matches[0]);
		for ($i = 0; $i < $cnt; $i++) {	// Разбираем каждый заголовок на патерны 
			preg_match($template, $matches[0][$i][0], $mt);
			
			$level = intval( $mt[1] );
			$pos = strpos ( $mt[2], '>' );
			$attr = ($pos>0)?substr( $mt[2], 0, $pos ):'';
			$txt = substr ( $mt[2], $pos+1 );
			$section = '</section><section'.$attr.'><title>'.$txt.'</title><section><title></title>';
			if ($prev_level >= $level) {
				$num_ends = $prev_level - $level + 1;
				if ($num_sections < $num_ends) $num_ends = $num_sections;
				for ($n=0; $n < $num_ends; $n++) {
					if (substr($text, -32) == '</title><section><title></title>') {
						// Удаляем в конце строки <section><title></title>
						$text = substr_replace($text, '', -24);
					} else {
						// Иначе закрываем секцию
						$section = '</section>'.$section;
					}
					$num_sections--;
				}
			}
			$num_sections++;
			$text .= substr($content, $start, $matches[0][$i][1]-$start). $section;
			$start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
			$prev_level = $level;
		}
		if (substr($text, -32) == '</title><section><title></title>') {
			// Удаляем в конце строки <section><title></title>
			$text = substr_replace($text, '', -24);
			$num_sections--;
		}
		$content = $text.substr($content, $start);
		// Обрамляем тегом <section>
		$content = '<section><title></title>'.$content;
		for ($n=0; $n < $num_sections+1; $n++) $content .= '</section>';

		// Обрабатываем заголовки секций
		$content = preg_replace_callback('/(<title>)(.*?)(<\/title>)/is', 
			function ($matches) {
				$fb2 = new bgFB2();
				$content = $fb2->section ($matches[2], $this->options);
				return $matches[1].$content.$matches[3];
			}, $content);

		// Обрабатываем внутри секций
		$content = preg_replace_callback('/(<\/title>)(.*?)(<\/?section)/is', 
			function ($matches) {
				$fb2 = new bgFB2();
				$content = $fb2->section ($matches[2], $this->options);
				return $matches[1].$content.$matches[3];
			}, $content);
		// Удаляем лишнее
		$content = preg_replace('/<title>\s*<\/title>/is', '',  $content);
		$content = preg_replace('/<section>\s*<\/section>/is', '',  $content);
		$content = preg_replace('/<section>\s*<\/section>/is', '',  $content);

		return $content;
	}
	
	function section ($content, $options) {
		
		// Преобразуем элементы оформления текста
		$content = str_replace('<b>', '<strong>',  $content);
		$content = str_replace('</b>', '</strong>',  $content);
		$content = str_replace('<i>', '<emphasis>',  $content);
		$content = str_replace('</i>', '</emphasis>',  $content);
		$content = str_replace('<em>', '<emphasis>',  $content);
		$content = str_replace('</em>', '</emphasis>',  $content);
		$content = str_replace('<strike>', '<strikethrough>',  $content);
		$content = str_replace('</strike>', '</strikethrough>',  $content);
		$content = str_replace('<s>', '<strikethrough>',  $content);
		$content = str_replace('</s>', '</strikethrough>',  $content);

		// Преобразуем горизонтальную линию в пустую строку
		$content = preg_replace('#<hr([^>]*?)>#is', '<empty-line/>',  $content);

		// Преобразуем заголовки h4-h6 в подзаголовки
		$content = preg_replace('/<h[4-6]([^>]*?)>/is', '<subtitle\1>',  $content);
		$content = preg_replace('/<\/h[4-6]>/is', '</subtitle>',  $content);
		
		// Цитаты 
		$content = preg_replace('/<blockquote([^>]*?)>/is', '<cite\1>',  $content);
		$content = str_replace('</blockquote>', '</cite>',  $content);
		
		// Изображения
		$content = preg_replace_callback('/<img\s+([^>]*?)\/>/is', 
			function ($match) {
				$attr = preg_replace_callback('/src\s*=\s*([\"\'])([^>]*?)(\1)/is', 
					function ($mt) {
						$filename = basename($mt[2]);
						$ext = substr(strrchr($filename, '.'), 1);
						if ($ext == 'gif') {
							$filename = str_replace ('gif', 'png', $filename);
							$ext = 'png';
						}
						if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png') 
							return 'l:href="#'.$filename.'"';
						else return "";
					}, $match[1]);
				if (preg_match ('/l:href/is', $attr)) return '<image '.$attr.' />';	
				else return "";
			}, $content);
					
		// Преобразуем <div> в <p>
		$content = preg_replace('/<div([^>]*?)>/is', '<p\1>',  $content);
		$content = str_replace('</div>', '</p>', $content);

		// Преобразуем списки в строки
		$content = preg_replace('/<ol([^>]*?)>/is', '<aid\1>',  $content);
		$content = preg_replace('/<ul([^>]*?)>/is', '<aid\1>',  $content);
		$content = str_replace('</ol>', '',  $content);
		$content = str_replace('</ul>', '',  $content);
		$content = preg_replace('/<li([^>]*?)>/is', '<p\1>• ',  $content);
		$content = str_replace('</li>', '</p>',  $content);

		// Абзацы
		$content = preg_replace('/<p([^>]*?)>/is', '</p><p\1>',  $content);
		$content = str_replace('</p>', '</p><p>',  $content);

		// Преобразуем <br> в </p><p>

		$content = $this->enclose_br($content);

		$content = preg_replace('#<br([^>]*?)>#is', '</p><p>',  $content);
	
		// Обрабляем содержимое, секции, блоки и абзацы в <p> ... </p>
		$content = str_replace('<title', '</p><title',  $content);
		$content = str_replace('</title>', '</title><p>',  $content);
		$content = str_replace('<subtitle', '</p><subtitle',  $content);
		$content = str_replace('</subtitle>', '</subtitle><p>',  $content);
//		$content = preg_replace('/<image([^>]*?)>/is', '</p><image\1><p>',  $content);	// Убрать комментарии для НЕ inline режима
		$content = str_replace('<empty-line/>', '</p><empty-line/><p>',  $content);
		$content = preg_replace('/<cite([^>]*?)>/is', '</p><cite\1><p>',  $content);
		$content = str_replace('</cite>', '</p></cite><p>',  $content);
		$content = preg_replace('/<table([^>]*?)>/is', '</p><table\1>',  $content);
		$content = str_replace('</table>', '</table><p>',  $content);
		$content = preg_replace('/<td([^>]*?)>/is', '<td\1><p>',  $content);
		$content = str_replace('</td>', '</p></td>',  $content);
		$content = '<p>'.$content.'</p>';

		// Убираем лишние <p> и </p>
		$content = preg_replace('/<p>\s*<p([^>]*?)>/is', '<p\1>',  $content);
		$content = preg_replace('/<p([^>]*?)>\s*<p([^>]*?)>/is', '<p\1>',  $content);
		$content = preg_replace('/<\/p>\s*<\/p>/is', '</p>',  $content);
		$content = preg_replace('/<p>\s*<\/p>/is', '',  $content);
		$content = preg_replace('/<p>\s*<\/p>/is', '',  $content);
		$content = str_replace(PHP_EOL . PHP_EOL, PHP_EOL,  $content);
		
		if (!$options['allow_p']) {
		// В ячейках таблиц абзацы запрещены
			$content = preg_replace_callback('/(<td([^>]*?)>)(.*?)(<\/td>)/is', 
				function ($matches) {
					$content = $matches[3];
					$content = preg_replace('/<\/p><p([^>]*?)>/is', ' ',  $content);
					$content = preg_replace('/<p([^>]*?)>/is', '',  $content);
					$content = preg_replace('/<\/p>/is', '',  $content);
					$content = preg_replace('/<subtitle([^>]*?)>/is', '',  $content);
					$content = preg_replace('/<\/subtitle>/is', '',  $content);
					$content = preg_replace('/<cite([^>]*?)>/is', '',  $content);
					$content = preg_replace('/<\/cite>/is', '',  $content);
					return $matches[1].$content.$matches[4];
				}, $content);
		}
		// Якори выносим в отдельный тег
		$content = preg_replace_callback('/<a\s+([^>]*?)>/is', 
			function ($match) {
				if (preg_match('/(name|id)\s*=\s*([\"\'])([^>]*?)(\2)/is', $match[1], $mt))
					$a = '<aid id="'.$mt[3].'">';
				else $a = '';

				if (preg_match('/href\s*=\s*([\"\'])([^>]*?)(\1)/is', $match[1], $mt)) {
					$a .= '<a l:href="'.$mt[2].'">';
				} else $a .= '<a>';

				return $a;	
			}, $content);
					
		// Якори (преносим id в элементы <p>, <subtitle>, <v>, <td>)
		$content = preg_replace_callback('/<(p|subtitle|td)(.*?)<aid( id=\"(.*?)\")>/is', 
				function ($matches) {
					if (preg_match ( '/id=\"/is' , $matches[2])) :
						return '<'.$matches[1].$matches[2];
					else :
						return '<'.$matches[1].$matches[3].$matches[2];
					endif;
				}, $content);
					
		// Удаляем пустые теги и неперенесенные метки
		$content = preg_replace('/<a>(.*?)<\/a>/is', '\1',  $content);
		$content = preg_replace('/<aid(.*?)>/is', '',  $content);	

		return $content;
	}
	
	function images($content, $options) {
		// Ищем все вхождения изображений 
		//$this->images ($content, $options).
		
		$upload_dir = "/";
		$schema = (@$_SERVER["HTTPS"] == "on") ? "https:" : "http:";
		$domain = '//'.$_SERVER["SERVER_NAME"];
		if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")	$domain .= ":".$_SERVER["SERVER_PORT"];
		$currentURL = $schema.$domain;
		$rootURI = $_SERVER['DOCUMENT_ROOT'];
		
		$template = '/<img\s+([^>]*?)src\s*=\s*([\"\'])([^>]*?)(\2)/is';
		preg_match_all($template, $content, $matches, PREG_OFFSET_CAPTURE);

		$text = "";
		$cnt = count($matches[0]);
		for ($i=0; $i<$cnt; $i++) {
			preg_match($template, $matches[0][$i][0], $mt);
			$path = $mt[3];
			if ($path[0] == '/' && $path[1] != '/') $path = $currentURL.$path; // Задан путь относительно root
			if( !ini_get('allow_url_fopen')) {	// В случае если allow_url_fopen запрещено на сервере
				if ( substr($path,0,4) == 'http' )
					$path = str_replace ($currentURL, $rootURI, $path);
				elseif ( substr($path,0,2) == '//' )
					$path = str_replace ($domain, $rootURI, $path);
			} 			
			$text .= $this->create_binary($path);
		}

		return $text;
	}
	function create_binary ($path) {
		$filename = basename($path);
		$ext = substr(strrchr($filename, '.'), 1);
		switch ($ext) {
			case 'jpg':
			case 'jpeg':
				 $type = 'jpeg';
				 break;
			case 'gif':
			case 'png':
				 $type = 'png';
				 break;
			default:
				return "";
		}
		if ($ext == 'gif') {
			$filename = str_replace ('gif', 'png', $filename);
			$image = $this->giftopng ($path);
		}
		else $image = file_get_contents($path);
		if (!$image) return "";
		$text = '<binary id="'.$filename.'" content-type="image/'.$type.'">'.base64_encode ($image).'</binary>'.PHP_EOL;

		return $text;		
	}
	function giftopng ($path) {
		$img = imagecreatefromgif($path); 
		$temp_file = tempnam(sys_get_temp_dir(), 'tmp').'.png';

		imagepng ($img, $temp_file, 9); 
		$image = file_get_contents($temp_file);
		
		imagedestroy($img);		// Освобождаем память
		unlink ($temp_file);	// Удаляем временный файл
		
		return $image;
	}
	
	function replaceEntities ($content, $str) {
		$allow_entities = array ();
		// Ключ - HTML-сущность, 
		// Значение - её замена на набор символов
		$listattr =	explode( ",", $str );
		foreach ($listattr as $attr) {
			preg_match('/(&#?[a-z0-9]+;)(\[(.*?)\])?/is', $attr, $mt);
			if (isset($mt[3])) $allow_entities[$mt[1]] = $mt[3];
			else $allow_entities[$mt[1]] = "";
		}
		// Ищем все вхождения HTML-сущностей
		preg_match_all('/&#?[a-z0-9]+;/is', $content, $matches, PREG_OFFSET_CAPTURE);
		$text = "";
		$start = 0;
		$cnt = count($matches[0]);
		for ($i=0; $i<$cnt; $i++) {
			// Замена для всех разрешенных HTML-сущностей
			$newmt = $this->checkentity($matches[0][$i][0], $allow_entities);
			$text .= substr($content, $start, $matches[0][$i][1]-$start). $newmt;
			$start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
		};
		$content = $text.substr($content, $start);

		$content = preg_replace_callback('/&([^&]*?[&\s])/is', 
			function($match) {
				if (!preg_match('/&(#?[a-z0-9]+;)/is', $match[0])) return '&amp;'.$match[1];
				else return $match[0];
			}
			, $content);

		return $content;
	}

	function checkentity($mt, $allow_entities) {
		foreach ($allow_entities as $entity => $symbols){
			if ($entity == $mt) {
				if ($symbols == "") return $entity;		// Если не задана замена, оставляем HTML-сущность
				else return $symbols;					// иначе, замещаем на символы
			}
		}
		return '';										// Остальные HTML-сущности удаляем
	}

	function enclose_br( $text ) {
		$tagstack = array();
		$newtext = '';

		// Теги форматирования текста
		$text_formatting_tags = array( 'strong', 'emphasis', 'u', 'strikethrough', 'sup', 'sub', 'code', 'a' );

		$text = preg_replace('#<br([^>]*?)>(\r?\n?)+#is', '<br>',  $text);
		// Просмотрим все теги
		while ( preg_match("/<(\/?[\w:]*)\s*([^>]*)>/", $text, $regex) ) {
			$i = strpos($text, $regex[0]);	// Позиция найденного тега
			$l = strlen($regex[0]);			// Кол-во символов в теге
			
			// Завершающий тег
			if ( isset($regex[1][0]) && '/' == $regex[1][0] ) { 
				$tagname = strtolower(substr($regex[1],1));
				$tag = '</'.$tagname.'>';

				// Если это тег форматирования текста
				if ( in_array( $tagname, $text_formatting_tags ) ) {
					// Проверим, нет ли открывающего тега в стеке
					$stacksize = count($tagstack);
					for ( $k = $stacksize-1; $k >=0 ; $k--) {
						// Если есть такой же, то сотрем его
						if($tagstack[$k] == $tagname) $tagstack[$k] = "";
					}
				}
			} else { // Открывающий тег
				$tagname = strtolower($regex[1]);
				$tag = '<'.$tagname.(($regex[2])?(' '.$regex[2]):"").'>';
			
				// Если это тег форматирования текста
				if ( in_array( $tagname, $text_formatting_tags ) ) {
					$stacksize = array_push( $tagstack, $tagname );	// Поместить тег в стек
				// Если это тег переноса строки 
				} elseif ($tagname == 'br') {
					// Просмотрим весь стек
					$stacksize = count($tagstack);
					for ( $k = 0; $k < $stacksize; $k++) {
						// Если в стеке есть незакрытые теги форматирования текста, 
						if ($tagstack[$k]){
							// то обрамим тег <br> этими тегами: спереди завершающие, сзади - открывающие 
							$tag = '</'.$tagstack[$k].'>'.$tag.'<'.$tagstack[$k].'>';
						}
					}
				}
			}
			$newtext .= substr($text, 0, $i).$tag;
			$text = substr($text, $i + $l);
		}
		// Добавить оставшийся текст
		$newtext .= $text;

		return $newtext;
	}
}
