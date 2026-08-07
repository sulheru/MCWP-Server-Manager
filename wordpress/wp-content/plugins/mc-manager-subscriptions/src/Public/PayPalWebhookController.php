<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_PayPal_Webhook_Controller
{
    private const NAMESPACE='optigrid-subscriptions/v1';
    private const ROUTE='/paypal/webhook';

    public function __construct(
        private OptiGrid_Subscriptions_Payment_Order_Repository $orders,
        private OptiGrid_Subscriptions_Checkout_Service $checkout,
        private OptiGrid_Subscriptions_PayPal_Gateway $paypal
    ) {}

    public function register(): void
    {
        add_action('rest_api_init',[$this,'register_route']);
    }

    public function register_route(): void
    {
        register_rest_route(self::NAMESPACE,self::ROUTE,[
            'methods'=>'POST','callback'=>[$this,'handle'],'permission_callback'=>'__return_true',
        ]);
    }

    public static function endpoint_url(): string
    {
        return rest_url(self::NAMESPACE.self::ROUTE);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $settings=OptiGrid_Subscriptions_Gateway_Settings::for_gateway('paypal');
            $webhook_id=trim((string)($settings['webhook_id'] ?? ''));
            if($webhook_id===''){return new WP_REST_Response(['ok'=>false,'error'=>'paypal_webhook_not_configured'],503);}

            $event=$request->get_json_params();
            if(!is_array($event)){return new WP_REST_Response(['ok'=>false,'error'=>'invalid_json'],400);}

            $headers=[
                'transmission_id'=>(string)$request->get_header('paypal-transmission-id'),
                'transmission_time'=>(string)$request->get_header('paypal-transmission-time'),
                'cert_url'=>(string)$request->get_header('paypal-cert-url'),
                'auth_algo'=>(string)$request->get_header('paypal-auth-algo'),
                'transmission_sig'=>(string)$request->get_header('paypal-transmission-sig'),
            ];

            $client=new OptiGrid_Subscriptions_PayPal_Client((string)($settings['client_id'] ?? ''),(string)($settings['client_secret'] ?? ''));
            if(!$client->verify_webhook_signature($headers,$event,$webhook_id)){
                return new WP_REST_Response(['ok'=>false,'error'=>'invalid_signature'],401);
            }

            $type=strtoupper((string)($event['event_type'] ?? ''));
            $supported=['CHECKOUT.ORDER.APPROVED','CHECKOUT.PAYMENT-APPROVAL.REVERSED','PAYMENT.CAPTURE.PENDING','PAYMENT.CAPTURE.COMPLETED','PAYMENT.CAPTURE.DENIED'];
            if(!in_array($type,$supported,true)){
                return new WP_REST_Response(['ok'=>true,'handled'=>false,'event_type'=>$type],200);
            }

            $public_id=$this->paypal->public_id_from_webhook($event);
            if($public_id===''){return new WP_REST_Response(['ok'=>false,'error'=>'local_reference_not_found'],422);}
            $order=$this->orders->find_by_public_id($public_id);
            if($order===null || (string)$order['gateway']!=='paypal'){
                return new WP_REST_Response(['ok'=>false,'error'=>'order_not_found'],404);
            }

            if((string)$order['status']!=='pending'){
                return new WP_REST_Response(['ok'=>true,'handled'=>true,'idempotent'=>true,'order_status'=>$order['status']],200);
            }

            if($type==='PAYMENT.CAPTURE.PENDING'){
                return new WP_REST_Response(['ok'=>true,'handled'=>true,'order_status'=>'pending'],200);
            }

            if($type==='CHECKOUT.ORDER.APPROVED'){
                $resource=is_array($event['resource'] ?? null)?$event['resource']:[];
                $paypal_order_id=sanitize_text_field((string)($resource['id'] ?? ''));
                if($paypal_order_id===''){throw new RuntimeException('CHECKOUT.ORDER.APPROVED sin PayPal Order ID.');}
                if(empty($order['gateway_reference'])){
                    $this->orders->set_gateway_reference((int)$order['id'],$paypal_order_id);
                    $order['gateway_reference']=$paypal_order_id;
                }
                $raw=$this->paypal->capture($order,$paypal_order_id);
                $result=$this->checkout->process_external_result($public_id,$raw);
                return new WP_REST_Response(['ok'=>true,'handled'=>true,'order_status'=>$result['order_status']],200);
            }

            if($type==='PAYMENT.CAPTURE.COMPLETED' || $type==='PAYMENT.CAPTURE.DENIED'){
                $raw=$this->paypal->result_from_capture_webhook($order,$event);
                if($raw===null){return new WP_REST_Response(['ok'=>true,'handled'=>false],200);}
                $result=$this->checkout->process_external_result($public_id,$raw);
                return new WP_REST_Response(['ok'=>true,'handled'=>true,'order_status'=>$result['order_status']],200);
            }

            $ref=sanitize_text_field((string)($order['gateway_reference'] ?? ''));
            $raw=[
                'gateway'=>'paypal','external_operation_id'=>$ref!==''?$ref:'paypal-reversed-'.$public_id,
                'scenario'=>'paypal_webhook_reversed','status'=>'rejected','amount'=>(string)$order['amount'],
                'currency'=>(string)$order['currency'],'message'=>'PayPal revirtió la aprobación del checkout.',
                'processed_at'=>current_time('mysql',true),'raw'=>$event,
            ];
            $result=$this->checkout->process_external_result($public_id,$raw);
            return new WP_REST_Response(['ok'=>true,'handled'=>true,'order_status'=>$result['order_status']],200);
        } catch(Throwable $e){
            error_log('[OptiGrid PayPal Webhook] '.$e->getMessage());
            return new WP_REST_Response(['ok'=>false,'error'=>'processing_error','message'=>$e->getMessage()],500);
        }
    }
}
