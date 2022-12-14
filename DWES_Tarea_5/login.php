<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!--
Copyright (C) 2015 Luis Cabrerizo Gómez

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
-->

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!-- Cargamos el fichero de funciones Javascript. Hay que cargarlo con 
            la etiqueta <script> para que no de errores -->
            <script type="text/javascript" src="scripts.js"></script>
            <title>Login</title>
    </head>
    <body>
        <?php
        // Iniciamos sesión
        session_start();

        // Cargamos los archivos necesarios
        require_once './configuracion.inc.php';
        require_once './Db.php';

        // Comprobamos si hay una petición de limpieza de usuario y password de 
        // sesión, lo que siginificariá que el usuario logeado desea deslogearse
        if (isset($_POST['clear'])) {
            // Eliminamos la informaicón del POST sobre usuario y contraseña
            unset($_POST['user']);
            unset($_POST['pass']);
        }
        
        // Definimos e inicializamos la variable de errores
        $error = " ";

        try {

            // Comprobamos si POST trae informaicón de usuario y password
            if (isset($_POST['user']) && isset($_POST['pass'])) {
                // Creamos un nuevo objeto de acceso a base de datos
                $db = new DB();

                // Obtenemos el listado de todas las personas
                if ($db->validarUsuario(md5($_POST['user']), md5($_POST['pass']))) {
                    // Guardamos el hash del usuario y del password en sesión
                    $_SESSION['user'] = md5($_POST['user']);
                    $_SESSION['pass'] = md5($_POST['pass']);

                    // Eliminamos la informaicón del POST sobre usuario y contraseña
                    unset($_POST['user']);
                    unset($_POST['pass']);

                    // Limpiamos la variable de errores
                    $error = " ";

                    // Navegamos a la página de inicio de la aplicación
                    header("location:index.php");
                } else {
                    // Mostramos un mensaje de error
                    $error = "Usuario o contraseña incorrectos";
                }
            }

            // Comprobamos si se trae información de error en el POST de la web
            if (isset($_POST['error'])) {

                // Dependiendo del error devuelto, mostramos un mensaje diferente al usuario
                switch ($_POST['error']) {
                    case 1:
                        // Mensaje error de validación
                        $error = "Debe introducir un usuario y una contraseña válidos";
                        break;

                    default:
                        // Mensaje por defecto
                        $error = "Error!. Pongase en contacto con el administrador en la siguiente dirección: $emailAdmin";
                        break;
                }
            }
        } catch (Exception $ex) {
            // Si se produce una excepción, asignamos el mensaje a la variable de error
            $error = $ex->getMessage();
        }


        // Asignamos la variable error a la plantilla
        $html->assign('error', $error);

        // Mostramos en el motor Smarty la plantilla de la página principal
        $html->display("login.tpl");
        ?>
    </body>
</html>