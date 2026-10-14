/* ============================================================
   JAVASCRIPT DE LA BIBLIOTECA DIGITAL
   Un solo archivo para todo el sitio, sin librerías externas.

   Todo lo que hace este archivo es una MEJORA visual: si el
   archivo no carga o algo falla, las páginas PHP siguen
   funcionando igual (formularios, ventas, compras, descargas).

   Organización:
     Ayudas (funciones cortas que usan varios componentes)
     initNavbar()         menú plegable e indicador animado
     initReveal()         aparición de elementos al entrar en pantalla
     initImages()         portadas: efecto de carga y reemplazo
     initCarousel()       carrusel 3D de libros destacados
     initTilt()           inclinación 3D de tarjetas y portada
     initAuthForms()      login y registro: ver contraseña y fortaleza
     initForms()          campo activo, estado de envío y filtro
     initToasts()         mensajes flotantes
     initConfirmations()  diálogo de confirmación
     initCashRegister()   caja: fila nueva, total y campo que se sacude
     initTables()         tablas: fila actualizada y entrada de filas
     init()               arranca todo

   Cada componente empieza comprobando si en la página está lo
   que necesita; si no está, termina enseguida sin hacer nada.
   ============================================================ */
(function () {
    "use strict";

    // ------------------------------------------------------------
    // AYUDAS
    // ------------------------------------------------------------

    // ¿La persona pidió menos movimiento en su equipo? Si es así se
    // apagan el cambio automático del carrusel y las inclinaciones.
    var consultaMovimiento = window.matchMedia("(prefers-reduced-motion: reduce)");
    function menosMovimiento() {
        return consultaMovimiento.matches;
    }

    // ¿El equipo tiene un cursor preciso (ratón)? En pantallas
    // táctiles no hay "pasar el cursor", así que no se inclina nada.
    function tieneCursor() {
        return window.matchMedia("(hover: hover) and (pointer: fine)").matches;
    }

    // Atajos para buscar elementos.
    function uno(selector, dentro) {
        return (dentro || document).querySelector(selector);
    }
    function todos(selector, dentro) {
        return Array.prototype.slice.call((dentro || document).querySelectorAll(selector));
    }

    // Datos que se guardan mientras dura la pestaña (para comparar
    // el ticket de la caja o recordar qué fila se modificó).
    function guardar(clave, valor) {
        try {
            sessionStorage.setItem(clave, JSON.stringify(valor));
        } catch (error) { /* sin almacenamiento: no pasa nada */ }
    }
    function leer(clave) {
        try {
            return JSON.parse(sessionStorage.getItem(clave));
        } catch (error) {
            return null;
        }
    }

    // Avisa una sola vez cuando un elemento entra en la pantalla.
    // Todo el sitio comparte UN solo observador: cada elemento
    // anotado tiene su acción, que recibe el elemento y su orden
    // entre los que entraron al mismo tiempo.
    var observador = null;
    var acciones = new Map();
    function alEntrarEnPantalla(elemento, accion) {
        // Navegador sin IntersectionObserver: se hace de inmediato.
        if (!("IntersectionObserver" in window)) {
            accion(elemento, 0);
            return;
        }
        if (!observador) {
            observador = new IntersectionObserver(function (entradas) {
                var orden = 0;
                entradas.forEach(function (entrada) {
                    var visto = entrada.target;
                    if (entrada.isIntersecting && acciones.has(visto)) {
                        var hacer = acciones.get(visto);
                        // Una sola vez: se deja de observar.
                        observador.unobserve(visto);
                        acciones.delete(visto);
                        hacer(visto, orden++);
                    }
                });
            }, { threshold: 0.08, rootMargin: "0px 0px -30px 0px" });
        }
        acciones.set(elemento, accion);
        observador.observe(elemento);
    }

    // Hace correr un número desde un valor hasta otro: arranca
    // rápido y frena al llegar. "formato" convierte el número en
    // el texto que se muestra. Lo usa el total de la caja.
    function animarNumero(elemento, desde, hasta, duracion, formato) {
        var inicio = null;
        function paso(ahora) {
            if (inicio === null) {
                inicio = ahora;
            }
            var avance = Math.min((ahora - inicio) / duracion, 1);
            var suave = 1 - Math.pow(1 - avance, 3);
            elemento.textContent = formato(desde + (hasta - desde) * suave);
            if (avance < 1) {
                requestAnimationFrame(paso);
            }
        }
        requestAnimationFrame(paso);
    }

    // Objeto público: guarda el estado que consultan las pruebas y
    // la función para mostrar un mensaje flotante.
    var Biblioteca = { menosMovimiento: menosMovimiento };
    window.Biblioteca = Biblioteca;

    // ------------------------------------------------------------
    // MENÚ
    // En pantallas chicas el menú se pliega detrás de un botón.
    // Además, una línea de color se desliza hasta el enlace sobre
    // el que está el cursor y vuelve al de la página actual.
    // ------------------------------------------------------------
    function initNavbar() {
        var cabecera = uno("header");
        var nav = uno("#menu-principal");
        if (!cabecera || !nav) {
            return;
        }
        var boton = uno(".menu-boton", cabecera);
        var activo = uno("a.activo", nav);

        // --- Indicador animado de la página activa ---
        var indicador = document.createElement("span");
        indicador.className = "nav-indicador";
        indicador.setAttribute("aria-hidden", "true");
        nav.appendChild(indicador);
        nav.classList.add("con-indicador");

        function mover(enlace) {
            if (!enlace) {
                indicador.style.opacity = "0";
                return;
            }
            // La línea ocupa el ancho del texto: se descuenta el relleno
            // lateral del enlace (que cambia según el ancho de la pantalla).
            var relleno = parseFloat(window.getComputedStyle(enlace).paddingLeft) || 0;
            indicador.style.width = Math.max(enlace.offsetWidth - relleno * 2, 8) + "px";
            indicador.style.transform = "translate(" + (enlace.offsetLeft + relleno) + "px, " + (enlace.offsetTop + enlace.offsetHeight - 4) + "px)";
            indicador.style.opacity = "1";
        }
        // Un solo aviso en el menú sirve para todos sus enlaces.
        function seguir(evento) {
            var enlace = evento.target.closest("a");
            if (enlace) {
                mover(enlace);
            }
        }
        function volver() {
            mover(activo);
        }
        nav.addEventListener("mouseover", seguir);
        nav.addEventListener("focusin", seguir);
        nav.addEventListener("mouseleave", volver);
        nav.addEventListener("focusout", volver);
        volver();

        // --- Botón del menú (pantallas chicas) ---
        function estaAbierto() {
            return cabecera.classList.contains("abierto");
        }
        function abrirMenu(abrir, devolverFoco) {
            cabecera.classList.toggle("abierto", abrir);
            boton.setAttribute("aria-expanded", abrir ? "true" : "false");
            boton.setAttribute("aria-label", abrir ? "Cerrar el menú" : "Abrir el menú");
            if (devolverFoco) {
                boton.focus();
            }
        }
        if (boton) {
            boton.hidden = false;
            cabecera.classList.add("menu-plegable");

            // El botón abre y cierra. Además el menú se cierra al
            // elegir un enlace, al tocar fuera y con la tecla Escape.
            document.addEventListener("click", function (evento) {
                if (boton.contains(evento.target)) {
                    abrirMenu(!estaAbierto(), false);
                } else if (estaAbierto() && (!cabecera.contains(evento.target) || evento.target.closest("nav a"))) {
                    abrirMenu(false, false);
                }
            });
            document.addEventListener("keydown", function (evento) {
                if (evento.key === "Escape" && estaAbierto()) {
                    abrirMenu(false, true);
                }
            });
        }

        // Si la ventana cambia de tamaño, el indicador se recoloca y,
        // si quedó grande, el menú vuelve a su estado normal.
        window.addEventListener("resize", function () {
            if (boton && window.innerWidth > 760 && estaAbierto()) {
                abrirMenu(false, false);
            }
            volver();
        });
    }

    // ------------------------------------------------------------
    // APARICIÓN AL ENTRAR EN PANTALLA
    // Los elementos con data-reveal, y los hijos de los que tienen
    // data-reveal-hijos, aparecen una sola vez cuando se acercan a
    // la zona visible. Los de un mismo grupo entran escalonados.
    // ------------------------------------------------------------
    function initReveal() {
        var elementos = todos("[data-reveal], [data-reveal-hijos] > *");
        if (!elementos.length) {
            return;
        }
        function revelar(elemento, orden) {
            // Cada elemento entra un poco después del anterior.
            elemento.style.setProperty("--retraso", Math.min(orden, 8) * 70 + "ms");
            elemento.classList.add("revelado");
        }
        elementos.forEach(function (elemento) {
            alEntrarEnPantalla(elemento, revelar);
        });
    }

    // ------------------------------------------------------------
    // PORTADAS
    // Mientras la imagen carga se ve un brillo sobre el marco. Al
    // cargar, la imagen aparece con un fundido. Si falla (o si la
    // imagen llega vacía), se quita y queda el reemplazo propio.
    // ------------------------------------------------------------
    function initImages() {
        var imagenes = todos("[data-portada] img");
        if (!imagenes.length) {
            return;
        }

        function marcoDe(imagen) {
            return imagen.tagName === "IMG" ? imagen.closest("[data-portada]") : null;
        }
        function quitar(imagen) {
            var marco = marcoDe(imagen);
            if (marco) {
                marco.classList.remove("cargando");
                marco.classList.add("sin-imagen");
                marco.removeChild(imagen);
            }
        }

        // Los avisos "load" y "error" de una imagen no suben por la
        // página, así que se escuchan en la fase de captura: con dos
        // avisos en el documento alcanza para todas las portadas.
        document.addEventListener("load", function (evento) {
            var marco = marcoDe(evento.target);
            if (!marco) {
                return;
            }
            // Una imagen de 1 x 1 es una portada vacía: se trata
            // como si hubiera fallado.
            if (evento.target.naturalWidth <= 1) {
                quitar(evento.target);
            } else {
                marco.classList.remove("cargando");
            }
        }, true);
        document.addEventListener("error", function (evento) {
            quitar(evento.target);
        }, true);

        imagenes.forEach(function (imagen) {
            if (imagen.complete && imagen.naturalWidth > 1) {
                return;                     // ya estaba cargada
            }
            if (imagen.complete && imagen.naturalWidth === 1) {
                quitar(imagen);             // llegó vacía
                return;
            }
            // Falta cargar (o es de carga diferida): se muestra el brillo.
            marcoDe(imagen).classList.add("cargando");
        });

        // Si algo está muy lento, a los 8 segundos se apaga el brillo y
        // se deja el reemplazo (la imagen puede llegar después).
        setTimeout(function () {
            todos("[data-portada].cargando").forEach(function (marco) {
                marco.classList.remove("cargando");
            });
        }, 8000);
    }

    // ------------------------------------------------------------
    // CARRUSEL 3D DE LIBROS DESTACADOS
    // La tarjeta activa va al centro; la anterior y la siguiente
    // quedan a los lados. Cambia solo cada 3 segundos y se puede
    // manejar con botones, puntos, teclado y deslizando el dedo.
    // ------------------------------------------------------------
    function initCarousel() {
        var raiz = uno("[data-carrusel]");
        if (!raiz) {
            return;
        }
        var laminas = todos("[data-slide]", raiz);
        var total = laminas.length;
        if (total < 2) {
            return;
        }

        var ESPERA = 3000;          // milisegundos entre un libro y el siguiente
        var zona = raiz.parentNode; // la sección completa de destacados
        var indice = 0;
        var reloj = null;           // el ÚNICO temporizador del cambio automático
        var pausaManual = false;    // la persona pulsó "Pausar"
        var encima = false;         // el cursor está sobre el carrusel
        var conFoco = false;        // el teclado está dentro del carrusel

        // Zona que avisa a los lectores de pantalla. Solo se usa cuando
        // la persona cambia de libro; el cambio automático no se anuncia.
        var aviso = document.createElement("p");
        aviso.className = "solo-lectores";
        aviso.setAttribute("aria-live", "polite");
        raiz.appendChild(aviso);

        // --- Indicadores: un punto por libro. Un solo aviso de clic
        //     en el contenedor sirve para todos los puntos. ---
        var puntos = document.createElement("div");
        puntos.className = "carrusel-puntos";
        var botonesPunto = laminas.map(function (lamina, i) {
            var punto = document.createElement("button");
            punto.type = "button";
            punto.setAttribute("data-ir", String(i));
            punto.setAttribute("aria-label", "Ir al libro " + (i + 1) + " de " + total);
            puntos.appendChild(punto);
            return punto;
        });
        raiz.appendChild(puntos);
        puntos.addEventListener("click", function (evento) {
            var punto = evento.target.closest("[data-ir]");
            if (punto) {
                ir(parseInt(punto.getAttribute("data-ir"), 10), true);
            }
        });

        // --- Botones: anterior, pausa y siguiente ---
        var DIBUJOS = {
            anterior: '<path d="M15 5l-7 7 7 7"/>',
            pausa: '<path d="M9 6v12M15 6v12"/>',
            seguir: '<path d="M8 5l11 7-11 7z"/>',
            siguiente: '<path d="M9 5l7 7-7 7"/>'
        };
        function crearBoton(accion, etiqueta) {
            var boton = document.createElement("button");
            boton.type = "button";
            boton.setAttribute("data-accion", accion);
            boton.setAttribute("aria-label", etiqueta);
            boton.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + DIBUJOS[accion] + "</svg>";
            return boton;
        }
        var botonPausa = crearBoton("pausa", "Pausar el cambio automático");
        var controles = uno(".carrusel-controles", zona);
        if (controles) {
            controles.appendChild(crearBoton("anterior", "Libro anterior"));
            controles.appendChild(botonPausa);
            controles.appendChild(crearBoton("siguiente", "Libro siguiente"));
            controles.hidden = false;
            controles.addEventListener("click", function (evento) {
                var boton = evento.target.closest("[data-accion]");
                if (!boton) {
                    return;
                }
                var accion = boton.getAttribute("data-accion");
                if (accion === "anterior") {
                    ir(indice - 1, true);
                } else if (accion === "siguiente") {
                    ir(indice + 1, true);
                } else {
                    pausaManual = !pausaManual;
                    pintarPausa();
                    programar();
                }
            });
        }

        function pintarPausa() {
            botonPausa.setAttribute("aria-pressed", pausaManual ? "true" : "false");
            botonPausa.setAttribute("aria-label", pausaManual ? "Reanudar el cambio automático" : "Pausar el cambio automático");
            uno("svg", botonPausa).innerHTML = pausaManual ? DIBUJOS.seguir : DIBUJOS.pausa;
            // Con "menos movimiento" no hay cambio automático que pausar.
            botonPausa.hidden = menosMovimiento();
        }

        // --- Coloca cada tarjeta según su distancia a la activa ---
        // A cada tarjeta se le anota su posición en "data-pos": 0 es la
        // activa, -1 y -2 las dos de la izquierda, 1 y 2 las dos de la
        // derecha, y -3 o 3 las que quedan fuera de la vista. La hoja de
        // estilos decide cómo se ve cada posición (y cuántas se muestran
        // según el ancho de la pantalla).
        function pintar() {
            laminas.forEach(function (lamina, i) {
                // Distancia circular: -1 es la anterior, 1 la siguiente.
                var distancia = ((i - indice) % total + total) % total;
                if (distancia > total / 2) {
                    distancia -= total;
                }
                lamina.setAttribute("data-pos", String(Math.max(-3, Math.min(3, distancia))));
                lamina.classList.toggle("es-activa", distancia === 0);
                // Las tarjetas que no están al centro no se leen ni reciben el teclado.
                lamina.setAttribute("aria-hidden", distancia === 0 ? "false" : "true");
                todos("a", lamina).forEach(function (enlace) {
                    enlace.tabIndex = distancia === 0 ? 0 : -1;
                });
            });
            botonesPunto.forEach(function (punto, i) {
                if (i === indice) {
                    punto.setAttribute("aria-current", "true");
                } else {
                    punto.removeAttribute("aria-current");
                }
            });
        }

        // --- Cambia de libro. "manual" es true si lo pidió la persona. ---
        function ir(nuevo, manual) {
            indice = ((nuevo % total) + total) % total;
            pintar();
            if (manual) {
                var titulo = uno("h4", laminas[indice]);
                aviso.textContent = "Libro " + (indice + 1) + " de " + total + (titulo ? ": " + titulo.textContent : "");
            }
            // Al cambiar a mano, el conteo de 3 segundos empieza de nuevo.
            programar();
        }

        // --- Cambio automático. Antes de crear un temporizador siempre
        //     se cancela el anterior: nunca hay más de uno activo. ---
        function programar() {
            clearTimeout(reloj);
            reloj = null;
            if (!menosMovimiento() && !pausaManual && !encima && !conFoco && !document.hidden) {
                reloj = setTimeout(function () { ir(indice + 1, false); }, ESPERA);
            }
        }

        // Pausa mientras el cursor o el teclado están sobre el carrusel.
        // En pantallas táctiles no existe "pasar el cursor": ahí no se pausa
        // por eso (si no, quedaría detenido después del primer toque). Y la
        // pausa por foco es solo para quien navega con el teclado.
        if (tieneCursor()) {
            zona.addEventListener("mouseenter", function () { encima = true; programar(); });
            zona.addEventListener("mouseleave", function () { encima = false; programar(); });
        }
        zona.addEventListener("focusin", function (evento) {
            conFoco = true;
            try {
                conFoco = evento.target.matches(":focus-visible");
            } catch (error) { /* navegador sin :focus-visible: se pausa igual */ }
            programar();
        });
        zona.addEventListener("focusout", function () { conFoco = false; programar(); });

        // Pausa cuando la pestaña no se está viendo.
        document.addEventListener("visibilitychange", programar);

        // Si cambia la preferencia de movimiento, se ajusta sin recargar.
        if (consultaMovimiento.addEventListener) {
            consultaMovimiento.addEventListener("change", function () { pintarPausa(); programar(); });
        }

        // Teclado: flechas izquierda y derecha.
        raiz.addEventListener("keydown", function (evento) {
            if (evento.key === "ArrowLeft" || evento.key === "ArrowRight") {
                evento.preventDefault();
                ir(indice + (evento.key === "ArrowLeft" ? -1 : 1), true);
            }
        });

        // Deslizar con el dedo (o arrastrar): más de 40 px cambia de libro.
        var inicioX = null;
        var ignorarClic = false;
        raiz.addEventListener("pointerdown", function (evento) { inicioX = evento.clientX; });
        raiz.addEventListener("pointercancel", function () { inicioX = null; });
        raiz.addEventListener("pointerup", function (evento) {
            if (inicioX === null) {
                return;
            }
            var recorrido = evento.clientX - inicioX;
            inicioX = null;
            if (Math.abs(recorrido) > 40) {
                ir(indice + (recorrido < 0 ? 1 : -1), true);
                // El clic que llega justo después del deslizamiento no cuenta
                // (si no, abriría el libro o cambiaría dos veces).
                ignorarClic = true;
                setTimeout(function () { ignorarClic = false; }, 350);
            }
        });

        // Clic dentro del carrusel (un solo aviso para todas las tarjetas):
        // el de después de deslizar se descarta, y el clic en una tarjeta
        // de los lados la trae al centro en lugar de abrir el libro.
        raiz.addEventListener("click", function (evento) {
            if (ignorarClic) {
                evento.preventDefault();
                evento.stopPropagation();
                return;
            }
            var posicion = laminas.indexOf(evento.target.closest("[data-slide]"));
            if (posicion > -1 && posicion !== indice) {
                evento.preventDefault();
                ir(posicion, true);
            }
        }, true);

        pintarPausa();
        pintar();
        programar();

        // Estado visible para las pruebas.
        Biblioteca.carrusel = {
            total: total,
            indice: function () { return indice; },
            enMarcha: function () { return reloj !== null; },
            ir: ir
        };
    }

    // ------------------------------------------------------------
    // INCLINACIÓN 3D
    // La tarjeta se inclina unos pocos grados hacia donde está el
    // cursor y vuelve suavemente al salir. Solo con ratón y solo
    // si no se pidió menos movimiento.
    // Se escucha el movimiento en el contenedor (no en cada tarjeta)
    // y la pantalla se actualiza solo cuando el cursor se mueve.
    // ------------------------------------------------------------
    function initTilt() {
        if (!tieneCursor() || menosMovimiento()) {
            return;
        }

        // contenedor: dónde se escucha; selector: qué se inclina;
        // maxX y maxY: grados como mucho; subir: píxeles que se levanta.
        function activar(contenedor, selector, maxX, maxY, subir) {
            if (!contenedor) {
                return false;
            }
            var actual = null;      // elemento inclinado en este momento
            var pendiente = false;  // ya hay una actualización pedida
            var posX = 0;
            var posY = 0;

            function soltar() {
                if (actual) {
                    actual.classList.remove("inclinando");
                    actual.style.removeProperty("--rx");
                    actual.style.removeProperty("--ry");
                    actual.style.removeProperty("--ty");
                    actual = null;
                }
            }

            contenedor.addEventListener("pointermove", function (evento) {
                var elemento = evento.target.closest(selector);
                if (elemento !== actual) {
                    soltar();
                    actual = elemento;
                    if (actual) {
                        actual.classList.add("inclinando");
                        actual.style.setProperty("--ty", subir + "px");
                    }
                }
                if (!actual) {
                    return;
                }
                var caja = actual.getBoundingClientRect();
                posX = (evento.clientX - caja.left) / caja.width - 0.5;    // de -0,5 a 0,5
                posY = (evento.clientY - caja.top) / caja.height - 0.5;
                // Se actualiza como mucho una vez por cuadro de pantalla.
                if (!pendiente) {
                    pendiente = true;
                    requestAnimationFrame(function () {
                        pendiente = false;
                        if (actual) {
                            actual.style.setProperty("--ry", (posX * 2 * maxY).toFixed(2) + "deg");
                            actual.style.setProperty("--rx", (-posY * 2 * maxX).toFixed(2) + "deg");
                        }
                    });
                }
            });
            contenedor.addEventListener("pointerleave", soltar);
            return true;
        }

        // Tarjetas del catálogo: como mucho 4 grados en X y 5 en Y.
        var enCatalogo = activar(uno(".catalogo"), ".libro", 4, 5, -6);
        // Portada de la ficha: un poco más marcada.
        var enFicha = activar(uno(".detalle-portada"), ".detalle-portada", 5, 8, 0);
        Biblioteca.inclinacion = enCatalogo || enFicha;
    }

    // ------------------------------------------------------------
    // LOGIN Y REGISTRO
    // Botón para ver la contraseña e indicador de fortaleza. Son
    // ayudas visuales: la comprobación real la hace PHP.
    // ------------------------------------------------------------
    function initAuthForms() {
        var campos = todos(".campo-clave");
        if (!campos.length) {
            return;
        }

        // --- Mostrar / ocultar contraseña ---
        campos.forEach(function (campo) {
            var entrada = uno("input", campo);
            if (!entrada) {
                return;
            }
            var boton = document.createElement("button");
            boton.type = "button";
            boton.className = "ver-clave";
            boton.textContent = "Mostrar";
            boton.setAttribute("aria-label", "Mostrar la contraseña");
            boton.setAttribute("aria-pressed", "false");
            boton.addEventListener("click", function () {
                var mostrar = entrada.type === "password";
                entrada.type = mostrar ? "text" : "password";
                boton.textContent = mostrar ? "Ocultar" : "Mostrar";
                boton.setAttribute("aria-label", mostrar ? "Ocultar la contraseña" : "Mostrar la contraseña");
                boton.setAttribute("aria-pressed", mostrar ? "true" : "false");
                entrada.focus();
            });
            campo.appendChild(boton);
        });

        // --- Fortaleza de la contraseña (solo en el registro) ---
        var medidor = uno("[data-fortaleza]");
        var entrada = medidor ? uno("#" + medidor.getAttribute("data-fortaleza")) : null;
        var texto = medidor ? uno(".fortaleza-texto", medidor) : null;
        if (!entrada || !texto) {
            return;
        }
        var NIVELES = ["Escribe una contraseña", "Débil", "Media", "Fuerte"];

        function medir() {
            var clave = entrada.value;
            // Un punto por cada condición que cumple la contraseña.
            var puntos = [
                clave.length >= 8,
                clave.length >= 12,
                /[a-z]/.test(clave) && /[A-Z]/.test(clave),
                /\d/.test(clave),
                /[^A-Za-z0-9]/.test(clave)
            ].filter(Boolean).length;

            var nivel = 0;
            if (clave.length > 0) {
                nivel = puntos <= 1 ? 1 : (puntos <= 3 ? 2 : 3);
            }
            medidor.setAttribute("data-nivel", String(nivel));
            texto.textContent = NIVELES[nivel];
        }
        medidor.hidden = false;
        entrada.addEventListener("input", medir);
        medir();
    }

    // ------------------------------------------------------------
    // FORMULARIOS
    // Marca el campo en el que se está escribiendo, muestra que el
    // formulario se está enviando y aplica el filtro de categoría
    // apenas se elige. Todo con avisos en el documento, sin poner
    // uno en cada campo o en cada botón.
    // ------------------------------------------------------------
    function initForms() {
        if (!uno("form, [data-espera]")) {
            return;
        }

        // --- Campo activo: su etiqueta cambia de color ---
        function etiquetaDe(campo) {
            var contenedor = campo.closest(".campo-clave") || campo;
            var anterior = contenedor.previousElementSibling;
            if (anterior && anterior.tagName === "LABEL") {
                return anterior;
            }
            var padre = contenedor.parentElement;
            return padre && padre.tagName === "DIV" ? padre : null;
        }
        function marcarCampo(evento) {
            if (evento.target.matches && evento.target.matches("input, select, textarea")) {
                var etiqueta = etiquetaDe(evento.target);
                if (etiqueta) {
                    etiqueta.classList.toggle("campo-activo", evento.type === "focusin");
                }
            }
        }
        document.addEventListener("focusin", marcarCampo);
        document.addEventListener("focusout", marcarCampo);

        // --- Estado de envío: el botón gira y, si tiene data-enviando,
        //     cambia su texto ("Ingresando...") ---
        document.addEventListener("submit", function (evento) {
            if (evento.defaultPrevented) {
                return;
            }
            var boton = evento.submitter || uno('button[type="submit"], button:not([type])', evento.target);
            if (!boton || boton.classList.contains("enviando")) {
                return;
            }
            boton.classList.add("enviando");
            boton.setAttribute("aria-busy", "true");
            if (boton.getAttribute("data-enviando")) {
                boton.setAttribute("data-texto", boton.textContent);
                boton.textContent = boton.getAttribute("data-enviando");
            }
        });

        // --- Los enlaces con data-espera ("Comprar digital") muestran
        //     que la página siguiente se está abriendo ---
        document.addEventListener("click", function (evento) {
            var enlace = evento.target.closest("[data-espera]");
            if (enlace) {
                enlace.classList.add("enviando");
            }
        });

        // Si se vuelve atrás con el navegador, los botones recuperan su aspecto.
        window.addEventListener("pageshow", function () {
            todos(".enviando").forEach(function (boton) {
                boton.classList.remove("enviando");
                boton.removeAttribute("aria-busy");
                if (boton.getAttribute("data-texto")) {
                    boton.textContent = boton.getAttribute("data-texto");
                }
            });
        });

        // --- Filtro del catálogo: al elegir una categoría se aplica solo ---
        var filtro = uno(".buscador select");
        if (filtro) {
            filtro.addEventListener("change", function () {
                filtro.form.submit();
            });
        }
    }

    // ------------------------------------------------------------
    // MENSAJES FLOTANTES
    // Los avisos que escribe PHP se muestran como mensaje flotante
    // abajo a la derecha: los de éxito se van solos; los de error
    // quedan hasta cerrarlos. Para que no se vea dos veces, el aviso
    // de la página se oculta cuando ya salió como flotante.
    // Excepción: en la caja, el "Agregado: ..." de cada libro
    // escaneado NO sale flotante; se queda en la página como antes.
    // Sin JavaScript no cambia nada: los avisos de PHP se ven igual.
    // ------------------------------------------------------------
    function initToasts() {
        var CLASES = { exito: "mensaje mensaje-exito", error: "mensaje mensaje-error", info: "mensaje" };
        var TILDE = '<svg class="tilde" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

        var contenedor = document.createElement("div");
        contenedor.className = "mensajes";
        contenedor.setAttribute("role", "status");
        contenedor.setAttribute("aria-live", "polite");
        document.body.appendChild(contenedor);

        function cerrar(caja) {
            caja.classList.add("saliendo");
            setTimeout(function () {
                if (caja.parentNode) {
                    caja.parentNode.removeChild(caja);
                }
            }, 320);
        }
        // Un solo aviso de clic sirve para el botón de cerrar de todos los mensajes.
        contenedor.addEventListener("click", function (evento) {
            if (evento.target.closest("button")) {
                cerrar(evento.target.closest(".mensaje"));
            }
        });

        // tipo: "exito", "error" o "info". Con fijo = true el mensaje
        // no se cierra solo aunque sea de éxito.
        function mostrar(texto, tipo, fijo) {
            var caja = document.createElement("div");
            caja.className = CLASES[tipo] || CLASES.info;
            var parrafo = document.createElement("p");
            parrafo.textContent = texto;
            var boton = document.createElement("button");
            boton.type = "button";
            boton.setAttribute("aria-label", "Cerrar el mensaje");
            boton.textContent = "×";
            caja.appendChild(parrafo);
            caja.appendChild(boton);
            contenedor.appendChild(caja);
            // Los errores no se cierran solos.
            if (tipo !== "error" && !fijo) {
                setTimeout(function () { cerrar(caja); }, 5000);
            }
            return caja;
        }
        Biblioteca.mensaje = mostrar;

        // Avisos de PHP que están directamente en la página (no los de
        // los formularios de login y registro, que ya están a la vista).
        var enCaja = !!uno(".form-caja");
        todos("main > p.exito, main > p.error").forEach(function (aviso) {
            var exito = aviso.classList.contains("exito");
            var texto = aviso.textContent.trim();
            var registrada = exito && /^(Venta|Compra) registrada/.test(texto);

            // Caja: un libro escaneado bien no genera mensaje flotante.
            // Solo lo generan los errores y la venta finalizada.
            if (enCaja && exito && !registrada) {
                return;
            }

            // La venta o compra registrada trae el número de venta: ese
            // mensaje queda hasta cerrarlo y lleva un tilde dibujado.
            var flotante = mostrar(texto, exito ? "exito" : "error", registrada);
            if (registrada) {
                uno("p", flotante).insertAdjacentHTML("afterbegin", TILDE);
            }

            // El aviso ya está a la vista como flotante: el de la página
            // se oculta para que el mismo texto no aparezca dos veces.
            aviso.hidden = true;
        });
    }

    // ------------------------------------------------------------
    // DIÁLOGO DE CONFIRMACIÓN
    // Los formularios con data-confirmar (dar de baja, eliminar,
    // vaciar el ticket) preguntan antes de enviarse. Un solo aviso
    // en el documento sirve para todos (en "Gestionar libros" hay
    // uno por cada libro).
    // ------------------------------------------------------------
    function initConfirmations() {
        if (!uno("form[data-confirmar]")) {
            return;
        }

        var dialogo = null;
        var pendiente = null;   // formulario que espera la respuesta
        if (window.HTMLDialogElement) {
            dialogo = document.createElement("dialog");
            dialogo.className = "dialogo";
            dialogo.setAttribute("aria-labelledby", "dialogo-titulo");
            dialogo.innerHTML = '<h3 id="dialogo-titulo">¿Confirmas la acción?</h3><p></p>' +
                '<div class="dialogo-botones"><button type="button" class="btn btn-secondary" data-dialogo="cancelar">Cancelar</button>' +
                '<button type="button" class="btn btn-danger" data-dialogo="confirmar">Confirmar</button></div>';
            document.body.appendChild(dialogo);

            dialogo.addEventListener("click", function (evento) {
                var respuesta = evento.target.getAttribute("data-dialogo");
                if (!respuesta) {
                    return;
                }
                var formulario = pendiente;
                dialogo.close();
                if (respuesta === "confirmar" && formulario) {
                    formulario.setAttribute("data-confirmado", "1");
                    if (formulario.requestSubmit) {
                        formulario.requestSubmit();
                    } else {
                        formulario.submit();
                    }
                }
            });
            dialogo.addEventListener("close", function () { pendiente = null; });
        }

        // Se escucha en la fase de captura para preguntar ANTES de que
        // cualquier otro componente trate el envío del formulario.
        document.addEventListener("submit", function (evento) {
            var formulario = evento.target;
            var pregunta = formulario.getAttribute("data-confirmar");
            if (!pregunta || formulario.getAttribute("data-confirmado")) {
                return;     // no hay que preguntar, o ya se confirmó
            }
            if (dialogo) {
                evento.preventDefault();
                pendiente = formulario;
                uno("p", dialogo).textContent = pregunta;
                dialogo.showModal();
            } else if (!window.confirm(pregunta)) {
                evento.preventDefault();
            }
        }, true);
    }

    // ------------------------------------------------------------
    // CAJA
    // Compara el ticket con el de la carga anterior para animar la
    // fila que se agregó, hace correr el total hasta su nuevo
    // valor y sacude el campo si el código no se encontró.
    // No toca la lógica de la venta: solo mira lo que PHP dibujó.
    // ------------------------------------------------------------
    function initCashRegister() {
        var campo = uno(".form-caja #codigo");
        if (!campo) {
            return;
        }

        // --- Filas nuevas o con más cantidad ---
        var antes = leer("caja-ticket") || {};
        var ahora = {};
        todos("tr[data-fila]").forEach(function (fila) {
            var id = fila.getAttribute("data-fila");
            var cantidad = parseInt(fila.getAttribute("data-cantidad"), 10) || 0;
            ahora[id] = cantidad;
            if (!(id in antes)) {
                fila.classList.add("fila-nueva");
            } else if (cantidad > antes[id]) {
                fila.classList.add("fila-actualizada");
            }
        });
        guardar("caja-ticket", ahora);

        // --- Total: corre desde el valor anterior hasta el nuevo ---
        var total = uno("[data-total]");
        var valor = total ? parseFloat(total.getAttribute("data-total")) : 0;
        var previo = leer("caja-total");
        guardar("caja-total", valor);

        function pesos(numero) {
            var partes = numero.toFixed(2).split(".");
            return "$ " + partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".") + "," + partes[1];
        }
        if (total && typeof previo === "number" && previo !== valor && !menosMovimiento()) {
            animarNumero(total, previo, valor, 450, pesos);
        }

        // --- Error: el campo de escaneo se sacude ---
        if (uno("main > p.error")) {
            campo.classList.add("sacudir");
            campo.addEventListener("animationend", function () { campo.classList.remove("sacudir"); });
        }
    }

    // ------------------------------------------------------------
    // TABLAS
    // Resalta la fila que se acaba de modificar (stock, libro,
    // usuario) y hace entrar las filas de a una.
    // ------------------------------------------------------------
    function initTables() {
        var tablas = todos(".tabla-stock");
        if (!tablas.length) {
            return;
        }

        // --- Recordar qué fila se está por modificar (un solo aviso
        //     para todos los formularios de la página) ---
        document.addEventListener("submit", function (evento) {
            var formulario = evento.target;
            var fila = formulario.closest("tr[data-fila]");
            var oculto = uno('input[type="hidden"][name="id"]', formulario);
            var id = fila ? fila.getAttribute("data-fila") : (oculto && parseInt(oculto.value, 10) > 0 ? oculto.value : null);
            if (id) {
                guardar("fila-modificada", { pagina: location.pathname, id: id });
            }
        });

        // --- Resaltarla al volver, si PHP avisó que salió bien ---
        var recordada = leer("fila-modificada");
        try { sessionStorage.removeItem("fila-modificada"); } catch (error) { /* nada */ }
        if (recordada && recordada.pagina === location.pathname && uno("main > p.exito")) {
            var modificada = uno('tr[data-fila="' + String(recordada.id).replace(/"/g, "") + '"]');
            if (modificada && !modificada.classList.contains("fila-nueva")) {
                modificada.classList.add("fila-actualizada");
            }
        }

        // --- Entrada escalonada de las filas (las primeras de cada tabla) ---
        tablas.forEach(function (tabla) {
            todos("tr", tabla).slice(1, 16).forEach(function (fila, i) {
                if (!fila.classList.contains("fila-nueva") && !fila.classList.contains("fila-actualizada")) {
                    fila.style.setProperty("--retraso", i * 35 + "ms");
                    fila.classList.add("revelado");
                }
            });
        });
    }

    // ------------------------------------------------------------
    // INICIO
    // Arranca cada componente por separado: si uno falla, se anota
    // el error y los demás siguen funcionando.
    // ------------------------------------------------------------
    function init() {
        [
            initNavbar, initReveal, initImages, initCarousel, initTilt,
            initAuthForms, initForms, initToasts, initConfirmations, initCashRegister, initTables
        ].forEach(function (componente) {
            try {
                componente();
            } catch (error) {
                if (window.console) {
                    console.error("Biblioteca: fallo en " + componente.name, error);
                }
            }
        });

        // Aviso para la página de que el JavaScript quedó funcionando.
        window.BibliotecaLista = true;
    }

    init();
})();
