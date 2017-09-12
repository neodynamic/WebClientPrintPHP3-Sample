<?php

namespace Neodynamic\SDK\Web;
use Exception;
use ZipArchive;

// Setting WebClientPrint
WebClientPrint::$licenseOwner = '';
WebClientPrint::$licenseKey = '';

//Set wcpcache folder RELATIVE to WebClientPrint.php file
//FILE WRITE permission on this folder is required!!!
WebClientPrint::$wcpCacheFolder = 'wcpcache/';

/**
 * WebClientPrint provides functions for registering the "WebClientPrint for PHP" solution 
 * script code in PHP web pages as well as for processing client requests and managing the
 * internal cache.
 * 
 * @author Neodynamic <http://neodynamic.com/support>
 * @copyright (c) 2016, Neodynamic SRL
 * @license http://neodynamic.com/eula Neodynamic EULA
 */
class WebClientPrint {
   
    const VERSION = '3.0.2017.912';
    const CLIENT_PRINT_JOB = 'clientPrint';
    const WCP = 'WEB_CLIENT_PRINT';
    const WCP_SCRIPT_AXD_GET_PRINTERS = 'getPrinters';
    const WCPP_SET_PRINTERS = 'printers';
    const WCP_SCRIPT_AXD_GET_WCPPVERSION = 'getWcppVersion';
    const WCPP_SET_VERSION = 'wcppVer';
    const GEN_WCP_SCRIPT_URL = 'u';
    const GEN_DETECT_WCPP_SCRIPT = 'd';
    const SID = 'sid';
    const PING = 'wcppping';
    
    const WCP_CACHE_WCPP_INSTALLED = 'WCPP_INSTALLED';
    const WCP_CACHE_WCPP_VER = 'WCPP_VER';
    const WCP_CACHE_PRINTERS = 'PRINTERS';
    
    
    /**
     * Gets or sets the License Owner
     * @var string 
     */
    static $licenseOwner = '';
    /**
     * Gets or sets the License Key
     * @var string
     */
    static $licenseKey = '';
    /**
     * Gets or sets the ABSOLUTE URL to WebClientPrint.php file
     * @var string
     */
    static $webClientPrintAbsoluteUrl = '';
    /**
     * Gets or sets the wcpcache folder URL RELATIVE to WebClientPrint.php file. 
     * FILE WRITE permission on this folder is required!!!
     * @var string
     */
    static $wcpCacheFolder = '';
    
    /**
     * Adds a new entry to the built-in file system cache. 
     * @param string $sid The user's session id
     * @param string $key The cache entry key
     * @param string $val The data value to put in the cache
     * @throws Exception
     */
    public static function cacheAdd($sid, $key, $val){
        if (Utils::isNullOrEmptyString(self::$wcpCacheFolder)){
            throw new Exception('WebClientPrint wcpCacheFolder is missing, please specify it.');
        }
        if (Utils::isNullOrEmptyString($sid)){
            throw new Exception('WebClientPrint FileName cache is missing, please specify it.');
        }
        $cacheFileName = (Utils::strEndsWith(self::$wcpCacheFolder, '/')?self::$wcpCacheFolder:self::$wcpCacheFolder.'/').$sid.'.wcpcache';
        $dataWCPP_VER = '';
        $dataPRINTERS = '';
            
        if(file_exists($cacheFileName)){
            $cache_info = parse_ini_file($cacheFileName);
            
            $dataWCPP_VER = $cache_info[self::WCP_CACHE_WCPP_VER];
            $dataPRINTERS = $cache_info[self::WCP_CACHE_PRINTERS];
        }
        
        if ($key === self::WCP_CACHE_WCPP_VER){
            $dataWCPP_VER = self::WCP_CACHE_WCPP_VER.'='.'"'.$val.'"';
            $dataPRINTERS = self::WCP_CACHE_PRINTERS.'='.'"'.$dataPRINTERS.'"';
        } else if ($key === self::WCP_CACHE_PRINTERS){
            $dataWCPP_VER = self::WCP_CACHE_WCPP_VER.'='.'"'.$dataWCPP_VER.'"';
            $dataPRINTERS = self::WCP_CACHE_PRINTERS.'='.'"'.$val.'"';
        }

        $data = $dataWCPP_VER.chr(13).chr(10).$dataPRINTERS;
        $handle = fopen($cacheFileName, 'w') or die('Cannot open file:  '.$cacheFileName);  
        fwrite($handle, $data);
        fclose($handle);
        
    }
    
    /**
     * Gets a value from the built-in file system cache based on the specified sid & key 
     * @param string $sid The user's session id
     * @param string $key The cache entry key
     * @return string Returns the value from the cache for the specified sid & key if it's found; or an empty string otherwise.
     * @throws Exception
     */
    public static function cacheGet($sid, $key){
        if (Utils::isNullOrEmptyString(self::$wcpCacheFolder)){
            throw new Exception('WebClientPrint wcpCacheFolder is missing, please specify it.');
        }
        if (Utils::isNullOrEmptyString($sid)){
            throw new Exception('WebClientPrint FileName cache is missing, please specify it.');
        }
        $cacheFileName = (Utils::strEndsWith(self::$wcpCacheFolder, '/')?self::$wcpCacheFolder:self::$wcpCacheFolder.'/').$sid.'.wcpcache';
        if(file_exists($cacheFileName)){
            $cache_info = parse_ini_file($cacheFileName, FALSE, INI_SCANNER_RAW);
                
            if($key===self::WCP_CACHE_WCPP_VER || $key===self::WCP_CACHE_WCPP_INSTALLED){
                return $cache_info[self::WCP_CACHE_WCPP_VER];
            }else if($key===self::WCP_CACHE_PRINTERS){
                return $cache_info[self::WCP_CACHE_PRINTERS];
            }else{
                return '';
            }
        }else{
            return '';
        }
    }
    
    /**
     * Cleans the built-in file system cache
     * @param integer $minutes The number of minutes after any files on the cache will be removed.
     */
    public static function cacheClean($minutes){
        if (!Utils::isNullOrEmptyString(self::$wcpCacheFolder)){
            $cacheDir = (Utils::strEndsWith(self::$wcpCacheFolder, '/')?self::$wcpCacheFolder:self::$wcpCacheFolder.'/');
            if ($handle = opendir($cacheDir)) {
                 while (false !== ($file = readdir($handle))) {
                    if ($file!='.' && $file!='..' && (time()-filectime($cacheDir.$file)) > (60*$minutes)) {
                        unlink($cacheDir.$file);
                    }
                 }
                 closedir($handle);
            }
        }
    }
    
    /**
     * Returns script code for detecting whether WCPP is installed at the client machine.
     *
     * The WCPP-detection script code ends with a 'success' or 'failure' status.
     * You can handle both situation by creating two javascript functions which names 
     * must be wcppDetectOnSuccess() and wcppDetectOnFailure(). 
     * These two functions will be automatically invoked by the WCPP-detection script code.
     * 
     * The WCPP-detection script uses a delay time variable which by default is 10000 ms (10 sec). 
     * You can change it by creating a javascript global variable which name must be wcppPingDelay_ms. 
     * For example, to use 5 sec instead of 10, you should add this to your script: 
     *   
     * var wcppPingDelay_ms = 5000;
     *    
     * @param string $webClientPrintControllerAbsoluteUrl The Absolute URL to the WebClientPrintController file.
     * @param string $sessionID The current Session ID.
     * @return string A [script] tag linking to the WCPP-detection script code.
     * @throws Exception
     */
    public static function createWcppDetectionScript($webClientPrintControllerAbsoluteUrl, $sessionID){
        
        if (Utils::isNullOrEmptyString($webClientPrintControllerAbsoluteUrl) || 
            !Utils::strStartsWith($webClientPrintControllerAbsoluteUrl, 'http')){
            throw new Exception('WebClientPrintController absolute URL is missing, please specify it.');
        }
        if (Utils::isNullOrEmptyString($sessionID)){
            throw new Exception('Session ID is missing, please specify it.');
        }
        
        $url = $webClientPrintControllerAbsoluteUrl.'?'.self::GEN_DETECT_WCPP_SCRIPT.'='.$sessionID;
        return '<script src="'.$url.'" type="text/javascript"></script>';
         
    }
    
    
    /**
     * Returns a [script] tag linking to the WebClientPrint script code by using 
     * the specified URL for the client print job generation.
     * 
     * @param string $webClientPrintControllerAbsoluteUrl The Absolute URL to the WebClientPrintController file.
     * @param string $clientPrintJobAbsoluteUrl The Absolute URL to the PHP file that creates ClientPrintJob objects.
     * @paran string $sessionID The current Session ID.
     * @return string A [script] tag linking to the WebClientPrint script code by using the specified URL for the client print job generation.
     * @throws Exception
     */
    public static function createScript($webClientPrintControllerAbsoluteUrl, $clientPrintJobAbsoluteUrl, $sessionID){
        if (Utils::isNullOrEmptyString($webClientPrintControllerAbsoluteUrl) || 
            !Utils::strStartsWith($webClientPrintControllerAbsoluteUrl, 'http')){
            throw new Exception('WebClientPrintController absolute URL is missing, please specify it.');
        }
        if (Utils::isNullOrEmptyString($clientPrintJobAbsoluteUrl) || 
            !Utils::strStartsWith($clientPrintJobAbsoluteUrl, 'http')){
            throw new Exception('ClientPrintJob absolute URL is missing, please specify it.');
        }
        if (Utils::isNullOrEmptyString($sessionID)){
            throw new Exception('Session ID is missing, please specify it.');
        }
        
        
        $wcpHandler = $webClientPrintControllerAbsoluteUrl.'?';
        $wcpHandler .= self::VERSION;
        $wcpHandler .= '&';
        $wcpHandler .= microtime(true);
        $wcpHandler .= '&sid=';
        $wcpHandler .= $sessionID;
        $wcpHandler .= '&'.self::GEN_WCP_SCRIPT_URL.'=';
        $wcpHandler .= base64_encode($clientPrintJobAbsoluteUrl);
        return '<script src="'.$wcpHandler.'" type="text/javascript"></script>';
    }
    
    
    /**
     * Generates the WebClientPrint scripts based on the specified query string. Result is stored in the HTTP Response Content
     * 
     * @param type $webClientPrintControllerAbsoluteUrl The Absolute URL to the WebClientPrintController file.
     * @param type $queryString The Query String from current HTTP Request.
     */
    public static function generateScript($webClientPrintControllerAbsoluteUrl, $queryString)
    {
        if (Utils::isNullOrEmptyString($webClientPrintControllerAbsoluteUrl) || 
            !Utils::strStartsWith($webClientPrintControllerAbsoluteUrl, 'http')){
            throw new Exception('WebClientPrintController absolute URL is missing, please specify it.');
        }
        
        parse_str($queryString, $qs);
    
        if(isset($qs[self::GEN_DETECT_WCPP_SCRIPT])){
            
            $curSID = $qs[self::GEN_DETECT_WCPP_SCRIPT];
            $dynamicIframeId = 'i'.substr(uniqid(), 0, 3);
            $absoluteWcpAxd = $webClientPrintControllerAbsoluteUrl.'?'.self::SID.'='.$curSID;
            
            $s1 = 'dmFyIGpzV0NQUD0oZnVuY3Rpb24oKXt2YXIgc2V0PDw8LU5FTy1IVE1MLUlELT4+Pj1mdW5jdGlvbigpe2lmKHdpbmRvdy5jaHJvbWUpeyQoJyM8PDwtTkVPLUhUTUwtSUQtPj4+JykuYXR0cignaHJlZicsJ3dlYmNsaWVudHByaW50MzonK2FyZ3VtZW50c1swXSk7dmFyIGE9JCgnYSM8PDwtTkVPLUhUTUwtSUQtPj4+JylbMF07dmFyIGV2T2JqPWRvY3VtZW50LmNyZWF0ZUV2ZW50KCdNb3VzZUV2ZW50cycpO2V2T2JqLmluaXRFdmVudCgnY2xpY2snLHRydWUsdHJ1ZSk7YS5kaXNwYXRjaEV2ZW50KGV2T2JqKX1lbHNleyQoJyM8PDwtTkVPLUhUTUwtSUQtPj4+JykuYXR0cignc3JjJywnd2ViY2xpZW50cHJpbnQzOicrYXJndW1lbnRzWzBdKX19O3JldHVybntpbml0OmZ1bmN0aW9uKCl7aWYod2luZG93LmNocm9tZSl7JCgnPGEgLz4nLHtpZDonPDw8LU5FTy1IVE1MLUlELT4+Pid9KS5hcHBlbmRUbygnYm9keScpfWVsc2V7JCgnPGlmcmFtZSAvPicse25hbWU6Jzw8PC1ORU8tSFRNTC1JRC0+Pj4nLGlkOic8PDwtTkVPLUhUTUwtSUQtPj4+Jyx3aWR0aDonMScsaGVpZ2h0OicxJyxzdHlsZTondmlzaWJpbGl0eTpoaWRkZW47cG9zaXRpb246YWJzb2x1dGUnfSkuYXBwZW5kVG8oJ2JvZHknKX19LHBpbmc6ZnVuY3Rpb24oKXtzZXQ8PDwtTkVPLUhUTUwtSUQtPj4+KCc8PDwtTkVPLVBJTkctVVJMLT4+PicrKGFyZ3VtZW50cy5sZW5ndGg9PTE/JyYnK2FyZ3VtZW50c1swXTonJykpO3ZhciBkZWxheV9tcz0odHlwZW9mIHdjcHBQaW5nRGVsYXlfbXM9PT0ndW5kZWZpbmVkJyk/MDp3Y3BwUGluZ0RlbGF5X21zO2lmKGRlbGF5X21zPjApe3NldFRpbWVvdXQoZnVuY3Rpb24oKXskLmdldCgnPDw8LU5FTy1VU0VSLUhBUy1XQ1BQLT4+PicsZnVuY3Rpb24oZGF0YSl7aWYoZGF0YS5sZW5ndGg+MCl7d2NwcERldGVjdE9uU3VjY2VzcyhkYXRhKX1lbHNle3djcHBEZXRlY3RPbkZhaWx1cmUoKX19KX0sZGVsYXlfbXMpfWVsc2V7dmFyIGZuY1dDUFA9c2V0SW50ZXJ2YWwoZ2V0V0NQUFZlcix3Y3BwUGluZ1RpbWVvdXRTdGVwX21zKTt2YXIgd2NwcF9jb3VudD0wO2Z1bmN0aW9uIGdldFdDUFBWZXIoKXtpZih3Y3BwX2NvdW50PD13Y3BwUGluZ1RpbWVvdXRfbXMpeyQuZ2V0KCc8PDwtTkVPLVVTRVItSEFTLVdDUFAtPj4+Jyx7J18nOiQubm93KCl9LGZ1bmN0aW9uKGRhdGEpe2lmKGRhdGEubGVuZ3RoPjApe2NsZWFySW50ZXJ2YWwoZm5jV0NQUCk7d2NwcERldGVjdE9uU3VjY2VzcyhkYXRhKX19KTt3Y3BwX2NvdW50Kz13Y3BwUGluZ1RpbWVvdXRTdGVwX21zfWVsc2V7Y2xlYXJJbnRlcnZhbChmbmNXQ1BQKTt3Y3BwRGV0ZWN0T25GYWlsdXJlKCl9fX19fX0pKCk7JChkb2N1bWVudCkucmVhZHkoZnVuY3Rpb24oKXtqc1dDUFAuaW5pdCgpO2pzV0NQUC5waW5nKCl9KTs=';
                    
            $s2 = base64_decode($s1);
            $s2 = str_replace('<<<-NEO-HTML-ID->>>', $dynamicIframeId, $s2);
            $s2 = str_replace('<<<-NEO-PING-URL->>>', $absoluteWcpAxd.'&'.self::PING, $s2);
            $s2 = str_replace('<<<-NEO-USER-HAS-WCPP->>>', $absoluteWcpAxd, $s2);
            
            return $s2;
            
        }else if(isset($qs[self::GEN_WCP_SCRIPT_URL])){
            
            $clientPrintJobUrl = base64_decode($qs[self::GEN_WCP_SCRIPT_URL]);
            if (strpos($clientPrintJobUrl, '?')>0){
                $clientPrintJobUrl .= '&';
            }else{
                $clientPrintJobUrl .= '?';
            }
            $clientPrintJobUrl .= self::CLIENT_PRINT_JOB;
            $absoluteWcpAxd = $webClientPrintControllerAbsoluteUrl;
            $wcppGetPrintersParam = '-getPrinters:'.$absoluteWcpAxd.'?'.self::WCP.'&'.self::SID.'=';
            $wcpHandlerGetPrinters = $absoluteWcpAxd.'?'.self::WCP.'&'.self::WCP_SCRIPT_AXD_GET_PRINTERS.'&'.self::SID.'=';
            $wcppGetWcppVerParam = '-getWcppVersion:'.$absoluteWcpAxd.'?'.self::WCP.'&'.self::SID.'=';
            $wcpHandlerGetWcppVer = $absoluteWcpAxd.'?'.self::WCP.'&'.self::WCP_SCRIPT_AXD_GET_WCPPVERSION.'&'.self::SID.'=';
            $sessionIDVal = $qs[self::SID];
        
            $s1 = 'dmFyIGpzV2ViQ2xpZW50UHJpbnQ9KGZ1bmN0aW9uKCl7dmFyIHNldEE9ZnVuY3Rpb24oKXt2YXIgZV9pZD0naWRfJytuZXcgRGF0ZSgpLmdldFRpbWUoKTtpZih3aW5kb3cuY2hyb21lKXskKCdib2R5JykuYXBwZW5kKCc8YSBpZD1cIicrZV9pZCsnXCI+PC9hPicpOyQoJyMnK2VfaWQpLmF0dHIoJ2hyZWYnLCd3ZWJjbGllbnRwcmludDM6Jythcmd1bWVudHNbMF0pO3ZhciBhPSQoJ2EjJytlX2lkKVswXTt2YXIgZXZPYmo9ZG9jdW1lbnQuY3JlYXRlRXZlbnQoJ01vdXNlRXZlbnRzJyk7ZXZPYmouaW5pdEV2ZW50KCdjbGljaycsdHJ1ZSx0cnVlKTthLmRpc3BhdGNoRXZlbnQoZXZPYmopfWVsc2V7JCgnYm9keScpLmFwcGVuZCgnPGlmcmFtZSBuYW1lPVwiJytlX2lkKydcIiBpZD1cIicrZV9pZCsnXCIgd2lkdGg9XCIxXCIgaGVpZ2h0PVwiMVwiIHN0eWxlPVwidmlzaWJpbGl0eTpoaWRkZW47cG9zaXRpb246YWJzb2x1dGVcIiAvPicpOyQoJyMnK2VfaWQpLmF0dHIoJ3NyYycsJ3dlYmNsaWVudHByaW50MzonK2FyZ3VtZW50c1swXSl9c2V0VGltZW91dChmdW5jdGlvbigpeyQoJyMnK2VfaWQpLnJlbW92ZSgpfSw1MDAwKX07cmV0dXJue3ByaW50OmZ1bmN0aW9uKCl7c2V0QSgnVVJMX1BSSU5UX0pPQicrKGFyZ3VtZW50cy5sZW5ndGg9PTE/JyYnK2FyZ3VtZW50c1swXTonJykpfSxnZXRQcmludGVyczpmdW5jdGlvbigpe3NldEEoJ1VSTF9XQ1BfQVhEX1dJVEhfR0VUX1BSSU5URVJTX0NPTU1BTkQnKyc8PDwtTkVPLVNFU1NJT04tSUQtPj4+Jyk7dmFyIGRlbGF5X21zPSh0eXBlb2Ygd2NwcEdldFByaW50ZXJzRGVsYXlfbXM9PT0ndW5kZWZpbmVkJyk/MDp3Y3BwR2V0UHJpbnRlcnNEZWxheV9tcztpZihkZWxheV9tcz4wKXtzZXRUaW1lb3V0KGZ1bmN0aW9uKCl7JC5nZXQoJ1VSTF9XQ1BfQVhEX0dFVF9QUklOVEVSUycrJzw8PC1ORU8tU0VTU0lPTi1JRC0+Pj4nLGZ1bmN0aW9uKGRhdGEpe2lmKGRhdGEubGVuZ3RoPjApe3djcEdldFByaW50ZXJzT25TdWNjZXNzKGRhdGEpfWVsc2V7d2NwR2V0UHJpbnRlcnNPbkZhaWx1cmUoKX19KX0sZGVsYXlfbXMpfWVsc2V7dmFyIGZuY0dldFByaW50ZXJzPXNldEludGVydmFsKGdldENsaWVudFByaW50ZXJzLHdjcHBHZXRQcmludGVyc1RpbWVvdXRTdGVwX21zKTt2YXIgd2NwcF9jb3VudD0wO2Z1bmN0aW9uIGdldENsaWVudFByaW50ZXJzKCl7aWYod2NwcF9jb3VudDw9d2NwcEdldFByaW50ZXJzVGltZW91dF9tcyl7JC5nZXQoJ1VSTF9XQ1BfQVhEX0dFVF9QUklOVEVSUycrJzw8PC1ORU8tU0VTU0lPTi1JRC0+Pj4nLHsnXyc6JC5ub3coKX0sZnVuY3Rpb24oZGF0YSl7aWYoZGF0YS5sZW5ndGg+MCl7Y2xlYXJJbnRlcnZhbChmbmNHZXRQcmludGVycyk7d2NwR2V0UHJpbnRlcnNPblN1Y2Nlc3MoZGF0YSl9fSk7d2NwcF9jb3VudCs9d2NwcEdldFByaW50ZXJzVGltZW91dFN0ZXBfbXN9ZWxzZXtjbGVhckludGVydmFsKGZuY0dldFByaW50ZXJzKTt3Y3BHZXRQcmludGVyc09uRmFpbHVyZSgpfX19fSxnZXRXY3BwVmVyOmZ1bmN0aW9uKCl7c2V0QSgnVVJMX1dDUF9BWERfV0lUSF9HRVRfV0NQUFZFUlNJT05fQ09NTUFORCcrJzw8PC1ORU8tU0VTU0lPTi1JRC0+Pj4nKTt2YXIgZGVsYXlfbXM9KHR5cGVvZiB3Y3BwR2V0VmVyRGVsYXlfbXM9PT0ndW5kZWZpbmVkJyk/MDp3Y3BwR2V0VmVyRGVsYXlfbXM7aWYoZGVsYXlfbXM+MCl7c2V0VGltZW91dChmdW5jdGlvbigpeyQuZ2V0KCdVUkxfV0NQX0FYRF9HRVRfV0NQUFZFUlNJT04nKyc8PDwtTkVPLVNFU1NJT04tSUQtPj4+JyxmdW5jdGlvbihkYXRhKXtpZihkYXRhLmxlbmd0aD4wKXt3Y3BHZXRXY3BwVmVyT25TdWNjZXNzKGRhdGEpfWVsc2V7d2NwR2V0V2NwcFZlck9uRmFpbHVyZSgpfX0pfSxkZWxheV9tcyl9ZWxzZXt2YXIgZm5jV0NQUD1zZXRJbnRlcnZhbChnZXRDbGllbnRWZXIsd2NwcEdldFZlclRpbWVvdXRTdGVwX21zKTt2YXIgd2NwcF9jb3VudD0wO2Z1bmN0aW9uIGdldENsaWVudFZlcigpe2lmKHdjcHBfY291bnQ8PXdjcHBHZXRWZXJUaW1lb3V0X21zKXskLmdldCgnVVJMX1dDUF9BWERfR0VUX1dDUFBWRVJTSU9OJysnPDw8LU5FTy1TRVNTSU9OLUlELT4+PicseydfJzokLm5vdygpfSxmdW5jdGlvbihkYXRhKXtpZihkYXRhLmxlbmd0aD4wKXtjbGVhckludGVydmFsKGZuY1dDUFApO3djcEdldFdjcHBWZXJPblN1Y2Nlc3MoZGF0YSl9fSk7d2NwcF9jb3VudCs9d2NwcEdldFZlclRpbWVvdXRTdGVwX21zfWVsc2V7Y2xlYXJJbnRlcnZhbChmbmNXQ1BQKTt3Y3BHZXRXY3BwVmVyT25GYWlsdXJlKCl9fX19LHNlbmQ6ZnVuY3Rpb24oKXtzZXRBLmFwcGx5KHRoaXMsYXJndW1lbnRzKX19fSkoKTs=';
    
            $s2 = base64_decode($s1);
            $s2 = str_replace('URL_PRINT_JOB', $clientPrintJobUrl, $s2);
            $s2 = str_replace('URL_WCP_AXD_WITH_GET_PRINTERS_COMMAND', $wcppGetPrintersParam, $s2);
            $s2 = str_replace('URL_WCP_AXD_GET_PRINTERS', $wcpHandlerGetPrinters, $s2);
            $s2 = str_replace('URL_WCP_AXD_WITH_GET_WCPPVERSION_COMMAND', $wcppGetWcppVerParam, $s2);
            $s2 = str_replace('URL_WCP_AXD_GET_WCPPVERSION', $wcpHandlerGetWcppVer, $s2);
            $s2 = str_replace('<<<-NEO-SESSION-ID->>>', $sessionIDVal, $s2);
            
            return $s2;
        }
        
    }
    
       
    /**
     * Generates printing script.
     */
    const GenPrintScript = 0;
    /**
     * Generates WebClientPrint Processor (WCPP) detection script.
     */ 
    const GenWcppDetectScript = 1;
    /**
     * Sets the installed printers list in the website cache.
     */        
    const ClientSetInstalledPrinters = 2;
    /**
     * Gets the installed printers list from the website cache.
     */
    const ClientGetInstalledPrinters = 3;
    /**
     * Sets the WebClientPrint Processor (WCPP) Version in the website cache.
     */
    const ClientSetWcppVersion = 4;
    /**
     * Gets the WebClientPrint Processor (WCPP) Version from the website cache.
     */
    const ClientGetWcppVersion = 5;
    
    /**
     * Determines the type of process request based on the Query String value. 
     * 
     * @param string $queryString The query string of the current request.
     * @return integer A valid type of process request. In case of an invalid value, an Exception is thrown.
     * @throws Exception 
     */
    public static function GetProcessRequestType($queryString){
        parse_str($queryString, $qs);
    
        if(isset($qs[self::SID])){
            if(isset($qs[self::PING])){
                return self::ClientSetWcppVersion;
            } else if(isset($qs[self::WCPP_SET_VERSION])){
                return self::ClientSetWcppVersion;
            } else if(isset($qs[self::WCPP_SET_PRINTERS])){
                return self::ClientSetInstalledPrinters;
            } else if(isset($qs[self::WCP_SCRIPT_AXD_GET_WCPPVERSION])){
                return self::ClientGetWcppVersion;
            } else if(isset($qs[self::WCP_SCRIPT_AXD_GET_PRINTERS])){
                return self::ClientGetInstalledPrinters;
            } else if(isset($qs[self::GEN_WCP_SCRIPT_URL])){
                return self::GenPrintScript;
            } else {
                return self::ClientGetWcppVersion;
            }
        } else if(isset($qs[self::GEN_DETECT_WCPP_SCRIPT])){
            return self::GenWcppDetectScript;
        } else {
            throw new Exception('No valid ProcessRequestType was found in the specified QueryString.');
        }
    }
    
}

/**
 * The base class for all kind of printers supported at the client side.
 */
abstract class ClientPrinter{
    
    public $printerId;
    public function serialize(){
        
    }
}

/**
 * It represents the default printer installed in the client machine.
 */
class DefaultPrinter extends ClientPrinter{
    public function __construct() {
        $this->printerId = chr(0);
    }
    
    public function serialize() {
        return $this->printerId;
    }
}

/**
 * It represents a printer installed in the client machine with an associated OS driver.
 */
class InstalledPrinter extends ClientPrinter{
    
    /**
     * Gets or sets the name of the printer installed in the client machine. Default value is an empty string.
     * @var string 
     */
    public $printerName = '';

    /**
     * Gets or sets whether to print to Default printer in case of the specified one is not found or missing. Default is False.
     * @var boolean 
     */
    public $printToDefaultIfNotFound = false;
    
    /**
     * Creates an instance of the InstalledPrinter class with the specified printer name.
     * @param string $printerName The name of the printer installed in the client machine.
     */
    public function __construct($printerName) {
        $this->printerId = chr(1);
        $this->printerName = $printerName;
    }
    
    public function serialize() {
        
        if (Utils::isNullOrEmptyString($this->printerName)){
             throw new Exception("The specified printer name is null or empty.");
        }
        
        if ($this->printToDefaultIfNotFound){
            return $this->printerId.$this->printerName.Utils::SER_SEP.'1';     
        }  else {
            return $this->printerId.$this->printerName;    
        }      
        
    }
}

/**
 * It represents a printer which is connected through a parallel port in the client machine.
 */
class ParallelPortPrinter extends ClientPrinter{
    
    /**
     * Gets or sets the parallel port name, for example LPT1. Default value is "LPT1"
     * @var string 
     */
    public $portName = "LPT1";

    /**
     * Creates an instance of the ParallelPortPrinter class with the specified port name.
     * @param string $portName The parallel port name, for example LPT1.
     */
    public function __construct($portName) {
        $this->printerId = chr(2);
        $this->portName = $portName;
    }
    
    public function serialize() {
        
        if (Utils::isNullOrEmptyString($this->portName)){
             throw new Exception("The specified parallel port name is null or empty.");
        }
        
        return $this->printerId.$this->portName;
    }
}

/**
 * It represents a printer which is connected through a serial port in the client machine.
 */
class SerialPortPrinter extends ClientPrinter{
    
    /**
     * Gets or sets the serial port name, for example COM1. Default value is "COM1"
     * @var string 
     */
    public $portName = "COM1";
    /**
     * Gets or sets the serial port baud rate in bits per second. Default value is 9600
     * @var integer 
     */
    public $baudRate = 9600;
    /**
     * Gets or sets the serial port parity-checking protocol. Default value is NONE = 0
     * NONE = 0, ODD = 1, EVEN = 2, MARK = 3, SPACE = 4
     * @var integer 
     */
    public $parity = SerialPortParity::NONE;
    /**
     * Gets or sets the serial port standard number of stopbits per byte. Default value is ONE = 1
     * ONE = 1, TWO = 2, ONE_POINT_FIVE = 3
     * @var integer
     */
    public $stopBits = SerialPortStopBits::ONE;
    /**
     * Gets or sets the serial port standard length of data bits per byte. Default value is 8
     * @var integer
     */
    public $dataBits = 8;
    /**
     * Gets or sets the handshaking protocol for serial port transmission of data. Default value is XON_XOFF = 1
     * NONE = 0, REQUEST_TO_SEND = 2, REQUEST_TO_SEND_XON_XOFF = 3, XON_XOFF = 1
     * @var integer
     */
    public $flowControl = SerialPortHandshake::XON_XOFF;
    
    /**
     * Creates an instance of the SerialPortPrinter class wiht the specified information.
     * @param string $portName The serial port name, for example COM1.
     * @param integer $baudRate The serial port baud rate in bits per second.
     * @param integer $parity The serial port parity-checking protocol.
     * @param integer $stopBits The serial port standard number of stopbits per byte.
     * @param integer $dataBits The serial port standard length of data bits per byte.
     * @param integer $flowControl The handshaking protocol for serial port transmission of data.
     */
    public function __construct($portName, $baudRate, $parity, $stopBits, $dataBits, $flowControl) {
        $this->printerId = chr(3);
        $this->portName = $portName;
        $this->baudRate = $baudRate;
        $this->parity = $parity;
        $this->stopBits = $stopBits;
        $this->dataBits = $dataBits;
        $this->flowControl = $flowControl;
    }
    
    public function serialize() {
        
        if (Utils::isNullOrEmptyString($this->portName)){
             throw new Exception("The specified serial port name is null or empty.");
        }
        
        return $this->printerId.$this->portName.Utils::SER_SEP.$this->baudRate.Utils::SER_SEP.$this->dataBits.Utils::SER_SEP.((int)$this->flowControl).Utils::SER_SEP.((int)$this->parity).Utils::SER_SEP.((int)$this->stopBits);
    }
}

/**
 * It represents a Network IP/Ethernet printer which can be reached from the client machine.
 */
class NetworkPrinter extends ClientPrinter{
    
    /**
     * Gets or sets the DNS name assigned to the printer. Default is an empty string
     * @var string 
     */
    public $dnsName = "";
    /**
     * Gets or sets the Internet Protocol (IP) address assigned to the printer. Default value is an empty string
     * @var string 
     */
    public $ipAddress = "";
    /**
     * Gets or sets the port number assigned to the printer. Default value is 0
     * @var integer 
     */
    public $port = 0;
    
    /**
     * Creates an instance of the NetworkPrinter class with the specified DNS name or IP Address, and port number.
     * @param string $dnsName The DNS name assigned to the printer.
     * @param string $ipAddress The Internet Protocol (IP) address assigned to the printer.
     * @param integer $port The port number assigned to the printer.
     */
    public function __construct($dnsName, $ipAddress, $port) {
        $this->printerId = chr(4);
        $this->dnsName = $dnsName;
        $this->ipAddress = $ipAddress;
        $this->port = $port;
    }
    
    public function serialize() {
        
        if (Utils::isNullOrEmptyString($this->dnsName) && Utils::isNullOrEmptyString($this->ipAddress)){
             throw new Exception("The specified network printer settings is not valid. You must specify the DNS Printer Name or its IP address.");
        }
        
        return $this->printerId.$this->dnsName.Utils::SER_SEP.$this->ipAddress.Utils::SER_SEP.$this->port;
    }
}

/**
 *  It represents a printer which will be selected by the user in the client machine. The user will be prompted with a print dialog.
 */
class UserSelectedPrinter extends ClientPrinter{
    public function __construct() {
        $this->printerId = chr(5);
    }
    
    public function serialize() {
        return $this->printerId;
    }
}

/**
 * Specifies the parity bit for Serial Port settings. 
 */
class SerialPortParity{
    const NONE = 0;
    const ODD = 1;
    const EVEN = 2;
    const MARK = 3;
    const SPACE = 4;
}

/**
 * Specifies the number of stop bits used for Serial Port settings.
 */
class SerialPortStopBits{
    const NONE = 0;
    const ONE = 1;
    const TWO = 2;
    const ONE_POINT_FIVE = 3;
}

/**
 * Specifies the control protocol used in establishing a serial port communication.
 */
class SerialPortHandshake{
    const NONE = 0;
    const REQUEST_TO_SEND = 2;
    const REQUEST_TO_SEND_XON_XOFF = 3;
    const XON_XOFF = 1;
}

/**
 * It represents a file in the server that will be printed at the client side.
 */
class PrintFile{
    
    /**
     * Gets or sets the path of the file at the server side that will be printed at the client side.
     * @var string 
     */
    public $filePath = '';
    /**
     * Gets or sets the file name that will be created at the client side. 
     * It must include the file extension like .pdf, .txt, .doc, .xls, etc.
     * @var string 
     */
    public $fileName = '';
    /**
     * Gets or sets the binary content of the file at the server side that will be printed at the client side.
     * @var string 
     */
    public $fileBinaryContent = '';
    
    /**
     * Gets or sets the num of copies for printing this file. Default is 1.
     * @var integer
     */
    public $copies = 1;
    
    const PREFIX = 'wcpPF:';
    const SEP = '|';
        
    /**
     * 
     * @param string $filePath The path of the file at the server side that will be printed at the client side.
     * @param string $fileName The file name that will be created at the client side. It must include the file extension like .pdf, .txt, .doc, .xls, etc.
     * @param string $fileBinaryContent The binary content of the file at the server side that will be printed at the client side.
     */
    public function __construct($filePath, $fileName, $fileBinaryContent) {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->fileBinaryContent = $fileBinaryContent;
        
    }
    
    public function serialize() {
        $file = str_replace('\\', 'BACKSLASHCHAR',$this->fileName );
        if($this->copies > 1){
             $pfc = 'PFC='.$this->copies;
             $file = substr($file, 0, strrpos($file, '.')).$pfc.substr($file, strrpos($file, '.'));
        }
        return self::PREFIX.$file.self::SEP.$this->getFileContent();
    }
    
    public function getFileContent(){
        $content = $this->fileBinaryContent;
        if(!Utils::isNullOrEmptyString($this->filePath)){
            $handle = fopen($this->filePath, 'rb');
            $content = fread($handle, filesize($this->filePath));
            fclose($handle);
        }
        return $content;
    }
}

/**
 * Some utility functions used by WebClientPrint for PHP solution.
 */
class Utils{
    const SER_SEP = "|";
    
    static function isNullOrEmptyString($s){
        return (!isset($s) || trim($s)==='');
    }
    
    static function formatHexValues($s){
        
        $buffer = '';
            
        $l = strlen($s);
        $i = 0;

        while ($i < $l)
        {
            if ($s[$i] == '0')
            {
                if ($i + 1 < $l && ($s[$i] == '0' && $s[$i + 1] == 'x'))
                {
                    if ($i + 2 < $l &&
                        (($s[$i + 2] >= '0' && $s[$i + 2] <= '9') || ($s[$i + 2] >= 'a' && $s[$i + 2] <= 'f') || ($s[$i + 2] >= 'A' && $s[$i + 2] <= 'F')))
                    {
                        if ($i + 3 < $l &&
                           (($s[$i + 3] >= '0' && $s[$i + 3] <= '9') || ($s[$i + 3] >= 'a' && $s[$i + 3] <= 'f') || ($s[$i + 3] >= 'A' && $s[$i + 3] <= 'F')))
                        {
                            try{
                                $buffer .= chr(intval(substr($s, $i, 4),16));
                                $i += 4;
                                continue;
                                
                            } catch (Exception $ex) {
                                throw new Exception("Invalid hex notation in the specified printer commands at index: ".$i);
                            }
                                
                            
                        }
                        else
                        {
                            try{
                                
                                $buffer .= chr(intval(substr($s, $i, 3),16));
                                $i += 3;
                                continue;
                                
                            } catch (Exception $ex) {
                                throw new ArgumentException("Invalid hex notation in the specified printer commands at index: ".$i);
                            }
                        }
                    }
                }
            }

            $buffer .= substr($s, $i, 1);
            
            $i++;
        }

        return $buffer;
        
    }
    
    public static function intToArray($i){
        return pack('C4',
            ($i >>  0) & 0xFF,
            ($i >>  8) & 0xFF,
            ($i >> 16) & 0xFF,
            ($i >> 24) & 0xFF
         );
    }
        
    public static function strleft($s1, $s2) {
	return substr($s1, 0, strpos($s1, $s2));
    }
    
    public static function strContains($s1, $s2){
        return (strpos($s1, $s2) !== false);
    }
    
    public static function strEndsWith($s1, $s2)
    {
        return substr($s1, -strlen($s2)) === $s2;
    }
    
    public static function strStartsWith($s1, $s2)
    {
        return substr($s1, 0, strlen($s2)) === $s2;
    }
    
}

/**
 * Specifies information about the print job to be processed at the client side.
 */
class ClientPrintJob{
    
    /**
     * Gets or sets the ClientPrinter object. Default is NULL.
     * The ClientPrinter object refers to the kind of printer that the client machine has attached or can reach.
     * - Use a DefaultPrinter object for using the default printer installed in the client machine.
     * - Use a InstalledPrinter object for using a printer installed in the client machine with an associated Windows driver.
     * - Use a ParallelPortPrinter object for using a printer which is connected through a parallel port in the client machine.
     * - Use a SerialPortPrinter object for using a printer which is connected through a serial port in the client machine.
     * - Use a NetworkPrinter object for using a Network IP/Ethernet printer which can be reached from the client machine.
     * @var ClientPrinter 
     */
    public $clientPrinter = null;
    /**
     * Gets or sets the printer's commands in text plain format. Default is an empty string.
     * @var string 
     */
    public $printerCommands = '';
    /**
     * Gets or sets the num of copies for Printer Commands. Default is 1.
     * Most Printer Command Languages already provide commands for printing copies. 
     * Always use that command instead of this property. 
     * Refer to the printer command language manual or specification for further details.
     * @var integer 
     */
    public $printerCommandsCopies = 1;
    /**
     * Gets or sets whether the printer commands have chars expressed in hexadecimal notation. Default is false.
     * The string set to the $printerCommands property can contain chars expressed in hexadecimal notation.
     * Many printer languages have commands which are represented by non-printable chars and to express these commands 
     * in a string could require many concatenations and hence be not so readable.
     * By using hex notation, you can make it simple and elegant. Here is an example: if you need to encode ASCII 27 (escape), 
     * then you can represent it as 0x27.        
     * @var boolean 
     */
    public $formatHexValues = false;
    /**
     * Gets or sets the PrintFile object to be printed at the client side. Default is NULL.
     * @var PrintFile 
     */
    public $printFile = null;
    /**
     * Gets or sets an array of PrintFile objects to be printed at the client side. Default is NULL.
     * @var array 
     */
    public $printFileGroup = null;
    
    
    /**
     * Sends this ClientPrintJob object to the client for further processing.
     * The ClientPrintJob object will be processed by the WCPP installed at the client machine.
     * @return string A string representing a ClientPrintJob object.
     */
    public function sendToClient(){
        
        $cpjHeader = chr(99).chr(112).chr(106).chr(2);
        
        $buffer = '';
        
        if (!Utils::isNullOrEmptyString($this->printerCommands)){
            if ($this->printerCommandsCopies > 1){
                $buffer .= 'PCC='.$this->printerCommandsCopies.Utils::SER_SEP;
            }
            if($this->formatHexValues){
                $buffer .= Utils::formatHexValues ($this->printerCommands);
            } else {
                $buffer .= $this->printerCommands;
            }
        } else if (isset ($this->printFile)){
            $buffer = $this->printFile->serialize();
        } else if (isset ($this->printFileGroup)){
            $buffer = 'wcpPFG:';
            $zip = new ZipArchive;
            $cacheFileName = (Utils::strEndsWith(WebClientPrint::$wcpCacheFolder, '/')?WebClientPrint::$wcpCacheFolder:WebClientPrint::$wcpCacheFolder.'/').'PFG'.uniqid().'.zip';
            $res = $zip->open($cacheFileName, ZipArchive::CREATE);
            if ($res === TRUE) {
                foreach ($this->printFileGroup as $printFile) {
                    $file = $printFile->fileName;
                    if($printFile->copies > 1){
                        $pfc = 'PFC='.$printFile->copies;
                        $file = substr($file, 0, strrpos($file, '.')).$pfc.substr($file, strrpos($file, '.'));
                    }   
                    $zip->addFromString($file, $printFile->getFileContent());
                }
                $zip->close();
                $handle = fopen($cacheFileName, 'rb');
                $buffer .= fread($handle, filesize($cacheFileName));
                fclose($handle);
                unlink($cacheFileName);
            } else {
                $buffer='Creating PrintFileGroup failed. Cannot create zip file.';
            }
        }
        
        $arrIdx1 = Utils::intToArray(strlen($buffer));
        
        if (!isset($this->clientPrinter)){
            $this->clientPrinter = new UserSelectedPrinter();
        }    
        
        $buffer .= $this->clientPrinter->serialize();
        
        $arrIdx2 = Utils::intToArray(strlen($buffer));
        
        $lo = '';
        if(Utils::isNullOrEmptyString(WebClientPrint::$licenseOwner)){
            $lo = substr(uniqid(), 0, 8);
        }  else {
            $lo = 'php>'.base64_encode(WebClientPrint::$licenseOwner);
        }
        $lk = '';
        if(Utils::isNullOrEmptyString(WebClientPrint::$licenseKey)){
            $lk = substr(uniqid(), 0, 8);
        }  else {
            $lk = WebClientPrint::$licenseKey;
        }
        $buffer .= $lo.chr(124).$lk;
        
        return $cpjHeader.$arrIdx1.$arrIdx2.$buffer;
    }
    
}

/**
 * Specifies information about a group of ClientPrintJob objects to be processed at the client side.
 */
class ClientPrintJobGroup{
    
    /**
     * Gets or sets an array of ClientPrintJob objects to be processed at the client side. Default is NULL.
     * @var array 
     */
    public $clientPrintJobGroup = null;
    
    /**
     * Sends this ClientPrintJobGroup object to the client for further processing.
     * The ClientPrintJobGroup object will be processed by the WCPP installed at the client machine.
     * @return string A string representing a ClientPrintJobGroup object.
     */
    public function sendToClient(){
        
        if (isset ($this->clientPrintJobGroup)){
            $groups = count($this->clientPrintJobGroup);
            
            $dataPartIndexes = Utils::intToArray($groups);
            
            $cpjgHeader = chr(99).chr(112).chr(106).chr(103).chr(2);
        
            $buffer = '';
            
            $cpjBytesCount = 0;
            
            foreach ($this->clientPrintJobGroup as $cpj) {
                $cpjBuffer = '';
                
                if (!Utils::isNullOrEmptyString($cpj->printerCommands)){
                    if ($cpj->printerCommandsCopies > 1){
                        $cpjBuffer .= 'PCC='.$cpj->printerCommandsCopies.Utils::SER_SEP;
                    }
                    if($cpj->formatHexValues){
                        $cpjBuffer .= Utils::formatHexValues ($cpj->printerCommands);
                    } else {
                        $cpjBuffer .= $cpj->printerCommands;
                    }
                } else if (isset ($cpj->printFile)){
                    $cpjBuffer = $cpj->printFile->serialize();
                } else if (isset ($cpj->printFileGroup)){
                    $cpjBuffer = 'wcpPFG:';
                    $zip = new ZipArchive;
                    $cacheFileName = (Utils::strEndsWith(WebClientPrint::$wcpCacheFolder, '/')?WebClientPrint::$wcpCacheFolder:WebClientPrint::$wcpCacheFolder.'/').'PFG'.uniqid().'.zip';
                    $res = $zip->open($cacheFileName, ZipArchive::CREATE);
                    if ($res === TRUE) {
                        foreach ($cpj->printFileGroup as $printFile) {
                            $file = $printFile->fileName;
                            if($printFile->copies > 1){
                                $pfc = 'PFC='.$printFile->copies;
                                $file = substr($file, 0, strrpos($file, '.')).$pfc.substr($file, strrpos($file, '.'));
                            }   
                            $zip->addFromString($file, $printFile->getFileContent());
                        }
                        $zip->close();
                        $handle = fopen($cacheFileName, 'rb');
                        $cpjBuffer .= fread($handle, filesize($cacheFileName));
                        fclose($handle);
                        unlink($cacheFileName);
                    } else {
                        $cpjBuffer='Creating PrintFileGroup failed. Cannot create zip file.';
                    }
                }

                $arrIdx1 = Utils::intToArray(strlen($cpjBuffer));

                if (!isset($cpj->clientPrinter)){
                    $cpj->clientPrinter = new UserSelectedPrinter();
                }    

                $cpjBuffer .= $cpj->clientPrinter->serialize();
                    
                $cpjBytesCount += strlen($arrIdx1.$cpjBuffer);
 
                $dataPartIndexes .= Utils::intToArray($cpjBytesCount);
 
                $buffer .= $arrIdx1.$cpjBuffer;
            }
                    
            
            $lo = '';
            if(Utils::isNullOrEmptyString(WebClientPrint::$licenseOwner)){
                $lo = substr(uniqid(), 0, 8);
            }  else {
                $lo = 'php>'.base64_encode(WebClientPrint::$licenseOwner);
            }
            $lk = '';
            if(Utils::isNullOrEmptyString(WebClientPrint::$licenseKey)){
                $lk = substr(uniqid(), 0, 8);
            }  else {
                $lk = WebClientPrint::$licenseKey;
            }
            $buffer .= $lo.chr(124).$lk;

            return $cpjgHeader.$dataPartIndexes.$buffer;    
        
        
        } else {
            
            return NULL;
        }
            
        
    }
}