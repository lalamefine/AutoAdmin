Description
================
This bundle generates admin pages automatically with no need for configuration.
You can manage your Doctrine entities (list, create, edit, delete, associations included) with a simple and clean interface that makes use of `__toString()` methods.
It only works with Single Column Identifiers (required and no composite keys) for now.

Installation
================
1. Run `composer require lalamefine/autoadmin`
2. If not done automatically, add the bundle in `config/bundles.php`:
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
4. __⚠ WARNING__ Configure security to restrict access :

    You need to configure security at routing level <br>
    I do recommand restricting `/autoadmin` (or your custom prefix) to a specific user role in `config/packages/security.yaml`.

License
================
This bundle is distributed under the LGPL-3.0-or-later license.
See the LICENSE file for more details.
