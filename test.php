<?php

class Api
{
    public $data;

    function __construct($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res === false) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }

        $this->data = json_decode($res);
    }
}

class Travel
{
    public $data;

    function __construct($url)
    {
        $this->data = (new Api($url))->data;
    }

    public function getData()
    {
        $result = [];

        if (is_array($this->data)) {
            $result = array_reduce($this->data, function ($carry, $item) {
                if (!isset($carry[$item->companyId])) {
                    $carry[$item->companyId] = ['companyId' => $item->companyId, 'price' => $item->price];
                } else {
                    $carry[$item->companyId]['price'] += $item->price;
                }
                return $carry;
            });
        }

        return $result;
    }
}

class Company
{
    public $data;

    function __construct($url)
    {
        $this->data = (new Api($url))->data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function mixData($companies, $travels)
    {
        $result = $parent = [];

        if (!empty($companies) && !empty($travels)) {
            $new = array();
            foreach ($companies as $company) {
                $company->cost = $travels[$company->id]['price'] ?? 0;
                $new[$company->parentId][] = $company;
                if ($company->parentId == "0") {
                    $parent[] = $company;
                }
                unset($company->createdAt);
                unset($company->parentId);
            }

            foreach ($parent as $company) {
                $result = array_merge($result, $this->createTree($new, array($company)));
            }
        }

        $this->array_multi_sum($result);

        return $result;
    }

    function createTree(&$list, $parent)
    {
        $tree = array();
        foreach ($parent as $company) {
            if (isset($list[$company->id])) {
                $company->children = $this->createTree($list, $list[$company->id]);
            } else {
                $company->children = [];
            }

            $tree[] = $company;
        }
        return $tree;
    }

    function array_multi_sum(&$arr)
    {
        foreach ($arr as &$child) {
            $sum = 0;

            if (!empty($child->children)) {
                $sum += array_reduce($child->children, function ($carry, $item) {
                    return $carry + $item->cost;
                });

                $sum += $this->array_multi_sum($child->children);
            }

            $child->cost += $sum;
        }

        return $sum;
    }
}

class TestScript
{
    public function execute()
    {
        $companyApi = "https://5f27781bf5d27e001612e057.mockapi.io/webprovise/companies";
        $travelApi = "http://5f27781bf5d27e001612e057.mockapi.io/webprovise/travels";
        $company = new Company($companyApi);
        $travels = (new Travel($travelApi))->getData();
        $companies = $company->getData();

        $start = microtime(true);

        $companies = $company->mixData($companies, $travels);
//        echo json_encode($companies);
        echo 'Total time: ' . (microtime(true) - $start);
    }
}

(new TestScript())->execute();
?>