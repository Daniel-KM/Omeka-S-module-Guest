Upgrade from module GuestUser
=============================

## Upgrade of data

The upgrade from the module [GuestUser] is automatic from the version is `3.3.5`
or higher. Simply install the module Guest and a check will be done during
install. If the version is lower, the module won’t install unless the module is
upgraded or disabled first.

If the version is good, the module will copy the original database table and
will copy all the settings.

The two modules can work alongside, since they don’t use the same routing.
Nevertheless, it's recommended to keep only one of them.

## Upgrade of templates of the themes

If the theme used in your site wasn’t customized, there is nothing to do and the
default views will be used.

Else, you have to rename them and to replace some strings in all files.

### Backup your files

Don’t forget to save your files.

### Manage files

- First, the main directory `view/guest-user/site/guest-user` should be copied
  as `view/guest/site/guest`.
- Second, create a directory `view/guest/site/anonymous`.
- Third, move the files `auth-error.phtml`, `confirm.phtml`, `forgot-password.phtml`,
  `login.phtml`, and `register.phtml` from `view/guest/site/guest` to `view/guest/site/anonymous`.

### Update strings

To update strings, you can use the commands below or do a "Search and replace"
in your favorite editor, on all files of the customized themes, in the following
order.
Warning: replacements are case sensitive, so check the box if needed.

- replace "guest-user" by "guest"
- replace "guestuser" by "guest"
- replace "guestUser" by "guest"
- replace "guest user" by "guest"
- replace "GuestUser" by "Guest"
- replace "Guest User" by "Guest"
- replace "Guest user" by "Guest"

For the routing and the path, anonymous visitors and guest users are now
separated, so the routes should be checked too.

- Replace `('site/guest', ['action' =>'register']` by `('site/guest/anonymous', ['action' => 'register>
- Replace `('site/guest', ['action' =>'login']` by `('site/guest/anonymous', ['action' => 'login']`
- Replace `('site/guest', ['action' =>'confirm']` by `('site/guest/anonymous', ['action' => 'confirm']`
- Replace `('site/guest', ['action' =>'auth-error']` by `('site/guest/anonymous', ['action' => 'auth-e>
- Replace `('site/guest', ['action' =>'forgot-password']` by `('site/guest/anonymous', ['action' => 'f>

- Replace `('site/guest', ['action' =>'accept-terms']` by `('site/guest/guest', ['action' => 'accept-t>
- Replace `('site/guest', ['action' =>'update-account']` by `('site/guest/guest', ['action' => 'update>
- Replace `('site/guest', ['action' =>'update-email']` by `('site/guest/guest', ['action' => 'update-e>
- Replace `('site/guest', ['action' =>'logout']` by `('site/guest/guest', ['action' => 'logout']`

### Automatic process from a Linux command

By command under Linux, run the file [modules/Guest/data/scripts/convert_guest_user_templates.sh]
from the root of Omeka.

After checking, in particular when there are many other customized files, you
can remove the old module Guest User and the directory `view/guest-user` in each
theme.


[GuestUser]: https://github.com/biblibre/omeka-s-module-Guest
[modules/Guest/data/scripts/convert_guest_user_templates.sh]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest/blob/master/data/scripts/convert_guest_user_templates.sh

