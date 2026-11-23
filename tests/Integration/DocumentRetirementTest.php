<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Bootstrap;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Router;
use PHPUnit\Framework\TestCase;

final class DocumentRetirementTest extends TestCase
{
    public function testFormerRoutesAreGoneAndApiDocumentationRemainsAvailable(): void
    {
        $router = Bootstrap::createContainer(':memory:')->get(Router::class);
        $schema = $router->dispatch(new Request('GET', '/openapi.json'));
        self::assertSame(200, $schema->getStatusCode());
        self::assertSame(200, $router->dispatch(new Request('GET', '/docs'))->getStatusCode());
        self::assertArrayHasKey('/api/appointments', $schema->getData()['paths']);

        $routes = [
            ['GET', '/api/documents'],
            ['POST', '/api/documents'],
            ['GET', '/api/documents/{id}'],
            ['DELETE', '/api/documents/{id}'],
            ['POST', '/api/documents/export'],
            ['POST', '/api/documents/test-ocr'],
            ['GET', '/api/patients/{id}/documents'],
        ];

        foreach ($routes as [$method, $path]) {
            self::assertArrayNotHasKey($path, $schema->getData()['paths']);
            $response = $router->dispatch(new Request($method, str_replace('{id}', 'sample-id', $path)));
            self::assertSame(404, $response->getStatusCode(), $method . ' ' . $path);
        }
    }

    public function testPatientDeletionPreservesLegacyDocumentsOnDisk(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'medic-legacy-');
        self::assertNotFalse($path);
        $documents = ['doc-1' => ['id' => 'doc-1', 'patient_id' => 'pat-1']];
        $patient = [
            'id' => 'pat-1',
            'first_name' => 'Dominique',
            'last_name' => 'Dupont',
            'email' => 'patient@example.test',
            'phone' => '0601020304',
        ];

        try {
            file_put_contents($path, json_encode([
                'patients' => ['pat-1' => $patient],
                'documents' => $documents,
            ], JSON_THROW_ON_ERROR));
            $router = Bootstrap::createContainer($path)->get(Router::class);

            self::assertSame(204, $router->dispatch(new Request('DELETE', '/api/patients/pat-1'))->getStatusCode());
            $saved = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([], $saved['patients']);
            self::assertSame($documents, $saved['documents']);
        } finally {
            unlink($path);
        }
    }
}
