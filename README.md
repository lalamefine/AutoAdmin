Description
================
This bundle generates admin pages automatically with no need for configuration.
You can manage your Doctrine entities (list, create, edit, delete, associations included) with a simple and clean interface that makes use of `__toString()` methods.

It is a no-dependency bundle and it only includes htmx.

Installation
================
1. Run `composer require lalamefine/autoadmin`
2. Add the bundle in `config/bundles.php` if not done automatically:
    ```php
    return [
        ...
        Lalamefine\Autoadmin\LalamefineAutoadminBundle::class => ['all' => true], // add this line
    ];
    ```
3. Add the route in `config/routes/autoadmin.yaml`:
    ```yaml
    app_file:
        resource: '@LalamefineAutoadminBundle/config/routes.yaml'
        prefix: autoadmin
    ```
    You can change the prefix if you want (e.g. `admin`).

Security Warning
================
You **NEED** to configure security at routing level <br>
Recommended: Restrict `/autoadmin` (or your custom prefix) to a specific user role in `security.yaml`.

License
================
This bundle is distributed under the LGPL-3.0-or-later license.
See the LICENSE file for more details.
