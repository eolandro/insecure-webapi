<?php 

//se acceden a las env para la db
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

function getToken(){
	//creamos el objeto fecha y obtuvimos la cantidad de segundos desde el 1ª enero 1970
	$fecha = date_create();
	$tiempo = date_timestamp_get($fecha);
	//vamos a generar un numero aleatorio
	$numero = mt_rand();
	//vamos a generar ua cadena compuesta
	$cadena = ''.$numero.$tiempo;
	// generar una segunda variable aleatoria
	$numero2 = mt_rand();
	// generar una segunda cadena compuesta
	$cadena2 = ''.$numero.$tiempo.$numero2;
	// generar primer hash en este caso de tipo sha1
	$hash_sha1 = sha1($cadena);
	// generar segundo hash de tipo MD5 
	$hash_md5 = md5($cadena2);
	return substr($hash_sha1,0,20).$hash_md5.substr($hash_sha1,20);
}

require 'vendor/autoload.php';
$f3 = \Base::instance();
/*
$f3->route('GET /',
	function() {
		echo 'Hello, world!';
	}
);
$f3->route('GET /saludo/@nombre',
	function($f3) {
		echo 'Hola '.$f3->get('PARAMS.nombre');
	}
);
*/ 
// Registro
/*
 * Este Registro recibe un JSON con el siguiente formato
 * 
 * { 
 *		"uname": "XXX",
 *		"email": "XXX",
 * 		"password": "XXX"
 * }
 * */

$f3->route('POST /Registro',
	function($f3) use ($db_host, $db_user, $db_pass, $db_name) {
		
		$db = new DB\SQL("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('email',$jsB) && array_key_exists('password',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		// TODO validar correo en json
		// TODO Control de error de la $DB
		try {
			$hashed_password = password_hash($jsB['password'], PASSWORD_BCRYPT);
			$R = $db->exec('insert into Usuario values(null,"'.$jsB['uname'].'","'.$jsB['email'].'","'.$hashed_password.'")');
		} catch (Exception $e) {
			echo '{"R":-2}';
			return;
		}
		echo "{\"R\":0,\"D\":".var_export($R,TRUE)."}";
	}
);





/*
 * Este Registro recibe un JSON con el siguiente formato
 * 
 * { 
 *		"uname": "XXX",
 * 		"password": "XXX"
 * }
 * 
 * Debe retornar un Token 
 * */


$f3->route('POST /Login',
	function($f3) use ($db_host, $db_user, $db_pass, $db_name) {
		
		$db = new DB\SQL("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('password',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		// TODO validar correo en json
		// TODO Control de error de la $DB
		try {
			$R = $db->exec('Select id, password from  Usuario where uname ="'.$jsB['uname'].'");');
		} catch (Exception $e) {
			echo '{"R":-2}';
			return;
		}
		if (empty($R)){
			echo '{"R":-3}';
			return;
		}

		//si no es coinciden el uname con el pwd, entonces no inicia sesión
		user = $R[0];
        if (!password_verify($jsB['password'], $user['password'])) {
                echo '{"R":-4, "msg":"Invalid username or password"}';
                return;
        }

		$T = getToken();
		//file_put_contents('/tmp/log','insert into AccesoToken values('.$R[0].',"'.$T.'",now())');
		$db->exec('Delete from AccesoToken where id_Usuario = "'.$R[0]['id'].'";');
		$R = $db->exec('insert into AccesoToken values('.$R[0]['id'].',"'.$T.'",now())');
		echo "{\"R\":0,\"D\":\"".$T."\"}";
	}
);


/*
 * Este subirimagen recibe un JSON con el siguiente formato
 * 
 * { 
 * 		"token: "XXX"
 *		"name": "XXX",
 * 		"data": "XXX",
 * 		"ext": "PNG"
 * }
 * 
 * Debe retornar codigo de estado
 * */

$f3->route('POST /Imagen',
	function($f3) use ($db_host, $db_user, $db_pass, $db_name) {
		//Directorio
		if (!file_exists('tmp')) {
			mkdir('tmp');
		}
		if (!file_exists('img')) {
			mkdir('img');
		}

		//se agregan variables para tamaño y extensiones
		$allowed_exts = ['png', 'jpg', 'jpeg', 'gif'];
		$max_file_size = 5 * 1024 * 1024; // 5MB

		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('name',$jsB) && array_key_exists('data',$jsB) && array_key_exists('ext',$jsB) && array_key_exists('token',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		
		$db = new DB\SQL("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		//se valida la extensión del archivo
		$ext = strtolower($jsB['ext']);
		if (!in_array($ext, $allowed_exts)) {
				echo '{"R":-1, "msg":"Invalid file type"}';
				return;
		}

		//se verifica que la data sea base64 válida
		$data = $jsB['data'];
		if (!base64_decode($data, true)) {
				echo '{"R":-1, "msg":"Invalid base64 data"}';
				return;
		}

		//se limita el tamaño del archivo
		$decoded_data = base64_decode($data);
		if (strlen($decoded_data) > $max_file_size) {
				echo '{"R":-1, "msg":"File size exceeds limit"}';
				return;
		}

		// Validar si el usuario esta en la base de datos
		$TKN = $jsB['token'];
		
		try {
			$R = $db->exec('select id_Usuario from AccesoToken where token = "'.$TKN.'"');
		} catch (Exception $e) {
			echo '{"R":-2}';
			return;
		}
		$id_Usuario = $R[0]['id_Usuario'];
		file_put_contents('tmp/'.$id_Usuario,base64_decode($jsB['data']));
		$jsB['data'] = '';
		////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////
		// Guardar info del archivo en la base de datos
		$R = $db->exec('insert into Imagen values(null,"'.$jsB['name'].'","img/",'.$id_Usuario.');');
		$R = $db->exec('select max(id) as idImagen from Imagen where id_Usuario = '.$id_Usuario);
		$idImagen = $R[0]['idImagen'];
		$R = $db->exec('update Imagen set ruta = "img/'.$idImagen.'.'.$jsB['ext'].'" where id = '.$idImagen);
		// Mover archivo a su nueva locacion
		rename('tmp/'.$id_Usuario,'img/'.$idImagen.'.'.$jsB['ext']);
		echo "{\"R\":0,\"D\":".$jsB['name']."}";

	}
);
/*
 * Este Registro recibe un JSON con el siguiente formato
 * 
 * { 
 * 		"token: "XXX",
 * 		"id": "XXX"
 * }
 * 
 * Debe retornar un Token 
 * */


$f3->route('POST /Descargar',
	function($f3) use ($db_host, $db_user, $db_pass, $db_name) {
		
		$db = new DB\SQL("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('token',$jsB) && array_key_exists('id',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		// TODO validar correo en json
		// Comprobar que el usuario sea valido
		$TKN = $jsB['token'];
		$idImagen = $jsB['id'];
		try {
			$R = $db->exec('select id_Usuario from AccesoToken where token = "'.$TKN.'"');
		} catch (Exception $e) {
			echo '{"R":-2}';
			return;
		}
		
		// Buscar imagen y enviarla
		try {
			$R = $db->exec('Select name,ruta from  Imagen where id = '.$idImagen);
		}catch (Exception $e) {
			echo '{"R":-3}';
			return;
		}
		$web = \Web::instance();
		ob_start();
		// send the file without any download dialog
		$info = pathinfo($R[0]['ruta']);
		$web->send($R[0]['ruta'],NULL,0,TRUE,$R[0]['name'].'.'.$info['extension']);
		$out=ob_get_clean();
		//echo "{\"R\":0,\"D\":\"".$T."\"}";
	}
);


$f3->run();


?>
