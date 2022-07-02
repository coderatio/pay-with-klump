<?php /** @noinspection PhpMissingReturnTypeInspection */
/**
 * WHMCS klump Payment Gateway Module
 * @developer: Cloudinos
 * @phpVersion: 5.6+
 *
 * This module allow bsuinesses receive or accept crypto on
 * their web hosting WHMCS platforms
 *
 * If want to modify this file, do ensure you test your code prpoerly
 * before creating a Pull Request.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/ for guides
 *
 * @copyright Copyright (c) Cloudinos Limited 2022
 * @license https://github.com/Cloudinos/whmcs-klump/blob/main/LICENSE
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Creates an error message
 *
 * @param $message
 *
 * @return string
 */
function errorMessage($message) {
    return "<div 
            class='label label-lg label-danger' 
            style='max-width: 100% !important; 
            white-space: inherit'
            >$message</div>";
}

/**
 * Define LazerPay metadata
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function klump_MetaData()
{
    return [
        'DisplayName' => 'Pay Small Small - Klump',
        'APIVersion' => '1.1', // Use API Version 1.1 as recommended by WHMCS team.
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * Define LazerPay configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 * @return array
 */
function klump_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Pay with Klump',
        ],
        // a text field type allows for single line text input
        'testSecretKey' => [
            'FriendlyName' => 'Test Secret Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter test secret key',
        ],
        'testPublicKey' => [
            'FriendlyName' => 'Test Public Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter test public key',
        ],
        'liveSecretKey' => [
            'FriendlyName' => 'Live Secret Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter live secret key',
        ],
        'livePublicKey' => [
            'FriendlyName' => 'Live Public Key',
            'Type' => 'password',
            'Size' => '225',
            'Default' => '',
            'Description' => 'Enter live public key',
        ],
        'callbackUrl' => [
            'FriendlyName' => 'Callback URL',
            'Value' => "https://{$_SERVER['HTTP_HOST']}/modules/gateways/klump/verify-payment.php",
            'Type' => 'text',
            'Description' => 'This will be used as <code>redirect_url</code> as described <b><a href="https://merchant.useklump.com/settings?tab=api" target="_blank">here</a></b>.',
        ],
        'testMode' => [
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ],

    ];
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 */
function klump_link($params)
{
    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    $itemName = $params['description'];

    // Client Parameters
    $firstName = $params['clientdetails']['firstname'];
    $lastName = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];

    $txnref = 'CLDKP_' . $invoiceId . '_' . time();
    $name = $firstName . ' ' . $lastName;
    $supportedCurrencies = ['NGN'];
    $callbackUrl = $params['callbackUrl'];



    /*
     * Return an error if user selected currency isn't supported by Lazerpay/
     * @see https://docs.useklump.com/docs/getting-started
     */
    if (!in_array($currencyCode, $supportedCurrencies, false)) {
        return errorMessage("Selected($currencyCode) currency isn't supported.");
    }

    /**
     * Returns an error if amount is less than minimum loan amount by Klump
     */
    if ($amount < 25000) {
        return errorMessage('Klump is only available for amount upto N25,000');
    }

    $isTestnet = $params['testMode'] !== '';
    $publicKey = $params['testPublicKey'];
    if (!$isTestnet) {
        $publicKey = $params['livePublicKey'];
    }

    /**
     * We used input type instead of a button becauce WHMCS stype this
     * by default. There is no extra styles needed from us.
     */
    $htmlOutput = '<div id="klump__checkout"></div>';
    $htmlOutput .= '<script src="https://js.useklump.com/klump.js" defer></script>';

    $htmlOutput .= '<script>
        const paymentButton = document.getElementById("klump__checkout");
        paymentButton.addEventListener("click", function(e) {
          e.preventDefault();
          
          const payload = {
            publicKey: "' . $publicKey . '",
            data: {
                amount: "' . $amount . '",
                redirect_url: getCallbackUrlForStatus(),
                currency: "'. $currencyCode .'",
                merchant_reference: "' . $txnref . '",
                meta_data: {
                    customer: "' . $name .'",
                    email: "'. $email .'"
                },
                items: [
                    {
                        name: "'. $itemName .'",
                        unit_price: "' . $amount . '",
                        quantity: 1,
                    },
                ],
            },
            onSuccess: (data) => {
                console.log(data);
                performCallbackAction("success", data.reference)
            },
            onError: (data) => {
                console.log(data);
                //performCallbackAction("error")
            },
            onLoad: (data) => {
                
            }
          }
                
          new Klump(payload)
        });
        
        function performCallbackAction(status, kref = "") {
            window.location.href = "' . $callbackUrl . '?invoice_id='.$invoiceId.'&trxref=' . $txnref . '&kref="+ kref +"&status=" + status;
        }
        
        function getCallbackUrlForStatus(status = "init") {
            return "' . $callbackUrl . '?invoice_id='.$invoiceId.'&trxref=' . $txnref . '&status=" + status;
        }
    </script>';

    return $htmlOutput;
}

