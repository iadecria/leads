<?php

namespace Tests\Feature;

use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_exposes_web_search_debug_information()
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"home_recent_matches":[{"date":"2026-08-16","competition":"Liga","home_team":"A","away_team":"B","home_score_ft":1,"away_score_ft":0,"home_score_ht":null,"away_score_ht":null,"source_urls":["https://example.com"]}],"away_recent_matches":[{"date":"2026-08-16","competition":"Liga","home_team":"C","away_team":"D","home_score_ft":0,"away_score_ft":1,"home_score_ht":null,"away_score_ht":null,"source_urls":["https://example.com"]}],"sources":["https://example.com"],"research_quality":"HIGH"}',
                            'tool_calls' => [
                                [
                                    'id' => 'call_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'web_search',
                                        'arguments' => '{}',
                                    ],
                                ],
                            ],
                            'annotations' => [
                                ['type' => 'citation', 'url' => 'https://example.com'],
                            ],
                        ],
                    ],
                ],
                'model' => 'google/gemini-2.5-flash',
                'provider' => 'Google',
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ], 200),
        ]);

        $client = app(OpenRouterClient::class);
        $result = $client->researchWebPluginJsonWithDebug('test prompt');

        $this->assertSame('HIGH', $result['result']['research_quality']);
        $this->assertTrue($result['debug']['web_search_requested']);
        $this->assertTrue($result['debug']['web_search_executed']);
        $this->assertSame('WEB_PLUGIN', $result['debug']['search_strategy']);
        $this->assertSame('google/gemini-2.5-flash', $result['debug']['resolved_model']);
        $this->assertSame('Google', $result['debug']['resolved_provider']);
        $this->assertSame(1, $result['debug']['sources_count']);
        $this->assertSame(10, $result['debug']['usage']['prompt_tokens']);
        $this->assertArrayHasKey('raw_response', $result['debug']);
    }

    public function test_client_uses_web_plugin_as_default_research_strategy()
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => '{"sources":[],"home_recent_matches":[],"away_recent_matches":[],"research_quality":"LOW"}',
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $client = app(OpenRouterClient::class);
        $result = $client->researchWebPluginJsonWithDebug('test prompt');

        $this->assertSame('LOW', $result['result']['research_quality']);
        $this->assertSame('WEB_PLUGIN', $result['debug']['search_strategy']);
        $this->assertTrue($result['debug']['web_search_requested']);
        $this->assertTrue($result['debug']['web_search_executed']);
    }

    public function test_fas_research_command_can_print_debug_output()
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"home_recent_matches":[],"away_recent_matches":[],"sources":[],"research_quality":"INSUFFICIENT"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('fas:research', [
            'date' => '2026-08-17',
            '--home' => 'Internacional',
            '--away' => 'Remo',
            '--debug' => true,
        ])
            ->expectsOutputToContain('"search_strategy": "WEB_PLUGIN"')
            ->assertExitCode(0);
    }
}
