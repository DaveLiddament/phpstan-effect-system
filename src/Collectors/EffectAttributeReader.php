<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use DaveLiddament\PhpstanEffectSystem\Attributes\HandlesEffect;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;

/**
 * Extracts effect attribute data from a method/function reflection.
 *
 * Only attribute arguments that resolve to exactly one constant string are
 * recorded; class constants fold to constant strings and work, truly dynamic
 * expressions are skipped (documented limitation).
 */
final class EffectAttributeReader
{
    private const ATTRIBUTE_BUCKETS = [
        Effect::class => 'effects',
        EffectFree::class => 'effectFree',
        HandlesEffect::class => 'handles',
    ];

    /**
     * @return array{effects: list<string>, effectFree: list<string>, handles: list<string>}
     */
    public function read(PhpFunctionFromParserNodeReflection $function): array
    {
        $result = ['effects' => [], 'effectFree' => [], 'handles' => []];

        foreach ($function->getAttributes() as $attribute) {
            $bucket = null;
            foreach (self::ATTRIBUTE_BUCKETS as $attributeClass => $bucketName) {
                if (strcasecmp($attribute->getName(), $attributeClass) === 0) {
                    $bucket = $bucketName;
                    break;
                }
            }
            if ($bucket === null) {
                continue;
            }

            $nameType = $attribute->getArgumentTypes()['name'] ?? null;
            if ($nameType === null) {
                continue;
            }

            $constantStrings = $nameType->getConstantStrings();
            if (count($constantStrings) !== 1) {
                continue;
            }

            $result[$bucket][] = $constantStrings[0]->getValue();
        }

        return $result;
    }
}
