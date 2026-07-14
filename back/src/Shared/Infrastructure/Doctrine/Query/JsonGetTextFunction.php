<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use RuntimeException;

/**
 * JSON_GET_TEXT(document, key) → PostgreSQL "document ->> key".
 *
 * Extracts a top-level JSON field as text so DQL queries can filter on JSON
 * columns (e.g. activity_logs.metadata ->> 'path').
 */
final class JsonGetTextFunction extends FunctionNode
{
    private ?Node $jsonDocument = null;
    private ?Node $jsonKey = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonDocument = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->jsonKey = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        if ($this->jsonDocument === null || $this->jsonKey === null) {
            throw new RuntimeException('JSON_GET_TEXT was not parsed before SQL generation.');
        }

        return sprintf(
            '(%s ->> %s)',
            $this->jsonDocument->dispatch($sqlWalker),
            $this->jsonKey->dispatch($sqlWalker),
        );
    }
}
