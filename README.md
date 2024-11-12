#AUTOFACTURA

<p>Autofactura utiliza peticiones de tipo SOAP por medio de peticiones de tipo CURL</p>

<code>
            curl_setopt_array($ch, [
                CURLOPT_SSL_VERIFYPEER => 1,
                CURLOPT_URL => "https://vpfe.dian.gov.co/WcfDianCustomerServices.svc",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $xml,
                CURLOPT_HTTPHEADER => [
                    "Content-type: application/soap+xml;charset=\"utf-8\"",
                    "SOAPAction: http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillAsync",
                    "Content-length: " . strlen($xml),
                    "Host: vpfe.dian.gov.co",
                    "Connection: Keep-Alive"
                ]
            ]);
</code>