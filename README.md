Guest (module for Omeka S)
==========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Guest] is a module for [Omeka S] that creates a role called `guest`, and
provides configuration options for a login and registration screen. Guests
become registered users in Omeka S, but have no other privileges to the admin
side of your Omeka S installation. This module is thus intended to be a common
module that other modules needing a guest user use as a dependency.

All features are available from api, in particular to login, to logout, to
register, to update the password, to update its own profile and settings, or to
ajax forms. It does not replace the standard api (/api/users/#id), but adds some
checks and features useful to build ajax dialogs.

If you need more similar roles, you may use the module [Guest Private], that
provides two more roles: `guest_private_site`, who can see all sites, that are
public or private, but not private pages or resources; `guest_private`, who can
see all sites, site pages and resources that are public or private. Like
`guest`, they don't have admin permission and cannot go to the admin board.

The module is compatible with module [User Names] and [Two Factor Authentication].

The module includes a way to request api without credentials but via session, so
it's easier to use ajax in public interface or web application (see [omeka/pull/1714]).
The feature is included in Omeka S version 4.1.


Installation
------------

### Installation

See general end user documentation for [installing a module].

The module [Common] must be installed first.

* From the zip

Download the last release [Guest.zip] from the list of releases, and uncompress
it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Guest`.

Then install it like any other Omeka module and follow the config instructions.

### Upgrade from module Guest User

The automatic upgrade from module [GuestUser], for data, settings and theme
templates, was removed in version 3.4.21. To upgrade from it when it is
installed, it is recommended to upgrade it first to version 3.3.5.1 or higher,
or to disable it. See [more information to upgrade templates] and code in other
files of [version 3.4.20].


Usage
-----

### Guest login form

A guest login form is provided in `/s/my_site/guest/login`.

### Guest register form

A guest login form is provided in `/s/my_site/guest/register`.

### Guest blocks for login and register

Site page blocks "Login" and "Register" are available too and can be added on any page.

### Terms agreement

A check box allows to force guests to accept terms agreement.

A button in the config forms allows to set or unset all guests acceptation,
in order to allow update of terms.

### Option redirect after login

When the module [Shibboleth] is used, this option is bypassed.

### Custom theme: Main login form

In some cases, you may want to use the same login form for all users, so you may
have to adapt it. You may use the navigation link too (in admin > sites > my-site > navigation).

```php
<?php
if ($this->identity()):
    echo $this->hyperlink($this->translate('Logout'), $this->url()->fromRoute('site/guest/guest', ['site-slug' => $site->slug(), 'action' => 'logout']), ['class' => 'logout']);
else:
    echo $this->hyperlink($this->translate('Login'), $this->url()->fromRoute('site/guest/anonymous', ['site-slug' => $site->slug(), 'action' => 'login']), ['class' => 'login']);
endif;
```

Api endpoint
------------

First, specify the roles that can login by api in the config form of the module.
Note that to allow any roles to login, in particular global admins, increase the
access points to check for security.

The main path is `/api/guest[/:action]` with all following actions that output
data as [jSend]:

- me
  Get infos about me (similar to /api/users/#id).

- login
  The user can log in with a post to `/api/login` with data `{"email":"elisabeth.ii@example.com","password"=""***"}`.
  In return, a session token will be returned. All other actions can be done
  with them: `/api/users/me?key_identity=xxx&key_credential=yyy`.

  If the option to create a local session cookie is set, the user will be
  authenticated locally too, so it allows to log in from a third party webservice,
  for example if a user logs in inside a Drupal site, he can log in the Omeka
  site simultaneously. This third party login should be done via an ajax call
  because the session cookie should be set in the browser, not in the server, so
  you can’t simply call the endpoint from the third party server. In you third
  party ajax, the header `Origin` should be filled in the request; this is
  generally the case with common js libraries.

  When a local session cookie is wanted, it is recommended to add a list of
  sites that have the right to log in the config for security reasons.

- logout

- session-token
  A session token can be created for api access. It is reset each time the user
  login or logout. The api keys have no limited life in Omeka.

- register
  A visitor can register too, if allowed in the config. Register requires an
  email. Other params are optional: `username`, `password`, and `site` (id or
  slug, that may be required via the config).

- forgot-password
  Requires the eamail as argument.

- dialog
  Get an html dialog for ajax interaction. The argument is "name" with values
  "login", "register", "forgot-password", or "2fa-token" (for module [Two Factor Authentication]).

Some specific paths are added. They works the same than /api/guest/:action, but
the output is slightly different. They are kept for compatibility with old
third-party tools. Furthermore, they use key_identity/key_credential to
authenticate.

- /api/login
- /api/logout
- /api/session-token
- /api/register
- /api/forgot-password
- /api/users/me

The path `/api/users/me` is used for the user that is currently authenticated
through the credentials arguments. This is a shortcut to `/api/users/{id}` for
common actions, in particular to get the current user own data without knowking
the id.

Other http methods are available to update the profile with the same path. For
example to update (http method `PATCH`/`POST`):
- email: /api/users/me?email=elisabeth.ii@example.com
- name: /api/users/me?name=elisabeth_ii
- password: /api/users/me?password=xxx&new_password=yyy

In all other cases, you should use the standard api (`/api/users/#id`).


TODO
----

- [x] Move pages to a standard page, in particular register page (see module [ContactUs]).
- [x] Normalize all api routes and json for rest api (register, login, logout, session-token, forgot-password, dialog).
- [ ] Add an option to use key_identity/key_credential with GuestApiController and deprecate ApiController?


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


License
-------

This plugin is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Copyright
---------

* Copyright Biblibre, 2016-2017
* Copyright Daniel Berthereau, 2017-2025 (see [Daniel-KM] on GitLab)

This module was based on a full rewrite of the plugin [Guest User] for [Omeka Classic]
by [BibLibre].


[Guest]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest
[Guest User]: https://gitlab.com/omeka/plugin-GuestUser
[Omeka S]: https://www.omeka.org/s
[GitLab]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest
[Guest Private]: https://gitlab.com/Daniel-KM/Omeka-S-module-GuestPrivate
[User Names]: https://github.com/ManOnDaMoon/omeka-s-module-UserNames
[Two Factor Authentication]: https://gitlab.com/Daniel-KM/Omeka-S-module-TwoFactorAuth
[omeka/pull/1714]: https://github.com/omeka/omeka-s/pull/1714
[ContactUs]: https://gitlab.com/Daniel-KM/Omeka-S-module-ContactUs
[Shibboleth]: https://gitlab.com/Daniel-KM/Omeka-S-module-Shibboleth
[jSend]: https://github.com/omniti-labs/jsend
[more information to upgrade templates]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest/-/blob/9964d30a65505975c4dd1af42eccbc001a02a4b9/Upgrade_from_GuestUser.md
[version 3.4.20]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest/-/tree/3.4.20
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[modules/Guest/data/scripts/convert_guest_user_templates.sh]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest/blob/master/data/scripts/convert_guest_user_templates.sh
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Guest/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GuestUser]: https://github.com/biblibre/omeka-s-module-GuestUser
[Omeka Classic]: https://omeka.org
[BibLibre]: https://github.com/biblibre
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
