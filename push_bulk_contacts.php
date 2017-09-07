<?php
/**
 * Created by PhpStorm.
 * User: anuradha
 * Date: 9/7/17
 * Time: 10:15 AM
 */
class dmContacts {
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
     * rendering html output
     *
     * @return string
     */
    protected function _html()
    {
        //sample UI
        $htmlOutput = "<form method='post' action='push_bulk_contacts.php'>";
        $htmlOutput .= "<dl>";
        $htmlOutput .= "<dt>Email</dt>";
        for ($i=0; $i<3; $i++) :
        $htmlOutput .= "<dd><input type='text' name='invitee_email[]' id='invitee_email' /></dd>";
        endfor;
        $htmlOutput .= "<dd>";
        $htmlOutput .= "<input type='submit' name='send_mail' value='Send Invitation' />";
        $htmlOutput .= "</dd>";
        $htmlOutput .= "</dl>";
        $htmlOutput .= "</form>";

        return $htmlOutput;
    }

    /**
     * Generate csv to push data to the dotmailer
     *
     * @param $emails
     * @param $customer
     */
    protected function _genCSV($emails, $customer)
    {
        $csvFile = Mage::getBaseDir() . '/var/export/contacts.csv';
        $fp = fopen($csvFile, 'w');
        $row = 1;
        if($emails)
        {
            $header = array('email', 'INVITER_FIRSTNAME', 'INVITER_LASTNAME', 'INVITER_EMAIL', 'FIRST_PUR_DIS_VALUE');
            fputcsv($fp, $header);
            foreach ($emails as $email)
            {
                fputcsv($fp, array($email, $customer->getFirstname(), $customer->getLastname(), $customer->getEmail(), 20));

                $row++;
            }
            fclose($fp);
        }
    }

    /**
     * push bulk contacts to the Dotmailer
     */
    public function pushContacts()
    {
        if(isset($_POST['send_mail']))
        {
            $this->_genCSV();
            $csvFile = Mage::getBaseDir() . '/var/export/contacts.csv';
            $baseUrl = 'https://api.dotmailer.com';
            $url = $baseUrl . "/v2/address-books/" . $this->dmAddressBook . "/contacts/import";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: multipart/form-data')
            );
            curl_setopt($ch, CURLAUTH_BASIC, CURLAUTH_DIGEST);
            curl_setopt(
                $ch, CURLOPT_USERPWD,
                $this->apiUsername . ':' . $this->apiPassword
            );
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            $args['file'] = curl_file_create(
                $csvFile, 'text/csv'
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            $response = json_decode(curl_exec($ch));
            var_dump($response);
        } else {
            echo $this->_html();
        }
    }
}

$clsContacts = new dmContacts();
$clsContacts->pushContacts();