<!DOCTYPE html>
<html>
	<head>
		<?php 
			require $_SERVER['DOCUMENT_ROOT']."/include/header.php"; 
			require $_SERVER['DOCUMENT_ROOT'].'/include/db_connect.php'; 
		?>
		<title>journuit - Anmeldung</title>
	</head>

	<body class="uk-height-viewport">
		<?php require $_SERVER['DOCUMENT_ROOT']."/include/navbar.php";?>
		<div class="uk-container uk-container-large">
			<div class="uk-flex uk-flex-column uk-flex-center uk-flex-middle">
				<div>
					<span id="journuit_big">
						<span id="white">jour</span><span id="black">nuit</span> <img data-src="http://landausflugsplaner.de/pictures/journuit-logo_big.png" alt="journuit Logo" uk-img>
					</span>
				</div>
				<div class="uk-margin-top uk-margin-bottom">
					<form method="POST">
					    <fieldset class="uk-fieldset">

					        <div class="uk-margin">
					            <div class="uk-inline">
						            <span class="uk-form-icon" uk-icon="icon: mail"></span>
						            <input name="email" class="uk-input" type="text" placeholder="Email-Adresse..." value="<?php if(isset($_POST['email'])){echo $_POST['email'];}?>">
						        </div>
					        </div>

					        <div class="uk-margin">
					            <div class="uk-inline">
						            <span class="uk-form-icon" uk-icon="icon: lock"></span>
						            <input name="passwort" class="uk-input" type="password" placeholder="Passwort...">
						        </div>
					        </div>

					    </fieldset>
					    <div class="uk-flex uk-flex-center uk-flex-middle">
					    	<button class="uk-button uk-button-default" name="register" value="true">Anmelden</button>
					    </div>
					</form>
				</div>
				<?php
					if(isset($_POST['register'])){
						try {

							$error = "";

						    $auth->login(htmlspecialchars($_POST['email']), htmlspecialchars($_POST['password']));
						}
						catch (\Delight\Auth\InvalidEmailException $e) {
						    $error = "Die Email-Adresse ist unbekannt.";
						}
						catch (\Delight\Auth\InvalidPasswordException $e) {
						    $error = "Falsches Passwort.";
						}
						catch (\Delight\Auth\TooManyRequestsException $e) {
						    $error = "Die maximale Anzahl an Anfragen wurde überschritten.";
						}

						echo "<script>window.location.replace('http://landausflugsplaner.de/index.php?login=success');</script>";
					}
				?>
			</div>
		</div>
	</body>
</html>