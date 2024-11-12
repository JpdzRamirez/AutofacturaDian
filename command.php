<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoFactura extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autofactura:enviar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envío automatico de facturas async';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        
        // CONSULTAR UN REGISTRO DE ZIPKEYS GUARDADOS 👍
        $zipKey = '8b1849d4-884b-4245-9094-08e76be927a3'; // ZIP KEY PRUEBAS
        
        // Construir el mensaje SOAP para GetStatusZip
        $xml = <<<XML
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"
                        xmlns:wcf="http://wcf.dian.colombia">
            <soap:Header/>
            <soap:Body>
                <wcf:GetStatusZip>
                    <wcf:trackId>$zipKey</wcf:trackId>
                </wcf:GetStatusZip>
            </soap:Body>
        </soap:Envelope>
        XML;

        $headers = array(
            "Content-type: application/soap+xml;charset=\"utf-8\"",
            "SOAPAction: http://wcf.dian.colombia/IWcfDianCustomerServices/GetStatusZip",
            "Content-length: " . strlen($xml),
            "Host: vpfe.dian.gov.co",
            "Connection: Keep-Alive"
        );
        
        // Configuración de cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_URL, "https://vpfe.dian.gov.co/WcfDianCustomerServices.svc");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
        
        
        $response = curl_exec($ch);
        
        // Verificar si hubo un error
        if ($response === false) {
            // si falla la solicitud
        } else {

            $dom = new DOMDocument();
            $dom->loadXML($response);
        
           // Crear un DOMXPath para consultar el XML
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('s', 'http://www.w3.org/2003/05/soap-envelope');
            $xpath->registerNamespace('b', 'http://schemas.datacontract.org/2004/07/DianResponse');

            // Consultar el StatusCode
            $statusCodeNodes = $xpath->query('//b:StatusCode');
            if ($statusCodeNodes->length > 0) {
                $statusCode = $statusCodeNodes->item(0)->nodeValue;
                //obtenemos el codigo de status
                /*00 = Procesado Correctamente
                    66= NSU no encontrado
                    90 = TrackId no encontrado
                    99 = validaciones contienen errores
                    en campos mandatorios */
            } 
        }
        
        // Cerrar la conexión cURL
        curl_close($ch);
        
        
    }
}
