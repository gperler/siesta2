<?php
declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Transfer;

use Nitria\ClassGenerator;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\GeneratorPlugin\Entity\MemberPlugin;
use Siesta\Model\Entity;

/**
 * @author Gregor Müller
 */
class ConstructorPlugin extends BasePlugin
{

    /**
     * @return array
     */
    public function getDependantPluginList(): array
    {
        return [
            MemberPlugin::class,
        ];
    }

    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);

        $method = $this->classGenerator->addConstructor();
        $method->addParameter("array", "data", "null");

        foreach ($this->entity->getCollectionList() as $collection) {
            $method->addCodeLine('$this->' . $collection->getName() . ' = [];');
        }


        $method->addCodeLine('$this->' . FromArrayPlugin::METHOD_FROM_ARRAY . '($data);');
    }

}