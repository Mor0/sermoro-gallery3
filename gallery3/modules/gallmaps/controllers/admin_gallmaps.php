<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2013 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class Admin_Gallmaps_Controller extends Admin_Controller {

  public function index() {
  print $this->_get_view();
  }

  public function hookker() {
	access::verify_csrf();

	$form = $this->_get_form();
	if ($form->validate()) {
		$kol_urls = intval($form->gallmaps->urls_number->value);
		$kod = $form->gallmaps->kodik->value;
		$pusk = $form->build_maps->zapusk->value;
		if ($kol_urls < 50) {
		    $kol_urls = 150;
		    message::error(t("Number of the URLs is too small"));
		}
		if (strlen($kod) > 500) {
		    $kod = '';
		    message::error(t("Code length is too big"));
		}
		module::set_var("gallmaps", "urls_number", $kol_urls);
		module::set_var("gallmaps", "kodik", $kod);
		if ($pusk) {
		    if ($status = $this->_build_maps()) {
			message::info($status);
		    }
		}
		message::success(t("Settings have been saved"));
		url::redirect("admin/gallmaps");
	}
	print $this->_get_view($form);
  }

  private function _get_view($form=null) {
	$vo = new Admin_View("admin.html");
	$vo->page_title = t("Manage HTML Gallery maps");
	$vo->content = new View("admin_gallmaps.html");
	$vo->content->form = empty($form) ? $this->_get_form() : $form;
	return $vo;
  }

  private function _get_form() {

	$form = new Forge("admin/gallmaps/hookker", "", "post", array("id" => "g-admin-form"));
	$group = $form->group("gallmaps")->label(t("Manage HTML maps parameters"));
	$group->input("urls_number")->label(t('Enter the number of the URL in map file:'))
	      ->value(module::get_var("gallmaps", "urls_number", "150"))
	      ->rules("valid_numeric|length[1,3]");
	$group->input("kodik")->label(t('Enter the standart Market code:'))
	      ->value(module::get_var("gallmaps", "kodik", ""));
	$group = $form->group("build_maps")->label(t("Build"));
	$group->checkbox("zapusk")->label(t("Build maps now"))
	      ->value(module::get_var("gallmaps", "zapusk", false));
//		->checked(false);

	$form->submit("submit")->value(t("Save"));
	return $form;
  }

  private function _build_maps() {

	$roo = $_SERVER['DOCUMENT_ROOT'];
	if (!is_writable($roo)) {
		message::error(t("$roo is not writable!"));
		return t("Cancelled maps building");
	}
	$urls_number  = module::get_var("gallmaps", "urls_number");
	$added_string = module::get_var("gallmaps", "kodik");
	$added_string = htmlspecialchars_decode($added_string);
	$protocol = Kohana::config('core.site_protocol');
	$protocol = (empty($protocol)) ? "http://" : $protocol;
	$dirname = array_shift(explode("/admin/gallmaps/hookker", $_SERVER['PHP_SELF'], 2));

	$realdirname = array_shift(explode("/index.php", $dirname, 2));
	$dirname = rtrim($dirname, '/');
	$realdirname = rtrim($realdirname, '/');
	$path = $protocol.$_SERVER['SERVER_NAME'].$dirname.'/';
	$realpath = $protocol.$_SERVER['SERVER_NAME'].$realdirname.'/';
	$locations = '';
	$lines = array();
	$lines = '';
	$lines = $this->_add_to_maps("album", $path);
	$locations = $this->_add_to_maps("photo", $path);
	$lines = array_splice($lines, count($lines), 0, $locations);
	$locations = '';
	$locations = $this->_add_to_maps("movie", $path);
	$lines = array_splice($lines, count($lines), 0, $locations);

	$ckoko = count($lines);
	$galmap = array();
	$ii = 1;
	$map = '';
	foreach ($lines as $line_number => $line) {
	  $map .= $line.'<br />';

	  if(($line_number == $ckoko-1)||($line_number >= $urls_number*$ii)){
		$galmap[$ii] = <<< EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru" dir="ltr">
<head>
<title>Photo Gallery HTML map 0{$ii}</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
</head>
<body>
$map
$added_string
</body>
</html>
EOT
		$temp = '/gallery3_map0'.$ii.'.php';
		$url = $roo . $temp;
		if (!$file_pointer = fopen($url,'wb')) {
			message::error(t("Unable to create map file. Map could not be saved."));
			return t("Cancelled maps building");
		}
		if (fwrite($file_pointer, $galmap[$ii]) === FALSE) {
			message::error(t("Unable to write to map file. Map could not be saved."));
			return t("Cancelled maps building");
		}
		if (!fclose($file_pointer)) {
			message::error(t("Unable to close map file."));
			return t("Cancelled maps building");
		}       
		$ii++;
		$map = ''; 
	  }
	}
	message::success(t("Maps has been saved"));
	return;
  }

  private function _add_to_maps($type, $path) {
	$locations = array();
	$i = 0;
	foreach (db::build()
	    ->select("relative_url_cache", "title")
	    ->from("items")
	    ->where("type", "=", "$type")
	    ->where("view_1", "=", 1)
	    ->execute() as $row) {
		$single_url = htmlspecialchars($path . htmlentities($row->relative_url_cache, ENT_QUOTES, "UTF-8"), ENT_QUOTES);
		$titul = $row->title;
		$locations[$i] = "<a href=\"$single_url\">$titul</a>"
		$i++;
	}
	return $locations;
  }

}   // End Admin_Gallmaps_Controller
