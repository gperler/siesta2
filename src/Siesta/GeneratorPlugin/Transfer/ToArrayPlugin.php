<?php

declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Transfer;

use Nitria\ClassGenerator;
use Nitria\Method;
use ReflectionException;
use Siesta\Contract\ArraySerializable;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\Model\Attribute;
use Siesta\Model\Collection;
use Siesta\Model\DBType;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;
use Siesta\Model\Reference;
use Siesta\Util\ArrayCycleDetector;

/**
 * @author Gregor Müller
 */
class ToArrayPlugin extends BasePlugin
{

    const string METHOD_TO_ARRAY = "toArray";


    /**
     * @param Entity $entity
     *
     * @return string[]
     */
    public function getUseClassNameList(Entity $entity): array
    {
        $useClassList = [
            ArrayCycleDetector::class
        ];
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
    public function getInterfaceList(): array
    {
        return [
            ArraySerializable::class,
            \JsonSerializable::class,
        ];
    }

    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     * @throws ReflectionException
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);
        $this->generateToArray();
        $this->generateJsonSerialize();
    }

    protected function generateJsonSerialize(): void
    {
        $method = $this->classGenerator->addPublicMethod('jsonSerialize');
        $method->setReturnType("array", false);
        $method->addCodeLine('return $this->' . self::METHOD_TO_ARRAY . '();');
    }


    /**
     * @throws ReflectionException
     */
    protected function generateToArray(): void
    {
        $method = $this->classGenerator->addPublicMethod(self::METHOD_TO_ARRAY);
        $method->addParameter('Siesta\Contract\CycleDetector', 'cycleDetector', 'null');
        $method->setReturnType('array', true);

        $this->generateCycleDetection($method);

        $this->generateAttributeListToArray($method);

        $this->generateReferenceListToArray($method);

        $this->generateCollectionListToArray($method);

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
        $method->addIfStart('!$cycleDetector->canProceed("' . $this->entity->getTableName() . '", $this)');
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
        $method->addCodeLine('$result["' . $name . '"] = $this->' . $name . '->' . self::METHOD_TO_ARRAY . '($cycleDetector);');
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
        $method->addCodeLine('$result["' . $name . '"][] = $entity->' . self::METHOD_TO_ARRAY . '($cycleDetector);');
        $method->addForeachEnd();
        $method->addIfEnd();
    }


}
