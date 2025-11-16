<?php

declare(strict_types=1);

namespace Siesta\GeneratorPlugin\Entity;

use JsonSerializable;
use Nitria\ClassGenerator;
use Siesta\GeneratorPlugin\BasePlugin;
use Siesta\Model\Entity;
use Siesta\Model\PHPType;

/**
 * @author Gregor Müller
 */
class JSONConverterPlugin extends BasePlugin
{
    const METHOD_FROM_JSON = "fromJSON";

    const METHOD_TO_JSON = "toJSON";


    /**
     * @return string[]
     */
    public function getDependantPluginList(): array
    {
        return [
            ArrayConverterPlugin::class,
        ];
    }

    /**
     * @return array
     */
    public function getInterfaceList(): array
    {
        return [JsonSerializable::class];
    }


    /**
     * @param Entity $entity
     * @param ClassGenerator $classGenerator
     */
    public function generate(Entity $entity, ClassGenerator $classGenerator): void
    {
        $this->setup($entity, $classGenerator);
        $this->generateFromJson();
        $this->generateToJson();
        $this->generateJsonSerialize();
    }

    /**
     *
     */
    protected function generateFromJson(): void
    {
        $method = $this->classGenerator->addPublicMethod(self::METHOD_FROM_JSON);
        $method->addParameter(PHPType::STRING, 'jsonString');

        $method->addCodeLine('$this->' . ArrayConverterPlugin::METHOD_FROM_ARRAY . '(json_decode($jsonString, true));');
    }

    /**
     *
     */
    protected function generateToJson(): void
    {
        $method = $this->classGenerator->addPublicMethod(self::METHOD_TO_JSON);

        $method->addParameter('Siesta\Contract\CycleDetector', 'cycleDetector', 'null');
        $method->setReturnType(PHPType::STRING);

        $method->addCodeLine('return json_encode($this->' . ArrayConverterPlugin::METHOD_TO_ARRAY . '($cycleDetector));');
    }


    protected function generateJsonSerialize(): void
    {
        $method = $this->classGenerator->addPublicMethod("jsonSerialize");
        $method->setReturnType("array", false);
        $method->addCodeLine('return $this->toArray();');

    }
}