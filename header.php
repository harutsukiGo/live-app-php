<div id="header" class="header navbar navbar-inverse navbar-fixed-top">
    <!-- begin container -->
    <div class="container">
    <!-- begin navbar-header -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#header-navbar">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a href="https://www.ats-sport.com/index.php"><img id="logo" src="/images/logo/ats-sport/Specialiste-Chronometrage-sportif.jpg" alt="ATS-SPORT" style="width:8vh;margin-top:4px" /></a>
        </div>
        <!-- end navbar-header -->
        <!-- begin navbar-collapse -->
        <div class="collapse navbar-collapse" id="header-navbar">
            <ul class="nav navbar-nav navbar-right">
                <li><a href="..">ACCUEIL</a></li>
                <li><a href="../calendrier.php" data-click="scroll-to-target">CALENDRIER</a></li>
                <li><a href="../resultats.php" data-click="scroll-to-target" data-toggle="dropdown">RESULTATS <b class="caret"></b></a>
                    <ul class="dropdown-menu dropdown-menu-left animated fadeInDown">
                        <li><a href="../resultats.php">Résultats des courses</a></li>
                    </ul>
                </li>
                <li><a href="#organisateur" data-click="scroll-to-target" data-toggle="dropdown">ORGANISATEUR <b class="caret"></b></a>
                    <ul class="dropdown-menu dropdown-menu-left animated fadeInDown">
                        <li><a href="../admin/login_v2.php">Accéder à votre espace perso</a></li>
                        <li><a href="../devis.php">Demander un devis</a></li>
                    </ul>
                </li>
                <li><a href="../prestations.php" data-click="scroll-to-target">QUI SOMMES NOUS ?</a></li>
				<li><a href="https://www.pointcourse.com" style="color:#1DAAE2;" target="_blank">BOUTIQUE</a>
					<!-- <ul class="dropdown-menu dropdown-menu-left animated fadeInDown">
                        <li><a href="https://www.pointcourse.com" target="_blank" data-click="scroll-to-target"><img src="/images/shop/Specialiste-dossard-sportif.png" height="45"></a></li>
                    </ul> -->
				</li>
				<?php if(isset($_SESSION["log_log"]) && $_SESSION["user_no_compte"]==0) { ?>	
					<li class="dropdown navbar-user">
						<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown">
							<!-- <img src="assets/img/<?php echo $_SESSION['avatar']; ?>" alt="" width="20px"/>  /-->
							<span class="hidden-xs"><?php echo $_SESSION["log_log"]; ?></span> <b class="caret"></b></br>
							
							
						</a>
						<ul class="dropdown-menu animated fadeInLeft">
							<li class="arrow"></li>
							<!-- <li><a href="profile.php">Edit Profile</a></li>
							<li><a href="javascript:;"><span class="badge badge-danger pull-right">2</span> Inbox</a></li>
							<li><a href="javascript:;">Calendar</a></li>
							<li><a href="javascript:;">Setting</a></li>
							<li class="divider"></li> /-->
							<li><a href="../internaute/moncompte.php">Editer mon compte</a></li>
							<li><a href="../index.php?act=disconnect">Se déconnecter</a></li>
							

						</ul>
					</li>
				<?php 
				$slug = "&course=" . $_GET['course'];
				if (! empty($_SESSION['idEpreuve'])) 
						{ 
						if ($_SESSION['info_caddie'] > 0) $visible='visible'; else $visible='none'; ?>
					<li><a style="padding:0" href="../insc.php?id_epreuve=<?php echo $_SESSION['idEpreuve']; ?>&step=3&tp=<?php echo $_GET['tp'] . $slug; ?>"><i class="fa fa-shopping-cart fa-2x text-white m-t-20"></i><span id="info_caddie" style="display:<?php echo $visible; ?>" class="badge badge-danger ml-2"><?php echo $_SESSION['info_caddie']; ?></span></a></li>
				<?php } ?>
				<?php } elseif ( !empty($_SESSION["user_no_compte"]) && $_SESSION["user_no_compte"]==1) { ?>
					<li class="dropdown navbar-user">
						<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown">
							<!-- <img src="assets/img/<?php echo $_SESSION['avatar']; ?>" alt="" width="20px"/>  /-->
							<span class="hidden-xs">INVITE</span> <b class="caret"></b></br>
						</a>
						<ul class="dropdown-menu animated fadeInLeft">
							<li class="arrow"></li>
							<li><a href="../index.php?act=disconnect">Vider ma session</a></li>
						</ul>
					</li>
				<?php } else { ?>
					<li><a href="../internaute/connexion.php">CONNEXION</a></li>
			
				<?php } ?>	
            </ul>
        </div>
        <!-- end navbar-collapse -->
    </div>
    <!-- end container -->
</div>