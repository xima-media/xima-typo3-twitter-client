create table tx_ximatwitterclient_domain_model_account (
	username      varchar(255) not null default '',
	image         int(11) unsigned default '0' not null,
	fetch_type    varchar(255) not null default '',
	fetch_options varchar(255) not null default '',
	max_results   int(11) unsigned default '0' not null,
);

create table tx_ximatwitterclient_domain_model_tweet (
	text text,
);