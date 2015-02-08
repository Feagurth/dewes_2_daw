<?php

/*
 * Copyright (C) 2014 Luis Cabrerizo Gómez
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once './db.php';

/**
 * Método para verificar los datos introducidos por el usuario
 * @param type $tipodoc Tipo de documento
 * @param type $fentrada Fecha de entrada
 * @param type $remit Remitente
 * @param type $dest Destinatario
 * @return int Devuelve 
 * 0 si la validación es correcta
 * 1 si hay un error en el remitente
 * 1 si hay un error en el destinatario
 * 3 si hay un error en el tipo de documento
 * 4 si hay un error en la fecha
 * 5 si el remitente está vacío
 * 6 si el destinatario está vacío
 * 7 si el tipo de documento está vacío
 */
function validarDatos($tipodoc, $fentrada, $remit, $dest) {
    // Inicializamos la variable de salida al valor que tendría si 
    // toda la validación fuese correcta
    $validacion = 0;

    // Verificamos con expresiones regulares que los caracteres 
    // introducidos para el remitente son los permitidos
    if (!preg_match("/^[0-9a-zA-ZñÑáÁéÉíÍóÓúÚ ]+$/", $remit)) {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida
        $validacion = 1;
    }

    // Verificamos con expresiones regulares que los caracteres 
    // introducidos para el destinatario son los permitidos
    if (!preg_match("/^[0-9a-zA-ZñÑáÁéÉíÍóÓúÚ ]+$/", $dest)) {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida                
        $validacion = 2;
    }

    // Verificamos con expresiones regulares que los caracteres 
    // introducidos para el tipo de documento son los permitidos           
    if (!preg_match("/^[0-9a-zA-ZñÑáÁéÉíÍóÓúÚ ]+$/", $tipodoc)) {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida                
        $validacion = 3;
    }

    // Verificamos que la fecha de entrada no sea superior a la fecha 
    // actual puesto que no deberían permitirse la entrada de documentos 
    // con dias posteriores al actual. No se puede registrar documentos 
    // que aún no han llegado a la oficina
    if ($fentrada > date('Y-m-d')) {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida                
        $validacion = 4;
    }

    // Verificamos que el remitente no esté vacío
    if ($remit == "") {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida                                
        $validacion = 5;
    }

    // Verificamos que el destinatario no esté vacío
    if ($dest == "") {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida                                
        $validacion = 6;
    }

    // Verificamos que el tipo de documento no esté vacío
    if ($tipodoc == "") {
        // Si la validación no se cumple, asignamos el valor 
        // correspondiente a la variable de salida                                
        $validacion = 7;
    }

    // Devolvemos la variable con el resultado de la validación
    return $validacion;
}

/**
 * Función que calcula el periodo actual a partir de la fecha del sistema
 * @return type El periodo actual
 */
function calcularPeriodoActual() {

    // Recuperamos la fecha actual del sistema
    $fecha = getdate();

    // Comprobamos el mes actual, si es mayor o igual a 9 (Septiembre) indica 
    // un nuevo periodo
    if ($fecha['mon'] >= 9) {

        // Para periodos con mes igual o superior a Septiembre, devolvemos el 
        // año actual y el siguiente formateados
        return $fecha['year'] . "/" . ($fecha['year'] + 1) . "-";
    } else {
        // Para periodos con mes inferior a Septiembre, devolvemos el año 
        // anterior y el actual formateados
        return ($fecha['year'] - 1) . "/" . ($fecha['year']) . "-";
    }
}

/**
 * Función que nos permite generar un número de registro para cada entrada
 * @param type $bd Instancia de la base de datos donde están los 
 * registros
 * @param type $tipo E si es un número de registro de entrada y S si es un 
 * número de registro de salida
 * @return string El número de registro generado
 */
function calcularNreg($tipo) {

    // Realizamos una consulta para recuperar el número de registro más alto 
    // que haya en la tabla de entradas de la base de datos    

    $db = new DB();

    $nreg = $db->calcularNReg($tipo);

    $periodoActual = calcularPeriodoActual();

    // Quitamos los 4 primeros caracteres del número recuperado, que 
    // corresponden con el año y las comparamos con el año actual del 
    // sistema
    if (substr($nreg, 0, 10) == $periodoActual) {
        // Si son iguales, quitamos los caracteres del año, convertimos 
        // la cadena resultante a entero y le sumamos 1.
        $nreg = ((int) substr($nreg, 10)) + 1;
    } else {
        // Si son distintos, asignamos 1 a la variable
        $nreg = 1;
    }

    // Finalmente cogemos el año actual del sistema y le concatenamos 
    // el número calculado formateado a 2 cifras con ceros en caso de 
    // tener un sólo dígito, consiguiendo de este modo el número de 
    // registro siguiente al más alto de la base de datos
    $nreg = $periodoActual . sprintf('%1$03d', $nreg);

    // Finalmente devolvemos el valor generado
    return $nreg;
}

/**
 * Función que nos permite reordenar los ficheros subidos al servidor y 
 * alojados en $_FILES dandoles una estructura más comoda para procesarlos
 * @param type $ficheros Los ficheros alojados en $_FILES
 * @return type Un array con la información de los ficheros ordenada por fichero
 */
function ordenarFicheros($ficheros) {



    // Creamos un nuevo array para almacenar los datos y devolverlos 
    // posteriormente
    $salida = array();

    // Comprobamos y almacenamos el número de ficheros que se han subido
    $cuenta = count($ficheros['addfile']['name']);

    // Recuperamos las claves del array de ficheros
    $claves = array_keys($ficheros['addfile']);

    // Iteramos tantas veces como ficheros haya
    for ($i = 0; $i < $cuenta; $i++) {

        // Iteramos por todas las claves que hay en el array de entrada
        foreach ($claves as $clave) {

            // Asignamos al fichero de salida cada uno de las claves del 
            // array de entrada para cada iteración de ficheros
            $salida[$i][$clave] = $ficheros['addfile'][$clave][$i];
        }
    }

    // Finalmente devolvemos el resultado
    return $salida;
}

/**
 * Función que nos crea un array para realizar el menú de la página principal
 * @return string Un array multidimensional con la estructura del menú
 */
function crearMenu() {
    // Creamos un array para representar el menú desplegable
    $submenu0[0]["navegacion"] = "1";
    $submenu0[0]["titulo"] = "Nueva Entrada";
    $submenu0[1]["navegacion"] = "2";
    $submenu0[1]["titulo"] = "Ver Entradas";

    $menu[0]["titulo"] = "Entradas";
    $menu[0]["submenu"] = $submenu0;

    $submenu1[0]["navegacion"] = "3";
    $submenu1[0]["titulo"] = "Nueva Salida";
    $submenu1[1]["navegacion"] = "4";
    $submenu1[1]["titulo"] = "Ver Salidas";

    $menu[1]["titulo"] = "Salidas";
    $menu[1]["submenu"] = $submenu1;

    // Devolvemos el menú creado
    return $menu;
}

function crearObjetosInserccion(&$registro, array &$ficheros, $tipoRegistro) {

    // Si es una insercción, volcamos los valores a insertar en 
    // variables directamente desde el POST de la página
    $nreg = $_POST['nreg'];
    $tipodoc = $_POST['tipodoc'];
    $fecha = $_POST['fecha'];
    $remit = $_POST['remit'];
    $dest = $_POST['dest'];

    // Hay que verificar si el POST trae valores para el checkbox 
    // de escaneado, puesto que si solo aparece el valor on si el 
    // checkbox ha sido marcado. Si no se ha marcado el checkbox, 
    // el POST no trae información alguna. Le asignamos 1 en caso 
    // de estar marcado y 0 si no lo está
    $esc = (isset($_POST['esc']) ? "1" : "0");

    // Verificamos si está marcado el checkbox
    if ($esc == 1 && isset($_FILES['addfile'])) {

        // Comprobamos si hay información en los ficheros subidos al 
        // servidor y si se ha producido algún error en la subida de 
        // los mismos
        if (isset($_FILES['addfile'])) {

            // Reordenamos los ficheros que hay en $_FILES para que 
            // nos sea más facil trabajar luego con ellos
            $archivos = ordenarFicheros($_FILES);


            // Recorremos todos los archivos para tratarlos
            foreach ($archivos as $file) {

                // Creamos un nuevo objeto fichero
                $fichero = new Fichero();

                // Asignamos 0 como valor para el id_documento 
                // al crear el objeto
                $fichero->setId_documento(0);

                // Le asignamos el nombre
                $fichero->setNombre($file['name']);

                // Le asignamos el tamaño
                $fichero->setTamanyo($file['size']);

                // Le asignamos el tipo
                $fichero->setTipo($file['type']);

                // Recuperamos la información del fichero con la función 
                // fopen especificando 'rb' como parámetro para que lea 
                // el fichero en binario, guardandolo en una variable 
                // tipo stream y lo asignamos al fichero
                $fichero->setDocumento(fopen($file['tmp_name'], 'rb'));

                // Almacenamos el fichero en el array
                $ficheros[] = $fichero;
            }
        }
    }

    $registro = new Registro(array(
        'id' => 0,
        'nreg' => $nreg,
        'tipo_reg' => $tipoRegistro,
        'tipodoc' => $tipodoc,
        'fecha' => $fecha,
        'remit' => $remit,
        'dest' => $dest,
        'esc' => sizeof($ficheros) > 0 ? 1 : 0
    ));
}