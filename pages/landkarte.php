<?php
	require $_SERVER['DOCUMENT_ROOT'].'/include/db_connect.php'; 
	require $_SERVER['DOCUMENT_ROOT'].'/include/functions.php';

	if(isset($_GET['rtb'])){
		$rtbUrl = htmlspecialchars($_GET['rtb']);
		$rtbId = getRtbIdFromUrl($db, $rtbUrl);
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<?php 
			require $_SERVER['DOCUMENT_ROOT']."/include/header.php"; 
		?>
		<title>journuit - Landkarte</title>
	</head>

	<body class="uk-height-viewport">
		<?php require $_SERVER['DOCUMENT_ROOT']."/include/navbar.php";?>
		<div class="uk-container uk-container-large">
		<?php
			$selectStandorte = $db->prepare("SELECT DISTINCT standorte.id, standorte.name, standorte.lat, standorte.lon, standorte.bild_id, bilder.file_ext FROM standorte JOIN eintraege ON (eintraege.standort_id = standorte.id) LEFT JOIN bilder ON (standorte.bild_id = bilder.id) WHERE eintraege.reisetagebuch_id = ? ORDER BY eintraege.datum, eintraege.uhrzeit ASC");
			$selectStandorte->execute(array($rtbId));
			$standorte = $selectStandorte->fetchAll(\PDO::FETCH_ASSOC);
			if(!empty($standorte)){
				?>
				<div class="uk-margin-top uk-margin-bottom uk-text-center">
					<a class="uk-icon-link uk-margin-left" uk-icon="icon: arrow-left; ratio: 1.2" href="reisetagebuecher.php?rtb=<?=$rtbUrl;?>">Zurück zum Reisetagebuch</a>
				</div>

				<div id="landkarte">
					<!-- Hier wird die Google Map angezeigt -->
				</div>

				<table class="uk-table uk-table-hover uk-table-justify uk-table-divider">
					<thead>
						<tr>
							<th class="uk-text-center">Orte (<?=sizeof($standorte);?>)</th>
							<th class="uk-text-right">Datum</th>
						</tr>
					</thead>
					<tbody>

					<?php
					$standortCount = 1;
					foreach($standorte as $standort){
						$selectEintraege = $db->prepare("SELECT titel, datum, uhrzeit FROM eintraege WHERE standort_id = ?");
						$selectEintraege->execute(array($standort['id']));
						$eintraege = $selectEintraege->fetchAll(\PDO::FETCH_ASSOC);

						$dateString = '';
						$eintraegeString = '';
						$eintragCount = 1;
						foreach($eintraege as $eintrag){
							$formatiertesDatum = strftime("%e. %B %Y", strtotime($eintrag['datum']));
							$uhrzeit = substr_replace($eintrag['uhrzeit'], ':', 2, 0);
							$dateString .= $formatiertesDatum.' '.$uhrzeit.'<br/>';
							$eintraegeString .= $eintrag['titel'];
							if($eintragCount != sizeof($eintraege)){
								$eintraegeString .= ', ';
							}
							$eintragCount++;
						}
						?>
						<tr class="standortBox" id="standort<?=$standortCount;?>">
							<td>
							<span class="uk-text-bold uk-h3 uk-margin-right"><?=$standortCount;?> </span>
							<?=$standort['name'];?>	(<?=$eintraegeString;?>)
							</td>
							<td class="uk-text-right">
								<?=$dateString;?>
							</td>
						</tr>
						<?php
						$standortCount++;
					}
					?>
					</tbody>
				</table>
				<?php
			} else {
				?>
				<div class="uk-margin-medium-top uk-text-center">
					<div class="uk-alert-warning" uk-alert>
						<p>Dieses Reisetagebuch enthält bisher noch kein Ort.</p>
					</div>

					<div class="uk-margin-bottom">
						<a href="reisetagebuecher.php?rtb=<?=$rtbUrl;?>" class="uk-link-reset">Zurück zum Reisetagebuch</a>
					</div>
				</div>
				<?php
			}
		?>
		</div>
		<script>
			var eintraegeMap;
	    	var currentInfoWindow = null; 
	    	<?php 
	    	echo "var orte = ".json_encode($standorte).";"; 
	    	?>
	    	var markers = Array();

	    	// Initialisieren der Map beim Seitenaufruf
			$(function() {
				initMap();
			});

	    	//Diese Funktion wird automatisch im case : map durch das Laden der Google Maps API aufgerufen
		    function initMap() {
		    	//Erstellen der "bounds" Variabel die später dazu dient die Map zu zentrieren wo alle Marker liegen
		    	var bounds = new google.maps.LatLngBounds();
		    	//Erstellen der Mitarbeiter-Map in der passenden div
		        eintraegeMap = new google.maps.Map(document.getElementById('landkarte'), {
		        	mapTypeId: 'terrain'
		        });

				//Für jeden ort wird ein Marker mit den dort wohnenden Mitarbeiter erstellt. Die "bounds" Variabel wird um die Position des Markers erweitert
		        for (var i = 0; i < orte.length; i++) { 
		        	createdMarker = createMarker(orte[i]);
		        	markers.push(createdMarker);
	        		bounds.extend(createdMarker.position);
	        		// Marker numerieren
	        		createdMarker.setIcon('https://raw.githubusercontent.com/Concept211/Google-Maps-Markers/master/images/marker_grey'+(i+1)+'.png');
			    }

			    // Die Map wird dort zentriert wo die Marker liegen
			    eintraegeMap.fitBounds(bounds);

			    // Fix damit die Map nach dem "fitBounds" nicht zu nahr ist
			    var listener = google.maps.event.addListener(eintraegeMap, "idle", function() { 
					if (eintraegeMap.getZoom() > 16) eintraegeMap.setZoom(16); 
					google.maps.event.removeListener(listener); 
				});

			    // Linien zwischen den Standorten
	            var path = new google.maps.MVCArray();
	            var poly = new google.maps.Polyline({
	            	map: eintraegeMap, 
	            	strokeColor: '#000000',
	            	strokeOpacity: 1,
          			strokeWeight: 3
	            });
	 
	            for (var i = 0; i < orte.length; i++) {
	                if ((i + 1) < orte.length) {
	                    var src = new google.maps.LatLng(orte[i]['lat'], orte[i]['lon']);
	                    var des = new google.maps.LatLng(orte[i+1]['lat'], orte[i+1]['lon']);
	                    path.push(src);
	                    poly.setPath(path);
	                }
	            }
	            // Die letzte Linie hinzufügen
	            var src = new google.maps.LatLng(orte[orte.length - 1]['lat'], orte[orte.length - 1]['lon']);
	            path.push(src);
	            poly.setPath(path);
			}

			function createMarker(ort){
				var ortLat = ort['lat'];
	    		var ortLon = ort['lon'];
	    		var ortname = ort['name'];

	            var marker = new google.maps.Marker({
	                map     : eintraegeMap,
	                title: ortname,
	                position: new google.maps.LatLng(ortLat, ortLon)
	            });

	            marker.addListener('click', function() {
	            	//Schliessen von offenen infoWindows damit immer nur eins aufbleibt
		        	eintraegeMap.setCenter(this.getPosition());
					eintraegeMap.setZoom(18);
		    	});
		    	
		    	return marker;
			}

			$('.standortBox').on('click', function(){
				var markerId = this.id.replace("standort", "") - 1;
				var markerInstance = markers[markerId];
				eintraegeMap.setCenter(markerInstance.getPosition());
				eintraegeMap.setZoom(18);
			});
		</script>
	</body>
</html>