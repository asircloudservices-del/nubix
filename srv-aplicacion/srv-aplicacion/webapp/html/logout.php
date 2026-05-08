<?php
// Se encarga de cerrar la sesión del usuario

session_start();

// Eliminamos las variables de sesión
unset($_SESSION["usuario_id"]);
unset($_SESSION["nombre"]);

// Eliminamos la sesión
session_destroy();

// Redirigimos al login y terminamos la ejecución
header("Location: /index.php");
exit();
