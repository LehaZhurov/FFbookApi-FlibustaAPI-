<?

class FlibustaScrabber{

    function __construct()
    {
        require("simple_html_dom.php");
    }

    protected function FileName($str)
    {
        $converter = array(
            'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
            'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
            'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
            'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
            'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
            'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
            'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
        );
        $value = mb_strtolower($str);
        $value = strtr($value, $converter);
        $value = mb_ereg_replace('[^-0-9a-z]', '-', $value);
        $value = mb_ereg_replace('[-]+', '-', $value);
        $value = trim($value, '-'); 
        return $value;
    }
    protected function GenerateAnotacia($code){   
        $query = 'http://flibusta.club/b/'.$code;
        $html = file_get_html($query); 
        $ann = array(
            "title"     => $html->find('h1')[0]->plaintext,
            "author"    => $html->find('span.row_content')[0]->plaintext,
            "genre"     => '',
            "lang"      => 'UTF-8',
            "version"   => 1,
            "cover"     => "https://flibusta.club".$html->find('img')[1]->src,
            "publisher" => '',
            "css"       => ' ',
            "allow_p"   => 'off'
        );
        return $ann;
    }
    protected function GetTextBook($code){
        $code = 'http://www.flibusta.site/b/'.$code.'/read';
        $html = file_get_contents($code);
        if(strpos($html, '<h2') !== false){
            return false;
        }
        $clear_html = strip_tags($html, '<p><b><h3><br><i>');
        return $clear_html;
    }
    protected function ConvertBook($id,$dir,$url){
        $opt = $this->GenerateAnotacia($id);
        $name_file = $this->FileName($opt['title']);
        if(empty($name_file)){
            return false;
        }
        $file_path = $url."/".$dir."/".$name_file.'.fb2';
        $text_book = $this->GetTextBook($id);
        if(!$text_book){
            return false;
        }
        require('bgFB2.php');
        $fb2 = New bgFB2(); 
        $book = $fb2->prepare($text_book,$opt);
        $fb2->save($dir."/".$name_file.".fb2",$book);
        return $file_path;
    }


    public function Download($id,$dir,$url){
        $site = $url;
        $url = get_headers('https://flibs.cloud/d?b='.$id.'&f=fb2');
        if($url[0]             == "HTTP/1.1 302 Found"){
            $file_path         = 'https://flibs.cloud/'.trim(explode(':',$url[5])[1]);
            $check_file        = get_headers($file_path);
            if($check_file[0]  == "HTTP/1.1 404 Not Found"){
                return $this->ConvertBook($id,$dir,$site);
            }else{
                return $file_path;
            }
        }else{
            return false;
        }
        return $url;
    }
    public function GetNewBook()
    {
        $data = [];
        $html = file_get_html("https://flibusta.club/");
        $i = 0;
        foreach($html->find('div.desc') as $div){
            foreach($div->find('div.date') as $date){
                $data[$i]['date']       = $date->plaintext;
            }
            foreach($div->find('div.name a') as $name){
                $data[$i]['name']       = $name->plaintext;
                $data[$i]['b_code']     = substr($name->href,3,10);
            }
            foreach($div->find('div.author a') as $author){
                $data[$i]['author']     = $author->plaintext;
                $data[$i]['a_code']     = substr($author->href,3,10);
            }
            foreach($div->find('div.intro') as $intro){
                $data[$i]['intro']      = $intro->plaintext;
            }
            foreach($div->find('div.intro') as $intro){
                $data[$i]['intro']      = $intro->plaintext;
            }
            $i++;
        }
        $i = 0;
        foreach($html->find('div.img a img') as $div){
            $data[$i]['img']            = $div->src;
            $i++;
        }
        return $data;
    }
    public function GetBookInfo($code)
    {
        $data = [];
        $html = file_get_html("https://flibusta.club/b/".$code);
        foreach($html->find('div.b_biblio_book_top') as $div){
            foreach($html->find('div.book_name') as $name){
                $data['name']                   = $name->plaintext;
            }
            foreach($html->find('div.book_left div.book_img img') as $img){
                $data['img']                    = $img->src;
            }
            $i = 0;
            foreach($html->find('div.book_desc div.author span.row_content a') as $author){
                $data['author'][$i]['name']     = $author->plaintext;
                $data['author'][$i]['a_code']   = substr($author->href,3,10)  ;
                $i++;
            }
            $i = 0;
            foreach($html->find('div.book_desc div.genre span.row_content a') as $genre){
                $data['genre'][$i]['name']      = $genre->plaintext;
                $data['genre'][$i]['g_code']    = substr($genre->href,3,10)  ;
                $i++;
            }
            $i = 0;
            foreach($html->find('div.book_desc div.series span.row_content a') as $series){
                $data['series'][$i]['name']     = $series->plaintext;
                $data['series'][$i]['s_code']   = substr($series->href,3,10)  ;
                $i++;
            }
            foreach($html->find('div.book_desc div.year_public span.row_content') as $year){
                $data['year']                   = $year->plaintext;
            }
        }
        foreach($html->find('div.b_biblio_book_annotation p.book') as $div){
            $data['annotation']                 = $div->plaintext;
        }
        return $data;
    }
    public function GetPopularBook($page)
    {
        $data = [];
        $html = file_get_html("https://flibusta.club/b?page=".$page);
        $i = 0;
        foreach($html->find('div.desc') as $div){
            foreach($div->find('div.date') as $date){
                $data[$i]['date']   = $date->plaintext;
            }
            foreach($div->find('div.name a') as $name){
                $data[$i]['name']   = $name->plaintext;
                $data[$i]['b_code'] = substr($name->href,3,10);
            }
            foreach($div->find('div.author a') as $author){
                $data[$i]['author'] = $author->plaintext;
                $data[$i]['a_code'] = substr($author->href,3,10);
            }
            foreach($div->find('div.intro') as $intro){
                $data[$i]['intro']  = $intro->plaintext;
            }
            foreach($div->find('div.intro') as $intro){
                $data[$i]['intro']  = $intro->plaintext;
            }
            $i++;
        }
        $i = 0;
        foreach($html->find('div.img a img') as $div){
            $data[$i]['img'] = $div->src;
            $i++;
        }
        return $data;
    }

    protected function OneResult($html){
        $code = substr($html->find('link')[0]->href,24,6);
        $book = $this->GetBookInfo($code);
        $data['book'][0]['b_name'] = $book['name'];
        $data['book'][0]['b_code'] = $code;
        $data['book'][0]['a_name'] = $book['author'][0]['name'];
        $data['book'][0]['a_code'] = $book['author'][0]['a_code'];
        return $data;
    }
    public function SearchFromAuthor($query,$page = 0){
        $result = $this->Search($query,$page)['auto'];
        if(!empty($result)){
            return $result;
        }else{
            return False;
        }
    }
    public function SearchFromBook($query,$page = 0)
    {
        $result = $this->Search($query,$page)['book'];
        if(!empty($result)){
            return $result;
        }else{
            return False;
        } 
    }
    public function SearchFromSerial($query,$page = 0)
    {
        $result = $this->Search($query,$page)['series'];
        if(!empty($result)){
            return $result;
        }else{
            return False;
        } 
    }
    protected function GetImageBook($code)
    {   
        $dir1 = substr($code,-3);
        $dir2 = substr($code,-2);
        if($dir2[0] == 0){
            $dir2 = $dir2[1];
        }
        if($dir1[0] == 0){
            $dir1 = substr($dir2,-2);
        }
        $url = "https://flibusta.club/i/".$dir1."/".$dir2."/".$code.".jpg";
        $url = trim($url);
        return $url;
    }
    public function Search($query,$page = 0)
    {
        $query              = str_replace(' ','+',$query);
        $data               = [];
        $data['auto']       = [];
        $data['series']     = [];
        $data['book']       = [];
        $html               = file_get_html("https://flibusta.club/booksearch?page=".$page."&ask=".$query);
        if(!$html->find('h1.title')){
            return $this->OneResult($html);
        }
        $i = 0;
        foreach($html->find('div#main li a') as $a){
                $class = $a->href[1];
                if($class == "s"){
                    $data['series'][] = [
                        's_name' => $a->plaintext,
                        's_code' => substr($a->href,3,10)
                    ];
                }elseif($class = 'a'){
                    $data['none_sort'][] = [
                        'n_name' => $a->plaintext,
                        'n_code' => $a->href
                    ];
                }
            $i++;
        }
        $count = count($data['none_sort']);
        $none = $data['none_sort'];
        for ($i=0; $i < $count ; $i++) { 
           if($none[$i]['n_code'][1] == "a"){
               if($none[$i-1]['n_code'][1] != "b"){
                   $data['auto'][]  = [
                        'a_name'    => $none[$i]['n_name'] , 
                        'a_code'    => substr($none[$i]['n_code'],3,10)
                    ];
               }else{
                    $data['book'][] = [
                        'b_name'    => $none[$i-1]['n_name'] ,
                        'b_code'    => substr($none[$i-1]['n_code'],3,10),
                        'b_cover'   => $this->GetImageBook(substr($none[$i-1]['n_code'],3,10)),
                        'a_name'    =>$none[$i]['n_name'],
                        "a_code"    => substr($none[$i]['n_code'],3,10)
                    ];
               }
           } 
        }
        unset($data['none_sort']);
        return $data;
    }
    public function GetBookFormAuthor($code,$language = "ru"){
        $data = [];
        $data['book'];
        $html = file_get_html("https://flibusta.club/a/".$code."/".$language);
        $i = 0;
        foreach($html->find('div.book__line a') as $book){
            if($book->href[1] == 'b'){
                $data['book'][$i] = [
                    'b_name'    => $book->plaintext,
                    'b_code'    => substr($book->href,3,10),
                    'b_cover'   => $this->GetImageBook(substr($book->href,3,10)),
                ];
                $i++;
            }
        }
        foreach($html->find('div.b_desc h1') as $book){
            $data['name']           = $book->plaintext;
        }
        foreach($html->find('div.b_desc div.txt') as $dis){
            $data['discription']    = $dis->plaintext;
        }
        return $data;
    } 
    public function GetBookFromSerial($code,$page = 0)
    {
        $data = [];
        $html = file_get_html("https://flibusta.club/s/".$code."?page=".$page);
        $i = 0;
        foreach($html->find('div.book__line a') as $book){
            if($book->href[1] == 'b'){
                $data[$i]=[
                    'b_name'    => $book->plaintext,
                    'b_code'    => substr($book->href,3,10),
                    'b_cover'   => $this->GetImageBook(substr($book->href,3,10)),
                ];
                $i++;
            }else{
                $data[$i-1]['a_name']   = $book->plaintext;
                $data[$i-1]['a_code']   = substr($book->href,3,10);
                $i++;
            }
            
        }
        return array_values($data);
    }

    public function GetListGenre(){
        $data = [];
        $html = file_get_html("http://flibusta.club/g/");
        $i = 0;
        $all_genre_string = '';
        foreach($html->find('a[href]') as $div){
            if(!empty($div->plaintext)){
                if($div->plaintext[0] != '('){
                    if(strlen($div->href )>1){
                        if($div->href[1] == 'g'){
                            $data[$i]['g_name'] = $div->plaintext;
                            $data[$i]['g_href'] = substr($div->href,3);
                        }
                    }
                }
            }
            $i++;
        };
        array_pop($data);
        $data = array_values($data);
        return $data;
    }

    public function GetBookGenre($b_code){
        $data = [];
        $html = file_get_html("https://flibusta.club/g/".$b_code."/Time");
        foreach($html->find('div.b_section_desc h1') as $book){
            $data['name'] = $book->plaintext;
        }
        $i = 0;
        foreach($html->find('ol a') as $book){
            if($book->href[1] == 'b'){
                $data['book'][$i] = [
                    'b_name' => $book->plaintext,
                    'b_code' => substr($book->href,3,10),
                    'b_cover' => $this->GetImageBook(substr($book->href,3,10)),
                ];
                $i++;
            }
        }
        return $data;
    }
}
