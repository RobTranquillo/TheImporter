<?php
/*
Plugin Name: TheImporter
Plugin URI: http://robtranquillo.wordpress.com
Description: Generate new worpress-posts by importig an directory. For more, read the readme!
Version: 0.1
Author: Rob Tranquillo
Author URI: http://robtranquillo.wordpress.com
Update Server: *
Min WP Version: 3.4.2
Max WP Version: *

	Copyright 2012  Rob Tranquillo  (email: rob.tranquillo@gmail.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

 class TheImporterException extends Exception {}

 class TheImporterPlugin {
	var $settings 		= array();	// update and get settings -> build $settings 
	var $deletescript	= array();	// $deletescript['files'], $deletescript['dirs'], $deletescript['sql']
	var $all_posts_data = array();	// this array contains all posts as objects 
	var $wp_upload_dir_url = ''; 	//needed for image creation
	
	/* *************************************************************************************
		This is the main function for TheImporter. It can be started by an cron script or from a browser with the URL-extension: "run_import=1"
		TheImporter_start looks into the folder, defined by the value of "importfolder" in settings.ini for post to import.	
	*/
	function plugin_page() {
		$this->settings( 'start' ); //update and get settings -> build $settings, 'start' defines the startpoint of the plugin
	
		if($_GET['run_import'] == '1') { $this->run_import(); exit; }
	
		//Beispiel-String, lt Einstellungen in den Settings, ANSI -> UTF8 kodieren oder eben nicht
		$str = "Für Löffel gehört füttern zum Geschäft!";
		if($this->settings['utf8_decode'] == 1)  $str = utf8_decode($str);				
				
		$now = '<br>'.date('Y-m-d H:i:s');
		echo '<h2>Plugin Settings</h2>'.
			'Hinweis: Alle Ordner müssen existieren! <br>'.
			'<b>Nicht vergessen, in der *.ini die Datenbankinformationen einzutragen! </b><br>'.			
			'<br>Server Time: '.$now. 
			'<form method=post enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?page=TheImporter/TheImporter.php">'.
			'<table>'.
				'<tr><td>InputDir:					</td><td>	<input 	name="settings[importfolder]" 		size=50	type="text"	value="'.$this->settings['importfolder'].'"> </td><td></td></tr>'.
				'<tr><td>WorkingDir:				</td><td>	<input 	name="settings[workfolder]"   		size=50 type="text"	value="'.$this->settings['workfolder'].'"> </td><td></td></tr>'.
				'<tr><td>Into category: 			</td><td>	'.wp_dropdown_categories(array('show_option_all' => 'no category or from XML', 'hide_empty' => 0, 'hierarchical' => 1, 'show_count' => 0, 'name' => 'parent_category', 'orderby' => 'name', 'selected' => $this->settings['parent_category'], 'echo'=> 0)).'</td><td> </td></tr>'.
				'<tr><td>as author: 				</td><td>	'.wp_dropdown_users(array('show_option_all'=>'none..', 'name' => 'author', 'selected' => $this->settings['author'], 'echo'=> 0 )).'</td><td> </td></tr>'.
				'<tr><td>logfile:	   	   			</td><td>	<input 	name="settings[logfile]" 			size=50	type="text"	value="'.$this->settings['logfile'].'"> </td><td> </td></tr>'.				 
				'<tr><td>logfile-history:			</td><td>	<input	name="settings[history_log]"		size=3	type="text"	value="'.$this->settings['history_log'].'" maxlength=1> (1 = yes / 0 = no)</td><td> </td></tr>'.
				'<tr><td>ANSI->UTF8:				</td><td>	<input	name="settings[utf8_decode]"		size=3	type="text"	value="'.$this->settings['utf8_decode'].'" maxlength=1> (1 = yes / 0 = no)</td><td> (<b>ANSII-example:</b> '.$str.')</td></tr>'.
				'<tr><td>Customfields in XML		</td><td>	<input	name="settings[xml_meta]"			size=3	type="text"	value="'.$this->settings['xml_meta'].'" maxlength=1> (1 = yes / 0 = no) NOT WORKING YET!</td><td> Custumfields are saved in an XML Stucture or in an [property]:[value] Structure?</td></tr>'.
				'<tr><td>delete further imported	</td><td>	<input	name="settings[delete_before_input]"size=3	type="text"	value="'.$this->settings['delete_before_input'].'" maxlength=1> (1 = yes / 0 = no)</td><td></td></tr>'.
				'<tr><td>							</td><td>	<br> <input name="wurst" type="submit" value="Einstellungen speichern"></td></tr>'.
			'</table>'.
			'</form>';
	}	
	
	
	/* *************************************************************************************
 		reads the settings *.ini and work with the given parameters 
		reads the input-dir and inserts all posts by parsing a textfile and input picures laying by the textfile
		on a sidechain it builds the delete-scripts for the next time when 
		autostart runs to clean the import directory
	*/
	function run_import()
	{
		ini_set('memory_limit','512M'); 
		define('WP_MAX_MEMORY_LIMIT', '512M');
		
		if( $this->get_posts_from_input_dir() )  $this->insert_posts();	
		echo 'Import finished. See the logfile for further information!';
	}
	
	/* *************************************************************************************
		collect all picture-files and one txt-file under $file with path
		Die Funktion sammelt alle Infos zusammen aus denen dann der nächste post gebaut wird
		title, post, customfields, tags, bilder in einem entp. Multiarray		
	*/	
	function get_files_from_dir( $rootdir )
	{
		if(  func_num_args() == 2 ) $files_arr = func_get_arg(1);  //eine vorhandenes Array wird weitergeführt (nur gebraucht wenn func recursiv verwendet wird)
		else $files_arr = Array();
		$pictureformats = explode(',' , $this->settings['pictureformats']);
		
		if( is_dir($rootdir) ) 
		{
			$rsc = opendir( $rootdir );
			while (false !== ($file = readdir($rsc))) 
			{	
				if( $file == '.' || $file == '..' ) continue;
				$file = $rootdir.'/'.$file;			
				
				//für jedes Bildformat in der *.ini suchen
				foreach( $pictureformats AS $format )
				{
					if(strtolower( substr($file,-3,strlen($file)) ) == $format)  //Ein Bild wurde gefunden
					$files_arr['pics'][] = $file;
					#echo_br($file);
				}
				
				if(strtolower( substr($file,-3,strlen($file)) ) == 'xml')  //Ein XML file wurde gefunden
				{ 
					$files_arr['postdata'] = $this->get_xml_file( $file ); //returns an OBJECT->! not an Array!
				}
			}
			closedir($rsc);	
		}
		#print_ar($files_arr);
		if(count($files_arr) > 0) return $files_arr;
		return false;			
	}	
	
	/* *************************************************************************************
		gets file content,  replace ampersand für HTML, and correct the multibyte-property
	*/
	function get_xml_file( $file )
	{
		$xml = file_get_contents( $file );
		$xml = str_replace('&', '&amp;', $xml);		//convert ampersamp to HTML cause XML interpret them by it self
		if( mb_detect_encoding($xml, 'UTF-8', true) === false ) $xml = utf8_encode( $xml );
		$xml = simplexml_load_string( $xml );
		return $xml;
	}
	
	
	/* *************************************************************************************
		Funktion kann genutzt werden um alle Daten aus dem importierten Verzeichnis nochmal 
		zu überarbeiten / kontrollieren / etc..
		Function can be uesed to do some postcollecting-operations with all that imported data
	*/
	function get_all_post_data( $dir )
	{	
		$files = $this->get_files_from_dir( $dir );
		if( $files === false ) $this->write_log('Fehler, konnten keinen Posts im Eingangs-Verzeichnis gefunden werden');  
		return $files;		
	}
	
	/* *************************************************************************************
		function scans importDir and add all data that be connected to a post into 
		global Array: $all_posts_data 		
	*/
	function get_posts_from_input_dir()
	{	
		if( ! is_dir( $this->settings['importfolder']) ) 
		{ 
			echo 
			$err = '<br> Fehler: kein echtes Verzeichnis in settings.ini angegeben!';
			$this->write_log($err);
			return false; 
		}

		if($rid = opendir( $this->settings['importfolder'] )) // $rid = handle für RootInputDir
		{
			while (false !== ($dir = readdir($rid))) 
			{
				if( $dir == '.' || $dir == '..' ) continue;
				$data = $this->get_all_post_data( $this->settings['importfolder'].'/'.$dir ); // alle Postdaten aus diesem Verz ermitteln
				if( $data !== false ) $this->all_posts_data[] = $data;
			}
			closedir($rid);
		}		
		if( count( $this->all_posts_data ) == 0 ) return false; //seems that no data was found
		else return true;
	}	
	
	
	/* *************************************************************************************
		inserts a new category to db and returns the new catID
		if no catagory is passed, the category from plugin-page will be used, it gives back CatID 1 for the top category
		Is a parentCategory selected in plugin-page then insert it as chiled of that one
		Is no parentCategory selected in plugin-page then insert it as chiled of the root cat
		Is no Category passed to the function give back the id of the parentCat
		Is no Category passed to the function and no parent Category selected in pluginpage give back the id of the root-cat = 1
		Is 
		
	*/
	function check_and_insert_category($catname)
	{
	
		if($this->settings['parent_category'] == '0') return 0;
		
		if( $catname = trim($catname) == '') return 1;
		
		//before questioning the db, habe a look in to the session
		if($_SESSION['newCat'][$catname]['cat_id'] != '') return $_SESSION['newCat'][$catname]['cat_id']; 
		
		//if the session was a woodway, take a look in the db
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT term_id,name FROM wp_terms WHERE name LIKE '$catname'" );			
		
		//create new cat //wenn keine alte KategorieID in der DB war
		if($rows['term_id'] == 0) 
		{			
			$cat_defaults = array(
				'cat_name' 				=> $catname,
				'category_description' 	=> $catname,
				'category_nicename' 	=> $catname,
				'category_parent' 		=> $this->plugin_settings['parent_category']);
			$cat_id = wp_insert_category($cat_defaults , $err);
		}
		$_SESSION['newCat'][$catname]['cat_id'] = $cat_id;
		return $cat_id; 
	}
	
	
	/*  *************************************************************************************
		check the author
		if no author is set in settings or in the imported txt-file, so return standard-author 1
		an author in plugin settings is stronger than an autor in the txt-file
	*/
	function check_and_insert_author( $author )				
	{		
		$author = trim($author);		
		if( $author == '' ) return 1;
		//if an is set author in settings, its more important than an passed author
		if( $this->plugin_settings['author'] != '' && $this->plugin_settings['author'] != 'none' ) $author = $this->plugin_settings['author'];
		
		//if the passed author exists, return it if not return standard author 1
		$u = get_user_by('id', $author );
		if( $u === false ) return 1;
		else return $author;		
	}
	
	/*  *************************************************************************************
		function inserts all posts there are found in the direcories into wpdb as new post
		[#post]
				->category  //gibts noch nicht
				->title 
				->post 			
				->author 
				->customfields 
				->tags
			[0] ..path pic1
			[1] ..path pic2
			[2] ..path pic3
			[...]
	*/		
	function insert_posts()				
	{						
		$this->write_log('startnewfile');
		require_once(ABSPATH . 'wp-admin/includes/image.php'); //needed for images-metadata creation	
		$this->wp_upload_dir_url = wp_upload_dir(); //needed for image creation
		global $wpdb;
		
		// löschen der zuletzt eingefügten Einträge
		if( $this->settings['delete_before_input'] == '1' ) $this->delete_old_db_entries();	
		
		//Insert all posts
		foreach( $this->all_posts_data AS $postdata )  
		{	
			$pics_arr = $postdata['pics'];
			$postdata = $postdata['postdata'];
			$title_txt = substr( $postdata->title->asXML(), 7, -8); //cutoff <title></title> tags
			$post_txt = substr( $postdata->post->asXML(), 6, -7);	//cutoff <post></post> tags
		
			//create Category for the post	
			$term_id = $this->check_and_insert_category( $postdata->category ); 
			
			//check author, if nobody set, its be set to author 1
			$author_id = $this->check_and_insert_author( $postdata->author );			

			if( strlen($title_txt) < 3 || strlen($post_txt) < 3 ) 
			{			
				$this->write_log('Post oder post-title seemed to be empty.');
				return false;
			}
			
				//Create the WP_Posting					
				$post = array(
					'post_title'	=> $title_txt,
					'post_content'	=> $post_txt,
					'post_status'	=> 'publish',
					'post_author'	=> $postdata->author,
					'post_category'	=> array( $term_id ),
					'tags_input'	=> explode(',', $postdata->tags)
				);
				$post_id = wp_insert_post( $post );
				
				$this->deletescript['sql'][] = "DELETE FROM $wpdb->posts WHERE ID = $post_id;";
				$this->deletescript['sql'][] = "DELETE FROM ".$wpdb->postmeta." WHERE post_id = $post_id;";					
				
				$this->deletescript['sql'][] = "DELETE ".$wpdb->term_relationships." FROM ".$wpdb->term_relationships." INNER JOIN ".$wpdb->term_taxonomy." ON ".$wpdb->term_taxonomy.".term_taxonomy_id = ".$wpdb->term_relationships.".term_taxonomy_id INNER JOIN ".$wpdb->terms." ON ".$wpdb->terms.".term_id = ".$wpdb->term_taxonomy.".term_id  WHERE ".$wpdb->terms.".term_id = $term_id;";
				$this->deletescript['sql'][] = "DELETE FROM ".$wpdb->term_taxonomy." WHERE term_id = $term_id;";							
				$this->deletescript['sql'][] = "DELETE FROM ".$wpdb->terms." WHERE term_id = $term_id;";	
				
				// and now add all custumfields related data
				$this->add_all_post_meta_for( $post_id, $postdata->customfields );
				
				//now add the pictures to the post
				$this->add_photos_to_the_post( $post_id, $pics_arr );
				#print_ar( $postdata );
		}		
		
		// write down the delete-script	
		if($this->deletescript['sql'])
		{ 
			$file = substr(__FILE__,0,-15) . '/plugin_del_script';  //write the log in the same dir as this script
			$handle = fopen($file, "w+"); 							//immer eine leere Date beginnen
			fwrite($handle, "// **** ".date('Y-m-d H:m:s')."**** //\r\n");
			fwrite($handle, "\r\n[SQL]\r\n");
			fwrite($handle, implode($this->deletescript['sql'],"\r\n"));
		}
		else 
		{
			$this->write_log('keine Daten im deletescript Array, deletescript kann nicht geschrieben werden');	
		}
	}

	
	/* *************************************************************************************					
		add every picture in the overload array to the aktual post_id
	*/
	function add_photos_to_the_post( $post_id, $arr )
	{
		//delete all files in /gebrauchtwagen/
		//system('rm -r '.$this->csv2ngg_settings['mainfolder'].'/*');
		foreach($arr AS $file)
		{
			$basename = basename($file);
			$wp_filetype 	= wp_check_filetype($basename, null );
			$attachment = array(
			 'guid' => $this->wp_upload_dir_url . '/' . $basename, 
			 'post_mime_type' => $wp_filetype['type'],
			 'post_title' => preg_replace('/\.[^.]+$/', '', $basename),
			 'post_content' => '',
			 'post_status' => 'inherit'
			);
			$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
			// you must first include the image.php file for the function
			// wp_generate_attachment_metadata() to work -> happend one function earlier
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			if( wp_update_attachment_metadata( $attach_id, $attach_data ) === false) 
				$this->write_log('wp_update_attachment_metadata error @:' . $file);
			else
			{
				$this->write_log('Image attachment write succesfully');
			}
		}
	}
	
	/* *************************************************************************************					
	*/
	function add_all_post_meta_for( $post_id, $meta )
	{
		if( $this->settings['xml_meta'] == '1') //the customfields arr saved in XLM structure
		{
			echo $err = ' XML Customfields not working yet';
			$this->write_log($err);
			return false;
		}
		else	///the customfields are saved in a [property] : [value] stucture
		{
			$meta = explode( "\n", $meta);
			foreach( $meta AS $line)
			{
				$pair = explode(':' , $line);
				$prop	= trim($pair[0]);
				$val	= trim($pair[1]);
				if( strlen($prop) > 2 && strlen($val) > 2 )
					add_post_meta($post_id, $prop, $val );	
			}
		}
	}
	
	
	/* *************************************************************************************
		aus dem letzten Durchlauf wurden alle db-Eintragungen gespeichert
		und nun gelöscht
		// deletes all Entrys, the DB writes befor					
	*/
	function delete_old_db_entries()
	{
		$delscript =  substr(__FILE__,0,-15).'plugin_del_script';
		if( ! file_exists($delscript) ) return false;

		global $wpdb;
		$CleaningLines = file( $delscript );
		foreach($CleaningLines AS $line) 
		{
			#echo '->'.$line;
			$line = trim($line);
			if($line == '[SQL]') 	{ $part = 3; $this->write_log('Start deleting old SQL entrys'); continue;}		
			
			//Kill all SQL entrys from last import-progress
			if($part == 3)  $wpdb->query($line);
			$err = mysql_error();
			if($err != '') $this->write_log('MySQL-Err: '.$err); 
		}
	}	
	
	/* *************************************************************************************
		handles the input of the settings at pluginstart
		first: read all settings into array $this->settings
		second: if some settings are changed -> write to ini-file
	*/
	function settings( $flag )
	{		
		if($_POST['parent_category'] != ''){ //copy the selected  WP_Category and Author one step deeper, into the settings array
			$_POST['settings']['parent_category'] = $_POST['parent_category']; 
			if( stripos($_POST['author'] , 'none..') > -1 ) $_POST['author'] = 'none'; 
			$_POST['settings']['author'] = $_POST['author']; 
		}
		
		while($_POST['settings']) //set possibly new settings
		{		
			$this->set_settings(key($_POST['settings']),htmlentities(trim(current($_POST['settings']))));
			array_shift($_POST['settings']);
		}		
		$this->settings  =  $this->get_settings('all'); //all settings
		
		// set a new starpoint in logfile
		if($flag=='start') $this->settings['start_logging'] = 1; 
	}
	
	
	/* *************************************************************************************
		Reads the settings.ini and gives back all values or a requested one
	*/
	function get_settings($key)
	{		
		$settings_arr = array();		
		$file = substr(__FILE__,0,-15) . 'settings.ini';  
		$lines = file($file);	
		foreach($lines AS $row)
		{		
			$row = explode('=',$row);			
			$row[0] = (trim($row[0]));
			$row[1] = (trim($row[1]));
			if($row[0] != '' && $row[1] != '') $settings_arr[$row[0]] = $row[1]; //wenn eine valide Zeile vorliegt speichere sie				
		}
		if($key == 'all') return $settings_arr;
		else return $settings_arr[$key];		
	}

	
	/* *************************************************************************************
		Function to change only ONE Setting or create it
	*/
	function set_settings($key,$value)
	{	
		$value = stripslashes($value);
		$settings_new_arr = array();
		$isset = 0;
		$file = substr(__FILE__,0,-15) . 'settings.ini';  //write ini in the same dir as this script
		$settings_arr = file($file); 		
		while($settings_arr)
		{
			$row = array_shift($settings_arr);
			if('' != $row)
			{						
				$values = explode('=',$row);				
				if(count($values) == 2) 
				{
					$values[0] =  trim($values[0]);
					$values[1] =  trim($values[1]);
					if($values[0] == $key) { array_push($settings_new_arr, $key . ' = ' . $value); $isset = 1; }//change the delivered setting row
					else array_push($settings_new_arr,$values[0] . ' = ' . $values[1]);					
				}
			}			
		}
		if($isset != 1) array_push($settings_new_arr,trim($key) . ' = ' . $value); //nothing to change? -> set the setting for the first time
		$handle = fopen($file, "w");
		if(!fwrite($handle, implode("\r\n",$settings_new_arr))) 
		{
			$this->write_log("SetSettings: Kann in die Datei $file nicht schreiben");
			exit;
		}
		$this->write_log('implode("\r\n",$settings_new_arr)');
	}
	
	
	/* *************************************************************************************
		inserts a new line in the logfile
		writes directly into a file, it slowest the plugin down a litte bit, but in advantage it 
		reports the last message before an crash or buffer/memory overflow
	*/
	function write_log()
	{ 		
		$file = substr(__FILE__,0,-15) . 'TheImporter.log';  //write the log in the same dir as this script
		if($this->settings['start_logging'] == 1) 
		{
			if($this->settings['history_log'] == '0') $handle = fopen($file, "w");
			else $handle = fopen($file, "a+");
			$value = "\r\n\r\n".'***  starting new TheImporter run ***';
			$this->settings['start_logging'] = 0;
		}
		else
		{
			$handle = fopen($file, "a");		
			$value = date('Y-m-d h:m:s : ').$value;
		}		
		fwrite($handle, "\r\n".$value);
		fclose($handle);
	}	
	
}//class end


function TheImporter_admin_menu() {
    require_once ABSPATH . '/wp-admin/admin.php';
    $plugin = new TheImporterPlugin;
    add_management_page('edit.php', 'TheImporter', 9, __FILE__, array($plugin, 'plugin_page'));
}

//Add an hook to AdminPage
add_action('admin_menu', 'TheImporter_admin_menu');  

?>
