<?php
require $_SERVER['DOCUMENT_ROOT'].'/include/db_connect.php'; 
require $_SERVER['DOCUMENT_ROOT'].'/include/functions.php';

if(isset($_FILES['files'])){
	$error = false;
	// TODO : Filesize und Type checken
	// Man nimmt den Benutzernamen des eingeloggten Benutzers um später den Ordner zu erstellen
	$selectUsername = $db->prepare("SELECT username FROM users WHERE id = ?");
	$selectUsername->execute(array($userId));
	$username = $selectUsername->fetchAll(\PDO::FETCH_ASSOC);
	$username = $username[0]['username'];

	// Dateiinformationen
    $file_name = $_FILES['files']['name'][0];
    $file_size = $_FILES['files']['size'][0];
    $file_tmp = $_FILES['files']['tmp_name'][0];
    $file_ext = substr($file_name, strpos($file_name, ".") + 1);
    $pictureId = uniqueDbId($db, 'bilder', 'id');
    $filename = "tmp_".$pictureId.".".$file_ext;

	$mask = "../users/$username/tmp_*.*";

	// Wenn verschiedene Bilder hochgeladen werden, gibt es eine Temporäre Datei pro Bild
	if(isset($_POST['multiple']) && $_POST['multiple'] == true){
		$fieldToFill = 1;
		$tmpFiles = array();

		for($i = 1; $i <= 3; $i++){
			$mask = "../users/$username/tmp".$i."_*.*";
			if(!empty(glob($mask))){
				$foundFile = glob($mask)[0];
				array_push($tmpFiles, $foundFile);
			}
		}

		if(!empty($tmpFiles)){
			$fieldToFillPos = strlen("../users/$username/tmp");
			$fieldToFill = substr($tmpFiles[sizeOf($tmpFiles) - 1], $fieldToFillPos, 1) + 1;
			if($fieldToFill == 4){
				$error = true;
			}
		}

		$mask = "../users/$username/tmp".$fieldToFill."_*.*";
		$filename = "tmp".$fieldToFill."_".$pictureId.".".$file_ext;
	} else {
		$fieldToFill = '';
	}

	if(!$error){
		$fullPath = "../users/$username/".$filename;

		// Alle Temporären Bilder löschen
		array_map('unlink', glob($mask));

	    // Das Bild wird mittels TinyPNG API kompressiert
	    $source = \Tinify\fromFile($file_tmp);
	    // Man behält das Erstellungsdatum und die GPS-Daten
		$sourcePreservedEXIF = $source->preserve("creation", "location");
		
		// Wenn keine Maßangaben übergeben wurden, normales Titelbild Format
		if(isset($_POST['width'], $_POST['height'])){
			$width = (int)$_POST['width'];
			$height = (int)$_POST['height'];
		} else {
			$width = 820;
			$height = 462;
		}

		// Bildgröße anpassen
		$resizedPicture = $sourcePreservedEXIF->resize(array(
		    "method" => "fit",
		    "width" => $width,
		    "height" => $height
		));

		// Die Datei wird in das Benutzerverzeichnis verschoben
		$resizedPicture->toFile($fullPath);

		$infos = array(
			'status' => 'OK',
			'pictureId' => $pictureId, 
			'file_ext' => $file_ext,
			'fieldToFill' => $fieldToFill
		);
	} else {
		$infos = array(
			'status' => 'ERROR'
		);
	}

	echo json_encode($infos);
}
?>