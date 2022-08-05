<?php

class mailerWaTransportServiceApi extends waWebasystIDApi
{
    public function __construct(array $options = [])
    {
        $config = new mailerWaTransportApiUrlConfig();
        $this->provider = new mailerWaTransportServiceUrlProvider([
            'config' => $config
        ]);
    }

    public function call($api_method, array $params = [], $http_method = waNet::METHOD_GET, array $net_options = [])
    {
        $cm = new waWebasystIDClientManager();
        $token = $cm->getSystemAccessToken();
        $resp = $this->requestApiMethod($api_method, $token, $params, $http_method, $net_options);
        if ($resp['status'] == 404) {
            // If endpoint URL is invalid, try to refresh endpoint config and repeate call
            $this->refreshApiUrlConfig();
            $resp = $this->requestApiMethod($api_method, $token, $params, $http_method, $net_options);
        }
        if (in_array($resp['status'], [301, 302]) && !empty(ifset($resp, 'headers', 'Location', null))) {
            // If endpoint URL is moved, try to repeate call to new url
            $url = $resp['headers']['Location'];
            $resp = $this->requestApiUrl($url, $token, $params, $http_method, $net_options);
        }
        return $resp;
    }

    public function serviceCall($service, array $params = [], $http_method = waNet::METHOD_GET, array $net_options = [])
    {
        $cm = new waWebasystIDClientManager();
        if (!$cm->isConnected()) {
            throw new waException('Not connected to WebasystID');
        }
        $url = $this->provider->getServiceUrl($service);
        if (!$url) {
            throw new waException('Unable to get URL for service '.htmlspecialchars($service));
        }
        $token = $cm->getSystemAccessToken();
        $resp = $this->requestApiUrl($url, $token, $params, $http_method, $net_options);
        if ($resp['status'] == 404) {
            // If endpoint URL is invalid, try to refresh endpoint config and repeate call
            $this->refreshApiUrlConfig();
            $url = $this->provider->getServiceUrl($service);
            if (!empty($url)) {
                $resp = $this->requestApiUrl($url, $token, $params, $http_method, $net_options);
            }
        }
        if (in_array($resp['status'], [301, 302]) && !empty(ifset($resp, 'headers', 'Location', null))) {
            // If endpoint URL is moved, try to repeate call to new url
            $url = $resp['headers']['Location'];
            $resp = $this->requestApiUrl($url, $token, $params, $http_method, $net_options);
        }
        return $resp;
    }

    public function isConnected()
    {
        static $result = null;
        if ($result === null) {
            $cm = new waWebasystIDClientManager();
            $result = !!$cm->isConnected();
        }
        return $result;
    }


    /**
     * @param string $service
     * @param array $params
     * @param array &$api_result data fetched from API call
     * @return bool true if sufficient balance, false if insufficient balance
     * @throws waException if unable to connect to API
     */
    public function balanceCheckService($service, $params, &$api_result)
    {
        if (!isset($params['locale'])) {
            $params['locale'] = wa()->getLocale();
        }
        $api_result = $this->call('balance/check-service', [
            'service' => $service,
        ] + $params, waNet::METHOD_POST);
        if ($api_result['status'] != 200) {
            throw new waException(_w('Webasyst API transport connection error'));
        } else {
            return !empty($api_result['response']['is_enough']);
        }
    }

    protected function refreshApiUrlConfig()
    {
        $config = new mailerWaTransportApiUrlConfig();
        $config->keepEndpointsSynchronized(true);
        $this->provider = new mailerWaTransportServiceUrlProvider([
            'config' => $config
        ]);
    }
}
