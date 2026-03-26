insert into `pages` (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
										 `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `TSconfig`, `module`)
values (1, 0, 'Main', '/', 0, 0, 0, 1, 1, 31, 31, 1, 1, 1, '', ''),
      (2, 1, 'Twitter', '/twitter', 0, 0, 0, 1, 1, 31, 31, 0, 254, 0, '', 'tw');
