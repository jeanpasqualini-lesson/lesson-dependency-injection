<?php
namespace tests\Loader;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * StringLoader
 *
 * @author Jean Pasqualini <jpasqualini75@gmail.com>
 */
class StringLoader
{
    protected $containerBuilder;

    public function __construct(ContainerBuilder $containerBuilder)
    {
        $this->containerBuilder = $containerBuilder;
    }

    protected function getLoaderClass($type)
    {
        switch($type)
        {
            case 'yaml':
                return YamlFileLoader::class;
            break;

            case 'xml':
                return XmlFileLoader::class;
            break;
        }
    }

    public function load($config, $type)
    {
        switch($type)
        {
            case 'yaml':
            case 'xml':
                $filename = tempnam(sys_get_temp_dir(), $type);
                file_put_contents($filename, $config);
                $loaderClass = $this->getLoaderClass($type);
                $yamlLoader = new $loaderClass($this->containerBuilder, new FileLocator(sys_get_temp_dir()));
                $yamlLoader->load($filename);
                break;
            default:
                throw new \Exception('loader '.$type.' is not registered');
                break;
        }
    }
}