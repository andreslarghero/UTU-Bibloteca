<?php
// ============================================================
// VISTA: libros_admin
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/libros_admin.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <main>
        <h2>Gestión de libros</h2>
        <p class="descripcion-pagina">Alta, edición y baja de los libros del catálogo, en formato físico, digital o ambos.</p>

        <!-- Aviso verde después de guardar, dar de baja o reactivar -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <!-- Errores de validación del formulario -->
        <?php foreach ($errores as $error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>

        <!-- Formulario de alta y edición. El campo oculto "id" indica
             si es un libro nuevo (0) o uno que ya existe.
             enctype="multipart/form-data" es necesario para poder enviar un archivo. -->
        <form class="form-libro" name="form_libro" method="POST" action="libros_admin.php" id="formulario" enctype="multipart/form-data">
            <h3><?php echo ($libro["id"] > 0) ? "Editar libro" : "Agregar libro"; ?></h3>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?php echo (int) $libro["id"]; ?>">

            <div>
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" maxlength="150" value="<?php echo htmlspecialchars($libro["titulo"]); ?>" required>
            </div>
            <div>
                <label for="autor">Autor</label>
                <input type="text" id="autor" name="autor" maxlength="100" value="<?php echo htmlspecialchars($libro["autor"]); ?>" required>
            </div>
            <div>
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Elige una categoría</option>
                    <?php foreach ($categorias as $id_categoria => $nombre_categoria) { ?>
                        <option value="<?php echo $id_categoria; ?>" <?php if ($libro["categoria_id"] == $id_categoria) echo "selected"; ?>>
                            <?php echo htmlspecialchars($nombre_categoria); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label for="anio">Año de publicación</label>
                <input type="number" id="anio" name="anio" min="1000" max="<?php echo date("Y"); ?>" value="<?php echo htmlspecialchars((string) $libro["anio"]); ?>">
            </div>
            <div>
                <label for="isbn">ISBN (opcional)</label>
                <input type="text" id="isbn" name="isbn" maxlength="20" value="<?php echo htmlspecialchars((string) $libro["isbn"]); ?>">
            </div>

            <!-- Formatos en los que se vende el libro: hay que marcar al menos uno -->
            <div class="campo-ancho">
                <!-- Título del grupo de casillas; cada casilla tiene además su propia etiqueta -->
                <label id="formatos_titulo">Formatos en los que se vende</label>
                <div class="casillas" role="group" aria-labelledby="formatos_titulo">
                    <label for="formato_fisico"><input type="checkbox" id="formato_fisico" name="formato_fisico" value="1" <?php if ($libro["formato_fisico"] == 1) echo "checked"; ?>> Formato físico</label>
                    <label for="formato_digital"><input type="checkbox" id="formato_digital" name="formato_digital" value="1" <?php if ($libro["formato_digital"] == 1) echo "checked"; ?>> Formato digital</label>
                </div>
            </div>

            <!-- Datos del formato físico: solo se exigen si está marcado "Formato físico" -->
            <div>
                <label for="codigo_barras">Código de barras (físico)</label>
                <input type="text" id="codigo_barras" name="codigo_barras" maxlength="20" value="<?php echo htmlspecialchars((string) $libro["codigo_barras"]); ?>">
            </div>
            <div>
                <label for="precio">Precio físico</label>
                <input type="number" id="precio" name="precio" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $libro["precio"]); ?>">
            </div>
            <div>
                <label for="stock">Stock (físico)</label>
                <input type="number" id="stock" name="stock" min="0" step="1" value="<?php echo htmlspecialchars((string) $libro["stock"]); ?>">
            </div>

            <!-- Datos del formato digital: solo se exigen si está marcado "Formato digital" -->
            <div>
                <label for="precio_digital">Precio digital</label>
                <input type="number" id="precio_digital" name="precio_digital" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $libro["precio_digital"]); ?>">
            </div>
            <div class="campo-ancho">
                <label for="archivo">Archivo digital (PDF o EPUB, máximo 20 MB)</label>
                <!-- "accept" solo ayuda a elegir el archivo; la comprobación real se hace en el servidor -->
                <input type="file" id="archivo" name="archivo" accept=".pdf,.epub">
                <!-- Nunca se muestra el nombre ni la ubicación del archivo guardado -->
                <?php if ($tiene_archivo) { ?>
                    <p class="ayuda">Este libro ya tiene un archivo digital cargado. Si no eliges otro, se conserva el actual.</p>
                <?php } else { ?>
                    <p class="ayuda">Este libro todavía no tiene un archivo digital cargado.</p>
                <?php } ?>
            </div>

            <div class="campo-ancho">
                <label for="portada">Portada (archivo de la carpeta img/portadas)</label>
                <input type="text" id="portada" name="portada" maxlength="255" placeholder="img/portadas/nombre-del-libro.webp" value="<?php echo htmlspecialchars((string) $libro["portada"]); ?>">
                <p class="ayuda">Si queda vacío, el libro se muestra con la portada de reemplazo.</p>
            </div>
            <div class="campo-ancho">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars((string) $libro["descripcion"]); ?></textarea>
            </div>
            <div class="campo-ancho botones-libro">
                <button class="btn btn-primary" type="submit"><?php echo ($libro["id"] > 0) ? "Guardar cambios" : "Agregar libro"; ?></button>
                <!-- Al editar, permite volver al formulario vacío sin guardar -->
                <?php if ($libro["id"] > 0) { ?>
                    <a class="volver" href="libros_admin.php">Cancelar</a>
                <?php } ?>
            </div>
        </form>

        <h3 class="titulo-ticket">Libros registrados</h3>

        <!-- Tabla con todos los libros, también los dados de baja -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>Título</th>
                <th>Autor</th>
                <th>Categoría</th>
                <th>ISBN</th>
                <th>Físico</th>
                <th>Stock</th>
                <th>Digital</th>
                <th>Estado</th>
                <th></th>
            </tr>
            <!-- Una fila por cada libro -->
            <?php foreach ($lista as $fila) { ?>
            <!-- data-fila: main.js lo usa para resaltar la fila recién modificada -->
            <tr data-fila="<?php echo $fila["id"]; ?>" <?php if ($fila["activo"] == 0) echo 'class="fila-inactiva"'; ?>>
                <td><?php echo htmlspecialchars($fila["titulo"]); ?></td>
                <td><?php echo htmlspecialchars($fila["autor"]); ?></td>
                <td><?php echo htmlspecialchars($fila["categoria"]); ?></td>
                <td><?php echo $fila["isbn"] ? htmlspecialchars($fila["isbn"]) : "Sin ISBN"; ?></td>
                <!-- Precio y stock del formato físico, y precio del digital.
                     Un guion indica que el libro no se vende en ese formato. -->
                <td class="sin-corte"><?php echo ($fila["formato_fisico"] == 1) ? "$ " . formatearPrecio($fila["precio"]) : "—"; ?></td>
                <td><?php echo ($fila["formato_fisico"] == 1) ? $fila["stock"] : "—"; ?></td>
                <td class="sin-corte"><?php echo ($fila["formato_digital"] == 1) ? "$ " . formatearPrecio($fila["precio_digital"]) : "—"; ?></td>
                <td>
                    <!-- Etiqueta verde si está activo, roja si está dado de baja -->
                    <?php if ($fila["activo"] == 1) { ?>
                        <span class="disponible">Activo</span>
                    <?php } else { ?>
                        <span class="agotado">Inactivo</span>
                    <?php } ?>
                </td>
                <td>
                    <div class="acciones-linea">
                        <a class="btn btn-primary btn-sm" href="libros_admin.php?editar=<?php echo $fila["id"]; ?>#formulario">Editar</a>
                        <!-- Baja lógica o reactivación, según el estado del libro -->
                        <!-- data-confirmar (solo al dar de baja): main.js pregunta antes de enviar -->
                        <form name="form_libro_estado_<?php echo $fila["id"]; ?>" method="POST" action="libros_admin.php" <?php if ($fila["activo"] == 1) echo 'data-confirmar="¿Dar de baja este libro? Dejará de verse en el catálogo y en la caja."'; ?>>
                            <input type="hidden" name="id" value="<?php echo $fila["id"]; ?>">
                            <?php if ($fila["activo"] == 1) { ?>
                                <input type="hidden" name="accion" value="baja">
                                <button class="btn btn-danger btn-sm" type="submit">Dar de baja</button>
                            <?php } else { ?>
                                <input type="hidden" name="accion" value="reactivar">
                                <button class="btn btn-success btn-sm" type="submit">Reactivar</button>
                            <?php } ?>
                        </form>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
    </main>

<?php include __DIR__ . "/parciales/pie.php"; ?>
