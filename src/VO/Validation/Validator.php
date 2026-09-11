<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Validation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Label;
use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ValidationResult as ValidationResultContract;
use Brahmic\ApiSutra\Contracts\Interfaces\Validation\ValidatorInterface;
use Brahmic\ApiSutra\Exceptions\Validation\ValidationException;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Illuminate\Contracts\Validation\Factory;
use ReflectionClass;
use ReflectionProperty;

final class Validator implements ValidatorInterface
{
    private static ?Factory $factory = null;

    public static function useFactory(Factory $factory): void
    {
        self::$factory = $factory;
    }

    #[\Override]
    public static function check(object $value): ValidationResultContract
    {
        $reflection = new ReflectionClass($value);
        $rules = [];
        $messages = [];
        $labels = [];
        $inputs = [];

        foreach ($reflection->getProperties() as $property) {
            $validate = self::getAttribute($property, Validate::class);
            if ($validate === null) {
                continue;
            }

            $name = $property->getName();
            $rules[$name] = $validate->rules;
            $inputs[$name] = self::readProperty($value, $property);

            if ($validate->message !== null) {
                foreach (explode('|', $validate->rules) as $rule) {
                    $ruleName = explode(':', $rule, 2)[0];
                    $messages["{$name}.{$ruleName}"] = $validate->message;
                }
            }

            $label = self::getAttribute($property, Label::class);
            if ($label !== null) {
                $labels[$name] = $label->name;
            }
        }

        if ($rules === []) {
            return new ValidationResult(true, []);
        }

        $messages = array_merge(self::resolveValidationMessages($reflection), $messages);

        $factory = self::resolveFactory();
        if ($factory === null) {
            return new ValidationResult(true, []);
        }

        $validator = $factory->make($inputs, $rules, $messages, $labels);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $fieldErrors) {
                foreach ($fieldErrors as $message) {
                    $errors[] = new ValidationError(
                        field: $field,
                        rule: self::extractRule($rules[$field] ?? ''),
                        message: $message,
                        input: $inputs[$field] ?? null,
                    );
                }
            }

            return new ValidationResult(false, $errors);
        }

        return new ValidationResult(true, []);
    }

    public static function validateOrThrow(object $value): void
    {
        $result = self::check($value);
        if ($result->failed()) {
            throw new ValidationException($result->errors());
        }
    }

    private static function resolveFactory(): ?Factory
    {
        if (self::$factory !== null) {
            return self::$factory;
        }

        $provider = ContainerProviderRegistry::resolve();
        $factory = $provider->validatorFactory();
        if ($factory instanceof Factory) {
            return $factory;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function resolveValidationMessages(ReflectionClass $reflection): array
    {
        if (!$reflection->hasMethod('validationMessages')) {
            return [];
        }

        $method = $reflection->getMethod('validationMessages');
        if (!$method->isStatic()) {
            return [];
        }

        $method->setAccessible(true);
        $result = $method->invoke(null);
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    private static function extractRule(string $rules): string
    {
        $first = explode('|', $rules)[0] ?? '';
        return explode(':', $first, 2)[0] ?: 'unknown';
    }

    private static function readProperty(object $value, ReflectionProperty $property): mixed
    {
        if ($property->isPublic()) {
            return $value->{$property->getName()} ?? null;
        }

        $property->setAccessible(true);
        return $property->getValue($value);
    }

    private static function getAttribute(ReflectionProperty $property, string $class): ?object
    {
        $attributes = $property->getAttributes($class);
        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
