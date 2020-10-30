<?php

namespace App\Http\Controllers;
use App\Path;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FindController extends Controller
{
   
    //traverse paths array from the back
    public function explore_back($path)
    {
        // Start from last seen node in DB (If application instance stopped for some reason)
          if ($path == '1'){

            $path = Path::latest()->first();
            $path = $path->path;

          }

          $paths = [$path];
       
          while ($paths){


            $path = array_pop($paths); // get node at back of paths array

            $seen_path = Path::where('path',$path)->first();

            //skip if node is seen and not a starting point
            if($seen_path && count($paths) >= 1){
                continue;
            }
            $response  = $this->CallAPI($path);
            
            //if request did not encounter a server error
            if(!$response->failed()){

                   
                    if($response->status() == 302 || $response->status() == 208){

                        $this->CallAPIAudience($path.'/audience-submissions');

                    };
                    
                    $seen_path = Path::where('path',$path)->first();

                    if(!$seen_path){
                        Path::create(['path'=>$path]);
                    }

                    $response = $response->json(); 
                
                    $new_paths =  $response['paths'];
                    $encryption = $response['encryption'];

                    $decrypted_paths = $this->decryptPaths($encryption,$new_paths);
                    
                    //if starting node, put new paths into cache (For futher exporing in new/another application instance)
                    if(count($paths) == 0){

                        Cache::put('back',$decrypted_paths); 
                    }

                    $paths = array_merge($paths,$decrypted_paths);
            }
            else{
                //put item back into paths array
                array_push($paths,$path);
            }
            

          }
          return 'Done Exploring!';
    }

    //traverse paths array from the front
    public function explore_front($path)
    {

         // Start from last seen node in DB (If application instance stopped for some reason)
          if ($path == '1'){

            $path = Path::latest()->first();
            $path = $path->path;

          }

          $paths = [$path];
       
          while ($paths){


            $path = array_shift($paths); // get node at front of paths array

            $seen_path = Path::where('path',$path)->first();

            //skip if node is seen and not a starting point
            if($seen_path && count($paths) >= 1){
                continue;
            }
            
            
            $response  = $this->CallAPI($path);
            
            //if request did not encounter a server error
            if(!$response->failed()){

                   
                    if($response->status() == 302 || $response->status() == 208){

                        $this->CallAPIAudience($path.'/audience-submissions');

                    };
                    
                    $seen_path = Path::where('path',$path)->first();

                    if(!$seen_path){
                        Path::create(['path'=>$path]);
                    }

                    $response = $response->json(); 
                
                    $new_paths =  $response['paths'];
                    $encryption = $response['encryption'];

                    $decrypted_paths = $this->decryptPaths($encryption,$new_paths);
                    
                    //if starting node, put new paths into cache (For futher exporing in new/another application instance)
                    if(count($paths) == 0){

                        Cache::put('front',$decrypted_paths); 
                    }

                    $paths = array_merge($paths,$decrypted_paths);
                    
            }
            else{

                //put item back into paths array
                array_push($paths,$path);
            }
            

          }
          return 'Done Exploring!';
    }

    function CallAPI($url)
    {
            $response = Http::retry(3600, 900)->withHeaders([
                'accountId' => env('id'),
                'Authorization' =>'Bearer '.env('token')
            ])->get(env('API').$url);

           return $response;    
        
    }
    

    public function CallAPIAudience($url)
    {
       $response = Http::post(env('API').$url,[
                'accountId' => env('id'),
                'accountType' => 'buycoins'
            ]);
    
    }



  public function decryptPaths($encryption,$paths_to_decrypt)
  {
    
    $paths = [];

    foreach ($paths_to_decrypt as $path_to_decrypt){

        $data = $path_to_decrypt['cipherId'];

        if(Cache::get($data)){

            $data  = Cache::get($data);

        }else{

            for($i=0; $i < $path_to_decrypt['n']; $i++){

                $data = openssl_decrypt(hex2bin($data), $encryption['algorithm'], $encryption['key'], OPENSSL_RAW_DATA , substr($encryption['key'],0,16));

            }

            Cache::put($path_to_decrypt['cipherId'],$data);
            
        }

        

        $seen_path = Path::where('path',$data)->first();
        
        if(!$seen_path){
            $paths[] = $data;
        }
  

    }

    return $paths;

  }

  function decodeHeader($header){  
	//process in one line units, and treat ":” as a delimiter  
	$result = array(); 
	foreach (explode("\n", $header) as $i=>$line) { 
		$temp = explode(":",$line); 
		$temp = array_map('trim',$temp);  //trim each element  
		if ( isset($temp[0]) and isset($temp[1]) ){  
			// process only the data separated by ”:” 
			$result[$temp[0]] = $temp[1];
		} 
	}
	return $result; 
}


  // peek nodes yet to be explored for futher exploration in another application instance
  public function getCahe()
  {
      $cache = [];
      $paths = [];
        
      $cache = array_merge($paths,Cache::get('front'));
      $cache = array_merge($cache,Cache::get('back'));

      foreach($cache as $path){

        $seen_path = Path::where('path',$path)->first();
        if(!$seen_path){
            $paths[] = $path;
        }
        
      }
      return dd($paths);
  }


  public function clearDB()
  {     
      Cache::flush();
      Path::query()->delete();
  }

}
