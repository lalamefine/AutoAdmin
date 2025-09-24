HOW to install : 


1. composer require lalamefine/autoadmin
2. Add the bundle in config/bundles.php :
   Lalamefine\Autoadmin\LalamefineAutoAdminBundle::class => ['all' => true],
3. Add the route in config/routes/autoadmin.yaml :
    app_file:
        resource: '@LalamefineAutoAdminBundle/config/routes.yaml'
        prefix: autoadmin
