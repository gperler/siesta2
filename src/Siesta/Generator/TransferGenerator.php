<?php

declare(strict_types=1);

namespace Siesta\Generator;

use Nitria\ClassGenerator;
use Siesta\Model\Entity;

/**
 * @author Gregor Müller
 */
class TransferGenerator extends AbstractGenerator
{

    /**
     * @var Entity
     */
    protected Entity $entity;

    /**
     * @var ClassGenerator
     */
    protected ClassGenerator $classGenerator;

    /**
     * @var string
     */
    protected string $basePath;

    /**
     * @param Entity $entity
     * @param string $baseDir
     */
    public function generate(Entity $entity, string $baseDir): void
    {
        $this->entity = $entity;
        $this->basePath = $baseDir;
        $this->classGenerator = new ClassGenerator($this->entity->getTransferClassName());

        $this->generateServiceEntity();
        $this->saveServiceEntity();
    }


    /**
     *
     */
    protected function saveServiceEntity(): void
    {
        $targetFile = $this->getTargetFile($this->entity, $this->entity->getTransferClassShortName());
        $this->classGenerator->writeToFile($targetFile);
    }

    /**
     *
     */
    protected function generateServiceEntity(): void
    {

        foreach ($this->getUseClassNameList($this->entity) as $useClass) {
            $this->classGenerator->addUsedClassName($useClass);
        }

        foreach ($this->getImplementedInterfaceList() as $interfaceName) {
            $this->classGenerator->addImplements($interfaceName);
        }

        foreach ($this->pluginList as $plugin) {
            $plugin->generate($this->entity, $this->classGenerator);
        }
    }

}