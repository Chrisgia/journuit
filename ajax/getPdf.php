<?php
	require $_SERVER['DOCUMENT_ROOT'].'/include/db_connect.php'; 
	require $_SERVER['DOCUMENT_ROOT'].'/include/functions.php';

	// FPDF-Klasse einbinden
	require_once('../vendor/fpdf/fpdf.php');

	if(isset($_POST['rtb'])){

		$rtbUrl = htmlspecialchars($_POST['rtb']);
		$rtbId = getRtbIdFromUrl($db, $rtbUrl);

		$mask = '../files/'.$rtbUrl.'/*.pdf';
		array_map('unlink', glob($mask));

		// Reisetagebuch auswählen
		$selectReisetagebuch = $db->prepare("SELECT reisetagebuecher.id, users.username, reisetagebuecher.users_id, titel, url, beschreibung, public, erstellt_am, bild_id, bilder.file_ext FROM reisetagebuecher LEFT JOIN bilder ON (reisetagebuecher.bild_id = bilder.id) JOIN users ON (users_id = users.id) WHERE reisetagebuecher.id = ?");
		$selectReisetagebuch->execute(array($rtbId));
		$reisetagebuch = $selectReisetagebuch->fetchAll(\PDO::FETCH_ASSOC);

		if($userId == $reisetagebuch[0]['users_id'] || ($userId != $reisetagebuch[0]['users_id'] && $reisetagebuch[0]['public'] == 1)){

			$rtbCreator = $reisetagebuch[0]['username'];
			$rtbTitel = iconv("UTF-8", "Windows-1252//TRANSLIT", $reisetagebuch[0]['titel']);
			$rtbBeschreibung = iconv("UTF-8", "Windows-1252//TRANSLIT", $reisetagebuch[0]['beschreibung']);

			// Anzahl an Einträgen
			$selectAnzahlEintraege = $db->prepare("SELECT COUNT(id) AS anzahl FROM eintraege WHERE reisetagebuch_id = ? AND entwurf = 0");
			$selectAnzahlEintraege->execute(array($rtbId));
			$anzahlEintraege = $selectAnzahlEintraege->fetchAll(\PDO::FETCH_ASSOC);

			$selectDates = $db->prepare("SELECT DISTINCT datum FROM eintraege WHERE reisetagebuch_id = ? AND entwurf = 0 ORDER BY datum ASC");
			$selectDates->execute(array($rtbId));
			$dates = $selectDates->fetchAll(\PDO::FETCH_ASSOC);
			
			class PDF extends FPDF {
				protected $col = 0; // Current column
				protected $y0;      // Ordinate of column start

				function Header() {
					//journuit Logo auf jeder Seite
					$this->Image('../pictures/pdf-background.png', 0, 0, $this->GetPageWidth(), $this->GetPageHeight());
					$this->Image('../pictures/journuit-logo_big.png', $this->GetPageWidth() - 20, 3, -300, -300, 'png', 'https://journuit.euresa-reisen.de');
				    // Save ordinate
				    $this->y0 = $this->GetY();
				}

				function SetCol($col) {
				    // Set position at a given column
				    $this->col = $col;
				    $x = 10+$col*65;
				    $this->SetLeftMargin($x);
				    $this->SetX($x);
				}

				function AcceptPageBreak() {
				    // Method accepting or not automatic page break
				    if($this->col<2) {
				        // Go to next column
				        $this->SetCol($this->col+1);
				        // Set ordinate to top
				        $this->SetY($this->y0);
				        // Keep on page
				        return false;
				    } else {
				        // Go back to first column
				        $this->SetCol(0);
				        // Page break
				        return true;
				    }
				}

				function printEintrag($uhrzeit, $titel, $standort, $text, $bilder) {
				    $this->SetFont('Times', 'B', 12);
				    $this->MultiCell(60, 5, iconv("UTF-8", "Windows-1252//TRANSLIT", $uhrzeit));
				    $this->SetFont('', 'BI');
				    $this->MultiCell(60, 5, iconv("UTF-8", "Windows-1252//TRANSLIT", $titel));
				    $this->SetFont('', 'B');
				    $this->MultiCell(60, 5, iconv("UTF-8", "Windows-1252//TRANSLIT", $standort));
				    $this->Ln(2);
				    $this->SetFont('', '');
				    $this->MultiCell(60, 5, iconv("UTF-8", "Windows-1252//TRANSLIT", $text));
				    $this->Ln(2);
				    foreach($bilder as $bild){
						$this->Image('../users/'.$bild, null, null, -400, -400, '', '../../users/'.$bild);
						$this->Ln(5);
					}
					$this->SetLineWidth(0.2);
				    $this->Line($this->GetX(), $this->GetY() + 1, $this->GetX() + 4, $this->GetY() + 1);
				    $this->Ln();
				}

				function printDatum($eintragDatum) {
				    $this->SetFont('Times','B',14);
				    $this->MultiCell(60, 5, iconv("UTF-8", "Windows-1252//TRANSLIT", $eintragDatum));
				    $this->SetLineWidth(0.7);
				    $this->Line($this->GetX(), $this->GetY() + 1, $this->GetX() + $this->GetStringWidth($eintragDatum) + 2, $this->GetY() + 1);
				    $this->Ln(3);
				}
			}

			$reisetagebuchPdf = new PDF("P", "mm", "A4"); // L=Querformat(Landscape), P=Hochformat(Portrait)
			$reisetagebuchPdf->SetAuthor($fullname);
			$reisetagebuchPdf->SetCreator('journuit - FPDF');
			$reisetagebuchPdf->SetTitle($rtbTitel);
			$reisetagebuchPdf->SetSubject($rtbBeschreibung);
			$reisetagebuchPdf->AliasNbPages();

			// Linien Weiß
			$reisetagebuchPdf->SetDrawColor(0);
			// Seite erzeugen (sozusagen: starten)
			$reisetagebuchPdf->AddPage();

			//Überschrift
			$reisetagebuchPdf->SetFont('Helvetica');
			$reisetagebuchPdf->SetFontSize(26);
			$reisetagebuchPdf->Text(($reisetagebuchPdf->GetPageWidth() - $reisetagebuchPdf->GetStringWidth($rtbTitel)) / 2, 15, $rtbTitel);
			$reisetagebuchPdf->SetFontSize(14);
			$reisetagebuchPdf->Text(($reisetagebuchPdf->GetPageWidth() / 2) + $reisetagebuchPdf->GetStringWidth($rtbTitel), 15, ', von '.$rtbCreator);

			if(!empty($reisetagebuch[0]['bild_id'])){
				$reisetagebuchPdf->Image('../users/'.$reisetagebuch[0]['username'].'/'.$reisetagebuch[0]['bild_id'].'.'.$reisetagebuch[0]['file_ext'], 0, 30, null, null, $reisetagebuch[0]['file_ext'], '../../users/'.$reisetagebuch[0]['username'].'/'.$reisetagebuch[0]['bild_id'].'.'.$reisetagebuch[0]['file_ext']);
			} else {
				$reisetagebuchPdf->Image('../pictures/no-picture.jpg', 25, 30);
			}

			$anzahlEintraege = $anzahlEintraege[0]['anzahl'];
			if($anzahlEintraege == 1){
				$anzahlEintraegeText = $anzahlEintraege.' Eintrag';
			} else {
				$anzahlEintraegeText = $anzahlEintraege.' '.iconv("UTF-8", "Windows-1252//TRANSLIT", 'Einträge');
			}

			$anzahlReisetage = sizeof($dates);
			if($anzahlReisetage == 1){
				$anzahlReisetageText = $anzahlReisetage.' Reisetag';
			} else {
				$anzahlReisetageText = $anzahlReisetage.' Reisetage';
			}

			$reisetagebuchPdf->SetY(190);

			$reisetagebuchPdf->SetFontSize(13);
			$reisetagebuchPdf->SetFont('', '');
			$reisetagebuchPdf->MultiCell($reisetagebuchPdf->GetPageWidth() - 30, 10, iconv("UTF-8", "Windows-1252//TRANSLIT", $reisetagebuch[0]['beschreibung']), 0, 1);

			$reisetagebuchPdf->SetFontSize(12);
			$reisetagebuchPdf->SetFont('', 'I');
			$reisetagebuchPdf->Cell(0, 10, $anzahlEintraegeText.', erstellt am '.getMySqlDate($reisetagebuch[0]['erstellt_am']).'.', 0, 1);
			$reisetagebuchPdf->Cell(0, 10, getMySqlDate($dates[0]['datum']).' - '.getMySqlDate($dates[sizeof($dates) - 1]['datum']).' ('.$anzahlReisetageText.')', 0, 1);
			$reisetagebuchPdf->Ln(10);
			$reisetagebuchPdf->Image('../files/'.$reisetagebuch[0]['url'].'/linkQrCode.png', ($reisetagebuchPdf->GetPageWidth() - 43) / 2, $reisetagebuchPdf->GetY(), null, null, 'png', 'https://journuit.euresa-reisen.de/pages/reisetagebuecher.php?rtb='.$reisetagebuch[0]['url']);

			if($anzahlEintraege > 0){
				$reisetagebuchPdf->AddPage();

				foreach($dates as $datum){
					$reisetagebuchPdf->SetFont('', 'B', 14);
					$formatiertesDatum = strftime("%e. %B %Y", strtotime($datum['datum']));
					$reisetagebuchPdf->printDatum($formatiertesDatum);
					// Einträge des Datums auwählen
					$selectEintraege = $db->prepare("SELECT id, titel, text, uhrzeit, standort_id, zusammenfassung, public FROM eintraege WHERE reisetagebuch_id = ? AND entwurf = 0 AND datum = ? ORDER BY uhrzeit ASC");
					$selectEintraege->execute(array($rtbId, $datum['datum']));
					$eintraege = $selectEintraege->fetchAll(\PDO::FETCH_ASSOC);

					$count = 1;
					$reisetagebuchPdf->SetFont('', '', 12);
					foreach($eintraege as $eintrag){
						$eintragsBilder = array();

						$bilder = array();
						// Eintragsbilder auswählen			
						$selectBilder = $db->prepare("SELECT bilder.id, bilder.file_ext FROM bilder JOIN eintraege_bilder ON (bilder.id = eintraege_bilder.bild_id) WHERE eintraege_bilder.eintrag_id = ?");
						$selectBilder->execute(array($eintrag['id']));
						$bilder = $selectBilder->fetchAll(\PDO::FETCH_ASSOC);
						if($eintrag['zusammenfassung'] != 1){
							if($eintrag['zusammenfassung'] != 1){
								if($eintrag['uhrzeit'] > 2400){
									$uhrzeit = substr_replace(str_pad($eintrag['uhrzeit'] - 2400, 4, '0', STR_PAD_LEFT), ':', 2, 0);
									$eintragUhrzeit =  '24:00 +'.$uhrzeit."\r\n";
								} else {
									$uhrzeit = substr_replace($eintrag['uhrzeit'], ':', 2, 0);
									$eintragUhrzeit =  $uhrzeit."\r\n";
								}
							}
							
						}

						$eintragTitel =  $eintrag['titel'];
						// Eintragsort auswählen
						$selectStandort = $db->prepare("SELECT name FROM standorte WHERE id = ?");
						$selectStandort->execute(array($eintrag['standort_id']));
						$standort = $selectStandort->fetchAll(\PDO::FETCH_ASSOC);

						if(!empty($standort)){
							$standortName = $standort[0]['name'];
						} else {
							$standortName = '?';
						}

						$eintragText =  $eintrag['text'];

						if(!empty($bilder)){
							foreach($bilder as $bild){
								array_push($eintragsBilder, $reisetagebuch[0]['username'].'/'.$bild['id'].'.'.$bild['file_ext']);
							}
						}

						$reisetagebuchPdf->printEintrag($eintragUhrzeit, $eintragTitel, $standortName, $eintragText, $eintragsBilder);
						$count++;
					}
				}
			}

			$reisetagebuchPdf->Output('../files/'.$rtbUrl.'/'.$rtbCreator.'_'.normalize($reisetagebuch[0]['titel']).'.pdf', 'F');
		}
	} else {
		echo 'Direkter Aufruf geblockt !';
	}
?>