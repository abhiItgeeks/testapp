<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class ProductController extends Controller
{
    public function sync(Request $request)
    {
        $session = $request->get('shopifySession');
        $domain = $session->getShop();
        $shop = explode('.',$domain);
        
        $apiURL = env('API_URL');
        $auth = env('AUTH');
        $headers = [
            'Authorization' => 'Basic '.$auth,
        ];
        $response = Http::withHeaders($headers)->get($apiURL);
        $statusCode = $response->status();
        $responseBody = json_decode($response->getBody(), true);
        if(!Schema::hasTable('syncing')){
            Schema::create('syncing', function (Blueprint $table) {
                $table->id();
                $table->string('shop')->nullable(true);
                $table->boolean('sync')->nullable(true);
                $table->timestamps();
            });
        }
        $checkExistsync = DB::table('syncing')->select('*')->where('shop',$shop[0])->count();
        if($checkExistsync == 0){
            DB::table('syncing')->insert([
                'shop'=>$shop[0],
                'sync'=>true,
                'created_at'=>date('Y/m/d H:i:s')
            ]);
        }else{
            DB::table('syncing')->where('shop',$shop[0])->update([
                'sync'=>true,
                'updated_at'=>date('Y/m/d H:i:s')
            ]);
        }
        if(!Schema::hasTable($shop[0].'_products')){
            $tablecreate = Schema::create($shop[0].'_products', function (Blueprint $table) {
                $table->id();
                $table->string('product_id')->nullable(true);
                $table->string('title')->nullable(true);
                $table->longText('description')->nullable(true);
                $table->string('sku')->nullable(true);
                $table->string('price')->nullable(true);
                $table->string('status')->nullable(true);
                $table->string('benefits')->nullable(true);
                $table->string('tags')->nullable(true);
                $table->string('secondaryImageUrls')->nullable(true);
                $table->string('mainImageUrl')->nullable(true);
                $table->string('channels')->nullable(true);
                $table->longText('data')->nullable(true);
                $table->boolean('created_status')->nullable(true);
                $table->boolean('updated_status')->nullable(true);
                $table->boolean('is_online')->nullable(true);
                $table->timestamps();
            });
            
        }
        if(empty($responseBody)){
            DB::table('syncing')->where('shop',$shop[0])->update([
                'sync'=>false,
                'updated_at'=>date('Y/m/d H:i:s')
            ]);
            if(Schema::hasTable($shop[0].'_log')){
                DB::table($shop[0].'_log')->where('shop',$shop[0])->insert([
                    'message'=>'some errors occurred while fetching data from seles force',
                    'type'=>'error'
                ]);
            }
            return response(['status'=>false,'message'=>'some errors occurred while fetching data from seles force']);
        }
        foreach ($responseBody as $value) {
            if(!empty($value['title'])){
                $title = $value['title'];
            }else{
                $title = '';
            }
            if(!empty($value['sku'])){
                $sku = $value['sku'];
            }else{
                $sku = '';
            }
            if(!empty($value['status'])){
                $status = $value['status'];
            }else{
                $status = '';
            }
            if(!empty($value['benefits'])){
                $benefits = $value['benefits'];
            }else{
                $benefits = '';
            }
            if(!empty($value['price'])){
                $price = $value['price'];
            }else{
                $price = '';
            }
            if(!empty($value['tags'])){
                $tags = json_encode($value['tags']);
            }else{
                $tags = '';
            }
            if(!empty($value['secondaryImageUrls'])){
                $secondaryImageUrls = json_encode($value['secondaryImageUrls']);
            }else{
                $secondaryImageUrls = '';
            }
            if(!empty($value['mainImageUrl'])){
                $mainImageUrl = $value['mainImageUrl'];
            }else{
                $mainImageUrl = '';
            }
            if(!empty($value['channels'])){
                $channels = json_encode($value['channels']);
            }else{
                $channels = '';
            }
            if(!empty($value['description'])){
                $description = $value['description'];
            }else{
                $description = '';
            }
            $checkExist = DB::table($shop[0].'_products')->select('*')->where('sku',$sku)->count();
            if($checkExist == 0){
                DB::table($shop[0].'_products')->insert([
                    'title'=>$title,
                    'description'=>$description,
                    'sku'=>$sku,
                    'price'=>$price,
                    'status'=>$status,
                    'benefits'=>$benefits,
                    'tags'=>$tags,
                    'secondaryImageUrls'=>$secondaryImageUrls,
                    'mainImageUrl'=>$mainImageUrl,
                    'channels'=>$channels,
                    'data'=>json_encode($value),
                    'created_status'=>0,
                    'updated_status'=>0,
                    'created_at'=>date('Y/m/d H:i:s')
                ]);
            }else{
                DB::table($shop[0].'_products')->where('sku',$sku)->update([
                    'title'=>$title,
                    'description'=>$description,
                    'sku'=>$sku,
                    'price'=>$price,
                    'status'=>$status,
                    'benefits'=>$benefits,
                    'tags'=>$tags,
                    'secondaryImageUrls'=>$secondaryImageUrls,
                    'mainImageUrl'=>$mainImageUrl,
                    'channels'=>$channels,
                    'data'=>json_encode($value),
                    'updated_status'=>0,
                    'updated_at'=>date('Y/m/d H:i:s')
                ]);
            }
        }
        return response(['status'=>true,'message'=>$session->getShop()]);
    }
    public function checkStatus(Request $request)
    {
        $session = $request->get('shopifySession');
        $domain = $session->getShop();
        $shop = explode('.',$domain);
        if(!Schema::hasTable('syncing')){
            Schema::create('syncing', function (Blueprint $table) {
                $table->id();
                $table->string('shop')->nullable(true);
                $table->boolean('sync')->nullable(true);
                $table->timestamps();
            });
        }
        $status = DB::table('syncing')->select('*')->where('shop',$shop[0])->first();
        if(empty($status)){
            DB::table('syncing')->insert([
                'shop'=>$shop[0],
                'sync'=>false,
                'created_at'=>date('Y/m/d H:i:s')
            ]);
            return response(['status'=>true,'sync'=>false]);
        }else{
            return response(['status'=>true,'sync'=>$status->sync,'updated'=>$status->updated_at]);
        }
    }
    public function delete(Request $request) {
        $head = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
        $shop = explode('.',$head);
        $id = $request->id;
        if(Schema::hasTable($shop[0].'_products')){
            DB::table($shop[0].'_products')->where('product_id',$id)->delete();
        }
    }
}
