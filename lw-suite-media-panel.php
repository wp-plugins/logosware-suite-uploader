<?php


if ( is_admin() && !class_exists( 'lws_media_panel' ) ) :

class lws_media_panel {
	
	var $plugin_folder ='lw-suite-uploader';
	var $plugin_url;
	
	var $numPerPage = 10;
	
	function lws_media_panel(){
		global $pagenow;
		
		// post.php lw_content_list
		if ( 'post.php' == $pagenow ) {
			// 管理画面の「投稿」でLogosware Suiteボタンが押された時に、コンテンツ一覧をxmlで返す
			if($_POST['action'] == "lw_content_list" || $_GET['action'] == "lw_content_list"){
				
				if(isset($_POST['index']) && is_numeric($_POST['index'])){
					$index = $_POST['index'];
				}else{
					$index = 1;
				}
				
				$postMetaList = $this->getLwContentsMeta($index);
				$postList     = $this->getLwContents($postMetaList);
				$this->outputSuiteContentListXml($postList,$postMetaList,$index);
			}
		}
		
		$this->plugin_url=get_bloginfo("wpurl") . "/wp-content/plugins/" . $this->plugin_folder;
		
	}
	
	
	function bind_hooks() {
		// init process for button control
		add_action('init', array(&$this,'lw_suite_addbuttons'));
		add_action('admin_print_scripts',array(&$this,'admin_javascript'));
		add_action('admin_footer',array(&$this,'admin_footer'));
	}
	
	function lw_suite_addbuttons() {
		
	   	// 操作中のユーザーに操作の許可があるかをチェックする
	   	if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ){
			return;
	    }
	    //Rich Editor(ビジュアルエディタ)
	    add_filter("mce_external_plugins", array(&$this,'add_lwsuite_tinymce_plugin'));
	    add_filter('mce_buttons', array(&$this,'register_button'));
	     
	    //HTMLエディタ
		add_action('edit_form_advanced', array(&$this,'print_javascript'));
		add_action('edit_form_advanced', array(&$this,'print_css'));
		add_action('edit_page_form',array(&$this,'print_javascript'));
		add_action('edit_page_form',array(&$this,'print_css'));
		//add_action('admin_footer','print_javascript');
	}
	 
	function register_button($buttons) {
	   	array_push($buttons, "lwsuploader");
	   	return $buttons;
	}
	 
	// Load the TinyMCE plugin : editor_plugin.js (wp2.5)
	function add_lwsuite_tinymce_plugin($plugin_array) {
	   	$plugin_array['lwsuploader'] = $this->plugin_url . '/tinymce3/editor_plugin.js';
	   	return $plugin_array;
	}
	
	
	/**
	 * POSTMETAテーブルに入っている、meta_keyが'_lw_suite_type'のレコードを取得
	 *
	 */
	function getLwContentsMeta($index=1){
		global $wpdb;
		
		//$query = "SELECT * FROM " . $wpdb->postmeta . " WHERE meta_key = '_wp_attached_file'";
		//$query = "SELECT * FROM " . $wpdb->postmeta . " pm LEFT JOIN " . $wpdb->posts . " p ON p.ID = pm.post_id " .
		//			" WHERE meta_key = '_lw_suite_type'";
		
		$query = "SELECT * FROM " . $wpdb->postmeta . " WHERE meta_key = '_lw_suite_type' ORDER BY post_id DESC";
		$query .= " LIMIT " . $wpdb->escape(($index-1)*$this->numPerPage) .",". $wpdb->escape($this->numPerPage);
		
		
		$postmetaList = $wpdb->get_results($query);
		
		return $postmetaList;
		
	}
	
	
	/**
	 * "LW Suiteアップロード"画面から登録されたファイルだけを取得する
	 *
	 */
	function getLwContents($postmetaList){
		if(count($postmetaList) <= 0){
			return array();
		}
		global $wpdb;
		
		$postIdList = array();
		foreach($postmetaList as $meta){
			$postIdList[] = $meta->post_id;
		}
		
		$idStr = implode(",",$postIdList);
		
		$query2 = "SELECT * FROM " . $wpdb->postmeta . " pm LEFT JOIN " . $wpdb->posts . " p ON p.ID = pm.post_id " .
					" WHERE p.ID in(". $wpdb->escape($idStr) .") AND meta_key = '_wp_attached_file' ORDER BY p.ID DESC ";
		$postList = $wpdb->get_results($query2);
		return $postList;
	}
	
	
	function getLwContentsMetaTotalCount(){
		global $wpdb;
		$query = "SELECT count(*) as cnt FROM " . $wpdb->postmeta . " WHERE meta_key = '_lw_suite_type' ";
		$result = $wpdb->get_results($query);
		return $result[0]->cnt;
	}
	
	
	/**
	 * 記事投稿でLW Suiteボタンを押した時に表示される、コンテンツ一覧をXMLで返す。
	 *
	 */
	function outputSuiteContentListXml($list,$metaList,$index){
		$lwsType = array();
		foreach($metaList as $row){
			$lwsType[$row->post_id] = $row->meta_value;
		}
		
		header("Last-Modified: ". gmdate("D, d M Y H:i:s"). " GMT");
		header("Cache-Control: no-cache, must-revalidate", true);
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Pragma: no-cache");

		header ("Content-type: text/xml; charset=UTF-8", true);
		
		echo ('<?xml version="1.0" encoding="UTF-8" ?>' . "\n");

		echo "<response>";

		echo "<result>true</result>";
		echo "<data>";
		echo "<![CDATA[";
		
		if(count($list)>0){
			echo "<table class='lws_table'>\n";
			echo "<tr>\n";
			echo "<th valign='top'></th>\n";
			echo "<th>ID</th>";
			echo "<th>ファイル名</th>";
			echo "<th>タイトル</th>";
			echo "</tr>\n";
			$i=0;
			foreach($list as $row){
				
				/*
				$urlParts = parse_url($row->meta_value);
				
				$path = wp_upload_dir();
				$url = $path['baseurl'] . "/" . substr($urlParts['path'], 0, -11) . "/index.html";
				*/
				// Multi User機能でSTORM mobile版が見れないので共通のディレクトリに保存するように修正
				$path = get_option('siteurl');
				$url = $path . "/wp-content/uploads/" . substr($row->meta_value, 0, -11) . "/index.html";
				
				if($i++ == 0){
					$checked = "checked";
					$firstUrl = $url;
				}else{
					$checked = "";
				}
				$contentsId = "lw_content_id_".$row->ID;
				
				$onClickStr  = "jQuery(\"#lws_id\").val(\"" . $row->ID . "\");jQuery(\"#lws_type\").val(\"" . $lwsType[$row->ID] . "\");";
				$onClickStr .= " jQuery(\"#lws_url\").val(jQuery(\"#".$contentsId."\").attr(\"href\"));";
				$onClickStr  = "onClick='".$onClickStr."'";
				
				echo "<tr>\n";
				echo "<td class='lws_list'><input type='radio' name='lw_suite' value='" . $row->ID . "' " .$onClickStr . " " . $checked." ></td>\n";
				echo "<td class='lws_list' width='40'>" . $row->ID . "</td>\n";
				echo "<td class='lws_list'>" . $row->post_title . "</td>\n";
				echo "<td class='lws_list'><A id='".$contentsId."' href='".$url."' target='_blank' >" . $row->post_name . "</A></td>\n";
				echo "</tr>\n";
			}
			echo "</table>\n";
			echo "<input type='hidden' id='lws_id' name='lws_id' value='".$list[0]->ID."'>";
			echo "<input type='hidden' id='lws_type' name='lws_type' value='".$lwsType[$list[0]->ID]."'>";
			echo "<br>\n";
			
			$this->printPager($index);
			
			echo "<br>\n";
			echo "サイズ：";
			echo "<label for='size_s'>S<input type='radio' id='size_s' name='lws_size' value='S' checked></label>\n";
			echo "<label for='size_m'>M<input type='radio' id='size_m' name='lws_size' value='M' ></label>\n";
			echo "<label for='size_l'>L<input type='radio' id='size_l' name='lws_size' value='L' ></label>\n";
			echo "<br>\n";
			echo "コンテンツ一覧のラジオボタンを選んで「OK」を押すと埋め込み用コードが挿入されます。\n";
			echo "<br>\n";
			echo "「サイズ」のラジオボタンの選択によって、埋め込まれるコンテンツの表示サイズが変わります。\n";
			echo "<br>\n";
			echo "<br>\n";
			echo "URL:<input type='text' id='lws_url' name='lws_url' size='100' value='".$firstUrl."' onClick='jQuery(this).select();return false;' >";
			echo "<br>\n";
			echo "URLは、コピーして使うことで、記事中の文章にリンクを設定できます。\n";
			
			
		}
		
		echo "]]>\n";
		echo "</data>\n";
		echo "</response>\n";

		exit;
		
	}
	
	/**
	 * ページ送りの出力
	 */
	private function printPager($index=1){
		
		$total = $this->getLwContentsMetaTotalCount();
		
		
		if($total > $this->numPerPage){ // 1ページ内に入りきらない時にページ送りを表示
		
			$totalPageNum = ceil($total / $this->numPerPage);
			
			$newerStr = "&lt;&lt;Newer";
			if($index > 1){
				$newerStr = "<A href='' onClick='getSuiteContentList(".($index-1).");return false;'>".$newerStr."</A>\n";
			}
			$newerStr = $newerStr."&nbsp;&nbsp;";
			echo $newerStr;
			
			echo $index."/".$totalPageNum;
			$this->numPerPage * $index;
			
			$olderStr = "Older&gt;&gt;";
			if($index < $totalPageNum){
				$olderStr = "<A href='' onClick='getSuiteContentList(".($index+1).");return false;'>".$olderStr."</A>\n";
			}
			$olderStr = "&nbsp;&nbsp;".$olderStr;
			$olderStr = $olderStr . "<br>\n";
			echo $olderStr;
		}
	}
	
	function admin_javascript(){
		//show only when editing a post or page.
		if (strpos($_SERVER['REQUEST_URI'], 'post.php') || strpos($_SERVER['REQUEST_URI'], 'post-new.php') || strpos($_SERVER['REQUEST_URI'], 'page-new.php') || strpos($_SERVER['REQUEST_URI'], 'page.php')) {
		
			//wp_enqueue_script only works  in => 'init'(for all), 'template_redirect'(for only public) , 'admin_print_scripts' for admin only
			if (function_exists('wp_enqueue_script')) {
				$jspath='/'. PLUGINDIR  . '/'. $this->plugin_folder.'/jqModal/jqModal.js';
				wp_enqueue_script('jqmodal_lw', $jspath, array('jquery'));
			}
		}
		
	}
	
	function print_css() {
		?>
<style type="text/css">

table.lws_table{
	text-align:left;
	width:100%;
	border-collapse:collapse;
}

td.lws_list{
	border-bottom:1px solid #000000;
}

</style>
		<?php
	}
	
	/**
	 * Javascriptの出力。
	 * 「投稿」でコンテンツ一覧を表示するためのjsの出力。
	 * 
	 * 
	 */
	function print_javascript() {
	 
?>
   <!--  for popup dialog -->
   <link href="<?php echo $this->plugin_url . '/jqModal/jqModal.css'; ?>" type="text/css" rel="stylesheet" />

   <script type="text/javascript">
   	jQuery(window).load(function(){
		// Add the buttons to the HTML view
		jQuery("#ed_toolbar").append('<input type=\"button\" class=\"ed_button\" onclick=\"jQuery(\'#dialog_lwsuploader\').jqmShow();getSuiteContentList();\" title=\"LW Suite\" value=\"LW Suite\" />');
   	});

	jQuery(window).load(function () {
		jQuery('#dialog_lwsuploader').jqm();
	});

	function update_lwsuploader(){
		
		lws_insert_shortcode();
		jQuery('#dialog_lwsuploader').jqmHide();
		jQuery('#suite_list').html('');
		
	}
	
	function getSuiteContentList(index){
		
		var url = "post.php";
		var result_id = "suite_list";
		var param = {"action":"lw_content_list","index":index};
		jAjax(url, result_id, param);
		
	}
	
	function jAjax(url, result_id, param){

		try {
			if (result_id != null && result_id.charAt(0) != '#') {
				result_id = "#" + result_id;
			}

			// Ajax
			var con = jQuery.ajax({
				url: url,
				type: 'POST',
				data: param,
				dataType: 'xml',
				timeout: 60000,	// msec
				success: function(xml){
					try {
						jQuery(xml).find('response').each(function(){
							var result = jQuery(this).find('result').text();
							var message = jQuery(this).find('message').text();
							var url = jQuery(this).find('url').text();
							var script = jQuery(this).find('scripts').text();
							var data = jQuery(this).find('data').text();
							
							
							if (result == "true") {
								var obj;
								try {
									if (result_id && result_id != "" && result_id != "#") {
										obj = jQuery(result_id);
										obj.html(data);
									}
								}
								catch (ex1) {}

								if (obj) {
									obj.show();
								}
							}

						}); // response function end
					}
					catch (ex) {
						alert(ex.message);
						return false;
					}
				},
				error: function(XMLHttpRequest, textStatus, errorThrown){
					//errorの場合
					alert("ajax error");
				}
			});	// success function end

		}
		catch (ex2) {
			alert(ex2);
			return false;
		}

		return true;
	}
	
	
	function lws_insert_shortcode() {
		
		var id = jQuery("#lws_id").val();
		var type = jQuery("#lws_type").val();
		var size = jQuery("input[name=lws_size]:checked").val();
		
		var shortcode = '[lwsuite id="' + id + '" type="' + type + '" size="' + size + '"]';
		
		top.send_to_editor(shortcode);
		top.tb_remove();
		return false;
	}
	
   	</script>
	
	
	<?php   
	  //end of print_javascript 
	}
	
	
	
	function admin_footer(){
		
		if (strpos($_SERVER['REQUEST_URI'], 'post.php') || strpos($_SERVER['REQUEST_URI'], 'post-new.php') || strpos($_SERVER['REQUEST_URI'], 'page-new.php') || strpos($_SERVER['REQUEST_URI'], 'page.php')) {
		
		?>
		<div id="dialog_lwsuploader" class='jqmWindow' style='display:none'>
	<div style='width:100%;text-align:center'>
		<h3>Logosware Suite</h3>
		 
		<form name='contents_selection' onsubmit='return false;' >
			コンテンツ一覧
			<div id="suite_list"></div>
			
		 	<p class='submit'><input type='button' value='OK' onclick='update_lwsuploader()'; >
		 	<input type='button' value='Cancel' onclick="jQuery('#dialog_lwsuploader').jqmHide();" >
		 	</p>
			</div>
		
		</form>
		
	</div>
		
	  <?php 
		}
	}

}

$lws_panel = new lws_media_panel();

$lws_panel->bind_hooks();

endif; // end if !class_exists()


?>