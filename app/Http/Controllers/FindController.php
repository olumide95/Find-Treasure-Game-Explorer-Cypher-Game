<?php

namespace App\Http\Controllers;
use App\Path;
use Illuminate\Support\Facades\Cache;

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
            
            
            [$response_code,$response,$header]  = $this->CallAPI($path);
            
            //if request did not encounter a server error
            if(!preg_match('/5[0-9][0-9]/', $response_code)){

                    while($response_code === 429){

                        $sleep_time = ($header["X-RateLimit-Reset"]-time())%60;
                    
                        if($sleep_time > 0){
                            sleep($sleep_time);
                        }

                        [$response_code,$response,$header] = $this->CallAPI($path);

                    }

                    if($response_code == 302 || $response_code == 208 || $response_code == 200){
                            
                        $seen_path = Path::where('path',$path)->first();
                        if(!$seen_path){
                            Path::create(['path'=>$path]);
                        }
                    
                        if(is_object(json_decode($response))){
                            
                            $new_paths =  json_decode($response)->paths;
                            $encryption = json_decode($response)->encryption;

                            $new_paths = $this->decryptPaths($encryption,$new_paths);
                            
                            //if starting node, put new paths into cache (For futher exporing in new/another application instance)
                            if(count($paths) == 0){
                                Cache::put('back',$new_paths);
                            }

                            $paths = array_merge($paths,$new_paths);
                        }
                        
                    }
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
            
            
            [$response_code,$response,$header]  = $this->CallAPI($path);
            
            //if request did not encounter a server error
            if(!preg_match('/5[0-9][0-9]/', $response_code)){

                    while($response_code === 429){

                        $sleep_time = ($header["X-RateLimit-Reset"]-time())%60;
                    
                        if($sleep_time > 0){
                            sleep($sleep_time);
                        }

                        [$response_code,$response,$header] = $this->CallAPI($path);

                    }

                    if($response_code == 302 || $response_code == 208 || $response_code == 200){
                            
                        $seen_path = Path::where('path',$path)->first();
                        if(!$seen_path){
                            Path::create(['path'=>$path]);
                        }
                    
                        if(is_object(json_decode($response))){
                            $new_paths =  json_decode($response)->paths;
                            $encryption = json_decode($response)->encryption;

                            $new_paths = $this->decryptPaths($encryption,$new_paths);
                            
                            //if starting node, put new paths into cache (For futher exporing in new/another application instance)
                            if(count($paths) == 0){

                                Cache::put('front',$new_paths); 
                            }

                            $paths = array_merge($paths,$new_paths);
                        }
                        
                    }
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
            $curl = curl_init();
 
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'gomoney: '.env('phone'),
                'Authorization: Bearer '.env('token')
            ));

            // Optional Authentication:
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

            curl_setopt($curl, CURLOPT_URL, env('API').$url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, true);

            $result = curl_exec($curl);
            
            $response_info = curl_getinfo($curl); //store info related to results  
            $response_code = $response_info['http_code']; //HTTP status code for communication result  
            $response_header_size = $response_info['header_size']; //header size for communication result  
            curl_close($curl); //close communication 

            //confirm the details of the header info because within the response header info is also included rate limit info,   
            $response_header = substr($result, 0, $response_header_size);  // clip the header  
            $response_body = substr($result, $response_header_size);  //clip the body  

            //check the header 
            $array_header = $this->decodeHeader($response_header);  //disassemble the header  
            
            // continue calling endpoint until it responds
            if(preg_match('/5[0-9][0-9]/', $response_code)){

                return $this->CallAPI($url);
            }

            return [$response_code,$response_body,$array_header];    

        
    }



  public function decryptPaths($encryption,$paths_to_decrypt)
  {

    $paths = [];

    foreach ($paths_to_decrypt as $path_to_decrypt){

        $data = $path_to_decrypt->cipherId;

        for($i=0; $i < $path_to_decrypt->n; $i++){

            $data = openssl_decrypt(hex2bin($data), $encryption->algorithm, $encryption->key, OPENSSL_RAW_DATA , substr($encryption->key,0,16));

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
