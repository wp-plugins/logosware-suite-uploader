<?php


if ( !class_exists( 'lws_shortcode' ) ) :

/**
 *
 * 記事を表示するときに、LwSuiteのショートコードをiframeに置き換える
 *
 */
class lws_shortcode {
	
	function lws_shortcode(){
		global $pagenow;
		
		add_action('wp_head', array(&$this,'print_css_link'));
		add_shortcode('lwsuite', array(&$this,'lwsuitetag_func'));
	}
	
	/**
	 * shortcode
	 * 
	 */
	function lwsuitetag_func( $atts ) {
		extract(shortcode_atts( array(
					'id' => 0,
					'type' => 'storm',
					'size' => 'm',
					), $atts ) );
		
		global $wpdb;
		$query = "SELECT * FROM " . $wpdb->postmeta . " pm LEFT JOIN " . $wpdb->posts . " p ON p.ID = pm.post_id " .
					" WHERE p.ID =".$id." AND meta_key = '_wp_attached_file'";
		$postList = $wpdb->get_results($query);
		
		if(count($postList) == 1){
			$startFile = $postList[0]->meta_value;
		}else{
			return "lw suite id_error";
		}
		
		$urlParts = parse_url($startFile);
		$uploadedDir = substr($urlParts['path'], 0, -11);
		
		/*
		$path = wp_upload_dir();
		$url = $path['baseurl'] . "/" . $uploadedDir;
		*/
		// Multi User機能でSTORM mobile版が見れないので共通のディレクトリに保存するように修正
		$path = get_option('siteurl');
		$url = $path . "/wp-content/uploads/" . $uploadedDir;
		//basedir
		$path = getCwd();
		$path2 = $path . "/wp-content/uploads";
		$path = array();
		$path['basedir'] = $path2;
		
		
		if($type == "storm"){
			if($size == "S"){
				$width  = "560";
				$height = "420";
			}else if($size == "L"){
				$width  = "800";
				$height = "600";
			}else{ //($size == "M")
				$width  = "640";
				$height = "480";
			}
			
			if(file_exists($path['basedir']."/".$uploadedDir."/embed.html")){
				$filename = '/embed.html';
			}else{
				$filename = '/index.html';
			}
			
			$retStr  = '<iframe width="'.$width.'" height="'.$height.'" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="';
			$retStr .= $url . $filename . '?start=pause&index=1"></iframe>';
			
			
			if(file_exists($path['basedir']."/".$uploadedDir."/share.html")){
				$filename = '/share.html';
			}else{
				$filename = '/index.html';
			}
			
			$retStr .= '<br><A class="motoBtn" href="'.$url.$filename.'?start=pause&index=1" target="_blank">元のコンテンツを見る</A>';
			
			
		}else if($type == "flipper"){
			if($size == "S"){
				$width  = "300";
				$height = "210";
			}else if($size == "L"){
				$width  = "800";
				$height = "560";
			}else{ //($size == "M")
				$width  = "500";
				$height = "350";
			}
			$retStr = '<iframe width="'.$width.'" height="'.$height.'" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="' . 
						$url . '/embedskin/index.html?page=1&initEmbedSkin=400_300_0"></iframe>';
			
		}else{ // if($type == "thinq")
			
			$retStr = '<iframe width="700" height="600" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="' . 
						$url . '/index.html"></iframe>';
			
		}
		
		
		//$retStr = "<B>ここに埋め込みコードを出力 id:" . $params['id'] . "</B>";
		return $retStr;
	}
	
	function print_css_link(){
		
		echo "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"" . get_option('siteurl')  . "/wp-content/plugins/lw-suite-uploader/css/btn.css\" />\n";
		
	}
}

$lws_shortcode = new lws_shortcode();


endif; // end if !class_exists()

?>