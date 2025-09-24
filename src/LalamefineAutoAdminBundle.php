<?php 
namespace Lalamefine\Autoadmin;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class LalamefineAutoadminBundle extends AbstractBundle
{
    
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Check the availability of the file info extension
        if (!extension_loaded('fileinfo')) {
            throw new \RuntimeException('The "fileinfo" PHP extension is required to use the LalamefineAutoAdminBundle.');
        }
        // load an XML, PHP or YAML file
        $container->import('../config/services.yaml');
        
    }
    
}