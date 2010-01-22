drop table ttirc_messages;
drop table ttirc_preset_destinations;
drop table ttirc_destinations;
drop table ttirc_servers;
drop table ttirc_connections;
drop table ttirc_users;
drop table ttirc_sessions;
drop table ttirc_version;
drop table ttirc_system;
drop function SUBSTRING_FOR_DATE(timestamp, int, int);

create table ttirc_system(id serial not null primary key,
	key varchar(120) not null,
	value text);

insert into ttirc_system (key, value) values ('MASTER_RUNNING', '');
insert into ttirc_system (key, value) values ('MASTER_HEARTBEAT', '');

create table ttirc_users (id serial not null primary key,
	login varchar(120) not null unique,
	pwd_hash varchar(250) not null,
	last_login timestamp default null,
	access_level integer not null default 0,
	email varchar(250) not null,
	nick varchar(120) not null,
	heartbeat timestamp not null default NOW(),
	quit_message varchar(120) not null default '',
	realname varchar(120) not null,
	created timestamp default null);

insert into ttirc_users (login,pwd_hash,access_level, nick, realname, email, quit_message) values ('admin', 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 10, 'test', 'Admin User', 'test@localhost', 'My hovercraft is full of eels');

create table ttirc_connections(id serial not null primary key,
	title varchar(120) not null,
	active_nick varchar(120) not null default '',
	enabled boolean not null default true,
	active_server varchar(120) not null default '',
	status integer not null default 0,
	last_sent_id integer not null default 0,
	owner_uid integer not null references ttirc_users(id) ON DELETE CASCADE);

insert into ttirc_connections (title,owner_uid) values ('GBU', 1);

create table ttirc_servers(id serial not null primary key,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE,
	server varchar(120) not null,
	encoding varchar(120) not null,
	port integer not null);

insert into ttirc_servers (connection_id, server, encoding, port) values (1, 'irc.volgo-balt.ru', 'koi8-r', 6667);

create table ttirc_destinations(id serial not null primary key,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE,
	destination varchar(120) not null,
	topic text not null default '',
	topic_owner varchar(120) not null default '',
	topic_set timestamp not null default NOW(),
	nicklist text not null default '');

create table ttirc_preset_destinations(id serial not null primary key,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE,
	destination varchar(120) not null);

insert into ttirc_preset_destinations (connection_id, destination) values (1, '#test');

create table ttirc_messages(id serial not null primary key,
	ts timestamp not null default NOW(),
	incoming boolean not null,
	message text not null,
	message_type integer not null default 0,
	sender varchar(120) not null,
	destination varchar(120) not null,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE);
	
create table ttirc_sessions (id varchar(250) unique not null primary key,
	data text,	
	expire integer not null);

create index ttirc_sessions_expire_index on ttirc_sessions(expire);

create function SUBSTRING_FOR_DATE(timestamp, int, int) RETURNS text AS 'SELECT SUBSTRING(CAST($1 AS text), $2, $3)' LANGUAGE 'sql';

create table ttirc_version (schema_version int not null);

insert into ttirc_version values (1);

