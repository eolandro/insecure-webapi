/*
###-
create database webapps;
GRANT ALL PRIVILEGES ON webapps.* TO 'udbwebaps'@'localhost' IDENTIFIED BY 'ku>;k8ND4CN4';
FLUSH PRIVILEGES
*/

/*use webapps;*/

create table if not exists Usuario(
	id int AUTO_INCREMENT, 
	uname varchar(50) not null,
	email varchar(250) not null,
	password varchar(250) not null,
	PRIMARY KEY (id)
)ENGINE=InnoDB;

create table if not exists AccesoToken(
	id_Usuario int primary key, 
	token varchar(250) not null,
	fecha datetime not null
)ENGINE=InnoDB;

create table if not exists Imagen(
	id int AUTO_INCREMENT, 
	name varchar(250) not null,
	ruta text not null,
	id_Usuario int not null,
	PRIMARY KEY (id)
)ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS LoginAudit (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT,
	username VARCHAR(50),
	timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	success BOOLEAN,
	FOREIGN KEY (user_id) REFERENCES Usuario(id) ON DELETE SET NULL
)ENGINE=InnoDB;

ALTER TABLE Usuario
ADD CONSTRAINT U_U
Unique (uname,email); 

ALTER TABLE AccesoToken 
ADD CONSTRAINT FK_AT_U
FOREIGN KEY (id_Usuario) REFERENCES Usuario(ID); 

ALTER TABLE Imagen 
ADD CONSTRAINT FK_I_U
FOREIGN KEY (id_Usuario) REFERENCES Usuario(ID); 
