<?php

namespace Tests\Unit;

use App\Services\Aios\CommandParser;
use PHPUnit\Framework\TestCase;

class CommandParserTest extends TestCase
{
    private CommandParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CommandParser;
    }

    public function test_room_add_with_quoted_name(): void
    {
        $parsed = $this->parser->parse("room add 'IT Team'");

        $this->assertSame('command', $parsed['type']);
        $this->assertSame('room.add', $parsed['command']);
        $this->assertSame(['IT Team'], $parsed['positional']);
    }

    public function test_agent_add_options(): void
    {
        $parsed = $this->parser->parse("agent add --room 01 --role 'Backend Dev' --model combo-coding");

        $this->assertSame('agent.add', $parsed['command']);
        $this->assertSame('01', $parsed['options']['room']);
        $this->assertSame('Backend Dev', $parsed['options']['role']);
        $this->assertSame('combo-coding', $parsed['options']['model']);
    }

    public function test_option_equals_syntax_and_flag(): void
    {
        $parsed = $this->parser->parse('role add SecOps --tools=git,sandbox --force');

        $this->assertSame('SecOps', $parsed['positional'][0]);
        $this->assertSame('git,sandbox', $parsed['options']['tools']);
        $this->assertTrue($parsed['options']['force']);
    }

    public function test_double_quotes_and_positional(): void
    {
        $parsed = $this->parser->parse('project run --room 01 "Aplikasi inventaris gudang"');

        $this->assertSame('project.run', $parsed['command']);
        $this->assertSame('Aplikasi inventaris gudang', $parsed['positional'][0]);
    }

    public function test_unknown_action_is_invalid(): void
    {
        $parsed = $this->parser->parse('room explode 01');

        $this->assertSame('invalid', $parsed['type']);
    }

    public function test_natural_language_is_detected(): void
    {
        $parsed = $this->parser->parse('Tambahkan Security Specialist ke Room-01');

        $this->assertSame('natural', $parsed['type']);
    }

    public function test_empty_input(): void
    {
        $this->assertSame('empty', $this->parser->parse('')['type']);
        $this->assertSame('empty', $this->parser->parse('   ')['type']);
    }
}
