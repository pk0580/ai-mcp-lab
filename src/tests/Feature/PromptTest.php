<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Mcp\Prompts\ErrorPrompt;
use App\Mcp\Prompts\SystemPrompt;
use App\Services\LLM\AiSdkService;
use Laravel\Mcp\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prompt_class_renders_correctly(): void
    {
        $content = (string) (new SystemPrompt())->handle(new Request())->content();
        $this->assertStringContainsString('NeuronAgent', $content);
        $this->assertStringContainsString('инструменты', $content);
    }

    public function test_error_prompt_class_renders_correctly(): void
    {
        $content = (string) (new ErrorPrompt())->handle(new Request(arguments: [
            'toolName' => 'SearchTool',
            'retryCount' => 1,
            'error' => 'Connection timed out',
        ]))->content();

        $this->assertStringContainsString('SearchTool', $content);
        $this->assertStringContainsString('1', $content);
        $this->assertStringContainsString('Connection timed out', $content);
    }

    public function test_ai_sdk_service_uses_prompt_classes(): void
    {
        $run = Run::create(['prompt' => 'Hello']);
        $run->steps()->create([
            'type' => 'error',
            'content' => 'Test error',
            'metadata' => ['tool' => 'MockTool', 'retry_count' => 2]
        ]);

        $service = new AiSdkService();

        // Используем рефлексию для вызова buildMessages
        $reflection = new \ReflectionClass(AiSdkService::class);
        $method = $reflection->getMethod('buildMessages');
        $method->setAccessible(true);

        $messages = $method->invoke($service, $run);

        // В buildMessages добавляется UserMessage для ошибки
        $errorMsg = $messages->last();
        $this->assertInstanceOf(\Laravel\Ai\Messages\UserMessage::class, $errorMsg);

        $content = $errorMsg->content;
        $this->assertStringContainsString('MockTool', $content);
        $this->assertStringContainsString('2', $content);
        $this->assertStringContainsString('Test error', $content);
    }
}
