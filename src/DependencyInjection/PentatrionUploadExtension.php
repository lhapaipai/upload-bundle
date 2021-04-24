<?php
namespace Pentatrion\UploadBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PentatrionUploadExtension extends Extension
{
  public function load(array $configs, ContainerBuilder $container)
  {
    $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('services.yaml');

    $configuration = $this->getConfiguration($configs, $container);
    $config = $this->processConfiguration($configuration, $configs);

    $webRoot = $container->getParameter('kernel.project_dir').'/public';
    $origins = [];
    if (null !== $config['origins']) {
      foreach ($config['origins'] as $key => $origin) {
        if (strpos($origin['path'], $webRoot) === 0) {
          $origin['webPrefix'] = substr($origin['path'], strlen($webRoot));
        }
        $origins[$key] = $origin;
      }
    }
    $container->setParameter('pentatrion_upload.origins', $origins);

    if (null !== $config['file_infos_helper']) {
      $container->setAlias('Pentatrion\UploadBundle\Service\FileInfosHelperInterface', $config['file_infos_helper']);
    }
  }
}