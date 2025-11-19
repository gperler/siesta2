<?php

declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Transfer;

use Civis\Common\ArrayUtil;
use Nitria\ClassGenerator;
use Nitria\Method;
use ReflectionException;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\GeneratorPlugin\Entity\CollectorGetterSetter;
use Siesta\GeneratorPlugin\ServiceClass\NewInstancePlugin;
use Siesta\Model\Attribute;
use Siesta\Model\Collection;
use Siesta\Model\DynamicCollection;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;
use Siesta\Model\Reference;
use Siesta\Util\ArrayAccessor;

/**
 * @author Gregor Müller
 */
class FromArrayPlugin extends BasePlugin
{

    const string METHOD_TO_ARRAY = "toArray";

    const string METHOD_FROM_ARRAY = "fromArray";

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
        return [
            ArrayAccessor::class,
        ];
    }

    /**
     * @return string[]
     */
    public function getInterfaceList(): array
    {
        return [];
    }

    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     * @throws ReflectionException
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);
        $this->generateFromArray();
    }


    /**
     * @throws ReflectionException
     */
    protected function generateFromArray(): void
    {
        $method = $this->classGenerator->addPublicMethod(self::METHOD_FROM_ARRAY);
        $method->addParameter("array", "data", null, null, true);
        $method->setReturnType('void', false);

        $this->generateAttributeListFromArray($method);

        $this->addCheckExisting($method);

        $this->generateReferenceListFromArray($method);

        $this->generateCollectionListFromArray($method);

        $this->generateDynamicCollectionListFromArray($method);

    }

    /**
     * @param Method $method
     * @throws ReflectionException
     */
    protected function generateAttributeListFromArray(Method $method): void
    {
        //$method->addCodeLine('$this->_initialArray = $data;');
        $method->addCodeLine('$arrayAccessor = new ArrayAccessor($data);');

        foreach ($this->entity->getAttributeList() as $attribute) {
            $this->generateAttributeFromArray($method, $attribute);
            $this->generateObjectAttributeFromArray($method, $attribute);
        }
    }

    /**
     * @param Method $method
     * @param Attribute $attribute
     */
    protected function generateAttributeFromArray(Method $method, Attribute $attribute): void
    {
        $name = $attribute->getPhpName();

        $accessorMethod = ArrayUtil::getFromArray(self::TYPE_ARRAY_ACCESSOR_MAPPING, $attribute->getPhpType());
        if ($accessorMethod === null) {
            return;
        }
        $addOn = $attribute->isEnum() ? 'From' : '';
        $method->addCodeLine('$this->set' . $attribute->getMethodName() . $addOn . '($arrayAccessor->' . $accessorMethod . '("' . $name . '"));');
    }

    /**
     * @param Method $method
     * @param Attribute $attribute
     * @throws ReflectionException
     */
    protected function generateObjectAttributeFromArray(Method $method, Attribute $attribute): void
    {
        if (!$attribute->getIsObject() || !$attribute->implementsArraySerializable()) {
            return;
        }

        $name = $attribute->getPhpName();
        $type = $attribute->getPhpType();

        // access array raw data and make sure it is not null
        $method->addCodeLine('$' . $name . 'Array = $arrayAccessor->getArray("' . $name . '");');
        $method->addIfStart('$' . $name . 'Array !== null');

        // instantiate new object and initialize it from array
        $method->addCodeLine('$' . $name . ' = new ' . $type . '();');
        $method->addCodeLine('$' . $name . '->fromArray($' . $name . 'Array);');

        // invoke setter to store object
        $method->addCodeLine('$this->set' . $attribute->getMethodName() . '($' . $name . ');');

        // done
        $method->addIfEnd();
    }

    /**
     * @param Method $method
     */
    protected function generateReferenceListFromArray(Method $method): void
    {
        foreach ($this->entity->getReferenceList() as $reference) {
            $this->generateReferenceFromArray($method, $reference);
        }
    }

    /**
     * @param Method $method
     * @param Reference $reference
     */
    protected function generateReferenceFromArray(Method $method, Reference $reference): void
    {
        $foreignEntity = $reference->getForeignEntity();
        $name = $reference->getName();

        // get data from array and make sure it is not null
        $method->addCodeLine('$' . $name . 'Array = $arrayAccessor->getArray("' . $name . '");');

        $constructCall = 'new ' . $foreignEntity->getTransferClassShortName() . '($' . $name . 'Array)';

        $setValue = '$' . $name . 'Array !== null ? ' . $constructCall . ' : null';

        // invoke setter to store attribute
        $method->addCodeLine('$this->set' . $reference->getMethodName() . '(' . $setValue . ');');
    }

    /**
     * @param Method $method
     */
    protected function generateCollectionListFromArray(Method $method): void
    {
        foreach ($this->entity->getCollectionList() as $collection) {
            $this->generateCollectionFromArray($method, $collection);
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
        $method->addForeachStart('$' . $name . 'Array as $transferArray');

        $constructCall = 'new ' . $foreignEntity->getTransferClassShortName() . '($transferArray)';

        $method->addCodeLine('$this->' . CollectorGetterSetter::METHOD_ADD_TO_PREFIX . $collection->getMethodName() . '(' . $constructCall . ');');

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
        $method->addCodeLine('$' . $name . '->' . self::METHOD_FROM_ARRAY . '($entityArray);');
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

    }

}
