<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Scrap extends CI_Controller {

	private $opts;
	private $plaintext;
	
	public function __construct()
	{
		parent::__construct();
		
		// Init header information
		$opts = array(
			'http' => array(
			'method' => "GET",
			'header' => "Accept-language: en\r\n" .
			"User-Agent: 	Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6\r\n" . "Cookie: search_sort=1; \r\n"
			)
		);
		$libraries = array(
		);
		$helpers = array (
			'url','file','simpledom'
		);
		$this->plaintext = TRUE;
		$this->load->library($libraries);
		$this->load->helper($helpers);
	}
	public function index()
	{
		echo "Hello World";
	}
	public function thisPage($url = NULL)
	{
		// variable URL dapat di inisialisasi melalui Request atau dibuat static 
		$search = "codeigniter";
		
		// Definisi dan Inisialisasi counter / Pagination
		$counter = 1;
		
		// Definisi dan Inisialisasi banyak Pagination yang di scrap
		$pagesCount = 2;
		
		for ($counter=1; $counter <= $pagesCount; $counter++)
		{
			if ($counter ==1)
			{
				$url = "http://citstudio.com/?s=codeigniter";
			}else{
				$url = "http://citstudio.com/page/".$counter."/?s=".$search;
			}
			
			echo "Start scraping for : ".$url."\n";
			$context = stream_context_create($this->opts);
					
			$html = file_get_html($url, FALSE, $context);
			
			if ($html) {
				foreach ($html->find("div#content div.post") as $el) {
					$item["title"] = trim(@$el->find("div.search-result h3.title", 0)->plaintext);
					$item["slug"] = trim(url_title($item["title"],'dash',TRUE));
					$datetime = trim(@$el->find("div.post-meta span.meta-date time", 0)->datetime);
					$item["date"] = date("Y-m-d",strtotime($datetime));
					$item["source_url"] =  @$el->find("div.search-result h3.title a", 0)->href;
					$item["thumbnail"] = @$el->find("div.search-content div.search-excerpt p a img", 0)->src;
					
					$item["article"] = "";
					
					// check if news already exists
					$checkForNews = "SELECT id, source_url FROM webscrap WHERE source_url='".$item["source_url"]."'";
					$resultForNews = $this->db->query($checkForNews)->result_array();
					if (sizeof($resultForNews) == 0)
					{
						$this->load->library('URLResolver');
						$resolver = new URLResolver();
						echo "Opening ".$item["source_url"]." \n";
						$url_result = $resolver->resolveURL($item["source_url"]);
						$item["source_url"] = $url_result->getURL();
						
						$content_html = @file_get_html($item["source_url"]);
						if ($content_html) 
						{
							if (sizeof($content_html->find("div.post-content div.post-excerpt p")) > 0) 
							{
								foreach ($content_html->find(".post-content div.post-excerpt p") as $content) 
								{
									$item["article"] .= " " . ($this->plaintext ? $content->plaintext : $content->outertext);
								}
								if (trim($item["article"]) != "") 
								{
									$insert = array(
											"title" => $item["title"],
											"date" => $item["date"],
											"content" => $item["article"],
											"thumbnail" => $item["thumbnail"],
											"slug" => $item["slug"],
											"source_url" => $item["source_url"]
									);
									$this->db->insert('webscrap', $insert);
									print_r($item);
								} 
								else 
								{
									echo "No Article Found \n";
								}
							} 
							else 
							{
								echo "No Article Found \n";
							}
							
						}
					}
					unset ($item);
				} //end of dom elemen finding
				echo "End Of Scrap. \n";
			} //end of html result context
		}
	}
}