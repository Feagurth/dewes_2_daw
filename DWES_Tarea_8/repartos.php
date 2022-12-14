<?php
/**
 * Desarrollo Web en Entorno Servidor
 * Tema 8 : Aplicaciones web híbridas
 * Ejemplo Rutas de reparto: repartos.php
 */
session_start();

// Incluimos la API de Google
require_once 'libs/google-api-php-client/src/apiClient.php';

require_once './libs/google-api-php-client/src/contrib/apiTasksService.php';

// Incluimoas la API de Google Calendar
require_once './libs/google-api-php-client/src/contrib/apiCalendarService.php';

// y la librería Xajax
require_once 'libs/xajax_core/xajax.inc.php';


// Creamos el objeto xajax
$xajax = new xajax('ajaxmaps.php');

// Configuramos la ruta en que se encuentra la carpeta xajax_js
$xajax->configure('javascript URI', './libs/');

// Y registramos las funciones que vamos a llamar desde JavaScript
$xajax->register(XAJAX_FUNCTION, "obtenerCoordenadas");
$xajax->register(XAJAX_FUNCTION, "ordenarReparto");

// Inicializamos la variable de error
$error = "";

// Creamos el objeto de la API de Google
$cliente = new apiClient();

// Y lo configuramos con los nuestros identificadores
$cliente->setClientId('1013740832220-67pm77vvshve5ssjm4n50n4km3qe01od.apps.googleusercontent.com');
$cliente->setClientSecret('rkI6OVN6MTiimjv-f2Yz1Nib');
$cliente->setRedirectUri('http://localhost/DWES_Tarea_8/repartos.php');
$cliente->setDeveloperKey('AIzaSyDtkjmDITyn4oQSpcuauV87uVRkwfb2Lu4');

// Creamos también un objeto para manejar las listas y sus tareas
$apitareas = new apiTasksService($cliente);

// Creamos un objeto para cmanejar los calendarios y sus eventos
$apicalendario = new apiCalendarService($cliente);

// Comprobamos o solicitamos la autorización de acceso
if (isset($_SESSION['clave_acceso'])) {
    $cliente->setAccessToken($_SESSION['clave_acceso']);
} else {
    $cliente->setAccessToken($cliente->authenticate());
    $_SESSION['clave_acceso'] = $cliente->getAccessToken();
}

// Comprobamos si se debe ejecutar alguna acción
if (isset($_GET['accion'])) {
    switch ($_GET['accion']) {
        case 'nuevalista':
            if (!empty($_GET['nuevotitulo'])) {
                // Crear una nueva lista de reparto

                try {
                    $nuevalista = new TaskList();

                    $nuevalista->setTitle('Reparto ' . date("d/m/Y", strtotime($_GET['nuevotitulo'])));

                    $apitareas->tasklists->insert($nuevalista);

                    // Recuperamos el Id del calendario principal
                    $idCalendario = $apicalendario->calendarList->listCalendarList()['items'][0]['id'];

                    // Creamos un nuevo evento de calendario
                    $event = new Event();

                    // Definimos el título del evento
                    $event->setSummary('Reparto');

                    // Creamos un objeto de fecha y hora para el evento
                    $start = new EventDateTime();

                    // Asignamos la hora de inicio como las 9 de la mañana del 
                    // dia en que se crea la tarea, recuperando el valor del 
                    // título de la tarea
                    $start->setDateTime($_GET['nuevotitulo'] . 'T09:00:00');
                    
                    // Especificamos una zona de tiempo al objeto fecha y hora
                    $start->setTimeZone('Europe/Madrid');

                    // Asignamos el objeto creado como la fecha y hora de 
                    // inicio de la tarea
                    $event->setStart($start);

                    // Creamos otro objeto de fecha y hora para el evento
                    $end = new EventDateTime();

                    // Asignamos la hora de fin como las 8 de la tarde del 
                    // dia en que se crea la tarea, recuperando el valor del 
                    // título de la tarea                    
                    $end->setDateTime($_GET['nuevotitulo'] . 'T20:00:00');
                    
                    // Especificamos una zona de tiempo al objeto fecha y hora
                    $end->setTimeZone('Europe/Madrid');

                    // Asignamos el objeto creado como la fecha y hora de 
                    // fin de la tarea                    
                    $event->setEnd($end);

                    // Usando el objeto creado anteriormente para controlar el 
                    // calendario, insertamos el evento creado en el calendario 
                    // cuya id teniamos almacenada y que correspondía con el 
                    // calendario principal
                    $apicalendario->events->insert($idCalendario, $event);
                } catch (Exception $e) {
                    $error = "Se ha producido un error al intentar crear un nuevo reparto.";
                }
            }
            break;
        case 'nuevatarea':
            if (!empty($_GET['nuevotitulo']) && !empty($_GET['idreparto']) && !empty($_GET['latitud']) && !empty($_GET['longitud'])) {
                // Crear una nueva tarea de envío
                try {
                    $nuevatarea = new Task();
                    $nuevatarea->setTitle($_GET['nuevotitulo']);
                    if (isset($_GET['direccion'])) {
                        $nuevatarea->setTitle($_GET['nuevotitulo'] . " - " . $_GET['direccion']);
                    } else {
                        $nuevatarea->setTitle($_GET['nuevotitulo']);
                    }
                    $nuevatarea->setNotes($_GET['latitud'] . "," . $_GET['longitud']);
                    // Añadimos la nueva tarea de envío a la lista de reparto
                    $apitareas->tasks->insert($_GET['idreparto'], $nuevatarea);
                } catch (Exception $e) {
                    $error = "Se ha producido un error al intentar crear un nuevo envío.";
                }
            }
            break;
        case 'borrarlista':
            if (!empty($_GET['reparto'])) {
                // Borrar una lista de reparto
                try {
                    $apitareas->tasklists->delete($_GET['reparto']);
                } catch (Exception $e) {
                    $error = "Se ha producido un error al intentar borrar el reparto.";
                }
            }
            break;
        case 'borrartarea':
            if (!empty($_GET['reparto']) && !empty($_GET['envio'])) {
                // Borrar una tarea de envío
                try {
                    $apitareas->tasks->delete($_GET['reparto'], $_GET['envio']);
                } catch (Exception $e) {
                    $error = "Se ha producido un error al intentar borrar el envío.";
                }
            }
            break;
        case 'ordenarEnvios':
            if (!empty($_GET['reparto']) && !empty($_GET['pos'])) {
                // Reordenar las tareas de envío según el orden que se recibe en el array 'pos'
                try {
                    // Primero obtenemos todas las tareas de la lista de reparto
                    $tareas = $apitareas->tasks->listTasks($_GET['reparto']);

                    // Y después las movemos según la posición recibida en el array 'pos'
                    // El array 'pos' indica la posición que debe tener cada tarea de la lista
                    // $pos[0] = 3 significa que la 1ª tarea (la de índice 0)
                    // debe ponerse en la 4ª posición (la de índice 3)
                    // 
                    // Lo convertimos en el array 'orden' que contiene las tareas que debe haber
                    //  en cada posición de la lista
                    // $orden[3] = 0 significa que en la 4ª posición debemos poner la 1ª tarea 
                    $orden = array_flip($_GET['pos']);

                    // Recorremos el array en orden inverso, esto es, empezando por la tarea
                    //  que debería figurar en última posición de la lista
                    // En cada paso ponemos una tarea en la primera posición de la lista
                    for ($i = count($orden) - 1; $i >= 0; $i--) {
                        $apitareas->tasks->move($_GET['reparto'], $tareas['items'][$orden[$i]]['id']);
                    }
                } catch (Exception $e) {
                    $error = "Se ha producido un error al intentar ordenar los envíos del reparto.";
                }
            }
            break;
    }
}

// Obtenemos el id de la lista de tareas por defecto, para no mostrarla
$listapordefecto = $apitareas->tasklists->get('@default');
$id_defecto = $listapordefecto['id'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
        <title>Ejemplo Tema 8: Rutas de reparto</title>
        <link href="estilos.css" rel="stylesheet" type="text/css" />
        <?php
            // Le indicamos a Xajax que incluya el código JavaScript necesario
            $xajax->printJavascript();
        ?>    
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
        <script type="text/javascript" src="codigo.js"></script>
    </head>

    <body>
        <div id="dialogo">
            <a id="cerrarDialogo" onclick="ocultarDialogo();">x</a>
            <h1>Datos del nuevo envío</h1>
            <form id="formenvio" name="formenvio" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
                <fieldset>
                    <div id="datosDireccion">
                        <p>
                            <label for='direccion' >Dirección:</label>
                            <input type='text' size="60" name='direccion' id='direccion' />
                        </p>
                        <input type='button' id='obtenerCoordenadas' value='Obtener coordenadas' onclick="getCoordenadas();"/><br />
                    </div>
                    <div id="datosEnvio">
                        <p>
                            <label for='latitud' >Latitud:</label>
                            <input type='text' size="10" name='latitud' id='latitud' />
                        </p>
                        <p>
                            <label for='longitud' >Longitud:</label>
                            <input type='text' size="10" name='longitud' id='longitud' />
                        </p>
                        <p>
                            <label for='altitud' >Altitud:</label>
                            <input type='text' size="10" name='altitud' id='altitud' />
                        </p>                        
                        <p>
                            <label for='nuevotitulo' >Título:</label>
                            <input type='text' size="40" name='nuevotitulo' id='titulo' />
                        </p>
                        <input type='hidden' name='accion' value='nuevatarea' />
                        <input type='hidden' name='idreparto' id='idrepartoactual' />
                        <input type='submit' id='nuevoEnvio' value='Crear nuevo Envío' />
                        <a href="#" onclick="abrirMaps();">Ver en Google Maps</a><br />
                    </div>
                </fieldset>
            </form>
        </div>
        <div id="fondonegro" onclick="ocultarDialogo();"></div>
        <div class="contenedor">
            <div class="encabezado">
                <h1>Ejemplo Tema 8: Rutas de reparto</h1>
                <form id="nuevoreparto" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
                    <fieldset>
                        <input type='hidden' name='accion' value='nuevalista' />
                        <input type='submit' id='crearnuevotitulo' value='Crear Nueva Lista de Reparto' />
                        <label for='nuevotitulo' >para la fecha:</label>
                        <input type='date' name='nuevotitulo' id='nuevotitulo' value="<?php echo date("Y-m-d"); ?>"/>
                    </fieldset>
                </form>
            </div>
            <div class="contenido">
                <?php
                $repartos = $apitareas->tasklists->listTasklists();
                // Para cada lista de reparto
                foreach ($repartos['items'] as $reparto) {
                    // Excluyendo la lista por defecto de Google Tasks
                    if ($reparto['id'] == $id_defecto) {
                        continue;
                    }

                    print '<div id="' . $reparto['id'] . '">';
                    print '<span class="titulo">' . $reparto['title'] . '</span>';
                    $idreparto = "'" . $reparto['id'] . "'";
                    print '<span class="accion">(<a href="#" onclick="ordenarReparto(' . $idreparto . ');">Ordenar</a>)</span>';
                    print '<span class="accion">(<a href="#" onclick="nuevoEnvio(' . $idreparto . ');">Nuevo Envío</a>)</span>';
                    print '<span class="accion">(<a href="' . $_SERVER['PHP_SELF'] . '?accion=borrarlista&reparto=' . $reparto['id'] . '">Borrar</a>)</span>';
                    print '<ul>';
                    // Cogemos de la lista de reparto las tareas de envío
                    $envios = $apitareas->tasks->listTasks($reparto['id']);

                    // Por si no hay tareas de envío en la lista
                    if (!empty($envios['items'])) {
                        foreach ($envios['items'] as $envio) {
                            // Creamos un elemento para cada una de las tareas de envío
                            $idenvio = "'" . $envio['id'] . "'";
                            print '<li title="' . $envio['notes'] . '" id="' . $idenvio . '">' . $envio['title'] . ' (' . $envio['notes'] . ')';
                            $coordenadas = "'" . $envio['notes'] . "'";
                            print '<span class="accion">  (<a href="#" onclick="abrirMaps(' . $coordenadas . ');">Ver mapa</a>)</span>';
                            print '<span class="accion">  (<a href="' . $_SERVER['PHP_SELF'] . '?accion=borrartarea&reparto=' . $reparto['id'] . '&envio=' . $envio['id'] . '">Borrar</a>)</span>';
                            print '</li>';
                        }
                    }
                    print '</ul>';
                    print '</div>';
                }
                ?>
            </div>
            <div class="pie">
                <?php print $error; ?>
            </div>
        </div>
    </body>
</html>
