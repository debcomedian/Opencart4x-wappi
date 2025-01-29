<?php

namespace Opencart\Admin\Controller\Extension\WappiproOc4x\Module;

class WappiPro extends \Opencart\System\Engine\Controller {
    private $error = [];
    private $code = ['wappipro_test', 'wappipro'];
    public $testResult = true;
    private $fields_test = [
        "wappipro_test_phone_number" => [
            "label"    => "Phone Number",
            "type"     => "isPhoneNumber",
            "value"    => "",
            "validate" => true,
        ],
    ];
    private $fields = [
        "wappipro_username" => ["label" => "Username", "type" => "isEmpty", "value" => "", "validate" => true],
        "wappipro_apiKey"   => ["label" => "API Key", "type" => "isEmpty", "value" => "", "validate" => true],
    ];

    public function __construct($registry) {
        parent::__construct($registry);
        require_once DIR_SYSTEM . '../extension/wappipro_oc4x/system/library/wappiproreplacements.php';
        $this->replacementsHandler = new \WappiProReplacements($registry);
    }

    public function index(): void {
        if (!$this->isModuleEnabled()) {
            $this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']));
            exit;
        }

        $this->load->language('extension/wappipro_oc4x/module/wappipro');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('../extension/wappipro_oc4x/admin/view/stylesheet/wappipro/wappipro.css');

        $this->load->model('setting/setting');
        $this->load->model('setting/module');
        $this->load->model('design/layout');
        $this->load->model('localisation/order_status');

        $data = [];
        $this->submitted($data);
        $this->loadFieldsToData($data);

        $data['error_warning'] = $this->error;

        $data['wappipro_logo'] = '../extension/wappipro_oc4x/admin/view/image/wappipro/logo.jpg';

        $data['about_title'] = $this->language->get('about_title');
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit']     = $this->language->get('text_edit');

        $data['btn_test_text']        = $this->language->get('btn_test_text');
        $data['btn_test_placeholder'] = $this->language->get('btn_test_placeholder');
        $data['btn_test_description'] = $this->language->get('btn_test_description');
        $data['btn_test_send']        = $this->language->get('btn_test_send');

        $data['btn_wappipro_self_sending_active'] = $this->language->get('btn_wappipro_self_sending_active');

        $data['btn_apiKey_text']        = $this->language->get('btn_apiKey_text');
        $data['btn_apiKey_placeholder'] = $this->language->get('btn_apiKey_placeholder');
        $data['btn_apiKey_description'] = $this->language->get('btn_apiKey_description');
        $data['btn_duble_admin']        = $this->language->get('btn_duble_admin');

        $data['btn_username_text']        = $this->language->get('btn_username_text');
        $data['btn_username_placeholder'] = $this->language->get('btn_username_placeholder');
        $data['btn_username_description'] = $this->language->get('btn_username_description');

        $data['btn_token_save_all'] = $this->language->get('btn_token_save_all');

        $data['order_status_list'] = $this->model_localisation_order_status->getOrderStatuses();  // ??

        $data['wappipro_test_result'] = $this->testResult;

        $settings = $this->model_setting_setting->getSetting('wappipro');
        $data['wappipro_order_status_active'] = [];
        $data['wappipro_order_status_message'] = [];
        $data['wappipro_admin_order_status_active'] = [];

        $data['text_available_variables'] = $this->language->get('text_available_variables');
        $data['text_variable'] = $this->language->get('text_variable');
        $data['text_description'] = $this->language->get('text_description');
        $data['text_close'] = $this->language->get('text_close');

        foreach ($data['order_status_list'] as $status) {
            $data['wappipro_order_status_active'][$status['order_status_id']] = $settings['wappipro_' . $status['order_status_id'] . '_active'] ?? '';
            $data['wappipro_order_status_message'][$status['order_status_id']] = $settings['wappipro_' . $status['order_status_id'] . '_message'] ?? '';
            $data['wappipro_admin_order_status_active'][$status['order_status_id']] = $settings['wappipro_admin_' . $status['order_status_id'] . '_active'] ?? '';
        }

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $lastOrderId = $this->getLastOrderId();
        if ($lastOrderId) {
            $this->replacementsHandler->loadReplacements($lastOrderId);
        
            $data['replacements'] = $this->replacementsHandler->getReplacements();
        } else {
            $data['replacements'] = [];
            $data['error_warning'][] = ['error' => 'No orders found.'];
        }

        $this->response->setOutput($this->load->view('extension/wappipro_oc4x/module/wappipro', $data));
    }

    public function getLastOrderId() {
        $query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` ORDER BY order_id DESC LIMIT 1");
        if ($query->num_rows > 0) {
            return (int)$query->row['order_id'];
        }
    
        return null;
    }
    private function getOrderReplacements($order_id)
    {
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);   
        if (!$order_info) {
            return [];
        }
        $replacements = [];
        foreach ($order_info as $key => $value) {
            $replacements[$key] = '{' . $key . '}';
        }
        return $replacements;
    }

    public function isModuleEnabled(): bool {
        $sql    = sprintf("SELECT * FROM %sextension WHERE code = 'wappipro'", DB_PREFIX);
        $result = $this->db->query($sql);

        return $result->num_rows > 0;
    }

    public function submitted(array &$data): bool {
        if (!empty($this->request->post)) {
            $this->fields_test['wappipro_test_phone_number']['value'] = $this->request->post['wappipro_test_phone_number'] ?? '';

            if (!empty($this->request->post['wappipro_test'])) {
                $this->validateFields();
                if (empty($this->request->post['wappipro_apiKey'])) {
                    $this->error[] = ["error" => $this->language->get('err_apikey')];
                }

                if (empty($this->request->post['wappipro_username'])) {
                    $this->error[] = ["error" => $this->language->get('err_profile')];
                }

                if (empty($this->error)) {
                    $this->saveFieldsToDB();
                    $settings = $this->model_setting_setting->getSetting('wappipro');
                    $phone = $this->model_setting_setting->getSetting('wappipro_test')['wappipro_test_phone_number'];

                    $message = $this->language->get('test_message');

                    $data_profile = $this->getProfileInfo($settings);
                    if (isset($data_profile['error'])) {
                        $this->testResult = false;
                        $data["payment_time_string"] = $this->language->get('unvalid_profile');
                    } else {
                        if (isset($data_profile['platform'])) {
                            $platform = $data_profile['platform'];
                            $data["payment_time_string"] = $data_profile["payment_time_string"];
                            if ($platform !== false) {
                                $this->model_setting_setting->editSetting("wappipro_platform", array('wappipro_platform' => $platform));
                                $this->_save_user($settings);
                                $this->testResult = $this->sendTestSMS($settings, $platform, $phone, $message);
                            } else {
                                $this->testResult = false;
                                $this->error[] = ["error" => $this->language->get('err_request')];
                            }
                        } else {
                            $this->testResult = $this->sendTestSMS($settings, '', $phone, $message);
                        }
                    }
                }
            } else {
                $this->testResult = true;
                $this->validateFields();
                if (empty($this->error)) {
                    $this->saveFieldsToDB();
                }
            }

            return true;
        }

        return false;
    }

    public function loadFieldsToData(array &$data): void {
        $settings = $this->model_setting_setting->getSetting('wappipro');
        $settings_test = $this->model_setting_setting->getSetting('wappipro_test');

        foreach ($this->fields as $key => $value) {
            $data[$key] = $settings[$key] ?? '';
        }

        foreach ($this->fields_test as $key => $value) {
            $data[$key] = $settings_test[$key] ?? '';
        }

        $order_status_list = $this->model_localisation_order_status->getOrderStatuses();
        foreach ($order_status_list as $status) {
            $data['wappipro_' . $status['order_status_id'] . '_active'] = $settings['wappipro_' . $status['order_status_id'] . '_active'] ?? '';
            $data['wappipro_' . $status['order_status_id'] . '_message'] = $settings['wappipro_' . $status['order_status_id'] . '_message'] ?? '';
            $data['wappipro_admin_' . $status['order_status_id'] . '_active'] = $settings['wappipro_admin_' . $status['order_status_id'] . '_active'] ?? '';
        }
    }

    public function saveFieldsToDB(): void {
        foreach (array_keys($this->fields) as $key) {
            $this->fields[$key] = $this->request->post[$key] ?? '';
        }

        $order_status_list = $this->model_localisation_order_status->getOrderStatuses();
        foreach ($order_status_list as $status) {
            $this->fields['wappipro_' . $status['order_status_id'] . '_message'] = $this->request->post['wappipro_' . $status['order_status_id'] . '_message'] ?? '';
            $this->fields['wappipro_' . $status['order_status_id'] . '_active'] = isset($this->request->post['wappipro_' . $status['order_status_id'] . '_active']) ? 'true' : 'false';
            $this->fields['wappipro_admin_' . $status['order_status_id'] . '_active'] = isset($this->request->post['wappipro_admin_' . $status['order_status_id'] . '_active']) ? 'true' : 'false';
        }

        $this->model_setting_setting->editSetting('wappipro', $this->fields);
        $test_settings = ['wappipro_test_phone_number' => $this->fields_test['wappipro_test_phone_number']['value']];
        $this->model_setting_setting->editSetting('wappipro_test', $test_settings);
    }

    public function validateFields(): void {
        foreach ($this->fields as $key => $value) {
            if (isset($value['validate'])) {
                $result = call_user_func_array(
                    [$this, $value['type']],
                    [$this->request->post[$key]]
                );
                if (!$result) {
                    $this->error[] = ["error" => $this->language->get('err_part1') . $value['label'] . $this->language->get('err_part2')];
                }
            }
        }
    }

    public function install()
    {
        $this->load->model('setting/setting');
        $settings = [
            'module_wappipro_status' => '1',
        ];
        
        $this->model_setting_setting->editSetting('module_wappipro', $settings);

        $this->load->model('setting/event');

        $event_data = [
            'code'        => 'wappipro',
            'description' => '',
            'trigger'     => 'catalog/model/checkout/order/addHistory/after',
            'action'      => 'extension/wappipro_oc4x/module/wappipro.status_change',
            'status'      => 1,
            'sort_order'  => 1
        ];

        $this->model_setting_event->addEvent($event_data);
    }

    public function uninstall(): void {
        $this->load->model('setting/event');
        $this->load->model('setting/setting');
        $this->model_setting_event->deleteEventByCode(
            'wappipro',
            'catalog/model/checkout/order/addHistory/after',
            'extension/wappipro/module/wappipro.status_change'
        );
        $this->model_setting_setting->deleteSetting('wappipro');
        $this->model_setting_setting->deleteSetting('wappipro_test');
        $this->model_setting_setting->deleteSetting('wappipro_platform');
        $this->model_setting_setting->deleteSetting('module_wappipro');
    }

    public function sendTestSMS(array $settings, string $platform, string $to, string $body): bool {
        $apiKey = $settings['wappipro_apiKey'] ?? '';
        $username = $settings['wappipro_username'] ?? '';

        if (!empty($apiKey)) {
            $req = array();

            if (strlen($username) != 20) {
                $req['postfields'] = json_encode(array(
                    'recipient' => $to,
                    'body' => $body,
                ));

                $req['header'] = array(
                    "accept: application/json",
                    "Authorization: " .  $apiKey,
                    "Content-Type: application/json",
                );

                $req['url'] = 'https://wappi.pro/'. $platform . 'api/sync/message/send?profile_id=' . $username;
            } else {
                $req['postfields'] = json_encode(array(
                    'recipient' => $to,
                    'body' => $body,
                    'cascade_id' => $username
                ));

                $req['header'] = array(
                    "accept: application/json",
                    "Authorization: " .  $apiKey,
                    "Content-Type: application/json",
                );

                $req['url'] = 'https://wappi.pro/csender/cascade/send';
            }
            try {
                $answer = json_decode($this->curlito(false, $req), true);
                return $answer === 200;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    private function curlito(bool $wait, array $req, string $method = ''): string {
        $curl = curl_init();
        $option = array(
            CURLOPT_URL => $req['url'],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $req['postfields'],
            CURLOPT_HTTPHEADER => $req['header'],
        );

        if ($wait) {
            $option[CURLOPT_TIMEOUT] = 30;
        } else {
            $option[CURLOPT_TIMEOUT_MS] = 5000;
            $option[CURLOPT_HEADER] = 0;
        }

        curl_setopt_array($curl, $option);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            error_log($err . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
            return "cURL Error #:" . $err;
        } else {
            return $http_status;
        }
    }

    public function getProfileInfo(array $settings): array {
        $apiKey = $settings['wappipro_apiKey'] ?? '';
        $username = $settings['wappipro_username'] ?? '';

        if (!$apiKey || !$username) {
            error_log('Missing API key or username' . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
            return ['error' => 'Missing API key or username'];
        }

        $username_len = strlen($username);
        
        if ($username_len != 20) {
            $url = 'https://wappi.pro/api/sync/get/status?profile_id=';
        } else {
            $url = 'https://wappi.pro/csender/cascade/get?cascade_id=';
        }
        $url .= urlencode($username);
        $headers = array(
            "accept: application/json",
            "Authorization: " . $apiKey,
            "Content-Type: application/json",
        );
    
        $curl = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers,
        );
    
        curl_setopt_array($curl, $options);
        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);
    
        if ($err) {
            error_log('cURL Error: ' . $err . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
            return ['error' => "cURL Error #: " . $err];
        }
    
        if ($http_status != 200) {
            error_log('HTTP Status: ' . $http_status . ' - ' . $result . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
            return ['error' => 'HTTP Status: ' . $http_status];
        }
    
        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Ошибка JSON: ' . json_last_error_msg() . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
            return ['error' => 'JSON Error: ' . json_last_error_msg()];
        }

        if ($username_len != 20) {
            if (isset($data['status'])) {
                error_log('Ошибка отправки GET-запроса: ' . $data['status'] . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
                return ['error' => 'Ошибка отправки GET-запроса: ' . $data['status']];
            }
        
            $platform = $data['platform'];
            if (array_key_exists('platform', $data) && $data['platform']) {
                $platform = ($data['platform'] === 'tg') ? 't' : '';
            } else {
                $platform = false;
            }

            $data["platform"] = $platform;
            $data['payment_time_string'] = $this->_parse_time($data);
        } else {
            $data['payment_time_string'] = $this->_output_cascade($data);
        }


        return $data;
    }

    private function _parse_time(array $data): string {
        $result_string = '';
        $time_sub = new \DateTime($data['payment_expired_at']);
        $time_curr = new \DateTime;

        if ($time_sub > $time_curr) {
            $time_diff = $time_curr->diff($time_sub);
            $days_diff = $time_diff->days;
            $hours_diff = $time_diff->h;
            $result_string .= $this->language->get("wappi_green_span_and_first_part") 
                            . $time_sub->format('Y-m-d') . $this->language->get("wappi_second_part");
    
            $days_diff_last_num = $days_diff % 10;
            $hours_diff_last_num = $hours_diff % 10;
    
            if ($days_diff !== 0) {
                $result_string .= $days_diff;
    
                if ($days_diff_last_num > 4 || ($days_diff > 10 && $days_diff < 21))
                    $result_string .= $this->language->get("wappi_days");
                else if ($days_diff_last_num === 1 )
                    $result_string .= $this->language->get("wappi_day");
                else
                    $result_string .= $this->language->get("wappi_day2");
            }
            $result_string .= $hours_diff;

            if ($hours_diff_last_num > 4 || ($hours_diff > 10 && $hours_diff < 20) || $hours_diff_last_num === 0) 
                $result_string .= $this->language->get("wappi_hours");    
            else if ($hours_diff_last_num === 1)
                $result_string .= $this->language->get("wappi_hour");
            else 
                $result_string .= $this->language->get("wappi_hour2");  
        } else {
            $result_string .= $this->language->get("wappi_subscription_period_expired");
        }
        return $result_string;        
    }
    
    private function _output_cascade($data) {
		if (isset($data['cascade']) && isset($data['cascade']['order'])) {
			$cascade = $data['cascade'];
			$cascade_name = $cascade['name'] ?? '';
			$order = $cascade['order'];
		
			$platforms = array_map(function ($item) {
				$platform = $item['platform'] ?? '';
				$profile_uuid = $item['profile_uuid'] ?? '';
		
				$platform_display = match ($platform) {
					'wz' => 'WhatsApp',
					'tg' => 'Telegram',
					'sms' => $this->language->get('sms'),
					default => $platform,
				};
		
				return "{$platform_display} {$profile_uuid}";
			}, $order);
		
			$platforms_list = implode(', ', $platforms);
		
			return $this->language->get('cascade') . " \"{$cascade_name}\": {$platforms_list}";
		} else {
			return $this->language->get('cascade_no_data');
		}
    }
    
    public function _save_user(array $settings): void {
        $apiKey = $settings['wappipro_apiKey'] ?? '';
        $username = $settings['wappipro_username'] ?? '';
        
        $message_json = json_encode(array(
            'url' => $_SERVER['HTTP_REFERER'],
            'module' => 'opencart4',
            'profile_uuid' => $username,
        ));
    
        $url = 'https://dev.wappi.pro/tapi/addInstall?profile_id=' . urlencode($username);
    
        $headers = array(
            'Accept: application/json',
            'Authorization: ' . $apiKey,
            'Content-Type: application/json',
        );
    
        $curl = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $message_json,
            CURLOPT_HTTPHEADER => $headers,
        );
    
        curl_setopt_array($curl, $options);
    
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        if ($err) {
            error_log("Error save user to db" . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
        } else if ($http_status !== 200) {
            error_log('HTTP Status save user: ' . $http_status . PHP_EOL, 3, DIR_LOGS . "wappi-errors.log");
        }
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isEmpty(string $str): bool
    {
        return !empty($str);
    }

    /**
     * @param string $number
     * @return bool
     */
    public static function isPhoneNumber(string $number): bool
    {
        if (empty($number)) {
            return false;
        }

        return !empty($number) && preg_match('/^[+0-9. ()-]*$/', $number);
    }
}
