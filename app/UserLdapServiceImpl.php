<?php

namespace App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UserLdapServiceImpl implements \App\Services\UserLdapService
{
    private string $ldapApiUrl;
    private string $otpApiUrl;
    private array $otpConfig;

    public function __construct()
    {
        $this->ldapApiUrl = config('ldap.ldap.api_url');
        $this->otpApiUrl = config('ldap.otp.api_url');
        $this->otpConfig = config('ldap.otp.config');
    }

    /**
     * Authentification LDAP
     */
    public function authenticate(string $cuid, string $password): array
    {
        try {
            $date = Carbon::now()->timezone('UTC')->format('Y-m-d H:i:s');

            $xml = '<?xml version="1.0"?>
                    <COMMANDE>
                        <TYPE>AUTH_SVC</TYPE>
                        <APPLINAME>Ubora</APPLINAME>
                        <CUID>'.$cuid.'</CUID>
                        <PASSWORD>'.$password.'</PASSWORD>
                        <DATE>'.$date.'</DATE>
                    </COMMANDE>';

            Log::info('LDAP raw request', ['cuid' => $cuid, 'xml' => $xml]);
            Log::info('API endpoint: ' . $this->ldapApiUrl . rtrim(config('ldap.ldap.enpoint', '/ldap'), '/') . '/');

            // Faire l'appel HTTP au LDAP
            $response = Http::withBody($xml, 'application/xml')
                ->withHeaders([
                    'Accept' => '*/*',
                    'Content-Type' => 'application/xml',
                ])
                ->timeout(10)
                ->post($this->ldapApiUrl . rtrim(config('ldap.ldap.enpoint', '/ldap'), '/') . '/');

            // Log de la réponse
            Log::info('LDAP raw response', ['cuid' => $cuid, 'raw_xml' => $response->body()]);

            if ($response->failed()) {
                throw new Exception("Échec de connexion au serveur LDAP, HTTP status: ".$response->status());
            }

            // Parser la réponse XML
            $responseXml = $response->body();
            $xmlObject = simplexml_load_string($responseXml, "SimpleXMLElement", LIBXML_NOCDATA);

            // Convertir en tableau PHP
            $userData = json_decode(json_encode($xmlObject), true);

            $reqStatus = $userData['REQSTATUS'] ?? null;

            if ($reqStatus !== 'SUCCESS') {
                throw new Exception('Cuid ou mot de passe incorrect');
            }

            // Nettoyer les champs
            $userData['PHONENUMBER'] = $userData['PHONENUMBER'] ?? null;
            $userData['EMAIL'] = $userData['EMAIL'] ?? null;

            // Stocker les infos essentielles dans un format standard
            $userCacheData = [
                'cuid' => $userData['CUID'] ?? $cuid,
                'name' => $userData['FULLNAME'] ?? ($userData['NAME'] ?? null),
                'phone' => $userData['PHONENUMBER'] ?? null,
                'email' => $userData['EMAIL'] ?? null,
            ];

            // Formater le numéro si présent
            if (!empty($userCacheData['phone'])) {
                $userCacheData['phone'] = $this->formatPhoneNumber($userCacheData['phone']);
            }

            // Stocker dans le cache pour OTP
            cache()->put("ldap_user_{$cuid}", $userCacheData, now()->addMinutes(10));

            Log::info('LDAP user data parsed', [
                'cuid' => $userCacheData['cuid'],
                'name' => $userCacheData['name'],
                'email' => $userCacheData['email'],
                'has_phone' => !empty($userCacheData['phone']),
                'has_email' => !empty($userCacheData['email']),
            ]);

            return $userCacheData;

        } catch (Exception $e) {
            Log::error('LDAP authentication exception', [
                'cuid' => $cuid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    /**
     * Génération OTP avec le numéro de téléphone ou email de l'utilisateur
     */
    public function generateOtp(string $cuid): array
    {
        // Récupérer les données utilisateur depuis le cache
        $userData = cache()->get("ldap_user_{$cuid}");
        if (!$userData) {
            return [
                'success' => false,
                'message' => 'Données utilisateur non trouvées. Veuillez vous reconnecter.'
            ];
        }

        // Déterminer la référence (téléphone prioritaire, sinon email)
        $reference = null;
        $channel = null;

        if (!empty($userData['phone'])) {
            $reference = $userData['phone'];
            $channel = 'phone';
        } elseif (!empty($userData['email'])) {
            $reference = $userData['email'];
            $channel = 'email';
        }

        if (!$reference) {
            return [
                'success' => false,
                'message' => 'Aucun numéro de téléphone ni email valide trouvé pour votre compte.'
            ];
        }

        // Générer un identifiant de transaction sécurisé
        $transactionId = Str::uuid()->toString();
        $cacheKey = "otp_transaction_{$transactionId}";

        // Stocker le contexte de la transaction (5 minutes d’expiration)
        cache()->put($cacheKey, [
            'cuid' => $cuid,
            'reference' => $reference,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(5)
        ], 300); // 300 secondes = 5 minutes

        // Préparer le payload pour l’API OTP
        $payload = [
            'reference' => $reference,
            'origin' => $this->otpConfig['origin'] ?? 'Ubora',
            'otpOveroutLine' => $this->otpConfig['otpOveroutLine'] ?? 300,
            'customMessage' => $this->otpConfig['customMessage'] ?? "Votre code OTP est : {otp}",
            'senderName' => $channel == "email" ? 'Ubora@orange.com' : "Ubora",
            'ignoreOrangeNumbers' => $this->otpConfig['ignoreOrangeNumbers'] ?? false,
        ];

        // Appel à l’API OTP
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(config('ldap.otp.timeout', 10))
            ->retry(config('ldap.otp.retry', 2))
            ->post($this->otpApiUrl . config('ldap.otp.endpoint.generate', '/generate'), $payload);

            $responseData = $response->json();

            
            Log::info('OTP API response', [
                'cuid' => $cuid,
                'http_status' => $response->status(),
                'api_code' => $responseData['code'] ?? null,
                'message' => $responseData['message'] ?? null,
                'channel' => $channel
            ]);

        } catch (\Exception $e) {
            // Erreur réseau, timeout, etc.
            Log::error('OTP API call failed', [
                'cuid' => $cuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Service OTP temporairement indisponible. Veuillez réessayer plus tard.'
            ];
        }

        $apiCode = $responseData['code'] ?? 500;
        $apiMessage = $responseData['message'] ?? 'Erreur inconnue du service OTP.';

        if ($apiCode === 200) {
            // Succès : retourner transaction_id pour l’étape suivante
            return [
                'success' => true,
                'message' => 'Un code de vérification a été envoyé à votre ' . ($channel === 'phone' ? 'téléphone' : 'adresse e-mail') . '.',
                'transaction_id' => $transactionId,
                'channel' => $channel,
                'reference_masked' => $reference
            ];
        } else {
            // Échec : nettoyer le cache
            cache()->forget($cacheKey);

            Log::warning('OTP generation rejected by API', [
                'cuid' => $cuid,
                'api_code' => $apiCode,
                'message' => $apiMessage
            ]);

            return [
                'success' => false,
                'message' => $apiMessage
            ];
        }
    }





    /**
     * Vérification OTP
     */
    public function verifyOtp(string $cuid, string $otp): bool
    {
        $ldapUser = cache()->get("ldap_user_{$cuid}");

        if (!$ldapUser) {
            throw new Exception("Session expirée.");
        }

        $reference = !empty($ldapUser['phone'])
            ? $ldapUser['phone']
            : (!empty($ldapUser['email']) ? $ldapUser['email'] : null);

        if (!$reference) {
            throw new Exception("Aucune référence valide pour OTP.");
        }

        $payload = [
            'reference'   => $reference,
            'origin'      => config('ldap.otp.config.origin', 'Ubora'),
            'receivedOtp' => $otp,
        ];

        $response = Http::post(
            $this->otpApiUrl . config('ldap.otp.endpoint.check', '/check'),
            $payload
        );

        $data = $response->json();

        Log::info('OTP verification response', [
            'cuid' => $cuid,
            'payload' => $payload,
            'response' => $data,
        ]);

        if ($response->failed()) {
            return false;
        }

        return isset($data['code'], $data['diagnosticResult'])
            && (string)$data['code'] === '200'
            && $data['diagnosticResult'] === true;
    }



    public function searchUser(string $search){
        return DB::table('ldap')
            ->where('email',$search)
            ->orWhere("cuid",'LIKE',"%$search%")
            ->orWhere('name','LIKE',"%$search%")
            ->get();
    }


    public function findUserByCuid(string $cuid)
    {
        return DB::table('ldap')
            ->where('cuid',$cuid)
            ->firstOrFail();
    }

    /**
     * Formater le numéro de téléphone
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Supprimer tous les caractères non numériques
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Si le numéro commence par 0, le convertir en format international (Congo)
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '243' . substr($phone, 1); // Format RDC
        }

        // Si le numéro a 9 chiffres (sans le 0), ajouter l'indicatif
        if (strlen($phone) === 9) {
            $phone = '243' . $phone;
        }

        return $phone;
    }

    /**
     * Masquer partiellement le numéro de téléphone pour les logs
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return '***' . substr($phone, -1);
        }

        $firstPart = substr($phone, 0, 3);
        $lastPart = substr($phone, -2);
        $masked = str_repeat('*', strlen($phone) - 5);

        return $firstPart . $masked . $lastPart;
    }

    /**
     * Parser la réponse XML du LDAP
     */
    private function parseLdapResponse(string $xmlResponse): array
    {
        try {
            $xmlResponse = trim($xmlResponse);

            // Vérifier si c'est une erreur
            if (str_contains($xmlResponse, '<ERROR>') || str_contains($xmlResponse, '<error>')) {
                $error = $this->extractXmlValue($xmlResponse, ['ERROR', 'error', 'message', 'MESSAGE']);
                throw new Exception($error ?? "Authentification échouée.");
            }

            // Extraire les informations utilisateur
            $userData = [
                'cuid' => $this->extractXmlValue($xmlResponse, ['CUID', 'cuid', 'LOGIN', 'login']),
                'name' => $this->extractXmlValue($xmlResponse, ['NAME', 'name', 'NOM', 'nom', 'FULLNAME']),
                'email' => $this->extractXmlValue($xmlResponse, ['EMAIL', 'email', 'MAIL', 'mail']),
                'phone' => $this->extractXmlValue($xmlResponse, ['PHONE', 'phone', 'TELEPHONE', 'TELEPHONE', 'TEL', 'MOBILE', 'mobile']),
                'department' => $this->extractXmlValue($xmlResponse, ['DEPARTMENT', 'department', 'SERVICE', 'service']),
                'status' => $this->extractXmlValue($xmlResponse, ['STATUS', 'status']) ?? 'active',
            ];

            // Nettoyer et formater les données
            foreach ($userData as $key => &$value) {
                if (is_string($value)) {
                    $value = trim($value);

                    // Convertir les valeurs vides en null
                    if ($value === '') {
                        $value = null;
                    }
                }
            }

            Log::info('LDAP user data parsed', [
                'cuid' => $userData['cuid'],
                'name' => $userData['name'],
                'has_phone' => !empty($userData['phone']),
                'has_email' => !empty($userData['email'])
            ]);

            return $userData;

        } catch (Exception $e) {
            Log::error('Failed to parse LDAP response', [
                'error' => $e->getMessage(),
                'xml_preview' => substr($xmlResponse, 0, 300)
            ]);
            throw $e;
        }
    }

    /**
     * Extraire une valeur d'un XML
     */
    private function extractXmlValue(string $xml, array $tagNames): ?string
    {
        foreach ($tagNames as $tag) {
            $patterns = [
                "/<{$tag}>(.*?)<\/{$tag}>/si",
                "/<{$tag}[^>]*>(.*?)<\/{$tag}>/si",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $xml, $matches)) {
                    return trim($matches[1] ?? '');
                }
            }

            // Essayer en majuscule
            $upperTag = strtoupper($tag);
            if ($upperTag !== $tag) {
                $patterns = [
                    "/<{$upperTag}>(.*?)<\/{$upperTag}>/si",
                    "/<{$upperTag}[^>]*>(.*?)<\/{$upperTag}>/si",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $xml, $matches)) {
                        return trim($matches[1] ?? '');
                    }
                }
            }
        }

        return null;
    }
}