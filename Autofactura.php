<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use ZipArchive;

class AutoFactura
{
public function facturar(Request $request)
{
        $hoy = Carbon::now();
        try {
            $tercero = TerceroCahors::with(['usuario', 'empresa'])->where('documento', $request->input('tercero'))->firstOrFail();
            $ultimaFactura = Factura::where('tipo', 'Venta')->where('prefijo', $request->input('formapago'))->latest('numero')->first();
        
            $numeroFactura = $ultimaFactura ? $ultimaFactura->numero + 1 : 1;
            $prefijo = $request->input('formapago');
            $resolucion = Resolucion::where('prefijo', $prefijo)->firstOrFail();
            $formapago = $prefijo === "FECR" ? "Crédito" : "Contado";
            
            $factura = new Factura([
                'descripcion' => $request->input('concepto'),
                'fecha' => $hoy,
                'placa' => $request->input('placa'),
                'valor' => $request->input('total'),
                'tipo' => "Venta",
                'year' => $hoy->year,
                'numero' => $numeroFactura,
                'prefijo' => $prefijo,
                'formapago' => $formapago,
                'terceros_id' => $tercero->id,
            ]);
        
            $productos = collect(json_decode($request->input('productos')));
            $iva = $productos->sum(fn($prod) => $prod->iva ?? 0);
            $baseiva = $productos->whereNotNull('iva')->sum('valor');
            $siniva = $productos->whereNull('iva')->sum('valor');
        
            $concatFact = "{$factura->prefijo}{$factura->numero}";
            $segCode = hash("sha384", 'YOUR-HASH-CODE' . $concatFact);
            $cufe = hash("sha384", "{$concatFact}{$hoy->format('Y-m-d')}{$hoy->format('H:i:s')}-05:00" .
                number_format($factura->valor - $iva, 2, ".", "") . '01' .
                number_format($iva, 2, ".", "") . '040.00030.00' . number_format($factura->valor, 2, ".", "") .
                'YOUR-NIT' . $tercero->documento . $resolucion->citec . '1');
        
            $qrcode = "NroFactura={$concatFact}\nNitFacturador=YOUR-NIT\nNitAdquiriente={$tercero->documento}\nFechaFactura={$hoy->format('Y-m-d')}\nValorTotalFactura={$factura->valor}\nCUFE={$cufe}\nURL=https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey={$cufe}";
        
            $vencimiento = $hoy->copy()->addMonth();
            $finMes = Carbon::now()->lastOfMonth();
            
            if ($tercero->usuario) {
                $tercero->municipio = $tercero->usuario->municipio;
                $tercero->direccion = $tercero->usuario->direccion;
                $tercero->email = $tercero->usuario->email;
            } else {
                $tercero->municipio = $tercero->empresa->municipio;
                $tercero->direccion = $tercero->empresa->direccion;
                $tercero->email = $tercero->empresa->email;
            }
        
            $xmlView = view('facturas.ublGenerica', compact('factura', 'iva', 'siniva', 'baseiva', 'hoy', 'productos', 'tercero', 'vencimiento', 'finMes', 'cufe', 'segCode', 'qrcode', 'resolucion'))->render();
        
            $storePath = storage_path("facturas/{$factura->prefijo}/{$concatFact}/");
            Storage::makeDirectory($storePath);
            $xmlView = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $xmlView;
            
            $signInvoice = new SignInvoice("{$storePath}/claves/Certificado.pfx", 'YOUR-CERT-PFX', $xmlView);
            Storage::disk('facturas')->put("{$factura->prefijo}/{$concatFact}/{$concatFact}.xml", $signInvoice->xml);
        
            $zip = new ZipArchive();
            $zip->open("{$storePath}{$concatFact}.zip", ZipArchive::CREATE);
            $zip->addFile("{$storePath}{$concatFact}.xml", "{$concatFact}.xml");
            $zip->close();
        
            $xmlPeticion = view('facturas.facturaPeticion', [
                'numfact' => "{$concatFact}.zip",
                'contenido' => base64_encode(file_get_contents("{$storePath}{$concatFact}.zip"))
            ])->render();
        
            $doc = new DOMDocument();
            $doc->loadXML('<?xml version="1.0" encoding="UTF-8"?>' . $xmlPeticion);
            
            $soapdian21 = new SOAPDIAN21("{$storePath}/claves/Certificado.pfx", "YOUR-PFX-CERT");
            $soapdian21->Action = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillAsync';
            $soapdian21->startNodes($doc->saveXML());
            $xml = $soapdian21->soap;
        
            $ch = curl_init();
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
            $response = curl_exec($ch);
            curl_close($ch);
        
            // Resto del manejo de respuesta SOAP, guardado de la factura y productos, email, etc.
            
            return json_encode(["respuesta" => "success", "msj" => $factura->id]);
            
        } catch (Exception $ex) {
            return json_encode(["respuesta" => "error", "msj" => $ex->getMessage()]);
        }
    }

}