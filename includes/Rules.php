<?php
if (!defined('ABSPATH')) exit;

class USAC_Rules {
    public static function check_compatibility($brand_id,$model_id,$countries){
        global $wpdb;
        if (!$model_id || empty($countries)) return ['overall'=>'unknown','per_country'=>[],'esim_supported_all'=>false];

        $map = [];
        $esim_all = true; $voice_all=true; $sms_all=true; $data_all=true;

        foreach ($countries as $cc){
            $row = $wpdb->get_row($wpdb->prepare("
              SELECT * FROM {$wpdb->prefix}usac_device_country WHERE model_id=%d AND country_code=%s",
              $model_id,$cc));
            if (!$row){ $map[$cc] = 'unknown'; continue; }
            $esim_all = $esim_all && (bool)$row->esim_supported;
            $voice_all = $voice_all && (bool)$row->voice_supported;
            $sms_all  = $sms_all  && (bool)$row->sms_supported;
            $data_all = $data_all && (bool)$row->data_supported;

            $map[$cc] = $row->data_supported ? 'data' : 'none';
            if ($row->voice_supported) $map[$cc] .= '+voice';
            if ($row->sms_supported)  $map[$cc] .= '+sms';
        }

        $overall = !$data_all ? 'not_compatible' : (($voice_all && $sms_all) ? 'full' : 'data_only');

        return [
            'overall'=>$overall,
            'per_country'=>$map,
            'esim_supported_all'=>$esim_all
        ];
    }

    // payload: countries[], sim_type, services{voice,sms,inbound_colombia}, dates{start,end,days}
    public static function calculate_quote($p){
        global $wpdb;
        $countries = $p['countries'] ?? [];
        $sim = $p['sim_type'] ?? 'esim';
        $days = max(1, intval($p['dates']['days'] ?? 1));
        $voice = !empty($p['services']['voice']);
        $sms   = !empty($p['services']['sms']);

        // Selecciona el/los planes que cubren todos los países
        // (para demo: tomamos el primer plan que cubra al menos 1 país; puedes sofisticarlo a gusto)
        $plan = (int)$wpdb->get_var($wpdb->prepare("
          SELECT p.id FROM {$wpdb->prefix}usac_plans p
          JOIN {$wpdb->prefix}usac_plan_country pc ON pc.plan_id=p.id
          WHERE p.active=1 AND pc.country_code IN (".implode(',', array_fill(0,count($countries),'%s')).")
          ORDER BY p.id LIMIT 1", ...$countries));

        if (!$plan) return ['currency'=>get_option('usac_currency','USD'),'total'=>0,'by_country'=>[],'days'=>$days];

        // Busca regla por rango
        $rule = $wpdb->get_row($wpdb->prepare("
          SELECT * FROM {$wpdb->prefix}usac_pricing_rules
          WHERE plan_id=%d AND sim_type=%s AND min_days<=%d AND max_days>=%d AND active=1
          ORDER BY min_days DESC LIMIT 1", $plan,$sim,$days,$days));

        if (!$rule) return ['currency'=>get_option('usac_currency','USD'),'total'=>0,'by_country'=>[],'days'=>$days];

        $per_country = [];
        foreach ($countries as $cc){
            $line = floatval($rule->base_price);
            if ($voice) $line += floatval($rule->voice_addon);
            if ($sms)   $line += floatval($rule->sms_addon);
            $line += floatval($rule->region_surcharge);
            $per_country[$cc] = round($line,2);
        }
        $total = array_sum($per_country);

        return [
            'currency'=>get_option('usac_currency','USD'),
            'total'=>round($total,2),
            'by_country'=>$per_country,
            'days'=>$days,
            'plan_id'=>$plan
        ];
    }
}
