<?php

declare(strict_types=1);

namespace Siesta\Generator;

use Codeception\Util\Debug;
use Nitria\ClassGenerator;
use Nitria\ClassType;
use Nitria\ScalarType;
use Siesta\Model\Attribute;
use Siesta\Model\Entity;

/**
 * @author Gregor Müller
 */
class EnumGenerator extends AbstractGenerator
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
        $this->classGenerator = new ClassGenerator($entity->getClassName());
        $this->entity = $entity;
        $this->basePath = $baseDir;

        foreach ($entity->getAttributeList() as $attribute) {
            if (!$attribute->isEnum()) {
                continue;
            }
            $this->generateEnumClass($entity, $attribute);
        }
    }


    /**
     * @param Entity $entity
     * @param Attribute $attribute
     * @return void
     */
    private function generateEnumClass(Entity $entity, Attribute $attribute): void
    {

        $className = $attribute->getEnumClassNameShort();
        $classPath = $attribute->getEnumClassName();

        $classGenerator = new ClassGenerator($classPath);
        $classGenerator->setClassType(ClassType::Enum);
        $classGenerator->setEnumType(ScalarType::String);

        foreach ($attribute->getEnumValues() as $enumValue) {
            $classGenerator->addEnumCase($enumValue, "'$enumValue'");
        }

        $basePath = rtrim($this->basePath, DIRECTORY_SEPARATOR);
        $targetFile = $basePath . DIRECTORY_SEPARATOR . $entity->getTargetPath() . DIRECTORY_SEPARATOR . $className . '.php';
        $classGenerator->writeToFile($targetFile);
    }


}