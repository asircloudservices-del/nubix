// Scripts para QoL (mejoras al sitio) y efectos visuales

// Muestra una animación de carga para las operaciones que tardan tiempo (como la instalación de WordPress)
document.addEventListener("DOMContentLoaded", function () {
  // Busca etiquetas que tengan la clase "spinner"
  const spinner = document.querySelector(".spinner");
  if (spinner) {
    // Crear la etiqueta "style" para añadir estilos CSS y hacer la animación
    const style = document.createElement("style");
    // Define los estilos aplicados:
    style.textContent = `
            @keyframes rotar {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spinner {
                display: inline-block;
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #2563eb;
                border-radius: 50%;
                animation: rotar 1s linear infinite;
                margin: 1rem 0;
            }
        `;
    // Añade la etiqueta
    document.head.appendChild(style);
  }

  // Durante la instalación de WordPress, recarga la página cada 10 segundos
  // Si encuentra que el estado es pendiente...
  const wordpress_pendiente = document.querySelector(
    '[data-wp-estado="pendiente"]',
  );
  if (wordpress_pendiente) {
    // Define un timeout de 10 segundos
    setTimeout(function () {
      // Recarga la página
      location.reload();
    }, 10000);
  }
});

// Mostrar alertas y errores como notificación
function notificar(tipo, mensaje) {
  // Crear un elemento de alerta dinámico
  const alerta = document.createElement("div");
  alerta.className = `alert alert-${tipo}`;
  alerta.textContent = mensaje;
  alerta.style.position = "fixed";
  alerta.style.top = "20px";
  alerta.style.right = "20px";
  alerta.style.zIndex = "1000";
  alerta.style.maxWidth = "400px";

  document.body.appendChild(alerta);

  // Auto-cerrar después de 5 segundos
  setTimeout(function () {
    alerta.remove();
  }, 5000);
}

// Se encarga de validar el formato del email
function validar_email(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

// Se encarga de validar el formato y longitud de la contraseña
function validar_contrasena(password) {
  if (password.length < 8) {
    return { ok: false, error: "Mínimo 8 caracteres" };
  }

  // Que la contraseña contenga al menos 1 caracter de cada tipo
  if (!/[a-z]/.test(password)) {
    return { ok: false, error: "Necesita minúsculas" };
  }
  if (!/[A-Z]/.test(password)) {
    return { ok: false, error: "Necesita mayúsculas" };
  }
  if (!/[0-9]/.test(password)) {
    return { ok: false, error: "Necesita números" };
  }

  return { ok: true };
}

// Implementación del modo oscuro:
function toggle_dark_mode() {
  document.documentElement.classList.toggle("dark");
  localStorage.setItem(
    "dark-mode",
    document.documentElement.classList.contains("dark"),
  );
}

document.addEventListener("DOMContentLoaded", function () {
  // Buscamos el botón de toggle en la navbar
  // Lo añadiremos en el HTML de dashboard, correo, etc.
  const toggleBtn = document.getElementById("dark-mode-toggle");

  if (toggleBtn) {
    // Actualizamos el icono según el estado actual al cargar
    toggleBtn.textContent = document.documentElement.classList.contains("dark")
      ? "☀️"
      : "🌙";

    toggleBtn.addEventListener("click", function () {
      // Alternamos la clase 'dark' en el elemento raíz
      // El CSS usará esta clase para cambiar los colores
      const isDark = document.documentElement.classList.toggle("dark");

      // Guardamos la preferencia en localStorage
      // para mantenerla entre visitas
      localStorage.setItem("dark-mode", isDark);

      // Actualizamos el icono del botón
      toggleBtn.textContent = isDark ? "☀️" : "🌙";
    });
  }
});

// Cargar preferencia de modo al iniciar
if (localStorage.getItem("dark-mode") === "true") {
  document.documentElement.classList.add("dark");
}
