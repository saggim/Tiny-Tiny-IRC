SET NAMES utf8;
SET CHARACTER SET utf8;

drop table if exists ttirc_user_prefs;
drop table if exists ttirc_settings_profiles;
drop table if exists ttirc_prefs;
drop table if exists ttirc_prefs_sections;
drop table if exists ttirc_prefs_types;
drop table if exists ttirc_messages;
drop table if exists ttirc_channels;
drop table if exists ttirc_servers;
drop table if exists ttirc_connections;
drop table if exists ttirc_users;
drop table if exists ttirc_sessions;
drop table if exists ttirc_version;
drop table if exists ttirc_system;

create table ttirc_system(id integer not null primary key auto_increment,
	param varchar(120) not null,
	value text) TYPE=InnoDB;

insert into ttirc_system (param, value) values ('MASTER_RUNNING', '');
insert into ttirc_system (param, value) values ('MASTER_HEARTBEAT', '');

create table ttirc_users (id integer not null primary key auto_increment,
	login varchar(120) not null unique,
	pwd_hash varchar(250) not null,
	last_login datetime default null,
	access_level integer not null default 0,
	email varchar(250) not null,
	nick varchar(120) not null,
	heartbeat datetime default null,
	quit_message varchar(120) not null default '',
	realname varchar(120) not null,
	created datetime default null) TYPE=InnoDB;

insert into ttirc_users (login,pwd_hash,access_level, nick, realname, email, quit_message) values ('admin', 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 10, 'test', 'Admin User', 'test@localhost', 'My hovercraft is full of eels');

create table ttirc_connections(id integer not null primary key auto_increment,
	title varchar(120) not null,
	active_nick varchar(120) not null default '',
	enabled boolean not null default false,
	active boolean not null default false,
	permanent boolean not null default false,
	active_server varchar(120) not null default '',
	encoding varchar(120) not null default 'UTF-8',
	status integer not null default 0,
	autojoin text not null,
	auto_connect boolean not null default false,
	visible boolean not null default true,
	userhosts text not null,
	connect_cmd text not null,
	nick varchar(120) not null default '',
	last_sent_id integer not null default 0,
	owner_uid integer not null references ttirc_users(id) ON DELETE CASCADE) TYPE=InnoDB;

insert into ttirc_connections (title,owner_uid,autojoin,encoding,auto_connect,userhosts,connect_cmd) values ('GBU', 1, '#test', 'koi8-r', true, '', '');

create table ttirc_servers(id integer not null primary key auto_increment,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE,
	server varchar(120) not null,
	port integer not null) TYPE=InnoDB;

insert into ttirc_servers (connection_id, server, port) values (1, 'irc.volgo-balt.ru', 6667);

create table ttirc_channels(id integer not null primary key auto_increment,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE,
	channel varchar(120) not null,
	topic text not null,
	topic_owner varchar(120) not null default '',
	topic_set datetime not null,
	chan_type integer not null default 0,
	nicklist text not null) TYPE=InnoDB;

create table ttirc_messages(id integer not null primary key auto_increment,
	ts datetime not null,
	incoming boolean not null,
	message text not null,
	message_type integer not null default 0,
	sender varchar(120) not null,
	channel varchar(120) not null,
	connection_id integer not null references ttirc_connections(id) ON DELETE CASCADE) TYPE=InnoDB;

create table ttirc_prefs_types (id integer not null primary key, 
	type_name varchar(100) not null) TYPE=InnoDB;

insert into ttirc_prefs_types (id, type_name) values (1, 'bool');
insert into ttirc_prefs_types (id, type_name) values (2, 'string');
insert into ttirc_prefs_types (id, type_name) values (3, 'integer');

create table ttirc_prefs_sections (id integer not null primary key, 
	section_name varchar(100) not null) TYPE=InnoDB;

insert into ttirc_prefs_sections (id, section_name) values (1, 'General');
insert into ttirc_prefs_sections (id, section_name) values (2, 'Interface');
insert into ttirc_prefs_sections (id, section_name) values (3, 'Advanced');

create table ttirc_prefs (pref_name varchar(250) not null primary key,
	type_id integer not null references ttirc_prefs_types(id),
	section_id integer default 1 not null references ttirc_prefs_sections(id),
	short_desc text not null,
	help_text text not null,
	access_level integer not null default 0,
	def_value text not null) TYPE=InnoDB;

insert into ttirc_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('USER_THEME', 2, '0', '', 1, '');

insert into ttirc_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('USER_STYLESHEET', 2, '', '', 2, '');

insert into ttirc_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('HIGHLIGHT_ON', 2, '', '', 1, '');

create table ttirc_settings_profiles(id integer not null primary key auto_increment,
	title varchar(250) not null,
	owner_uid integer not null references ttirc_users(id) on delete cascade) TYPE=InnoDB;

create table ttirc_user_prefs (
	owner_uid integer not null references ttirc_users(id) ON DELETE CASCADE,
	pref_name varchar(250) not null references ttirc_prefs(pref_name) ON DELETE CASCADE,
	profile integer references ttirc_settings_profiles(id) ON DELETE CASCADE,
	value text not null) TYPE=InnoDB;

create index ttirc_user_prefs_owner_uid_index on ttirc_user_prefs(owner_uid);

create table ttirc_sessions (id varchar(250) unique not null primary key,
	data text,	
	expire integer not null) TYPE=InnoDB;

create index ttirc_sessions_expire_index on ttirc_sessions(expire);

create table ttirc_version (schema_version integer not null) TYPE=InnoDB;

insert into ttirc_version values (2);

