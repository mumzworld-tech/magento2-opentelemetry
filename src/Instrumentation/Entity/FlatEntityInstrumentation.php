<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * Instrument generic Magento 2 entities (EAV + non-EAV) for save, load, delete operations.
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Entity;

use OpenTelemetry\API\Trace\SpanKind;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class FlatEntityInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.entity.flat';
    private const SPAN_NAME_PREFIX = 'FlatEntity:';

    private const INSTRUMENTED_ENTITY_CLASSES = [
        \Magento\Quote\Model\ResourceModel\Quote::class,
        \Magento\Quote\Model\ResourceModel\Quote\Item::class,
        \Magento\Sales\Model\ResourceModel\Order::class,
        \Magento\Sales\Model\ResourceModel\Order\Item::class,
        \Magento\Sales\Model\ResourceModel\Order\Invoice::class,
        \Magento\Sales\Model\ResourceModel\Order\Creditmemo::class,
    ];

    /**
     * @inheritdoc
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    protected static function getInstrumentationName(): string
    {
        return self::INSTRUMENTATION_NAME;
    }

    /**
     * @inheritdoc
     */
    public static function register(): void
    {
        foreach (['load', 'save', 'delete'] as $method) {
            foreach (self::INSTRUMENTED_ENTITY_CLASSES as $class) {
                self::hookFlatEntityOperation($class, $method);
            }
        }

        // Note: collection-level tracing intentionally disabled (too noisy)
    }

    /**
     * @param string $class
     * @param string $method
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function hookFlatEntityOperation(string $class, string $method): void
    {
        hook(
            $class,
            $method,
            static function (
                object $subject,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($method) {
                $spanName = sprintf(
                    '%s %s: %s',
                    self::SPAN_NAME_PREFIX,
                    self::extractClassName($class),
                    $method
                );
                $builder = self::createSpanBuilder($spanName, $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute('magento.entity.flat.operation', $method)
                    ->setAttribute('magento.entity.flat.class', get_class($subject));

                if ($method === 'load') {
                    $entityId = self::getFlatEntityIdFromParams($params[0] ?? null);
                    $builder->setAttribute('magento.entity.flat.entity_id', $entityId);
                } elseif (in_array($method, ['save', 'delete'], true)) {
                    $builder->setAttribute(
                        'magento.entity.flat.entity_id',
                        method_exists($subject, 'getId') ? (int)$subject->getId() : null
                    );
                    if ($method === 'save') {
                        $builder->setAttribute(
                            'magento.entity.flat.isObjectNew',
                            method_exists($subject, 'isObjectNew') ? (int)$subject->isObjectNew() : 0
                        );
                    }
                }

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                object $subject,
                array $params,
                mixed $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    /**
     * Extracts the Flat entity ID from the given parameter.
     *
     * @param mixed $params
     * @return int
     */
    private static function getFlatEntityIdFromParams(mixed $params): int
    {
        $entityId = 0;
        $entity = $params ?? null;
        if (is_object($entity) && method_exists($entity, 'getId')) {
            $entityId = (int)$entity->getId();
        } elseif (is_scalar($entity)) {
            $entityId = (int)$entity;
        }

        return $entityId;
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
