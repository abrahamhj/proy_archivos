<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
  echo "Ya has iniciado sesión. Redirigiendo...";
  if ($_SESSION['rol'] === 'admin') {
    header("Location: panel_admin.php");
  } else {
    header("Location: panel_usuario.php");
  }
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <title>SALVEMOS LOS ARCHIVOS - BOLIVIA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilos/index.css" />
  <link rel="stylesheet" href="estilos/menu.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <header>
    <div class="navbar">
      <!-- Logo -->
      <div class="logo">
        <a href="index.php">
          <img src="img/logo.jpg" alt="Logo Salvemos los Archivos Bolivia">
        </a>
      </div>

      <!-- Menú principal -->
      <ul class="menu" id="menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
        <li><a href="#quienes"><i class="fa-solid fa-users"></i> ¿Quiénes Somos?</a></li>

        <li class="dropdown">
          <a href="#"><i class="fa-solid fa-folder-open"></i> Actividades</a>
          <ul class="submenu">
            <li><a href="#investigaciones"><i class="fa-solid fa-magnifying-glass"></i> Investigaciones</a></li>
            <li><a href="#fotos"><i class="fa-solid fa-image"></i> Fotos</a></li>
            <li><a href="#videos"><i class="fa-solid fa-video"></i> Videos</a></li>
          </ul>
        </li>

        <li><a href="#talleres"><i class="fa-solid fa-chalkboard-user"></i> Talleres</a></li>
        <li><a href="#contacto"><i class="fa-solid fa-envelope"></i> Contacto</a></li>
      </ul>

      <!-- Acciones (incluye BÚSQUEDA) -->
      <div class="actions">
        <a class="btn-login" href="login.php">
          <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
        </a>

        <div class="search-wrap" id="searchWrap">
          <button class="search-btn" id="searchToggle" aria-controls="searchBox" aria-expanded="false" title="Buscar">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
          <div class="search-box" id="searchBox">
            <input class="search-input" type="text" placeholder="Buscar..." aria-label="Buscar">
            <button class="search-submit">Buscar</button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <section class="hero" aria-label="Portada">
    <div class="slide active" style="background-image:url('img/slider1.jpg')"></div>
    <div class="slide" style="background-image:url('img/slider2.jpg')"></div>
    <div class="slide" style="background-image:url('img/slider3.jpg')"></div>
    <h1>SALVEMOS LOS ARCHIVOS — BOLIVIA</h1>
  </section>

  <main>
    <!-- ¿QUIÉNES SOMOS? -->
    <section id="quienes" aria-labelledby="ttl-quienes">
      <div class="about">
        <div>
          <div class="circle" aria-hidden="true">
            <div class="circle-item active" style="background-image:url('img/equipo1.jpg')"></div>
            <div class="circle-item" style="background-image:url('img/equipo2.jpg')"></div>
            <div class="circle-item" style="background-image:url('img/equipo3.jpg')"></div>
          </div>
        </div>

        <div>
          <article class="about-card">
            <h2 id="ttl-quienes">¿Quiénes Somos?</h2>
            <p>
              Somos un colectivo dedicado a la preservación y valoración de los archivos en Bolivia.
              Buscamos proteger y difundir la memoria documental del país para fortalecer la identidad
              cultural y el acceso al conocimiento.
            </p>
          </article>

          <div class="about-grid" role="list">
            <div class="info-card" role="listitem">
              <h3>Misión</h3>
              <p>Preservar, proteger y difundir la memoria documental de Bolivia, sensibilizando a la sociedad sobre su valor.</p>
            </div>
            <div class="info-card" role="listitem">
              <h3>Visión</h3>
              <p>Ser referentes en la protección de archivos históricos y promover un acceso abierto y responsable.</p>
            </div>
            <div class="info-card" role="listitem">
              <h3>Objetivos</h3>
              <ul>
                <li>Rescate y preservación documental.</li>
                <li>Sensibilización social.</li>
                <li>Proyectos de digitalización.</li>
                <li>Redes de apoyo y cooperación.</li>
                <li>Acceso libre, equitativo y responsable.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ACTIVIDADES -->
    <section id="actividades" aria-labelledby="ttl-actividades">
      <h2 class="section-title" id="ttl-actividades">Actividades Recientes</h2>
      <div class="activities">
        <?php
        include "conexion.php";
        $sql = "SELECT id_archivo, nombre, tipo FROM archivos ORDER BY id_archivo DESC LIMIT 3";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
          $isLogged = isset($_SESSION['id_usuario']);
          while ($row = $result->fetch_assoc()) {
            $id     = (int)$row['id_archivo'];
            $nombre = htmlspecialchars($row['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
            $tipo   = htmlspecialchars($row['tipo'] ?? '', ENT_QUOTES, 'UTF-8');

            // Enlace según sesión/tipo
            if (!$isLogged) {
              $link = "login.php";
            } else {
              if ($tipo === "video") {
                $link = "ver_video.php?id=" . $id;
              } else {
                $link = "uploads/" . rawurlencode($nombre);
              }
            }

            // Miniatura (por simplicidad usamos el mismo archivo; si tienes thumbs, cámbialo)
            $thumb = "uploads/" . rawurlencode($nombre);
            $alt = $nombre !== '' ? $nombre : 'Archivo reciente';
            echo '<article class="activity">';
            echo   '<a href="' . $link . '" target="_blank" rel="noopener">';
            echo     '<img src="' . $thumb . '" alt="' . $alt . '" />';
            echo     '<div class="txt">' . $alt . '</div>';
            echo   '</a>';
            echo '</article>';
          }
        } else {
          echo '<p>No hay actividades recientes todavía.</p>';
        }
        ?>
      </div>
    </section>
  </main>

  <footer>
    <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia · Todos los derechos reservados</p>
  </footer>

  <script>
    // Hamburguesa (móvil)
    const burger = document.getElementById('burger');
    const menu = document.getElementById('menu');
    burger.addEventListener('click', () => menu.classList.toggle('show'));

    // Búsqueda (toggle + cerrar al hacer click fuera)
    const searchWrap = document.getElementById('searchWrap');
    const searchToggle = document.getElementById('searchToggle');

    searchToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const opened = searchWrap.classList.toggle('open');
      searchToggle.setAttribute('aria-expanded', String(opened));
      if (opened) searchWrap.querySelector('.search-input').focus();
    });

    document.addEventListener('click', (e) => {
      if (!searchWrap.contains(e.target)) {
        searchWrap.classList.remove('open');
        searchToggle.setAttribute('aria-expanded', 'false');
      }
    });
  </script>



</body>

</html>