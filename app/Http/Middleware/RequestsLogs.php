<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestsLogs
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $body =  $request->all();
        $logged_user = "";
        if (isset($body["password"])) {
            unset($body["password"]);
        }
        if (Auth::check()) {
            $logged_user = Auth::user()->cuid;
        }
        $log = [
            'Client IP' => $this->getIpAdress(),
            'URL' => $request->getUri(),
            'METHOD' => $request->getMethod(),
            'BODY' => $body,
            'RESPONSE' => $response,
            'USER' => $logged_user
        ];

        //info(json_encode($log));
        $_SESSION['timeout'] = time();
        info('' . json_encode($log) . '\n');
        return $response;
    }

    public function getIpAdress(){
        if(!empty($_SERVER['HTTP_CLIENT_IP'])) {  
            $ip = $_SERVER['HTTP_CLIENT_IP'];  
            }  
        //whether ip is from the proxy  
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {  
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];  
        }  
        //whether ip is from the remote address  
        else{  
                $ip = $_SERVER['REMOTE_ADDR'];  
        }  
        return $ip;  
    }
}
