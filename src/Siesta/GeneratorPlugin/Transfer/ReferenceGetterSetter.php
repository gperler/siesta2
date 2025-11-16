<?php

declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Transfer;

use Nitria\ClassGenerator;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\GeneratorPlugin\Entity\MemberPlugin;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;
use Siesta\Model\Reference;

/**
 * @author Gregor Müller
 */
class ReferenceGetterSetter extends BasePlugin
{
    /**
     * @param Entity $entity
     *
     * @return string[]
     */
    public function getUseClassNameList(Entity $entity): array
    {
        $useClassList = [];
        foreach ($entity->getReferenceList() as $reference) {
            $foreignEntity = $reference->getForeignEntity();
            $useClassList[] = $foreignEntity->getTransferClassName();
            $useClassList[] = $foreignEntity->getClassName();
        }

        foreach ($entity->getReferenceList() as $reference) {
            $foreignEntity = $reference->getForeignEntity();
            $serviceFactory = $foreignEntity->getServiceFactoryClass();
            if ($serviceFactory !== null) {
                $useClassList[] = $serviceFactory;
            }
        }

        return $useClassList;
    }

    /**
     * @return string[]
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

        foreach ($entity->getReferenceList() as $reference) {
            $this->generateReferenceGetter($reference);
            $this->generateReferenceSetter($reference);
        }
    }

    /**
     * @param Reference $reference
     * @return void
     */
    protected function generateReferenceGetter(Reference $reference): void
    {
        $foreignEntity = $reference->getForeignEntity();
        $methodName = 'get' . $reference->getMethodName();
        $memberName = '$this->' . $reference->getName();

        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->setReturnType($foreignEntity->getTransferClassName(), true);
        $method->addCodeLine('return ' . $memberName . ';');
    }


    /**
     * @param Reference $reference
     */
    protected function generateReferenceSetter(Reference $reference): void
    {
        $name = $reference->getName();
        $methodName = 'set' . $reference->getMethodName();
        $foreignEntity = $reference->getForeignEntity();

        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter($foreignEntity->getTransferClassName(), 'transfer', null, null, true);

        $method->addCodeLine('$this->' . $name . ' = $transfer;');

        foreach ($reference->getReferenceMappingList() as $referenceMapping) {
            $localAttribute = $referenceMapping->getLocalAttribute();
            $foreignAttribute = $referenceMapping->getForeignAttribute();
            $method->addCodeLine('$this->' . $localAttribute->getPhpName() . ' =  $transfer?->get' . $foreignAttribute->getMethodName() . '();');
        }
    }

}