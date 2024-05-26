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
			$stmt = $db->prepare('INSERT INTO Usuario (uname, email, password) VALUES (?, ?, ?)');
			$R = $stmt->execute([$jsB['uname'], $jsB['email'], $hashed_password]);
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
			$stmt = $db->prepare('SELECT id, password FROM Usuario WHERE uname = ?');
			$stmt->execute([$jsB['uname']]);
			$R = $stmt->fetchAll();
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
			$stmt = $db->prepare('INSERT INTO LoginAudit (username, success) VALUES (?, ?)');
			$stmt->execute([$jsB['uname'], false]);
			echo '{"R":-4, "msg":"Invalid username or password"}';
			return;
        }

		$stmt = $db->prepare('INSERT INTO LoginAudit (user_id, username, success) VALUES (?, ?, ?)');
		$stmt->execute([$user['id'], $jsB['uname'], true]);

		$T = getToken();

		$stmt = $db->prepare('DELETE FROM AccesoToken WHERE id_Usuario = ?');
		$stmt->execute([$user['id']]);

		$stmt = $db->prepare('INSERT INTO AccesoToken (id_Usuario, token, fecha_creacion) VALUES (?, ?, now())');
		$stmt->execute([$user['id'], $T]);

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
		$max_file_size = 10 * 1024; // 10KB

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
			$stmt = $db->prepare('SELECT id_Usuario FROM AccesoToken WHERE token = ?');
			$stmt->execute([$TKN]);
			$R = $stmt->fetchAll();
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
		$stmt = $db->prepare('INSERT INTO Imagen (name, ruta, id_Usuario) VALUES (?, "img/", ?)');
        $stmt->execute([$jsB['name'], $id_Usuario]);
		
        $stmt = $db->prepare('SELECT max(id) as idImagen FROM Imagen WHERE id_Usuario = ?');
        $stmt->execute([$id_Usuario]);
        $R = $stmt->fetchAll();

		$idImagen = $R[0]['idImagen'];

		$stmt = $db->prepare('UPDATE Imagen SET ruta = ? WHERE id = ?');
        $stmt->execute(['img/'.$idImagen.'.'.$jsB['ext'], $idImagen]);

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
			$stmt = $db->prepare('SELECT id_Usuario FROM AccesoToken WHERE token = ?');
			$stmt->execute([$TKN]);
			$Rt = $stmt->fetchAll();
		} catch (Exception $e) {
			echo '{"R":-2}';
			return;
		}

		if (empty($R)) {
			echo '{"R":-3, "msg":"Token Invalido"}';
			return;
		}
		
		$userId = $R[0]['id_Usuario'];

		// Buscar imagen y enviarla
		try {
			$stmt = $db->prepare('SELECT name, ruta FROM Imagen WHERE id = ? AND id_Usuario = ?');
			$stmt->execute([$idImagen, $userId]);
			$R = $stmt->fetchAll();
		}catch (Exception $e) {
			echo '{"R":-4}';
			return;
		}

		if (empty($R)) {
			echo '{"R":-5, "msg":"Acceso denegado a imagen"}';
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
