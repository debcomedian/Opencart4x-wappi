<?php
class WappiProReplacements {
    private $replacements = [];
    private $db;
    private $language;
    private $load;
    public function __construct($registry) {
        $this->db = $registry->get('db');
        $this->language = $registry->get('language');
        $this->load = $registry->get('load');
    }
    public function setReplacements($data) {
        if (is_array($data)) {
            $this->replacements = $data;
        }
    }
    public function getReplacements() {
        return $this->replacements;
    }
    public function loadReplacements($order_id) {
        $this->load->language('extension/module/wappipro');
    
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
    
        if ($query->num_rows > 0) {
            foreach ($query->row as $key => $value) {
                switch ($key) {
                    case 'total':
                        $this->replacements[$key] = $this->language->get('text_total');
                        break;
                    
                    case 'date_added':
                    case 'date_modified':
                    case 'custom_field':
                    case 'payment_custom_field':
                    case 'shipping_custom_field':
                    case 'ip':
                    case 'forwarded_ip':
                        $this->replacements[$key] = $this->language->get('text_' . $key);
                        break;
    
                    case 'user_agent':
                        $this->replacements[$key] = $this->language->get('text_user_agent');
                        break;
    
                    default:
                        $lang_key = 'text_' . $key;
                        if ($this->language->get($lang_key) !== $lang_key) {
                            $this->replacements[$key] = $this->language->get($lang_key);
                        } else {
                            $this->replacements[$key] = '';
                        }
                        break;
                }
            }
        }
    }
}