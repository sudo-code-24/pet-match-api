<?php

namespace App\Providers;

use App\TypeScript\EloquentModelTransformer;
use App\TypeScript\NoopFormatter;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;

class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $outputFile = (string) config('typescript-transformer.output_file', base_path('../pet-match/types/generated.d.ts'));
        $outputDirectory = (string) dirname($outputFile);
        $outputFilename = (string) basename($outputFile);

        $config
            ->transformer(EloquentModelTransformer::class)
            ->transformer(EnumTransformer::class)
            ->outputDirectory($outputDirectory)
            ->transformDirectories(app_path())
            ->writer(new GlobalNamespaceWriter($outputFilename))
            ->formatter(NoopFormatter::class);
    }
}
