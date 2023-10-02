<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

session_start();

class LoginController extends Controller
{
    private $ldap;


    // fonction pour l'authntification ds utilisateurs
    public function Login(Request $req)
    {
        try {

            //on recupère le cuid et la mot de passe entrés par l'utilisateurs dans la requète
            $cuid = $req->cuid;
            $password = $req->password;

            //vérification que si l'utilisateur est dans la BD de l'application
            $user = $this->checkUser($cuid);
            info($user);

            if ($user == 'erreur') {
                //s'il y a une erreur lors de la vérificatin
                $loginstatus =  'check-user-error';
                Log::info($loginstatus);
                return $this->responseBuilder($loginstatus);
            } else if (empty($user)) {
                //si l'objet est vide, on a pas trouvé le cuid dans la BDD de l'application
                $loginstatus = 'check-user-failed';
                Log::info($loginstatus);
                return $this->responseBuilder($loginstatus);
            } else {
                //la vérification a réussie
                $loginstatus = 'check-user-success';
                Log::info($loginstatus);
            }

            // La requête vers ldap pour l'authentification
            $date = now();

            $client = new Client;

            $body = '<?xml version="1.0"?>
            <COMMAND>
            <TYPE>AUTH_SVC</TYPE>
            <APPLINAME>' . config('app.name') . '</APPLINAME>
            <CUID>' . $cuid . '</CUID>
            <PASSWORD>' . $password . '</PASSWORD>
            <DATE>' . $date . '</DATE>       
            </COMMAND>';


            $headers = [
                'Content-Type' => 'application/xml',
            ];



            $res = $client->request('POST', config('settings.ldap'), [
                'headers' => $headers,
                'body' => $body
            ]);

            // conversion de la réponse xml en array
            $content = (array) simplexml_load_string($res->getBody()->getContents());
            Log::info("LDAP response" . json_encode($content) . '');

            //on recupère les elements de la réponse si elle n'est pas vide
            if (!empty($content)) {
                $this->ldap = [
                    'CUID' => $content["CUID"],
                    'DATE' => $content["DATE"],
                    'FULLNAME' => $content["FULLNAME"],
                    'DESCRIPTION' => $content["DESCRIPTION"],
                    'PHONENUMBER' => $content["PHONENUMBER"],
                    'EMAIL' => $content["EMAIL"],
                    'REQSTATUS' =>  $content["REQSTATUS"],

                ];


                if ($this->ldap['REQSTATUS'] == "SUCCESS") {
                    // si l'authentification a réussi on appel la fonction pour update l'utilisateurs si c'est sa prmière connexion ou si il y a des changements ldap
                    $loginstatus = 'ldap-auth-success';
                    Log::info($loginstatus);

                    $updateduser = $this->updateUser($user, $this->ldap, $password);
                    if ($updateduser == "Update-user-error") {
                        // si le update echoue
                        $loginstatus = "update-user-error";
                        return $this->responseBuilder($loginstatus);
                    }

                    $loginstatus = $updateduser;
                    Log::info($loginstatus);
                    /* return $loginstatus; */

                    //sinon on envoi la requète pour génerer l'otp et l'envoyer par sms
                    $sendOtp =  $this->sendOtp($this->ldap);
                    $loginstatus = $sendOtp;
                    Log::info($loginstatus);
                    return $this->responseBuilder($loginstatus);
                } else {
                    //l'authentification a échoué
                    $loginstatus = 'ldap-auth-failed';
                    Log::info($loginstatus);
                    return $this->responseBuilder($loginstatus);
                }
            } else {
                //la réponse est vide
                $loginstatus =  'ldap-not-found';
                Log::info($loginstatus);
                return $this->responseBuilder($loginstatus);
            }
        } catch (\Exception $e) {

            Log::error($e->getMessage());
            $loginstatus =  'ldap-error';
            return $this->responseBuilder($loginstatus);
        }
    }

    //fonction pour vérifier que l'utilisateur est dans la BDD de l'application
    private function checkUser($cuid)
    {
        try {
            $user = User::where('cuid', $cuid)
                ->first();
            return $user;
        } catch (\Throwable $th) {
            /* Log::error($th->getMessage()); */
            Log::error($th);
            return "erreur";
        }
    }

    //fonction pour mettre à jour les information de l'utilissateur daans la BDD
    private function updateUser($user, $ldap, $password)
    {
        if ($user->password == "" || $user->fullname == "" || $user->description == "" ||  $user->email == "" || $user->msisdn == "") {
            try {
                
                $udpateduser = User::where('cuid', $user->cuid)
                    ->update([
                        'fullname' => $ldap['FULLNAME'],
                        'description' => $ldap['DESCRIPTION'],
                        'email' => $ldap['EMAIL'],
                        'phonenumber' =>  str_replace(' ', '', $ldap['PHONENUMBER']),
                        'pass' =>  Hash::make(Str::random(10))
                    ]);
                Log::info($udpateduser);
                return "User updated";
            } catch (\Throwable $e) {
                Log::error($e->getMessage());
                return "Update-user-error";
            }
        } elseif (!(Hash::check($password, $user->password))) {
            try {
                $udpateduser = User::where('cuid', $user->cuid)
                    ->update([
                        'pass' =>  Hash::make($password)
                    ]);
                Log::info($udpateduser);
                return "User-password-updated";
            } catch (\Throwable $e) {
                Log::error($e->getMessage());
                return "Update-user-error";
            }
        } else {
            return "Nothing to update";
        }
    }


    //Generation et envoi du code OTP
    private function sendOtp($ldap)
    {
        $msisdn = $ldap["PHONENUMBER"];
        $msisdn = str_replace(' ', '', $msisdn);
        $msisdn = substr($msisdn, -9);
        $msisdn =  '0' . $msisdn . '';

        $_SESSION['phonenumber'] = $msisdn;
        
      /*   $_SESSION['email'] = $ldap["EMAIL"]; */

        $client = new Client;

        /*         $body1 = json_encode([
            "reference" => $msisdn,
            "origin"  => "Ubora Assessments",
            "receivedOtp"  => "",
            "otpOveroutTime"  => 300000,
            "customMessage"  => "",
            "senderName"  => "Ubora Assessments"

        ]);

        $body2 = json_encode([
            "reference" => $ldap["EMAIL"],
            "origin"  => "Ubora Assessments",
            "receivedOtp"  => "",
            "otpOveroutTime"  => 300000,
            "customMessage"  => "",
            "senderName"  => "Ubora Assessments"

        ]);



        $headers = [
            'Content-Type' => 'application/json',
        ]; */

        try {

            /*   $res = $client->request('POST', config('settings.otp_generate'), [
                'headers' => $headers,
                'body' => $body1
            ]);

            $content = $res->getBody()->getContents();
            Log::info("OTP generate API:" . json_encode($content) . ''); */

            $content = $this->sendOtpRequest($ldap["PHONENUMBER"], 'Ubora Assessments');
            info($content);
            if ($content["code"] == 200) {
                //log message
                $_SESSION['reference'] = $msisdn;
                $_SESSION['senderName']= 'Ubora Assessments';
                return 'OTP-generate-sucess';
            } elseif ($content["code"] == 400) {

                $content = $this->sendOtpRequest($ldap["EMAIL"], 'ubora.otp@orange.com');

                if ($content["code"] == 200) {
                    $_SESSION['reference'] = $ldap["EMAIL"];
                    $_SESSION['senderName']= 'ubora.otp@orange.com';
                    return 'OTP-generate-sucess';
                } else {
                    return 'OTP-generate-failed';
                }
            } else {return $content;}
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return "OTP-error";
        }
    }


    private function sendOtpRequest($reference, $senderName)
    {
        try {
            $body1 = json_encode([
                "reference" => $reference,
                "origin"  => $senderName,
                "receivedOtp"  => "",
                "otpOveroutTime"  => 300000,
                "customMessage"  => "",
                "senderName"  => $senderName
    
            ]);
    
            $headers = [
                'Content-Type' => 'application/json',
            ];
    
            $client = new Client;
    
            $res = $client->request('POST', config('settings.otp_generate'), [
                'headers' => $headers,
                'body' => $body1
            ]);
    
            info($body1);
    
            $content = $res->getBody()->getContents();
            Log::info("OTP generate API:" . json_encode($content) . '');
            if (!empty($content)) {
                $content = json_decode($content, true);
                return $content;
            } else {
                return 'OTP-not-found';
            }
        } catch (\Throwable $th) {
           Log::error($th->getMessage());
           $content['code'] = 400;
           return  $content;
        }
        
    }


    //vérification du code OTP entré par l'utilisateur
    public function checkOtp(Request $req)
    {
        //on recupère le code dans la requête
        $userOtp = $req->all();
        info($userOtp['reference']);
        info($userOtp['senderName']);

        $reference = $userOtp['reference'];
       /*  $msisdn = str_replace(' ', '', $msisdn);
        $msisdn = substr($msisdn, -9); */

        // la requête pour vérififer le code
        $client = new Client;
        $body = json_encode([
            "reference" =>  $reference ,
            "origin"  => $userOtp['senderName'],
            "receivedOtp"  => $userOtp['userOtp'],
            "senderName"  => $userOtp['senderName']

        ]);

        $headers = [
            'Content-Type' => 'application/json',
        ];

        try {

            $res = $client->request('POST', config('settings.otp_check'), [
                'headers' => $headers,
                'body' => $body
            ]);

            $content = $res->getBody()->getContents();
            $content = (array) json_decode($content);
            Log::info("OTP check API" . json_encode($content) . '');

            if (!empty($content)) {
                // si la vérification est réussie on authentifie l'utilisateur: récuperer ses info en BDD 
                if ($content['diagnosticResult'] == true) {
                    $user = new User;
                    $user = User::where('phonenumber', '=', substr_replace($userOtp['reference'],'+243',0,1))
                        ->orWhere('email','=',$userOtp['reference'])
                        ->first();
                    info($user);
                    if (!empty($user)) {

                        //déconnecter les autres sessions
                        /*    Auth::logoutOtherDevices($user->pass); */
                        Auth::login($user);
                        $req->session()->regenerate();
                        $status = 'user-logged';
                        /*     Log::info(Auth::user()->cuid); */
                        Log::info($status);
                        return response()->json([
                            'code' => 200,
                            'description' => "Success",
                            'message' => "Login Success",
                            'user' => $user
                        ]);
                    } else {

                        //on ne retrouve pas l'utilisateur en BDD
                        $status = 'user-loggin-failed';
                        info($status);
                        return $this->responseBuilder($status);
                        /*    return  redirect()->route('login')->with("auth_error", "Vous n'êtes pas autorisé à utiliser l'application"); */
                    }
                } else {
                    // la verficatio OTP a échouée;
                    $status = 'OTP-check-failed';
                    return $this->responseBuilder($status);
                    /*   return  redirect()->route('/')->with("auth_error", "Code OTP Invalide"); */
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Log::error($e->getMessage());
            $status = "OTP-check-error";
            return $this->responseBuilder($status);
                /* return  redirect()->route('login')->with("auth_error", "Erreur interne du serveur") */;
        }
    }


    private function responseBuilder($status)
    {
        switch ($status) {
            case 'ldap-auth-failed':
                return response()->json([
                    'code' => 401,
                    'description' => 'Unauthorized',
                    'message' => "Echec d'autentification, veuillez saisir les bons identifiants",
                ]);
                break;
            case  'ldap-not-found':
                return response()->json([
                    'code' => 404,
                    'description' => 'Not found',
                    'message' => "Not found",
                ]);
                break;
            case 'ldap-error':
                return response()->json([
                    'code' => 500,
                    'description' => 'Error',
                    'message' => "Erreur interne du serveur",
                ]);
                break;

            case 'check-user-error':
                return response()->json([
                    'code' => 500,
                    'description' => 'Error',
                    'message' => "Erreur interne du serveur",
                ]);
                break;

            case 'check-user-failed':
                return response()->json([
                    'code' => 401,
                    'description' => "",
                    'message' => "Vous n'êtes pas autorisé à utiliser l'application"
                ]);
                break;

            case "update-user-error":
                return response()->json([
                    'code' => 500,
                    'description' => 'Error',
                    'message' => "Erreur interne du serveur",
                ]);

            case 'OTP-generate-sucess':
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'message' => "OTP Code generated successfully",
                    'reference' => $_SESSION['reference'],
                    'senderName' => $_SESSION['senderName']
                ]);
                break;


            case 'OTP-generate-failed':
                return response()->json([
                    'code' => 400,
                    'description' => "Bad request",
                    'message' => "Bad request"
                ]);
                break;


            case 'OTP-not-found':
                return response()->json([
                    'code' => 404,
                    'description' => "Not found",
                    'message' => "OTP-not-found"
                ]);
                break;

            case 'OTP-error':
                return response()->json([
                    'code' => 500,
                    'description' => "Erreur",
                    'message' => "Erreur interne du serveur"
                ]);
                break;

            case 'user-logged':
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'message' => "Login Success",
                    'user' => Auth::user()
                ]);
                break;

            case 'user-loggin-failed':
                return response()->json([
                    'code' => 401,
                    'description' => "Vous n'êtes pas autorisé à utiliser l'application",
                    'message' => "Vous n'êtes pas autorisé à utiliser l'application"
                ]);
                break;

            case 'OTP-check-failed':
                return response()->json([
                    'code' => 400,
                    'description' => "Invalid OTP",
                    'message' => "Code OTP Invalide"
                ]);
                break;

            case 'OTP-check-error':
                return response()->json([
                    'code' => 500,
                    'description' => "OTP Check Error",
                    'message' => "Erreur interne du serveur"
                ]);
                break;

            default:
                return response()->json([
                    'code' => 500,
                    'message' => "Erreur interne du serveur",
                ]);
                break;
        }
    }
}
