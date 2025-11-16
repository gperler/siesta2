<?php

declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Entity;

use Nitria\ClassGenerator;
use Nitria\Method;
use ReflectionException;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\GeneratorPlugin\ServiceClass\NewInstancePlugin;
use Siesta\Model\Attribute;
use Siesta\Model\Collection;
use Siesta\Model\DBType;
use Siesta\Model\DynamicCollection;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;
use Siesta\Model\Reference;

/**
 * @author Gregor Müller
 */
class FromTransferPlugin extends BasePlugin
{

    const string FROM_TRANSFER = "fromTransfer";

    const string TO_TRANSFER = "toTransfer";

    const array TYPE_ARRAY_ACCESSOR_MAPPING = [
        PHPType::BOOL => "getBooleanValue",
        PHPType::INT => "getIntegerValue",
        PHPType::FLOAT => "getFloatValue",
        PHPType::STRING => "getStringValue",
        PHPType::SIESTA_DATE_TIME => "getDateTime",
        PHPType::ARRAY => "getArray"
    ];

    /**
     * @param Entity $entity
     *
     * @return string[]
     */
    public function getUseClassNameList(Entity $entity): array
    {
        $useClassList = [

        ];
        foreach ($entity->getReferenceList() as $reference) {
            $foreignEntity = $reference->getForeignEntity();
            if ($foreignEntity !== null) {
                $useClassList[] = $foreignEntity->getTransferClassName();
            }
        }
        return $useClassList;
    }

    /**
     * @return string[]
     */
    public function getInterfaceList(): array
    {
        return [
        ];
    }

    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);
        $this->fromTransfer();
        //$this->generateToArray();
    }


    /**
     * @return void
     */
    protected function fromTransfer(): void
    {
        $method = $this->classGenerator->addPublicMethod(self::FROM_TRANSFER);
        $method->addParameter($this->entity->getTransferClassName(), "transfer");
        $method->setReturnType('void', false);

        $this->generateAttributeListFromArray($method);
    }

    /**
     * @param Method $method
     * @throws ReflectionException
     */
    protected function generateAttributeListFromArray(Method $method): void
    {
        foreach ($this->entity->getAttributeList() as $attribute) {
            $method->addCodeLine('$this->set' . $attribute->getMethodName() . '($transfer->get' . $attribute->getMethodName() . '());');
        }
    }




    /**
     * @param Method $method
     * @param Collection $collection
     */
    protected function generateCollectionFromArray(Method $method, Collection $collection): void
    {
        $foreignEntity = $collection->getForeignEntity();
        $name = $collection->getName();

        // get collection data and make sure it exists
        $method->addCodeLine('$' . $name . 'Array = $arrayAccessor->getArray("' . $name . '");');
        $method->addIfStart('$' . $name . 'Array !== null');

        // iterate array data
        $method->addForeachStart('$' . $name . 'Array as $entityArray');

        // instantiate new foreign entity initialize it and add it to the collection
        $method->addCodeLine('$' . $name . ' = ' . $foreignEntity->getServiceAccess() . '->' . NewInstancePlugin::METHOD_NEW_INSTANCE . '();');
        $method->addCodeLine('$' . $name . '->' . self::TO_TRANSFER . '($entityArray);');
        $method->addCodeLine('$this->' . CollectorGetterSetter::METHOD_ADD_TO_PREFIX . $collection->getMethodName() . '($' . $name . ');');

        $method->addForeachEnd();

        $method->addIfEnd();

    }

    /**
     * @param Method $method
     */
    protected function generateDynamicCollectionListFromArray(Method $method): void
    {
        foreach ($this->entity->getDynamicCollectionList() as $dynamicCollection) {
            $this->generateDynamicCollectionFromArray($method, $dynamicCollection);
        }
    }

    /**
     * @param Method $method
     * @param DynamicCollection $dynamicCollection
     */
    protected function generateDynamicCollectionFromArray(Method $method, DynamicCollection $dynamicCollection): void
    {
        $foreignEntity = $dynamicCollection->getForeignEntity();
        $name = $dynamicCollection->getName();

        // get collection data and make sure it exists
        $method->addCodeLine('$' . $name . 'Array = $arrayAccessor->getArray("' . $name . '");');
        $method->addIfStart('$' . $name . 'Array !== null');

        // iterate array data
        $method->addForeachStart('$' . $name . 'Array as $entityArray');

        // instantiate new foreign entity initialize it and add it to the collection
        $method->addCodeLine('$' . $name . ' = ' . $foreignEntity->getServiceAccess() . '->' . NewInstancePlugin::METHOD_NEW_INSTANCE . '();');
        $method->addCodeLine('$' . $name . '->' . self::TO_TRANSFER . '($entityArray);');
        $method->addCodeLine('$this->' . CollectorGetterSetter::METHOD_ADD_TO_PREFIX . $dynamicCollection->getMethodName() . '($' . $name . ');');

        $method->addForeachEnd();

        $method->addIfEnd();

    }

    /**
     * @param Method $method
     */
    protected function addCheckExisting(Method $method): void
    {
        if (!$this->entity->hasPrimaryKey()) {
            return;
        }

        $pkCheckList = [];
        foreach ($this->entity->getPrimaryKeyAttributeList() as $attribute) {
            $pkCheckList[] = '($this->' . $attribute->getPhpName() . ' !== null)';
        }

//        $pkCheck = implode(" && ", $pkCheckList);
//        $method->addCodeLine('$this->_existing = ' . $pkCheck . ';');
    }

    /**
     * @throws ReflectionException
     */
    protected function generateToArray(): void
    {
        $method = $this->classGenerator->addPublicMethod(self::FROM_TRANSFER);
        $method->addParameter('Siesta\Contract\CycleDetector', 'cycleDetector', 'null');
        $method->setReturnType('array', true);

        $this->generateCycleDetection($method);

        $this->generateAttributeListToArray($method);

        $this->generateReferenceListToArray($method);

        $this->generateCollectionListToArray($method);

        $this->generateDynamicCollectionListToArray($method);

        $method->addCodeLine('return $result;');
    }

    /**
     * @param Method $method
     */
    protected function generateCycleDetection(Method $method): void
    {
        $method->addIfStart('$cycleDetector === null');
        $method->addCodeLine('$cycleDetector = new ArrayCycleDetector();');
        $method->addIfEnd();
        $method->addNewLine();

        // canProceed
        $method->addIfStart('!$cycleDetector->canProceed(self::TABLE_NAME, $this)');
        $method->addCodeLine('return null;');
        $method->addIfEnd();
        $method->addNewLine();
    }

    /**
     * @param Method $method
     * @throws ReflectionException
     */
    protected function generateAttributeListToArray(Method $method): void
    {

        $method->addCodeLine('$result = [');
        $method->incrementIndent();
        foreach ($this->entity->getAttributeList() as $index => $attribute) {
            $line = $this->generateAttributeToArray($attribute);
            if (($index + 1) !== sizeof($this->entity->getAttributeList())) {
                $line .= ",";
            }
            $method->addCodeLine($line);
        }
        $method->decrementIndent();
        $method->addCodeLine('];');
    }

    /**
     * @param Attribute $attribute
     * @return string
     * @throws ReflectionException
     */
    protected function generateAttributeToArray(Attribute $attribute): string
    {
        $name = $attribute->getPhpName();
        $type = $attribute->getPhpType();
        $methodName = 'get' . $attribute->getMethodName();

        if ($attribute->getIsObject() && $attribute->implementsArraySerializable()) {
            return '"' . $name . '" => ($this->' . $methodName . '() !== null) ? $this->' . $methodName . '()->toArray() : null';
        }

        if ($type === PHPType::SIESTA_DATE_TIME) {
            if ($attribute->getDbType() === DBType::DATE) {
                return '"' . $name . '" => ($this->' . $methodName . '() !== null) ? $this->' . $methodName . '()->getSQLDate() : null';
            }

            if ($attribute->getDbType() === DBType::DATETIME) {
                return '"' . $name . '" => ($this->' . $methodName . '() !== null) ? $this->' . $methodName . '()->getJSONDateTime() : null';
            }

            if ($attribute->getDbType() === DBType::TIME) {
                return '"' . $name . '" => ($this->' . $methodName . '() !== null) ? $this->' . $methodName . '()->getSQLTime() : null';
            }
        }
        if ($attribute->isEnum()) {
            return '"' . $name . '" => $this->' . $methodName . '()?->value';
        }

        return '"' . $name . '" => $this->' . $methodName . '()';
    }

    /**
     * @param Method $method
     */
    protected function generateReferenceListToArray(Method $method): void
    {
        foreach ($this->entity->getReferenceList() as $reference) {
            $this->generateReferenceToArray($method, $reference);
        }
    }

    /**
     * @param Method $method
     * @param Reference $reference
     */
    protected function generateReferenceToArray(Method $method, Reference $reference): void
    {
        $name = $reference->getName();
        $method->addIfStart('$this->' . $name . ' !== null');
        $method->addCodeLine('$result["' . $name . '"] = $this->' . $name . '->' . self::FROM_TRANSFER . '($cycleDetector);');
        $method->addIfEnd();
    }

    /**
     * @param Method $method
     */
    protected function generateCollectionListToArray(Method $method): void
    {
        foreach ($this->entity->getCollectionList() as $collection) {
            $this->generateCollectionToArray($method, $collection);
        }
    }

    /**
     * @param Method $method
     * @param Collection $collection
     */
    protected function generateCollectionToArray(Method $method, Collection $collection): void
    {
        $name = $collection->getName();
        $method->addCodeLine('$result["' . $name . '"] = [];');

        $method->addIfStart('$this->' . $name . ' !== null');
        $method->addForeachStart('$this->' . $name . ' as $entity');
        $method->addCodeLine('$result["' . $name . '"][] = $entity->' . self::FROM_TRANSFER . '($cycleDetector);');
        $method->addForeachEnd();
        $method->addIfEnd();
    }

    /**
     * @param Method $method
     */
    protected function generateDynamicCollectionListToArray(Method $method): void
    {
        foreach ($this->entity->getDynamicCollectionList() as $dynamicCollection) {
            $this->generateDynamicCollectionToArray($method, $dynamicCollection);
        }
    }

    /**
     * @param Method $method
     * @param DynamicCollection $dynamicCollection
     */
    protected function generateDynamicCollectionToArray(Method $method, DynamicCollection $dynamicCollection): void
    {
        $name = $dynamicCollection->getName();
        $method->addCodeLine('$result["' . $name . '"] = [];');

        $method->addIfStart('$this->' . $name . ' !== null');
        $method->addForeachStart('$this->' . $name . ' as $entity');
        $method->addCodeLine('$result["' . $name . '"][] = $entity->' . self::FROM_TRANSFER . '($cycleDetector);');
        $method->addForeachEnd();
        $method->addIfEnd();
    }
}
