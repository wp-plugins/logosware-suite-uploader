<?php
/*
Plugin Name: LOGOSWARE SUITE UPLOADER
Plugin URI: http://www.logosware.com/
Description: LOGOSWARE FLIPPER, STORM, THiNQをアップロードするプラグインです。
Author: LOGOSWARE
Version: 1.1.5
Author URI: http://www.logosware.com/
*/


include_once dirname(__FILE__) . '/lw-suite-media-panel.php';
include_once dirname(__FILE__) . '/lw-suite-shortcode.php';



if ( is_admin() && !class_exists( 'lw_suite_uploader' ) ) :

class lw_suite_uploader {
	var $upDirName = "logosware/";
	var $upDir = ""; 		//"../wp-content/uploads/logosware/";
	var $tempDir = ""; 		//"../wp-content/uploads/logosware/temp/";
	
	var $menuTitle = "LW Suiteアップロード"; // 管理画面メニューツリー
	var $pluginTitle = "LOGOSWARE SUITE UPLOADER"; // HTMLのタイトル
	var $pageCaption = "<h2>Logosware Suite アップロード</h2>"; // アップロードページの一番上に表示される文字
	
	var $suiteTypeArray = array("FL"=>"flipper", "ST"=>"storm","TH"=>"thinq");
	
	/**
	 * コンストラクター。
	 * メンバ変数の初期化とaction、filterの追加。
	 */
	function lw_suite_uploader() {
		global $pagenow;
		global $blog_id;

		if(!empty($blog_id)){
			 $this->upDirName = $this->upDirName . $blog_id . "/";
		}
		
		/*
		$path = wp_upload_dir();
		$this->upDir = $path['basedir'] . "/" . $this->upDirName;
		$this->tempDir = $path['basedir'] . "/" . $this->upDirName . "temp/";
		*/
		
		// Multi User機能でSTORM mobile版が見れないので共通のディレクトリに保存するように修正
		$path = getCwd();
		$length1 = strlen($path);
		$length2 = strlen("/wp-admin");
		$path = mb_substr($path,0 ,$length1 - $length2);
		$path = $path . "/wp-content/uploads";
		$this->upDir = $path . "/" . $this->upDirName;
		$this->tempDir = $path . "/" . $this->upDirName . "temp/";
		
		
		// 管理メニューに追加するフック
		add_action('admin_menu',  array( &$this,'mt_add_page' ));
		
		if ( $pagenow == 'upload.php' ) {
			add_action('admin_head', array( &$this,'delete_files' ));
		}
	}
	
	
	
	/**
	 * 管理画面から削除されたLWコンテンツファイルをサーバー上から削除する処理
	 */
	function delete_files(){
		global $wpdb;
		
		$query = "SELECT * FROM " . $wpdb->postmeta . " WHERE meta_key = '_wp_attached_file'";
		$postmetaList = $wpdb->get_results($query);
		
		// アップロードディレクトリのディレクトリ一覧を取得
		if( !file_exists($this->upDir) ){
			// 1つもアップロードしていない場合
			return;
		}
		$dirList = array();
		$drc=dir($this->upDir);
		while($f=$drc->read()) {
			if($f != ".." && $f != "."){
				$fl = $this->upDir.$f;
				$pathParts = pathinfo($fl);
				if(is_dir($fl)){
					if($fl."/" != $this->tempDir){ // tempディレクトリは除く
						$dirList[] = $f;
						//echo $f;
						//echo "<br>";
					}
				}
			
			}
		}
		$drc->close();
		
		// postmetaレコードにないディレクトリを見つける
		foreach($dirList as $directory){
			$isDirFound = false;
			foreach($postmetaList as $meta){
				if( $this->upDirName . $directory == dirname($meta->meta_value) ){
					$isDirFound = true;
				}
			}
			
			// ディレクトリがあって、レコードが無い場合に、ディレクトリを削除
			if(!$isDirFound){
				//echo "削除対象";
				//echo $this->upDir.$directory;
				$this->removeDirectory($this->upDir.$directory);
			}
		}
		
	}
	
	
	
	/**
	 * メニューに追加するフック に対するaction関数
	 */
	function mt_add_page() {
	    add_media_page($this->pluginTitle, $this->menuTitle, 6, 'lwfile_upload', array(&$this,'lwfile_upload_page'));
	}
	
	/**
	 * "Stormアップロード"メニューがクリックされたときのaction関数
	 */
	function lwfile_upload_page() {
		global $pagenow;
		
		echo "<div class=\"wrap\">\n";
		echo "<div id=\"icon-upload\" class=\"icon32\"><br></div>\n";
		echo $this->pageCaption;
		
		if ( 'upload.php' == $pagenow ) {
			if($_GET['step'] == "uploadzip"){
				//add_action('admin_menu',  array( &$this,'mt_add_page' ));
				$this->register();
			}
			// アップロードフォームを表示
			$this->displayForm();
		}
		echo "</div>\n";
	}
	
	function displayForm(){
		$size = $this->formatSize(wp_max_upload_size());
?>
		<form enctype="multipart/form-data" method="post" action="upload.php?page=lwfile_upload&step=uploadzip" class="media-upload-form type-form validate" id="file-form">
		<input type="file" name="zip">
		<input type="submit" value="アップロード" class="button">
		</form>
		<br>
		アップロードファイルの最大サイズ: <?php echo $size; ?>
<?php
	}
	
	function displayErrorMsg($msg,$isError=true){
		if($isError){
			echo "<p style='color:red'>";
		}
		echo $msg;
		if($isError){
			echo "</p>";
		}
	}
	
	/**
	 * ファイル登録
	 * 
	 */
	function register(){
		if (is_uploaded_file($_FILES["zip"]["tmp_name"])) {
			// アップロードディレクトリ
			if( !file_exists($this->upDir) ){
				//ディレクトリがなかったら作る
				lw_suite_uploader::createDir($this->upDir);
				chmod($this->upDir, 0777);
			}
			// アップロード一時ディレクトリ
			if( !file_exists($this->tempDir) ){
				//ディレクトリがなかったら作る
				lw_suite_uploader::createDir($this->tempDir);
				chmod($this->tempDir, 0777);
			}
			$temporaryDir = date("YmdHis");
			$temporaryPath = $this->tempDir . $temporaryDir . "/";
			//ディレクトリを作る
			lw_suite_uploader::createDir($temporaryPath);
			
			// ファイル名の拡張子を除いた部分を取得
			$pathParts = pathinfo($_FILES["zip"]["name"]);
			if( $pathParts["extension"] != "zip"){
				$this->displayErrorMsg("拡張子がzipではありません。");
				return false;
			}
			
			// アップロードされたzipの拡張子を除いたファイル名
			$basenameWithoutExt = mb_substr($_FILES["zip"]["name"], 0, mb_strlen($_FILES["zip"]["name"])-4);
			// 一時ディレクトリに保存するときのファイル名
			$tempZipName = "zipfile".date("His").".zip";
			// 一時ディレクトリに保存するときのパス
			$tempZipPath = $temporaryPath . $tempZipName;
			if (move_uploaded_file($_FILES["zip"]["tmp_name"], $tempZipPath)) {
				chmod($tempZipPath, 0777);
				
				//echo ($_FILES["zip"]["name"] . "をアップロードしました。");
				
				// zipの解凍
				if( !file_exists($tempZipPath) ){
					echo "zipのアップロードに失敗しました。";
					return false;
				}
				
				//if( !$this->unzip($tempZipPath, $this->tempDir . "20110125") ){
				if( !$this->extractZip($tempZipPath, $temporaryPath . $basenameWithoutExt ."/") ){
					$this->displayErrorMsg("zipの解凍に失敗しました。");
					return false;
				}
				
				if( file_exists($temporaryPath . $basenameWithoutExt) ){
					list($xmlPath, $typeSuite, $isDirNameTwice) = $this->findXml($temporaryPath, $basenameWithoutExt);
					if($xmlPath == ""){
						$this->displayErrorMsg("FLIPPERとSTORM以外のファイルです。(XMLファイルが見つかりませんでした)");
						return false;
					}
					
					$contentName = $this->getContentInfo($xmlPath, $typeSuite);
					
				}else{
					$this->displayErrorMsg("zipファイルの解凍に失敗しました。");
					return false;
				}
				
				$contentDirName = $this->moveToContentsDir($temporaryDir,$basenameWithoutExt,$isDirNameTwice,$typeSuite);
				$this->registerDB($basenameWithoutExt, $contentDirName, $contentName, $typeSuite);
				$message  = "ファイルを登録しました。\n<br>";
				$message .= "【投稿を編集】または【固定ページを編集】のLWボタン（HTMLモードの場合はLW Suiteボタン）からコンテンツを選択できます。";
				$this->displayErrorMsg($message);
				return true;
				
			} else {
				$this->displayErrorMsg("ファイルをアップロードできません。");
			}
		} else {
			$this->displayErrorMsg("ファイルが選択されていません。");
		}
	}
	
	/**
	 * 　xmlファイルを見つける
	 * ・タイトルを取得するため
	 * ・FLIPPERかSTORMかを判定するため
	 * ・zipを解凍したときzipファイル名と同じディレクトリが出来る場合と出来ない場合を分けるため
	 * 
	 * 戻り値：array(xmlのパス, $typeSuite, $isDirNameTwice);
	 */
	private function findXml($temporaryPath, $basenameWithoutExt){
		$path = $temporaryPath . $basenameWithoutExt;
		
		if( file_exists($path . "/book.xml") ){ // FLIPPER
			return array($path . "/book.xml",  "FL", false);
		}else if( file_exists($path . "/". $basenameWithoutExt . "/book.xml") ){
			return array($path . "/". $basenameWithoutExt . "/book.xml",  "FL", true);
			
		}else if( file_exists($path . "/contents_config.xml") ){ // STORM
			return array($path . "/contents_config.xml",  "ST", false);
		}else if( file_exists($path . "/". $basenameWithoutExt . "/contents_config.xml") ){
			return array($path . "/". $basenameWithoutExt . "/contents_config.xml",  "ST", true);
			
		}else if( file_exists($path . "/THiNQplayer.swf") ){ // THiNQ
			return array($path . "/index.html",  "TH", false);
		}else if( file_exists($path . "/". $basenameWithoutExt . "/THiNQplayer.swf") ){
			return array($path . "/". $basenameWithoutExt . "/setting/first.xml",  "TH", true);
		}else{
			return array("", "", false);
		}
	}
	
	
	
	private function moveToContentsDir($temporaryDir, $basenameWithoutExt, $isDirNameTwice, $typeSuite=true){
		
		if($typeSuite == "FL"){
			$contentDirName = "FL" . date("YmdHis");
		}else if($typeSuite == "ST"){
			$contentDirName = "ST" . date("YmdHis");
		}else if($typeSuite == "TH"){
			$contentDirName = "TH" . date("YmdHis");
		}
		$destPath = $this->upDir . $contentDirName . "/";
		
		if($isDirNameTwice){
			// 解凍したときzipファイル名と同じディレクトリが出来る場合
			$basenameWithoutExt = $basenameWithoutExt . "/" . $basenameWithoutExt;
		}
		rename($this->tempDir . $temporaryDir . "/" . $basenameWithoutExt, $destPath);
		chmod($destPath,0777);
		
		// tempディレクトリを消す
		$this->removeDirectory($this->tempDir . $temporaryDir . "/");
		
		return $contentDirName;
	}
	


	/**
	 * XMLを解析してタイトルを取得する
	 */
	private function getContentInfo($path,$typeSuite){
		$xmlObj = $this->readXml($path);
		//echo "<pre>";
		//print_r($xmlObj);
		//echo "</pre>";

		if($typeSuite == "FL"){
			return $xmlObj->bookInformation->bookTitle;
		}else if($typeSuite == "ST"){ // storm
			// stormのxmlからタイトルを取得
			return $xmlObj->title;
		}else if($typeSuite == "TH"){ // thinq
			return $xmlObj->h1->testName;
		}
	}
	private function readXml($xmlPath){
		if (file_exists($xmlPath)) {
			$xmlObj = simplexml_load_file($xmlPath,'SimpleXMLElement', LIBXML_NOCDATA);
		} else {
			//exit('Failed to open book.xml.');
			return array();
		}
		return $xmlObj;
	}
	
	/**
	 * zipファイルを解凍
	 *
	 * @param unknown_type $path
	 */
	public function extractZip($file, $path=""){
		if (!file_exists($file)) {
			// "zipファイルが見つかりませんでした"
			return false;
		}
		
		if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
			// 2011/4/22 windows用 PHP5.2.0以上
			//$destPath = dirname($path);
			$ret = lw_suite_uploader::unzip($file,$path);
			return $ret;
			//return array($destPath,$ret);
		}else{
			//標準出力に書き出すとエラーになるので出力ファイルを指定する
			$outputPath = "/dev/null";
			//$destPath = dirname($file)."unzip";
			if($path == ""){
				$path = dirname($file);
			}
			system("unzip ".$file. " -d ".$path . " > ".$outputPath, &$ret);
		}
		return true;
	}
	
	
	/**
	 * データベースに登録
	 * 
	 */
	private function registerDB($basenameWithoutExt, $contentDirName, $contentName, $typeSuite){
		global $wpdb;
		
		
		/*
		INSERT INTO `wp_posts` (`ID`,
		 `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`,
		 `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, 
		 `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, 
		 `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES
		 
		(4,
		 1, '2011-01-26 12:05:08', '2011-01-26 03:05:08', '', '10製-LI010108-02_kai02', '',
		 'inherit', 'open', 'open', '', '10%e8%a3%bd-li010108-02_kai02',
		 '', '', '2011-01-26 12:05:08', '2011-01-26 03:05:08', '', 0,
		 'http://soukenwp/wp-content/uploads/2011/01/10製-LI010108-02_kai02.pdf', 0, 'attachment', 'application/pdf', 0);
		
		
		INSERT INTO `wp_postmeta` (`meta_id`, `post_id`, `meta_key`, `meta_value`) VALUES
(2, 4, '_wp_attached_file', '2011/01/10製-LI010108-02_kai02.pdf');
		
		*/
		
		if(true){
			//strom
			$startFileName = "index.html";
		}else{
			//flipper
			$startFileName = "index.html";
		}
		
		
		//$path = wp_upload_dir();
		// Multi User機能でSTORM mobile版が見れないので共通のディレクトリに保存するように修正
		$path = get_option('siteurl');
		$url = $path . "/wp-content/uploads";
		$path = array();
		$path['baseurl'] = $url;
		
		
		$url =  $path['baseurl'] . "/" . $this->upDirName . $contentDirName . "/" . $startFileName;
		$uploadedPath = $this->upDirName . $contentDirName . "/" . $startFileName;
		$currentUser = wp_get_current_user();
		
		$wpdb->insert( $wpdb->posts,	array( 	'post_author' 			=> $currentUser->ID, // get_the_author_meta('ID'), 
												'post_date' 			=> date("Y-m-d H:i:s"), 
												'post_date_gmt' 		=> gmdate("Y-m-d H:i:s"), 
												'post_content' 			=> '', 
												'post_title' 			=> htmlspecialchars($basenameWithoutExt), //urlencode($basenameWithoutExt),
												'post_excerpt' 			=> htmlspecialchars($contentName), 
												'post_status' 			=> 'inherit', 
												'comment_status' 		=> 'open', 
												'ping_status' 			=> 'open', 
												'post_password' 		=> '', 
												'post_name' 			=> htmlspecialchars($contentName), 
												'to_ping' 				=> '', 
												'pinged' 				=> '', 
												'post_modified' 		=> date("Y-m-d H:i:s"), 
												'post_modified_gmt' 	=> gmdate("Y-m-d H:i:s"), 
												'post_content_filtered' => '', 
												'post_parent' 			=> 0, 
												'guid' 					=> $url, 
												'menu_order' 			=> 0, 
												'post_type' 			=> 'attachment', 
												'post_mime_type' 		=> 'text/html', 
												'comment_count' 		=> 0, 
										), 
										array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d' ) );
		$postId = $wpdb->insert_id;
		
		
		
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $postId, 'meta_key' => '_wp_attached_file', 'meta_value' => $uploadedPath ), array( '%d', '%s', '%s' ) );
		// wp_postmeta に Lw Suite 種別を設定
		$suiteTypeName = $this->suiteTypeArray[$typeSuite];
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $postId, 'meta_key' => '_lw_suite_type', 'meta_value' => $suiteTypeName ), array( '%d', '%s', '%s' ) );
		
		//$myrows = $wpdb->get_results( "SELECT * FROM wp_postmeta" );
		//echo "test";
		//print_r($myrows);
	}
	
	
	
	/**
	 * ZIPファイルの解凍。
	 *
	 * @param string $file
	 * @param string $path
	 * @return boolean
	 * @since PHP5.2.0
	 */
	public static function unzip($file, $path) {

		$result = false;

		// PHP 5.2.0以上で利用可能
		$zip = new ZipArchive();
		if ($zip->open($file) === true) {
			$result = $zip->extractTo($path);

			// クローズ
			$zip->close();
		}

		return $result;
	}
	
	
	
	/**
	 * 再帰的にディレクトリ作成
	 *
	 * @param string $path
	 */
	public static function createDir($path, $mode = 0777) {
		if (file_exists($path)) {
			return;
		}
		$buf = explode(DIRECTORY_SEPARATOR, $path);
		$str = '';
		$cnt = sizeof($buf);
		for ($i = 0; $i < $cnt; $i++) {
			$str .= $buf[$i] . DIRECTORY_SEPARATOR;
			if (!file_exists($str)) {
				mkdir($str);
				chmod($str, $mode);
			}
		}
	}
	
	/**
	 * 中にファイルやディレクトリがあるディレクトリを再帰処理で削除する関数
	 * 
	 */
	function removeDirectory($dir) {
		if ($handle = opendir($dir)) {
			while (false !== ($item = readdir($handle))) {
				if ($item != "." && $item != "..") {
					if (is_dir("$dir/$item")) {
						$this->removeDirectory("$dir/$item");
					} else {
					unlink("$dir/$item");
				}
			}
		}
		closedir($handle);
		rmdir($dir);
		}
	}
	
	
	
	private function formatSize($size) {
		$sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
		if($size == 0){
			return('0Bytes');
		}else{
			return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]);
		}
	}



} // end lw_suite_uploader

$GLOBALS['lw_suite_uploader'] = new lw_suite_uploader();

endif; // end if !class_exists()

?>