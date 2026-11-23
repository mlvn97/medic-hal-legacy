<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

class OpenApiController extends BaseController
{
    public function schema(Request $request): Response
    {
        $schema = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'API de Prise de Rendez-vous Médicaux (Santé Connect)',
                'description' => 'API REST complète pour la gestion des praticiens de santé, des dossiers patients, des plannings de consultation et la réservation temps-réel de rendez-vous médicaux avec prévention des conflits et gestion des créneaux.',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Santé Connect Support',
                    'email' => 'contact@sante-connect.local',
                ],
            ],
            'servers' => [
                [
                    'url' => 'http://localhost:8000',
                    'description' => 'Serveur local de développement',
                ],
            ],
            'paths' => [
                '/api/health' => [
                    'get' => [
                        'summary' => 'État de santé de l\'API',
                        'tags' => ['Système'],
                        'responses' => [
                            '200' => ['description' => 'API opérationnelle'],
                        ],
                    ],
                ],
                '/api/specialities' => [
                    'get' => [
                        'summary' => 'Liste des spécialités médicales',
                        'tags' => ['Praticiens'],
                        'responses' => [
                            '200' => ['description' => 'Liste des spécialités'],
                        ],
                    ],
                ],
                '/api/practitioners' => [
                    'get' => [
                        'summary' => 'Rechercher et lister des praticiens de santé',
                        'tags' => ['Praticiens'],
                        'parameters' => [
                            ['name' => 'speciality', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                            ['name' => 'city', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                            ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Liste des praticiens correspondants'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Enregistrer un nouveau praticien',
                        'tags' => ['Praticiens'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['first_name', 'last_name', 'email', 'phone', 'speciality', 'address', 'city', 'zip_code'],
                                        'properties' => [
                                            'first_name' => ['type' => 'string', 'example' => 'Sophie'],
                                            'last_name' => ['type' => 'string', 'example' => 'Martin'],
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'dr.martin@sante-connect.local'],
                                            'phone' => ['type' => 'string', 'example' => '0142685900'],
                                            'speciality' => ['type' => 'string', 'example' => 'Médecin généraliste'],
                                            'address' => ['type' => 'string', 'example' => '12 Rue de la République'],
                                            'city' => ['type' => 'string', 'example' => 'Paris'],
                                            'zip_code' => ['type' => 'string', 'example' => '75001'],
                                            'consultation_fee' => ['type' => 'number', 'example' => 26.5],
                                            'slot_duration_minutes' => ['type' => 'integer', 'example' => 30],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Praticien créé'],
                            '422' => ['description' => 'Erreur de validation'],
                        ],
                    ],
                ],
                '/api/practitioners/{id}' => [
                    'get' => [
                        'summary' => 'Détails d\'un praticien',
                        'tags' => ['Praticiens'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => [
                            '200' => ['description' => 'Détails du praticien'],
                            '404' => ['description' => 'Praticien non trouvé'],
                        ],
                    ],
                    'put' => [
                        'summary' => 'Mettre à jour un praticien',
                        'tags' => ['Praticiens'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => [
                            '200' => ['description' => 'Praticien mis à jour'],
                        ],
                    ],
                    'delete' => [
                        'summary' => 'Supprimer un praticien',
                        'tags' => ['Praticiens'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => [
                            '204' => ['description' => 'Praticien supprimé'],
                        ],
                    ],
                ],
                '/api/practitioners/{id}/availabilities' => [
                    'get' => [
                        'summary' => 'Obtenir les horaires et règles de travail du praticien',
                        'tags' => ['Disponibilités'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Plages de travail']],
                    ],
                    'post' => [
                        'summary' => 'Définir les horaires de travail ou exceptions d\'un praticien',
                        'tags' => ['Disponibilités'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Plages enregistrées']],
                    ],
                ],
                '/api/availabilities/{availabilityId}' => [
                    'delete' => [
                        'summary' => 'Supprimer une règle de disponibilité',
                        'tags' => ['Disponibilités'],
                        'parameters' => [['name' => 'availabilityId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['204' => ['description' => 'Disponibilité supprimée']],
                    ],
                ],
                '/api/practitioners/{id}/slots' => [
                    'get' => [
                        'summary' => 'Calculer en temps réel les créneaux disponibles pour réservation',
                        'tags' => ['Créneaux & Réservation'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'date', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date', 'example' => '2026-09-21']],
                            ['name' => 'start_date', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date', 'example' => '2026-09-21']],
                            ['name' => 'end_date', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date', 'example' => '2026-09-28']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Créneaux disponibles groupés par date'],
                        ],
                    ],
                ],
                '/api/practitioners/{id}/appointments' => [
                    'get' => [
                        'summary' => 'Consulter les rendez-vous du praticien',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'from', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                            ['name' => 'to', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['CONFIRMED', 'PENDING', 'CANCELLED', 'COMPLETED', 'NO_SHOW']]],
                        ],
                        'responses' => ['200' => ['description' => 'Liste des rendez-vous']],
                    ],
                ],
                '/api/patients' => [
                    'get' => [
                        'summary' => 'Lister et rechercher des patients',
                        'tags' => ['Patients'],
                        'parameters' => [['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Liste des patients']],
                    ],
                    'post' => [
                        'summary' => 'Créer un dossier patient',
                        'tags' => ['Patients'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['first_name', 'last_name', 'email', 'phone'],
                                        'properties' => [
                                            'first_name' => ['type' => 'string', 'example' => 'Jean'],
                                            'last_name' => ['type' => 'string', 'example' => 'Dupont'],
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'jean.dupont@email.local'],
                                            'phone' => ['type' => 'string', 'example' => '0612345678'],
                                            'birth_date' => ['type' => 'string', 'format' => 'date', 'example' => '1988-05-14'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Patient créé'],
                            '422' => ['description' => 'Erreur de validation'],
                        ],
                    ],
                ],
                '/api/patients/{id}' => [
                    'get' => [
                        'summary' => 'Détails d\'un patient',
                        'tags' => ['Patients'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Détails du patient']],
                    ],
                    'put' => [
                        'summary' => 'Modifier un patient',
                        'tags' => ['Patients'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Patient mis à jour']],
                    ],
                    'delete' => [
                        'summary' => 'Supprimer un patient',
                        'tags' => ['Patients'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['204' => ['description' => 'Patient supprimé']],
                    ],
                ],
                '/api/patients/{id}/appointments' => [
                    'get' => [
                        'summary' => 'Historique des rendez-vous du patient',
                        'tags' => ['Patients'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Rendez-vous du patient']],
                    ],
                ],
                '/api/appointments' => [
                    'post' => [
                        'summary' => 'Réserver un nouveau rendez-vous médical',
                        'description' => 'Valide la disponibilité du créneau chez le praticien, empêche les doubles réservations pour le praticien et le patient.',
                        'tags' => ['Rendez-vous'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['practitioner_id', 'patient_id', 'start_time'],
                                        'properties' => [
                                            'practitioner_id' => ['type' => 'string'],
                                            'patient_id' => ['type' => 'string'],
                                            'start_time' => ['type' => 'string', 'format' => 'date-time', 'example' => '2026-09-22T09:00:00+02:00'],
                                            'end_time' => ['type' => 'string', 'format' => 'date-time'],
                                            'duration_minutes' => ['type' => 'integer', 'example' => 30],
                                            'reason' => ['type' => 'string', 'example' => 'Consultation de suivi'],
                                            'notes' => ['type' => 'string', 'example' => 'Renouvellement ordonnance'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Rendez-vous réservé avec succès'],
                            '400' => ['description' => 'Créneau en dehors des disponibilités ou passé'],
                            '409' => ['description' => 'Conflit d\'horaire (praticien, patient ou calendrier personnel occupé)'],
                            '503' => ['description' => 'Calendrier personnel temporairement indisponible'],
                            '422' => ['description' => 'Paramètres invalides'],
                        ],
                    ],
                ],
                '/api/appointments/{id}' => [
                    'get' => [
                        'summary' => 'Détails d\'un rendez-vous',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Détails du rendez-vous']],
                    ],
                    'put' => [
                        'summary' => 'Modifier le motif ou les notes du rendez-vous',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Rendez-vous mis à jour']],
                    ],
                ],
                '/api/appointments/{id}/reschedule' => [
                    'patch' => [
                        'summary' => 'Reporter un rendez-vous (reprogrammation)',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['start_time'],
                                        'properties' => [
                                            'start_time' => ['type' => 'string', 'format' => 'date-time'],
                                            'duration_minutes' => ['type' => 'integer', 'example' => 30],
                                            'reason' => ['type' => 'string', 'example' => 'Empêchement professionnel'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Rendez-vous reporté avec succès'],
                            '409' => ['description' => 'Nouveau créneau indisponible'],
                            '503' => ['description' => 'Calendrier personnel temporairement indisponible'],
                        ],
                    ],
                ],
                '/api/appointments/{id}/cancel' => [
                    'patch' => [
                        'summary' => 'Annuler un rendez-vous',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['reason'],
                                        'properties' => [
                                            'reason' => ['type' => 'string', 'example' => 'Guérison spontanée / Annulation patient'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Rendez-vous annulé'],
                        ],
                    ],
                ],
                '/api/appointments/{id}/complete' => [
                    'patch' => [
                        'summary' => 'Marquer un rendez-vous comme honoré / terminé',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Rendez-vous terminé']],
                    ],
                ],
                '/api/appointments/{id}/no-show' => [
                    'patch' => [
                        'summary' => 'Déclarer une absence patient non excusée (No-show)',
                        'tags' => ['Rendez-vous'],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Absence enregistrée']],
                    ],
                ],
            ],
        ];

        return Response::json($schema);
    }

    public function docs(Request $request): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santé Connect API - Documentation Interactive</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .header { background: #0b5394; color: white; padding: 16px 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0 0; opacity: 0.85; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🩺 Santé Connect - API Prise de Rendez-vous Médicaux</h1>
        <p>Documentation interactive OpenAPI 3.0 & Swagger UI</p>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout"
            });
        };
    </script>
</body>
</html>
HTML;

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
