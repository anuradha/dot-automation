<?php
class transactionalData
{
    public function __construct()
    {
        $this->dbHost = '';
        $this->dbUser = '';
        $this->dbPass = '';
        $this->dbName = '';

        $this->apiUsername = '<api-username>';
        $this->apiPassword = '<api-password>';
        $this->baseUrl = 'https://api.dotmailer.com';

        $this->importStatLog = 'logs/import_status_' . date('Y-m-d') . '.log';
        $this->logFile = 'logs/transactional_data_' . date('Y-m-d') . '.log';
        $this->failLog = 'logs/missing_contacts_' . date('Y-m-d') . '.log';
    }

    /**
     * push transactional data to dotmailer
     *
     * @string jsonData
     */
    public function pushTransactionalData()
    {
        $proceed = 0;
        $transData = array();
        $conn = mysqli_connect($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
        $orderSql = "SELECT * FROM orders";
        //$orderSql = "SELECT * FROM orders WHERE email = 'dpkitchen1@bigpond.com'";

        $orderResult = $conn->query($orderSql);
        if($proceed < $orderResult->num_rows)
        {
            $importStatus = "Collecting Data... Please wait..." . PHP_EOL;
            echo $importStatus; //do not delete
            file_put_contents($this->importStatLog, $importStatus, FILE_APPEND);
        }

        while ($orderRow = $orderResult->fetch_assoc())
        {
            $order_id = $orderRow['order_id'];
            $discount_amount = $orderRow['discount_amount'];
            $order_total = round($orderRow['order_total']);
            $payment = $orderRow['payment'];
            ($orderRow['delivery_method'] == "freeshipping_freeshipping") ? $delivery_method = "Free Shipping" : $delivery_method = $row['delivery_method'];
            $delivery_total = $orderRow['delivery_total'];
            $currency = $orderRow['currency'];
            $order_status = $orderRow['order_status'];
            $customer_id = $orderRow['customer_id'];
            $email = $orderRow['email'];
            $quote_id = $orderRow['quote_id'];
            $store_name = $orderRow['store_name'];
            $purchase_date = str_replace(' ', 'T', $orderRow['purchase_date']) . 'Z';
            $subtotal = $orderRow['subtotal'];

            $contentHeader = array(
                'key' => $order_id,
                'contactIdentifier' => strtolower($email)
            );

            $contentBody = array(
                'order_total' => $order_total,
                'quote_id' => $quote_id,
                'discount_amount' => $discount_amount,
                'payment' => $payment,
                'delivery_method' => $delivery_method,
                'delivery_total' => $delivery_total,
                'currency' => $currency,
                'order_status' => ucfirst($order_status),
                'email' => $email,
                'store_name' => $store_name,
                'purchase_date' => $purchase_date,
                'sub_total' => $subtotal,
                'customer_id' => $customer_id
            );

            $orderProdSql = "SELECT * FROM products WHERE increment_id = '" . $order_id . "'";
            $orderProdResult = $conn->query($orderProdSql);
            $jsonData = '';
            if ($orderProdResult->num_rows > 0) {
                $i=1;
                while ($orderProdRow = $orderProdResult->fetch_assoc())
                {
                    $product_name = $orderProdRow['product_name'];
                    $sku = $orderProdRow['sku'];
                    $qty_ordered = $orderProdRow['qty_ordered'];
                    $price = $orderProdRow['price'];

                    $products = [
                        'product'.$i => [
                            'name' => $product_name,
                            'sku' => $sku,
                            'qty_ordered' => $qty_ordered,
                            'price' => $price
                        ],
                    ];

                    $i++;

                    $contentBody = $contentBody+$products;
                }
            }

            $content = array('json'=>json_encode($contentBody));

            $transData[] = $contentHeader + $content; //join content header and body and push to the dotmailer
            $jsonData.= json_encode($transData);
            $jsonData.=',';

            echo $proceed . " records collected" . PHP_EOL;

            $proceed++;
        }

        $jsonData = rtrim($jsonData, ',');

        //write to the log file
        $importStatus = $orderResult->num_rows . " of records collected." . PHP_EOL;
        echo $importStatus . "Ready to push to the Dotmailer. Please wait..." . PHP_EOL;
        $importStatusLogMessage = date('Y-m-d H:i:s') . '  ' . $importStatus . PHP_EOL;
        file_put_contents($this->importStatLog, $importStatusLogMessage, FILE_APPEND);
        sleep(1);

        return $jsonData;
    }

    /**
     * Dotmailer connection via API
     */

    public function pushToDotmailer()
    {
        $transData = $this->pushTransactionalData();

        //API connectivity
        $url = $this->baseUrl . "/v2/contacts/transactional-data/import/Orders";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, array('Accept: application/json',
                'Content-Type: application/json')
        );
        curl_setopt($ch, CURLAUTH_BASIC, CURLAUTH_DIGEST);
        curl_setopt(
            $ch, CURLOPT_USERPWD,
            $this->apiUsername . ':' . $this->apiPassword
        );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $transData);

        $response = json_decode(curl_exec($ch));
        print_r($response); //print the response if you want to the see the end result.

        //write the result into a log file
        $importStatus = "Insight Data push is completed. " . PHP_EOL;
        echo $importStatus; //do not delete
        $importLogMessage = $importStatus . PHP_EOL . "Import Id :" . $response->id . PHP_EOL;
        $importLogMessage .= '-------------------------------------------------------------------------' . PHP_EOL;
        file_put_contents($this->importStatLog, $importLogMessage, FILE_APPEND);
    }
}

$clsTransData = new transactionalData();
$clsTransData->pushToDotmailer();