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
            $headers = [
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json'
            ];
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
                    if(!empty($product['crmId'])){
                        array_push($tags,$product['crmId']);
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
                    if (!$getProduct->created_status || !$getProduct->updated_status) {
                        $query = 'query{
                            products(query: "sku:'.$sku.'", first: 1) {
                                nodes {
                                id
                                variants(first: 1) {
                                    nodes {
                                    id  
                                    sku
                                    }
                                }
                            }
                            }
                        }';
                        $checkProducturl = 'https://'.$shop_url.'/admin/api/2023-04/graphql.json';
                        $checkProductresponse = Http::withHeaders($headers)->post($checkProducturl,["query"=>$query]);
                        $checkProductstatusCode = $checkProductresponse->status();
                        $checkProduct = json_decode($checkProductresponse->getBody(), true);
                        if(!empty($checkProduct['data']['products']['nodes'])){
                            $existproduct = $checkProduct['data']['products']['nodes'];
                            $productId = str_replace('gid://shopify/Product/','',$existproduct[0]['id']);
                            foreach ($existproduct[0]['variants']['nodes'] as $var) {
                                if($var['sku'] == $sku){
                                    $var_sku = $var['sku'];
                                    $variantId = str_replace('gid://shopify/ProductVariant/','',$var['id']);
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
                                                    "inventory_quantity"=>$inventory,
                                                    "inventory_policy"=>"continue"
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
                                    $body['product']['id'] = $productId;
                                    $url = 'https://'.$shop_url.'/admin/api/2023-04/products/'.$productId.'.json';
                                    $response = Http::withHeaders($headers)->PUT($url,$body);
                                    $statusCode = $response->status();
                                    $responseBody = json_decode($response->getBody(), true);
                                    if($statusCode == 200){
                                        DB::table($shop[0].'_products')->where('sku',$sku)->update([
                                            'updated_status'=>1,
                                        ]);
                                    }
                                }
                            }
                        }else{
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
                                            "inventory_quantity"=>$inventory,
                                            "inventory_policy"=>"continue"
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
                        }
                        // die;
                    }
                    
                }
                if(empty($getProducts)){
                    DB::table('syncing')->where('shop',$shop[0])->update([
                        'sync'=>false,
                        // 'updated_at'=>date('Y/m/d H:i:s')
                    ]);
                }
            }
        }
    }
}
