<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Diagnostics;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\VO\Audit\DebugInfo;
use Brahmic\ApiSutra\VO\Audit\PipelineEvent;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final readonly class AuditLogger
{
    /**
     * @var array<string, int>
     */
    private const array LOG_LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    public function __construct(
        private ClientConfig $config,
    ) {
    }

    public function addAudit(
        array &$audit,
        PipelineStage $stage,
        PipelineContext $context,
        ?float $start,
        ?DebugInfo $debug,
    ): void {
        $timestamp = microtime(true);
        $duration = $start !== null ? ($timestamp - $start) * 1000 : null;
        $payload = $this->config->debug ? $debug : null;

        $audit[] = new PipelineEvent(
            stage: $stage,
            timestamp: $timestamp,
            duration: $duration,
            requestClass: $context->request::class,
            role: $context->role,
            payload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $secretFields
     */
    public function log(string $level, string $message, array $context = [], array $secretFields = []): void
    {
        $logger = $this->config->logger;
        if (!$logger instanceof LoggerInterface) {
            return;
        }

        if (!$this->shouldLog($level)) {
            return;
        }

        $logger->log($level, $message, $this->config->redaction->withFields($secretFields)->context($context));
    }

    private function shouldLog(string $level): bool
    {
        $min = self::LOG_LEVELS[$this->config->logLevel] ?? self::LOG_LEVELS[LogLevel::INFO];
        $current = self::LOG_LEVELS[$level] ?? self::LOG_LEVELS[LogLevel::INFO];

        return $current >= $min;
    }
}
