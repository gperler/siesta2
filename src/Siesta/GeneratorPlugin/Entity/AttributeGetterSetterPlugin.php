<?php
declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Entity;

use Nitria\ClassGenerator;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\Model\Attribute;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;
use Siesta\Sequencer\SequencerFactory;
use Siesta\Util\StringUtil;

/**
 * @author Gregor Müller
 */
class AttributeGetterSetterPlugin extends BasePlugin
{
    /**
     * @param Entity $entity
     *
     * @return string[]
     */
    public function getUseClassNameList(Entity $entity): array
    {
        $useList = [];
        foreach ($entity->getAttributeList() as $attribute) {
            if ($attribute->getPhpType() === PHPType::STRING) {
                $useList[] = StringUtil::class;
            }
            if ($attribute->getAutoValue() !== null) {
                $useList[] = SequencerFactory::class;
            }
        }
        return $useList;
    }

    /**
     * @return string[]
     */
    public function getDependantPluginList(): array
    {
        return [
            MemberPlugin::class,
            ConstantPlugin::class,
        ];
    }

    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);

        foreach ($entity->getAttributeList() as $attribute) {

            if ($attribute->getIsPrimaryKey()) {
                $this->generatePrimaryKeyGetter($attribute);
            } else {
                $this->generateGetter($attribute);
            }

            $this->generateEnumIs($attribute);

            $this->generateSetter($attribute);

            $this->generateEnumSet($attribute);

            $this->generateEnumSetFrom($attribute);

            if ($attribute->getPhpType() === PHPType::ARRAY) {
                $this->generateAddToArrayType($attribute);
                $this->generateGetFromArrayType($attribute);
            }
        }
    }

    /**
     * @param Attribute $attribute
     */
    protected function generateGetter(Attribute $attribute): void
    {
        $methodName = "get" . $attribute->getMethodName();
        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->setReturnType($attribute->getFullyQualifiedTypeName());

        $method->addCodeLine('return $this->' . $attribute->getPhpName() . ';');


    }

    /**
     * @param Attribute $attribute
     * @return void
     */
    protected function generateEnumIs(Attribute $attribute): void
    {
        if (!$attribute->isEnum()) {
            return;
        }
        foreach ($attribute->getEnumValues() as $enumValue) {
            $methodName = "is" . $attribute->getMethodName() . ucfirst($enumValue);
            $method = $this->classGenerator->addPublicMethod($methodName);
            $method->setReturnType("bool", false);

            $classNameShort = $attribute->getEnumClassNameShort();
            $method->addCodeLine('return $this->' . $attribute->getPhpName() . ' === ' . $classNameShort . '::' . $enumValue . ';');
        }
    }

    /**
     * @param Attribute $attribute
     */
    protected function generatePrimaryKeyGetter(Attribute $attribute): void
    {
        $methodName = "get" . $attribute->getMethodName();
        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter(PHPType::BOOL, 'generateKey', 'false');
        $method->addParameter(PHPType::STRING, 'connectionName', 'null');
        $method->setReturnType($attribute->getPhpType(), true);

        $autoValue = $attribute->getAutoValue();

        $memberName = '$this->' . $attribute->getPhpName();

        $method->addIfStart('$generateKey && ' . $memberName . ' === null');
        $method->addCodeLine($memberName . ' = SequencerFactory::nextSequence("' . $autoValue . '", self::TABLE_NAME, $connectionName);');
        $method->addIfEnd();

        $method->addCodeLine('return ' . $memberName . ';');
    }

    /**
     * @param Attribute $attribute
     */
    protected function generateSetter(Attribute $attribute): void
    {
        $name = $attribute->getPhpName();
        $type = $attribute->getPhpType();
        $methodName = "set" . $attribute->getMethodName();

        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter($attribute->getFullyQualifiedTypeName(), $name, null, null, true);


        if ($type === PHPType::STRING && !$attribute->isEnum()) {
            $length = $attribute->getLength() !== null ? $attribute->getLength() : 'null';
            $method->addCodeLine('$this->' . $name . ' = StringUtil::trimToNull($' . $name . ", " . $length . ");");
            return;
        }
        $method->addCodeLine('$this->' . $name . ' = $' . $name . ";");
    }


    /**
     * @param Attribute $attribute
     * @return void
     */
    protected function generateEnumSet(Attribute $attribute): void
    {
        if (!$attribute->isEnum()) {
            return;
        }
        foreach ($attribute->getEnumValues() as $enumValue) {
            $methodName = "set" . $attribute->getMethodName() . ucfirst($enumValue);
            $method = $this->classGenerator->addPublicMethod($methodName);
            $classNameShort = $attribute->getEnumClassNameShort();
            $method->addCodeLine('$this->' . $attribute->getPhpName() . ' = ' . $classNameShort . '::' . $enumValue . ';');
        }
    }

    /**
     * @param Attribute $attribute
     * @return void
     */
    protected function generateEnumSetFrom(Attribute $attribute): void
    {
        if (!$attribute->isEnum()) {
            return;
        }
        $methodName = "set" . $attribute->getMethodName() . 'From';
        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter(PHPType::STRING, 'value', null, null, true);

        $method->addIfStart('$value === null');
        $method->addCodeLine('return;');
        $method->addIfEnd();

        $classNameShort = $attribute->getEnumClassNameShort();

        $method->addCodeLine('$this->' . $attribute->getPhpName() . ' = ' . $classNameShort . '::tryFrom($value);');
    }


    /**
     * @param Attribute $attribute
     */
    protected function generateAddToArrayType(Attribute $attribute): void
    {
        $methodName = "addTo" . $attribute->getMethodName();
        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter(PHPType::STRING, "key");
        $method->addParameter(null, "value", 'null');

        $memberName = '$this->' . $attribute->getPhpName();

        $method->addIfStart($memberName . ' === null');
        $method->addCodeLine($memberName . ' = [];');
        $method->addIfEnd();

        $method->addCodeLine($memberName . '[$key] = $value;');
    }

    /**
     * @param Attribute $attribute
     * @return void
     */
    protected function generateGetFromArrayType(Attribute $attribute): void
    {
        $methodName = "getFrom" . $attribute->getMethodName();
        $method = $this->classGenerator->addPublicMethod($methodName);
        $method->addParameter(PHPType::STRING, "key");
        $method->setReturnType(null, true);
        $memberName = '$this->' . $attribute->getPhpName();

        $method->addCodeLine('return ArrayUtil::getFromArray(' . $memberName . ', $key);');

    }

}