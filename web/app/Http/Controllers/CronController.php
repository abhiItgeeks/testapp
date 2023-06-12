<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class CronController extends Controller
{
    public function index(Request $request)
    {
        $shops = DB::table('sessions')->select('shop','access_token')->whereNotNull('access_token')->where('access_token','<>','')->get()->toArray();
        foreach ($shops as $domain) {
            $shop = explode('.',$domain->shop);
            $shop_url = $domain->shop;
            $token = $domain->access_token;
            $syncShops = DB::table('syncing')->where('shop',$shop[0])->where('sync',true)->first();
            if(!empty($syncShops)){
                $getProducts = DB::table($syncShops->shop.'_products')->select('*')->where('updated_status',false)->orWhere('created_status',false)->limit(25)->get()->toArray();
                // dd($getProducts);
                foreach ($getProducts as $getProduct) {
                    $product = (array)json_decode($getProduct->data);
                    
                    if(!empty($product['title'])){
                        $title = $product['title'];
                    }else{
                        $title = '';
                    }
                    if(!empty($product['sku'])){
                        $sku = $product['sku'];
                    }else{
                        $sku = '';
                    }
                    if(!empty($product['status'])){
                        $status = $product['status'];
                    }else{
                        $status = '';
                    }
                    if(!empty($product['benefits'])){
                        $benefits = $product['benefits'];
                    }else{
                        $benefits = '';
                    }
                    if(!empty($product['price'])){
                        $price = $product['price'];
                    }else{
                        $price = 0;
                    }
                    if(!empty($product['tags'])){
                        $tags = $product['tags'];
                    }else{
                        $tags = '';
                    }
                    $mainImageUrl = [];
                    if(!empty($product['mainImageUrl'])){
                        // $mainImageUrl['src'] = $product['mainImageUrl'];
                        array_push($mainImageUrl,(['src'=> $product['mainImageUrl']]));
                    }
                    if(!empty($product['secondaryImageUrls'])){
                        $secondaryImageUrls = $product['secondaryImageUrls'];
                        foreach ($secondaryImageUrls as $key) {
                            array_push($mainImageUrl,(['src'=> $key]));
                        }
                    }
                    if(!empty($product['channels'])){
                        if(in_array("POS",$product['channels'])){
                            $channels = 'global';
                        }else{
                            $channels = 'web';
                        }
                    }else{
                        $channels = 'web';
                    }
                    if(!empty($product['description'])){
                        $description = $product['description'];
                    }else{
                        $description = '';
                    }
                    if(!empty($product['vendor'])){
                        $vendor = $product['vendor'];
                    }else{
                        $vendor = '';
                    }
                    if(!empty($product['productType'])){
                        $productType = $product['productType'];
                    }else{
                        $productType = '';
                    }
                    if(!empty($product['inventory'])){
                        $inventory = $product['inventory'];
                    }else{
                        $inventory = 0;
                    }
                    $headers = [
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json'
                    ];
                    // dd($product);
                    $body = [
                        "product"=>[
                            'title'=>$title,
                            "body_html"=>$description,
                            "vendor"=>$vendor,
                            "status"=>strtolower($status),
                            "product_type"=>$productType,
                            "variants"=>[
                                [
                                    "price" => $price,
                                    "sku" => $sku,
                                    "inventory_management"=>'shopify',
                                    "inventory_quantity"=>$inventory
                                ]
                            ],
                            "tags"=>$tags,
                            "images"=>$mainImageUrl,
                            "published_scope"=>$channels,
                            // "template_suffix"=>"template1",
                            // "metafields"=>[
                            //     [
                            //         "key" => "new",
                            //         "value" => "newvalue",
                            //         "type" => "single_line_text_field",
                            //         "namespace" => "global"
                            //     ]
                            // ]
                        ]
                    ];
                    if (!$getProduct->created_status) {
                        $url = 'https://'.$shop_url.'/admin/api/2023-04/products.json';
                        $response = Http::withHeaders($headers)->post($url,$body);
                        $statusCode = $response->status();
                        $responseBody = json_decode($response->getBody(), true);
                        $product_id = $responseBody['product']['id'];
                        if($statusCode == 201){
                            DB::table($shop[0].'_products')->where('sku',$sku)->update([
                                'product_id'=>$product_id,
                                'created_status'=>1,
                                'updated_status'=>1,
                            ]);
                        }
                        // die;
                    }else if(!$getProduct->updated_status){
                        $id = $getProduct->product_id;
                        $body['product']['id'] = $id;
                        $url = 'https://'.$shop_url.'/admin/api/2023-04/products/'.$id.'.json';
                        $response = Http::withHeaders($headers)->PUT($url,$body);
                        $statusCode = $response->status();
                        $responseBody = json_decode($response->getBody(), true);
                        if($statusCode == 200){
                            DB::table($shop[0].'_products')->where('sku',$sku)->update([
                                'updated_status'=>1,
                            ]);
                        }
                    }
                    // die;
                    
                }
                if(empty($getProducts)){
                    DB::table('syncing')->where('shop',$shop[0])->update([
                        'sync'=>false,
                        'updated_at'=>date('Y/m/d H:i:s')
                    ]);
                }
            }
        }
    }
}
