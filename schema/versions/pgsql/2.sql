begin;

alter table ttirc_system rename column key to param;

update ttirc_version set schema_version = 2;

commit;
