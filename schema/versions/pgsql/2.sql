begin;

insert into ttirc_prefs (pref_name,type_id,def_value,short_desc,section_id) values('NOTIFY_ON', 2, '', '', 1);

update ttirc_version set schema_version = 2;

commit;

