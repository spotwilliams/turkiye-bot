<?php

use App\Ai\Agents\SchoolMessageProcessor;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Attributes\UseSmartestModel;

test('text provider and model default to ollama + gemma3', function () {
    config()->set('ai.default', env('AI_DEFAULT_TEXT_PROVIDER', 'ollama'));
    config()->set('ai.default_text_model', env('AI_DEFAULT_TEXT_MODEL', 'gemma3:12b'));

    expect(config('ai.default'))->toBe('ollama')
        ->and(config('ai.default_text_model'))->toBe('gemma3:12b');
});

test('school message processor agent does not hardcode a provider or model', function () {
    $reflection = new ReflectionClass(SchoolMessageProcessor::class);

    expect($reflection->getAttributes(Provider::class))->toBeEmpty()
        ->and($reflection->getAttributes(Model::class))->toBeEmpty()
        ->and($reflection->getAttributes(UseCheapestModel::class))->toBeEmpty()
        ->and($reflection->getAttributes(UseSmartestModel::class))->toBeEmpty();
});

test('agent resolves its model from config so env controls it', function () {
    config()->set('ai.default_text_model', 'gemini-2.5-flash');

    expect((new SchoolMessageProcessor)->model())->toBe('gemini-2.5-flash');
});
