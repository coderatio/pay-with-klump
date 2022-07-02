<?php


namespace Cloudinos\WHMCS\Klump\Services;


use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as Capsule;

define("KLUMP_MODULE_NAME", 'klump');

class Klump
{
    protected $params = [];
    public function __construct() {
        $this->params = getGatewayVariables(KLUMP_MODULE_NAME);
    }

    /*
     * Get callback secret and SystemURL to form the callback URL
     */
    public function getCallbackUrl()
    {
        return rtrim($this->getSystemUrl(), '/') . '/modules/gateways/'.KLUMP_MODULE_NAME.'/verify-payment.php';
    }

    /*
     * Get user configured API public key from database
     *
     * @return string
     */
    public function getPublicKey()
    {
        $publicKey = $this->params['testPublicKey'];
        if (!$this->isTestNet()) {
            $publicKey = $this->params['livePublicKey'];
        }

        return $publicKey;
    }

    /*
     * Get user configured API secret key from database
     *
     * @return string
     */
    public function getSecretKey()
    {
        $secretKey = $this->params['testSecretKey'];
        if (!$this->isTestNet()) {
            $secretKey = $this->params['liveSecretKey'];
        }

        return $secretKey;
    }

    /**
     * Checks if test mode is turned ON
     *
     * @return bool
     */
    public function isTestNet() {
        return $this->params['testMode'] === 'on';
    }

    /**
     * Gets invoice with currency. This is used to balance currency on
     * WHMCS and Klump.
     *
     * @param $invoiceId
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getInvoiceByIdWithCurrency($invoiceId) {
        return Capsule::table('tblinvoices')
            ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
            ->leftJoin('tblcurrencies', 'tblcurrencies.id', '=', 'tblclients.currency')
            ->select([
                'tblinvoices.invoicenum',
                'tblinvoices.id',
                'tblclients.currency',
                'tblcurrencies.code as currency_code'])
            ->where('tblinvoices.id', $invoiceId)
            ->first();
    }

    /**
     * Gets an invoice by its ID
     *
     * @param $invoiceId
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getInvoiceById($invoiceId)
    {
        return Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();
    }

    /*
     * Get URL of the WHMCS installation
     */
    public function getSystemUrl()
    {
        return $this->params['systemurl'];
    }

    /**
     * Gets the transaction with reference in the url
     *
     * @param $reference
     *
     * @return array|bool|float|int|object|string|null
     */
    public function getTransaction($reference)
    {
        try {
            return \GuzzleHttp\json_decode(
                $this->setSecretKeyClient()->request('GET', $this->getVerifyTransactionUrl($reference)
                )->getBody()
                    ->getContents()
            );
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Sets public key to be used for HTTP request
     *
     * @return \GuzzleHttp\Client
     */
    public function setPublicKeyClient()
    {
        return new Client(['headers' => ['klum-api-key' => trim($this->getPublicKey())]]);
    }

    public function setSecretKeyClient()
    {
        return new Client(['headers' => ['klump-secret-key' => trim($this->getSecretKey())]]);
    }


    /**
     * The verify URI from Lazerpay's docs
     *
     * @param $reference
     *
     * @return string
     */
    public function getVerifyTransactionUrl($reference)
    {
        return 'https://api.useklump.com/v1/transactions/'. rawurlencode($reference) .'/verify';
    }

    /**
     * Checks convert to Currency is set on WHMCS admin for Klump
     *
     * @return mixed
     */
    public function convertToIsEnabled()
    {
        return $this->params['convertto'];
    }

    /**
     * Gets an invoice URL
     *
     * @param $invoiceId
     *
     * @return string
     */
    public function getInvoiceUrl($invoiceId)
    {
        return rtrim($this->getSystemUrl(), '/') . "/viewinvoice.php?id={$invoiceId}";
    }

    /**
     * Loads invoice page
     *
     * @param $invoiceId
     */
    public function loadInvoicePage($invoiceId)
    {
        header('Location: ' . $this->getInvoiceUrl($invoiceId));
        exit();
    }

    /**
     * Loads micro config for the module.
     *
     * @throws \JsonException
     */
    public function getConfig()
    {
        return json_decode(file_get_contents(__DIR__ . './../config.json'), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Checks Klump is active
     *
     * @return mixed
     */
    public function isActive() {
        return $this->params['type'];
    }

    /**
     * Checks Klump is not active
     *
     * @return bool
     */
    public function isNotActive()
    {
        return !$this->params['type'];
    }
}