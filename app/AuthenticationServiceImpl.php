<?php

namespace App;

use App\Services\UserService;
use App\Services\UserLdapService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\Request; 
use App\Services\AuthenticationService;

class AuthenticationServiceImpl implements \App\Services\AuthenticationService
{
    public function __construct(
        private UserService $userService,
        private UserLdapService $userLdapService
    ) {}

    /**
     *  Authentification LDAP
     */
    public function login(string $cuid, string $password): array
    {
        try {
            // Authentification LDAP
            $ldapUser = $this->userLdapService->authenticate($cuid, $password);


            // Générer OTP
            Log::info('Génération OTP', ['cuid' => $cuid]);
            $otpResult = $this->userLdapService->generateOtp($cuid);

            if (!$otpResult['success']) {
                return [
                    'success' => false,
                    'error' => $otpResult['message']
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'status' => 'otp_sent',
                    'cuid' => $cuid,
                    'message' => $otpResult['message'],
                    'transaction_id' => $otpResult['transaction_id'],
                    'channel' => $otpResult['channel'],
                    'reference_masked' => $otpResult['reference_masked'] ?? null
                ]
            ];

        } catch (\Exception $e) {
            // Analyser le message pour détecter les erreurs métier
            $message = $e->getMessage();

            // Cas 1 : identifiants invalides (venant de ton match dans l'ancien code)
            if (str_contains($message, 'Identifiants') || str_contains($message, 'Invalid credentials')) {
                return [
                    'success' => false,
                    'error' => 'Identifiants incorrects.'
                ];
            }

            // Cas 2 : compte bloqué
            if (str_contains($message, 'bloqué') || str_contains($message, 'locked')) {
                return [
                    'success' => false,
                    'error' => 'Votre compte est temporairement bloqué.'
                ];
            }

            // Cas 3 : autre erreur métier connue
            if (str_contains($message, 'Échec de l’authentification')) {
                return [
                    'success' => false,
                    'error' => 'Échec de l’authentification. Veuillez réessayer.'
                ];
            }

            throw $e;
        }
    }



    /**
     *  Vérification OTP
     */
    public function verifyOtp(string $cuid, string $otp): array
    {
        $ldapUser = cache()->get("ldap_user_{$cuid}");

        if (!$ldapUser) {
            throw new Exception("Session expirée.");
        }

        $email = $ldapUser['email'] ?? null;

        if (!$email) {
            throw new Exception("Email utilisateur introuvable.");
        }

        if (!$this->userLdapService->verifyOtp($cuid, $otp)) {
            throw new Exception("Code OTP incorrect.");
        }

        // Récupération locale via email
        $user = $this->userService->findByEmail($email);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Nettoyage
        cache()->forget("ldap_user_{$cuid}");

        return [
            'user'  => $user,
            'token'=> $token,
        ];
    }




    /**
     * Déconnexion
     */
    public function logout(): void
    {
        try {
            $user = auth()->user();
            if ($user) {
                // Supprimer le token courant
                $user->currentAccessToken()->delete();

                // Nettoyer le cache
                Cache::forget("ldap_user_{$user->cuid}");

                Log::info('User logged out', ['user_id' => $user->id]);
            }
        } catch (Exception $e) {
            Log::error('Logout exception', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            throw new Exception("Erreur lors de la déconnexion.");
        }
    }


    public function resendOtp(string $cuid): void
    {
        try {
            // Vérifier le délai minimum entre les envois (60 secondes)
            $lastSent = Cache::get("otp_last_sent_{$cuid}");

            if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
                $remaining = 60 - now()->diffInSeconds($lastSent);
                throw new Exception("Veuillez attendre {$remaining} secondes avant de redemander un code.");
            }

            // Envoyer l'OTP via le même channel
            $this->userLdapService->generateOtp($cuid);

            // Enregistrer l'heure d'envoi
            Cache::put("otp_last_sent_{$cuid}", now(), now()->addMinutes(2));

            Log::info('OTP resent', ['cuid' => $cuid]);

        } catch (Exception $e) {
            Log::error('OTP resend failed', [
                'cuid' => $cuid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }



    /**
     * Masquer le numéro pour l'affichage
     */
    private function maskPhoneForDisplay(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return '***' . substr($phone, -1);
        }

        // Afficher seulement les 4 derniers chiffres
        $visible = substr($phone, -4);
        return '*** **** ' . $visible;
    }

    /**
     * Masquer partiellement le numéro de téléphone
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

}