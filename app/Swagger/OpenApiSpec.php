<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        title: "My API",
        version: "1.0.0",
        description: "API documentation"
    )
)]
class OpenApiSpec
{
}
