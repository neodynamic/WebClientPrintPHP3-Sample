<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Session;

//Includes WebClientPrint classes
include_once(app_path() . '\WebClientPrint\WebClientPrint.php');
use Neodynamic\SDK\Web\WebClientPrint;


class HomeController extends Controller
{
    public function index(){

        $wcppScript = WebClientPrint::createWcppDetectionScript(action('WebClientPrintController@processRequest'), Session::getId());    

        return view('home.index', ['wcppScript' => $wcppScript]);
    }

    public function samples(){
        return view('home.samples');
    }
    
}
