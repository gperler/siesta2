<?php
declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Transfer;

use Nitria\ClassGenerator;
use Siesta\CodeGenerator\GeneratorHelper;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\Model\Collection;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;

class CollectorGetterSetterPlugin extends BasePlugin
{
    const METHOD_ADD_TO_PREFIX = "addTo";

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getUseClassNameList(Entity $entity): array
    {
        $useList = [];
        foreach ($entity->getCollectionList() as $collection) {
            $foreignEntity = $collection->getForeignEntity();
            $useList[] = $foreignEntity->getTransferClassName();
        }
        return $useList;
    }

    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);

        foreach ($this->entity->getCollectionList() as $collection) {
            $this->generateCollectionGetter($collection);
            $this->generateAddToCollection($collection);
        }

    }

    /**
     * @param Collection $collection
     */
    protected function generateCollectionGetter(Collection $collection): void
    {
        $methodName = 'get' . $collection->getMethodName();
        $name = $collection->getName();
        $foreignEntity = $collection->getForeignEntity();

        $method = $this->classGenerator->addPublicMethod($methodName);
        $helper = new GeneratorHelper($method);
        $method->setReturnType($foreignEntity->getTransferClassName() . '[]');

        // return collection
        $method->addCodeLine('return $this->' . $name . ';');
    }


    /**
     * @param Collection $collection
     */
    protected function generateAddToCollection(Collection $collection): void
    {
        $foreignEntity = $collection->getForeignEntity();
        $foreignClass = $foreignEntity->getTransferClassName();
        $methodName = self::METHOD_ADD_TO_PREFIX . $collection->getMethodName();

        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter($foreignClass, 'transfer');

        // add reference to entity
        $reference = $collection->getForeignReference();
        $method->addCodeLine('$transfer->set' . $reference->getMethodName() . '($this);');

        // check if collection is already array
        $member = '$this->' . $collection->getName();
        $method->addIfStart($member . ' === null');
        $method->addCodeLine($member . ' = [];');
        $method->addIfEnd();

        // add entity to collection
        $method->addCodeLine($member . '[] = $transfer;');
    }


}